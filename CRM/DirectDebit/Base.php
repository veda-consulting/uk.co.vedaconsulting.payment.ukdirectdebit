<?php
/**
 * Class CRM_DirectDebit_Base
 *
 * Base class for classes that interact with Xero using push and pull methods.
 */
class CRM_DirectDebit_Base
{
  protected static $_apiUrl = 'https://secure.ddprocessing.co.uk';

  /**
   * Return API URL with base prepended
   * @param string $path
   * @param string $request
   * @return string
   */
  public static function getApiUrl($path = '', $request = '') {
    return self::$_apiUrl.$path.'?'.$request;
  }
  /**
   * Generate a Direct Debit Reference (BACS reference)
   * @return string
   */
  public static function getDDIReference() {
    $tempDDIReference = self::rand_str(16);

    CRM_Core_DAO::executeQuery("
        INSERT INTO civicrm_direct_debit
        (ddi_reference, created)
        VALUES
        (%1, NOW())
        ", array(1 => array((string)$tempDDIReference , 'String'))
    );

    // Now get the ID for the record we've just created and create a sequenced DDI Reference Number
    $selectSql  = " SELECT id ";
    $selectSql .= " FROM civicrm_direct_debit cdd ";
    $selectSql .= " WHERE cdd.ddi_reference = %1 ";
    $selectParams  = array( 1 => array( $tempDDIReference , 'String' ) );
    $dao = CRM_Core_DAO::executeQuery( $selectSql, $selectParams );
    $dao->fetch();

    $directDebitId = $dao->id;

    // Replace the DDI Reference Number with our new unique sequenced version
    $transactionPrefix = CRM_DirectDebit_Base::getTransactionPrefix();
    $DDIReference      = $transactionPrefix . sprintf( "%08s", $directDebitId );

    $updateSql  = " UPDATE civicrm_direct_debit cdd ";
    $updateSql .= " SET cdd.ddi_reference = %0 ";
    $updateSql .= " WHERE cdd.id = %1 ";

    $updateParams = array( array( (string) $DDIReference , 'String' ),
      array( (int)    $directDebitId, 'Int'    ),
    );

    CRM_Core_DAO::executeQuery( $updateSql, $updateParams );

    return $DDIReference;
  }

  /**
   * Check if direct debit submission is completed
   * @param $DDIReference
   * @return bool
   */
  static function isDDSubmissionComplete( $DDIReference ) {
    $isComplete = false;

    $selectSql     =  " SELECT complete_flag ";
    $selectSql     .= " FROM civicrm_direct_debit cdd ";
    $selectSql     .= " WHERE cdd.ddi_reference = %1 ";
    $selectParams  = array( 1 => array( $DDIReference , 'String' ) );
    $dao           = CRM_Core_DAO::executeQuery( $selectSql, $selectParams );

    if ( $dao->fetch() ) {
      if ( $dao->complete_flag == 1 ) {
        $isComplete = true;
      }
    }
    return $isComplete;
  }

  /**
   * Called after contribution page has been completed
   * Main purpose is to tidy the contribution
   * And to setup the relevant Direct Debit Mandate Information
   *
   * @param $objects
   */
  static function completeDirectDebitSetup( $objects )  {
    $params['contactID'] = $objects['contact']->id;
    $params['trxn_id'] = $objects['contributionRecur']->trxn_id;

    // Get the preferred communication method
    $sql = <<<EOF
    SELECT confirmation_method
    FROM   civicrm_direct_debit
    WHERE  ddi_reference = %0
EOF;

    $params['confirmation_method'] = CRM_Core_DAO::singleValueQuery( $sql, array( array( $params['trxn_id'], 'String' ) ) );

    // Create an activity to indicate Direct Debit Sign up
    $activityID = CRM_DirectDebit_Base::createDDSignUpActivity($params);

    // Set the DD Record to be complete
    $sql = <<<EOF
            UPDATE civicrm_direct_debit
            SET    complete_flag = 1
            WHERE  ddi_reference = %0;
EOF;

    CRM_Core_DAO::executeQuery($sql, array(array((string)$params['trxn_id'], 'String'))
    );
  }

  /**
   *   Send a post request with cURL
   *
   * @param $url URL to send request to
   * @param $data POST data to send (in URL encoded Key=value pairs)
   * @param $username
   * @param $password
   * @param $path
   * @return mixed
   */
  public static function requestPost($url, $data, $username, $password, $path){
    // Set a one-minute timeout for this script
    set_time_limit(160);

    $options = array(
      CURLOPT_RETURNTRANSFER => true, // return web page
      CURLOPT_HEADER => false, // don't return headers
      CURLOPT_POST => true,
      CURLOPT_USERPWD => $username . ':' . $password,
      CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
      CURLOPT_HTTPHEADER => array("Accept: application/xml"),
      CURLOPT_USERAGENT => "CiviCRM PHP DD Client", // Let SmartDebit see who we are
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
    );

    $session = curl_init( $url . $path);
    curl_setopt_array( $session, $options );

    // Tell curl that this is the body of the POST
    curl_setopt ($session, CURLOPT_POSTFIELDS, $data );

    // $output contains the output string
    $output = curl_exec($session);
    $header = curl_getinfo($session);

    //Store the raw response for later as it's useful to see for integration and understanding
    $_SESSION["rawresponse"] = $output;

    if(curl_errno($session)) {
      $resultsArray["Status"] = "FAIL";
      $resultsArray['StatusDetail'] = curl_error($session);
    }
    else {
      // Results are XML so turn this into a PHP Array
      $resultsArray = json_decode(json_encode((array) simplexml_load_string($output)),1);

      // Determine if the call failed or not
      switch ($header["http_code"]) {
        case 200:
          $resultsArray["Status"] = "OK";
          break;
        default:
          $resultsArray["Status"] = "INVALID";
      }
    }
    // Return the output
    return $resultsArray;
  }

  /**
   * Calculate the earliest possible collection date based on todays date plus the collection interval setting.
   * @param $collectionDay
   * @return DateTime
   */
  static function firstCollectionDate($collectionDay) {
    // Initialise date objects with today's date
    $today                    = new DateTime();
    $earliestCollectionDate   = new DateTime();
    $collectionDateThisMonth  = new DateTime();
    $collectionDateNextMonth  = new DateTime();
    $collectionDateMonthAfter = new DateTime();
    $collectionInterval = uk_direct_debit_civicrm_getSetting('collection_interval');

    // Calculate earliest possible collection date
    $earliestCollectionDate->add(new DateInterval( 'P'.$collectionInterval.'D' ));

    // Get the current year, month and next month to create the 2 potential collection dates
    $todaysMonth = $today->format('m');
    $nextMonth   = $today->format('m') + 1;
    $monthAfter  = $today->format('m') + 2;
    $todaysYear  = $today->format('Y');

    $collectionDateThisMonth->setDate($todaysYear, $todaysMonth, $collectionDay);
    $collectionDateNextMonth->setDate($todaysYear, $nextMonth, $collectionDay);
    $collectionDateMonthAfter->setDate($todaysYear, $monthAfter, $collectionDay);

    // Calculate first collection date
    if ($earliestCollectionDate > $collectionDateNextMonth) {
      // Month after next
      return $collectionDateMonthAfter;
    }
    elseif ($earliestCollectionDate > $collectionDateThisMonth) {
      // Next Month
      return $collectionDateNextMonth;
    }
    else {
      // This month
      return $collectionDateThisMonth;
    }
  }

  /**
   * Format collection day like 1st, 2nd, 3rd, 4th etc.
   * @param $collectionDay
   * @return string
   */
  static function formatPreferredCollectionDay( $collectionDay ) {
    $ends = array( 'th'
    , 'st'
    , 'nd'
    , 'rd'
    , 'th'
    , 'th'
    , 'th'
    , 'th'
    , 'th'
    , 'th'
    );
    if ( ( $collectionDay%100 ) >= 11 && ( $collectionDay%100 ) <= 13 )
      $abbreviation = $collectionDay . 'th';
    else
      $abbreviation = $collectionDay . $ends[$collectionDay % 10];

    return $abbreviation;
  }

  /**
   * Function will return the possible array of collection days with formatted label
   */
  static function getCollectionDaysOptions() {
    $intervalDate = new DateTime();
    $interval     = uk_direct_debit_civicrm_getSetting('collection_interval');

    $intervalDate->modify( "+$interval day" );
    $intervalDay = $intervalDate->format( 'd' );

    $collectionDays = uk_direct_debit_civicrm_getSetting('collection_days');

    // Split the array
    $tempCollectionDaysArray  = explode( ',', $collectionDays );
    $earlyCollectionDaysArray = array();
    $lateCollectionDaysArray  = array();

    // Build 2 arrays around next collection date
    foreach( $tempCollectionDaysArray as $key => $value ){
      if ( $value >= $intervalDay ) {
        $earlyCollectionDaysArray[] = $value;
      }
      else {
        $lateCollectionDaysArray[]  = $value;
      }
    }
    // Merge arrays for select list
    $allCollectionDays = array_merge( $earlyCollectionDaysArray, $lateCollectionDaysArray );

    // Loop through and format each label
    foreach( $allCollectionDays as $key => $value ){
      $collectionDaysArray[$value] = self::formatPreferredCollectionDay( $value );
    }
    return $collectionDaysArray;
  }

  /**
   * Create a Direct Debit Sign Up Activity for contact
   * @param $params
   * @return mixed
   */
  static function createDDSignUpActivity( &$params ) {
    if ( $params['confirmation_method'] == 'POST' ) {
      $activityTypeLetterID = CRM_DirectDebit_Base::getActivityTypeLetter();
      $activityLetterParams = array(
        'source_contact_id'  => $params['contactID'],
        'target_contact_id'  => $params['contactID'],
        'activity_type_id'   => $activityTypeLetterID,
        'subject'            => sprintf("Direct Debit Sign Up, Mandate ID : %s", $params['trxn_id'] ),
        'activity_date_time' => date( 'YmdHis' ),
        'status_id'          => 1,
        'version'            => 3
      );

      $resultLetter = civicrm_api( 'activity'
        , 'create'
        , $activityLetterParams
      );
    }

    $activityTypeID = CRM_DirectDebit_Base::getActivityType();
    $activityParams = array(
      'source_contact_id'  => $params['contactID'],
      'target_contact_id'  => $params['contactID'],
      'activity_type_id'   => $activityTypeID,
      'subject'            => sprintf("Direct Debit Sign Up, Mandate ID : %s", $params['trxn_id'] ) ,
      'activity_date_time' => date( 'YmdHis' ),
      'status_id'          => 2,
      'version'            => 3
    );

    $result     = civicrm_api( 'activity','create', $activityParams );
    $activityID = $result['id'];

    return $activityID;
  }

  // FIXME: This function is not used anywhere? Remove?
  /**
   * Send email receipt for direct debit signup.
   * @param $type
   * @param $contactID
   * @param $pageID
   * @param $recur
   * @param bool $autoRenewMembership
   */
  function directDebitSignUpNofify( $type, $contactID, $pageID, $recur, $autoRenewMembership = FALSE ) {
    $value = array();
    if ( $pageID ) {
      CRM_Core_DAO::commonRetrieveAll( 'CRM_Contribute_DAO_ContributionPage'
        , 'id'
        , $pageID
        , $value
        , array( 'title'
        , 'is_email_receipt'
        , 'receipt_from_name'
        , 'receipt_from_email'
        , 'cc_receipt'
        , 'bcc_receipt'
        )
      );
    }

    $isEmailReceipt = CRM_Utils_Array::value( 'is_email_receipt', $value[$pageID] );
    $isOfflineRecur = FALSE;
    if ( !$pageID && $recur->id ) {
      $isOfflineRecur = TRUE;
    }
    if ( $isEmailReceipt || $isOfflineRecur ) {
      if ( $pageID ) {
        $receiptFrom = sprintf('"%s" <%s>'
          , CRM_Utils_Array::value( 'receipt_from_name', $value[$pageID] )
          , $value[$pageID]['receipt_from_email']
        );

        $receiptFromName  = $value[$pageID]['receipt_from_name'];
        $receiptFromEmail = $value[$pageID]['receipt_from_email'];
      }
      else {
        $domainValues     = CRM_Core_BAO_Domain::getNameAndEmail();
        $receiptFrom      = "$domainValues[0] <$domainValues[1]>";
        $receiptFromName  = $domainValues[0];
        $receiptFromEmail = $domainValues[1];
      }

      list( $displayName, $email ) = CRM_Contact_BAO_Contact_Location::getEmailDetails( $contactID, FALSE );
      $templatesParams = array( 'groupName' => 'msg_tpl_workflow_contribution',
        'valueName' => 'contribution_recurring_notify',
        'contactId' => $contactID,
        'tplParams' => array( 'recur_frequency_interval' => $recur->frequency_interval,
          'recur_frequency_unit'     => $recur->frequency_unit,
          'recur_installments'       => $recur->installments,
          'recur_start_date'         => $recur->start_date,
          'recur_end_date'           => $recur->end_date,
          'recur_amount'             => $recur->amount,
          'recur_txnType'            => $type,
          'displayName'              => $displayName,
          'receipt_from_name'        => $receiptFromName,
          'receipt_from_email'       => $receiptFromEmail,
          'auto_renew_membership'    => $autoRenewMembership,
        ),
        'from'      => $receiptFrom,
        'toName'    => $displayName,
        'toEmail'   => $email
      );

      list( $sent, $subject, $message, $html ) = CRM_Core_BAO_MessageTemplates::sendTemplate( $templatesParams );

      if ( !$sent ) {
        CRM_Core_Error::debug_log_message('UK Direct Debit Failure: mail not sent for recurring notification for contactID: '.$contactID);
      }
    }
  }

  // FIXME: This function not used anywhere? Remove?
  /**
   * Get confirmation template
   * @return mixed
   */
  static function getDDConfirmationTemplate() {
    $default_template_name    = "direct_debit_confirmation";
    $default_template_sql     = "SELECT * FROM civicrm_msg_template mt WHERE mt.msg_title = %1";
    $default_template_params  = array( 1 => array( $default_template_name , 'String' ));
    $default_template_dao     = CRM_Core_DAO::executeQuery( $default_template_sql, $default_template_params );
    $default_template_dao->fetch();
    return $default_template_dao->msg_html;
  }

  private static function rand_str( $len )
  {
    // The alphabet the random string consists of
    $abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    // The default length the random key should have
    $defaultLength = 3;

    // Ensure $len is a valid number
    // Should be less than or equal to strlen( $abc ) but at least $defaultLength
    $len = max( min( intval( $len ), strlen( $abc )), $defaultLength );

    // Return snippet of random string as random string
    return substr( str_shuffle( $abc ), 0, $len );
  }

  static function getCompanyName() {
    $domain = CRM_Core_BAO_Domain::getDomain();
    return $domain->name;
  }

  static function getCompanyAddress() {
    $companyAddress = array();

    $domain = CRM_Core_BAO_Domain::getDomain();
    $domainLoc = $domain->getLocationValues();

    $companyAddress['company_name'] = $domain->name;
    if (!empty($domainLoc['address'])) {
      $companyAddress['address1']     = $domainLoc['address'][1]['street_address'];
      if (array_key_exists('supplemental_address_1', $domainLoc['address'][1])) {
        $companyAddress['address2']     = $domainLoc['address'][1]['supplemental_address_1'];
      }
      if (array_key_exists('supplemental_address_2', $domainLoc['address'][1])) {
        $companyAddress['address3']     = $domainLoc['address'][1]['supplemental_address_2'];
      }
      $companyAddress['town']         = $domainLoc['address'][1]['city'];
      $companyAddress['postcode']     = $domainLoc['address'][1]['postal_code'];
      if (array_key_exists('county_id', $domainLoc['address'][1])) {
        $companyAddress['county']       = CRM_Core_PseudoConstant::county($domainLoc['address'][1]['county_id']);
      }
      $companyAddress['country_id']   = CRM_Core_PseudoConstant::country($domainLoc['address'][1]['country_id']);
    }

    return $companyAddress;
  }

  static function getActivityType() {
    return uk_direct_debit_civicrm_getSetting('activity_type');
  }

  static function getActivityTypeLetter() {
    return uk_direct_debit_civicrm_getSetting('activity_type_letter');
  }

  static function getTelephoneNumber() {
    return uk_direct_debit_civicrm_getSetting('telephone_number');
  }

  static function getEmailAddress() {
    return uk_direct_debit_civicrm_getSetting('email_address');
  }

  static function getDomainName() {
    return uk_direct_debit_civicrm_getSetting('domain_name');
  }

  static function getTransactionPrefix() {
    return uk_direct_debit_civicrm_getSetting('transaction_prefix');
  }

  /**
   * Function will return the SUN number broken down into individual characters passed as an array
   */
  static function getSUNParts() {
    return str_split(self::getSUN());
  }

  /**
   * Function will return the SUN number broken down into individual characters passed as an array
   */
  static function getSUN() {
    return uk_direct_debit_civicrm_getSetting('service_user_number');
  }

  /**
   * Function will return the Payment instrument to be used by DD payment processor
   */
  static function getDefaultPaymentInstrumentID() {
    return uk_direct_debit_civicrm_getSetting('payment_instrument_id');
  }

  /**
   * Function will return the default Financial Type to be used by DD payment processor
   */
  static function getDefaultFinancialTypeID() {
    return uk_direct_debit_civicrm_getSetting('financial_type');
  }

  static function getCountry( $country_id ) {
    $country = null;
    if ( !empty( $country_id ) ) {
      $sql    = "SELECT name FROM civicrm_country WHERE id = %1";
      $params = array( 1 => array( $country_id , 'Integer' ) );
      $dao    = CRM_Core_DAO::executeQuery( $sql, $params );
      $dao->fetch();
      $country = $dao->name;
    }
    return $country;
  }

  static function getStateProvince( $state_province_id ) {
    $stateProvince = null;
    if ( !empty( $state_province_id ) ) {
      $sql    = "SELECT name FROM civicrm_state_province WHERE id = %1";
      $params = array( 1 => array( $state_province_id , 'Integer' ) );
      $dao    = CRM_Core_DAO::executeQuery( $sql, $params );
      $dao->fetch();
      $stateProvince = $dao->name;
    }
    return $stateProvince;
  }

  /**
   * Translate Smart Debit Frequency Unit/Factor to CiviCRM frequency unit/interval (eg. W,1 = day,7)
   * @param $sdFrequencyUnit
   * @param $sdFrequencyFactor
   * @return array ($civicrm_frequency_unit, $civicrm_frequency_interval)
   */
  static function translateSmartDebitFrequencytoCiviCRM($sdFrequencyUnit, $sdFrequencyFactor) {
    if (empty($sdFrequencyFactor)) {
      $sdFrequencyFactor = 1;
    }
    switch ($sdFrequencyUnit) {
      case 'W':
        $unit = 'day';
        $interval = $sdFrequencyFactor * 7;
        break;
      case 'M':
        $unit = 'month';
        $interval = $sdFrequencyFactor;
        break;
      case 'Q':
        $unit = 'month';
        $interval = $sdFrequencyFactor*3;
        break;
      case 'Y':
      default:
        $unit = 'year';
        $interval = $sdFrequencyFactor;
    }
    return array ($unit, $interval);
  }

  /**
   * Get array of valid frequency Units (for display in select etc)
   * @return array
   */
  static function getSmartDebitFrequencyUnits() {
    $frequencyUnits = array(
      'W'  => 'Weekly',
      'M'  => 'Monthly',
      'Q'  => 'Quarterly',
      'Y'  => 'Annually'
    );
    return $frequencyUnits;
  }

  /**
   * * Get array of valid frequency Intervals (for display in select etc)
   * @return array
   */
  static function getCiviCRMFrequencyIntervals() {
    $frequencyIntervals = array(
      1  => '1',
      2  => '2',
      3  => '3',
      4  => '4',
      5  => '5',
      6  => '6',
      7  => '7',
      8  => '8',
      9  => '9',
      10 => '10',
      11 => '11',
      12 => '12'
    );
    return $frequencyIntervals;
  }

  /**
   * Create a new recurring contribution for the direct debit instruction we set up.
   * @param $recurParams
   */
  static function createRecurContribution($recurParams) {
    // Mandatory Parameters
    // Amount
    if (empty($recurParams['amount'])) {
      CRM_Core_Error::debug_log_message('SmartDebit createRecurContribution: ERROR must specify amount!');
      return FALSE;
    }
    else {
      // Make sure it's properly formatted (ie remove symbols etc)
      $recurParams['amount'] = preg_replace("/([^0-9\\.])/i", "", $recurParams['amount']);
    }
    if (empty($recurParams['contact_id'])) {
      CRM_Core_Error::debug_log_message('SmartDebit createRecurContribution: ERROR must specify contact_id!');
      return FALSE;
    }

    // Optional parameters
    // Set default processor_id
    if (empty($recurParams['payment_processor_id'])) {
      $recurParams['payment_processor_id'] = CRM_Core_Payment_Smartdebitdd::getSmartDebitPaymentProcessorID();
    }
    // Set status
    if (empty($recurParams['contribution_status_id'])) {
      // Default to "In Progress" as we assume setup was successful at this point
      $recurParams['contribution_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');
    }
    // Set unit/interval
    if (isset($recurParams['frequency_type'])) {
      if (empty($recurParams['frequency_factor'])) {
        $recurParams['frequency_factor'] = 1;
      }
      // Convert SmartDebit frequency params if we have them
      list($recurParams['frequency_unit'], $recurParams['frequency_interval']) = CRM_DirectDebit_Base::translateSmartDebitFrequencytoCiviCRM($recurParams['frequency_type'], $recurParams['frequency_factor']);
    }
    if (empty($recurParams['frequency_unit']) && empty($recurParams['frequency_interval'])) {
      // Default to 1 year if undefined
      $recurParams['frequency_unit'] = 'year';
      $recurParams['frequency_interval'] = 1;
    }
    // Default to today for creation date
    if (empty($recurParams['create_date'])) {
      $recurParams['create_date'] = date('YmdHis');
    }
    else {
      $recurParams['create_date'] = CRM_Utils_Date::processDate($recurParams['create_date']);
    }
    // Default to today for modified date
    if (empty($recurParams['modified_date'])) {
      $recurParams['modified_date'] = date('YmdHis');
    }
    else {
      $recurParams['modified_date'] = CRM_Utils_Date::processDate($recurParams['modified_date']);
    }
    // Default to today for modified date
    if (empty($recurParams['start_date'])) {
      $recurParams['start_date'] = date('YmdHis');
    }
    else {
      $recurParams['start_date'] = CRM_Utils_Date::processDate($recurParams['start_date']);
    }
    // Cycle day defaults to day of start date
    if (empty($recurParams['cycle_day'])) {
      $recurParams['cycle_day'] = date('j', strtotime($recurParams['start_date'])); //Day of the month without leading zeros
    }
    // processor id (match trxn_id)
    if (empty($recurParams['processor_id'])) {
      if (!empty($recurParams['reference_number'])) {
        $recurParams['processor_id'] = $recurParams['reference_number'];
      }
      elseif (!empty($recurParams['trxn_id'])) {
        $recurParams['processor_id'] = $recurParams['trxn_id'];
      }
      else {
        $recurParams['processor_id'] = '';
      }
    }
    // trxn_id (match processor id)
    if (empty($recurParams['trxn_id'])) {
      if (!empty($recurParams['processor_id'])) {
        $recurParams['trxn_id'] = $recurParams['processor_id'];
      }
      else {
        $recurParams['trxn_id'] = '';
      }
    }
    // Default value for payment_instrument id (payment method, eg. "Direct Debit")
    if (empty($recurParams['payment_instrument_id'])){
      $recurParams['payment_instrument_id'] = CRM_DirectDebit_Base::getDefaultPaymentInstrumentID();
    }
    // Default value for financial_type_id (eg. "Member dues")
    if (empty($recurParams['financial_type_id'])){
      $recurParams['financial_type_id'] = CRM_DirectDebit_Base::getDefaultFinancialTypeID();
    }
    // Default currency
    if (empty($recurParams['currency'])) {
      $config = CRM_Core_Config::singleton();
      $recurParams['currency'] = $config->defaultCurrency;
    }
    // Invoice ID
    if (empty($recurParams['invoice_id'])) {
      $recurParams['invoice_id'] = md5(uniqid(rand(), TRUE ));
    }

    // Build recur params
    $recurParams = array(
      'contact_id' =>  $recurParams['contact_id'],
      'create_date' => $recurParams['create_date'],
      'modified_date' => $recurParams['modified_date'],
      'start_date' => $recurParams['start_date'],
      'amount' => $recurParams['amount'],
      'frequency_unit' => $recurParams['frequency_unit'],
      'frequency_interval' => $recurParams['frequency_interval'],
      'payment_processor_id' => $recurParams['payment_processor_id'],
      'contribution_status_id'=> $recurParams['contribution_status_id'],
      'trxn_id'	=> $recurParams['trxn_id'],
      'financial_type_id'	=> $recurParams['financial_type_id'],
      'auto_renew' => '1', // Make auto renew
      'cycle_day' => $recurParams['cycle_day'],
      'currency' => $recurParams['currency'],
      'processor_id' => $recurParams['processor_id'],
      'payment_instrument_id' => $recurParams['payment_instrument_id'],
      'invoice_id' => $recurParams['invoice_id'],
    );
    // Create the recurring contribution

    $result = civicrm_api3('ContributionRecur', 'create', $recurParams);
    return array_merge($result, $recurParams);
  }

  /**
   * Create a new recurring contribution for the direct debit instruction we set up.
   * @param $recurParams
   * @return object
   */
  static function createContribution($recurParams) {
    // Mandatory Parameters
    // Amount
    if (empty($recurParams['total_amount'])) {
      CRM_Core_Error::debug_log_message('SmartDebit createRecurContribution: ERROR must specify amount!');
      return FALSE;
    }
    else {
      // Make sure it's properly formatted (ie remove symbols etc)
      $recurParams['total_amount'] = preg_replace("/([^0-9\\.])/i", "", $recurParams['total_amount']);
    }
    if (empty($recurParams['contact_id'])) {
      CRM_Core_Error::debug_log_message('SmartDebit createRecurContribution: ERROR must specify contact_id!');
      return FALSE;
    }

    // Optional parameters
    // Set default processor_id
    if (empty($recurParams['payment_processor_id'])) {
      $recurParams['payment_processor_id'] = CRM_Core_Payment_Smartdebitdd::getSmartDebitPaymentProcessorID();
    }
    // Set status
    if (empty($recurParams['contribution_status_id'])) {
      // Default to "In Progress" as we assume setup was successful at this point
      $recurParams['contribution_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');
    }
    // Default to today for modified date
    if (empty($recurParams['receive_date'])) {
      $recurParams['receive_date'] = date('YmdHis');
    }
    else {
      $recurParams['receive_date'] = CRM_Utils_Date::processDate($recurParams['receive_date']);
    }
    // processor id (match trxn_id)
    if (empty($recurParams['processor_id'])) {
      if (!empty($recurParams['reference_number'])) {
        $recurParams['processor_id'] = $recurParams['reference_number'];
      }
      elseif (!empty($recurParams['trxn_id'])) {
        $recurParams['processor_id'] = $recurParams['trxn_id'];
      }
      else {
        $recurParams['processor_id'] = '';
      }
    }
    // trxn_id (match processor id)
    if (empty($recurParams['trxn_id'])) {
      if (!empty($recurParams['processor_id'])) {
        $recurParams['trxn_id'] = $recurParams['processor_id'];
      }
      else {
        $recurParams['trxn_id'] = '';
      }
    }
    // Default value for payment_instrument id (payment method, eg. "Direct Debit")
    if (empty($recurParams['payment_instrument_id'])){
      $recurParams['payment_instrument_id'] = CRM_DirectDebit_Base::getDefaultPaymentInstrumentID();
    }
    // Default value for financial_type_id (eg. "Member dues")
    if (empty($recurParams['financial_type_id'])){
      $recurParams['financial_type_id'] = CRM_DirectDebit_Base::getDefaultFinancialTypeID();
    }
    // Default currency
    if (empty($recurParams['currency'])) {
      $config = CRM_Core_Config::singleton();
      $recurParams['currency'] = $config->defaultCurrency;
    }
    // Invoice ID
    if (empty($recurParams['invoice_id'])) {
      $recurParams['invoice_id'] = md5(uniqid(rand(), TRUE ));
    }

    // Build recur params
    $contributionParams = array(
      'contact_id' =>  $recurParams['contact_id'],
      'receive_date' => $recurParams['receive_date'],
      'total_amount' => $recurParams['total_amount'],
      'payment_processor_id' => $recurParams['payment_processor_id'],
      'contribution_status_id'=> $recurParams['contribution_status_id'],
      'trxn_id'	=> $recurParams['trxn_id'],
      'financial_type_id'	=> $recurParams['financial_type_id'],
      'currency' => $recurParams['currency'],
      'processor_id' => $recurParams['processor_id'],
      'payment_instrument_id' => $recurParams['payment_instrument_id'],
      'invoice_id' => $recurParams['invoice_id'],
    );
    if (!empty($recurParams['contribution_id'])) {
      $contributionParams['contribution_id'] = $recurParams['contribution_id'];
    }
    if (!empty($recurParams['contribution_recur_id'])) {
      $contributionParams['contribution_recur_id'] = $recurParams['contribution_recur_id'];
    }

    // Create/Update the contribution
    $result = civicrm_api3('Contribution', 'create', $contributionParams);
    return array_merge($result, $contributionParams);
  }
}
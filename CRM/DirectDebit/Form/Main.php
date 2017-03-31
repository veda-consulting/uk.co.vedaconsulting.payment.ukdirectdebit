<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

/**
 * This class generates form components for processing Event
 *
 */
require_once 'CRM/Core/Form.php';

class CRM_DirectDebit_Form_Main extends CRM_Core_Form
{
  function rand_str( $len )
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

  function getDDIReference() {

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
    $transactionPrefix = CRM_DirectDebit_Form_Main::getTransactionPrefix();
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

  function getEmailAddress() {
    return uk_direct_debit_civicrm_getSetting('email_address');
  }

  static function getDomainName() {
    return uk_direct_debit_civicrm_getSetting('domain_name');
  }

  static function getTransactionPrefix() {
    return uk_direct_debit_civicrm_getSetting('transaction_prefix');
  }

  static function getAutoRenewMembership() {
    return uk_direct_debit_civicrm_getSetting('auto_renew_membership');
  }

  function getCountry( $country_id ) {
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

  function getStateProvince( $state_province_id ) {
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

  /** create all fields needed for direct debit transaction
   *
   * @return void
   * @access public
   */
  function setDirectDebitFields( &$form ) {

    //   CRM_Core_Payment_Form::_setPaymentFields($form);

    $form->_paymentFields['account_holder'] = array(
      'htmlType'    => 'text',
      'name'        => 'account_holder',
      'title'       => ts( 'Account Holder' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 18
      , 'autocomplete' => 'on'
      ),
      'is_required' => TRUE
    );

    //e.g. IBAN can have maxlength of 34 digits
    $form->_paymentFields['bank_account_number'] = array(
      'htmlType'    => 'text',
      'name'        => 'bank_account_number',
      'title'       => ts( 'Bank Account Number' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 34
      , 'autocomplete' => 'off'
      ),
      'is_required' => TRUE
    );

    //e.g. SWIFT-BIC can have maxlength of 11 digits
    $form->_paymentFields['bank_identification_number'] = array(
      'htmlType'    => 'text',
      'name'        => 'bank_identification_number',
      'title'       => ts( 'Sort Code' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 11
      , 'autocomplete' => 'off'
      ),
      'is_required' => TRUE
    );

    $form->_paymentFields['bank_name'] = array(
      'htmlType'    => 'text',
      'name'        => 'bank_name',
      'title'       => ts( 'Bank Name' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 64
      , 'autocomplete' => 'off'
      ),
      'is_required' => TRUE
    );

    // Get the collection days options
    $collectionDaysArray = CRM_DirectDebit_Form_Main::getCollectionDaysOptions();

    $form->_paymentFields['preferred_collection_day'] = array(
      'htmlType'    => 'select',
      'name'        => 'preferred_collection_day',
      'title'       => ts( 'Preferred Collection Day' ),
      'cc_field'    => TRUE,
      'attributes'  => $collectionDaysArray, // array('1' => '1st', '8' => '8th', '21' => '21st'),
      'is_required' => TRUE
    );

    $form->_paymentFields['confirmation_method'] = array(
      'htmlType'    => 'select',
      'name'        => 'confirmation_method',
      'title'       => ts( 'Confirm By' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'EMAIL' => 'Email'
      , 'POST' => 'Post'
      ),
      'is_required' => TRUE
    );

    $form->_paymentFields['payer_confirmation'] = array(
      'htmlType'    => 'checkbox',
      'name'        => 'payer_confirmation',
      'title'       => ts( 'Please confirm that you are the account holder and only person required to authorise Direct Debits from this account' ),
      'cc_field'    => TRUE,
      'is_required' => TRUE
    );

    $form->_paymentFields['ddi_reference'] = array(
      'htmlType'    => 'hidden',
      'name'        => 'ddi_reference',
      'title'       => ts('DDI Reference'),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 64
      , 'autocomplete' => 'off'
      ),
      'is_required' => FALSE,
      'default'     => 'hello'
    );

    $telephoneNumber = self::getTelephoneNumber();
    $form->assign( 'telephoneNumber', $telephoneNumber );

    $companyName = self::getCompanyName();
    $form->assign( 'companyName', $companyName );
  }

  /**
   * Function to add all the direct debit fields
   *
   * @return None
   * @access public
   */
  function buildDirectDebit( &$form, $useRequired = FALSE ) {
    if ( $form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_FORM ) {
      self::setDirectDebitFields( $form );
      foreach ( $form->_paymentFields as $name => $field ) {
        if ( isset($field['cc_field'] ) &&
          $field['cc_field']
        ) {
          if ($field['htmlType'] == 'chainSelect') {
            $form->addChainSelect($field['name'], array('required' => $useRequired && $field['is_required']));
          }
          else {
            $form->add( $field['htmlType'],
              $field['name'],
              $field['title'],
              CRM_Utils_Array::value('attributes', $field),
              $useRequired ? $field['is_required'] : FALSE
            );
          }
        }
      }

      $form->addRule( 'bank_identification_number',
        ts( 'Please enter a valid Bank Identification Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );

      $form->addRule( 'bank_account_number',
        ts( 'Please enter a valid Bank Account Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );
    }

    if ( $form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_BUTTON ) {
      $form->_expressButtonName = $form->getButtonName( $form->buttonType(), 'express' );
      $form->add( 'image',
        $form->_expressButtonName,
        $form->_paymentProcessor['url_button'],
        array( 'class' => 'form-submit' )
      );
    }

    $defaults['ddi_reference'] = self::getDDIReference();
    $form->setDefaults($defaults);
  }

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

  /*
   * Function will return the SUN number broken down into individual characters passed as an array
   */
  static function getSUNParts() {
    return str_split( self::getSUN() );
  }

  /*
   * Function will return the SUN number broken down into individual characters passed as an array
   */
  static function getSUN() {
    return uk_direct_debit_civicrm_getSetting('service_user_number');
  }

  /*
   * Function will return the Payment instrument to be used by DD payment processor
   */
  static function getDDPaymentInstrumentID() {
    return uk_direct_debit_civicrm_getSetting('payment_instrument_id');
  }

  /*
 * Function will return the possible array of collection days with formatted label
 */
  function getCollectionDaysOptions() {
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

  /*
   * Called after contribution page has been completed
   * Main purpose is to tidy the contribution
   * And to setup the relevant Direct Debit Mandate Information
   */
  // Amended as now being called via the IPN and not the doDirectPayment
  // function completeDirectDebitSetup( $response, &$params )  {
  function completeDirectDebitSetup( $objects )  {

    CRM_Core_Error::debug_log_message( 'CRM_DirectDebit_Form_Main.completeDirectDebitSetup $params=' . print_r( $objects, true ) );

    require_once 'api/api.php';

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
    $activityID = self::createDDSignUpActivity( $params );

    // Set the DD Record to be complete
    $sql = <<<EOF
            UPDATE civicrm_direct_debit
            SET    complete_flag = 1
            WHERE  ddi_reference = %0;
EOF;

    CRM_Core_DAO::executeQuery( $sql
      , array( array( (string) $params['trxn_id'],'String' ) )
    );

    CRM_Core_Error::debug_log_message('CRM_DirectDebit_Form_Main: Completed completeDirectDebitSetup Function.');
  }

  function createDDSignUpActivity( &$params ) {

    require_once 'api/api.php';

    if ( $params['confirmation_method'] == 'POST' ) {
      $activityTypeLetterID = self::getActivityTypeLetter();

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

    $activityTypeID = self::getActivityType();

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

  static function firstCollectionDate( $collectionDay, $startDate ) {
    // Initialise date objects with today's date
    $today                    = new DateTime();
    $todayPlusDateInterval    = new DateTime();
    $collectionDateThisMonth  = new DateTime();
    $collectionDateNextMonth  = new DateTime();
    $collectionDateMonthAfter = new DateTime();

    $interval = uk_direct_debit_civicrm_getSetting('collection_interval');

    // If we are not starting from today, then reset today's date and interval date
    if ( !empty( $startDate ) ) {
      $today                 = DateTime::createFromFormat( 'Y-m-d', $startDate );
      $todayPlusDateInterval = DateTime::createFromFormat( 'Y-m-d', $startDate );
    }

    // Add the day interval to create a date interval days from today's date
    $dateInterval  = 'P' . $interval . 'D';
    $todayPlusDateInterval->add( new DateInterval( $dateInterval ) );

    // Get the current year, month and next month to create the 2 potential collection dates
    $todaysMonth = $today->format('m');
    $nextMonth   = $today->format('m') + 1;
    $monthAfter  = $today->format('m') + 2;
    $todaysYear  = $today->format('Y');

    $collectionDateThisMonth->setDate(  $todaysYear, $todaysMonth, $collectionDay );
    $collectionDateNextMonth->setDate(  $todaysYear, $nextMonth,   $collectionDay );
    $collectionDateMonthAfter->setDate( $todaysYear, $monthAfter,  $collectionDay );

    // Determine which is the next collection date
    if ( $todayPlusDateInterval >= $collectionDateThisMonth ) {
      if ( $todayPlusDateInterval >= $collectionDateNextMonth ) {
        $returnDate = $collectionDateMonthAfter;
      }
      else {
        $returnDate = $collectionDateNextMonth;
      }
    }
    else {
      $returnDate = $collectionDateThisMonth;
    }

    return $returnDate;

  }

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
        require_once 'CRM/Core/BAO/Domain.php';
        $domainValues     = CRM_Core_BAO_Domain::getNameAndEmail();
        $receiptFrom      = "$domainValues[0] <$domainValues[1]>";
        $receiptFromName  = $domainValues[0];
        $receiptFromEmail = $domainValues[1];
      }

      require_once 'CRM/Contact/BAO/Contact/Location.php';
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

      require_once 'CRM/Core/BAO/MessageTemplates.php';
      list( $sent, $subject, $message, $html ) = CRM_Core_BAO_MessageTemplates::sendTemplate( $templatesParams );

      if ( $sent ) {
        CRM_Core_Error::debug_log_message('Success: mail sent for recurring notification.');
      }
      else {
        CRM_Core_Error::debug_log_message('Failure: mail not sent for recurring notification.');
      }
    }
  }

  function insert_file_for_activity( $file_name , $activity_id ) {

    $upload_date = date( 'Y-m-d H:i:s' );

    $file_sql = <<<EOF
                INSERT INTO civicrm_file
                SET         mime_type   = %1
                ,           uri         = %2
                ,           upload_date = %3
EOF;

    $file_params  = array(
      1 => array( "text/csv"     , 'String' ) ,
      2 => array( $file_name     , 'String' ) ,
      3 => array( $upload_date   , 'String' )
    );
    $file_dao = CRM_Core_DAO::executeQuery( $file_sql, $file_params );

    $select_sql = <<<EOF
                SELECT   id
                FROM     civicrm_file
                WHERE    mime_type   = %1
                AND      uri         = %2
                AND      upload_date = %3
                ORDER BY id DESC
EOF;
    $select_dao = CRM_Core_DAO::executeQuery( $select_sql, $file_params );
    $select_dao->fetch();
    $file_id = $select_dao->id;

    $custom_sql = <<<EOF
                INSERT INTO civicrm_entity_file
                SET         entity_id    = %1
                ,           entity_table = %2
                ,           file_id = %3
EOF;
    $custom_params  = array(
      1 => array( $activity_id   , 'Integer' ) ,
      2 => array('civicrm_activity' , 'String') ,
      3 => array( $file_id   , 'Integer' )
    );

    $custom_dao = CRM_Core_DAO::executeQuery( $custom_sql, $custom_params );
  }

  function getDDConfirmationTemplate() {
    $default_template_name    = "direct_debit_confirmation";
    $default_template_sql     = "SELECT * FROM civicrm_msg_template mt WHERE mt.msg_title = %1";
    $default_template_params  = array( 1 => array( $default_template_name , 'String' ));
    $default_template_dao     = CRM_Core_DAO::executeQuery( $default_template_sql, $default_template_params );
    $default_template_dao->fetch();

    return $default_template_dao->msg_html;
  }

  /*
   * Function to produce PDF
   * Author : rajesh@millertech.co.uk
   */
  static function html2pdf( $text , $fileName = 'FiscalReceipts.pdf' , $calling = "internal" ) {

    require_once 'packages/dompdf/dompdf_config.inc.php';
    spl_autoload_register( 'DOMPDF_autoload' );
    $dompdf = new DOMPDF();

    $values = array();
    if ( !is_array( $text ) ) {
      $values =  array( $text );
    } else {
      $values =& $text;
    }

    $html = '';
    foreach ( $values as $value ) {
      $html .= "{$value}\n";
    }

    //echo $html;exit;

    $html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );

    $dompdf->load_html( $html );
    $dompdf->set_paper ('a4', 'portrait');
    $dompdf->render();

    if( $calling == "external" ) { // like calling from cron job
      $fileContent = $dompdf->output();
      return $fileContent;
    }
    else{
      $dompdf->stream( $fileName );
    }
    exit;
  }

  static function record_response( $direct_debit_response ) {
    $sql  = <<<EOF
            UPDATE civicrm_direct_debit
            SET    created                  = NOW()
            ,      data_type                = %0
            ,      entity_type              = %1
            ,      entity_id                = %2
            ,      bank_name                = %3
            ,      branch                   = %4
            ,      address1                 = %5
            ,      address2                 = %6
            ,      address3                 = %7
            ,      address4                 = %8
            ,      town                     = %9
            ,      county                   = %10
            ,      postcode                 = %11
            ,      first_collection_date    = %12
            ,      preferred_collection_day = %13
            ,      confirmation_method      = %14
            ,      response_status          = %15
            ,      response_raw             = %16
            ,      request_counter          = request_counter + 1
            WHERE  ddi_reference            = %17
EOF;

    CRM_Core_DAO::executeQuery( $sql, array(
        array( (string)  $direct_debit_response['data_type']               , 'String'  ),
        array( (string)  $direct_debit_response['entity_type']             , 'String'  ),
        array( (integer) $direct_debit_response['entity_id']               , 'Integer' ),
        array( (string)  $direct_debit_response['bank_name']               , 'String'  ),
        array( (string)  $direct_debit_response['branch']                  , 'String'  ),
        array( (string)  $direct_debit_response['address1']                , 'String'  ),
        array( (string)  $direct_debit_response['address2']                , 'String'  ),
        array( (string)  $direct_debit_response['address3']                , 'String'  ),
        array( (string)  $direct_debit_response['address4']                , 'String'  ),
        array( (string)  $direct_debit_response['town']                    , 'String'  ),
        array( (string)  $direct_debit_response['county']                  , 'String'  ),
        array( (string)  $direct_debit_response['postcode']                , 'String'  ),
        array( (string)  $direct_debit_response['first_collection_date']   , 'String'  ),
        array( (string)  $direct_debit_response['preferred_collection_day'], 'String'  ),
        array( (string)  $direct_debit_response['confirmation_method']     , 'String'  ),
        array( (string)  $direct_debit_response['response_status']         , 'String'  ),
        array( (string)  $direct_debit_response['response_raw']            , 'String'  ),
        array( (string)  $direct_debit_response['ddi_reference']           , 'String'  )
      )
    );
  }

  /**
   * Change a price set field to be required for a specific event.
   */
  function UK_Direct_Debit_civicrm_buildForm( $formName, &$form ) {
    if ($formName == 'CRM_Event_Form_Registration_Register') {
      if ($form->_eventId == EVENT_ID) {
        $form->addRule('price_3', ts('This field is required.'), 'required');
      }
    }
  }


  function buildOfflineDirectDebit(&$form, $useRequired = FALSE) {
    if ( $form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_FORM ) {
      self::setDirectDebitFields( $form );
      self::setBillingDetailsFields($form);
      foreach ( $form->_paymentFields as $name => $field ) {
        if ( isset($field['cc_field'] ) &&
          $field['cc_field']
        ) {
          if ($field['htmlType'] == 'chainSelect') {
            $form->addChainSelect($field['name'], array('required' => $useRequired && $field['is_required']));
          }
          else {
            $form->add( $field['htmlType'],
              $field['name'],
              $field['title'],
              CRM_Utils_Array::value('attributes', $field),
              $useRequired ? $field['is_required'] : FALSE
            );
          }
        }
      }

      $form->addRule( 'bank_identification_number',
        ts( 'Please enter a valid Bank Identification Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );

      $form->addRule( 'bank_account_number',
        ts( 'Please enter a valid Bank Account Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );
    }

  }

  function setBillingDetailsFields(&$form) {
    $bltID =  $form->_bltID;
    $form->_paymentFields['billing_first_name'] = array(
      'htmlType' => 'text',
      'name' => 'billing_first_name',
      'title' => ts('Billing First Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields['billing_middle_name'] = array(
      'htmlType' => 'text',
      'name' => 'billing_middle_name',
      'title' => ts('Billing Middle Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => FALSE,
    );

    $form->_paymentFields['billing_last_name'] = array(
      'htmlType' => 'text',
      'name' => 'billing_last_name',
      'title' => ts('Billing Last Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_street_address-{$bltID}"] = array(
      'htmlType' => 'text',
      'name' => "billing_street_address-{$bltID}",
      'title' => ts('Street Address'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_city-{$bltID}"] = array(
      'htmlType' => 'text',
      'name' => "billing_city-{$bltID}",
      'title' => ts('City'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_state_province_id-{$bltID}"] = array(
      'htmlType' => 'select',
      'title' => ts('State/Province'),
      'name' => "billing_state_province_id-{$bltID}",
      'cc_field' => TRUE,
      'attributes' => array(
          '' => ts('- select -'),
        ) +
        CRM_Core_PseudoConstant::stateProvince(),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_postal_code-{$bltID}"] = array(
      'htmlType' => 'text',
      'name' => "billing_postal_code-{$bltID}",
      'title' => ts('Postal Code'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_country_id-{$bltID}"] = array(
      'htmlType' => 'select',
      'name' => "billing_country_id-{$bltID}",
      'title' => ts('Country'),
      'cc_field' => TRUE,
      'attributes' => array(
          '' => ts('- select -'),
        ) +
        CRM_Core_PseudoConstant::country(),
      'is_required' => TRUE,
    );

  }

}

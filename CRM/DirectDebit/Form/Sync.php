<?php

class CRM_DirectDebit_Form_Sync extends CRM_Core_Form {
  const QUEUE_NAME = 'sm-pull';
  const END_URL    = 'civicrm/directdebit/sync';
  const END_PARAMS = 'state=done';
  const BATCH_COUNT = 10;

  function preProcess() {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats  = array();
      $stats = uk_direct_debit_civicrm_getSetting('sd_stats');
      $total  = uk_direct_debit_civicrm_getSetting('total');
      $stats['Total'] = $total;
      $this->assign('stats', $stats);
    }
  }

  public function buildQuickForm() {
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Import'),
      ),
    );
    // Add the Buttons.
    $this->addButtons($buttons);
  }

  public function postProcess() {
    $financialType = uk_direct_debit_civicrm_getSetting('financial_type');
    if(empty($financialType)) {
      CRM_Core_Session::setStatus(ts('Make sure financial Type is set in the setting'), 'UK Direct Debit', 'error');
      return FALSE;
    }
    $runner = self::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure smart debit settings are correctly configured in the payment processor setting page'));
    }
  }

  /**
   * Build Queue for sync job
   *
   * @return bool|CRM_Queue_Runner
   */
  static function getRunner() {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));

    $transactionIdList = "'dummyId'";  //Initialised so have at least one entry in list
    $smartDebitArray = self::getSmartDebitPayments();
    if (empty($smartDebitArray))
      return FALSE;

    $count  = count($smartDebitArray);

    uk_direct_debit_civicrm_saveSetting('total', $count);

    // Set the Number of Rounds
    $rounds = ceil($count/self::BATCH_COUNT);
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start   = $i * self::BATCH_COUNT;
      $contactsarray  = array_slice($smartDebitArray, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      $task    = new CRM_Queue_Task(
        array('CRM_DirectDebit_Form_Sync', 'syncSmartDebitRecords'),
        array(array($contactsarray)),
        "Pulling smart debit - Contacts {$counter} of {$count}"
      );

      // Add the Task to the Queue
      $queue->createItem($task);
      $i++;
    }

    if (!empty($smartDebitArray)) {
      // Setup the Runner
      $runner = new CRM_Queue_Runner(array(
        'title' => ts('Import From Smart Debit'),
        'queue' => $queue,
        'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
        'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
      ));

      uk_direct_debit_civicrm_saveSetting('sd_stats', NULL);
      return $runner;
    }
    return FALSE;
  }

  /**
   * Synchronise SmartDebit records with CiviCRM
   * Run via daily sync job
   *
   * @param CRM_Queue_TaskContext $ctx
   * @param $contactsarray
   * @return int
   */
  static function syncSmartDebitRecords(CRM_Queue_TaskContext $ctx, $contactsarray) {
    $contactsarray  = array_shift($contactsarray);
    foreach ($contactsarray as $key => $smartDebitRecord) {
      if(!$smartDebitRecord['start_date'] || !$smartDebitRecord['frequency_type']) {
        // Invalid record, ignore
        CRM_Core_Error::debug_log_message( 'syncSmartDebitRecords: either start_date or frequency type is missing from smart debit record, Ignoring the smart debit member= '. print_r($smartDebitRecord['reference_number'], true), $out = false);
        continue;
      }
      $result             = self::syncContribution($smartDebitRecord);

      if(!$result) { // no smart debit reference_number is present in civi
        $amount           = $smartDebitRecord['regular_amount'];
        $amount           = str_replace("£","",$amount);
        list($frequencyUnit, $frequencyInterval) =
          CRM_DirectDebit_Base::translateSmartDebitFrequencytoCiviCRM($smartDebitRecord['frequency_type'], $smartDebitRecord['frequency_factor']);
        $start_date       = CRM_Utils_Date::processDate($smartDebitRecord['start_date']);

        $api_contact_key = uk_direct_debit_civicrm_getSetting('api_contact_key');
        $api_contact_val_regex = uk_direct_debit_civicrm_getSetting('api_contact_val_regex');
        $api_contact_val_regex_index = uk_direct_debit_civicrm_getSetting('api_contact_val_regex_index');

        $contact          = new CRM_Contact_BAO_Contact();
        if(!$api_contact_val_regex) {
          $contact->id      = $smartDebitRecord[$api_contact_key];
        }
        else {
          $output = preg_match($api_contact_val_regex, $smartDebitRecord[$api_contact_key] , $results);
          if($output){
            $contact->id      = $results[$api_contact_val_regex_index];
          }
        }

        if($contact->id && $contact->find()) { // check for contact id is present in civi
          $paymentProcessorType   = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
          $paymentProcessorTypeId = CRM_Utils_Array::key('Smart_Debit', $paymentProcessorType); //15

          if(!empty($paymentProcessorTypeId)) { //smart debit processor type
            $query  = "
              SELECT cr.processor_id, cr.id as recur_id
              FROM `civicrm_contribution_recur` cr
              INNER JOIN civicrm_payment_processor pp ON pp.id = cr.payment_processor_id
              WHERE pp.payment_processor_type_id = %1
              AND cr.`frequency_unit` = %2
              AND cr.`frequency_interval` = %3
              AND cr.`start_date` = %4
              AND cr.`contact_id` = %5";

            $params =
              array(
                '1' => array($paymentProcessorTypeId , 'Integer'),
                '2' => array($frequencyUnit , 'String'),
                '3' => array($frequencyInterval , 'Integer'),
                '4' => array($start_date , 'String'),
                '5' => array($contact->id , 'Integer'),
              );

            $dao = CRM_Core_DAO::executeQuery($query ,$params);

            $dao->fetch();
            $reference  = isset($dao->processor_id) ? $dao->processor_id : NULL;
            $recurID    = isset($dao->recur_id) ? $dao->recur_id : NULL;

            if($recurID && !$reference) {
              $recurUpdate = civicrm_api('ContributionRecur', 'create', array('version' => 3,'id'=> $recurID, 'processor_id' => $smartDebitRecord['reference_number']));
              //call IPN method
              self::syncContribution($smartDebitRecord);
              continue;
            }
            else if($recurID && $reference) { // if reference is present, no action taken
              CRM_Core_Error::debug_log_message( 'syncSmartDebitRecords reference found. No action taken = '. print_r($reference, true), $out = false );
            }
            //}
            else if (!$recurID && !$reference){ // no recur is present in civi
              // Creat new contribution record
              $paymentProcessorId    = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessor', $paymentProcessorTypeId, 'id', 'payment_processor_type_id');
              $config                = CRM_Core_Config::singleton();
              $recurParams =
                array(
                  'version'               => 3,
                  'frequency_unit'        => $frequencyUnit,
                  'frequency_interval'    => $frequencyInterval,
                  'start_date'            => $start_date,
                  'processor_id'          => $smartDebitRecord['reference_number'],
                  'currency'              => $config->defaultCurrency,
                  'contact_id'            => $contact->id,
                  'amount'                => $amount,
                  'invoice_id'            => md5(uniqid(rand(), TRUE )),
                  'contribution_status_id'=> 5,
                  'payment_processor_id'  => $paymentProcessorId,
                );
              $result = civicrm_api('ContributionRecur', 'create', $recurParams);

              self::syncContribution($smartDebitRecord);
              continue;
            }
          }
        }
        else {
          CRM_Core_Error::debug_log_message( 'syncSmartDebitRecords: Contact not found in CiviCRM ID=  '. print_r($contact->id, true), $out = false );
          $setting = uk_direct_debit_civicrm_getSetting('sd_stats');
          uk_direct_debit_civicrm_saveSetting('sd_stats',
            array(
              'Added' => $setting['Added'],
              'New' => $setting['New'],
              'Canceled' => $setting['Canceled'],
              'Failed' => $setting['Failed'],
              'Not_Handled' => (1+ $setting['Not_Handled']),
              'Live' => $setting['Live'])
          );
        }
      }
    }
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Return difference between two dates in format
   * @param $date_1
   * @param $date_2
   * @param string $differenceFormat
   * @return string
   */
  static function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' ) {
    $datetime1 = date_create($date_1);
    $datetime2 = date_create($date_2);

    $interval = date_diff($datetime1, $datetime2);

    return $interval->format($differenceFormat);

  }

  /**
   * Retrieve Payer Contact Details from SmartDebit
   * Called during daily sync job
   * @param null $referenceNumber
   * @return array|bool
   */
  static function getSmartDebitPayments($referenceNumber = NULL) {
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username    = CRM_Utils_Array::value('user_name', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('signature', $userDetails);

    // Send payment POST to the target URL
    $url = CRM_DirectDebit_Base::getApiUrl('/api/data/dump', "query[service_user][pslid]=$pslid&query[report_format]=XML");

    // Restrict to a single payer if we have a reference
    if ($referenceNumber) {
      $url .= "&query[reference_number]=$referenceNumber";
    }
    $response = CRM_DirectDebit_Base::requestPost($url, '', $username, $password, '');

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
      case 'OK':
        $smartDebitArray = array();

        // Cater for a single response
        if (isset($response['Data']['PayerDetails']['@attributes'])) {
          $smartDebitArray[] = $response['Data']['PayerDetails']['@attributes'];
        } else {
          foreach ($response['Data']['PayerDetails'] as $key => $value) {
            $smartDebitArray[] = $value['@attributes'];
          }
        }
        return $smartDebitArray;
      default:
        if (isset($response['error'])) {
          $msg = $response['error'];
        }
        $msg .= '<br />An error occurred.';
        CRM_Core_Session::setStatus(ts($msg), 'Smart Debit', 'error');
        CRM_Core_Error::debug_log_message('Smart Debit: getSmartDebitPayments Error: '. $msg);
        return false;
    }
  }

  /**
   * Make sure we have a matching contribution record for the recurrence, then call addContribution
   * @param array $smartDebitRecord
   * @return bool
   */
  static function syncContribution($smartDebitRecord = array()) {
    //set transaction type
    /* Smart Debit statuses are as follows
                 0 Draft
                 1 New
                 10 Live
                 11 Cancelled
                 12 Rejected
    */
    $query = "
        SELECT cc.id as contribution_id, cr.id as recur_id, cr.invoice_id, cc.`contact_id`, cc.financial_type_id, max(receive_date) as receive_date
        FROM civicrm_contribution_recur cr
        LEFT JOIN civicrm_contribution cc ON (cr.id = cc.`contribution_recur_id` AND cc.`is_test` = 0)
        WHERE cr.processor_id = %1
        AND cr.processor_id IS NOT NULL";

    $params = array( 1 => array($smartDebitRecord['reference_number'], 'String' ) );
    $dao = CRM_Core_DAO::executeQuery( $query, $params);

    $dao->fetch();
    $contributionID         = $dao->contribution_id;
    $receiveDate            = $dao->receive_date;
    $financialTypeID      = $dao->financial_type_id;
    $contactID              = $dao->contact_id;
    $recurID                = $dao->recur_id;
    $invoiceID             = $dao->invoice_id;

    if ($recurID) {
      // We have a recurrence ID in the database
      if(!$contributionID) {
        // We have a recurID but no contribution ID, so we need to create a new contribution record
        $financialTypeID = uk_direct_debit_civicrm_getSetting('financial_type');
        $invoiceID = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionRecur', $recurID, 'invoice_id', 'id');
        $contactID = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionRecur', $recurID, 'contact_id', 'id');
        $amount = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionRecur', $recurID, 'amount', 'id');
        $receiveDate = CRM_Utils_Date::processDate($smartDebitRecord['start_date']);
        // create new contribution record for the recur just created
        $contributeParams =
          array(
            'version' => 3,
            'receive_date' => $receiveDate,
            'contact_id' => $contactID,
            'contribution_recur_id' => $recurID,
            'total_amount' => $amount,
            'invoice_id' => $invoiceID,
            'trxn_id' => $smartDebitRecord['reference_number'],
            'financial_type_id' => $financialTypeID
          );

        $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);
        if (!isset($contributeResult['id'])) {
          return FALSE;
        }
        $contributionID = $contributeResult['id'];

      }
      // Now we should have a contribution ID so call IPN
      self::addContribution($smartDebitRecord, $receiveDate, $contactID, $contributionID, $invoiceID, $recurID, $financialTypeID);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Call IPN to record contributions in civicrm
   *
   * @param array $smartDebitRecord
   * @param $receiveDate
   * @param $contactID
   * @param $contributionID
   * @param $invoice_id
   * @param $recurID
   * @param $financial_type_id
   */
  static function addContribution($smartDebitRecord = array(), $receiveDate, $contactID, $contributionID, $invoice_id, $recurID, $financial_type_id) {
    $added = $new = $canceled = $failed = $nothandled = $live = 0;
    $now              = date('Ymd');
    $today_date       = strtotime($now);
    $today_date       = date("Y-m-d", $today_date);
    $frequency_type   = $smartDebitRecord['frequency_type'];
    $txnType          = $smartDebitRecord['current_state'];
    $amount           = $smartDebitRecord['regular_amount'];
    $amount           = str_replace("£","",$amount);
    $start_date       = $smartDebitRecord['start_date'];

    switch ($txnType) {
      case '1': // New
        //Do nothing as the first contribution is already there in civicrm
        $new =  1;
        break;

      case '10': // Live
        // Record a new live contribution in civicrm
        $result = 0;
        $receiveDate  = strtotime($receiveDate);
        $receiveDate  = date("Y-m-d", $receiveDate);
        if( ($today_date > $start_date) && ($frequency_type == 'M')){
          $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%m');
        }
        if( ($today_date > $start_date) && ($frequency_type == 'Y')){
          $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%y');
        }
        if( ($today_date > $start_date) && ($frequency_type == 'Q')){
          $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%m');
          $result = (int)($result/3);
        }
        if( ($today_date > $start_date) && ($frequency_type == 'W')){
          $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%m');
          $result = $result * 4;
        }

        if ($result > 0) {
          $i = 1;
          while ($i <= $result) {
            $trxn_id = $smartDebitRecord['reference_number'].'-'.$today_date.'-'.$i;
            $query = "processor_name=Smart+Debit&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&mc_gross=".$amount."&invoice=".$invoice_id."&payment_status=Completed&txn_type=recurring_payment&contributionRecurID=".$recurID."&txn_id=".$trxn_id."&financial_type_id=".$financial_type_id;
            $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
            call_CiviCRM_IPN($url);
            $i++;
          }
        }
        $added    = $result;
        $live     = 1;
        break;

      case '11': // Cancelled
        // Record a cancelled contribution in civicrm
        $trxn_id = $smartDebitRecord['reference_number'].'-'.$today_date;
        $query = "processor_name=Smart+Debit&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&mc_gross=".$amount."&invoice=".$invoice_id."&payment_status=Completed&txn_type=subscr_cancel&contributionRecurID=".$recurID."&txn_id=".$trxn_id."&financial_type_id=".$financial_type_id;
        $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
        call_CiviCRM_IPN($url);
        $canceled = 1;
        break;

      case '12': // Rejected
        // Record a failed contribution in civicrm
        $trxn_id = $smartDebitRecord['reference_number'].'-'.$today_date;
        $query = "processor_name=Smart+Debit&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&mc_gross=".$amount."&invoice=".$invoice_id."&payment_status=Completed&txn_type=subscr_failed&contributionRecurID=".$recurID."&txn_id=".$trxn_id."&financial_type_id=".$financial_type_id;
        $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
        call_CiviCRM_IPN($url);
        $failed   = 1;
        break;
    }
    // Save statistics
    $setting = uk_direct_debit_civicrm_getSetting('sd_stats');
    if (isset($setting['Added']) && (is_int($setting['Added']))) {
      $added += $setting['Added'];
    }
    if (isset($setting['New']) && (is_int($setting['New']))) {
      $new += $setting['New'];
    }
    if (isset($setting['Canceled']) && (is_int($setting['Canceled']))) {
      $canceled += $setting['Canceled'];
    }
    if (isset($setting['Failed']) && (is_int($setting['Failed']))) {
      $failed += $setting['Failed'];
    }
    if (isset($setting['Not_Handled']) && (is_int($setting['Not_Handled']))) {
      $nothandled += $setting['Not_Handled'];
    }
    if (isset($setting['Live']) && (is_int($setting['Live']))) {
      $live += $setting['Live'];
    }
    uk_direct_debit_civicrm_saveSetting('sd_stats',
      array(
        'Added' => $added,
        'New' => $new,
        'Canceled' => $canceled,
        'Failed' => $failed,
        'Not_Handled' => $nothandled,
        'Live' => $live,
      )
    );
  }
}

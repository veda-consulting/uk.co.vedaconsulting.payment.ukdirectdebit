<?php

class CRM_DirectDebit_Sync
{
  const QUEUE_NAME = 'sm-pull';
  const END_URL = 'civicrm/directdebit/sync';
  const END_PARAMS = 'state=done';
  const BATCH_COUNT = 10;

  /**
   * Build Queue for sync job
   *
   * @return bool|CRM_Queue_Runner
   */
  static function getRunner($redirect=TRUE)
  {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name' => self::QUEUE_NAME,
      'type' => 'Sql',
      'reset' => TRUE,
    ));

    // Get collection report for today
    CRM_Core_Error::debug_log_message('SmartDebit cron: Retrieving Daily Collection Report.');
    $date = new DateTime();
    $collections = CRM_DirectDebit_Auddis::getSmartDebitCollectionReport($date->format('Y-m-d'));
    if (!isset($collections['error'])) {
      CRM_DirectDebit_Auddis::saveSmartDebitCollectionReport($collections);
    }
    CRM_DirectDebit_Auddis::removeOldSmartDebitCollectionReports();

    CRM_Core_Error::debug_log_message('SmartDebit cron: Retrieving Smart Debit Payer Contact Details.');
    // Get list of payers from SmartDebit
    $smartDebitArray = self::getSmartDebitPayerContactDetails();
    if (empty($smartDebitArray))
      return FALSE;

    $count = count($smartDebitArray);

    uk_direct_debit_civicrm_saveSetting('total', $count);

    // Set the Number of Rounds
    $rounds = ceil($count / self::BATCH_COUNT);
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start = $i * self::BATCH_COUNT;
      $contactsarray = array_slice($smartDebitArray, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      if ($counter > $count) $counter = $count;
      $task = new CRM_Queue_Task(
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
      $runnerParams = array(
        'title' => ts('Import From Smart Debit'),
        'queue' => $queue,
        'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      );
      if ($redirect) {
        // We don't want to redirect when run via API/sync job or we don't get a return value
        $runnerParams['onEndUrl'] = CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE);
      }

      $runner = new CRM_Queue_Runner($runnerParams);

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
  static function syncSmartDebitRecords(CRM_Queue_TaskContext $ctx, $contactsarray)
  {
    $contactsarray = array_shift($contactsarray);

    $api_contact_key = uk_direct_debit_civicrm_getSetting('api_contact_key');
    $api_contact_val_regex = uk_direct_debit_civicrm_getSetting('api_contact_val_regex');
    $api_contact_val_regex_index = uk_direct_debit_civicrm_getSetting('api_contact_val_regex_index');

    foreach ($contactsarray as $key => $smartDebitRecord) {
      if (!$smartDebitRecord['start_date'] || !$smartDebitRecord['frequency_type']) {
        // Invalid record, ignore
        CRM_Core_Error::debug_log_message('syncSmartDebitRecords: either start_date or frequency type is missing from smart debit record, Ignoring the smart debit member= ' . print_r($smartDebitRecord['reference_number'], true), $out = false);
        continue;
      }
      $smartDebitRecord['amount'] = $smartDebitRecord['regular_amount'];
      // Get Contact ID
      $contact = new CRM_Contact_BAO_Contact();
      $contact->id = $smartDebitRecord[$api_contact_key];
      if ($contact->id && $contact->find()) {
        // Contact exists in CiviCRM so try and sync contribution with contact
        $smartDebitRecord['contact_id'] = $contact->id;
      }
      else {
        CRM_Core_Error::debug_log_message('syncSmartDebitRecords: ERROR: Contact ID: '. $contact->id . ' does not exist in CiviCRM (Smart Debit Ref: '. $smartDebitRecord['reference_number'].')');
        continue;
      }

      $result = self::syncContribution($smartDebitRecord);

      if (!$result) {
        // We couldn't sync with a valid recurring contribution in CiviCRM
        CRM_Core_Error::debug_log_message('SmartDebit syncSmartDebitRecords. Could not sync from SmartDebit ref=' . $smartDebitRecord['reference_number']);
        $setting = uk_direct_debit_civicrm_getSetting('sd_stats');
        uk_direct_debit_civicrm_saveSetting('sd_stats',
          array(
            'Added' => $setting['Added'],
            'New' => $setting['New'],
            'Canceled' => $setting['Canceled'],
            'Failed' => $setting['Failed'],
            'Not_Handled' => (1 + $setting['Not_Handled']),
            'Live' => $setting['Live'])
        );
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
  static function dateDifference($date_1, $date_2, $differenceFormat = '%a')
  {
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
  static function getSmartDebitPayerContactDetails($referenceNumber = NULL)
  {
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    // Send payment POST to the target URL
    $url = CRM_DirectDebit_Base::getApiUrl('/api/data/dump', "query[service_user][pslid]="
                                            .urlencode($pslid)."&query[report_format]=XML");

    // Restrict to a single payer if we have a reference
    if ($referenceNumber) {
      $url .= "&query[reference_number]=".urlencode($referenceNumber);
    }
    $response = CRM_DirectDebit_Base::requestPost($url, '', $username, $password, '');

    // Take action based upon the response status
    switch (strtoupper($response["Status"])) {
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
        CRM_Core_Error::debug_log_message('Smart Debit: getSmartDebitPayments Error: ' . $msg);
        return false;
    }
  }

  /**
   * Make sure we have a matching contribution record for the recurrence, then call addContribution
   * @param array $smartDebitRecord
   * @return bool
   */
  static function syncContribution($smartDebitRecord)
  {
    //set transaction type
    /* Smart Debit statuses are as follows
                 0 Draft
                 1 New
                 10 Live
                 11 Cancelled
                 12 Rejected
    */
    // Get Recurring contribution from CiviCRM using Smart Debit reference (processor_id)
    $contributionRecur = civicrm_api3('ContributionRecur', 'get', array(
      'sequential' => 1,
      'processor_id' => $smartDebitRecord['reference_number'],
    ));
    // Should return 1 recurring contribution record for ref number.
    // If more than 1 we'll use the first one but log a warning.
    // If no records we can create a new recurring contribution

    if ($contributionRecur['count'] > 1) {
      // There should only be one for the reference, so let's delete any which do not have contributions.
      //  A new recurring contribution record will then be created and used.
      CRM_Core_Error::debug_log_message('SmartDebit syncContribution: WARNING: More than one recurring contribution record found in CiviCRM for reference: '
        . $smartDebitRecord['reference_number'] . '; Attempting to fix...');

      // Check for contributions for each recurring contribution record and delete the recurring record if it has no contributions
      foreach ($contributionRecur['values'] as $recur) {
        $contribution = civicrm_api3('Contribution', 'get', array(
          'sequential' => 1,
          'return' => array('contribution_id', 'receive_date', 'financial_type_id', 'contact_id', 'contribution_recur_id', 'invoice_id'),
          'contribution_recur_id' => $recur['id'],
        ));
        if ($contribution['count'] == 0) {
          // Only delete if there are no contributions
          // Delete the recurring record, it can be recreated later
          CRM_Core_Error::debug_log_message('SmartDebit syncContribution: No contributions for recur id: ' . $recur['id'] . '; 
                                                     Deleting recurring contribution record (it will be re-synced from SmartDebit)');

          // Actually delete the recurring record
          $result = civicrm_api3('ContributionRecur', 'delete', array(
            'id' => $recur['id'],
          ));
          if (!empty($result['is_error'])) {
            CRM_Core_Error::debug_log_message('SmartDebit syncContribution: ERROR deleting recurring contribution record: ' . $recur['id'] . '; You should fix this manually! '
              . print_r($result, true));
          }
        }
      }
      // Get Recurring contribution from CiviCRM using Smart Debit reference (processor_id)
      // We do this again as we may have deleted some above
      $contributionRecur = civicrm_api3('ContributionRecur', 'get', array(
        'sequential' => 1,
        'processor_id' => $smartDebitRecord['reference_number'],
      ));
    }

    if ($contributionRecur['count'] == 0) {
      // If there is 0 recurring contributions we can create it
      CRM_Core_Error::debug_log_message('SmartDebit syncContribution: No recurring contribution record found in CiviCRM for reference: ' . $smartDebitRecord['reference_number']);
      $result = CRM_DirectDebit_Base::createRecurContribution($smartDebitRecord);
      if (!empty($result['is_error'])) {
        CRM_Core_Error::debug_log_message('SmartDebit syncContribution: FAILED to create recurring contribution for reference: ' . $smartDebitRecord['reference_number']);
      } else {
        CRM_Core_Error::debug_log_message('SmartDebit syncContribution: Created recurring contribution for reference: ' . $smartDebitRecord['reference_number']);
      }
    }

    // Get Recurring contribution from CiviCRM using Smart Debit reference (processor_id)
    // We do this again as we may have a new one
    $contributionRecur = civicrm_api3('ContributionRecur', 'get', array(
      'sequential' => 1,
      'processor_id' => $smartDebitRecord['reference_number'],
    ));

    // We now have a single recurring contribution record for the reference
    $recur = $contributionRecur['values'][0];
    $contribution = civicrm_api3('Contribution', 'get', array(
      'sequential' => 1,
      'return' => array('contribution_id', 'receive_date', 'financial_type_id', 'contact_id', 'contribution_recur_id', 'invoice_id'),
      'contribution_recur_id' => $recur['id'],
    ));

    // If more than 1 we'll use the first one but log a warning.
    // If no records we need to create a contribution
    if ($contribution['count'] == 0) {
      // Got no contributions for recurring contribution record, we'll create one later
      $contributionID = NULL;
      $recurID = $recur['id'];
    } elseif ($contribution['count'] > 0) {
      // Got a contribution record, use it
      // If there is more than one record we use the first one
      $contributionRecord = $contribution['values'][0];
      $contributionID = $contributionRecord['contribution_id'];
      $recurID = $contributionRecord['contribution_recur_id'];
    }
    if ($contribution['count'] > 1) {
      // We've already assigned the first one, but log a warning if there are more than one.
      CRM_Core_Error::debug_log_message('SmartDebit syncContribution: WARNING: More than one contribution found in CiviCRM for reference: '
        . $smartDebitRecord['reference_number'] . '; Using the first one!');
    }

    if ($recurID) {
      // We have a recurrence ID in the database
      $smartDebitRecord['contribution_recur_id'] = $recurID;
      $smartDebitRecord['receive_date'] = $smartDebitRecord['start_date'];
      if (!empty($recur['invoice_id'])) {
        $smartDebitRecord['invoice_id'] = $recur['invoice_id'];
      }
      $smartDebitRecord['contact_id'] = $recur['contact_id'];
      $smartDebitRecord['total_amount'] = $recur['amount'];
      if (!$contributionID) {
        // We have a recurID but no contribution ID, so we need to create a new contribution record
        // create new contribution record for the recur just created
        // Check if we already have a contribution record for this transaction
        try {
          $existingContribution = civicrm_api3('Contribution', 'getsingle', array(
            'trxn_id' => $smartDebitRecord['reference_number'],
          ));
          if (empty($existingContribution['is_error'])) {
            // Update the existing contribution record
            $contributionID = $existingContribution['contribution_id'];
            $smartDebitRecord['contribution_status_id'] = $existingContribution['contribution_status_id'];
          }
        } catch (Exception $e) {
          // getsingle will except if no contribution found.
          // No existing contribution, so we'll create a new one.
        }
        $smartDebitRecord['contribution_id'] = $contributionID;
        // Create the contribution
        $contributionResult = CRM_DirectDebit_Base::createContribution($smartDebitRecord);
        if (!empty($contributeResult['is_error'])) {
          return FALSE;
        }
        if (empty($contributionResult['id'])) {
          return FALSE;
        }
        else {
          $contributionID = $contributionResult['id'];
        }

        $result = civicrm_api3('Contribution', 'getsingle', array(
          'sequential' => 1,
          'return' => array("financial_type_id"),
          'id' => $contributionID,
        ));

        $smartDebitRecord['financial_type_id'] = $result['financial_type_id'];

        // Now we should have a contribution ID so call IPN to add/verify the contribution
        self::addContribution($smartDebitRecord, $contributionID, $recurID);
        return TRUE;
      }
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
  static function addContribution($smartDebitRecord = array(), $contributionID, $recurID)
  {
    $added = $new = $canceled = $failed = $nothandled = $live = 0;
    $now = date('Ymd');
    $today_date = strtotime($now);
    $today_date = date("Y-m-d", $today_date);
    $frequency_type = $smartDebitRecord['frequency_type'];
    $txnType = $smartDebitRecord['current_state'];
    $amount = $smartDebitRecord['regular_amount'];
    $amount = str_replace("£", "", $amount);
    $start_date = $smartDebitRecord['start_date'];
    $receiveDate = $smartDebitRecord['receive_date'];
    $contactID = $smartDebitRecord['contact_id'];
    $invoice_id = $smartDebitRecord['invoice_id'];
    $financial_type_id = $smartDebitRecord['financial_type_id'];

    switch ($txnType) {
      case '1': // New
        //Do nothing as the first contribution is already there in civicrm
        $new = 1;
        break;

      case '10': // Live
        // Record a new live contribution in civicrm
        $result = 0;
        $receiveDate = strtotime($receiveDate);
        $receiveDate = date("Y-m-d", $receiveDate);
        if (($today_date > $start_date) && ($frequency_type == 'M')) {
          $result = CRM_DirectDebit_Sync::dateDifference($today_date, $receiveDate, '%m');
        }
        if (($today_date > $start_date) && ($frequency_type == 'Y')) {
          $result = CRM_DirectDebit_Sync::dateDifference($today_date, $receiveDate, '%y');
        }
        if (($today_date > $start_date) && ($frequency_type == 'Q')) {
          $result = CRM_DirectDebit_Sync::dateDifference($today_date, $receiveDate, '%m');
          $result = (int)($result / 3);
        }
        if (($today_date > $start_date) && ($frequency_type == 'W')) {
          $result = CRM_DirectDebit_Sync::dateDifference($today_date, $receiveDate, '%m');
          $result = $result * 4;
        }

        if ($result > 0) {
          $i = 1;
          while ($i <= $result) {
            $trxn_id = $smartDebitRecord['reference_number'] . '-' . $today_date . '-' . $i;
            CRM_Core_Payment_Smartdebitdd::callIPN('recurring_payment', $trxn_id, $contactID, $contributionID, $amount, $invoice_id, $recurID, $financial_type_id);
            $i++;
          }
        }
        $added = $result;
        $live = 1;
        break;

      case '11': // Cancelled
        // Record a cancelled contribution in civicrm
        $trxn_id = $smartDebitRecord['reference_number'] . '-' . $today_date;
        CRM_Core_Payment_Smartdebitdd::callIPN('subscr_cancel', $trxn_id, $contactID, $contributionID, $amount, $invoice_id, $recurID, $financial_type_id);
        $canceled = 1;
        break;

      case '12': // Rejected
        // Record a failed contribution in civicrm
        $trxn_id = $smartDebitRecord['reference_number'] . '-' . $today_date;
        CRM_Core_Payment_Smartdebitdd::callIPN('subscr_failed', $trxn_id, $contactID, $contributionID, $amount, $invoice_id, $recurID, $financial_type_id);
        $failed = 1;
        break;
    }
    // Save statistics
    $setting = uk_direct_debit_civicrm_getSetting('sd_stats');
    if (isset($setting['Added'])) {
      $added += intval($setting['Added']);
    }
    if (isset($setting['New'])) {
      $new += intval($setting['New']);
    }
    if (isset($setting['Canceled'])) {
      $canceled += intval($setting['Canceled']);
    }
    if (isset($setting['Failed'])) {
      $failed += intval($setting['Failed']);
    }
    if (isset($setting['Not_Handled'])) {
      $nothandled += intval($setting['Not_Handled']);
    }
    if (isset($setting['Live'])) {
      $live += intval($setting['Live']);
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

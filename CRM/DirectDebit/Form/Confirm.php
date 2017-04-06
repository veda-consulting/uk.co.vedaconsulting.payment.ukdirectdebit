<?php

require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/Session.php';
require_once 'CRM/Core/PseudoConstant.php';

class CRM_DirectDebit_Form_Confirm extends CRM_Core_Form {
  const QUEUE_NAME = 'sm-pull';
  const END_URL    = 'civicrm/directdebit/syncsd/confirm';
  const END_PARAMS = 'state=done';
  const BATCH_COUNT = 10;
  const SD_SETTING_GROUP = 'SmartDebit Preferences';

  public $auddisDate = NULL;

  public function preProcess() {
    $status = 0;
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');

    //MV:store the contribution results ids 
    if(!CRM_Core_DAO::checkTableExists('veda_civicrm_smartdebit_import_success_contributions')) {

      $createSql = "CREATE TABLE `veda_civicrm_smartdebit_import_success_contributions` (
                   `id` int(10) unsigned NOT NULL AUTO_INCREMENT, 
                   `transaction_id` varchar(255) DEFAULT NULL,
                   `contribution_id` int(11) DEFAULT NULL,
                   `contact_id` int(11) DEFAULT NULL,
                   `contact` varchar(255) DEFAULT NULL,
                   `amount` decimal(20,2) DEFAULT NULL,
                   `frequency` varchar(255) DEFAULT NULL,
                   `is_membership_renew` int(11) DEFAULT NULL,
                   `membership_renew_from` varchar(255) DEFAULT NULL,
                   `membership_renew_to` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`)
         ) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=latin1";

      CRM_Core_DAO::executeQuery($createSql);

    }
    elseif($state != 'done') {
      $emptySql = "TRUNCATE TABLE veda_civicrm_smartdebit_import_success_contributions";
      CRM_Core_DAO::executeQuery($emptySql);
    }

    if ($state == 'done') {
      $status = 1;

      $rejectedids  = uk_direct_debit_civicrm_getSetting('rejected_ids');
      $this->assign('rejectedids', $rejectedids);
      $getSQL = "SELECT * FROM veda_civicrm_smartdebit_import_success_contributions";
      $getDAO = CRM_Core_DAO::executeQuery($getSQL);
      $ids    = $totalContributionAmount = array();
      while( $getDAO->fetch() ){
        $transactionURL = CRM_Utils_System::url("civicrm/contact/view/contribution", "action=view&reset=1&id={$getDAO->contribution_id}&cid={$getDAO->contact_id}&context=home");
        $contactURL     = CRM_Utils_System::url("civicrm/contact/view", "reset=1&cid={$getDAO->contact_id}");
        $ids[] = array(
          'transaction_id'  => sprintf("<a href=%s>%s</a>", $transactionURL, $getDAO->transaction_id),
          'display_name'    => sprintf("<a href=%s>%s</a>", $contactURL, $getDAO->contact),
          'amount'          => CRM_Utils_Money::format($getDAO->amount),
          'frequency'       => ucwords($getDAO->frequency),
          'from'            => ($getDAO->membership_renew_from == 'NULL') ? 'NULL' : CRM_Utils_Date::customFormat($getDAO->membership_renew_from),
          'to'              => ($getDAO->membership_renew_to == 'NULL') ? 'NULL' : CRM_Utils_Date::customFormat($getDAO->membership_renew_to),
        );
        $totalContributionAmount[] =  $getDAO->amount;
      }
      $totalAmountAdded = array_sum($totalContributionAmount);
      $totalAmountAdded = CRM_Utils_Money::format($totalAmountAdded);
      $this->assign('ids', $ids);
      $this->assign('totalAmountAdded', $totalAmountAdded);
      $this->assign('totalValidContribution', count($ids));
      $this->assign('totalRejectedContribution', count($rejectedids));
    }
    $this->assign('status', $status);
  }

  public function buildQuickForm() {
    $auddisIDs = explode(',', CRM_Utils_Request::retrieve('auddisID', 'String', $this, false));
    $aruddIDs = explode(',', CRM_Utils_Request::retrieve('aruddID', 'String', $this, false));
    $this->add('hidden', 'auddisIDs', serialize($auddisIDs));
    $this->add('hidden', 'aruddIDs', serialize($aruddIDs));
    $redirectUrlBack = CRM_Utils_System::url('civicrm/directdebit/syncsd', 'reset=1');

    $this->addButtons(array(
        array(
          'type' => 'submit',
          'name' => ts('Confirm Sync'),
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'js' => array('onclick' => "location.href='{$redirectUrlBack}'; return false;"),
          'name' => ts('Cancel'),
        )
      )
    );
  }

  public function postProcess() {
    $params     = $this->controller->exportValues();
    $auddisIDs = unserialize($params['auddisIDs']);
    $aruddIDs = unserialize($params['aruddIDs']);

    $runner = self::getRunner($auddisIDs, $aruddIDs);
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure smart debit settings are correctly configured in the payment processor setting page'));
    }
  }

  static function getRunner($auddisIDs = NULL, $aruddIDs = NULL) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));

    // Get the matched IDs for processing and put them in transactionIds array
    $selectQuery = "SELECT `transaction_id` as trxn_id, `receive_date` as receive_date FROM `veda_civicrm_smartdebit_import`";
    $aMatchedids  = uk_direct_debit_civicrm_getSetting('result_ids');
    if(!empty($aMatchedids)){
      $selectQuery .= " WHERE transaction_id IN (".implode(', ', $aMatchedids)." )";
    }
    $dao = CRM_Core_DAO::executeQuery($selectQuery);
    $transactionIds = array();
    while($dao->fetch()) {
      $transactionIds[] = $dao->trxn_id;
    }
    // Get number of matched transactions
    $count  = count($transactionIds);

    // Set the Number of Rounds
    $rounds = ceil($count/self::BATCH_COUNT);
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start   = $i * self::BATCH_COUNT;
      $transactionIdsBatch  = array_slice($transactionIds, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      if ($counter > $count) $counter = $count;
      $task    = new CRM_Queue_Task(
        array('CRM_DirectDebit_Form_Confirm', 'syncSmartDebitRecords'),
        array(array($transactionIdsBatch)),
        "Pulling smart debit - Contacts {$counter} of {$count}"
      );

      // Add the Task to the Queue
      $queue->createItem($task);
      $i++;
    }

    if (!empty($transactionIds)) {
      // Setup the Runner
      $runner = new CRM_Queue_Runner(array(
        'title' => ts('Import From Smart Debit'),
        'queue' => $queue,
        'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
        'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
      ));

      // Reset the counter when sync starts
      uk_direct_debit_civicrm_saveSetting('rejected_ids', NULL);

      // Add contributions for rejected payments with the status of 'failed'
      $ids = array();

      // Retrieve AUDDIS files from SmartDebit
      if($auddisIDs) {
        // Find the relevant auddis file
        foreach ($auddisIDs as $auddisID) {
          $auddisFiles[] = CRM_DirectDebit_Auddis::getSmartDebitAuddisFile($auddisID);
        }
        // Process AUDDIS files
        foreach ($auddisFiles as $auddisFile) {
          $auddisDate = $auddisFile['auddis_date'];
          unset($auddisFile['auddis_date']);
          foreach ($auddisFile as $key => $value) {

            $sql = "
            SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id, ctrc.financial_type_id
            FROM civicrm_contribution_recur ctrc
            INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id)
            WHERE ctrc.trxn_id = %1";

            $params = array( 1 => array( $value['reference'], 'String' ) );
            $dao = CRM_Core_DAO::executeQuery( $sql, $params);

            if ($dao->fetch()) {
              $contributeParams =
                array(
                  'version'                => 3,
                  'contact_id'             => $dao->contact_id,
                  'contribution_recur_id'  => $dao->contribution_recur_id,
                  'total_amount'           => $dao->amount,
                  'invoice_id'             => md5(uniqid(rand(), TRUE )),
                  'trxn_id'                => $value['reference'].'/'.CRM_Utils_Date::processDate($receiveDate),
                  'financial_type_id'      => $dao->financial_type_id,
                  'payment_instrument_id'  => $dao->payment_instrument_id,
                  'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed'),
                  'source'                 => 'Smart Debit Import',
                  'receive_date'           => $value['effective-date'],
                );

              // Allow params to be modified via hook
              CRM_DirectDebit_Utils_Hook::alterSmartDebitContributionParams( $contributeParams );
              $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);

              if(!$contributeResult['is_error']) {
                $contributionID = $contributeResult['id'];
                // get contact display name to display in result screen
                $contactParams = array('version' => 3, 'id' => $contributeResult['values'][$contributionID]['contact_id']);
                $contactResult = civicrm_api('Contact', 'getsingle', $contactParams);

                $ids[$contributionID] = array('cid' => $contributeResult['values'][$contributionID]['contact_id'],
                  'id' => $contributionID,
                  'display_name' => $contactResult['display_name'],
                  'total_amount' => CRM_Utils_Money::format($contributeResult['values'][$contributionID]['total_amount']),
                  'trxn_id'      => $value['reference'],
                  'status'       => $contributeResult['label'],
                );

                // Allow auddis rejected contribution to be handled by hook
                CRM_DirectDebit_Utils_Hook::handleAuddisRejectedContribution($contributionID);
              }
            }
          }
          // Create activity now we've processed auddis
          $params = array(
            'version' => 3,
            'sequential' => 1,
            'activity_type_id' => 6,
            'subject' => 'SmartDebitAUDDIS'.$auddisDate,
            'details' => 'Sync had been processed already for this date '.$auddisDate,
          );
          $result = civicrm_api('Activity', 'create', $params);
        }
      }


      // Add contributions for rejected payments with the status of 'failed'
      /*
       * [@attributes] => Array
                                                                (
                                                                    [ref] => 12345689
                                                                    [transCode] => 01
                                                                    [returnCode] => 0
                                                                    [payerReference] => 268855
                                                                    [returnDescription] => REFER TO PAYER
                                                                    [originalProcessingDate] => 2016-03-14
                                                                    [currency] => GBP
                                                                    [valueOf] => 10.50
                                                                )
       */
      // Retrieve ARUDD files from SmartDebit
      if($aruddIDs) {
        foreach ($aruddIDs as $aruddID) {
          $aruddFiles[] = CRM_DirectDebit_Auddis::getSmartDebitAruddFile($aruddID);
        }
        // Process ARUDD files
        foreach ($aruddFiles as $aruddFile) {
          $aruddDate = $aruddFile['arudd_date'];
          unset($aruddFile['arudd_date']);
          foreach ($aruddFile as $key => $value) {
            $sql = "
            SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id, ctrc.financial_type_id
            FROM civicrm_contribution_recur ctrc
            INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id)
            WHERE ctrc.trxn_id = %1";

            $params = array( 1 => array( $value['ref'], 'String' ) );
            $dao = CRM_Core_DAO::executeQuery( $sql, $params);

            if ($dao->fetch()) {
              $contributeParams =
                array(
                  'version'                => 3,
                  'contact_id'             => $dao->contact_id,
                  'contribution_recur_id'  => $dao->contribution_recur_id,
                  'total_amount'           => $dao->amount,
                  'invoice_id'             => md5(uniqid(rand(), TRUE )),
                  'trxn_id'                => $value['ref'].'/'.CRM_Utils_Date::processDate($receiveDate),
                  'financial_type_id'      => $dao->financial_type_id,
                  'payment_instrument_id'  => $dao->payment_instrument_id,
                  'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed'),
                  'source'                 => 'Smart Debit Import',
                  'receive_date'           => $value['originalProcessingDate'],
                );

              // Allow params to be modified via hook
              CRM_DirectDebit_Utils_Hook::alterSmartDebitContributionParams( $contributeParams );

              $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);

              if(!$contributeResult['is_error']) {
                $contributionID   = $contributeResult['id'];
                // get contact display name to display in result screen
                $contactParams = array('version' => 3, 'id' => $contributeResult['values'][$contributionID]['contact_id']);
                $contactResult = civicrm_api('Contact', 'getsingle', $contactParams);

                $ids[$contributionID] = array('cid' => $contributeResult['values'][$contributionID]['contact_id'],
                  'id' => $contributionID,
                  'display_name' => $contactResult['display_name'],
                  'total_amount' => CRM_Utils_Money::format($contributeResult['values'][$contributionID]['total_amount']),
                  'trxn_id'      => $value['ref'],
                  'status'       => $contributeResult['label'],
                );

                // Allow auddis rejected contribution to be handled by hook
                CRM_DirectDebit_Utils_Hook::handleAuddisRejectedContribution( $contributionID );
              }
            }
          }
          // Create activity now we've processed auddis
          $params = array(
            'version' => 3,
            'sequential' => 1,
            'activity_type_id' => 6,
            'subject' => 'SmartDebitARUDD'.$aruddDate,
            'details' => 'Sync had been processed already for this date '.$aruddDate,
          );
          $result = civicrm_api('Activity', 'create', $params);
        }
      }

      uk_direct_debit_civicrm_saveSetting('rejected_ids', $ids);
      return $runner;
    }
    return FALSE;
  }

  static function syncSmartDebitRecords(CRM_Queue_TaskContext $ctx, $transactionIdsBatch) {
    $transactionIdsBatch  = array_shift($transactionIdsBatch);
    $ids = array();

    foreach ($transactionIdsBatch as $key => $transactionId) {
      $sql = "
        SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.frequency_interval, ctrc.payment_instrument_id, ctrc.financial_type_id
        FROM civicrm_contribution_recur ctrc
        INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id)
        WHERE ctrc.trxn_id = %1";

      $params = array( 1 => array( $transactionId, 'String' ) );
      $dao = CRM_Core_DAO::executeQuery( $sql, $params);

      $selectQuery  = "SELECT `receive_date` as receive_date, `amount` as amount FROM `veda_civicrm_smartdebit_import` WHERE `transaction_id` = '{$transactionId}'";
      $daoSelect    = CRM_Core_DAO::executeQuery($selectQuery);
      $daoSelect->fetch();

      // Smart debit charge file has dates in UK format
      // UK dates (eg. 27/05/1990) won't work with strtotime, even with timezone properly set.
      // However, if you just replace "/" with "-" it will work fine.
      $receiveDate = date('Y-m-d', strtotime(str_replace('/', '-', $daoSelect->receive_date)));

      if ($dao->fetch()) {
        $contributeParams =
          array(
            'version'                => 3,
            'contact_id'             => $dao->contact_id,
            'contribution_recur_id'  => $dao->contribution_recur_id,
            'total_amount'           => $daoSelect->amount,
            'invoice_id'             => md5(uniqid(rand(), TRUE )),
            'trxn_id'                => $transactionId.'/'.CRM_Utils_Date::processDate($receiveDate),
            'financial_type_id'      => $dao->financial_type_id,
            'payment_instrument_id'  => $dao->payment_instrument_id,
            'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
            'source'                 => 'Smart Debit Import',
            'receive_date'           => CRM_Utils_Date::processDate($receiveDate),
          );

        // Check if the contribution is first payment
        // if yes, update the contribution instead of creating one
        // as CiviCRM should have created the first contribution
        $contributeParams = self::checkIfFirstPayment($contributeParams, $dao->frequency_unit, $dao->frequency_interval);

        // Allow params to be modified via hook
        CRM_DirectDebit_Utils_Hook::alterSmartDebitContributionParams($contributeParams);

        $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);
        $membershipRenew = 0;
        CRM_Core_Error::debug_log_message('SmartDebit syncSmartDebitRecords: $contributeResult='.print_r($contributeResult)); //DEBUG

        if(!$contributeResult['is_error']) {
          CRM_Core_Error::debug_log_message('syncSmartDebitRecords: Created contribution success'); //DEBUG
          // Get recurring contribution ID
          $contributionID   = $contributeResult['id'];
          $contributionRecurID     = $contributeResult['values'][$contributionID]['contribution_recur_id'];
          // Get membership ID for recurring contribution
          $membershipRecord = civicrm_api3('Membership', 'get', array(
            'sequential' => 1,
            'return' => array("id", "end_date", "status_id"),
            'contribution_recur_id' => $contributionRecurID,
          ));
          if (isset($membershipRecord['id'])) {
            $membershipID = $membershipRecord['id'];
          }

          CRM_Core_Error::debug_log_message('membershipID = '. $membershipID); //DEBUG
          if (!empty($membershipID)) {
            // Get membership dates
            if (isset($membershipRecord['values'][0]['end_date'])) {
              $membershipEndDate = $membershipRecord['values'][0]['end_date'];
            }
            else {
              // Membership is probably pending so we can't do anything here
              // We shouldn't get here because the completed contribution should renew the membership
            }

            // Create membership payment
            self::createMembershipPayment($membershipID, $contributionID);

            // Get recurring contribution details
            $contributionRecur = civicrm_api("ContributionRecur","get", array ('version' => '3', 'id' => $contributionRecurID));
            if (isset($contributionRecur['values'][$contributionRecurID]['frequency_unit'])) {
              $frequencyUnit = $contributionRecur['values'][$contributionRecurID]['frequency_unit'];
              $frequencyInterval = $contributionRecur['values'][$contributionRecurID]['frequency_interval'];
            }
            else {
              CRM_Core_Error::debug_log_message('SmartDebit syncSmartDebitRecords: FrequencyUnit/Interval not defined for recurring contribution='.$contributionRecurID);
              // Membership won't be renewed as we don't know the renewal frequency
            }

            // FIXME: What do we do if we don't have an end date? Will it get created for us when membership payment is made?
            $membershipRenewStartDate = $membershipEndDate;
            $membershipRenewEndDate = date("Y-m-d", strtotime($membershipEndDate));

            // Renew the membership if we have a renewal frequency
            if (isset($frequencyUnit)) {
              // Increase new membership end date by one period
              $membershipRenewEndDate = date("Y-m-d",strtotime(date("Y-m-d", strtotime($membershipEndDate)) . " +$frequencyInterval $frequencyUnit"));

              $membershipParams = array ( 'version'       => '3',
                                          'membership_id' => $membershipID,
                                          'id'            => $membershipID,
                                          'end_date'      => $membershipRenewEndDate,
              );

              // Set a flag to be sent to hook, so that membership renewal can be skipped
              $membershipParams['renew'] = 1;

              // Allow membership update params to be modified via hook
              CRM_DirectDebit_Utils_Hook::handleSmartDebitMembershipRenewal($membershipParams);

              // Membership renewal may be skipped in hook by setting 'renew' = 0
              if ($membershipParams['renew'] == 1) {
                // remove the renew key from params array, which need to be passed to API
                $membershipRenew = $membershipParams['renew'];
                unset($membershipParams['renew']);
                // Update/Renew the membership
                //FIXME: Do we also need to change the membership status?
                $updatedMember = civicrm_api("Membership", "create", $membershipParams);
              }
            }
          }
          // get contact display name to display in result screen
          $contactParams = array('version' => 3, 'id' => $contributeResult['values'][$contributionID]['contact_id']);
          $contactResult = civicrm_api('Contact', 'getsingle', $contactParams);

          $ids[$contributionID]= array('cid' => $contributeResult['values'][$contributionID]['contact_id'],
                                       'id'  => $contributionID,
                                       'display_name'  => $contactResult['display_name'],
          );

          // Store the results in veda_civicrm_smartdebit_import_success_contributions table
          $keepSuccessResultsSQL = "
            INSERT Into veda_civicrm_smartdebit_import_success_contributions
            ( `transaction_id`, `contribution_id`, `contact_id`, `contact`, `amount`, `frequency`, `is_membership_renew`, `membershipRenewStartDate`, `membership_renew_to` )
            VALUES ( %1, %2, %3, %4, %5, %6, %7, %8, %9 )
          ";
          $keepSuccessResultsParams = array(
            1 => array( $transactionId, 'String'),
            2 => array( $contributionID, 'Integer'),
            3 => array( $contactResult['id'], 'Integer'),
            4 => array( $contactResult['display_name'], 'String'),
            5 => array( $contributeResult['values'][$contributionID]['total_amount'], 'String'),
            6 => array( $frequencyInterval . ' ' . $frequencyUnit, 'String'),
            7 => array( $membershipRenew, 'Integer'),
            8 => array( $membershipRenewStartDate, 'String'),
            9 => array ($membershipRenewEndDate, 'String'),
          );
          CRM_Core_DAO::executeQuery($keepSuccessResultsSQL, $keepSuccessResultsParams);
        }
        else {
          // No membership ID so we don't do anything with membership
          CRM_Core_Error::debug_log_message('SmartDebit syncSmartDebitRecords: No Membership ID! contributeResult = '.print_r($contributeResult, TRUE)); //DEBUG
        }
      }
    }
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /*
   * Function to check if the contribution is first contribution
   * for the recurring contribution record
   */
  static function checkIfFirstPayment($params, $frequencyUnit = 'year', $frequencyInterval = 1) {
    if (empty($params['contribution_recur_id'])) {
      return;
    }

    // Get days difference to determine if this is first payment
    $days = self::daysDifferenceForFrequency($frequencyUnit, $frequencyInterval);

    $contributionResult = civicrm_api3('Contribution', 'get', array(
      'contribution_recur_id' => $params['contribution_recur_id'],
    ));

    // We have only one contribution for the recurring record
    if ($contributionResult['count'] == 1) {
      $contributionDetails = $contributionResult['values'][$contributionResult['id']];

      if (!empty($contributionDetails['receive_date']) && !empty($params['receive_date'])) {
        // Find the date difference between the contribution date and new collection date
        $dateDiff = self::getDateDifference($params['receive_date'], $contributionDetails['receive_date']);

        // if diff is less than set number of days, return Contribution ID to update the contribution
        // If $days == 0 it's a lifetime membership
        if (($dateDiff < $days) && ($days != 0)) {
          $params['id'] = $contributionResult['id'];
          unset($params['source']);
        }
      }
    }
    // Get the recent pending contribution if there is more than 1 payment for the recurring record
    else if ($contributionResult['count'] > 1) {
      $sqlParams = array(
        1 => array($params['contribution_recur_id'], 'Integer'),
      );
      $sql = "SELECT cc.id, cc.receive_date FROM civicrm_contribution cc WHERE cc.contribution_recur_id = %1 ORDER BY cc.receive_date DESC";
      $dao = CRM_Core_DAO::executeQuery($sql , $sqlParams);
      while($dao->fetch()) {
        if (!empty($dao->receive_date) && !empty($params['receive_date'])) {
          $dateDiff = self::getDateDifference($params['receive_date'], $dao->receive_date);

          // if diff is less than set number of days, return Contribution ID to update the contribution
          if ($dateDiff < $days) {
            $params['id'] = $dao->id;
            unset($params['source']);
          }
        }
      }
    }
    return $params;
  }

  /**
   * Function to return number of days difference to check between current date
   * and payment date to determine if this is first payment or not
   *
   * @param $frequencyUnit
   * @param $frequencyInterval
   * @return int
   */
  static function daysDifferenceForFrequency($frequencyUnit, $frequencyInterval) {
    switch ($frequencyUnit) {
      case 'day':
        $days = $frequencyInterval * 1;
      case 'month':
        $days = $frequencyInterval * 7;
        break;
      case 'year':
        $days = $frequencyInterval * 30;
        break;
      case 'lifetime':
        $days = 0;
        break;
      default:
        $days = 30;
        break;
    }
    return $days;
  }

  /**
   * Function to get number of days difference between 2 dates
   * @param $date1
   * @param $date2
   * @return float
   */
  static function getDateDifference($date1, $date2) {
    return floor((strtotime($date1) - strtotime($date2))/(60*60*24));
  }

  /**
   * Link Membership ID with Contribution ID
   * @param $membershipId
   * @param $contributionId
   */
  function createMembershipPayment($membershipId, $contributionId) {
    if (empty($membershipId) || empty($contributionId)) {
      return;
    }

    // Check if membership payment already exist for the contribution
    $params = array(
      'version' => 3,
      'membership_id' => $membershipId,
      'contribution_id' => $contributionId,
    );
    $membershipPayment = civicrm_api('MembershipPayment', 'get', $params);

    // Create if the membership payment not exists
    if ($membershipPayment['count'] == 0) {
      $membershipPayment = civicrm_api('MembershipPayment', 'create', $params);
    }
  }
}

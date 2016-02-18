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
    
      $creatSql = "CREATE TABLE `veda_civicrm_smartdebit_import_success_contributions` (
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
      
      CRM_Core_DAO::executeQuery($creatSql);
      
    }
    elseif($state != 'done') {
      $emptySql = "TRUNCATE TABLE veda_civicrm_smartdebit_import_success_contributions";
      CRM_Core_DAO::executeQuery($emptySql);
    }

    if ($state == 'done') {
      $status = 1;

      // $ids  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids');

      $rejectedids  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'rejected_ids');
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

    $auddisDates = CRM_Utils_Request::retrieve('auddisDates', 'String', $this, false, '', 'GET');
    $this->add('hidden', 'auddisDate', serialize($auddisDates));
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
    $auddisDates = unserialize($params['auddisDate']);
    $financialTypeID    = CRM_Core_BAO_Setting::getItem('UK Direct Debit', 'financial_type');

    // Check financialType is set in the civicrm_setting table
    if(empty($financialTypeID)) {
      CRM_Core_Session::setStatus(ts('Make sure Financial Type is set in UK Direct Debit setting'), Error, 'error');
      return FALSE;
    }

    $runner = self::getRunner($auddisDates);
    if ($runner) {
      // Create activity for the sync just finished with the auddis date
      foreach ($auddisDates as $auddisDate) {

        $params = array(
          'version' => 3,
          'sequential' => 1,
          'activity_type_id' => 6,
          'subject' => $auddisDate,
          'details' => 'Sync had been processed already for this date '.$auddisDate,
        );
        $result = civicrm_api('Activity', 'create', $params);
      }
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure smart debit settings are correctly configured in the payment processor setting page'));
    }
  }

  static function getRunner($auddisDates = NULL) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));

    // List of auddis files
    $auddisArray      = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis();
    if($auddisDates) {
    // Find the relevant auddis file
      foreach ($auddisDates as $auddisDate) {
        $auddisDetails  = CRM_DirectDebit_Form_Auddis::getRightAuddisFile($auddisArray, $auddisDate);
        $auddisFiles[] = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis($auddisDetails['uri']);
      }
    }

    $selectQuery = "SELECT `transaction_id` as trxn_id, `receive_date` as receive_date FROM `veda_civicrm_smartdebit_import`";
	//MV: TO process only the matched Ids 
    $aMatchedids  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids');
    if(!empty($aMatchedids)){
      $selectQuery .= " WHERE transaction_id IN (".implode(', ', $aMatchedids)." )";
    }

    $dao = CRM_Core_DAO::executeQuery($selectQuery);
    $traIds = array();
    while($dao->fetch()) {
      $traIds[] = $dao->trxn_id;
      $receiveDate  = $dao->receive_date;
    }

    $count  = count($traIds);

    // Set the Number of Rounds
    $rounds = ceil($count/self::BATCH_COUNT);
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start   = $i * self::BATCH_COUNT;
      $contactsarray  = array_slice($traIds, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      $task    = new CRM_Queue_Task(
        array('CRM_DirectDebit_Form_Confirm', 'syncSmartDebitRecords'),
        array(array($contactsarray), array($auddisDetails), array($auddisFiles), $auddisDate),
        "Pulling smart debit - Contacts {$counter} of {$count}"
      );

      // Add the Task to the Queu
      $queue->createItem($task);
      $i++;
    }

    if (!empty($traIds)) {
      // Setup the Runner
      $runner = new CRM_Queue_Runner(array(
        'title' => ts('Import From Smart Debit'),
        'queue' => $queue,
        'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
        'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
      ));

      // Reset the counter when sync starts
      //$query1 = "UPDATE civicrm_setting SET value = NULL WHERE name = 'result_ids'";
      $query2 = "UPDATE civicrm_setting SET value = NULL WHERE name = 'rejected_ids'";

      //CRM_Core_DAO::executeQuery($query1);
      CRM_Core_DAO::executeQuery($query2);

      // Add contributions for rejected payments with the status of 'failed'
      $ids = array();
      foreach ($auddisFiles as $auddisFile) {
        foreach ($auddisFile as $key => $value) {

          $sql = "
            SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id
            FROM civicrm_contribution_recur ctrc
            INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id)
            WHERE ctrc.trxn_id = %1";

          $params = array( 1 => array( $value['reference'], 'String' ) );
          $dao = CRM_Core_DAO::executeQuery( $sql, $params);

          $financialTypeID    = CRM_Core_BAO_Setting::getItem('UK Direct Debit', 'financial_type');
          // RS: Commenting below line, as we save the financial type ID in civicrm_setting table
          // $financialTypeID  = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $financialType, 'id', 'name');

          if ($dao->fetch()) {
            $contributeParams =
            array(
              'version'                => 3,
              'contact_id'             => $dao->contact_id,
              'contribution_recur_id'  => $dao->contribution_recur_id,
              'total_amount'           => $dao->amount,
              'invoice_id'             => md5(uniqid(rand(), TRUE )),
              'trxn_id'                => $value['reference'].'/'.CRM_Utils_Date::processDate($receiveDate),
              'financial_type_id'      => $financialTypeID,
              'payment_instrument_id'  => $dao->payment_instrument_id,
              'contribution_status_id' => 4,
              'source'                 => 'Smart Debit Import',
              'receive_date'           => $value['effective-date'],
            );

            // Allow params to be modified via hook
            CRM_DirectDebit_Utils_Hook::alterSmartDebitContributionParams( $contributeParams );

            $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);

            if(!$contributeResult['is_error']) {
              $contributionID   = $contributeResult['id'];
              // get contact display name to display in result screen
              $contactParams = array('version' => 3, 'id' => $contributeResult['values'][$contributionID]['contact_id']);
              $contactResult = civicrm_api('Contact', 'getsingle', $contactParams);

              $ids[$contributionID]= array(   'cid' => $contributeResult['values'][$contributionID]['contact_id']
                                            , 'id' => $contributionID
                                            , 'display_name' => $contactResult['display_name']
                                            , 'total_amount' => CRM_Utils_Money::format($contributeResult['values'][$contributionID]['total_amount'])
                                            , 'trxn_id'      => $value['reference']
                                            , 'status'       => $statusResult['label']
                                            );

              // Allow auddis rejected contribution to be handled by hook
              CRM_DirectDebit_Utils_Hook::handleAuddisRejectedContribution( $contributionID );
            }
          }
        }
      }

      CRM_Core_BAO_Setting::setItem($ids,
        CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'rejected_ids'
      );
      return $runner;
    }
    return FALSE;
  }

  static function syncSmartDebitRecords(CRM_Queue_TaskContext $ctx, $contactsarray, $auddisDetails, $auddisFile, $auddisDate ) {

    $contactsarray  = array_shift($contactsarray);
    $auddisDetails  = array_shift($auddisDetails);
    $auddisFile     = array_shift($auddisFile);

    $ids = array();

    foreach ($contactsarray as $key => $smartDebitRecord) {

      $sql = "
        SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id
        FROM civicrm_contribution_recur ctrc
        INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id)
        WHERE ctrc.trxn_id = %1";

      $params = array( 1 => array( $smartDebitRecord, 'String' ) );
      $dao = CRM_Core_DAO::executeQuery( $sql, $params);

      $selectQuery  = "SELECT `receive_date` as receive_date FROM `veda_civicrm_smartdebit_import` WHERE `transaction_id` = '{$smartDebitRecord}'";
      $daoSelect    = CRM_Core_DAO::executeQuery($selectQuery);
      $daoSelect->fetch();

      $financialTypeID    = CRM_Core_BAO_Setting::getItem('UK Direct Debit', 'financial_type');
      // RS: Commenting below line, as we save the financial type ID in civicrm_setting table
      //$financialTypeID  = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $financialType, 'id', 'name');

      // Smart debit charge file has dates in UK format
      // UK dates (eg. 27/05/1990) won't work with strotime, even with timezone properly set.
      // However, if you just replace "/" with "-" it will work fine.
      $receiveDate = date('Y-m-d', strtotime(str_replace('/', '-', $daoSelect->receive_date)));

      if ($dao->fetch()) {
        $contributeParams =
        array(
          'version'                => 3,
          'contact_id'             => $dao->contact_id,
          'contribution_recur_id'  => $dao->contribution_recur_id,
          'total_amount'           => $dao->amount,
          'invoice_id'             => md5(uniqid(rand(), TRUE )),
          'trxn_id'                => $smartDebitRecord.'/'.CRM_Utils_Date::processDate($receiveDate),
          'financial_type_id'      => $financialTypeID,
          'payment_instrument_id'  => $dao->payment_instrument_id,
          'contribution_status_id' => 1,
          'source'                 => 'Smart Debit Import',
          'receive_date'           => CRM_Utils_Date::processDate($receiveDate),
        );

        // Check if the contribution is first payment
        // if yes, update the contribution instead of creating one
        // as CiviCRM should have created the first contribution
        $contributeParams = self::checkIfFirstPayment($contributeParams, $dao->frequency_unit);

        // Allow params to be modified via hook
        CRM_DirectDebit_Utils_Hook::alterSmartDebitContributionParams( $contributeParams );

        $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);
        $membershipRenew  = 0;
        $frequency    = $membership_renew_from = $membership_renew_to = 'NULL';

    // CRM_Core_Error::debug_log_message('contributeParams '. print_r($contributeParams, TRUE));
    // CRM_Core_Error::debug_log_message('contributeResult '. print_r($contributeResult, TRUE));
        if(!$contributeResult['is_error']) {

          $contributionID   = $contributeResult['id'];
          $contriReurID     = $contributeResult['values'][$contributionID]['contribution_recur_id'];
	  $columnExists	    = CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id');
          if($columnExists) {
	    $membershipQuery  = "SELECT `membership_id` FROM `civicrm_contribution_recur` WHERE `id` = %1";
	    $membershipID     = CRM_Core_DAO::singleValueQuery($membershipQuery, array( 1 => array( $contriReurID, 'Int' ) ) );
	  }

          // If membership ID is empty, check if we can get from contribution_membership table
          // Latest CiviCRM versions have contribution_recur_id in civicrm_membership table
          if (empty($membershipID)) {
            $columnExists	    = CRM_Core_DAO::checkFieldExists('civicrm_membership', 'contribution_recur_id');
            if($columnExists) {
              $membershipQuery  = "SELECT `id` FROM `civicrm_membership` WHERE `contribution_recur_id` = %1";
              $membershipID     = CRM_Core_DAO::singleValueQuery($membershipQuery, array( 1 => array( $contriReurID, 'Int' ) ) );
            }
          }

          // CRM_Core_Error::debug_log_message('membershipID = '. print_r($membershipID, TRUE));
          if (!empty($membershipID)) {

            $getMembership  = civicrm_api("Membership"
                                      ,"get"
                                      , array ('version'       => '3'
                                              ,'membership_id' => $membershipID
                                              )
                                      );

            $membershipEndDate   = $getMembership['values'][$membershipID]['end_date'];
            $membership_renew_from   = $getMembership['values'][$membershipID]['end_date'];

            $tempEndDateArray['from'] = date('d-M-Y', strtotime($membershipEndDate));

            $contributionReceiveDate = $contributeResult['values'][$contributionID]['receive_date'];

            $contributionReceiveDateString = date("Ymd", strtotime($contributionReceiveDate));
            $membershipEndDateString = date("Ymd", strtotime($membershipEndDate));

            $contributionRecurring = civicrm_api("ContributionRecur"
                                                ,"get"
                                                , array ('version' => '3'
                                                        ,'id'      => $contriReurID
                                                        )
                                                );

            $frequencyUnit = $contributionRecurring['values'][$contriReurID]['frequency_unit'];
            $frequency     = $contributionRecurring['values'][$contriReurID]['frequency_unit'];
            $frequencyInterval     = $contributionRecurring['values'][$contriReurID]['frequency_interval'];
            
            // CRM_Core_Error::debug_log_message('frequencyUnit = '. print_r($frequencyUnit, TRUE));
            if (!is_null($frequencyUnit)) {
              $membershipEndDateString = date("Y-m-d",strtotime(date("Y-m-d", strtotime($membershipEndDate)) . " +$frequencyInterval $frequencyUnit"));
              $membership_renew_to    = date("Y-m-d",strtotime(date("Y-m-d", strtotime($membershipEndDate)) . " +$frequencyInterval $frequencyUnit"));

              $membershipParams = array ( 'version'       => '3'
                                         , 'membership_id' => $membershipID
                                         , 'id'            => $membershipID
                                         , 'end_date'      => $membershipEndDateString
                                        );

              // Set a flag to be sent to hook, so that membership renewal can be skipped
              $membershipParams['renew'] = 1;

              // Allow membership update params to be modified via hook
              CRM_DirectDebit_Utils_Hook::handleSmartDebitMembershipRenewal( $membershipParams );

              // Membership renewal may be skipped in hook by setting 'renew' = 0
              if ($membershipParams['renew'] == 1 ) {

                // remove the renew kay from params array, which need to be passed to API
                $membershipRenew = $membershipParams['renew'];
                unset($membershipParams['renew']);

                $updatedMember = civicrm_api("Membership"
                                              ,"create"
                                              , $membershipParams
                                              );
                
                $tempEndDateArray['to'] = date('d -M-Y', strtotime($membershipEndDateString));
              }
            }
          }
          //MV, changes to display the more information after sync.
          $tempRenewalDate  = NULL;
          if( !empty($membershipID) && !empty($membershipEndDateString) ){
            $tempRenewalDate = implode(' <b> To </b> ', $tempEndDateArray);
          }
          // get contact display name to display in result screen
          $contactParams = array('version' => 3, 'id' => $contributeResult['values'][$contributionID]['contact_id']);
          $contactResult = civicrm_api('Contact', 'getsingle', $contactParams);

          $ids[$contributionID]= array(   'cid'           => $contributeResult['values'][$contributionID]['contact_id']
                                        , 'id'            => $contributionID
                                        , 'display_name'  => $contactResult['display_name']
                                        // , 'renewal_date'  => $tempRenewalDate
                                        // , 'amount'        => CRM_Utils_Money::format($contributeResult['values'][$contributionID]['total_amount'])
                                        // , 'frequency'     => ucwords($frequencyUnit)
                                        );
    // CRM_Core_Error::debug_log_message('ids[contributionID] '. print_r($ids[$contributionID], TRUE));
          $contactId  = $contactResult['id'] ;
          $contactName= $contactResult['display_name'];
          $amount     = $contributeResult['values'][$contributionID]['total_amount'];

          $keepSuccessResultsSQL = "
            INSERT Into veda_civicrm_smartdebit_import_success_contributions
            ( `transaction_id`, `contribution_id`, `contact_id`, `contact`, `amount`, `frequency`, `is_membership_renew`, `membership_renew_from`, `membership_renew_to` )
            VALUES ( %1, %2, %3, %4, %5, %6, %7, '{$membership_renew_from}', '{$membership_renew_to}' )
          ";
          
          $keepSuccessResultsParams = array(
            1 => array( $smartDebitRecord, 'String'),
            2 => array( $contributionID, 'Integer'),
            3 => array( $contactResult['id'], 'Integer'),
            4 => array( $contactResult['display_name'], 'String'),
            5 => array( $contributeResult['values'][$contributionID]['total_amount'], 'String'),
            6 => array( $frequency, 'String'),
            7 => array( $membershipRenew, 'Integer'),
            // 8 => array( $membership_renew_from, 'Date'),
            // 9 => array( $membership_renew_to, 'Date'),
          ); 
          // CRM_Core_Error::debug_log_message('keepSuccessResultsParams = '.print_r($keepSuccessResultsParams, TRUE));
          CRM_Core_DAO::executeQuery($keepSuccessResultsSQL, $keepSuccessResultsParams);

        }else{
          CRM_Core_Error::debug_log_message('contributeResult = '.print_r($contributeResult, TRUE));

        }
      }
      
      
    }

    // MV : valid contributions details store in custom table
    // commenting, Not anymore using to store contribution result_ids in setting table
    // $prevResults      = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids');

    // if($prevResults) {
    //   $compositeResults = array_merge($prevResults, $ids);
    //   CRM_Core_BAO_Setting::setItem($compositeResults,
    //     CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids'
    //   );
    // }
    // else {
    //   CRM_Core_BAO_Setting::setItem($ids,
    //     CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids'
    //   );
    // }


    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /*
   * Function to check if the contribution is first contribution
   * for the recurring contribution record
   */
  static function checkIfFirstPayment($params, $frequencyUnit = 'month') {

    if (empty($params['contribution_recur_id'])) {
      return;
    }

    // Get days difference to determine if this is first payment
    $days = self::daysDifferenceForFrequency($frequencyUnit);

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
        if ($dateDiff < $days) {
          $params['id'] = $contributionResult['id'];
          unset($params['source']);
        }
      }
    }
    // Get the recent pending contribution if there are more than 1 payment for the recurring record
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

  /*
   * Function to return number of days difference to check between current date
   * and payment date to determine if this is first payment or not
   */
  static function daysDifferenceForFrequency($frequencyUnit) {
    switch ($frequencyUnit) {
      case 'month':
        $days = 7;
        break;

      case 'year':
        $days = 30;
        break;

      default:
        $days = 7;
        break;
    }

    return $days;
  }

  /*
   * Function to get number of days difference between 2 dates
   */
  static function getDateDifference($date1, $date2) {
    return floor((strtotime($date1) - strtotime($date2))/(60*60*24));
  }
}

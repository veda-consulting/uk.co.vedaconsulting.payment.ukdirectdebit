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
    if ($state == 'done') {
      $status = 1;
      
      $ids  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids');
      $rejectedids  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'rejected_ids');
      $this->assign('ids', $ids);
      $this->assign('rejectedids', $rejectedids);
      $this->assign('totalValidContribution', count($ids));
      $this->assign('totalRejectedContribution', count($rejectedids));
      
      
    }
    $this->assign('status', $status);
  }
  
  public function buildQuickForm() {
    
    $auddisDate = CRM_Utils_Array::value('date', $_GET, '');
    $this->add('hidden', 'auddisDate', $auddisDate);
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
    $auddisDate = $params['auddisDate'];
    $financialType    = CRM_Core_BAO_Setting::getItem('UK Direct Debit', 'financial_type');
    
    // Check financialType is set in the civicrm_setting table
    if(empty($financialType)) {
      CRM_Core_Session::setStatus(ts('Make sure financial Type is set in the setting'), Error, 'error');
      return FALSE;
    }
    
    $runner = self::getRunner($auddisDate);
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure smart debit settings are correctly configured in the payment processor setting page'));
    }
  }
  
  static function getRunner($auddisDate = NULL) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));    
    
    // List of auddis files
    $auddisArray      = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis();
    
    // Find the relevant auddis file
    $auddisDetails    = CRM_DirectDebit_Form_Auddis::getRightAuddisFile($auddisArray, $auddisDate);
    $auddisFile       = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis($auddisDetails['uri']);
    
    // Smart Debit Payment Records
    $smartDebitArray  = CRM_DirectDebit_Form_SyncSd::getSmartDebitPayments();
    
    $count  = count($smartDebitArray);
    
    // Set the Number of Rounds
    $rounds = ceil($count/self::BATCH_COUNT);
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start   = $i * self::BATCH_COUNT;
      $contactsarray  = array_slice($smartDebitArray, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      $task    = new CRM_Queue_Task(
        array('CRM_DirectDebit_Form_Confirm', 'syncSmartDebitRecords'),
        array(array($contactsarray), array($auddisDetails), array($auddisFile), $auddisDate),
        "Pulling smart debit - Contacts {$counter} of {$count}"
      );

      // Add the Task to the Queu
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
      
      // Reset the counter when sync starts
      $query1 = "UPDATE civicrm_setting SET value = NULL WHERE name = 'result_ids'"; 
      $query2 = "UPDATE civicrm_setting SET value = NULL WHERE name = 'rejected_ids'"; 
      
      CRM_Core_DAO::executeQuery($query1);
      CRM_Core_DAO::executeQuery($query2);
      
      // Add contributions for rejected payments with the status of 'failed'
      $ids = array();
      foreach ($auddisFile as $key => $value) {
      
        $sql = "
          SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id  
          FROM civicrm_contribution_recur ctrc 
          INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id) 
          WHERE ctrc.trxn_id = %1";

        $params = array( 1 => array( $value['reference'], 'String' ) );
        $dao = CRM_Core_DAO::executeQuery( $sql, $params);
        
        $financialType    = CRM_Core_BAO_Setting::getItem('UK Direct Debit', 'financial_type');
        $financialTypeID  = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $financialType, 'id', 'name');
        
        if ($dao->fetch()) {
          $contributeParams = 
          array(
            'version'                => 3,
            'contact_id'             => $dao->contact_id,
            'contribution_recur_id'  => $dao->contribution_recur_id,
            'total_amount'           => $dao->amount,
            'invoice_id'             => md5(uniqid(rand(), TRUE )),
            'trxn_id'                => $value['reference'].'/'.$auddisDate,
            'financial_type_id'      => $financialTypeID,
            'payment_instrument_id'  => $dao->payment_instrument_id,
            'contribution_status_id' => 4,
          );

          $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);
          
          if(!$contributeResult['is_error']) {
            $contributionID   = $contributeResult['id'];
            $ids[$contributionID]= array('cid' => $contributeResult['values'][$contributionID]['contact_id'], 'id' => $contributionID);
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
      
      $doWork = TRUE;
      
      // Check the status of smart debit 
      if(!($smartDebitRecord['current_state'] == 10 || $smartDebitRecord['current_state'] == 1)) {
        $doWork = FALSE;
        continue;
      }
      // Check the start date with the report generation date
      if(strtotime($smartDebitRecord['start_date']) > strtotime($auddisDetails['report_generation_date'])) {
        $doWork = FALSE;
        continue;
      }
      // Check the rejection
      foreach ($auddisFile as $key => $auddisEntry) {
        if($auddisEntry['reference'] == $smartDebitRecord['reference_number'] ) {
          $doWork = FALSE;
          break;
        }
      }

      if($doWork) {

        $sql = "
          SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id  
          FROM civicrm_contribution_recur ctrc 
          INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id) 
          WHERE ctrc.trxn_id = %1";

        $params = array( 1 => array( $smartDebitRecord['reference_number'], 'String' ) );
        $dao = CRM_Core_DAO::executeQuery( $sql, $params);
        
        $financialType    = CRM_Core_BAO_Setting::getItem('UK Direct Debit', 'financial_type');
        $financialTypeID  = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $financialType, 'id', 'name');

        if ($dao->fetch()) {
          $contributeParams = 
          array(
            'version'                => 3,
            'contact_id'             => $dao->contact_id,
            'contribution_recur_id'  => $dao->contribution_recur_id,
            'total_amount'           => $dao->amount,
            'invoice_id'             => md5(uniqid(rand(), TRUE )),
            'trxn_id'                => $smartDebitRecord['reference_number'].'/'.$auddisDate,
            'financial_type_id'      => $financialTypeID,
            'payment_instrument_id'  => $dao->payment_instrument_id,
          );

          $contributeResult = civicrm_api('Contribution', 'create', $contributeParams);
          
          if(!$contributeResult['is_error']) {
            $contributionID   = $contributeResult['id'];
            $ids[$contributionID]= array('cid' => $contributeResult['values'][$contributionID]['contact_id'], 'id' => $contributionID);
          }
        }
      }


    }
    
    $prevResults      = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids');
    
    if($prevResults) {
      $compositeResults = array_merge($prevResults, $ids);
      CRM_Core_BAO_Setting::setItem($compositeResults,
        CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids'
      );
    }
    else {
      CRM_Core_BAO_Setting::setItem($ids,
        CRM_DirectDebit_Form_Confirm::SD_SETTING_GROUP, 'result_ids'
      );
    }
          
    
    return CRM_Queue_Task::TASK_SUCCESS;
  }
  

  
}

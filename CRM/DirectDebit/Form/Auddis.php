<?php

  require_once 'CRM/Core/Form.php';
  require_once 'CRM/Core/Session.php';
  require_once 'CRM/Core/PseudoConstant.php';
    
class CRM_DirectDebit_Form_Auddis extends CRM_Core_Form {
  
  function preProcess() {
        parent::preProcess();
  } 
  
  function buildQuickForm() {
    
    $auddisDate = CRM_Utils_Array::value('date', $_GET, '');
    $auddisArray = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis();
    
    $auddisDetails  = self::getRightAuddisFile($auddisArray, $auddisDate);
    
    $auddisFile = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis($auddisDetails['uri']);
    
    CRM_Core_Error::debug_log_message( 'buildQuickForm $auddisFile= '. print_r($auddisFile, true), $out = false );


    $newAuddisArray = array();
    
    // Display the rejected payments
    foreach ($auddisFile as $key => $value) {
      
      $sql = "
        SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit  
        FROM civicrm_contribution_recur ctrc 
        LEFT JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id) 
        WHERE ctrc.trxn_id = %1";

        $params = array( 1 => array( $value['reference'], 'String' ) );
        $dao = CRM_Core_DAO::executeQuery( $sql, $params);

        if ($dao->fetch()) {

          $newAuddisArray[$key]['contribution_recur_id']    = $dao->contribution_recur_id;
          $newAuddisArray[$key]['contact_id']               = $dao->contact_id;
          $newAuddisArray[$key]['contact_name']             = $dao->display_name;
          $newAuddisArray[$key]['start_date']               = $dao->start_date;
          $newAuddisArray[$key]['frequency']                = $dao->frequency_unit;
          $newAuddisArray[$key]['amount']                   = $dao->amount;
          $newAuddisArray[$key]['contribution_status_id']   = $dao->contribution_status_id;
          $newAuddisArray[$key]['transaction_id']           = $dao->trxn_id;
          $newAuddisArray[$key]['reference']                = $value['reference'];
          $newAuddisArray[$key]['reason-code']              = $value['reason-code'];

        }

    }
    
    // Calculate the total rejected
    $totalRejected = 0;
    foreach ($newAuddisArray as $key => $value) {
      $totalRejected += $value['amount'];
    }
    $totalRejected='£'.number_format((float)$totalRejected, 2, '.', '');
    $this->assign('totalRejected', $totalRejected);
    
    $smartDebitArray = CRM_DirectDebit_Form_SyncSd::getSmartDebitPayments();
    
    $listArray = array();
    $notStarted = 0;
    $notInLive  = 0;
    
    // Display the valid payments
    foreach ($smartDebitArray as $key => $smartDebitRecord) {
      $doWork = TRUE;

      if(!($smartDebitRecord['current_state'] == 10 || $smartDebitRecord['current_state'] == 1)) {
        $notInLive ++ ;
        $doWork = FALSE;
        continue;
      }
      // Check the start date with the report generation date
      if(strtotime($smartDebitRecord['start_date']) > strtotime($auddisDetails['report_generation_date'])) {
        $notStarted ++;
        $doWork = FALSE;
        continue;
      }

      // Check the rejection
      foreach ($auddisFile as $auddisEntry) {
        if($auddisEntry['reference'] == $smartDebitRecord['reference_number'] ) {
          $doWork = FALSE;
          break;
        }
      }

      if($doWork) {

        $sql = "
          SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit  
          FROM civicrm_contribution_recur ctrc 
          INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id) 
          WHERE ctrc.trxn_id = %1";

        $params = array( 1 => array( $smartDebitRecord['reference_number'], 'String' ) );
        $dao = CRM_Core_DAO::executeQuery( $sql, $params);

        if ($dao->fetch()) {
          
            $listArray[$key]['contribution_recur_id'] = $dao->contribution_recur_id;
            $listArray[$key]['contact_id']            = $dao->contact_id;
            $listArray[$key]['contact_name']          = $dao->display_name;
            $listArray[$key]['start_date']            = $dao->start_date;
            $listArray[$key]['frequency']             = $dao->frequency_unit;
            $listArray[$key]['amount']                = $dao->amount;
            $listArray[$key]['contribution_status_id']    = $dao->contribution_status_id;
            $listArray[$key]['transaction_id']        = $dao->trxn_id;

        }
      }
    }

    $totalSmartDebitPayments  = count($smartDebitArray);
    $validPayments            = count($listArray);
    $differnce                = $totalSmartDebitPayments - $notStarted - $validPayments - $notInLive;
    
    if($differnce>0){
      
      // Add membership_id column in recur table if not exists already
      $columnExists = CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id');
      if(!$columnExists) {
        $query = "
          ALTER TABLE civicrm_contribution_recur
          ADD membership_id int(10) unsigned AFTER contact_id,
          ADD CONSTRAINT FK_civicrm_contribution_recur_membership_id
          FOREIGN KEY(membership_id) REFERENCES civicrm_membership(id) ON DELETE CASCADE ON UPDATE RESTRICT";

        CRM_Core_DAO::executeQuery($query);
      }
      $message = "Found some data fix issues. Please do it by clicking the 'Data Fix' button and then do the sync";
      CRM_Core_Session::setStatus($message);
      
    }
    
    $redirectUrlBack      = CRM_Utils_System::url('civicrm/directdebit/syncsd', 'reset=1');
    $redirectUrlContinue  = CRM_Utils_System::url('civicrm/directdebit/syncsd/confirm', 'date=' . $auddisDate. '&reset=1');
    $fixUrl               = CRM_Utils_System::url('civicrm/smartdebit/reconciliation/list', 'checkMissingFromCivi=1&sync=1',TRUE, NULL, FALSE);
    
    $this->addButtons(array(
            array(
              'type' => 'next',
              'js' => array('onclick' => "location.href='{$redirectUrlContinue}'; return false;"),
              'name' => ts('Continue'),
              ),
            array(
              'type' => 'back',
              'js' => array('onclick' => "location.href='{$redirectUrlBack}'; return false;"),
              'name' => ts('Cancel'),
            ),
            array(
              'type' => 'submit',
              'js' => array('onclick' => "location.href='{$fixUrl}'; return false;"),
              'name' => ts('Data Fix'),
            )

          )
    );
              
    $total = 0;
    foreach ($listArray as $value) {
      $total += $value['amount'];
    }
    $total='£'.number_format((float)$total, 2, '.', '');
    
    
    $this->assign('newAuddisArray', $newAuddisArray);
    $this->assign('listArray', $listArray);
    $this->assign('total', $total);
    
    parent::buildQuickForm();
  }
  
  function postProcess() {
    
    parent::postProcess();
  }
  
  static function getRightAuddisFile($auddisArray = array(), $auddisDate = NULL) {
    $auddisDetails = array();
    if($auddisArray && $auddisDate) {
     foreach ($auddisArray as $key => $auddis) {
       if(strtotime($auddisDate) == strtotime(substr($auddis['report_generation_date'], 0, 10))){
         $auddisDetails['auddis_id']              = $auddis['auddis_id'];
         $auddisDetails['report_generation_date'] = substr($auddis['report_generation_date'], 0, 10);
         $auddisDetails['uri']                    = $auddis['@attributes']['uri'];
         break;
       }

     }
    }
    return $auddisDetails;
  }
    
    
  
}

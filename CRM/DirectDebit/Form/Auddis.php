<?php

  require_once 'CRM/Core/Form.php';
  require_once 'CRM/Core/Session.php';
  require_once 'CRM/Core/PseudoConstant.php';
    
class CRM_DirectDebit_Form_Auddis extends CRM_Core_Form {
  
  function preProcess() {
        parent::preProcess();
  } 
  
  function buildQuickForm() {
    
    $auddisDates = CRM_Utils_Request::retrieve('auddisDates', 'String', $this, false);
      
    $auddisArray = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis();
    if($auddisDates) {
      foreach ($auddisDates as $auddisDate) {
        $auddisDetails  = self::getRightAuddisFile($auddisArray, $auddisDate);
        $auddisFiles[] = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis($auddisDetails['uri']);
      }
    }
    
    // Display the rejected payments
    $newAuddisArray = array();
    $key = 0;
    foreach ($auddisFiles as $auddisFile) {
      foreach ($auddisFile as $inside => $value) {

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
            $key++;
          }

      }
    }
    
    // Calculate the total rejected
    $totalRejected = 0;
    foreach ($newAuddisArray as $key => $value) {
      $totalRejected += $value['amount'];
    }
    $totalRejected='£'.number_format((float)$totalRejected, 2, '.', '');
    $this->assign('totalRejected', $totalRejected);
    
    $listArray = array();
    $notStarted = 0;
    $notInLive  = 0;
    
    // Display the valid payments
    $transactionIdList = "'dummyId'";
    $contributintrxnId = "'dummyId'";
    $selectQuery = "SELECT `transaction_id` as trxn_id, receive_date as receive_date FROM `veda_civicrm_smartdebit_import`";
    $dao = CRM_Core_DAO::executeQuery($selectQuery);
    while($dao->fetch()) {
      $transactionIdList .= ", '".$dao->trxn_id."' "; // Transaction ID
      $contributintrxnId .= ", '".$dao->trxn_id.'/'.CRM_Utils_Date::processDate($dao->receive_date)."' ";
    }
    
    $contributionQuery = "
        SELECT cc.contact_id, cc.total_amount, cc.trxn_id as cc_trxn_id, ctrc.trxn_id as ctrc_trxn_id
        FROM `civicrm_contribution` cc
        INNER JOIN civicrm_contribution_recur ctrc ON (ctrc.id = cc.contribution_recur_id)
        WHERE cc.`trxn_id` IN ( $contributintrxnId )";
    
      $dao = CRM_Core_DAO::executeQuery($contributionQuery);
      $contriTraIds = "'dummyId'";
      $processedIds = "'dummyId'";
      while($dao->fetch()){
        $processedIds .= ", '".$dao->ctrc_trxn_id."' ";
        $contriTraIds .= ", '".$dao->cc_trxn_id."' ";
      }
    
    $sql = "SELECT ctrc.id contribution_recur_id ,ctrc.contact_id , cont.display_name ,ctrc.start_date , ctrc.amount, ctrc.trxn_id , ctrc.frequency_unit, ctrc.payment_instrument_id  
      FROM civicrm_contribution_recur ctrc 
      INNER JOIN civicrm_contact cont ON (ctrc.contact_id = cont.id) 
      WHERE ctrc.trxn_id IN ($transactionIdList) AND ctrc.trxn_id NOT IN ( $processedIds )";

      $dao = CRM_Core_DAO::executeQuery($sql);
      $key = 0;
      while ($dao->fetch()) {

        $listArray[$key]['contribution_recur_id'] = $dao->contribution_recur_id;
        $listArray[$key]['contact_id']            = $dao->contact_id;
        $listArray[$key]['contact_name']          = $dao->display_name;
        $listArray[$key]['start_date']            = $dao->start_date;
        $listArray[$key]['frequency']             = $dao->frequency_unit;
        $listArray[$key]['amount']                = $dao->amount;
        $listArray[$key]['contribution_status_id']    = $dao->contribution_status_id;
        $listArray[$key]['transaction_id']        = $dao->trxn_id;

        $key++;

      }
      
      // Show the already processed contributions
    $contributionQuery = "
        SELECT cc.contact_id, cont.display_name, cc.total_amount, cc.trxn_id
        FROM `civicrm_contribution` cc
        INNER JOIN civicrm_contact cont ON (cc.contact_id = cont.id) 
        WHERE cc.`trxn_id` IN ( $contributintrxnId )";
      $dao = CRM_Core_DAO::executeQuery($contributionQuery);
      $existArray = array();
      $key = 0;
      while ($dao->fetch()) {
        $existArray[$key]['contribution_recur_id'] = $dao->contribution_recur_id;
        $existArray[$key]['contact_id']            = $dao->contact_id;
        $existArray[$key]['contact_name']          = $dao->display_name;
        $existArray[$key]['start_date']            = $dao->start_date;
        $existArray[$key]['frequency']             = $dao->frequency_unit;
        $existArray[$key]['amount']                = $dao->total_amount;
        $existArray[$key]['contribution_status_id']    = $dao->contribution_status_id;
        $existArray[$key]['transaction_id']        = $dao->trxn_id;

        $key++;

      }
    foreach ($auddisDates as $value) {
      $queryDates .= "auddisDates[]=".$value."&";
    }
    
    $redirectUrlBack      = CRM_Utils_System::url('civicrm/directdebit/syncsd', 'reset=1');
    $redirectUrlContinue  = CRM_Utils_System::url('civicrm/directdebit/syncsd/confirm');
    $this->addButtons(array(
            array(
              'type' => 'next',
              'js' => array('onclick' => "location.href='{$redirectUrlContinue}' + '?' + '{$queryDates}' ; return false;"),
              'name' => ts('Continue'),
              ),
            array(
              'type' => 'back',
              'js' => array('onclick' => "location.href='{$redirectUrlBack}'; return false;"),
              'name' => ts('Cancel'),
            ),
            
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
    $this->assign('existArray', $existArray);
    
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

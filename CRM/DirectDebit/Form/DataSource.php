<?php

class CRM_DirectDebit_Form_DataSource extends CRM_Core_Form {
  // DataSource Form
  // Path: civicrm/directdebit/syncsd/import

  public function buildQuickForm() {
    if(!CRM_Core_DAO::checkTableExists('veda_civicrm_smartdebit_import')) {
      $createSql = "CREATE TABLE `veda_civicrm_smartdebit_import` (
                   `id` int(10) unsigned NOT NULL AUTO_INCREMENT, 
                   `transaction_id` varchar(255) DEFAULT NULL,
                   `contact` varchar(255) DEFAULT NULL,
                   `amount` decimal(20,2) DEFAULT NULL,
                   `info` int(11) DEFAULT NULL,
                   `receive_date` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`)
         ) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=latin1";
      CRM_Core_DAO::executeQuery($createSql);
    }
    // if the table veda_civicrm_smartdebit_import exists, then empty it
    else {
      $emptySql = "TRUNCATE TABLE veda_civicrm_smartdebit_import";
      CRM_Core_DAO::executeQuery($emptySql);
    }

    #MV: to get the collection details
    $this->addDate('collection_date', ts('Collection Date'), FALSE, array('formatType' => 'custom'));
    $this->addButtons(array(
        array(
          'type' => 'next',
          'name' => ts('Continue >>'),
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ),
      )
    );
  }

  /**
   * Process the collection report
   *
   * @return void
   * @access public
   */
  public function postProcess() {
    #MV: amend to get the collections by date.
    $exportValues     = $this->controller->exportValues();
    $dateOfCollection = $exportValues['collection_date'];

    $aCollectionDate  = array();
    if(empty($dateOfCollection)){
      CRM_Core_Session::setStatus(ts('Please select the collection date'), ts('Smart Debit'), 'error');
      $url = CRM_Utils_System::url('civicrm/directdebit/syncsd/import', 'reset=1');
      CRM_Core_Session::singleton()->pushUserContext($url);
      return false;
    }
    else {
      $dateOfCollection = date('Y-m-d', strtotime($dateOfCollection));
      $aCollectionDate  = self::getSmartDebitCollectionReport( $dateOfCollection );
      $session = CRM_Core_Session::singleton();
      $session->set("collection_date", $dateOfCollection);
    }

    if( $aCollectionDate === false ){
      return false;
    }

    $tableName = "veda_civicrm_smartdebit_import";
    if(!empty($aCollectionDate)){
      foreach ($aCollectionDate as $key => $value) {
        $resultCollection = array(
          'transaction_id' => $value['reference_number'],
          'contact'        => $value['account_name'],
          'amount'         => $value['amount'],
          'receive_date'   => !empty($value['debit_date']) ? date('YmdHis', strtotime(str_replace('/', '-', $value['debit_date']))) : NULL,
        );
        $insertValue[] = " ( \"". implode('", "', $resultCollection) . "\" )";
      }
      $sql = " INSERT INTO `{$tableName}`
              (`transaction_id`, `contact`, `amount`, `receive_date`)
              VALUES ".implode(', ', $insertValue)."
              ";
      CRM_Core_DAO::executeQuery($sql);
    }
    $url = CRM_Utils_System::url('civicrm/directdebit/syncsd', 'reset=1'); // SyncSD form
    CRM_Core_Session::singleton()->pushUserContext($url);
  }
}


<?php

class CRM_DirectDebit_Form_DataSource extends CRM_Core_Form {

  private $defaultCsvToDbParams = array(
    'fieldsTerminatedBy' => ',',
    'ignoreLines' => 0,
    'linesTerminatedBy' => '\n',
    'optionallyEnclosedBy' => '"',
    'characterSet' => 'utf8',
  );

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
    // if the veda_civicrm_smartdebit_import, then empty it
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
   * Process the uploaded file
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
      $aCollectionDate  = self::getSmartDebitPayments( $dateOfCollection );
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
    $url = CRM_Utils_System::url('civicrm/directdebit/syncsd', 'reset=1');
    CRM_Core_Session::singleton()->pushUserContext($url);
  }

  #MV : added new function to get collections by date
  static function getSmartDebitPayments( $dateOfCollection ) {
    if( empty($dateOfCollection)){
      CRM_Core_Session::setStatus(ts('Please select the collection date'), ts('Smart Debit'), 'error');
      return false;
    }

    $userDetails = self::getSmartDebitUserDetails();
    $username    = CRM_Utils_Array::value('username', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('pslid', $userDetails);

    $collections = array();
    $url         = "https://secure.ddprocessing.co.uk/api/get_collection_report?query[service_user][pslid]=$pslid&query[debit_date]=$dateOfCollection";
    $response    = CRM_DirectDebit_Form_Sync::requestPost( $url, $username, $password );

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
      case 'OK':
        if (!isset($response['Successes']) || !isset($response['Rejects'])) {
          $url = CRM_Utils_System::url('civicrm/directdebit/syncsd/import');
          CRM_Core_Session::setStatus($response['Summary'], ts('Sorry'), 'error');
          CRM_Utils_System::redirect($url);
          return FALSE;
        }
        // Cater for a single response
        if (isset($response['Successes']['Success']['@attributes'])) {
          $collections[] = $response['Successes']['Success']['@attributes'];
        } else {
          foreach ($response['Successes']['Success'] as $key => $value) {
            $collections[] = $value['@attributes'];
          }
        }
        return $collections;
      case 'INVALID':
        $url = CRM_Utils_System::url('civicrm/directdebit/syncsd/import');
        CRM_Core_Session::setStatus($response['error'], ts('UK Direct Debit'), 'error');
        CRM_Utils_System::redirect($url);
        return FALSE;
      default:
        return false;
    }//end switch
  }

  static function getSmartDebitUserDetails() {
    $paymentProcessorType = CRM_Contribute_BAO_Contribution::buildOptions('payment_processor_type');
    $paymentProcessorTypeId = CRM_Utils_Array::key('Smart_Debit', $paymentProcessorType);
    $domainID               = CRM_Core_Config::domainID();

    if(empty($paymentProcessorTypeId)) {
      CRM_Core_Session::setStatus(ts('Make sure Payment Processor Type (Smart Debit) is set in Payment Processor setting'), ts('UK Direct Debit'), 'error');
      return FALSE;
    }

    $sql  = " SELECT user_name ";
    $sql .= " ,      password ";
    $sql .= " ,      signature ";
    $sql .= " FROM civicrm_payment_processor ";
    $sql .= " WHERE payment_processor_type_id = %1 ";
    $sql .= " AND is_test= %2 AND domain_id = %3";

    $params = array( 1 => array( $paymentProcessorTypeId, 'Integer' )
    , 2 => array( '0', 'Int' )
    , 3 => array( $domainID, 'Int' )
    );

    $dao    = CRM_Core_DAO::executeQuery( $sql, $params);
    $result = array();
    if ($dao->fetch()) {
      if(empty($dao->user_name) || empty($dao->password) || empty($dao->signature)) {
        CRM_Core_Session::setStatus(ts('Smart Debit API User Details Missing, Please check the Smart Debit Payment Processor is configured Properly'), ts('UK Direct Debit'), 'error');
        return FALSE;
      }
      $result   = array(
        'username' => $dao->user_name,
        'password' => $dao->password,
        'pslid'    => $dao->signature,
      );

    }
    return $result;
  }//end function
}


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
    
      $creatSql = "CREATE TABLE `veda_civicrm_smartdebit_import` (
                   `id` int(10) unsigned NOT NULL AUTO_INCREMENT, 
                   `transaction_id` varchar(255) DEFAULT NULL,
                   `contact` varchar(255) DEFAULT NULL,
                   `amount` decimal(20,2) DEFAULT NULL,
                   `info` int(11) DEFAULT NULL,
                   `receive_date` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`)
         ) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=latin1";
      
      CRM_Core_DAO::executeQuery($creatSql);
      
    }
    // if the veda_civicrm_smartdebit_import, then empty it
    else {
      $emptySql = "TRUNCATE TABLE veda_civicrm_smartdebit_import";
      CRM_Core_DAO::executeQuery($emptySql);
    }

    #MV: to get the collection details
    // $dateOfCollection = '2014-01-01';
    $this->addDate('collection_date', ts('Collection Date'), FALSE, array('formatType' => 'custom'));


    #end

    // //Setting Upload File Size
    // $config = CRM_Core_Config::singleton();
    // if ($config->maxImportFileSize >= 8388608) {
    //   $uploadFileSize = 8388608;
    // }
    // else {
    //   $uploadFileSize = $config->maxImportFileSize;
    // }
    // $uploadSize = round(($uploadFileSize / (1024 * 1024)), 2);

    // $this->assign('uploadSize', $uploadSize);

    // $this->add('file', 'uploadFile', ts('Import Data File'), 'size=30 maxlength=255', TRUE);

    // $this->setMaxFileSize($uploadFileSize);


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
    if(!empty($dateOfCollection)){
      $dateOfCollection = date('Y-m-d', strtotime($dateOfCollection));
      $aCollectionDate  = self::getSmartDebitPayments( $dateOfCollection );
    }

    if( $aCollectionDate === false ){
      return false;
    }

    // $fileName         = $this->controller->exportValue($this->_name, 'uploadFile');
    // $fileName         = $fileName['name'];

    // if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    //         $fileName = str_replace('\\', '\\\\', $fileName);
    // }
    // $session = CRM_Core_Session::singleton();
    // $config = CRM_Core_Config::singleton();
    // $params = $this->defaultCsvToDbParams;
    $tableName = "veda_civicrm_smartdebit_import";
    // $setFieldsSql = implode(', ', $setFields);
    // $columnFieldsSql = implode(', ', $columnFields);
    
    // foreach(array('transaction_id', 'contact', 'amount', 'info', 'receive_date') as $field) {
    //   $columnField = "@dummy";
    //   if($field !== null) {
    //       $setFields[] = "$field = @$field";
    //       $columnField = "@$field";

    //       $fieldsCreated[] = $field;
    //   }

    //   $columnFields[] = $columnField;
    // }
    // $setFieldsSql = implode(', ', $setFields);
    // $columnFieldsSql = implode(', ', $columnFields);
    
    // $sql = "LOAD DATA LOCAL INFILE '$fileName' INTO TABLE $tableName
    //         CHARACTER SET {$params['characterSet']}
    //         FIELDS TERMINATED BY '{$params['fieldsTerminatedBy']}'
    //             OPTIONALLY ENCLOSED BY '{$params['optionallyEnclosedBy']}'
    //         LINES TERMINATED BY '{$params['linesTerminatedBy']}'
    //         IGNORE {$params['ignoreLines']} LINES
    //         ($columnFieldsSql) SET {$setFieldsSql}";

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
    $redirectUrlForward      = CRM_Utils_System::url('civicrm/directdebit/syncsd', 'reset=1');
    CRM_Utils_System::redirect($redirectUrlForward);
    
  }
  /**
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   * @access public
   */
  public function getTitle() {
    return ts('Upload Data');
  }

  #MV : added new function to get collections by date
  static function getSmartDebitPayments( $dateOfCollection ) { 
    if( empty($dateOfCollection)){
      CRM_Core_Session::setStatus(ts('Please Select the Date of Collection'), Error, 'error');
      return false;
    }

    $userDetails = self::getSmartDebitUserDetails();
    $username    = CRM_Utils_Array::value('username', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('pslid', $userDetails);
  
    $collections = array();
    $url         = "https://secure.ddprocessing.co.uk/api/get_collection_report?query[service_user][pslid]=$pslid&query[debit_date]=$dateOfCollection";
    $response    = CRM_DirectDebit_Form_SyncSd::requestPost( $url, $username, $password );    

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
        case 'OK':

            $collections = array();

            // Cater for a single response
            if (isset($response['Successes']['Success']['@attributes'])) {
              $collections[] = $response['Successes']['Success']['@attributes'];
            } else {
              foreach ($response['Successes']['Success'] as $key => $value) {
                $collections[] = $value['@attributes'];
              }         
            }         
            return $collections;
            
        default:
            return false;
    }//end switch
   
  }

  static function getSmartDebitUserDetails(){

    $paymentProcessorType   = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
    $paymentProcessorTypeId = CRM_Utils_Array::key('Smart Debit', $paymentProcessorType);
    $domainID               = CRM_Core_Config::domainID();

    if(empty($paymentProcessorTypeId)) {
      CRM_Core_Session::setStatus(ts('Make sure Payment Processor Type (Smart Debit) is set in Payment Processor setting'), Error, 'error');
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
        CRM_Core_Session::setStatus(ts('Smart Debit API User Details Missing, Please check the Smart Debit Payment Processor is configured Properly'), Error, 'error');
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


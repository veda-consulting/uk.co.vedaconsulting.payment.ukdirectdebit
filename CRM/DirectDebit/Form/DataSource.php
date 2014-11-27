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
    //Setting Upload File Size
    $config = CRM_Core_Config::singleton();
    if ($config->maxImportFileSize >= 8388608) {
      $uploadFileSize = 8388608;
    }
    else {
      $uploadFileSize = $config->maxImportFileSize;
    }
    $uploadSize = round(($uploadFileSize / (1024 * 1024)), 2);

    $this->assign('uploadSize', $uploadSize);

    $this->add('file', 'uploadFile', ts('Import Data File'), 'size=30 maxlength=255', TRUE);

    $this->setMaxFileSize($uploadFileSize);


    $this->addButtons(array(
        array(
          'type' => 'upload',
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

    $fileName         = $this->controller->exportValue($this->_name, 'uploadFile');
    $fileName         = $fileName['name'];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $fileName = str_replace('\\', '\\\\', $fileName);
    }
    $session = CRM_Core_Session::singleton();
    $config = CRM_Core_Config::singleton();
    $params = $this->defaultCsvToDbParams;
    $tableName = "veda_civicrm_smartdebit_import";
    $setFieldsSql = implode(', ', $setFields);
    $columnFieldsSql = implode(', ', $columnFields);
    
    foreach(array('transaction_id', 'contact', 'amount', 'info', 'receive_date') as $field) {
      $columnField = "@dummy";
      if($field !== null) {
          $setFields[] = "$field = @$field";
          $columnField = "@$field";

          $fieldsCreated[] = $field;
      }

      $columnFields[] = $columnField;
    }
    $setFieldsSql = implode(', ', $setFields);
    $columnFieldsSql = implode(', ', $columnFields);
    
    $sql = "LOAD DATA LOCAL INFILE '$fileName' INTO TABLE $tableName
            CHARACTER SET {$params['characterSet']}
            FIELDS TERMINATED BY '{$params['fieldsTerminatedBy']}'
                OPTIONALLY ENCLOSED BY '{$params['optionallyEnclosedBy']}'
            LINES TERMINATED BY '{$params['linesTerminatedBy']}'
            IGNORE {$params['ignoreLines']} LINES
            ($columnFieldsSql) SET {$setFieldsSql}";
            
            CRM_Core_DAO::executeQuery($sql);
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

}


<?php

class CRM_DirectDebit_Form_Newdonation extends CRM_Core_Form {
  
  public $_contactID;
  
  public $_paymentProcessor = array();
  
  public $_id;
  
  public $_action;
  
  public $_paymentFields = array();
  
  public $_fields = array();
  
  public $_bltID = 5;
  
  public function preProcess() {
    // Check permission for action.
    if (!CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::ADD)) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }  
    // Get the contact id
    $this->_contactID = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    // Get the action.
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'add');  
    
    // Get the smart debit payment processor details
    $this->_paymentProcessor = self::getSmartDebitDetails();
   
  }
  
  public function buildQuickForm() {
    $totalAmount = $this->addMoney('amount', ts('Amount'), CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_Contribution', 'total_amount'), TRUE, 'currency', NULL);
    $this->add('text', "email-{$this->_bltID}", ts('Email Address'), CRM_Core_DAO::getAttribute('CRM_Core_DAO_Email', 'email'), TRUE );
    $this->addRule("email-{$this->_bltID}", ts('Email is not valid.'), 'email');  
    $frequencyUnit = $this->add('select', 'frequency_unit',
      ts('Frequency Unit'),
      array('' => ts('- select -')) + CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, FALSE, NULL, 'name'),
      TRUE, NULL
    );
    
    $financialType = $this->add('select', 'financial_type_id',
      ts('Financial Type'),
      array('' => ts('- select -')) + CRM_Contribute_PseudoConstant::financialType(),
      TRUE
    );
        
    $submitButton = array(
      array('type' => 'upload',
        'name' => ts('Confirm Direct Debit'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
        'isDefault' => TRUE,),
      array('type' => 'cancel',
      'name' => ts('Cancel'),)
    );
   
    require_once 'UK_Direct_Debit/Form/Main.php';
    $ddForm = new UK_Direct_Debit_Form_Main();
    $ddForm->buildOfflineDirectDebit( $this );
    $defaults['ddi_reference'] = $ddForm::getDDIReference();
    $this->setDefaults($defaults);
    $this->assign('bltID', $this->_bltID);
    
    $this->addFormRule(array('CRM_DirectDebit_Form_Newdonation', 'formRule'), $this);
    $this->addButtons($submitButton);

  }
  
  public function setDefaultValues() {
    $defaults			      = array();
    list($displayName, $email)	      = CRM_Contact_BAO_Contact_Location::getEmailDetails($this->_contactID);
    $defaults['email-'.$this->_bltID] = $email;
    return $defaults;
  }
  
  public static function formRule($fields, $files, $self) {
    $errors = array ();      
    require_once self::getSmartDebitPaymentPath();
    $validateOutput = uk_co_vedaconsulting_payment_smartdebitdd::validatePayment($fields, $files, $self);
    if ($validateOutput['is_error'] == 1) {
      $errors['_qf_default'] = $validateOutput['error_message'];
    }
    return $errors ? $errors : TRUE;      
  }
  
  function postProcess() {
    $params		  = $this->controller->exportValues($this->_name);
    $params['contactID']  = $this->_contactID;
    require_once self::getSmartDebitPaymentPath();      
    $smartDebitResponse	  = uk_co_vedaconsulting_payment_smartdebitdd:: doDirectPayment($params);
    if ($smartDebitResponse['is_error'] == 1) {
        CRM_Core_Session::singleton()->pushUserContext($params['entryURL']);
        return;
    }
    $start_date		  = date('Y-m-d', strtotime($smartDebitResponse['start_date']));
    $trxn_id		  = $smartDebitResponse['trxn_id'];
    list($y, $m, $d)	  = explode('-', $smartDebitResponse['start_date']);
    
    // Build recur params
    $recurParams = array(
	'contact_id'		=>  $this->_contactID,
	'create_date'		=>  date('YmdHis'),
	'modified_date'		=>  date('YmdHis'),
	'start_date'		=>  CRM_Utils_Date::processDate($start_date),
	'amount'		=>  $params['amount'],
	'frequency_unit'	=>  self::translateSmartDebitFrequencyUnit($smartDebitResponse['frequency_type']),
	'frequency_interval'	=>  1,
	'payment_processor_id'	=>  $this->_paymentProcessor['id'],
	'contribution_status_id'=>  CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress'),
	'trxn_id'		=>  $trxn_id,
	'financial_type_id'	=>  $params['financial_type_id'],
	'auto_renew'		=> '1', // Make auto renew
	'cycle_day'		=> $d,
	'currency'		=> 'GBP',//Smart Debit supports UK currency
	'processor_id'          => $trxn_id,
	'payment_instrument_id' => UK_Direct_Debit_Form_Main::getDDPaymentInstrumentID(),
    );
    $recurring		 = CRM_Contribute_BAO_ContributionRecur::add($recurParams);

    CRM_Core_Session::setStatus(ts('Created Direct Debit with reference: '). $recurParams['trxn_id'], ts('Direct Debit'), 'success');
    $url = CRM_Utils_System::url('civicrm/contact/view/contributionrecur', 'reset=1&id='.$recurring->id.'&cid='.$recurring->contact_id.'&context=contribution');
    CRM_Core_Session::singleton()->pushUserContext($url);
  }
  

  static function translateSmartDebitFrequencyUnit($smartDebitFrequency) {
    if ($smartDebitFrequency == 'Q') {
      return('month' );
    }
    if ($smartDebitFrequency == 'Y') {
      return('year' );
    }
    return('month' );
  }
    
  static function getSmartDebitDetails(){
    $paymentProcessorType   = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
    $paymentProcessorTypeId = CRM_Utils_Array::key('Smart_Debit', $paymentProcessorType);
    $domainID               = CRM_Core_Config::domainID();

    if(empty($paymentProcessorTypeId)) {
      CRM_Core_Session::setStatus(ts('Make sure Payment Processor Type (Smart Debit) is set in Payment Processor setting'), Error, 'error');
      return FALSE;
    }

    $sql  = " SELECT user_name ";
    $sql .= " ,      password ";
    $sql .= " ,      signature ";
    $sql .= " ,      billing_mode ";
    $sql .= " ,      payment_type ";
    $sql .= " ,      url_api ";
    $sql .= " ,      id ";
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
        'user_name'	  => $dao->user_name,
        'password'	  => $dao->password,
        'signature'	  => $dao->signature,
        'billing_mode'    => $dao->billing_mode,
        'payment_type'    => $dao->payment_type,
        'url_api'	  => $dao->url_api,
        'id'		  => $dao->id,
      );

    }
    return $result;

  }//end function
    
  static function getSmartDebitPaymentPath() {
    $config   = CRM_Core_Config::singleton();
    $extenDr  = $config->extensionsDir;
    $path = $extenDr . DIRECTORY_SEPARATOR .'uk.co.vedaconsulting.payment.smartdebitdd' .DIRECTORY_SEPARATOR.'smart_debit_dd.php';
    if (!file_exists($path)) {
      CRM_Core_Error::debug_log_message('ukdirectdebit - you need to install smartdebit extension (file not found '.$path.')');
      CRM_Core_Session::setStatus('Please install smartdebit extension', 'UK Direct Debit', 'alert');
    }
    return $path;
  }
}



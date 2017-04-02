<?php

class CRM_DirectDebit_Form_Newdonation extends CRM_Core_Form {
  // FIXME: What is this form used for?

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
    $this->_paymentProcessor = CRM_DirectDebit_Auddis::getSmartDebitDetails();

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

    // Build Direct Debit Payment Fields including Billing
    $ddForm = new CRM_DirectDebit_Form_Main();
    $ddForm->buildOfflineDirectDebit( $this );
    // Required for validation
    $defaults['ddi_reference'] = CRM_DirectDebit_Base::getDDIReference();
    $this->setDefaults($defaults);
    // Required for billing blocks to be displayed
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
    $validateOutput = CRM_Core_Payment_Smartdebitdd::validatePayment($fields, $files, $self);
    if ($validateOutput['is_error'] == 1) {
      $errors['_qf_default'] = $validateOutput['error_message'];
    }
    return $errors ? $errors : TRUE;
  }

  function postProcess() {
    $params		  = $this->controller->exportValues($this->_name);
    $params['contactID']  = $this->_contactID;

    $smartDebitResponse	  = CRM_Core_Payment_Smartdebitdd::doDirectPayment($params);
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
      'frequency_unit'	=>  CRM_DirectDebit_Base::translateSmartDebitFrequencyUnit($smartDebitResponse['frequency_type']),
      'frequency_interval'	=>  1,
      'payment_processor_id'	=>  $this->_paymentProcessor['id'],
      'contribution_status_id'=>  CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress'),
      'trxn_id'		=>  $trxn_id,
      'financial_type_id'	=>  $params['financial_type_id'],
      'auto_renew'		=> '1', // Make auto renew
      'cycle_day'		=> $d,
      'currency'		=> 'GBP',//Smart Debit supports UK currency
      'processor_id'          => $trxn_id,
      'payment_instrument_id' => CRM_DirectDebit_Base::getDDPaymentInstrumentID(),
    );
    $recurring		 = CRM_Contribute_BAO_ContributionRecur::add($recurParams);

    CRM_Core_Session::setStatus(ts('Created Direct Debit with reference: '). $recurParams['trxn_id'], ts('Direct Debit'), 'success');
    $url = CRM_Utils_System::url('civicrm/contact/view/contributionrecur', 'reset=1&id='.$recurring->id.'&cid='.$recurring->contact_id.'&context=contribution');
    CRM_Core_Session::singleton()->pushUserContext($url);
  }
}



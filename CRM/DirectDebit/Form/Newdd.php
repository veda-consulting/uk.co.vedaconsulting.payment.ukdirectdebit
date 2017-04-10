<?php

/**
 * Class CRM_DirectDebit_Form_Newdd
 * This form is accessed at civicrm/directdebit/new
 * It allows for creation of a new membership direct debit via the backend
 */
class CRM_DirectDebit_Form_Newdd extends CRM_Core_Form {
  public $_contactID;

  public $_paymentProcessor = array();

  public $_id;

  public $_action;

  public $_paymentFields = array();

  public $_fields = array();

  public $_bltID = 5;

  public $_membershipAmount;

  public function preProcess() {
    // Check permission for action.
    if (!CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::ADD)) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }
    // Get the contact id
    $this->_contactID = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    // Get the action.
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'add');
    // Get the membership id
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE);
    // Get the smart debit payment processor details
    $this->_paymentProcessor = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();

  }

  public function buildQuickForm() {
    // Membership amount
    $totalAmount = $this->addMoney('amount', ts('Amount'), CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_Contribution', 'total_amount'), TRUE, 'currency', NULL);
    $this->add('text', "email-{$this->_bltID}", ts('Email Address'), CRM_Core_DAO::getAttribute('CRM_Core_DAO_Email', 'email'), TRUE );
    $this->addRule("email-{$this->_bltID}", ts('Email is not valid.'), 'email');
    // Membership Frequench Month/Year
    $this->add('hidden', 'frequency_unit');
    $this->add('hidden', 'frequency_interval');

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
    $ddForm->buildDirectDebitForm( $this );
    // Required for validation
    $defaults['ddi_reference'] = CRM_DirectDebit_Base::getDDIReference();
    $this->setDefaults($defaults);
    // Required for billing blocks to be displayed
    $this->assign('bltID', $this->_bltID);

    $this->addFormRule(array('CRM_DirectDebit_Form_Newdd', 'formRule'), $this);
    $this->addButtons($submitButton);

  }

  public function setDefaultValues() {
    $defaults			      = array();
    $contactDetails		      = self::getContactDetails($this->_id);
    $defaults['email-'.$this->_bltID] = $contactDetails['email'];
    $defaults['amount']		      = $contactDetails['amount'];
    $defaults['frequency_unit']	      = $contactDetails['frequency_unit'];
    $this->_membershipAmount	      = $contactDetails['amount'];
    return $defaults;
  }

  public static function formRule($fields, $files, $self) {
    $errors = array ();
    if($fields['amount'] < $self->_membershipAmount) {
      $errors['amount'] = ts('Amount can not be less than corresponding membership amount');
      return $errors;
    }
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
    $contributionDetails  = self::getContactDetails($this->_id);
    $contributionID	  = $contributionDetails['contribution_id'];
    $invoiceID		  = $contributionDetails['invoice_id'];
    $financial_type_id    = $contributionDetails['financial_type_id'];
    $contributionRecurID  = $contributionDetails['contribution_recur_id'];
    $isRecur		  = 1;

    if(empty($contributionRecurID)) {
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
        'invoice_id'		=>  $invoiceID,
        'financial_type_id'	=>  $financial_type_id,
        'auto_renew'		=> '1', // Make auto renew
        'processor_id'          =>  $trxn_id,
        'payment_instrument_id' => CRM_DirectDebit_Base::getDefaultPaymentInstrumentID(),//Direct Debit
      );

      $recurring		  = CRM_Contribute_BAO_ContributionRecur::add($recurParams);
      $contributionRecurID	  = $recurring->id;

      if ($contributionRecurID && $contributionID) {
        // Update Contribution trxn_id, recur_id, receive_date if already contribution was there to this membership
        $contributionUpdateParams = array(
          1 => array($contributionRecurID, 'Int'),
          2 => array($trxn_id, 'String'),
          3 => array(CRM_Utils_Date::processDate($start_date), 'String'),
          4 => array($contributionID, 'Int'));
        CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution SET contribution_recur_id = %1, trxn_id = %2, receive_date = %3 WHERE id = %4', $contributionUpdateParams);

      }
      if ($contributionRecurID && empty($contributionID)) {
        $contributionParams     = array();
        $contributionParams['financial_type_id']      = $financial_type_id;
        $contributionParams['contact_id']             = $this->_contactID;
        $contributionParams['source']                 = 'Offline Membership Direct Debit';
        $contributionParams['total_amount']           = $params['amount'];
        $contributionParams['invoice_id']             = $invoiceID;
        $contributionParams['trxn_id']                = $trxn_id;
        $contributionParams['contribution_recur_id']  = $contributionRecurID;
        $contributionParams['contribution_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
        $contributionParams['receive_date']           = CRM_Utils_Date::processDate($start_date);
        $contribution                                 = CRM_Contribute_BAO_Contribution::add($contributionParams);
        $contributionID                               = $contribution->id;
        // Attach Contribution to Membership
        CRM_Member_BAO_MembershipPayment::create(array(
          'membership_id' => $this->_id,
          'contribution_id' => $contributionID,
        ));
      }

      // Update Membership column in recurring table
      CRM_Core_DAO::executeQuery('UPDATE civicrm_membership SET contribution_recur_id = %1 WHERE id = %2', array(1=>array($contributionRecurID, 'Int'), 2=>array($this->_id, 'Int')));

    }

    $paymentProcessorType = urlencode( 'Smart_Debit' );
    $paymentType	  = urlencode( $this->_paymentProcessor['payment_type']);
    $membershipID         = urlencode( $this->_id );
    $contactID            = urlencode( $this->_contactID );
    $invoiceID            = urlencode( $invoiceID );
    $amount               = urlencode( $params['amount'] );
    $trxn_id              = urlencode( $trxn_id );
    $collection_day       = urlencode( $params['preferred_collection_day'] );

    CRM_Core_Error::debug_log_message( 'paymentProcessorType='.$paymentProcessorType);
    CRM_Core_Error::debug_log_message( 'paymentType='.$paymentType);
    CRM_Core_Error::debug_log_message( 'membershipID='.$membershipID);
    CRM_Core_Error::debug_log_message( 'contributionID='.$contributionID);
    CRM_Core_Error::debug_log_message( 'contactID='.$contactID);
    CRM_Core_Error::debug_log_message( 'invoiceID='.$invoiceID);
    CRM_Core_Error::debug_log_message( 'amount='.$amount);
    CRM_Core_Error::debug_log_message( 'isRecur='.$isRecur);
    CRM_Core_Error::debug_log_message( 'trxn_id='.$trxn_id);
    CRM_Core_Error::debug_log_message( 'start_date='.$start_date);
    CRM_Core_Error::debug_log_message( 'collection_day='.$collection_day);
    CRM_Core_Error::debug_log_message( 'contributionRecurID:' .$contributionRecurID );
    CRM_Core_Error::debug_log_message( 'CIVICRM_UF_BASEURL='.CIVICRM_UF_BASEURL);

    $query = "processor_name=".$paymentProcessorType."&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&membershipID=".$membershipID."&invoice=".$invoiceID."&mc_gross=".$amount."&payment_status=Completed&txn_type=recurring_payment&contributionRecurID=$contributionRecurID&txn_id=$trxn_id&first_collection_date=$start_date&collection_day=$collection_day";

    CRM_Core_Error:: debug_log_message( 'uk_direct_debit_civicrm_postProcess query = '.$query);

    // Get the recur ID for the contribution
    $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
    CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_postProcess url='.$url);
    call_CiviCRM_IPN($url);

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

  static function getContactDetails($membershipID) {
    if (empty($membershipID)) {
      return;
    }
    $query = "
      SELECT cc.id, cc.invoice_id, cmt.financial_type_id, ce.email, cmt.minimum_fee, cm.contribution_recur_id, cmt.duration_unit
      FROM civicrm_membership cm 
      INNER JOIN civicrm_membership_type cmt ON cmt.id = cm.membership_type_id
      LEFT JOIN civicrm_membership_payment cmp ON cmp.membership_id = cm.id
      LEFT JOIN civicrm_contribution cc ON cc.id = cmp.contribution_id      
      LEFT JOIN civicrm_email ce ON (ce.contact_id = cm.contact_id AND ce.is_primary = 1)
      WHERE cm.id = %1";
    $dao = CRM_Core_DAO::executeQuery($query, array(1 => array($membershipID, 'Int')));
    $contactDetails = array();
    if ($dao->fetch()) {
      $contactDetails['contribution_id']	= $dao->id;
      $contactDetails['amount']			= $dao->minimum_fee;
      $contactDetails['invoice_id']		= $dao->invoice_id;
      $contactDetails['financial_type_id']	= $dao->financial_type_id;
      $contactDetails['email']			= $dao->email;
      $contactDetails['contribution_recur_id']  = $dao->contribution_recur_id;
      $contactDetails['frequency_unit']		= $dao->duration_unit;
    }
    return $contactDetails;
  }
}



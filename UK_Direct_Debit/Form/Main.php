<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

/**
 * This class generates form components for processing Event  
 * 
 */
require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/BAO/Setting.php';

class UK_Direct_Debit_Form_Main extends CRM_Core_Form
{
  CONST
    SETTING_GROUP_UK_DD_NAME = 'UK Direct Debit'
   ,DD_SIGN_UP_ACITIVITY_TYPE_ID = 46; /* TODO get this from DB based on civicrm setting?. Also needs creating on Install */

  function rand_str( $len )
  {
    // The alphabet the random string consists of
    $abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    // The default length the random key should have
    $defaultLength = 3;

    // Ensure $len is a valid number
    // Should be less than or equal to strlen( $abc ) but at least $defaultLength
    $len = max( min( intval( $len ), strlen( $abc )), $defaultLength );

    // Return snippet of random string as random string
    return substr( str_shuffle( $abc ), 0, $len );
  }
  
  function getDDIReference() {
      
    $tempDDIReference = self::rand_str(16);
      
    CRM_Core_DAO::executeQuery("
        INSERT INTO civicrm_direct_debit
        (ddi_reference)
        VALUES
        (%1)
        ", array(1 => array((string)$tempDDIReference , 'String'))
    ); 

    // Now get the ID for the record we've just created and create a sequenced DDI Reference Number
    $selectSql  = " SELECT id "; 
    $selectSql .= " FROM civicrm_direct_debit cdd ";
    $selectSql .= " WHERE cdd.ddi_reference = %1 ";
    $selectParams  = array( 1 => array( $tempDDIReference , 'String' ));
    $dao = CRM_Core_DAO::executeQuery( $selectSql, $selectParams );
    $dao->fetch();    
    
    $directDebitId = $dao->id;
    
    // Replace the DDI Reference Number with our new unique sequenced version
    $transactionPrefix = UK_Direct_Debit_Form_Main::getTransactionPrefix();
    $DDIReference = $transactionPrefix . sprintf("%08s", $directDebitId);
    
    $updateSql  = " UPDATE civicrm_direct_debit cdd "; 
    $updateSql .= " SET cdd.ddi_reference = %0 ";
    $updateSql .= " WHERE cdd.id = %1 ";
    
    $updateParams = array(array((string)$DDIReference, 'String'),
                          array((int)$directDebitId,      'Int'),
                    );

    CRM_Core_DAO::executeQuery($updateSql, $updateParams);     
  
    return $DDIReference;
  }
  
  function isDDSubmissionComplete($DDIReference) {
    $isComplete = false;
    
    $selectSql  = " SELECT complete_flag "; 
    $selectSql .= " FROM civicrm_direct_debit cdd ";
    $selectSql .= " WHERE cdd.ddi_reference = %1 ";
    $selectParams  = array( 1 => array( $DDIReference , 'String' ));
    $dao = CRM_Core_DAO::executeQuery( $selectSql, $selectParams );
    
    if ($dao->fetch()) {
        if ($dao->complete_flag == 1) {
            $isComplete = true;
        }
    }
    
    return $isComplete;
  }
  
  function getCompanyName() {
      
    $companyName = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_name');
    return $companyName;
  }
  
  function getCompanyAddress() {
      
    $companyAddress = array();
    
    $companyAddress['company_name'] = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_name');  
    $companyAddress['address1']     = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_address1');  
    $companyAddress['address2']     = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_address2');  
    $companyAddress['address3']     = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_address3');  
    $companyAddress['address4']     = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_address4');  
    $companyAddress['town']         = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_town');  
    $companyAddress['county']       = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_county');  
    $companyAddress['postcode']     = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'company_postcode');  

    return $companyAddress;     
  }
  
  function getActivityType() {
      
    $activityType = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'activity_type');
    return $activityType;
  }
  
  function getActivityTypeLetter() {
      
    $activityTypeLetter = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'activity_type_letter');
    return $activityTypeLetter;
  }  
    
  function getTelephoneNumber() {
      
    $telephoneNumber = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'telephone_number');
    return $telephoneNumber;
  }
  
  function getEmailAddress() {
      
    $emailAddress = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'email_address');
    return $emailAddress;
  }
  
  function getDomainName() {
      
    $domainName = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'domain_name');
    return $domainName;
  }
  
  function getTransactionPrefix() {
      
    $transactionPrefix = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'transaction_prefix');
    return $transactionPrefix;
  }
  
  function getCountry($country_id) {   
    
    if (!empty($country_id)) {
        $sql = "SELECT name FROM civicrm_country WHERE id = %1";
        $params = array( 1 => array( $country_id , 'Integer' ));
        $dao = CRM_Core_DAO::executeQuery( $sql, $params );
        $dao->fetch();
        $country = $dao->name;
    }
   
    return $country;

  }
  
  function getStateProvince($state_province_id) {
      
    if (!empty($state_province_id)) {
        $sql = "SELECT name FROM civicrm_state_province WHERE id = %1";
        $params = array( 1 => array( $state_province_id , 'Integer' ));
        $dao = CRM_Core_DAO::executeQuery( $sql, $params );
        $dao->fetch();
        $stateProvince = $dao->name;
    }
    
    return $stateProvince;

  }  
  
  /** create all fields needed for direct debit transaction
   *
   * @return void
   * @access public
   */
  function setDirectDebitFields(&$form) {

 //   CRM_Core_Payment_Form::_setPaymentFields($form);

    $form->_paymentFields['account_holder'] = array(
      'htmlType' => 'text',
      'name' => 'account_holder',
      'title' => ts('Account Holder'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 20, 'maxlength' => 34, 'autocomplete' => 'on'),
      'is_required' => TRUE,
    );

    //e.g. IBAN can have maxlength of 34 digits
    $form->_paymentFields['bank_account_number'] = array(
      'htmlType' => 'text',
      'name' => 'bank_account_number',
      'title' => ts('Bank Account Number'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 20, 'maxlength' => 34, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    //e.g. SWIFT-BIC can have maxlength of 11 digits
    $form->_paymentFields['bank_identification_number'] = array(
      'htmlType' => 'text',
      'name' => 'bank_identification_number',
      'title' => ts('Sort Code'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 20, 'maxlength' => 11, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields['bank_name'] = array(
      'htmlType' => 'text',
      'name' => 'bank_name',
      'title' => ts('Bank Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 20, 'maxlength' => 64, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    // Get the collection days options
    require_once 'UK_Direct_Debit/Form/Main.php';
    $collectionDaysArray = UK_Direct_Debit_Form_Main::getCollectionDaysOptions(); 
   
    $form->_paymentFields['preferred_collection_day'] = array(
      'htmlType' => 'select',
      'name' => 'preferred_collection_day',
      'title' => ts('Preferred Collection Day'),
      'cc_field' => TRUE,
      'attributes' => $collectionDaysArray, // array('1' => '1st', '8' => '8th', '21' => '21st'),
//      'attributes' => array('1' => '1st', '8' => '8th', '21' => '21st'),
      'is_required' => TRUE,
    );
    
    $form->_paymentFields['confirmation_method'] = array(
      'htmlType' => 'select',
      'name' => 'confirmation_method',
      'title' => ts('Confirm By'),
      'cc_field' => TRUE,
      'attributes' => array('EMAIL' => 'Email', 'POST' => 'Post'),
      'is_required' => TRUE,
    );
    
    $form->_paymentFields['payer_confirmation'] = array(
      'htmlType' => 'checkbox',
      'name' => 'payer_confirmation',
      'title' => ts('Please confirm that you are the account holder and only person required to authorise Direct Debits from this account'),
      'cc_field' => TRUE,
      'is_required' => TRUE,
    );
        
    $form->_paymentFields['ddi_reference'] = array(
      'htmlType' => 'hidden',
      'name' => 'ddi_reference',
      'title' => ts('DDI Reference'),
     'cc_field' => TRUE,
      'attributes' => array('size' => 20, 'maxlength' => 64, 'autocomplete' => 'off'),
      'is_required' => FALSE,
      'default' => 'hello'
    );
    
    $telephoneNumber = self::getTelephoneNumber();  
    $form->assign('telephoneNumber', $telephoneNumber);    

    $companyName = self::getCompanyName();
    $form->assign('companyName', $companyName);    
  }

  /**
   * Function to add all the direct debit fields
   *
   * @return None
   * @access public
   */
  function buildDirectDebit(&$form, $useRequired = FALSE) {

    if ($form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_FORM) {
      self::setDirectDebitFields($form);
      foreach ($form->_paymentFields as $name => $field) {
        if (isset($field['cc_field']) &&
          $field['cc_field']
        ) {
          $form->add($field['htmlType'],
            $field['name'],
            $field['title'],
            $field['attributes'],
            $useRequired ? $field['is_required'] : FALSE
          );
        }
      }

      $form->addRule('bank_identification_number',
        ts('Please enter a valid Bank Identification Number (value must not contain punctuation characters).'),
        'nopunctuation'
      );

      $form->addRule('bank_account_number',
        ts('Please enter a valid Bank Account Number (value must not contain punctuation characters).'),
        'nopunctuation'
      );
    }

    if ($form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_BUTTON) {
      $form->_expressButtonName = $form->getButtonName($form->buttonType(), 'express');
      $form->add('image',
        $form->_expressButtonName,
        $form->_paymentProcessor['url_button'],
        array('class' => 'form-submit')
      );
    }

    $defaults['ddi_reference'] = self::getDDIReference();
     
    $form->setDefaults($defaults);  
    
  }
  
  function formatPrefferedCollectionDay($collectionDay) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if (($collectionDay%100) >= 11 && ($collectionDay%100) <= 13)
      $abbreviation = $collectionDay. 'th';
    else
      $abbreviation = $collectionDay. $ends[$collectionDay % 10];
    
    return $abbreviation;
  }

  /*
   * Function will return the SUN number broken down into individual characters passed as an array
   */
  function getSUNParts() {

    $SUNArray = str_split(self::getSUN());

    return $SUNArray;
  }

  /*
   * Function will return the SUN number broken down into individual characters passed as an array
   */
  function getSUN() {

    $SUN = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'service_user_number');    

    return $SUN;
  }
  
  /*
   * Function will return the Payment instrument to be used by DD payment processor
   */
  function getDDPaymentInstrumentID() {

    $DDContributionID = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'payment_instrument_id');    

    return $DDContributionID;
  }  

    /*
   * Function will return the possible array of collection days with formatted label
   */
  function getCollectionDaysOptions() {

    $intervalDate = new DateTime();
    $interval = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'collection_interval');
    $intervalDate->modify("+$interval day");
    $intervalDay = $intervalDate->format('d');
    
    $collectionDays = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'collection_days');

    // Split the array
    $tempCollectionDaysArray = explode(',', $collectionDays);
    
    $earlyCollectionDaysArray = array();
    $lateCollectionDaysArray = array();

    // Build 2 arrays around next collection date
    foreach( $tempCollectionDaysArray as $key => $value){
      
      if ($value >= $intervalDay) {
        $earlyCollectionDaysArray[] = $value;
      }
      else {
        $lateCollectionDaysArray[] = $value;
      }
    }
    
    // Merge arrays for select list 
    $allCollectionDays = array_merge($earlyCollectionDaysArray, $lateCollectionDaysArray);

    // Loop through and format each label
    foreach( $allCollectionDays as $key => $value){
      $collectionDaysArray[$value] = self::formatPrefferedCollectionDay($value);      
    }
    
    return $collectionDaysArray;
  }
  
  /*
   * Called after contribution page has been completed
   * Main purpose is to tidy the contribution
   * And to setup the relevant Direct Debit Mandate Information
   */
  function completeDirectDebitSetup($response, &$params)  {

    require_once 'api/api.php';
    
    // Check if the contributionRecurID is set
    // If so then update the trxn id with the mandate number
    // Edit the start date to be the correct date
    if (!empty($params['contributionRecurID'])) {
      require_once 'CRM/Contribute/DAO/ContributionRecur.php';
      $recurDAO = new CRM_Contribute_DAO_ContributionRecur();
      
      $recurDAO->id = $params['contributionRecurID'];
      $recurDAO->find();
      $recurDAO->fetch();
     
      $transaction = new CRM_Core_Transaction();
      
      if (!empty($response['start_date'])) {
        $start_date = $response['start_date'];
      } else {
          if (!empty($params['start_date'])) {
              $start_date = $params['start_date'];
          }
      }
      
      $recurDAO->start_date = CRM_Utils_Date::isoToMysql($start_date);
      
      $recurDAO->create_date = CRM_Utils_Date::isoToMysql($recurDAO->create_date);
      $recurDAO->modified_date = CRM_Utils_Date::isoToMysql($recurDAO->modified_date);
      $recurDAO->payment_instrument_id = self::getDDPaymentInstrumentID();
            
      $recurDAO->trxn_id = CRM_Utils_Date::isoToMysql($response['trxn_id']);
      
      $recurDAO->save();

//      if ($objects == CRM_Core_DAO::$_nullObject) {
        $transaction->commit();
//      }
//      else {
//        require_once 'CRM/Core/Payment/BaseIPN.php';
//        $baseIPN = new CRM_Core_Payment_BaseIPN();
//        return $baseIPN->cancelled($objects, $transaction);
//      }

    }
    
    // Check if the contributionID has been set
    // If so update it to be the same date as the start date
    if (!empty($params['contributionID'])) {
        
      //$contributionArray = civicrm_api('Contribution', 'Get', array('id' => $params['contributionID'], 'version' => 3));
      
      //$contribution = $contributionArray['values'][$contributionArray['id']];
      $contribution['id'] = $params['contributionID'];
      $contribution['contribution_id'] = $params['contributionID'];
      $contribution['receive_date'] = CRM_Utils_Date::isoToMysql($response['start_date']);
      $contribution['payment_instrument_id'] = self::getDDPaymentInstrumentID();
      $contribution['payment_instrument'] = 'Direct Debit';
      $contribution['fee_amount'] = 0;
      $contribution['net_amount'] = 0;
      
      $contribution['version'] = 3;

CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup id='                    .$contribution['id']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup contribution_id='       .$contribution['contribution_id']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup receive_date='          .$contribution['receive_date']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup payment_instrument_id=' .$contribution['payment_instrument_id']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup payment_instrument='    .$contribution['payment_instrument']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup fee_amount='            .$contribution['fee_amount']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup net_amount='            .$contribution['net_amount']);
CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main.completeDirectDebitSetup version='               .$contribution['version']);

      $updatedContribution = civicrm_api('Contribution', 'Update', $contribution);
      
      if ($updatedContribution['is_error']) {         
        CRM_Core_Error::debug_log_message("ERROR : Complete Direct Debit, Contribution Updated, Response is ".$updatedContribution['error_message']);
      }
    }
 
    // TODO Create an activity to indicate Direct Debit Sign up? Attach Letter above
    $activityID = self::createDDSignUpActivity($response, $params);

    // TODO Create mail merged file with Direct Debit Mandate
    
    // Get the HTML template for DD confirmation
    $default_html = self::getDDConfirmationTemplate();
   
    // TODO Merge in the tokens to the document
    // Merge the document with the contact in question
   
    $frequencyInterval = $recurDAO->frequency_interval;
    $frequencyUnit = $recurDAO->frequency_unit;
    $numberOfInstallemts = $recurDAO->installments;
    $bankName = $params['bank_name'];
    $accountHolder = $params['account_holder'];
    $accountNumber = 'xxxx'. substr($params['bank_account_number'], 4);
    $sortcode = $params['bank_identification_number'];
    $startDate = date("jS F Y", strtotime($recurDAO->start_date));
    $transactionReference = $recurDAO->trxn_id;
    $firstPaymentAmount = $recurDAO->amount;
    $recurringPaymentAmount = $recurDAO->amount;
    $serviceUserNumber = self::getSUN();
    $serviceUserName = self::getCompanyName();
    $telephoneNumber = self::getTelephoneNumber();
    $emailAddress = self::getEmailAddress();
    $salutationName = $params['billing_first_name'].' '.$params['billing_middle_name'].' '.$params['billing_last_name'];
    $address = '';
    $address .= !empty($params['billing_street_address-5'])    ? $params['billing_street_address-5'].'<br/>' : '';
    $address .= !empty($params['billing_city-5'])              ? $params['billing_city-5'].'<br/>' : '';
    $address .= !empty($params['billing_state_province_id-5']) ? self::getStateProvince($params['billing_state_province_id-5']).'<br/>' : '';
    $address .= !empty($params['billing_postal_code-5'])       ? $params['billing_postal_code-5'].'<br/>' : '';
    $address .= !empty($params['billing_country_id-5'])        ? self::getCountry($params['billing_country_id-5']).'<br/>' : '';
    
    $default_html = str_replace(  '{full_address}'            , $address ,                $default_html);
    $default_html = str_replace(  '{salutation_name}'         , $salutationName ,         $default_html);
    $default_html = str_replace(  '{account_holder}'          , $accountHolder ,          $default_html);
    $default_html = str_replace(  '{account_number}'          , $accountNumber ,          $default_html);
    $default_html = str_replace(  '{sortcode}'                , $sortcode ,               $default_html);
    $default_html = str_replace(  '{start_date}'              , $startDate ,              $default_html);
    $default_html = str_replace(  '{first_payment_amount}'    , $firstPaymentAmount ,     $default_html);
    $default_html = str_replace(  '{recurring_payment_amount}', $recurringPaymentAmount , $default_html);
    $default_html = str_replace(  '{frequency_unit}'          , $frequencyUnit ,          $default_html);
    $default_html = str_replace(  '{telephone_number}'        , $telephoneNumber ,        $default_html);
    $default_html = str_replace(  '{email_address}'           , $emailAddress ,           $default_html);
    $default_html = str_replace(  '{service_user_number}'     , $serviceUserNumber ,      $default_html);
    $default_html = str_replace(  '{service_user_name}'       , $serviceUserName ,        $default_html);
    $default_html = str_replace(  '{transaction_reference}'   , $transactionReference ,   $default_html);
 
    // Are we emailing this (depending on the communication preference chosen during sign up?)
    // if we are then also set the status of the activity to completed as there is no need to send out a paper form
    
    // Turn into a PDF and attach to activity
    require_once("CRM/Core/Config.php");
    $config =& CRM_Core_Config::singleton( );  
    $file_name = 'DDSignUp-Activity-'.$activityID.'.pdf';
    $csv_path = $config->customFileUploadDir;
    $filePathName   = "{$csv_path}{$file_name}";
                    
    $fileContent = self::html2pdf( $default_html , $file_name , "external");
    
    

    $handle = fopen($filePathName, 'w');
    file_put_contents($filePathName, $fileContent);
    fclose($handle);

    // We're not doing this as deleting the file removes the link and you can no longer open it    // S
    self::insert_file_for_activity($file_name , $activityID);

    // Send an email to the contributor if the confirmation method was email
    if ($params['confirmation_method'] == 'EMAIL') {
        
        $notifyAttachments[] = array(
          'fullPath' => $filePathName,
          'mime_type' => 'application/pdf',
          'cleanName' => $file_name,
        );
        
        self::directDebitSignUpNofify('START', $params['contactID'], $params['contributionPageID'], $recurDAO, FALSE, $notifyAttachments);        
    }

    CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main: About to Update the civicrm_direct_debit record to mark as complete.');
    CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main: response[trxn_id] = '.$response['trxn_id']);
    
    // Set the DD Record to be complete
    $sql  = " UPDATE civicrm_direct_debit SET ";
    $sql .= "  complete_flag = 1 ";
    $sql .= " WHERE ddi_reference = SUBSTRING_INDEX(%0, '-', 1) ";

    CRM_Core_DAO::executeQuery($sql, array(
        array((string)$response['trxn_id'],      'String'),
    ));    

    CRM_Core_Error::debug_log_message('UK_Direct_Debit_Form_Main: Completed completeDirectDebitSetup Function.');
    
  }

  function createDDSignUpActivity($response, &$params) {

    require_once 'api/api.php';      

    if ($params['confirmation_method'] == 'POST') {
        $activityTypeLetterID = self::getActivityTypeLetter(); 

        $activityLetterParams = array(
                    'source_contact_id' => $params['contactID'],
                    'target_contact_id' => $params['contactID'],
                    'activity_type_id'  => $activityTypeLetterID,
                    'subject' => 'Direct Debit Sign Up, Mandate ID : '.$response['trxn_id'],
                    'activity_date_time' => date('YmdHis'),
                    'status_id'=> 1,
                    'version' => 3
                    );

        $resultLetter = civicrm_api( 'activity','create', $activityLetterParams );    

    }
    
    $activityTypeID = self::getActivityType(); 
    
    $activityParams = array(
                'source_contact_id' => $params['contactID'],
                'target_contact_id' => $params['contactID'],
                'activity_type_id'  => $activityTypeID,
                'subject' => 'Direct Debit Sign Up, Mandate ID : '.$response['trxn_id'],
                'activity_date_time' => date('YmdHis'),
                'status_id'=> 2,
                'version' => 3,
                );
 
    $result = civicrm_api( 'activity','create', $activityParams );

    $activityID = $result['id'];

    return $activityID;
  }
  
  function firstCollectionDate($collectionDay, $startDate) {

    // Initialise date objects with today's date
    $today                    = new DateTime();
    $todayPlusDateInterval    = new DateTime();
    $collectionDateThisMonth  = new DateTime();
    $collectionDateNextMonth  = new DateTime();
    $collectionDateMonthAfter = new DateTime();

    $interval = CRM_Core_BAO_Setting::getItem(self::SETTING_GROUP_UK_DD_NAME,'collection_interval');

    // If we are not starting from today, then reset today's date and interval date        
    if (!empty($startDate)) {
        $today = DateTime::createFromFormat('Y-m-d', $startDate);
        $todayPlusDateInterval = DateTime::createFromFormat('Y-m-d', $startDate);
    }

    // Add the day interval to create a date interval days from today's date
    $dateInterval = 'P'.$interval.'D';
    $todayPlusDateInterval->add(new DateInterval($dateInterval));

    // Get the current year, month and next month to create the 2 potential collection dates
    $todaysMonth = $today->format('m');
    $nextMonth   = $today->format('m') + 1;
    $monthAfter  = $today->format('m') + 2;
    $todaysYear  = $today->format('Y');

    $collectionDateThisMonth->setDate($todaysYear,  $todaysMonth, $collectionDay);
    $collectionDateNextMonth->setDate($todaysYear,  $nextMonth,   $collectionDay);
    $collectionDateMonthAfter->setDate($todaysYear, $monthAfter,  $collectionDay);

    // Determine which is the next collection date
    if ($todayPlusDateInterval > $collectionDateThisMonth) {
      if ($todayPlusDateInterval > $collectionDateNextMonth) {
        $returnDate = $collectionDateMonthAfter;
      }
      else {
        $returnDate = $collectionDateNextMonth;          
      }
    } 
    else {
        $returnDate = $collectionDateThisMonth;
    }
    
    return $returnDate;
    
  }
  
  function directDebitSignUpNofify($type, $contactID, $pageID, $recur, $autoRenewMembership = FALSE) {
    $value = array();
    if ($pageID) {
      CRM_Core_DAO::commonRetrieveAll('CRM_Contribute_DAO_ContributionPage', 'id',
        $pageID, $value,
        array(
          'title', 'is_email_receipt', 'receipt_from_name',
          'receipt_from_email', 'cc_receipt', 'bcc_receipt',
        )
      );
    }

    $isEmailReceipt = CRM_Utils_Array::value('is_email_receipt', $value[$pageID]);
    $isOfflineRecur = FALSE;
    if (!$pageID && $recur->id) {
      $isOfflineRecur = TRUE;
    }
    if ($isEmailReceipt || $isOfflineRecur) {
      if ($pageID) {
        $receiptFrom = '"' . CRM_Utils_Array::value('receipt_from_name', $value[$pageID]) . '" <' . $value[$pageID]['receipt_from_email'] . '>';

        $receiptFromName = $value[$pageID]['receipt_from_name'];
        $receiptFromEmail = $value[$pageID]['receipt_from_email'];
      }
      else {
        require_once 'CRM/Core/BAO/Domain.php';
        $domainValues     = CRM_Core_BAO_Domain::getNameAndEmail();
        $receiptFrom      = "$domainValues[0] <$domainValues[1]>";
        $receiptFromName  = $domainValues[0];
        $receiptFromEmail = $domainValues[1];
      }

      require_once 'CRM/Contact/BAO/Contact/Location.php';
      list($displayName, $email) = CRM_Contact_BAO_Contact_Location::getEmailDetails($contactID, FALSE);
      $templatesParams = array(
        'groupName' => 'msg_tpl_workflow_contribution',
        'valueName' => 'contribution_recurring_notify',
        'contactId' => $contactID,
        'tplParams' => array(
          'recur_frequency_interval' => $recur->frequency_interval,
          'recur_frequency_unit' => $recur->frequency_unit,
          'recur_installments' => $recur->installments,
          'recur_start_date' => $recur->start_date,
          'recur_end_date' => $recur->end_date,
          'recur_amount' => $recur->amount,
          'recur_txnType' => $type,
          'displayName' => $displayName,
          'receipt_from_name' => $receiptFromName,
          'receipt_from_email' => $receiptFromEmail,
          'auto_renew_membership' => $autoRenewMembership,
        ),
        'from' => $receiptFrom,
        'toName' => $displayName,
        'toEmail' => $email,
      );     

      require_once 'CRM/Core/BAO/MessageTemplates.php';
      list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplates::sendTemplate($templatesParams);

      if ($sent) {
        CRM_Core_Error::debug_log_message('Success: mail sent for recurring notification.');
      }
      else {
        CRM_Core_Error::debug_log_message('Failure: mail not sent for recurring notification.');
      }
    }
  }

  function insert_file_for_activity( $file_name , $activity_id ) {

        $upload_date = date('Y-m-d H:i:s');
        
        $file_sql = "INSERT INTO civicrm_file SET mime_type = %1 , uri = %2 , upload_date=%3";
        $file_params  = array( 
                              1 => array( "text/csv"   , 'String' ) ,
                              2 => array( $file_name   , 'String' ) ,
                              3 => array( $upload_date   , 'String' )  
                             );
        $file_dao = CRM_Core_DAO::executeQuery( $file_sql, $file_params );
        
        $select_sql = "SELECT id FROM civicrm_file WHERE mime_type = %1 AND uri = %2 AND upload_date = %3  ORDER BY id DESC";
        $select_dao = CRM_Core_DAO::executeQuery( $select_sql, $file_params );
        $select_dao->fetch();
        $file_id = $select_dao->id;
        
        $custom_sql = "INSERT INTO civicrm_entity_file SET entity_id = %1 , entity_table = %2 , file_id = %3";
        $custom_params  = array( 
                              1 => array( $activity_id   , 'Integer' ) ,
                              2 => array('civicrm_activity' , 'String') ,
                              3 => array( $file_id   , 'Integer' ) 
                             );
        
        $custom_dao = CRM_Core_DAO::executeQuery( $custom_sql, $custom_params );
  }    

  function getDDConfirmationTemplate() {
    $default_template_name = "direct_debit_confirmation";
    $default_template_sql = "SELECT * FROM civicrm_msg_template mt WHERE mt.msg_title = %1";
    $default_template_params  = array( 1 => array( $default_template_name , 'String' ));
    $default_template_dao = CRM_Core_DAO::executeQuery( $default_template_sql, $default_template_params );
    $default_template_dao->fetch();

    return $default_template_dao->msg_html;    
  }

  /*
   * Function to produce PDF
   * Author : rajesh@millertech.co.uk
   */
  static function html2pdf( $text , $fileName = 'FiscalReceipts.pdf' , $calling = "internal" ) {

      require_once 'packages/dompdf/dompdf_config.inc.php';
      spl_autoload_register('DOMPDF_autoload');
      $dompdf = new DOMPDF( );

      $values = array( );
      if ( ! is_array( $text ) ) {
          $values =  array( $text );
      } else {
          $values =& $text;
      }

      $html = '';
      foreach ( $values as $value ) {
          $html .= "{$value}\n";
      }

      //echo $html;exit;

      $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

      $dompdf->load_html( $html );
      $dompdf->set_paper ('a4', 'portrait');
      $dompdf->render( );

      if($calling == "external"){ // like calling from cron job
        $fileContent = $dompdf->output();
        return $fileContent;
      }
      else{
        $dompdf->stream( $fileName );
      }
      exit;
  }
  


  function record_response($direct_debit_response) {

    $sql  = " UPDATE civicrm_direct_debit SET ";
    $sql .= "  created = NOW() ";
    $sql .= ", data_type = %0 ";
    $sql .= ", entity_type = %1 ";
    $sql .= ", entity_id = %2 ";
    $sql .= ", bank_name = %3 ";
    $sql .= ", branch = %4 ";
    $sql .= ", address1 = %5 ";
    $sql .= ", address2 = %6 ";
    $sql .= ", address3 = %7 ";
    $sql .= ", address4 = %8 ";
    $sql .= ", town = %9 ";
    $sql .= ", county = %10 ";
    $sql .= ", postcode = %11 ";
    $sql .= ", first_collection_date = %12 ";
    $sql .= ", preferred_collection_day = %13 ";
    $sql .= ", confirmation_method = %14 ";
    $sql .= ", response_status = %15 ";
    $sql .= ", response_raw = %16 ";
    $sql .= ", request_counter = request_counter + 1 ";
    $sql .= " WHERE ddi_reference = %17 ";

    CRM_Core_DAO::executeQuery($sql, array(
        array((string)$direct_debit_response['data_type']                 , 'String'),
        array((string)$direct_debit_response['entity_type']               , 'String'),
        array((integer)$direct_debit_response['entity_id']                , 'Integer'),
        array((string)$direct_debit_response['bank_name']                 , 'String'),
        array((string)$direct_debit_response['branch']                    , 'String'),
        array((string)$direct_debit_response['address1']                  , 'String'),
        array((string)$direct_debit_response['address2']                  , 'String'),
        array((string)$direct_debit_response['address3']                  , 'String'),
        array((string)$direct_debit_response['address4']                  , 'String'),
        array((string)$direct_debit_response['town']                      , 'String'),
        array((string)$direct_debit_response['county']                    , 'String'),
        array((string)$direct_debit_response['postcode']                  , 'String'),
        array((string)$direct_debit_response['first_collection_date']     , 'String'),
        array((string)$direct_debit_response['preferred_collection_day']  , 'String'),
        array((string)$direct_debit_response['confirmation_method']       , 'String'),
        array((string)$direct_debit_response['response_status']           , 'String'),
        array((string)$direct_debit_response['response_raw']              , 'String'),
        array((string)$direct_debit_response['ddi_reference']             , 'String'),
    ));
    
  }
  
/**
 * Change a price set field to be required for a specific event.
 */
function UK_Direct_Debit_civicrm_buildForm($formName, &$form) {
print_r($form);
die;
  if ($formName == 'CRM_Event_Form_Registration_Register') {
    if ($form->_eventId == EVENT_ID) {
      $form->addRule('price_3', ts('This field is required.'), 'required');
    }
  }
}  

}
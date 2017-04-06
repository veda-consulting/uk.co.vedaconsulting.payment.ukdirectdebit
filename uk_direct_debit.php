<?php

require_once 'uk_direct_debit.civix.php';

/**
 * Save setting with prefix in database
 * @param $name
 * @param $value
 */
function uk_direct_debit_civicrm_saveSetting($name, $value) {
  civicrm_api3('setting', 'create', array(CRM_DirectDebit_Form_Settings::getSettingName($name,true) => $value));
}

/**
 * Read setting that has prefix in database and return single value
 * @param $name
 * @return mixed
 */
function uk_direct_debit_civicrm_getSetting($name) {
  $settings = civicrm_api3('setting', 'get', array('return' => CRM_DirectDebit_Form_Settings::getSettingName($name,true)));
  if (isset($settings['values'][1][CRM_DirectDebit_Form_Settings::getSettingName($name,true)])) {
    return $settings['values'][1][CRM_DirectDebit_Form_Settings::getSettingName($name, true)];
  }
  return '';
}

/**
 * Implementation of hook_civicrm_install().
 */
function uk_direct_debit_civicrm_install() {
  _uk_direct_debit_civix_civicrm_install();

  // Create an Direct Debit Activity Type
  // See if we already have this type
  $ddActivity = civicrm_api3('OptionValue', 'get', array(
    'option_group_id' => "activity_type",
    'name' => "Direct Debit Sign Up",
  ));
  if (empty($ddActivity['count'])) {
    $activityParams = array('version' => '3'
    , 'option_group_id' => "activity_type"
    , 'name' => 'Direct Debit Sign Up'
    , 'description' => 'Direct Debit Sign Up');
    $activityType = civicrm_api('OptionValue', 'Create', $activityParams);
    $activityTypeId = $activityType['values'][$activityType['id']]['value'];
    uk_direct_debit_civicrm_saveSetting('activity_type', $activityTypeId);
  }

  // See if we already have this type
  $ddActivity = civicrm_api3('OptionValue', 'get', array(
    'option_group_id' => "activity_type",
    'name' => "DD Confirmation Letter",
  ));
  if (empty($ddActivity['count'])) {
    // Otherwise create it
    $activityParams = array('version' => '3'
    , 'option_group_id' => "activity_type"
    , 'name' => 'DD Confirmation Letter'
    , 'description' => 'DD Confirmation Letter');
    $activityType = civicrm_api('OptionValue', 'Create', $activityParams);
    $activityTypeId = $activityType['values'][$activityType['id']]['value'];
    uk_direct_debit_civicrm_saveSetting('activity_type_letter', $activityTypeId);
  }

  // Create an Direct Debit Payment Instrument
  // See if we already have this type
  $ddPayment = civicrm_api3('OptionValue', 'get', array(
    'option_group_id' => "payment_instrument",
    'name' => "Direct Debit",
  ));
  if (empty($ddPayment['count'])) {
    // Otherwise create it
    $paymentParams = array('version' => '3'
    , 'option_group_id' => "payment_instrument"
    , 'name' => 'Direct Debit'
    , 'description' => 'Direct Debit');
    $paymentType = civicrm_api('OptionValue', 'Create', $paymentParams);
    $paymentTypeId = $paymentType['values'][$paymentType['id']]['value'];
    uk_direct_debit_civicrm_saveSetting('payment_instrument_id', $paymentTypeId);
  }

  // On install, create a table for keeping track of online direct debits
  CRM_Core_DAO::executeQuery("
         CREATE TABLE IF NOT EXISTS `civicrm_direct_debit` (
        `id`                        int(10) unsigned NOT NULL auto_increment,
        `created`                   datetime NOT NULL,
        `data_type`                 varchar(16) ,
        `entity_type`               varchar(32) ,
        `entity_id`                 int(10) unsigned,
        `bank_name`                 varchar(100) ,
        `branch`                    varchar(100) ,
        `address1`                  varchar(100) ,
        `address2`                  varchar(100) ,
        `address3`                  varchar(100) ,
        `address4`                  varchar(100) ,
        `town`                      varchar(100) ,
        `county`                    varchar(100) ,
        `postcode`                  varchar(20)  ,
        `first_collection_date`     varchar(100),
        `preferred_collection_day`  varchar(100) ,
        `confirmation_method`       varchar(100) ,
        `ddi_reference`             varchar(100) NOT NULL,
        `response_status`           varchar(100) ,
        `response_raw`              longtext     ,
        `request_counter`           int(10) unsigned,
        `complete_flag`             tinyint unsigned,
        `additional_details1`       varchar(100),
        `additional_details2`       varchar(100),
        `additional_details3`       varchar(100),
        `additional_details4`       varchar(100),
        `additional_details5`       varchar(100),
        PRIMARY KEY  (`id`),
        KEY `entity_id` (`entity_id`),
        KEY `data_type` (`data_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
    ");

  // Create a table to store imported collection reports (CRM_DirectDebit_Auddis::getSmartDebitCollectionReport())
  if(!CRM_Core_DAO::checkTableExists('veda_civicrm_smartdebit_import')) {
    $createSql = "CREATE TABLE `veda_civicrm_smartdebit_import` (
                   `id` int(10) unsigned NOT NULL AUTO_INCREMENT, 
                   `transaction_id` varchar(255) DEFAULT NULL,
                   `contact` varchar(255) DEFAULT NULL,
                   `contact_id` varchar(255) DEFAULT NULL,
                   `amount` decimal(20,2) DEFAULT NULL,
                   `info` int(11) DEFAULT NULL,
                   `receive_date` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`)
         ) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=latin1";
    CRM_Core_DAO::executeQuery($createSql);
  }

  uk_direct_debit_message_template();
}

/**
 * Implementation of hook_civicrm_uninstall().
 */
function uk_direct_debit_civicrm_uninstall() {
  _uk_direct_debit_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function uk_direct_debit_civicrm_enable() {
  _uk_direct_debit_civix_civicrm_enable();
}

function uk_direct_debit_civicrm_disable() {
  if (CRM_Extension_System::singleton()->getManager()->getStatus('uk.co.vedaconsulting.module.reconciliation.smartdebit') == 'installed') {
    CRM_Core_Session::setStatus("", ts('Disabling Smart Debit Reconciliation extension'), "success");
    $result = civicrm_api3('Extension', 'disable', array(
      'sequential' => 1,
      'keys' => "uk.co.vedaconsulting.module.reconciliation.smartdebit",
    ));
  }

  _uk_direct_debit_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, enabled, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function uk_direct_debit_civicrm_managed(&$entities) {
  _uk_direct_debit_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_config
 */
function uk_direct_debit_civicrm_config( &$config ) {
  _uk_direct_debit_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders()
 *
 * @param $metaDataFolders
 */
function uk_direct_debit_civicrm_alterSettingsFolders(&$metaDataFolders){
  _uk_direct_debit_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_xmlMenu()
 *
 * @param $files
 */
function uk_direct_debit_civicrm_xmlMenu( &$files ) {
  _uk_direct_debit_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_navigationMenu().
 */
function uk_direct_debit_civicrm_navigationMenu( &$params ) {

  $item[] =  array (
    'name'       => 'UK Direct Debit',
    'url'        => null,
    'permission' => 'administer CiviCRM',
    'operator'   => null,
    'separator'  => null,
  );
  _uk_direct_debit_civix_insert_navigation_menu($params, 'Administer', $item[0]);
  $item[] = array (
    'name'       => 'UK Direct Debit Settings',
    'url'        => 'civicrm/directdebit/settings?reset=1',
    'permission' => 'administer CiviCRM',
    'operator'   => "NULL",
    'separator'  => "NULL",
  );
  _uk_direct_debit_civix_insert_navigation_menu($params, 'Administer/UK Direct Debit', $item[1]);

  $item[] = array(
    'label' => ts('Import Smart Debit Contributions'),
    'name'  => 'Import Smart Debit Contributions',
    'url'   => 'civicrm/directdebit/syncsd?reset=1',
    'permission' => 'administer CiviCRM',
  );

  _uk_direct_debit_civix_insert_navigation_menu($params, 'Contributions', $item[2]);
}


function uk_direct_debit_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ) {
  if ($objectName == 'Membership') {
    $cid = $values['cid'];
    $id = $values['id'];
    $name   = ts('Setup Direct Debit');
    $title  = ts('Setup Direct Debit');
    $url    = 'civicrm/directdebit/new';
    $qs	    = "action=add&reset=1&cid=$cid&id=$id";
    $recurID = CRM_Core_DAO::singleValueQuery('SELECT contribution_recur_id FROM civicrm_membership WHERE id = %1', array(1=>array($id, 'Int')));
    if($recurID) {
      $name   = ts('View Direct Debit');
      $title  = ts('View Direct Debit');
      $url    = 'civicrm/contact/view/contributionrecur';
      $qs   	= "reset=1&id=$recurID&cid=$cid";
    }
    $links[] = array(
      'name' => $name,
      'title' => $title,
      'url' => $url,
      'qs' => $qs
    );

  }
}

function uk_direct_debit_message_template() {

  $msg_title   = 'direct_debit_confirmation';
  $msg_subject = 'Thank you for your direct debit sign-up';

  $text = ' ';
  /*
     $text  = '{ts 1=$displayName}Dear %1{/ts},';
     $text .= '';
     $text .= '{ts}Thanks for your direct debit sign-up.{/ts}';
     $text .= '';
     $text .= '{ts 1=$recur_frequency_interval 2=$recur_frequency_unit 3=$recur_installments}This recurring contribution will be automatically processed every %1 %2(s) for a total of %3 installment(s).{/ts}';
     $text .= '';
     $text .= 'Thank you for choosing to pay for your gas by monthly Direct Debit';
     $text .= '';
     $text .= 'We need to check that we’ve got your bank details right. If not, please call us on 000 070 0000.';
     $text .= '';
     $text .= 'We’re open 9am to 5pm Monday to Friday.';
     $text .= '';
     $text .= 'Your bank account name: MR';
     $text .= 'Your bank account number: 0000000';
     $text .= 'Your bank sort code: 00-00-00';
     $text .= 'Your monthly payment amount £200.00';
     $text .= '';
     $text .= '{ts 1=$recur_frequency_interval 2=$recur_frequency_unit 3=$recur_installments}This recurring contribution will be automatically processed every %1 %2(s) for a total of %3 installment(s).{/ts}';
     $text .= '';
     $text .= 'Day of the month when we’ll take your payments: on or just after 28th';
     $text .= '';
     $text .= 'Date when we’ll take your first payment {$recur_start_date|crmDate}';
     $text .= '';
     $text .= 'Your gas account number (on your bank statement with our name gas company plc): 00000000';
     $text .= '';
     $text .= 'Our Originator’s Identification Number: 00000000';
     $text .= '';
     $text .= 'Thanks for being a gas company plc customer. If we can help at all please get in touch – that’s what we’re here for.';
     $text .= '';
     $text .= 'Yours sincerely,';
  */
  $html = ' ';

  /*
     $html  = '<div>{full_address}</div>';
     $html .= '<p>&nbsp;</p>';
     $html .= '<div>Dear {salutation_name},</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div><strong>Important:</strong> Confirmation of the set-up of your Direct Debit Instruction.</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>Having accepted your Direct Debit details, I would like to confirm that they are correct.</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>Please can you check that the list below, including your payment schedule is correct.</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>';
     $html .= '<table><tbody>';
     $html .= ' <tr><td>Account name:</td><td>{account_holder}</td></tr>';
     $html .= ' <tr><td>Account Number:</td><td>{account_number}</td></tr>';
     $html .= ' <tr><td>Bank Sort Code:</td><td>{sortcode}</td></tr>';
     $html .= ' <tr><td>Date of first collection:</td><td>{start_date}</td></tr>';
     $html .= ' <tr><td>The first amount will be:</td><td>&pound;{first_payment_amount}</td></tr>';
     $html .= ' <tr><td>Followed by amounts of:</td><td>&pound;{recurring_payment_amount}</td></tr>';
     $html .= ' <tr><td>Frequency of collection:</td><td>{frequency_unit}</td></tr>';
     $html .= '</tbody></table>';
     $html .= '</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>If any of the above details are incorrect please call Customer Services as soon as possible on {telephone_number} or email us at {email_address}. However, if your details are correct you need do nothing and your Direct Debit will be processed as normal. You have the right to cancel your Direct Debit at any time. A copy of the Direct Debit Guarantee is below.</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>For information, the collections will be made using this reference:</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>';
     $html .= '<table><tbody>';
     $html .= ' <tr><td>Service User Number:</td><td>{service_user_number}</td></tr>';
     $html .= ' <tr><td>Service User Name:</td><td>{service_user_name}</td></tr>';
     $html .= ' <tr><td>Reference:</td><td>{transaction_reference}</td></tr>';
     $html .= '</tbody></table>';
     $html .= '</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>Yours sincerely,</div>';
     $html .= '<div>Customer Services</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div>&nbsp;</div>';
     $html .= '<div style="border:solid #000000;">';
     $html .= '<div>';
     $html .= ' <table border="0" cellpadding="0" cellspacing="0" class="MsoNormalTable" style="width:100.0%;mso-cellspacing:0cm;background:white; mso-yfti-tbllook:1184;mso-padding-alt:0cm 0cm 0cm 0cm" width="100%">';
     $html .= '   <tbody>';
     $html .= '     <tr style="mso-yfti-irow:0;mso-yfti-firstrow:yes;mso-yfti-lastrow: yes;height:60.0pt">';
     $html .= '       <td style="padding:0cm 0cm 0cm 0cm;height:60.0pt">';
     $html .= '         <p>';
     $html .= '           <span style="font-size:15.0pt;font-family:&quot;Arial&quot;,&quot;sans-serif&quot;">The Direct Debit Guarantee<o:p></o:p></span></p>';
     $html .= '       </td>';
     $html .= '       <td style="padding:0cm 0cm 0cm 0cm;height:60.0pt">';
     $html .= '         <p align="right" class="MsoNormal" style="text-align:right">';
     $html .= '           <span style="mso-fareast-font-family:&quot;Times New Roman&quot;"><img id="_x0000_i1027" src="/images/dd_logo_small.jpg" style="border-width: 0pt; border-style: solid; width: 204px; height: 65px;" /><o:p></o:p></span></p>';
     $html .= '       </td>';
     $html .= '     </tr>';
     $html .= '   </tbody>';
     $html .= ' </table>';
     $html .= '</div>';
     $html .= '<div style="margin-bottom:10px">';
     $html .= ' This Guarantee is offered by all banks and building societies that accept instructions to pay Direct Debits.</div>';
     $html .= '<div style="margin-bottom:10px">';
     $html .= ' If there are any changes to the amount, date or frequency of your Direct Debit {service_user_name} will notify you five (5) days in advance of your account being debited or as otherwise agreed. If you request {service_user_name} to collect a payment, confirmation of the amount and date will be given to you at the time of the request.</div>';
     $html .= '<div style="margin-bottom:10px">';
     $html .= ' If an error is made in the payment of your Direct Debit by {service_user_name} or your bank or building society you are entitled to a full and immediate refund of the amount paid from your bank or building society.</div>';
     $html .= '<div style="margin-bottom:10px">';
     $html .= ' If you receive a refund you are not entitled to, you must pay it back when {service_user_name} asks you to.</div>';
     $html .= '<div>';
     $html .= ' You can cancel a Direct Debit at any time by simply contacting your bank or building society. Written confirmation may be required. Please also notify us.</div>';
     $html .= '</div>';
     $html .= '<p>';
     $html .= ' &nbsp;</p>';
  */
  $template_sql  = " INSERT INTO civicrm_msg_template SET ";
  $template_sql .= " msg_title   = %0, ";
  $template_sql .= " msg_subject = %1, ";
  $template_sql .= " msg_text    = %2, ";
  $template_sql .= " msg_html    = %3 ";

  $template_params = array(array($msg_title,   'String'),
    array($msg_subject, 'String'),
    array($text,        'String'),
    array($html,        'String'),
  );

  CRM_Core_DAO::executeQuery($template_sql, $template_params);
}

function uk_direct_debit_civicrm_buildForm( $formName, &$form )
{
  // Contribution / Payment / Signup forms
  if (($formName == 'CRM_Financial_Form_Payment')
    || $formName == 'CRM_Contribute_Form_Contribution_Main'
  ) {
    // Main payment/contribution page form
    CRM_Core_Region::instance('billing-block-pre')->add(array(
      'template' => 'CRM/DirectDebit/BillingBlock/BillingBlockPre.tpl',
    ));
    CRM_Core_Region::instance('billing-block-post')->add(array(
      'template' => 'CRM/DirectDebit/BillingBlock/BillingBlockPost.tpl',
    ));
  }
  //Smart Debit
  if (isset($form->_paymentProcessor['payment_processor_type']) && ($form->_paymentProcessor['payment_processor_type'] == 'Smart_Debit')) {
    // Contribution Thankyou form
    if ($formName == 'CRM_Contribute_Form_Contribution_ThankYou') {
      // Show the direct debit mandate on the thankyou page
      CRM_Core_Region::instance('contribution-thankyou-billing-block')->update('default', array(
        'disabled' => TRUE,
      ));
      CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
        'template' => 'CRM/Contribute/Form/Contribution/DirectDebitMandate.tpl',
      ));
    } // Confirm Contribution (check details and confirm)
    elseif ($formName == 'CRM_Contribute_Form_Contribution_Confirm') {
      // Show the direct debit agreement on the confirm page
      CRM_Core_Region::instance('contribution-confirm-recur')->update('default', array(
        'disabled' => TRUE,
      ));
      CRM_Core_Region::instance('contribution-confirm-recur')->add(array(
        'template' => 'CRM/Contribute/Form/Contribution/DirectDebitRecur.tpl',
      ));

      CRM_Core_Region::instance('contribution-confirm-billing-block')->update('default', array(
        'disabled' => TRUE,
      ));
      CRM_Core_Region::instance('contribution-confirm-billing-block')->add(array(
        'template' => 'CRM/Contribute/Form/Contribution/DirectDebitAgreement.tpl',
      ));
    } elseif ($formName == 'CRM_Contribute_Form_UpdateSubscription') {
      // Accessed when you click edit on a recurring contribution
      $paymentProcessor = $form->_paymentProcessor;
      if (isset($paymentProcessor['payment_processor_type']) && ($paymentProcessor['payment_processor_type'] == 'Smart_Debit')) {
        $recurID = $form->getVar('contributionRecurID');
        $linkedMembership = FALSE;
        $membershipRecord = civicrm_api3('Membership', 'get', array(
          'sequential' => 1,
          'return' => array("id"),
          'contribution_recur_id' => $recurID,
        ));
        if (isset($membershipRecord['id'])) {
          $linkedMembership = TRUE;
        }

        $form->removeElement('installments');

        $frequencyUnits = array('W' => 'week', 'M' => 'month', 'Q' => 'quarter', 'Y' => 'year');
        $frequencyIntervals = array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11, 12 => 12);

        $form->addElement('select', 'frequency_unit', ts('Frequency'),
          array('' => ts('- select -')) + $frequencyUnits
        );
        $form->addElement('select', 'frequency_interval', ts('Frequency Interval'),
          array('' => ts('- select -')) + $frequencyIntervals
        );
        $form->addDate('start_date', ts('Start Date'), FALSE, array('formatType' => 'custom'));
        $form->addDate('end_date', ts('End Date'), FALSE, array('formatType' => 'custom'));
        $form->add('text', 'account_holder', ts('Account Holder'), array('size' => 20, 'maxlength' => 18, 'autocomplete' => 'on'));
        $form->add('text', 'bank_account_number', ts('Bank Account Number'), array('size' => 20, 'maxlength' => 8, 'autocomplete' => 'off'));
        $form->add('text', 'bank_identification_number', ts('Sort Code'), array('size' => 20, 'maxlength' => 6, 'autocomplete' => 'off'));
        $form->add('text', 'bank_name', ts('Bank Name'), array('size' => 20, 'maxlength' => 64, 'autocomplete' => 'off'));
        $form->add('hidden', 'payment_processor_type', 'Smart_Debit');
        $subscriptionDetails = $form->getVar('_subscriptionDetails');
        $reference = $subscriptionDetails->subscription_id;
        $frequencyUnit = $subscriptionDetails->frequency_unit;
        $frequencyInterval = $subscriptionDetails->frequency_interval;
        $recur = new CRM_Contribute_BAO_ContributionRecur();
        $recur->processor_id = $reference;
        $recur->find(TRUE);
        $startDate = $recur->start_date;
        list($defaults['start_date'], $defaults['start_date_time']) = CRM_Utils_Date::setDateDefaults($startDate, NULL);
        $defaults['frequency_unit'] = array_search($frequencyUnit, $frequencyUnits);
        $defaults['frequency_interval'] = array_search($frequencyInterval, $frequencyIntervals);
        $form->setDefaults($defaults);
        if ($linkedMembership) {
          $form->assign('membership', TRUE);
          $e =& $form->getElement('frequency_unit');
          $e->freeze();
          $e =& $form->getElement('frequency_interval');
          $e->freeze();
          $e =& $form->getElement('start_date');
          $e->freeze();
        }
      }
    } elseif ($formName == 'CRM_Contribute_Form_UpdateBilling') {
      // This is triggered by clicking "Change Billing Details" on a recurring contribution.
    }
    if ($formName == 'CRM_Contribute_Form_CancelSubscription') {
      // This is triggered when you cancel a recurring contribution
      $paymentProcessorObj = $form->getVar('_paymentProcessorObj');
      $paymentProcessorName = $paymentProcessorObj->_processorName;
      if ($paymentProcessorName == 'Smart Debit Processor') {
        $form->addRule('send_cancel_request', 'Please select one of these options', 'required');
      }
    }
  }
}

/**
 * Send a post request with cURL
 *
 * @param $url
 */
function call_CiviCRM_IPN($url){
  // Set a one-minute timeout for this script
  set_time_limit(60);

  $options = array(
    CURLOPT_RETURNTRANSFER => true, // return web page
    CURLOPT_HEADER => false, // don't return headers
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
  );

  $session = curl_init( $url );
  curl_setopt_array( $session, $options );

  // $output contains the output string
  $output = curl_exec($session);
  $header = curl_getinfo($session);
}

/**
 * Renew Membership by one period if the membership expires
 * before the contribution collection date starts
 * @author shobbs
 * @param $membershipID
 */
function renew_membership_by_one_period($membershipID) {
  // Get the membership info
  $membership = civicrm_api("Membership"
    ,"get"
    , array ('version'       => '3'
    ,'membership_id' => $membershipID
    )
  );

  // Get the membership payment(s)
  $membershipPayment = civicrm_api("MembershipPayment"
    ,"get"
    , array ('version'       => '3'
    ,'membership_id' => $membershipID
    )
  );

  $contributionID = $membershipPayment['values'][$membershipPayment['id']]['contribution_id'];

  // Get the contribution
  $contribution = civicrm_api("Contribution"
    ,"get"
    ,array ('version'         => '3'
    ,'contribution_id' => $contributionID
    )
  );

  $contributionReceiveDate = $contribution['values'][$contributionID]['receive_date'];
  $contributionReceiveDateString = date("Ymd", strtotime($contributionReceiveDate));
  // For a new membership, membership end date won't be defined.
  if (empty($membership['values'][$membershipID]['end_date'])) {
    $membershipEndDateString = $contributionReceiveDateString;
  }
  else {
    $membershipEndDateString = date("Ymd", strtotime($membership['values'][$membershipID]['end_date']));
  }

  $contributionRecurID = $membership['values'][$membershipID]['contribution_recur_id'];

  if ($contributionReceiveDateString >= $membershipEndDateString) {
    $contributionRecurring = civicrm_api("ContributionRecur"
      ,"get"
      , array ('version' => '3'
      ,'id'      => $contributionRecurID
      )
    );

    $frequencyUnit = $contributionRecurring['values'][$contributionRecurID]['frequency_unit'];
    $frequencyInterval = $contributionRecurring['values'][$contributionRecurID]['frequency_interval'];
    if (!is_null($frequencyUnit) && !is_null($frequencyInterval)) {
      $membershipEndDateString = date("Y-m-d",strtotime($membershipEndDateString) . " +$frequencyInterval $frequencyUnit");
    }
  }
  $updatedMember = civicrm_api("Membership"
    ,"create"
    , array ('version'       => '3',
      'id'            => $membershipID,
      'end_date'      => $membershipEndDateString,
    )
  );
}

/**
 * Search for membership contributions
 *
 * @param $objectName
 * @param $tasks
 */
function uk_direct_debit_civicrm_searchTasks( $objectName, &$tasks ) {
  //for contributions only
  if($objectName != 'membership') {
    return;
  }
  if (!(array_search('Renew Memberships', $tasks)))
  {
    $tasks[] = array(
      'title' => 'Renew Memberships',
      'class' => array('CRM_Member_Form_Task_RenewMembership'),
      'result' => true
    );
  }
}

function uk_direct_debit_civicrm_pre( $op, $objectName, $id, &$params ) {
}

function uk_direct_debit_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contribute_Page_ContributionRecur') {
    // On the view recurring contribution page we add some info from smart debit if we have it
    $session = CRM_Core_Session::singleton();
    $userID = $session->get('userID');
    $contactID = $page->getVar('_contactId');
    if (CRM_Contact_BAO_Contact_Permission::allow($userID, CRM_Core_Permission::EDIT)) {
      $recurID = $page->getVar('_id');

      $queryParams = array(
        'sequential' => 1,
        'return' => array("processor_id", "id"),
        'id' => $recurID,
        'contact_id' => $contactID,
      );

      $recurRef = civicrm_api3('ContributionRecur', 'getsingle', $queryParams);

      $contributionRecurDetails = array();
      if (!empty($recurRef['processor_id'])) {
        $smartDebitResponse = CRM_DirectDebit_Sync::getSmartDebitPayerContactDetails($recurRef['processor_id']);
        foreach ($smartDebitResponse[0] as $key => $value) {
          $contributionRecurDetails[$key] = $value;
        }
      }
      // Add Smart Debit details via js
      CRM_Core_Resources::singleton()->addVars('ukdirectdebit', array( 'recurdetails' => $contributionRecurDetails));
      CRM_Core_Resources::singleton()->addScriptFile('uk.co.vedaconsulting.payment.ukdirectdebit', 'js/recurdetails.js');
      $contributionRecurDetails = json_encode($contributionRecurDetails);
      $page->assign('contributionRecurDetails', $contributionRecurDetails);
    }
  }
}

function uk_direct_debit_civicrm_validateForm($name, &$fields, &$files, &$form, &$errors) {
  // Only do recurring edit form
  if ($name == 'CRM_Contribute_Form_UpdateSubscription') {
    // only do if payment process is Smart Debit
    if (isset($fields['payment_processor_type']) && $fields['payment_processor_type'] == 'Smart_Debit') {
      $recurID = $form->getVar('_crid');
      $linkedMembership = FALSE;
      // Check this recurring contribution is linked to membership
      $membershipID = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_membership WHERE contribution_recur_id = %1', array(1 => array($recurID, 'Int')));
      if ($membershipID) {
        $linkedMembership = TRUE;
      }
      // If recurring is linked to membership then check amount is higher than membership amount
      if ($linkedMembership) {
        $query = "
	SELECT cc.total_amount
	FROM civicrm_contribution cc 
	INNER JOIN civicrm_membership_payment cmp ON cmp.contribution_id = cc.id
	INNER JOIN civicrm_membership cm ON cm.id = cmp.membership_id
	WHERE cmp.membership_id = %1";
        $membershipAmount = CRM_Core_DAO::singleValueQuery($query, array(1 => array($membershipID, 'Int')));
        if ($fields['amount'] < $membershipAmount) {
          $errors['amount'] = ts('Amount should be higher than corresponding membership amount');
        }
      }
    }
  }
}

/*
 * This hook is used to perform the IPN code on the direct debit contribution
 * This should result in the membership showing as active, so this only really applies to membership base contribution forms
 *
 * @param $formName
 * @param $form
 */
function uk_direct_debit_civicrm_postProcess( $formName, &$form ) {
  // Check the form being submitted is a contribution form
  // FIXME: Implement cancel subscription
  //CRM_Contribute_Form_CancelSubscription
  //
  if ( is_a( $form, 'CRM_Contribute_Form_Contribution_Confirm' ) ) {
    $paymentType = urlencode($form->_paymentProcessor['payment_type']);
    $isRecur = urlencode($form->_values['is_recur']);
    $paymentProcessorType = urlencode($form->_paymentProcessor['payment_processor_type']);

    // Now only do this if the payment processor type is Direct Debit as other payment processors may do this another way
    if (($paymentType == CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT) && ($paymentProcessorType == 'Smart_Debit')) {
      if (empty($form->_contactID) || empty($form->_id) || empty ($form->_contributionID))
        return;
      $contributionInfo = civicrm_api3('Contribution', 'get', array(
        'sequential' => 1,
        'return' => array("id", "contribution_recur_id", "receive_date"),
        'contact_id' => $form->_contactID,
        'id' => $form->_contributionID,
        'contribution_page_id' => $form->_id,
      ));

      $contribution = $contributionInfo['values'][0];
      $contributionID = $contribution['id'];
      $contributionRecurID = $contribution['contribution_recur_id'];
      $start_date = $contribution['receive_date'];

      if ($isRecur == 1) {
        $paymentProcessorType = urlencode($form->_paymentProcessor['payment_processor_type']);
        $membershipID = urlencode($form->_params['membershipID']);
        $contactID = urlencode($form->getVar('_contactID'));
        $invoiceID = urlencode($form->_params['invoiceID']);
        $amount = urlencode($form->_params['amount']);
        $trxn_id = urlencode($form->_params['ddi_reference']);
        $collection_day = urlencode($form->_params['preferred_collection_day']);

        CRM_Core_Payment_Smartdebitdd::callIPN("recurring_payment", $trxn_id, $contactID, $contributionID, $amount, $invoiceID, $contributionRecurID,
          null, $membershipID, $start_date, $collection_day);

        renew_membership_by_one_period($membershipID);
        return;
      }
      else {
        /* PS 23/05/2013
         * Not Recurring, only need to move the receive_date of the contribution
         */
        $contrib_result = civicrm_api("Contribution"
          , "create"
          , array('version' => '3'
          , 'id' => $contributionID
          , 'receive_date' => $start_date
          )
        );
      }
    }
  }
}

<?php

require_once 'uk_direct_debit.civix.php';

/**
 * Save setting with prefix in database
 * @param $name
 * @param $value
 */
function uk_direct_debit_civicrm_saveSetting($name, $value) {
  civicrm_api3('setting', 'create', array(CRM_DirectDebit_Form_Settings::getSettingName($name,true) => serialize($value)));
}

/**
 * Read setting that has prefix in database and return single value
 * @param $name
 * @return mixed
 */
function uk_direct_debit_civicrm_getSetting($name) {
  $settings = civicrm_api3('setting', 'get', array('return' => CRM_DirectDebit_Form_Settings::getSettingName($name,true)));
  if (isset($settings['values'][1][CRM_DirectDebit_Form_Settings::getSettingName($name,true)])) {
    return unserialize($settings['values'][1][CRM_DirectDebit_Form_Settings::getSettingName($name, true)]);
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

  uk_direct_debit_message_template();

  $syncJob = civicrm_api3('Job', 'get', array(
    'name' => "SmartDebit Sync",
  ));
  if (empty($syncJob['count'])) {
    // create a sync job
    $params = array(
      'sequential' => 1,
      'name' => 'SmartDebit Sync',
      'description' => 'Sync contacts from smartdebit to civicrm.',
      'run_frequency' => 'Daily',
      'api_entity' => 'Ukdirectdebit',
      'api_action' => 'sync',
      'is_active' => 0,
    );
    $result = civicrm_api3('job', 'create', $params);
  }
}

/**
 * Implementation of hook_civicrm_uninstall().
 */
function uk_direct_debit_civicrm_uninstall() {
  _uk_direct_debit_civix_civicrm_uninstall();

  $smartdebitMenuItems = array(
    'Import Smart Debit Contributions',
  );

  foreach ($smartdebitMenuItems as $name) {
    $itemId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', $name, 'id', 'name', TRUE);
    if ($itemId) {
      CRM_Core_BAO_Navigation::processDelete($itemId);
    }
  }
  CRM_Core_BAO_Navigation::resetNavigation();
}

/**
 * Implementation of hook_civicrm_enable
 */
function uk_direct_debit_civicrm_enable() {
  _uk_direct_debit_civix_civicrm_enable();

  /*if (CRM_Extension_System::singleton()->getManager()->getStatus('uk.co.vedaconsulting.payment.smartdebitdd') == 'disabled') {
    CRM_Core_Session::setStatus("", ts('Enabling Smart Debit extension'), "success");
    $result = civicrm_api3('Extension', 'enable', array(
      'sequential' => 1,
      'keys' => "uk.co.vedaconsulting.payment.smartdebitdd",
    ));
  }*/
}

function uk_direct_debit_civicrm_disable() {
  /*if (CRM_Extension_System::singleton()->getManager()->getStatus('uk.co.vedaconsulting.payment.smartdebitdd') == 'installed') {
    CRM_Core_Session::setStatus("", ts('Disabling Smart Debit extension'), "success");
    $result = civicrm_api3('Extension', 'disable', array(
      'sequential' => 1,
      'keys' => "uk.co.vedaconsulting.payment.smartdebitdd",
    ));
  }*/
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
    'name'       => 'Mark Auddis',
    'url'        => 'civicrm/directdebit/syncsd/activity?reset=1',
    'permission' => 'administer CiviCRM',
    'operator'   => null,
    'separator'  => null,
  );
  $item[] = array (
    'name'       => 'UK Direct Debit Settings',
    'url'        => 'civicrm/directdebit/settings?reset=1',
    'permission' => 'administer CiviCRM',
    'operator'   => "NULL",
    'separator'  => 1,
  );
  _uk_direct_debit_civix_insert_navigation_menu($params, 'Administer/UK Direct Debit', $item[1]);
  _uk_direct_debit_civix_insert_navigation_menu($params, 'Administer/UK Direct Debit', $item[2]);

  $item[] = array(
    'label' => ts('Import Smart Debit Contributions'),
    'name'  => 'Import Smart Debit Contributions',
    'url'   => 'civicrm/directdebit/syncsd/import?reset=1',
    'permission' => 'administer CiviCRM',
  );

  _uk_direct_debit_civix_insert_navigation_menu($params, 'Contributions', $item[3]);
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

function uk_direct_debit_civicrm_buildForm( $formName, &$form ) {
  // Contribution completed (thankyou page)
  if ($formName == 'CRM_Contribute_Form_Contribution_ThankYou') {
    // Gocardless
    if ($form->_paymentProcessor['payment_processor_type'] == 'Gocardless') {
      if (isset($_GET['redirect_flow_id'])) {

        $config = CRM_Core_Config::singleton();
        $extenDr = $config->extensionsDir;
        $gocardless_extension_path = $extenDr . DIRECTORY_SEPARATOR . 'uk.co.vedaconsulting.payment.gocardlessdd' . DIRECTORY_SEPARATOR;
        require_once($gocardless_extension_path . 'gocardless_includes.php');

        $paymentProcessorType = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
        $paymentProcessorTypeId = CRM_Utils_Array::key('Gocardless', $paymentProcessorType);
        $domainID = CRM_Core_Config::domainID();

        $sql = " SELECT user_name ";
        $sql .= " ,      password ";
        $sql .= " ,      signature ";
        $sql .= " ,      subject ";
        $sql .= " ,      url_api ";
        $sql .= " FROM civicrm_payment_processor ";
        $sql .= " WHERE payment_processor_type_id = %1 ";
        $sql .= " AND is_test= %2 ";
        $sql .= " AND domain_id = %3 ";

        $isTest = 0;
        if ($form->_mode == 'test') {
          $isTest = 1;
        }

        $params = array(1 => array($paymentProcessorTypeId, 'Integer')
        , 2 => array($isTest, 'Int')
        , 3 => array($domainID, 'Int')
        );

        $dao = CRM_Core_DAO::executeQuery($sql, $params);

        if ($dao->fetch()) {
          $access_token = $dao->user_name;
          $api_url = $dao->url_api;
        }

        if ($access_token && $api_url) {
          $redirect_flow_id = $_GET['redirect_flow_id'];
          $session_token = $_GET['qfKey'];
          //For action complete params
          $complete_params = array(
            "session_token" => $session_token,
          );

          $data = json_encode(array('data' => (object)$complete_params));
          $redirect_path = "redirect_flows/" . $redirect_flow_id . "/actions/complete";
          // Create header with access token
          $header = array();
          $header[] = 'GoCardless-Version: 2015-07-06';
          $header[] = 'Accept: application/json';
          $header[] = 'Content-Type: application/json';
          $header[] = 'Authorization: Bearer ' . $access_token;

          $response = requestPostGocardless($api_url, $redirect_path, $header, $data);
          CRM_Core_Error::debug_var('$response in thank you page', $response);
          if (strtoupper($response["Status"] == 'OK')) {
            $contactID = $_GET['cid'];
            $pageID = $form->_id;
            $sql = "
                  SELECT id AS contribution_id, contribution_recur_id, total_amount
                  FROM civicrm_contribution
                  WHERE contact_id = %1
                  AND contribution_page_id = %2
                  ORDER BY id DESC
                  LIMIT 1
          ";

            $sql_params = array(1 => array($contactID, 'Int'), 2 => array($pageID, 'Int'));
            $selectdao = CRM_Core_DAO::executeQuery($sql, $sql_params);
            $selectdao->fetch();
            $contributionId = $selectdao->contribution_id;
            $contributionRecurId = $selectdao->contribution_recur_id;
            $membershipID = CRM_Core_DAO::singleValueQuery('select membership_id from civicrm_membership_payment where contribution_id = %1', array(1 => array($contributionId, 'Int')));
            $interval_unit = 'monthly';
            $interval_unit_civi_format = 'month';
            if ($membershipID) {//Membership Join Form
              $findMembershipDurationQuery = "
              SELECT cmt.duration_unit as unit, cmt.duration_interval as duration
              FROM civicrm_membership cm 
              INNER JOIN civicrm_membership_type cmt ON cmt.id = cm.membership_type_id
              WHERE cm.id = %1";
              $findMembershipDurationDao = CRM_Core_DAO::executeQuery($findMembershipDurationQuery, array(1 => array($membershipID, 'Int')));
              $findMembershipDurationDao->fetch();
              $interval_duration = $findMembershipDurationDao->duration;
              if ($findMembershipDurationDao->unit == 'year') {
                $interval_unit_civi_format = $findMembershipDurationDao->unit;
                $interval_unit = 'yearly';
              }
            }
            if (!$membershipID && $contributionRecurId) { // Donation Form
              $interval_duration = $form->_params['frequency_interval'];
              if ($form->_params['frequency_unit'] == 'year') {
                $interval_unit_civi_format = $form->_params['frequency_unit'];
                $interval_unit = 'yearly';
              }
            }
            // Create subscription
            $subscription_params = array(
              "amount" => 100 * $selectdao->total_amount,
              "currency" => 'GBP',
              "name" => $form->_values['title'],
              "interval_unit" => $interval_unit,
              "interval" => $interval_duration,
              "links" => array("mandate" => $response['redirect_flows']['links']['mandate'])
            );

            if (!empty($form->_params['preferred_collection_day']) && $interval_unit == 'monthly') {
              $subscription_params['day_of_month'] = $form->_params['preferred_collection_day'];
            } else if ($interval_unit == 'monthly') {
              $subscription_params['day_of_month'] = "1";//This is required field by Gocardless Pro API
            }
            $data = json_encode(array('subscriptions' => (object)$subscription_params));
            $redirect_path = "subscriptions";
            //require_once $gocardless_extension_path.'gocardless_includes.php';
            $response = requestPostGocardless($api_url, $redirect_path, $header, $data);
            CRM_Core_Error::debug_var('$response in thank you page after calling subscriptino', $response);
            if (strtoupper($response["Status"] == 'OK')) {
              $start_date = date('Y-m-d', strtotime($response['subscriptions']['start_date']));
              $trxn_id = $response['subscriptions']['id'];
              $recurring_contribution_status_id = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');

              $query = "
                  UPDATE civicrm_contribution
                  SET trxn_id = %1 , contribution_status_id = 1, receive_date = %3
                  WHERE id = %2";


              $sql_params = array(1 => array($trxn_id, 'String'), 2 => array($contributionId, 'Int'), 3 => array($start_date, 'String'));
              $dao = CRM_Core_DAO::executeQuery($query, $sql_params);
              if ($contributionRecurId) {
                // Update contribution recur trxn_id and start_date and status.
                $recurUpdateQuery = "
                      UPDATE civicrm_contribution_recur
                      SET trxn_id = %1, contribution_status_id = %2, start_date = %3
                      WHERE id = %4";
                $recurUpdateQueryParams = array(
                  1 => array($trxn_id, 'String'),
                  2 => array($recurring_contribution_status_id, 'Int'),
                  3 => array($start_date, 'String'),
                  4 => array($contributionRecurId, 'Int'));
                $recurringDao = CRM_Core_DAO::executeQuery($recurUpdateQuery, $recurUpdateQueryParams);
              }

              if ($membershipID) { // Update memberhsip dates if it is memberhip page
                $membershipEndDateString = date("Y-m-d", strtotime(date("Y-m-d", strtotime($start_date)) . " +$interval_duration $interval_unit_civi_format"));
                $updatedMember = civicrm_api("Membership", "create",
                  array('version' => '3',
                    'id' => $membershipID,
                    'end_date' => $membershipEndDateString,
                    'start_date' => $start_date,
                    'join_date' => $start_date,
                    'status_id' => 1,//New
                  ));
              }
            }
            //CRM_Utils_System::redirect($response['redirect_flows']['redirect_url']);
          } else {
            CRM_Core_Error::debug_var('uk_co_vedaconsulting_payment_gocardlessdd uk_direct_debit_php thank you page api call response error', $response);
            CRM_Core_Error::debug_var('uk_co_vedaconsulting_payment_gocardlessdd uk_direct_debit_php thank you page api post params $api_url ', $api_url);
            CRM_Core_Error::debug_var('uk_co_vedaconsulting_payment_gocardlessdd uk_direct_debit_php thank you page api post params $redirect_path ', $redirect_path);
            CRM_Core_Error::debug_var('uk_co_vedaconsulting_payment_gocardlessdd uk_direct_debit_php thank you page api post params $header ', $header);
            CRM_Core_Error::debug_var('uk_co_vedaconsulting_payment_gocardlessdd uk_direct_debit_php thank you page api post params $data ', $data);
            CRM_Core_Session::setStatus('Could not create subscription through Gocardless API, contact Admin', ts("Error"), "error");
            CRM_Utils_System::civiExit();
          }
        }
      }

      if (isset($_GET['resource_id'])) {
        CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_buildForm' . print_r($form, true), $out = false);
        require_once 'lib/GoCardless.php';

        $paymentProcessorType = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
        $paymentProcessorTypeId = CRM_Utils_Array::key('Gocardless', $paymentProcessorType);
        $domainID = CRM_Core_Config::domainID();

        $sql = " SELECT user_name ";
        $sql .= " ,      password ";
        $sql .= " ,      signature ";
        $sql .= " ,      subject ";
        $sql .= " FROM civicrm_payment_processor ";
        $sql .= " WHERE payment_processor_type_id = %1 ";
        $sql .= " AND is_test= %2 ";
        $sql .= " AND domain_id = %3 ";

        $isTest = 0;
        if ($form->_mode == 'test') {
          $isTest = 1;
        }

        $params = array(1 => array($paymentProcessorTypeId, 'Integer')
        , 2 => array($isTest, 'Int')
        , 3 => array($domainID, 'Int')
        );

        $dao = CRM_Core_DAO::executeQuery($sql, $params);

        if ($dao->fetch()) {
          $app_id = $dao->user_name;
          $app_secret = $dao->password;
          $merchant_id = $dao->signature;
          $access_token = $dao->subject;
        }

        $account_details = array(
          'app_id' => $app_id,
          'app_secret' => $app_secret,
          'merchant_id' => $merchant_id,
          'access_token' => $access_token,
        );

        // Fail nicely if no account details set
        if (!$account_details['app_id'] && !$account_details['app_secret']) {
          echo '<p>First sign up to <a href="http://gocardless.com">GoCardless</a> and
        copy your sandbox API credentials from the \'Developer\' tab into the top of
        this script.</p>';
          exit();
        }

        // Initialize GoCardless
        // Set $environment to 'production' if live. Default is 'sandbox'
        if ($form->_mode == 'live') {
          GoCardless::$environment = 'production';
        }

        GoCardless::set_account_details($account_details);

        $confirm_params = array(
          'resource_id' => $_GET['resource_id'],
          'resource_type' => $_GET['resource_type'],
          'resource_uri' => $_GET['resource_uri'],
          'signature' => $_GET['signature']
        );

        // State is optional
        if (isset($_GET['state'])) {
          $confirm_params['state'] = $_GET['state'];
        }

        $contactID = $_GET['cid'];
        $pageID = $form->_id;
        CRM_Core_Error::debug_log_message('in the build thank you confirm parms' . print_r($confirm_params, true), $out = false);
        $confirmed_resource = GoCardless::confirm_resource($confirm_params);
        CRM_Core_Error::debug_log_message('in the bild thank you confirmed_resources ' . print_r($confirmed_resource, true), $out = false);
        $start_date = date('Y-m-d', strtotime($confirmed_resource->start_at));
        $trxn_id = $confirmed_resource->id;
        $recurring_contribution_status_id = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');

        $query = "
            UPDATE civicrm_contribution
            SET trxn_id = %1 , contribution_status_id = 1, receive_date = %3
            WHERE id = %2";

        $sql = "
            SELECT id as contribution_id, contribution_recur_id
            FROM civicrm_contribution
            WHERE contact_id = %1
            AND contribution_page_id = %2
            ORDER BY id DESC
            LIMIT 1";

        $sql_params = array(1 => array($contactID, 'Int'), 2 => array($pageID, 'Int'));
        $selectdao = CRM_Core_DAO::executeQuery($sql, $sql_params);
        $selectdao->fetch();
        $contributionId = $selectdao->contribution_id;
        $contributionRecurId = $selectdao->contribution_recur_id;

        $sql_params = array(1 => array($_GET['resource_id'], 'String'), 2 => array($contributionId, 'Int'), 3 => array($start_date, 'String'));
        $dao = CRM_Core_DAO::executeQuery($query, $sql_params);

        // Update contribution recur trxn_id and start_date and status.
        $recurUpdateQuery = "
	      UPDATE civicrm_contribution_recur
	      SET trxn_id = %1, contribution_status_id = %2, start_date = %3
	      WHERE id = %4";
        $recurUpdateQueryParams = array(
          1 => array($trxn_id, 'String'),
          2 => array($recurring_contribution_status_id, 'Int'),
          3 => array($start_date, 'String'),
          4 => array($contributionRecurId, 'Int'));
        $recurringDao = CRM_Core_DAO::executeQuery($recurUpdateQuery, $recurUpdateQueryParams);

        CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_buildform #1');
        CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_buildform form=' . print_r($form, TRUE));

        CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_buildform #1');
      }

      // If no subscription created
      if (empty($_GET['resource_id']) && empty($_GET['redirect_flow_id'])) {
        $cancelURL = CRM_Utils_System::url('civicrm/contribute/transact',
          "_qf_Main_display=1&cancel=1&qfKey={$_GET['qfKey']}",
          true, null, false);

        CRM_Utils_System::redirect($cancelURL);

        $contactID = $_GET['cid'];
        $pageID = $form->_id;

        $query = "
            UPDATE civicrm_contribution
            SET contribution_status_id = %1
            WHERE contact_id = %2
            AND contribution_page_id = %3";

        $sql_params = array(1 => array(4, 'Int'), 2 => array($contactID, 'Int'), 3 => array($pageID, 'Int'));
        $dao = CRM_Core_DAO::executeQuery($query, $sql_params);
      }
    }

    //Smart Debit
    if (isset($form->_paymentProcessor['payment_processor_type']) && ($form->_paymentProcessor['payment_processor_type'] == 'Smart_Debit')) {
      CRM_Core_Region::instance('contribution-thankyou-billing-block')->update('default', array(
        'disabled' => TRUE,
      ));
      CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
        'template' => 'CRM/Contribute/Form/Contribution/DirectDebitMandate.tpl',
      ));
    }
  }
  // Confirm Contribution (check details and confirm)
  elseif ($formName == 'CRM_Contribute_Form_Contribution_Confirm') {
    if (isset($form->_paymentProcessor['payment_processor_type']) && ($form->_paymentProcessor['payment_processor_type'] == 'Smart_Debit')) {
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
    }
  }
  // Main payment form
  elseif (($formName == 'CRM_Financial_Form_Payment')
    || $formName == 'CRM_Contribute_Form_Contribution_Main') {
    // FIXME: MJW 20170331: We show this directdebit mandate for Gocardless as well, should it only be for Smart Debit?
    CRM_Core_Region::instance('billing-block-pre')->add(array(
      'template' => 'CRM/DirectDebit/BillingBlock/BillingBlockPre.tpl',
    ));
    CRM_Core_Region::instance('billing-block-post')->add(array(
      'template' => 'CRM/DirectDebit/BillingBlock/BillingBlockPost.tpl',
    ));
  }
  // FIXME: Where is this form used?
  elseif ($formName == 'CRM_DirectDebit_Form_DirectDebit') {
    if ($form->_eventId == EVENT_ID) {
      $form->addRule('price_3', ts('This field is required.'), 'required');
    }
  }
  // FIXME: Where is this form used?
  elseif ($formName == 'CRM_Contribute_Form_UpdateSubscription') {
    $paymentProcessor = $form->_paymentProcessor;
    if (isset($paymentProcessor['payment_processor_type']) && ($paymentProcessor['payment_processor_type'] == 'Smart_Debit')) {
      $recurID = $form->getVar('_crid');
      $linkedMembership = FALSE;
      $membershipID = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_membership WHERE contribution_recur_id = %1', array(1=>array($recurID, 'Int')));
      if ($membershipID) {
        $linkedMembership = TRUE;
      }
      $form->removeElement('installments');
      $frequencyType = array(
        'D'  => 'Daily',
        'W'  => 'Weekly',
        'M'  => 'Monthly',
        'Y'  => 'Annually'
      );

      $form->addElement('select', 'frequency_unit', ts('Frequency'),
        array('' => ts('- select -')) + $frequencyType
      );
      $form->addDate('start_date', ts('Start Date'), FALSE, array('formatType' => 'custom'));
      $form->addDate('end_date', ts('End Date'), FALSE, array('formatType' => 'custom'));
      $form->add('text', 'account_holder', ts('Account Holder'), array('size' => 20, 'maxlength' => 18, 'autocomplete' => 'on'));
      $form->add('text', 'bank_account_number', ts('Bank Account Number'), array('size' => 20, 'maxlength' => 8, 'autocomplete' => 'off'));
      $form->add('text', 'bank_identification_number', ts('Sort Code'), array('size' => 20, 'maxlength' => 6, 'autocomplete' => 'off'));
      $form->add('text', 'bank_name', ts('Bank Name'), array('size' => 20, 'maxlength' => 64, 'autocomplete' => 'off'));
      $form->add('hidden', 'payment_processor_type', 'Smart_Debit');
      $subscriptionDetails  = $form->getVar('_subscriptionDetails');
      $reference            = $subscriptionDetails->subscription_id;
      $frequencyUnit        = $subscriptionDetails->frequency_unit;
      $frequencyUnits       = array('D' =>'day','W'=> 'week','M'=> 'month', 'Y' => 'year');
      $recur = new CRM_Contribute_BAO_ContributionRecur();
      $recur->processor_id  = $reference;
      $recur->find(TRUE);
      $startDate        = $recur->start_date;
      list($defaults['start_date'], $defaults['start_date_time']) = CRM_Utils_Date::setDateDefaults($startDate, NULL);
      $defaults['frequency_unit'] = array_search($frequencyUnit, $frequencyUnits);
      $form->setDefaults($defaults);
      if ($linkedMembership) {
        $form->assign('membership', TRUE);
        $e =& $form->getElement('frequency_unit');
        $e->freeze();
        $e =& $form->getElement('start_date');
        $e->freeze();
      }
    }
  }
  // FIXME: Where is this form used?
  elseif ($formName == 'CRM_Contribute_Form_UpdateBilling') {
    $paymentProcessor = $form->_paymentProcessor;
    if($paymentProcessor['payment_processor_type'] == 'Smart_Debit') {
      //Build billing details block
      $ddForm = new CRM_DirectDebit_Form_Main();
      $ddForm->buildOfflineDirectDebit($form);
    }
  }
  // FIXME: Where is this form used?
  elseif ($formName == 'CRM_Contribute_Form_CancelSubscription') {
    $paymentProcessorObj      = $form->getVar('_paymentProcessorObj');
    $paymentProcessorName     = $paymentProcessorObj->_processorName;
    if ($paymentProcessorName == 'Smart Debit Processor') {
      $form->addRule('send_cancel_request', 'Please select one of these options', 'required');
    }
  }

  // FIXME: Which forms use this code?
  if ( isset($form->_paymentProcessor['payment_type']) && ($form->_paymentProcessor['payment_type'] == CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT) ) {
    // Set DDI Reference
    if (!empty($form->_submitValues['ddi_reference']))
      $ddi_reference = $form->_submitValues['ddi_reference'];
    if (isset($form->_params)) {
      if (!empty($form->_params['ddi_reference']))
        $ddi_reference = $form->_params['ddi_reference'];
    }

    if ($form->_paymentProcessor['payment_processor_type'] == 'Smart_Debit') {
      if (!empty($ddi_reference)) {
        /*** Get details for ddi
         * then setup local array with all details
         * then smarty assign the array
         */
        $query = " SELECT * ";
        $query .= " FROM civicrm_direct_debit ";
        $query .= " WHERE ddi_reference = %1 ";
        $query .= " ORDER BY id DESC ";
        $query .= " LIMIT 1 ";

        $params = array(1 => array((string)$ddi_reference, 'String'));
        $dao = CRM_Core_DAO::executeQuery($query, $params);

        if ($dao->fetch()) {
          $uk_direct_debit['company_name'] = CRM_DirectDebit_Base::getCompanyName();
          $uk_direct_debit['bank_name'] = $dao->bank_name;
          $uk_direct_debit['branch'] = $dao->branch;
          $uk_direct_debit['address1'] = $dao->address1;
          $uk_direct_debit['address2'] = $dao->address2;
          $uk_direct_debit['address3'] = $dao->address3;
          $uk_direct_debit['address4'] = $dao->address4;
          $uk_direct_debit['town'] = $dao->town;
          $uk_direct_debit['county'] = $dao->county;
          $uk_direct_debit['postcode'] = $dao->postcode;
          $uk_direct_debit['first_collection_date'] = $dao->first_collection_date;
          $uk_direct_debit['preferred_collection_day'] = $dao->preferred_collection_day;
          $uk_direct_debit['confirmation_method'] = $dao->confirmation_method;
          $uk_direct_debit['formatted_preferred_collection_day'] = CRM_DirectDebit_Base::formatPreferredCollectionDay($dao->preferred_collection_day);
        }
      }
      else {
        // Set defaults
        $uk_direct_debit['formatted_preferred_collection_day'] = '';
        $uk_direct_debit['company_name']             = CRM_DirectDebit_Base::getCompanyName();
        $uk_direct_debit['bank_name']                = '';
        $uk_direct_debit['branch']                   = '';
        $uk_direct_debit['address1']                 = '';
        $uk_direct_debit['address2']                 = '';
        $uk_direct_debit['address3']                 = '';
        $uk_direct_debit['address4']                 = '';
        $uk_direct_debit['town']                     = '';
        $uk_direct_debit['county']                   = '';
        $uk_direct_debit['postcode']                 = '';
        $uk_direct_debit['first_collection_date']    = '';
        $uk_direct_debit['preferred_collection_day'] = '';
        $uk_direct_debit['confirmation_method']      = '';
        $uk_direct_debit['formatted_preferred_collection_day'] = '';
      }
    }
    else if ($form->_paymentProcessor['payment_processor_type'] == 'Gocardless') {
      // Gocardless
      $uk_direct_debit['formatted_preferred_collection_day'] 	= CRM_DirectDebit_Base::formatPreferredCollectionDay($form->_params['preferred_collection_day']);
      $collectionDate                                         = CRM_DirectDebit_Base::firstCollectionDate($form->_params['preferred_collection_day']);
      $uk_direct_debit['first_collection_date']               = $collectionDate->format("Y-m-d");
      $uk_direct_debit['confirmation_method']                 = 'EMAIL'; //KJ fixme as we don't give options to choose
      $uk_direct_debit['company_name']                        = CRM_DirectDebit_Base::getCompanyName();
    }

    $form->assign( 'direct_debit_details', $uk_direct_debit );
    $form->assign( 'service_user_number', CRM_DirectDebit_Base::getSUNParts());
    $form->assign( 'company_address', CRM_DirectDebit_Base::getCompanyAddress());
    $form->assign( 'directDebitDate', date('Ymd'));
  }
}

/**
 * Send a post request with cURL
 *
 * @param $url
 */
function call_CiviCRM_IPN($url){
  // Set a one-minute timeout for this script
  set_time_limit(160);

  $options = array(
    CURLOPT_RETURNTRANSFER => true, // return web page
    CURLOPT_HEADER => false, // don't return headers
    // TO DO Should be posting CURLOPT_POST => true,
    // CURLOPT_HTTPHEADER => array("Accept: application/xml"),
    // CURLOPT_USERAGENT => "XYZ Co's PHP iDD Client", // Let SmartDebit see who we are
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
  );

  $session = curl_init( $url );
  curl_setopt_array( $session, $options );

  // Tell curl that this is the body of the POST
  // TO DO - Should be a post - need to fix
  // curl_setopt ($session, CURLOPT_POSTFIELDS, $data);

  // $output contains the output string
  $output = curl_exec($session);
  $header = curl_getinfo($session);
} // END function requestPost()

/**
 * Renew Membership by one period if the membership expires
 * before the contribution collection date starts
 * @author shobbs
 * @param $membershipID
 */
function renew_membership_by_one_period($membershipID) {

  // Check if Membership End Date has been updated
  $getMembership = civicrm_api("Membership"
    ,"get"
    , array ('version'       => '3'
    ,'membership_id' => $membershipID
    )
  );

  $membershipEndDate   = $getMembership['values'][$membershipID]['end_date'];
  $contributionRecurID = $getMembership['values'][$membershipID]['contribution_recur_id'];

  // Get the Contribution ID
  $getMembershipPayment = civicrm_api("MembershipPayment"
    ,"get"
    , array ('version'       => '3'
    ,'membership_id' => $membershipID
    )
  );

  $contributionID = $getMembershipPayment['values'][$getMembershipPayment['id']]['contribution_id'];

  // Get the contribution
  $contribution = civicrm_api("Contribution"
    ,"get"
    ,array ('version'         => '3'
    ,'contribution_id' => $contributionID
    )
  );

  $contributionReceiveDate = $contribution['values'][$contributionID]['receive_date'];
  $contributionReceiveDateString = date("Ymd", strtotime($contributionReceiveDate));
  $membershipEndDateString = date("Ymd", strtotime($membershipEndDate));

  if ($contributionReceiveDateString > $membershipEndDateString) {
    $contributionRecurring = civicrm_api("ContributionRecur"
      ,"get"
      , array ('version' => '3'
      ,'id'      => $contributionRecurID
      )
    );

    $frequencyUnit = $contributionRecurring['values'][$contributionRecurID]['frequency_unit'];
    if (!is_null($frequencyUnit)) {
      $membershipEndDateString = date("Y-m-d",strtotime(date("Y-m-d", strtotime($membershipEndDate)) . " +1 $frequencyUnit"));
    }
  }
  $updatedMember = civicrm_api("Membership"
    ,"create"
    , array ('version'       => '3',
      'membership_id' => $membershipID,
      'id'            => $membershipID,
      'end_date'      => $membershipEndDateString,
    )
  );
}

/**
 * TODO To add "" to search contribution actions
 * @author mzeman
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
  if(($objectName == 'ContributionRecur') && ($op =='edit')) {
    if (isset($params['payment_processor_type']) && ($params['payment_processor_type'] == 'Smart_Debit')) {
      $params['start_date'] = CRM_Utils_Date::processDate($params['start_date']);
      $params['end_date'] = CRM_Utils_Date::processDate($params['end_date']);
      // FIXME: use CRM_DirectDebit_Base::translateSmartDebitFrequencytoCiviCRM()
      $frequencyUnit = $params['frequency_unit'];
      $frequencyUnits = array('D' => 'day', 'W' => 'week', 'M' => 'month', 'Y' => 'year');
      $params['frequency_unit'] = CRM_Utils_Array::value($frequencyUnit, $frequencyUnits);
    }
  }
}

function uk_direct_debit_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contribute_Page_ContributionRecur') {
    $session = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');
    if (CRM_Contact_BAO_Contact_Permission::allow($contactID, CRM_Core_Permission::EDIT)) {
      $recurID = $page->getVar('_id');
      $query = "
	SELECT cr.trxn_id FROM civicrm_contribution_recur cr
	INNER JOIN civicrm_payment_processor cpp ON cpp.id = cr.payment_processor_id
	INNER JOIN civicrm_payment_processor_type cppt ON cppt.id = cpp.payment_processor_type_id
	WHERE cppt.name = %1 AND cr.id = %2";

      $queryParams = array (
        1 => array('Smart_Debit', 'String'),
        2 => array($recurID, 'Int'),
      );

      $smartDebitReference = CRM_Core_DAO::singleValueQuery($query, $queryParams);
      $contributionRecurDetails = array();
      if (!empty($smartDebitReference)) {
        $smartDebitResponse = CRM_DirectDebit_Form_Sync::getSmartDebitPayments($smartDebitReference);
        foreach ($smartDebitResponse[0] as $key => $value) {
          $contributionRecurDetails[$key] = $value;
        }
        $contributionRecurDetails = json_encode($contributionRecurDetails);
        $page->assign('contributionRecurDetails', $contributionRecurDetails);
      }
    }
  }
}

function uk_direct_debit_civicrm_validateForm($name, &$fields, &$files, &$form, &$errors) {
  // Only do recurring edit form
  if (!in_array($name, array(
    'CRM_Contribute_Form_UpdateSubscription',
  ))) {
    return;
  }
  // only do if payment process is Smart Debit
  if (isset($fields['payment_processor_type']) && $fields['payment_processor_type'] == 'Smart_Debit') {
    $recurID	      = $form->getVar('_crid');
    $linkedMembership = FALSE;
    // Check this recurring contribution is linked to membership
    $membershipID = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_membership WHERE contribution_recur_id = %1', array(1=>array($recurID, 'Int')));
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
      if($fields['amount'] < $membershipAmount) {
        $errors['amount'] = ts('Amount should be higher than corresponding membership amount');
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
  if ( is_a( $form, 'CRM_Contribute_Form_Contribution_Confirm' ) ) {
    $paymentType = urlencode( $form->_paymentProcessor['payment_type'] );
    $isRecur     = urlencode( $form->_values['is_recur'] );
    $paymentProcessorType     = urlencode( $form->_paymentProcessor['payment_processor_type']);

    // Now only do this is the payment processor type is Direct Debit as other payment processors may do this another way
    if ( $paymentType == 2 && ($paymentProcessorType == 'Smart_Debit') ) {
      $aContribParam =
        array(
          1 => array($form->_contactID, 'Integer'),
          2 => array($form->_id, 'Integer'),
        );
      $query  = "SELECT id, contribution_recur_id
        FROM civicrm_contribution
        WHERE contact_id = %1 AND contribution_page_id = %2
        ORDER BY id DESC LIMIT 1";
      $dao    = CRM_Core_DAO::executeQuery($query, $aContribParam);
      $dao->fetch();

      $contributionID      = $dao->id;
      $contributionRecurID = $dao->contribution_recur_id;

      $start_date     = urlencode( $form->_values['start_date'] );

      if ( $isRecur == 1 ) {
        $paymentProcessorType = urlencode( $form->_paymentProcessor['payment_processor_type'] );
        $membershipID         = urlencode( $form->_params['membershipID'] );
        $contactID            = urlencode( $form->getVar( '_contactID' ) );
        $invoiceID            = urlencode( $form->_params['invoiceID'] );
        $amount               = urlencode( $form->_params['amount'] );
        $trxn_id              = urlencode( $form->_params['trxn_id'] );
        $collection_day       = urlencode( $form->_params['preferred_collection_day'] );

        $query = "processor_name=".$paymentProcessorType."&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&membershipID=".$membershipID."&invoice=".$invoiceID."&mc_gross=".$amount."&payment_status=Completed&txn_type=recurring_payment&contributionRecurID=$contributionRecurID&txn_id=$trxn_id&first_collection_date=$start_date&collection_day=$collection_day";

        // Get the recur ID for the contribution
        $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
        call_CiviCRM_IPN($url);

        renew_membership_by_one_period($membershipID);

        return;
      } else {
        /* PS 23/05/2013
         * Not Recurring, only need to move the receive_date of the contribution
         */
        $contrib_result = civicrm_api(   "Contribution"
          ,"create"
          ,array ('version'         => '3'
          ,'contribution_id' => $contributionID
          ,'id'              => $contributionID
          ,'receive_date'    => $start_date
          )
        );
      } // Recurring
    } // Paid by DD
  }
}

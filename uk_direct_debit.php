<?php

function register_install() {
  Global $base_url;
  $base_url;

  $sUrl  = sprintf( "www.vedaconsulting.co.uk/direct-debit-extension-registration?domain_name='%s'&timestamp='%s'"
                  , urlencode( $base_url )
                  , date('Y-m-d H:i:s')
                  );
  $oCurl = curl_init();

  curl_setopt( $oCurl, CURLOPT_URL, $sUrl );
  curl_exec  ( $oCurl                     );
  curl_close ( $oCurl                     );
}

function uk_direct_debit_civicrm_install( ) {
  register_install();

  require_once 'CRM/Core/BAO/Setting.php';
  CRM_Core_BAO_Setting::setItem('Veda Test Company',
          'UK Direct Debit',
          'company_name'
        );

  CRM_Core_BAO_Setting::setItem('15',
          'UK Direct Debit',
          'collection_interval'
        );

  CRM_Core_BAO_Setting::setItem('1,8,22',
          'UK Direct Debit',
          'collection_days'
        );

  CRM_Core_BAO_Setting::setItem('123456',
          'UK Direct Debit',
          'service_user_number'
        );

  CRM_Core_BAO_Setting::setItem('0171 567 4545',
          'UK Direct Debit',
          'telephone_number'
        );

  CRM_Core_BAO_Setting::setItem('fred.bloggs@vedaconsulting.co.uk',
          'UK Direct Debit',
          'email_address'
        );

  CRM_Core_BAO_Setting::setItem('Address Line 1',
          'UK Direct Debit',
          'company_address1'
        );

  CRM_Core_BAO_Setting::setItem('Address Line 2',
          'UK Direct Debit',
          'company_address2'
        );

  CRM_Core_BAO_Setting::setItem('Address Line 3',
          'UK Direct Debit',
          'company_address3'
        );

  CRM_Core_BAO_Setting::setItem('Address Line 4',
          'UK Direct Debit',
          'company_address4'
        );

  CRM_Core_BAO_Setting::setItem('Address Town',
          'UK Direct Debit',
          'company_town'
        );

  CRM_Core_BAO_Setting::setItem('Address County',
          'UK Direct Debit',
          'company_county'
        );

  CRM_Core_BAO_Setting::setItem('Address Postcode',
          'UK Direct Debit',
          'company_postcode'
        );

  CRM_Core_BAO_Setting::setItem('8',
          'UK Direct Debit',
          'payment_instrument_id'
        );

  CRM_Core_BAO_Setting::setItem('vedaconsulting.co.uk',
          'UK Direct Debit',
          'domain_name'
        );

  CRM_Core_BAO_Setting::setItem('WEB',
          'UK Direct Debit',
          'transaction_prefix'
        );

  CRM_Core_BAO_Setting::setItem('Y',
          'UK Direct Debit',
          'auto_renew_membership'
        );
  CRM_Core_BAO_Setting::setItem('payerReference',
          'UK Direct Debit',
          'api_contact_key'
        );
  CRM_Core_BAO_Setting::setItem(NULL,
          'UK Direct Debit',
          'api_contact_val_regex'
        );
  CRM_Core_BAO_Setting::setItem(NULL,
          'UK Direct Debit',
          'api_contact_val_regex_index'
        );
  CRM_Core_BAO_Setting::setItem(NULL,
          'UK Direct Debit',
          'financial_type'
        );

  // Create an Direct Debit Activity Type
  require_once 'api/api.php';
  $optionGroupParams = array('version'         => '3'
                            ,'name'            => 'activity_type');
  $optionGroup = civicrm_api('OptionGroup', 'Get', $optionGroupParams);

  $activityParams = array('version'         => '3'
                         ,'option_group_id' => $optionGroup['id']
                         ,'name'            => 'Direct Debit Sign Up'
                         ,'description'     => 'Direct Debit Sign Up');
  $activityType = civicrm_api('OptionValue', 'Create', $activityParams);

  CRM_Core_BAO_Setting::setItem($activityType['values'][$activityType['id']]['value']
                               ,'UK Direct Debit'
                               ,'activity_type'
                               );
  $activityParams = array('version'         => '3'
                         ,'option_group_id' => $optionGroup['id']
                         ,'name'            => 'DD Confirmation Letter'
                         ,'description'     => 'DD Confirmation Letter');
  $activityType = civicrm_api('OptionValue', 'Create', $activityParams);

  CRM_Core_BAO_Setting::setItem($activityType['values'][$activityType['id']]['value']
                               ,'UK Direct Debit'
                               ,'activity_type_letter'
                               );

   // Create an Direct Debit Payment Instrument
  $optionGroupParams = array('version'         => '3'
                            ,'name'            => 'payment_instrument');
  $optionGroup = civicrm_api('OptionGroup', 'Get', $optionGroupParams);

  $paymentParams = array('version'         => '3'
                         ,'option_group_id' => $optionGroup['id']
                         ,'name'            => 'Direct Debit'
                         ,'label'           => 'Direct Debit'
                         ,'description'     => 'Direct Debit');
  $paymentInstrument = civicrm_api('OptionValue', 'Create', $paymentParams);

    // On install, create a table for keeping track of online direct debits
    require_once "CRM/Core/DAO.php";
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

  /**
   * TODO Create contribution type Direct Debit
   * TODO New Activity Type Direct Debit Sign Up
   * TODO New Activity Type Direct Debit Mandate Sent
   */

  //$import->run($xml_file);
  //require_once('CRM_Core_Invoke');
  //CRM_Core_Invoke::rebuildMenuAndCaches( );
  
  // create a sync job
  $params = array(
    'sequential' => 1,
    'name'          => 'SmartDebit Sync',
    'description'   => 'Sync contacts from smartdebit to civicrm.',
    'run_frequency' => 'Daily',
    'api_entity'    => 'Ukdirectdebit',
    'api_action'    => 'sync',
    'is_active'     => 0,
  );
  $result = civicrm_api3('job', 'create', $params);
  
  $parentId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Contributions', 'id', 'name');
  $weight   = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Import Contributions', 'weight', 'name');

  if ($parentId) {
    $smartdebitMenu = 
      array(
        array(
          'label' => ts('Import Smart Debit Contributions'),
          'name'  => 'Import Smart Debit Contributions',
          'url'   => 'civicrm/directdebit/syncsd&reset=1',
        ),
      );

    foreach ($smartdebitMenu as $key => $menuItems) {
      $menuItems['is_active'] = 1;
      $menuItems['parent_id'] = $parentId;
      $menuItems['weight']    = ++$weight;
      $menuItems['permission'] = 'administer CiviCRM';
      CRM_Core_BAO_Navigation::add($menuItems);
    }
    CRM_Core_BAO_Navigation::resetNavigation();
  }

}

function uk_direct_debit_civicrm_uninstall( ) {
  
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

function uk_direct_debit_civicrm_enable() {
/**
  $dirRoot =dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
  $xml_file = $dirRoot . 'sql' . DIRECTORY_SEPARATOR . 'xml' . DIRECTORY_SEPARATOR . 'vehicle.xml';
  require_once 'CRM/Utils/Migrate/Import.php';
  $import = new CRM_Utils_Migrate_Import();
  $import->run($xml_file);
 *
 */

  CRM_Core_Invoke::rebuildMenuAndCaches( );
}

function uk_direct_debit_civicrm_config( &$config ) {

    $template =& CRM_Core_Smarty::singleton( );

    $schoolRoot =
        dirname( __FILE__ ) . DIRECTORY_SEPARATOR ;

    $schoolDir = $schoolRoot . 'templates';

    if ( is_array( $template->template_dir ) ) {
        array_unshift( $template->template_dir, $schoolDir );
    } else {
        $template->template_dir = array( $schoolDir, $template->template_dir );
    }

    // also fix php include path
    $include_path = $schoolRoot . PATH_SEPARATOR . get_include_path( );

    set_include_path( $include_path );
    /**
    // assign the profile ids
    $gidStudent = CRM_Core_DAO::getFieldValue( 'CRM_Core_DAO_UFGroup', 'Student_Information', 'id', 'name' );
    $gidParent = CRM_Core_DAO::getFieldValue( 'CRM_Core_DAO_UFGroup', 'Parent_Information', 'id', 'name' );
    $template->assign( 'parentProfileID' , $gidParent  );
    $template->assign( 'studentProfileID', $gidStudent );
     *
     * @param type $page
     * @return type
     */

}

function uk_direct_debit_civicrm_xmlMenu( &$files ) {
  $files[] = dirname(__FILE__)."/xml/Menu/DirectDebit.xml";
}

function uk_direct_debit_civicrm_buildForm( $formName, &$form ) {
//print($formName);
//print_r($form);
//die;

    require_once 'CRM/Core/Payment.php';
    require_once 'UK_Direct_Debit/Form/Main.php';

    if ( isset($form->_paymentProcessor['payment_type']) && CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT ) {

        if (!empty($form->_submitValues['ddi_reference']))
           $ddi_reference = $form->_submitValues['ddi_reference'];

        if (isset($form->_params)) {
            if (!empty($form->_params['ddi_reference']))
                $ddi_reference = $form->_params['ddi_reference'];
        }

        if ( $form->_paymentProcessor['payment_type'] && !empty($ddi_reference) ){
            /*** select dwtails for ddi
             * then setup local array with all details
             * then smarty assign the array
             */
            $query  = " SELECT * ";
            $query .= " FROM civicrm_direct_debit ";
            $query .= " WHERE ddi_reference = %1 ";
            $query .= " ORDER BY id DESC ";
            $query .= " LIMIT 1 ";

            $params = array( 1 => array( (string)$ddi_reference, 'String' ) );
            $dao = CRM_Core_DAO::executeQuery( $query, $params );

            if ($dao->fetch()) {

                $uk_direct_debit['formatted_preferred_collection_day'] = UK_Direct_Debit_Form_Main::formatPrefferedCollectionDay($dao->preferred_collection_day);
                $uk_direct_debit['company_name']             = UK_Direct_Debit_Form_Main::getCompanyName();
                $uk_direct_debit['bank_name']                = $dao->bank_name;
                $uk_direct_debit['branch']                   = $dao->branch;
                $uk_direct_debit['address1']                 = $dao->address1;
                $uk_direct_debit['address2']                 = $dao->address2;
                $uk_direct_debit['address3']                 = $dao->address3;
                $uk_direct_debit['address4']                 = $dao->address4;
                $uk_direct_debit['town']                     = $dao->town;
                $uk_direct_debit['county']                   = $dao->county;
                $uk_direct_debit['postcode']                 = $dao->postcode;
                $uk_direct_debit['first_collection_date']    = $dao->first_collection_date;
                $uk_direct_debit['preferred_collection_day'] = $dao->preferred_collection_day;
                $uk_direct_debit['confirmation_method']      = $dao->confirmation_method;
                $uk_direct_debit['formatted_preferred_collection_day'] = UK_Direct_Debit_Form_Main::formatPrefferedCollectionDay($dao->preferred_collection_day);
                $uk_direct_debit['company_name']             = UK_Direct_Debit_Form_Main::getCompanyName();

                /** TO DO Unset the unused fields **/
            }

//            $form->assign( 'service_user_number', UK_Direct_Debit_Form_Main::getSUNParts());

//            $form->assign( 'company_address', UK_Direct_Debit_Form_Main::getCompanyAddress());

        }
        else {
            $uk_direct_debit['formatted_preferred_collection_day'] = '';
            $uk_direct_debit['company_name']             = UK_Direct_Debit_Form_Main::getCompanyName();
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
            $uk_direct_debit['formatted_preferred_collection_day'] = '';;
            $uk_direct_debit['company_name']             = UK_Direct_Debit_Form_Main::getCompanyName();
        }

        $form->assign( 'direct_debit_details', $uk_direct_debit );
        $form->assign( 'service_user_number', UK_Direct_Debit_Form_Main::getSUNParts());
        $form->assign( 'company_address', UK_Direct_Debit_Form_Main::getCompanyAddress());
        $form->assign( 'directDebitDate', date('Ymd'));

    }


    if ($formName == 'CRM_DirectDebit_Form_DirectDebit') {
        if ($form->_eventId == EVENT_ID) {
          $form->addRule('price_3', ts('This field is required.'), 'required');
        }
    }
    
    if ($formName == 'CRM_Contribute_Form_UpdateSubscription') {
      $paymentProcessor = $form->_paymentProcessor;
      if($paymentProcessor['payment_processor_type'] == 'Smart Debit') {
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
        $form->add('hidden', 'payment_processor_type', 'Smart Debit');
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
    }
  }

}

/*************************************************************
  Send a post request with cURL
    $url = URL to send request to
    $data = POST data to send (in URL encoded Key=value pairs)
*************************************************************/
function call_CiviCRM_IPN($url){

  CRM_Core_Error::debug_log_message('call_CiviCRM_IPN url='.$url);

  // Set a one-minute timeout for this script
  set_time_limit(160);

  // Initialise output variable
  $output = array();

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

  $header = curl_getinfo( $session );

  CRM_Core_Error::debug_var( 'result : ', $output);

} // END function requestPost()

/*
 * This hook is used to perform the IPN code on the direct debit contribution
 * This should result in the membership showing as active, so this only really applies to membership base contribution forms
 *
 */
function uk_direct_debit_civicrm_postProcess( $formName, &$form ) {
  // Check the form being submitted is a contribution form
  if ( is_a( $form, 'CRM_Contribute_Form_Contribution_Confirm' ) ) {
    CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_postProcess #1');
    CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_postProcess form='.print_r($form, TRUE));

    CRM_Core_Error::debug_log_message('CRM_Contribute_Form_Contribution_Confirm #1');

    require_once 'UK_Direct_Debit/Form/Main.php';

    CRM_Core_Error:: debug_log_message( 'Firing IPN code');

    $paymentType = urlencode( $form->_paymentProcessor['payment_type'] );
    $isRecur     = urlencode( $form->_values['is_recur'] );

    // Now only do this is the payment processor type is Direct Debit as other payment processors may do this another way
    if ( $paymentType == 2 ) {
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

      $start_date     = urlencode( $form->_params['start_date'] );

      if ( $isRecur == 1 ) {
        CRM_Core_Error::debug_log_message('uk_direct_debit_civicrm_postProcess #2');

        $paymentProcessorType = urlencode( $form->_paymentProcessor['payment_processor_type'] );
        $membershipID         = urlencode( $form->_values['membership_id'] );
        $contactID            = urlencode( $form->getVar( '_contactID' ) );
        $invoiceID            = urlencode( $form->_params['invoiceID'] );
        $amount               = urlencode( $form->_params['amount'] );
        $trxn_id              = urlencode( $form->_params['trxn_id'] );
        $collection_day       = urlencode( $form->_params['preferred_collection_day'] );

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

  //dpm($membershipID, "Before renew_membership - membershipID");
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

/**
 * Renew Membership by one period if the membership expires
 * before the contribution collection date starts
 * @author shobbs
 */
function renew_membership_by_one_period($membershipID) {

    $autoRenewMembership = UK_Direct_Debit_Form_Main::getAutoRenewMembership();

    if ($autoRenewMembership = 'Y') {

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
//    if (!is_null(array_search('Renew Memberships', $tasks)))
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
  if(($objectName == 'ContributionRecur' && $op =='edit') && ($params['payment_processor_type'] == 'Smart Debit') ) {
    $params['start_date'] = CRM_Utils_Date::processDate($params['start_date']);
    $params['end_date']   = CRM_Utils_Date::processDate($params['end_date']);
    $frequencyUnit        = $params['frequency_unit'];
    $frequencyUnits       = array('D' =>'day','W'=> 'week','M'=> 'month', 'Y' => 'year');
    $params['frequency_unit'] = CRM_Utils_Array::value($frequencyUnit, $frequencyUnits);
  }
}

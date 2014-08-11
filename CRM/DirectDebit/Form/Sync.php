<?php

class CRM_DirectDebit_Form_Sync extends CRM_Core_Form {
  const QUEUE_NAME = 'sm-pull';
  const END_URL    = 'civicrm/directdebit/sync';
  const END_PARAMS = 'state=done';
  const BATCH_COUNT = 10;
  const SD_SETTING_GROUP = 'SmartDebit Preferences';
  
  function preProcess() {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats  = array();
      $stats = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
      $total  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'total');
      $stats['Total'] = $total;
      $this->assign('stats', $stats);
    }
  }
  
  public function buildQuickForm() {
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Import'),
      ),
    );
    // Add the Buttons.
    $this->addButtons($buttons);
  }
  
  public function postProcess() {
    $runner = self::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure smart debit settings are correctly configured in the payment processor setting page'));
    }
  }
  
  static function getRunner() {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));    
     
    $transactionIdList = "'dummyId'";  //Initialised so have at least one entry in list
    $smartDebitArray = self::getSmartDebitPayments();
    $count  = count($smartDebitArray);
    
    CRM_Core_BAO_Setting::setItem($count,
        CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP,
        'total'
      );
    
    // Set the Number of Rounds
    $rounds = ceil($count/self::BATCH_COUNT);
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start   = $i * self::BATCH_COUNT;
      $contactsarray  = array_slice($smartDebitArray, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      $task    = new CRM_Queue_Task(
        array('CRM_DirectDebit_Form_Sync', 'syncContacts'),
        array(array($contactsarray)),
        "Pulling smart debit - Contacts {$counter} of {$count}"
      );

      // Add the Task to the Queu
      $queue->createItem($task);
      $i++;
    }
    
    if (!empty($smartDebitArray)) {
      // Setup the Runner
      $runner = new CRM_Queue_Runner(array(
        'title' => ts('Import From Smart Debit'),
        'queue' => $queue,
        'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
        'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
      ));
      
      $query = "UPDATE civicrm_setting SET value = NULL WHERE name = 'sd_stats'"; 
      CRM_Core_DAO::executeQuery($query);
      return $runner;
    }
    return FALSE;
  }
  
  static function syncContacts(CRM_Queue_TaskContext $ctx, $contactsarray) {
    
    $contactsarray  = array_shift($contactsarray);
    foreach ($contactsarray as $key => $smartDebitRecord) {
      $query = "
        SELECT cc.`id`, cc.contribution_recur_id, cc.invoice_id, cc.`contact_id`, cc.financial_type_id, max(receive_date) as receive_date
        FROM `civicrm_contribution` cc
        INNER JOIN civicrm_contribution_recur cr ON cr.id = cc.`contribution_recur_id`
        WHERE cr.processor_id = %1
        AND cc.`is_test` = 0";
      
      $params = array( 1 => array($smartDebitRecord['reference_number'], 'String' ) );
      $dao = CRM_Core_DAO::executeQuery( $query, $params);
      
      if ($dao->fetch()) {
        $contributionID         = $dao->id;
        $receiveDate            = $dao->receive_date;
        $financial_type_id      = $dao->financial_type_id;
        $contactID              = $dao->contact_id;
        $recurID                = $dao->contribution_recur_id;
        $invoice_id             = $dao->invoice_id;
      }
      
      if($recurID && $contributionID) {
        //set transaction type
       /* Smart Debit statuses are as follows
                    0 Draft
                    1 New
                    10 Live
                    11 Cancelled
                    12 Rejected
                   * 
                   */
        $now              = date('Ymd');
        $today_date       = strtotime($now);
        $today_date       = date("Y-m-d", $today_date);
        $frequency_type   = $smartDebitRecord['frequency_type'];
        $txnType          = $smartDebitRecord['current_state'];
        $amount           = $smartDebitRecord['regular_amount'];
        $amount           = str_replace("£","",$amount);
        $start_date       = $smartDebitRecord['start_date'];
        
        switch ($txnType) {
          case '1':
            //Do nothing as the first contribution is already there in civicrm
            $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
            CRM_Core_BAO_Setting::setItem(array('Added' => $setting['Added'], 'New' => (1 + $setting['New']), 'Canceled' => $setting['Canceled'], 'Failed' => $setting['Failed']),
              CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
            );
            break;
            
          case '10':
            
            $receiveDate  = strtotime($receiveDate);
            $receiveDate  = date("Y-m-d", $receiveDate);
            
            if( ($today_date > $start_date) && ($frequency_type == 'M')){
              $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%m');
            }
            
            if( ($today_date > $start_date) && ($frequency_type == 'Y')){
              $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%y');
            }
            
            if( ($today_date > $start_date) && ($frequency_type == 'Q')){
              $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%m');
              $result = (int)($result/3);
            }
            
            if( ($today_date > $start_date) && ($frequency_type == 'W')){
              $result = CRM_DirectDebit_Form_Sync::dateDifference($today_date, $receiveDate,'%m');
              $result = $result * 4;
            }
            
            if($result>0) {
              $i = 1;
              while ($i <= $result) {
                $trxn_id = $smartDebitRecord['reference_number'].'-'.$today_date.'-'.$i;
                $query = "processor_name=Smart+Debit&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&mc_gross=".$amount."&invoice=".$invoice_id."&payment_status=Completed&txn_type=recurring_payment&contributionRecurID=".$recurID."&txn_id=".$trxn_id."&financial_type_id=".$financial_type_id;
                $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
                call_CiviCRM_IPN($url);
                $i++;
              }
            }
            
            $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
            CRM_Core_BAO_Setting::setItem(array('Added' => ($result + $setting['Added']), 'New' => $setting['New'], 'Canceled' => $setting['Canceled'], 'Failed' => $setting['Failed']),
              CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
            );
            break;
            
          case '11':
            $trxn_id = $smartDebitRecord['reference_number'].'-'.$today_date;
            $query = "processor_name=Smart+Debit&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&mc_gross=".$amount."&invoice=".$invoice_id."&payment_status=Completed&txn_type=subscr_cancel&contributionRecurID=".$recurID."&txn_id=".$trxn_id."&financial_type_id=".$financial_type_id;
            $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
            call_CiviCRM_IPN($url);
            
            $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
            CRM_Core_BAO_Setting::setItem(array('Added' => $setting['Added'], 'New' => $setting['New'], 'Canceled' => (1 + $setting['Canceled']), 'Failed' => $setting['Failed']),
              CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
            );
            break;
          
          case '12':
            $trxn_id = $smartDebitRecord['reference_number'].'-'.$today_date;
            $query = "processor_name=Smart+Debit&module=contribute&contactID=".$contactID."&contributionID=".$contributionID."&mc_gross=".$amount."&invoice=".$invoice_id."&payment_status=Completed&txn_type=subscr_failed&contributionRecurID=".$recurID."&txn_id=".$trxn_id."&financial_type_id=".$financial_type_id;
            $url = CRM_Utils_System::url('civicrm/payment/ipn', $query,  TRUE, NULL, FALSE, TRUE);
            call_CiviCRM_IPN($url);
            
            $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
            CRM_Core_BAO_Setting::setItem(array('Added' => $setting['Added'], 'New' => $setting['New'], 'Canceled' => $setting['Canceled'], 'Failed' => (1 + $setting['Failed'])),
              CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
            );
            break;

        }
      }
    }
    return CRM_Queue_Task::TASK_SUCCESS;
  }
  
  
  static function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' ) {
      $datetime1 = date_create($date_1);
      $datetime2 = date_create($date_2);

      $interval = date_diff($datetime1, $datetime2);

      return $interval->format($differenceFormat);
    
   }
   
  static function getSmartDebitPayments($referenceNumber = NULL) {
    $paymentProcessorType = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
    $paymentProcessorTypeId = CRM_Utils_Array::key('Smart Debit', $paymentProcessorType);
  
      $sql  = " SELECT user_name ";
      $sql .= " ,      password ";
      $sql .= " ,      signature "; 
      $sql .= " FROM civicrm_payment_processor "; 
      $sql .= " WHERE payment_processor_type_id = %1 "; 
      $sql .= " AND is_test= %2 ";

      $params = array( 1 => array( $paymentProcessorTypeId, 'Integer' )
                     , 2 => array( '0', 'Int' )    
                     );

      $dao = CRM_Core_DAO::executeQuery( $sql, $params);

      if ($dao->fetch()) {

          $username = $dao->user_name;
          $password = $dao->password;
          $pslid    = $dao->signature;

      }
    
    // Send payment POST to the target URL
    $url = "https://secure.ddprocessing.co.uk/api/data/dump?query[service_user][pslid]=$pslid&query[report_format]=XML";
    
    // Restrict to a single payer if we have a reference
    if ($referenceNumber) {
      $url .= "&query[reference_number]=$referenceNumber";
    }
		
    $response = self::requestPost( $url, $username, $password );    

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
        case 'OK':
        
            $smartDebitArray = array();
						
					  // Cater for a single response
					  if (isset($response['Data']['PayerDetails']['@attributes'])) {
							$smartDebitArray[] = $response['Data']['PayerDetails']['@attributes'];
						} else {
							foreach ($response['Data']['PayerDetails'] as $key => $value) {
							  $smartDebitArray[] = $value['@attributes'];
							}
						}             
            return $smartDebitArray;
            
        default:
            return false;
    }
   
  }
  
  static function requestPost($url, $username, $password){
        // Set a one-minute timeout for this script
        set_time_limit(160);

        // Initialise output variable
        $output = array();

        $options = array(
                        CURLOPT_RETURNTRANSFER => true, // return web page
                        CURLOPT_HEADER => false, // don't return headers
                        CURLOPT_POST => true,
                        CURLOPT_USERPWD => $username . ':' . $password,
                        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                        CURLOPT_HTTPHEADER => array("Accept: application/xml"),
                        CURLOPT_USERAGENT => "XYZ Co's PHP iDD Client", // Let Webservice see who we are
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_SSL_VERIFYPEER => false,
                      );

        $session = curl_init( $url );

        curl_setopt_array( $session, $options );

        // Tell curl that this is the body of the POST
        curl_setopt ($session, CURLOPT_POSTFIELDS, null );

        // $output contains the output string
        $output = curl_exec($session);
        $header = curl_getinfo( $session );

        //Store the raw response for later as it's useful to see for integration and understanding 
        $_SESSION["rawresponse"] = $output;

        if(curl_errno($session)) {
          $resultsArray["Status"] = "FAIL";  
          $resultsArray['StatusDetail'] = curl_error($session);
        }
        else {
          // Results are XML so turn this into a PHP Array
          $resultsArray = json_decode(json_encode((array) simplexml_load_string($output)),1);  

          // Determine if the call failed or not
          switch ($header["http_code"]) {
            case 200:
              $resultsArray["Status"] = "OK";
              break;
            default:
              $resultsArray["Status"] = "INVALID";
          }
        }

        // Return the output
        return $resultsArray;

    } // END function requestPost()
}
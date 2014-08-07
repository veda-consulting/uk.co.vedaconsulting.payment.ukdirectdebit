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
      $stats['Blocked'] = $total - array_sum($stats);
      
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
    $first = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP,
        'first', NULL, TRUE
    );
    // Setup a Task in the Queue
    $i = 0;
    while ($i < $rounds) {
      $start   = $i * self::BATCH_COUNT;
      $contactsarray  = array_slice($smartDebitArray, $start, self::BATCH_COUNT, TRUE);
      $counter = ($rounds > 1) ? ($start + self::BATCH_COUNT) : $count;
      $task    = new CRM_Queue_Task(
        array('CRM_DirectDebit_Form_Sync', 'syncContacts'),
        array(array($contactsarray), $first),
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
      CRM_Core_BAO_Setting::setItem(FALSE,
        CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP,
        'first'
      );
      
      $query = "UPDATE civicrm_setting SET value = NULL WHERE name = 'sd_stats'"; 
      CRM_Core_DAO::executeQuery($query);
      return $runner;
    }
    return FALSE;
  }
  
  static function syncContacts(CRM_Queue_TaskContext $ctx, $contactsarray, $first) {
    
    $contactsarray  = array_shift($contactsarray);
    foreach ($contactsarray as $key => $smartDebitRecord) {
      $now = date('YmdHis');
      $query = "
        SELECT cc.`id`,cc.contribution_recur_id, cr.contribution_status_id, cc.`contact_id`, cc.financial_type_id, cc.contribution_page_id, cc.currency, cc.payment_instrument_id, max(receive_date) as receive_date
        FROM `civicrm_contribution` cc
        LEFT JOIN civicrm_contribution_recur cr ON cr.id = cc.`contribution_recur_id`
        WHERE cr.processor_id = %1
        AND cc.`is_test` = 0";
      
      $params = array( 1 => array($smartDebitRecord['reference_number'], 'String' ) );
      $dao = CRM_Core_DAO::executeQuery( $query, $params);
      
      if ($dao->fetch()) {
        $contributionID         = $dao->id;
        $receiveDate            = $dao->receive_date;
        $financial_type_id      = $dao->financial_type_id;
        $contribution_page_id   = $dao->contribution_page_id;
        $currency               = $dao->currency;
        $contact_id             = $dao->contact_id;
        $payment_instrument_id  = $dao->payment_instrument_id;
        $recurID                = $dao->contribution_recur_id;
        $contribution_status_id = $dao->contribution_status_id;
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
        $txnType = $smartDebitRecord['current_state'];
        $newContribution = FALSE;
        switch ($txnType) {
          case '1':
            $create_date = $now;
            //$statusID = $contributionrecur->contribution_status_id;
            if ($contribution_status_id != 5) {
              $contribution_status_id = 2;
            }
            break;
            
          case '10':
            $frequency_type   = $smartDebitRecord['frequency_type'];
            $today_date       = strtotime($now);
            $today_date       = date("Y-m-d", $today_date);
            $start_date       = $smartDebitRecord['start_date'];

            $sDate  = explode('-', $start_date);
            $tDate  = explode('-', $today_date);
            
            $sYear  = $sDate[0];
            $sMonth = $sDate[1];
            $sDay   = $sDate[2];

            $tYear  = $tDate[0];
            $tMonth = $tDate[1];
            $tDay   = $tDate[2];
            
            $receiveDate  = strtotime($receiveDate);
            $receiveDate  = date("Y-m-d", $receiveDate);
            $rDate  = explode('-', $receiveDate);

            $rYear  = $rDate[0];
            $rMonth = $rDate[1];
            $rDay   = $rDate[2];
            
            if( ($tDate > $sDate) && ($frequency_type == 'M') && ($rDay<$tDay) && ($rMonth == $tMonth)){
              $newContribution  = TRUE;
            }
            
            if( ($tDate > $sDate) && ($frequency_type == 'M') && ($rDay>$tDay) && ($rMonth == $tMonth -1)){
              $newContribution  = TRUE;
              $tMonth           = $rMonth;
            }
            
            if(($tDate > $sDate) && ($frequency_type == 'M') && ($rDay<$tDay) && ($rMonth == ($tMonth -1))){
              $newContribution  = TRUE;
            }
            
            if( ($tDate > $sDate) && ($frequency_type == 'Y') && ($rDay<$tDay) && ($rMonth == $tMonth) && ($rYear == $tYear -1) ){
              $newContribution  = TRUE;
            }
            
            if( ($tDate > $sDate) && ($frequency_type == 'Q') && ($rDay<$tDay) && ($rMonth == ($tMonth-3)) ){
              $newContribution  = TRUE;
            }
            $receive_date       = $now;
            break;
            
          case '12':
            if ($contribution_status_id != 3) {
              $contribution_status_id = 1;
            }
            $end_date = $now;
            break;

          case '11':
            $contribution_status_id = 3;
            $cancel_date = $now;
            break;

        }

        $recur_params = array(
          'id'                      =>  $recurID,
          'create_date'             =>  $create_date,
          'contribution_status_id'  =>  $contribution_status_id,
          'end_date'                =>  $end_date,
          'cancel_date'             =>  $cancel_date,
        );
        $result = CRM_Contribute_BAO_ContributionRecur::add($recur_params);

        if($first) {
          if(($end_date || $cancel_date) == $now ) {
            CRM_Contribute_BAO_ContributionRecur::deleteRecurContribution($recurID);
            CRM_Contribute_BAO_Contribution::deleteContribution($contributionID);
          
            $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
            CRM_Core_BAO_Setting::setItem(array('Added' => $setting['Added'], 'Updated' => $setting['Updated'], 'Canceled' => (1 + $setting['Canceled'])),
              CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
            );
          } 
          else if(($receive_date || $create_date) == $now) {
            
            $trxn_id = $smartDebitRecord['reference_number'].'-'.$tMonth.'-'.$tYear;
          
            if($frequency_type == 'Y') {
              $trxn_id = $smartDebitRecord['reference_number'].'-'.$tYear;
            }
            $params = array(
              'version'     => 3,
              'sequential'  => 1,
              'id'          => $contributionID,
              'trxn_id'     => $trxn_id,
            );
            $result = civicrm_api('Contribution', 'create', $params);
        
            $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
            CRM_Core_BAO_Setting::setItem(array('Added' => $setting['Added'], 'Updated' => (1 + $setting['Updated']), 'Canceled' => $setting['Canceled']),
              CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
            );
          }
        }

        if(!$first && ($end_date || $cancel_date) == $now) {
          $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
          CRM_Core_BAO_Setting::setItem(array('Added' => $setting['Added'], 'Updated' => $setting['Updated'], 'Canceled' =>(1 + $setting['Canceled'])),
            CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
          );
         }


        if (!$first  && ($newContribution == TRUE)) {
          //check if this contribution transaction is already processed
          //if not create a contribution and then get it processed
          $contribution = new CRM_Contribute_BAO_Contribution();
          //$contribution->trxn_id = $trxn_id;
          if($frequency_type == 'M') {
            $contribution->trxn_id = $smartDebitRecord['reference_number'].'-'.$tMonth.'-'.$tYear;
          }
          if($frequency_type == 'Y') {
            $contribution->trxn_id = $smartDebitRecord['reference_number'].'-'.$tYear;
          }
          
          $amount = $smartDebitRecord['regular_amount'];
          
          if ($contribution->trxn_id && $contribution->find()) {
            CRM_Core_Error::debug_log_message("returning since contribution has already been handled");
            continue;
          }
          $total_amount = str_replace("£","",$amount);

          $contribution->contact_id = $contact_id;
          $contribution->financial_type_id  = $financial_type_id;
          $contribution->contribution_page_id = $contribution_page_id;
          $contribution->contribution_recur_id = $recurID;
          $contribution->receive_date = $now;
          $contribution->currency = $currency;
          $contribution->payment_instrument_id = $payment_instrument_id;
          $contribution->total_amount = $total_amount;
          $contribution->save();
          
          $setting  = CRM_Core_BAO_Setting::getItem(CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats');
          CRM_Core_BAO_Setting::setItem(array('Added' => (1 + $setting['Added']), 'Updated' => $setting['Updated'], 'Canceled' => $setting['Canceled']),
          CRM_DirectDebit_Form_Sync::SD_SETTING_GROUP, 'sd_stats'
          );
         
        }
      }
    }
    return CRM_Queue_Task::TASK_SUCCESS;
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
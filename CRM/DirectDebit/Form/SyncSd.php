<?php
  require_once 'CRM/Core/Form.php';
  require_once 'CRM/Core/Session.php';
  require_once 'CRM/Core/PseudoConstant.php';
    
class CRM_DirectDebit_Form_SyncSd extends CRM_Core_Form {
  
  function preProcess() {
    parent::preProcess();
  }
  
  public function buildQuickForm() {
    $auddisDetails    = array();
    $auddisDates      = array();
    $auddisArray      = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis();
    
    if($auddisArray) {
      foreach ($auddisArray as $key => $auddis) {
          $auddisDetails['auddis_id']              = $auddis['auddis_id'];
          $auddisDetails['report_generation_date'] = substr($auddis['report_generation_date'], 0, 10);
          $auddisDates[]                           = substr($auddis['report_generation_date'], 0, 10);
          $auddisDetails['uri']                    = $auddis['@attributes']['uri'];

      }
    }
     
    $auddisDates = array_combine($auddisDates, $auddisDates);
    $this->addElement('select', 'auddis_date', ts('Auddis Date'), array('' => ts('- select -')) + $auddisDates);
    $this->addDate('sync_date', ts('Sync Date'), FALSE, array('formatType' => 'custom'));
    $this->addButtons(array(
              array(
                'type' => 'next',
                'name' => ts('Continue'),
                'isDefault' => TRUE,
                ),
              array(
                'type' => 'back',
                'name' => ts('Cancel'),
              )
            )
    );
    parent::buildQuickForm();
  }
  
  function postProcess() {
    $params = $this->controller->exportValues();
    $auddisDate = $params['auddis_date'];
    $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $auddisDate, 'details', 'subject');
    if($details) {
      CRM_Core_Session::setStatus(ts($details), Error, 'error');
      CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/syncsd', '&reset=1'));
    }
   
    CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/auddis', 'date=' . $auddisDate. '&reset=1'));
      
    parent::postProcess();
  }
  
  static function getSmartDebitAuddis($uri = NULL) {
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

    if($uri) {
      $urlAuddis          = $uri."?query[service_user][pslid]=$pslid";
      $responseAuddis     = self::requestPost( $urlAuddis, $username, $password );   
      $scrambled          = str_replace(" ","+",$responseAuddis['file']);
      $outputafterencode  = base64_decode($scrambled);
      $auddisArray        = json_decode(json_encode((array) simplexml_load_string($outputafterencode)),1);

      $result = array();

      if($auddisArray['Data']['MessagingAdvices']['MessagingAdvice']['@attributes']) {
        $result[0] = $auddisArray['Data']['MessagingAdvices']['MessagingAdvice']['@attributes'];
      }
      else {
        foreach ($auddisArray['Data']['MessagingAdvices']['MessagingAdvice'] as $key => $value) {
          $result[$key] = $value['@attributes'];

        }
      }

      return $result;
    }

    else {

  // Send payment POST to the target URL
      $urlAuddis = "https://secure.ddprocessing.co.uk/api/auddis/list?query[service_user][pslid]=$pslid";

      $responseAuddis = self::requestPost( $urlAuddis, $username, $password );    

      // Take action based upon the response status
      switch ( strtoupper( $responseAuddis["Status"] ) ) {
          case 'OK':

              $auddisArray = array();

              // Cater for a single response
              if (isset($responseAuddis['auddis'])) {
                $auddisArray = $responseAuddis['auddis'];
              }           
              return $auddisArray;

          default:
              return false;
      }
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
    
}

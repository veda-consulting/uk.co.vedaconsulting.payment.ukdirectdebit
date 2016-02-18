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

    // Get all auddis files from the API
    $auddisArray      = CRM_DirectDebit_Form_SyncSd::getSmartDebitAuddis();

    // Get the auddis Dates from the Auddis Files
    if($auddisArray) {
      if (isset($auddisArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($auddisArray as $key => $auddis) {
          $auddisDetails['auddis_id']              = $auddis['auddis_id'];
          $auddisDetails['report_generation_date'] = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
          $auddisDates[]                           = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
          $auddisDetails['uri']                    = $auddis['@attributes']['uri'];
        }
      } else {
        // Only one result returned
        $auddisDetails['auddis_id']              = $auddisArray['auddis_id'];
        $auddisDetails['report_generation_date'] = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
        $auddisDates[]                           = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
        $auddisDetails['uri']                    = $auddisArray['@attributes']['uri'];
      }
    }

    // Get the already processed Auddis Dates
    $processedAuddisDates = array();
    if($auddisDates) {
      foreach ($auddisDates as $auddisDate) {
        $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $auddisDate, 'details', 'subject');
        if($details) {
          $processedAuddisDates[] = $auddisDate;
        }
      }
    }

    // Show only the valid auddis dates in the multi select box
    $auddisDates = array_diff($auddisDates, $processedAuddisDates);
    // Check if array to not empty, to avoid warning
    if (!empty($auddisDates)) {
      $auddisDates = array_combine($auddisDates, $auddisDates);
    }

    if (count($auddisDates) <= 10) {
      // setting minimum height to 2 since widget looks strange when size (height) is 1
      $groupSize = max(count($auddisDates), 2);
    }
    else {
      $groupSize = 10;
    }

    $inG = &$this->addElement('advmultiselect', 'includeAuddisDate',
      ts('Include Auddis Date(s)') . ' ',
      $auddisDates,
      array(
        'size' => $groupSize,
        'style' => 'width:auto; min-width:240px;',
        'class' => 'advmultiselect',
      )
    );

    $this->assign('groupCount', count($auddisDates));

    $auddisDatesArray = array('' => ts('- select -'));
    if (!empty($auddisDates)) {
      $auddisDatesArray = $auddisDatesArray + $auddisDates;
    }
    $this->addElement('select', 'auddis_date', ts('Auddis Date'), $auddisDatesArray);
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
    $auddisDates = $params['includeAuddisDate'];

    // Make the query string to send in the url for the next page
    $queryDates = '';
    foreach ($auddisDates as $value) {
      $queryDates .= "auddisDates[]=".$value."&";
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/auddis', ''.$queryDates. '&reset=1'));

    parent::postProcess();
  }

  static function getSmartDebitAuddis($uri = NULL) {
    $session = CRM_Core_Session::singleton();
    $dateOfCollection = $session->get('collection_date');
    $userDetails = CRM_DirectDebit_Form_DataSource::getSmartDebitUserDetails();
    $username    = CRM_Utils_Array::value('username', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('pslid', $userDetails);
  

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
      $previousDateBackMonth = date('Y-m-d', strtotime($dateOfCollection.'-1 month'));
      $urlAuddis = "https://secure.ddprocessing.co.uk/api/auddis/list?query[service_user][pslid]=$pslid&query[from_date]=$previousDateBackMonth&query[till_date]=$dateOfCollection";

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
      
    $userDetails = CRM_DirectDebit_Form_DataSource::getSmartDebitUserDetails();
    $username    = CRM_Utils_Array::value('username', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('pslid', $userDetails);

    // Send payment POST to the target URL
    $url = "https://secure.ddprocessing.co.uk/api/data/dump?query[service_user][pslid]=$pslid&query[report_format]=XML";
    // Restrict to a single payer if we have a reference
    if ($referenceNumber) {
      $url .= "&query[reference_number]=".rawurlencode($referenceNumber);
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

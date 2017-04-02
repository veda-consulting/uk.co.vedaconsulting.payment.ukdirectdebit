<?php

class CRM_DirectDebit_Auddis
{
  /*
   * Notification of failed debits and cancelled or amended DDIs are made available via Automated Direct Debit
   * Instruction Service (AUDDIS), Automated Return of Unpaid Direct Debit (ARUDD) files and Automated Direct Debit
   * Amendment and Cancellation (ADDACS) files. Notification of any claims relating to disputed Debits are made via
   * Direct Debit Indemnity Claim Advice (DDICA) reports.
   */
  static function getSmartDebitAuddis($uri = NULL)
  {
    $session = CRM_Core_Session::singleton();
    $dateOfCollection = $session->get('collection_date');
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    if ($uri) {
      $urlAuddis = $uri . "?query[service_user][pslid]=$pslid";
      $responseAuddis = CRM_DirectDebit_Base::requestPost($urlAuddis, '', $username, $password, '');
      $scrambled = str_replace(" ", "+", $responseAuddis['file']);
      $outputafterencode = base64_decode($scrambled);
      $auddisArray = json_decode(json_encode((array)simplexml_load_string($outputafterencode)), 1);
      $result = array();

      if ($auddisArray['Data']['MessagingAdvices']['MessagingAdvice']['@attributes']) {
        $result[0] = $auddisArray['Data']['MessagingAdvices']['MessagingAdvice']['@attributes'];
      } else {
        foreach ($auddisArray['Data']['MessagingAdvices']['MessagingAdvice'] as $key => $value) {
          $result[$key] = $value['@attributes'];
        }
      }
      return $result;
    } else {
      // Send payment POST to the target URL
      $previousDateBackMonth = date('Y-m-d', strtotime($dateOfCollection . '-1 month'));
      $urlAuddis = CRM_DirectDebit_Base::getApiUrl('/api/auddis/list',
        "query[service_user][pslid]=$pslid&query[from_date]=$previousDateBackMonth&query[till_date]=$dateOfCollection");
      $responseAuddis = CRM_DirectDebit_Base::requestPost($urlAuddis, '', $username, $password, '');
      // Take action based upon the response status
      switch (strtoupper($responseAuddis['Status'])) {
        case 'OK':
          return $responseAuddis;
        default:
          return false;
      }
    }
  }

  static function getSmartDebitArudd($uri = NULL)
  {
    $session = CRM_Core_Session::singleton();
    $dateOfCollection = $session->get('collection_date');
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    if ($uri) {
      $urlArudd = $uri . "?query[service_user][pslid]=$pslid";
      $responseArudd = CRM_DirectDebit_Base::requestPost($urlArudd, '', $username, $password, '');
      $scrambled = str_replace(" ", "+", $responseArudd['file']);
      $outputafterencode = base64_decode($scrambled);
      $aruddArray = json_decode(json_encode((array)simplexml_load_string($outputafterencode)), 1);
      $result = array();

      if ($aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem']['@attributes']) {
        $result[0] = $aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem']['@attributes'];
      } else {
        foreach ($aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem'] as $key => $value) {
          $result[$key] = $value['@attributes'];
        }
      }
      return $result;
    } else {
      $previousDateBackMonth = date('Y-m-d', strtotime($dateOfCollection . '-1 month'));

// Send payment POST to the target URL
      $urlArudd = CRM_DirectDebit_Base::getApiUrl('/api/arudd/list', "query[service_user][pslid]=$pslid&query[from_date]=$previousDateBackMonth&query[till_date]=$dateOfCollection");
      $responseArudd = CRM_DirectDebit_Base::requestPost($urlArudd, '', $username, $password, '');

// Take action based upon the response status
      switch (strtoupper($responseArudd["Status"])) {
        case 'OK':
          $aruddArray = array();
// Cater for a single response
          if (isset($responseArudd['arudd'])) {
            $aruddArray = $responseArudd['arudd'];
          }
          return $aruddArray;
        default:
          return false;
      }
    }
  }

  static function getRightAuddisFile($auddisArray = array(), $auddisDate = NULL)
  {
    $auddisDetails = array();
    if ($auddisArray && $auddisDate) {
      if (isset($auddisArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($auddisArray as $key => $auddis) {
          if (strtotime($auddisDate) == strtotime(substr($auddis['report_generation_date'], 0, 10))) {
            $auddisDetails['auddis_id'] = $auddis['auddis_id'];
            $auddisDetails['report_generation_date'] = substr($auddis['report_generation_date'], 0, 10);
            $auddisDetails['uri'] = $auddis['@attributes']['uri'];
            break;
          }
        }
      } else {
        // Only one result returned
        if (strtotime($auddisDate) == strtotime(substr($auddisArray['report_generation_date'], 0, 10))) {
          $auddisDetails['auddis_id'] = $auddisArray['auddis_id'];
          $auddisDetails['report_generation_date'] = substr($auddisArray['report_generation_date'], 0, 10);
          $auddisDetails['uri'] = $auddisArray['@attributes']['uri'];
        }
      }
    }
    return $auddisDetails;
  }

  static function getRightAruddFile($aruddArray = array(), $aruddDate = NULL)
  {
    $aruddDetails = array();
    if ($aruddArray && $aruddDate) {
      if (isset($aruddArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($aruddArray as $key => $arudd) {
          if (strtotime($aruddDate) == strtotime(substr($arudd['current_processing_date'], 0, 10))) {
            $aruddDetails['arudd_id'] = $arudd['arudd_id'];
            $aruddDetails['current_processing_date'] = substr($arudd['current_processing_date'], 0, 10);
            $aruddDetails['uri'] = $arudd['@attributes']['uri'];
            break;
          }
        }
      } else {
        // Only one result returned
        if (strtotime($aruddDate) == strtotime(substr($aruddArray['current_processing_date'], 0, 10))) {
          $aruddDetails['arudd_id'] = $aruddArray['arudd_id'];
          $aruddDetails['current_processing_date'] = substr($aruddArray['current_processing_date'], 0, 10);
          $aruddDetails['uri'] = $aruddArray['@attributes']['uri'];
        }
      }
    }
    return $aruddDetails;
  }

  /**
   * Retrieve Collection Report from Smart Debit
   * @param $dateOfCollection
   * @return array|bool
   */
  static function getSmartDebitCollectionReport( $dateOfCollection ) {
    if( empty($dateOfCollection)){
      CRM_Core_Session::setStatus(ts('Please select the collection date'), ts('Smart Debit'), 'error');
      return false;
    }

    $userDetails = self::getSmartDebitUserDetails();
    $username    = CRM_Utils_Array::value('user_name', $userDetails);
    $password    = CRM_Utils_Array::value('password', $userDetails);
    $pslid       = CRM_Utils_Array::value('signature', $userDetails);

    $collections = array();
    $url = CRM_DirectDebit_Base::getApiUrl('/api/get_collection_report', "query[service_user][pslid]=$pslid&query[debit_date]=$dateOfCollection");
    $response    = CRM_DirectDebit_Base::requestPost($url, '', $username, $password, '');

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
      case 'OK':
        if (!isset($response['Successes']) || !isset($response['Rejects'])) {
          $url = CRM_Utils_System::url('civicrm/directdebit/syncsd/import'); // DataSource Form
          CRM_Core_Session::setStatus($response['Summary'], ts('Sorry'), 'error');
          CRM_Utils_System::redirect($url);
          return FALSE;
        }
        // Cater for a single response
        if (isset($response['Successes']['Success']['@attributes'])) {
          $collections[] = $response['Successes']['Success']['@attributes'];
        } else {
          foreach ($response['Successes']['Success'] as $key => $value) {
            $collections[] = $value['@attributes'];
          }
        }
        return $collections;
      case 'INVALID':
        $url = CRM_Utils_System::url('civicrm/directdebit/syncsd/import'); // DataSource Form
        CRM_Core_Session::setStatus($response['error'], ts('UK Direct Debit'), 'error');
        CRM_Utils_System::redirect($url);
        return FALSE;
      default:
        return FALSE;
    }
  }

  /**
   * Get Smart Debit User Details
   * @return array|bool
   */
  static function getSmartDebitUserDetails(){
    $paymentProcessorType   = CRM_Core_PseudoConstant::paymentProcessorType(false, null, 'name');
    $paymentProcessorTypeId = CRM_Utils_Array::key('Smart_Debit', $paymentProcessorType);
    $domainID               = CRM_Core_Config::domainID();

    if(empty($paymentProcessorTypeId)) {
      CRM_Core_Session::setStatus(ts('Make sure Payment Processor Type (Smart Debit) is set in Payment Processor setting'), Error, 'error');
      return FALSE;
    }

    $sql  = " SELECT user_name ";
    $sql .= " ,      password ";
    $sql .= " ,      signature ";
    $sql .= " ,      billing_mode ";
    $sql .= " ,      payment_type ";
    $sql .= " ,      url_api ";
    $sql .= " ,      id ";
    $sql .= " FROM civicrm_payment_processor ";
    $sql .= " WHERE payment_processor_type_id = %1 ";
    $sql .= " AND is_test= %2 AND domain_id = %3";

    $params = array( 1 => array( $paymentProcessorTypeId, 'Integer' )
    , 2 => array( '0', 'Int' )
    , 3 => array( $domainID, 'Int' )
    );

    $dao    = CRM_Core_DAO::executeQuery( $sql, $params);
    $result = array();
    if ($dao->fetch()) {
      if(empty($dao->user_name) || empty($dao->password) || empty($dao->signature)) {
        CRM_Core_Session::setStatus(ts('Smart Debit API User Details Missing, Please check the Smart Debit Payment Processor is configured Properly'), Error, 'error');
        return FALSE;
      }
      $result   = array(
        'user_name'	  => $dao->user_name,
        'password'	  => $dao->password,
        'signature'	  => $dao->signature,
        'billing_mode'    => $dao->billing_mode,
        'payment_type'    => $dao->payment_type,
        'url_api'	  => $dao->url_api,
        'id'		  => $dao->id,
      );
    }
    return $result;
  }
}
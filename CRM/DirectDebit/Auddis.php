<?php

class CRM_DirectDebit_Auddis
{
  /*
   * Notification of failed debits and cancelled or amended DDIs are made available via Automated Direct Debit
   * Instruction Service (AUDDIS), Automated Return of Unpaid Direct Debit (ARUDD) files and Automated Direct Debit
   * Amendment and Cancellation (ADDACS) files. Notification of any claims relating to disputed Debits are made via
   * Direct Debit Indemnity Claim Advice (DDICA) reports.
   */

  /**
   * Get List of AUDDIS files from SmartDebit for the past month.
   * If dateOfCollection is not specified it defaults to today.
   * @param null $dateOfCollection
   * @return bool|mixed
   */
  static function getSmartDebitAuddisList($dateOfCollectionStart = null, $dateOfCollectionEnd = null)
  {
    if (!isset($dateOfCollectionEnd)) {
      $dateOfCollectionEnd = date('Y-m-d', new DateTime()); // Today
    }
    if (!isset($dateOfCollectionStart)) {
      $dateOfCollectionStart = date('Y-m-d', strtotime($dateOfCollectionEnd . '-1 month'));
    }
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    // Send payment POST to the target URL

    $urlAuddis = CRM_DirectDebit_Base::getApiUrl('/api/auddis/list',
      "query[service_user][pslid]=$pslid&query[from_date]=$dateOfCollectionStart&query[till_date]=$dateOfCollectionEnd");
    $responseAuddis = CRM_DirectDebit_Base::requestPost($urlAuddis, '', $username, $password, '');
    // Take action based upon the response status
    switch (strtoupper($responseAuddis['Status'])) {
      case 'OK':
        return $responseAuddis;
      default:
        return false;
    }
  }

  /**
   * Get AUDDIS file from Smart Debit. $uri is retrieved using getSmartDebitAuddisList
   * @param null $uri
   * @return array
   */
  static function getSmartDebitAuddisFile($fileId)
  {
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    if (empty($fileId)) {
      CRM_Core_Error::debug_log_message('SmartDebit getSmartDebitAuddisFile: Must specify file ID!');
      return false;
    }
    $url = CRM_DirectDebit_Base::getApiUrl("/api/auddis/$fileId",
      "query[service_user][pslid]=$pslid");
    $responseAuddis = CRM_DirectDebit_Base::requestPost($url, '', $username, $password, '');
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
    if (isset($auddisArray['Data']['MessagingAdvices']['Header']['@attributes']['report-generation-date'])) {
      $result['auddis_date'] = $auddisArray['Data']['MessagingAdvices']['Header']['@attributes']['report-generation-date'];
    }
    return $result;
  }

  /**
   * Get List of ARUDD files from SmartDebit for the past month.
   * If dateOfCollection is not specified it defaults to today.
   * @param null $dateOfCollection
   * @return bool|mixed
   */
  static function getSmartDebitAruddList($dateOfCollectionStart = null, $dateOfCollectionEnd = null)
  {
    if (!isset($dateOfCollectionEnd)) {
      $dateOfCollectionEnd = date('Y-m-d', new DateTime()); // Today
    }
    if (!isset($dateOfCollectionStart)) {
      $dateOfCollectionStart = date('Y-m-d', strtotime($dateOfCollectionEnd . '-1 month'));
    }
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    // Send payment POST to the target URL
    $urlArudd = CRM_DirectDebit_Base::getApiUrl('/api/arudd/list', "query[service_user][pslid]=$pslid&query[from_date]=$dateOfCollectionStart&query[till_date]=$dateOfCollectionEnd");
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

  /**
   * Get ARUDD file from Smart Debit. $uri is retrieved using getSmartDebitAuddisList
   * @param null $uri
   * @return array
   */
  static function getSmartDebitAruddFile($fileId)
  {
    $userDetails = CRM_DirectDebit_Auddis::getSmartDebitUserDetails();
    $username = CRM_Utils_Array::value('user_name', $userDetails);
    $password = CRM_Utils_Array::value('password', $userDetails);
    $pslid = CRM_Utils_Array::value('signature', $userDetails);

    if (!isset($fileId)) {
      CRM_Core_Error::debug_log_message('SmartDebit getSmartDebitAruddFile: Must specify file ID!');
      return false;
    }

    $url = CRM_DirectDebit_Base::getApiUrl("/api/arudd/$fileId",
      "query[service_user][pslid]=$pslid");
    $responseArudd = CRM_DirectDebit_Base::requestPost($url, '', $username, $password, '');
    $scrambled = str_replace(" ", "+", $responseArudd['file']);
    $outputafterencode = base64_decode($scrambled);
    $aruddArray = json_decode(json_encode((array)simplexml_load_string($outputafterencode)), 1);
    $result = array();

    if (isset($aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem']['@attributes'])) {
      // Got a single result
      // FIXME: Check that this is correct (ie. results not in array at ReturnedDebitItem if single
      $result[0] = $aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem']['@attributes'];
    } else {
      foreach ($aruddArray['Data']['ARUDD']['Advice']['OriginatingAccountRecords']['OriginatingAccountRecord']['ReturnedDebitItem'] as $key => $value) {
        $result[$key] = $value['@attributes'];
      }
    }
    $result['arudd_date'] = $aruddArray['Data']['ARUDD']['Header']['@attributes']['currentProcessingDate'];
    return $result;
  }

  /**
   * Get the AUDDIS record for $auddisDate. Returns FALSE if not found.
   * @param array $auddisArray
   * @param null $auddisDate
   * @return array
   */
  static function getRightAuddisFile($auddisArray = array(), $auddisDate = NULL)
  {
    $auddisDetails = array();
    if ($auddisArray && $auddisDate) {
      if (isset($auddisArray['@attributes']['results']) && ($auddisArray['@attributes']['results'] > 1)) {
        // Multiple results returned
        foreach ($auddisArray['auddis'] as $key => $auddis) {
          if (strtotime($auddisDate) == strtotime(substr($auddis['report_generation_date'], 0, 10))) {
            $auddisDetails['auddis_id'] = $auddis['auddis_id'];
            $auddisDetails['report_generation_date'] = substr($auddis['report_generation_date'], 0, 10);
            $auddisDetails['uri'] = $auddis['@attributes']['uri'];
            return $auddisDetails;
          }
        }
      } else {
        // Only one result returned
        if (strtotime($auddisDate) == strtotime(substr($auddisArray['report_generation_date'], 0, 10))) {
          $auddisDetails['auddis_id'] = $auddisArray['auddis_id'];
          $auddisDetails['report_generation_date'] = substr($auddisArray['report_generation_date'], 0, 10);
          $auddisDetails['uri'] = $auddisArray['@attributes']['uri'];
          return $auddisDetails;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the ARUDD record for $aruddDate. Returns FALSE if not found
   * @param array $aruddArray
   * @param null $aruddDate
   * @return array
   */
  static function getRightAruddFile($aruddArray = array(), $aruddDate = NULL)
  {
    if ($aruddArray && $aruddDate) {
      if (isset($aruddArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($aruddArray as $key => $arudd) {
          if (strtotime($aruddDate) == strtotime(substr($arudd['current_processing_date'], 0, 10))) {
            $aruddDetails['arudd_id'] = $arudd['arudd_id'];
            $aruddDetails['current_processing_date'] = substr($arudd['current_processing_date'], 0, 10);
            $aruddDetails['uri'] = $arudd['@attributes']['uri'];
            return $aruddDetails;
          }
        }
      } else {
        // Only one result returned
        if (strtotime($aruddDate) == strtotime(substr($aruddArray['current_processing_date'], 0, 10))) {
          $aruddDetails['arudd_id'] = $aruddArray['arudd_id'];
          $aruddDetails['current_processing_date'] = substr($aruddArray['current_processing_date'], 0, 10);
          $aruddDetails['uri'] = $aruddArray['@attributes']['uri'];
          return $aruddDetails;
        }
      }
    }
    return FALSE;
  }

  static function getSmartDebitCollectionReportForMonth($dateOfCollection) {
    if( empty($dateOfCollection)){
      // CRM_Core_Session::setStatus(ts('Please select the collection date'), ts('Smart Debit'), 'error');
      $collections['error'] = 'Please specify a collection date';
      return $collections;
    }
    $collections = array();

    // Empty the collection reports table
    $emptySql = "TRUNCATE TABLE veda_civicrm_smartdebit_import";
    CRM_Core_DAO::executeQuery($emptySql);

    // Get a collection report for every day of the month
    $dateEnd = new DateTime($dateOfCollection);
    $dateStart = clone $dateEnd;
    $dateStart->modify('-1 month');
    $dateCurrent = clone $dateEnd;

    while ($dateCurrent > $dateStart) {
      $newCollections = CRM_DirectDebit_Auddis::getSmartDebitCollectionReport($dateCurrent->format('Y-m-d'));
      if (!isset($newCollections['error'])) {
        $collections = array_merge($collections, $newCollections);
      }
      $dateCurrent->modify('-1 day');
    }
    return $collections;
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
    $url = CRM_DirectDebit_Base::getApiUrl('/api/get_successful_collection_report', "query[service_user][pslid]=$pslid&query[collection_date]=$dateOfCollection");
    $response    = CRM_DirectDebit_Base::requestPost($url, '', $username, $password, '');

    // Take action based upon the response status
    switch ( strtoupper( $response["Status"] ) ) {
      case 'OK':
        if (!isset($response['Successes']) || !isset($response['Rejects'])) {
          $collections['error'] = $response['Summary'];
          return $collections;
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
        $collections['error'] = $response['error'];
        return $collections;
      default:
        $collections['error'] = $response['error'];
        return $collections;
    }
  }

  /**
   * Save collection report (getSmartDebitCollectionReport) to database
   * @param $collections
   */
  static function saveSmartDebitCollectionReport($collections) {
    $tableName = "veda_civicrm_smartdebit_import";
    if(!empty($collections)){
      foreach ($collections as $key => $value) {
        $resultCollection = array(
          'transaction_id' => $value['reference_number'],
          'contact'        => $value['account_name'],
          'contact_id'     => $value['customer_id'],
          'amount'         => $value['amount'],
          'receive_date'   => !empty($value['debit_date']) ? date('YmdHis', strtotime(str_replace('/', '-', $value['debit_date']))) : NULL,
        );
        $insertValue[] = " ( \"". implode('", "', $resultCollection) . "\" )";
      }
      $sql = " INSERT INTO `{$tableName}`
              (`transaction_id`, `contact`, `contact_id`, `amount`, `receive_date`)
              VALUES ".implode(', ', $insertValue)."
              ";
      CRM_Core_DAO::executeQuery($sql);
    }
  }

  /**
   * Remove all collection report date from veda_civicrm_smartdebit_import that is older than one month
   */
  static function removeOldSmartDebitCollectionReports() {
    $date = new DateTime();
    $date->modify('-1 month');
    $dateString = $date->format('Ymd') . '000000';
    $query = "DELETE FROM `veda_civicrm_smartdebit_import` WHERE receive_date < %1";
    $params = array(1 => array($dateString, 'String'));
    CRM_Core_DAO::executeQuery($query, $params);
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
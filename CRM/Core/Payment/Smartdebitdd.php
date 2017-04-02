<?php

require_once 'CRM/Core/Payment.php';

class CRM_Core_Payment_Smartdebitdd extends CRM_Core_Payment
{
  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = NULL;

  /**
   * mode of operation: live or test
   *
   * @var object
   */
  protected $_mode = NULL;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   * @param $paymentProcessor
   */
  function __construct($mode, &$paymentProcessor)
  {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Smart Debit Processor');
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   * @param $paymentProcessor
   * @return object
   * @static
   *
   */
  static function &singleton($mode, &$paymentProcessor, &$paymentForm = NULL, $force = FALSE)
  {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === NULL) {
      self::$_singleton[$processorName] = new self($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig()
  {
    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('The "Bill To ID" is not set in the Administer CiviCRM Payment Processor.');
    }

    /* TO DO
     * Add check to ensure password is also set
     * Also the URL's for api site
     */

    if (!empty($error)) {
      return implode('<p>', $error);
    } else {
      return NULL;
    }
  }

  // FIXME: Is this function actually used?
  /**
   * @param CRM_Core_Form $form
   * @return bool|void
   */
  function buildForm(&$form)
  {
    $ddForm = new CRM_DirectDebit_Form_Main();
    $ddForm->buildDirectDebit($form);
    // If we are updating billing address of smart debit mandate we don't need to validate, validation will happen in updateSubscriptionBillingInfo method
    if ($form->getVar('_name') == 'UpdateBilling') {
      return;
    }
    $form->addFormRule(array('CRM_Core_Payment_Smartdebitdd', 'validatePayment'), $form);
    return TRUE;
  }

  /**
   * Get contact email for POSTing to Smart Debit API
   * @param $params
   * @return mixed
   */
  static function getUserEmail(&$params)
  {
    // Set email
    if (!empty($params['email-Primary'])) {
      $useremail = $params['email-Primary'];
    } else {
      $useremail = $params['email-5'];
    }
    return $useremail;
  }

  /**
   * From the selected collection day determine when the actual collection start date could be
   * For direct debit we need to allow 10 working days prior to collection for cooling off
   * We also may need to send them a letter etc
   *
   */
  static function getCollectionStartDate(&$params)
  {
    $preferredCollectionDay = $params['preferred_collection_day'];
    return CRM_DirectDebit_Base::firstCollectionDate($preferredCollectionDay);
  }

  /**
   * Determine the frequency based on the recurring params if set
   * Should check the [frequency_unit] and if set use that
   * Smart debit supports frequency intervals of 1-4 for each Y,Q,M,W.
   *
   * @return array (string Y,Q,M,W,O; int frequencyInterval)
   */
  static function getCollectionFrequency(&$params)
  {
    // Smart Debit supports Y, Q, M, W parameters
    // We return 'O' if the payment is not recurring.  You should then supply an end date to smart debit
    //    to ensure only a single payment is taken.
    $frequencyUnit = (isset($params['frequency_unit'])) ? $params['frequency_unit'] : '';
    $frequencyInterval = (isset($params['frequency_interval'])) ? $params['frequency_interval'] : 1;

    switch (strtolower($frequencyUnit)) {
      case 'year':
        $collectionFrequency = 'Y';
        break;
      case 'month':
        if ($frequencyInterval % 3 != 0) {
          // Monthly
          if ($frequencyInterval > 4) {
            CRM_Core_Error::debug_log_message('The maximum monthly collection interval for Smart Debit is 4 months but you specified ' . $frequencyInterval . ' months. 
            Resetting to 4 months. If you meant to select a quarterly interval make sure the collection interval is a multiple of 3.');
            $frequencyInterval = 4;
          }
          $collectionFrequency = 'M';
        } else {
          // Quarterly (frequencyInterval is a multiple of 3)
          if ($frequencyInterval > 12) {
            CRM_Core_Error::debug_log_message('The maximum quarterly collection interval for Smart Debit is 4 quarters but you specified ' . $frequencyInterval . ' months. Resetting to 4 quarters');
            $frequencyInterval = 12;
          }
          // Convert frequencyInterval from months to quarters
          $frequencyInterval = ($frequencyInterval / 3);
          $collectionFrequency = 'Q';
        }
        break;
      case 'day':
        // Make sure frequencyInterval is a multiple of 7 days (ie 1 week)
        if ($frequencyInterval % 7 != 0) {
          CRM_Core_Error::debug_log_message('The minimum collection interval for Smart Debit is 1 week but you specified ' . $frequencyInterval . ' days. Resetting to 1 week');
          $frequencyInterval = 7;
        }
        if ($frequencyInterval > 28) {
          CRM_Core_Error::debug_log_message('The maximum weekly collection interval for Smart Debit is 4 weeks but you specified ' . $frequencyInterval . ' days. Resetting to 4 weeks');
          $frequencyInterval = 28;
        }
        // Convert frequencyInterval from days to weeks
        $frequencyInterval = ($frequencyInterval / 7);
        $collectionFrequency = 'W';
        break;
      default:
        $collectionFrequency = 'O';
        $frequencyInterval = 1; // Not really needed here
    }
    return array($collectionFrequency, $frequencyInterval);
  }

  /**
   * Replace comma with space
   * @param $pString
   * @return mixed
   */
  static function replaceCommaWithSpace($pString)
  {
    return str_replace(',', ' ', $pString);
  }

  /**
   * Prepare Post Array for POSTing to Smart Debit APi
   * @param $fields
   * @param null $self
   * @return array
   */
  static function preparePostArray($fields, $self = NULL)
  {
    $collectionDate = self::getCollectionStartDate($fields);
    $amount = 0;
    $serviceUserId = NULL;
    if (isset($fields['amount'])) {
      // Set amount in pence if not already set that way.
      $amount = $fields['amount'];
      // $amount might be a string (?) e.g. £12.00, so try just in case
      try {
        $amount = $amount * 100;
      } catch (Exception $e) {
        //Leave amount as it was
        $amount = $fields['amount'];
      }
    }

    if (isset($self->_paymentProcessor['signature'])) {
      $serviceUserId = $self->_paymentProcessor['signature'];
    }

    if (isset($fields['contactID'])) {
      $payerReference = $fields['contactID'];
    } elseif (isset($fields['cms_contactID'])) {
      $payerReference = $fields['cms_contactID'];
    } else {
      $payerReference = 'CIVICRMEXT';
    }

    // Construct params list to send to Smart Debit ...
    $smartDebitParams = array(
      'variable_ddi[service_user][pslid]' => $serviceUserId,
      'variable_ddi[reference_number]' => $fields['ddi_reference'],
      'variable_ddi[payer_reference]' => $payerReference,
      'variable_ddi[first_name]' => $fields['billing_first_name'],
      'variable_ddi[last_name]' => $fields['billing_last_name'],
      'variable_ddi[address_1]' => self::replaceCommaWithSpace($fields['billing_street_address-5']),
      'variable_ddi[town]' => self::replaceCommaWithSpace($fields['billing_city-5']),
      'variable_ddi[postcode]' => $fields['billing_postal_code-5'],
      'variable_ddi[country]' => $fields['billing_country_id-5'], //*** $params['billing_country-5']
      'variable_ddi[account_name]' => $fields['account_holder'],
      'variable_ddi[sort_code]' => $fields['bank_identification_number'],
      'variable_ddi[account_number]' => $fields['bank_account_number'],
      'variable_ddi[regular_amount]' => $amount,
      'variable_ddi[first_amount]' => $amount,
      'variable_ddi[default_amount]' => $amount,
      'variable_ddi[start_date]' => $collectionDate->format("Y-m-d"),
      'variable_ddi[email_address]' => self::getUserEmail($fields),
    );

    list($collectionFrequency, $collectionInterval) = self::getCollectionFrequency($fields);
    if ($collectionFrequency == 'O') {
      $collectionFrequency = 'Y';
      // Set end date 6 days after start date (min DD freq with Smart Debit is 1 week/7days)
      $endDate = $collectionDate->add(new DateInterval('P6D'));
      $smartDebitParams['variable_ddi[end_date]'] = $endDate->format("Y-m-d");
    }
    $smartDebitParams['variable_ddi[frequency_type]'] = $collectionFrequency;
    $smartDebitParams['variable_ddi[frequency_factor]'] = $collectionInterval;

    return $smartDebitParams;
  }

  /**
   * Sets appropriate parameters and calls Smart Debit API to validate a payment (does not setup the payment)
   *
   * @param array $params name value pair of contribution data
   *
   * @return array $result
   * @access public
   *
   */
  static function validatePayment($fields, $files, $self)
  {
    $validateParams = $fields;

    /* First thing to do is check if the DD has already been submitted */
    if (CRM_DirectDebit_Base::isDDSubmissionComplete($fields['ddi_reference'])) {
      $response[] = "PreviouslySubmitted";
      return self::invalid($response, $validateParams);
    }

    $smartDebitParams = self::preparePostArray($validateParams, $self);

    // Construct post string
    $post = '';
    foreach ($smartDebitParams as $key => $value) {
      $post .= ($key != 'variable_ddi[service_user][pslid]' ? '&' : '') . $key . '=' . urlencode($value);
    }

    // Get the API Username and Password
    $username = $self->_paymentProcessor['user_name'];
    $password = $self->_paymentProcessor['password'];

    // Send payment POST to the target URL
    $url = $self->_paymentProcessor['url_api'];
    $request_path = 'api/ddi/variable/validate';

    $response = CRM_DirectDebit_Base::requestPost($url, $post, $username, $password, $request_path);

    $direct_debit_response = array();
    $direct_debit_response['data_type'] = 'recurring';
    $direct_debit_response['entity_type'] = 'contribution_recur';
    $direct_debit_response['first_collection_date'] = $smartDebitParams['variable_ddi[start_date]'];
    $direct_debit_response['preferred_collection_day'] = $fields['preferred_collection_day'];
    $direct_debit_response['confirmation_method'] = $fields['confirmation_method'];
    $direct_debit_response['ddi_reference'] = $fields['ddi_reference'];
    $direct_debit_response['response_status'] = $response['Status'];
    $direct_debit_response['response_raw'] = NULL;
    $direct_debit_response['entity_id'] = NULL;
    $direct_debit_response['bank_name'] = NULL;
    $direct_debit_response['branch'] = NULL;
    $direct_debit_response['address1'] = NULL;
    $direct_debit_response['address2'] = NULL;
    $direct_debit_response['address3'] = NULL;
    $direct_debit_response['address4'] = NULL;
    $direct_debit_response['town'] = NULL;
    $direct_debit_response['county'] = NULL;
    $direct_debit_response['postcode'] = NULL;

    if (!empty($response['error'])) {
      $direct_debit_response['response_raw'] = $response['error'];
    }

    // Take action based upon the response status
    switch (strtoupper($response["Status"])) {
      case 'OK':
        $direct_debit_response['entity_id'] = isset($fields['entity_id']) ? $fields['entity_id'] : 0;
        $direct_debit_response['bank_name'] = $response['success'][2]["@attributes"]["bank_name"];
        $direct_debit_response['branch'] = $response['success'][2]["@attributes"]["branch"];
        $direct_debit_response['address1'] = $response['success'][2]["@attributes"]["address1"];
        $direct_debit_response['address2'] = $response['success'][2]["@attributes"]["address2"];
        $direct_debit_response['address3'] = $response['success'][2]["@attributes"]["address3"];
        $direct_debit_response['address4'] = $response['success'][2]["@attributes"]["address4"];
        $direct_debit_response['town'] = $response['success'][2]["@attributes"]["town"];
        $direct_debit_response['county'] = $response['success'][2]["@attributes"]["county"];
        $direct_debit_response['postcode'] = $response['success'][2]["@attributes"]["postcode"];

        self::recordSmartDebitResponse($direct_debit_response);
        return self::validate_succeed($response, $fields);
      case 'REJECTED':
        self::recordSmartDebitResponse($direct_debit_response);
        $_SESSION['contribution_attempt'] = 'failed';
        return self::rejected($response, $fields);
      case 'INVALID':
        self::recordSmartDebitResponse($direct_debit_response);
        $_SESSION['contribution_attempt'] = 'failed';
        return self::invalid($response, $fields);
      default:
        self::recordSmartDebitResponse($direct_debit_response);
        $_SESSION['contribution_attempt'] = 'failed';
        return self::error($response, $fields);
    }
  }

  /**
   * Sets appropriate parameters and calls Smart Debit API to create a payment
   *
   * @param array $params name value pair of contribution data
   *
   * @return array $result
   * @access public
   *
   */
  function doDirectPayment(&$params)
  {
    $validateParams = $params;
    $smartDebitParams = self::preparePostArray($validateParams);
    $serviceUserId = $this->_paymentProcessor['signature'];

    // Construct post string
    $post = '';
    foreach ($smartDebitParams as $key => $value) {
      $post .= ($key != 'variable_ddi[service_user][pslid]' ? '&' : '') . $key . '=' . ($key != 'variable_ddi[service_user][pslid]' ? urlencode($value) : $serviceUserId);
    }
    // Get the API Username and Password
    $username = $this->_paymentProcessor['user_name'];
    $password = $this->_paymentProcessor['password'];

    // Send payment POST to the target URL
    $url = $this->_paymentProcessor['url_api'];
    $request_path = 'api/ddi/variable/create';

    $response = CRM_DirectDebit_Base::requestPost($url, $post, $username, $password, $request_path);
    $response['reference_number'] = $smartDebitParams['variable_ddi[reference_number]'];

    // Take action based upon the response status
    switch (strtoupper($response["Status"])) {
      case 'OK':
        return self::succeed($response, $params);
      case 'REJECTED':
        $_SESSION['contribution_attempt'] = 'failed';
        return self::rejected($response, $params);
      case 'INVALID':
        $_SESSION['contribution_attempt'] = 'failed';
        return self::invalid($response, $params);
      default:
        $_SESSION['contribution_attempt'] = 'failed';
        return self::error($response, $params);
    }
  }

  /**
   * Record the response from SmartDebit after validatePayment()
   * @param $direct_debit_response
   */
  static function recordSmartDebitResponse($direct_debit_response)
  {
    $sql = <<<EOF
            UPDATE civicrm_direct_debit
            SET    created                  = NOW()
            ,      data_type                = %0
            ,      entity_type              = %1
            ,      entity_id                = %2
            ,      bank_name                = %3
            ,      branch                   = %4
            ,      address1                 = %5
            ,      address2                 = %6
            ,      address3                 = %7
            ,      address4                 = %8
            ,      town                     = %9
            ,      county                   = %10
            ,      postcode                 = %11
            ,      first_collection_date    = %12
            ,      preferred_collection_day = %13
            ,      confirmation_method      = %14
            ,      response_status          = %15
            ,      response_raw             = %16
            ,      request_counter          = request_counter + 1
            WHERE  ddi_reference            = %17
EOF;

    CRM_Core_DAO::executeQuery($sql, array(
        array((string)$direct_debit_response['data_type'], 'String'),
        array((string)$direct_debit_response['entity_type'], 'String'),
        array((integer)$direct_debit_response['entity_id'], 'Integer'),
        array((string)$direct_debit_response['bank_name'], 'String'),
        array((string)$direct_debit_response['branch'], 'String'),
        array((string)$direct_debit_response['address1'], 'String'),
        array((string)$direct_debit_response['address2'], 'String'),
        array((string)$direct_debit_response['address3'], 'String'),
        array((string)$direct_debit_response['address4'], 'String'),
        array((string)$direct_debit_response['town'], 'String'),
        array((string)$direct_debit_response['county'], 'String'),
        array((string)$direct_debit_response['postcode'], 'String'),
        array((string)$direct_debit_response['first_collection_date'], 'String'),
        array((string)$direct_debit_response['preferred_collection_day'], 'String'),
        array((string)$direct_debit_response['confirmation_method'], 'String'),
        array((string)$direct_debit_response['response_status'], 'String'),
        array((string)$direct_debit_response['response_raw'], 'String'),
        array((string)$direct_debit_response['ddi_reference'], 'String')
      )
    );
  }

  /**
   * SmartDebit payment has succeeded
   * @param $response
   * @return array
   */
  private static function validate_succeed($response, &$params)
  {
    // Clear any old error messages from stack
    $response['trxn_id'] = $params['ddi_reference'];
    return true;
  }

  /**
   * SmartDebit payment has succeeded
   * @param $response
   * @return array
   */
  private static function succeed($response, &$params)
  {
    $response['trxn_id'] = $response['reference_number'];
    return $response;
  }

  /**
   * SmartDebit payment has failed
   * @param $response
   * @param $params
   * @return array
   */
  private static function invalid($response, $params)
  {
    $msg = "Unfortunately, it seems the details provided are invalid – please double check your billing address and direct debit details and try again.";
    $msg .= "<ul>";

    foreach ($response as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $errorItem) {
          $msg .= "<li>";
          $msg .= $errorItem;
          $msg .= "</li>";
        }
      } else {
        if ($key == 'error') {
          $msg .= "<li>";
          $msg .= $value;
          $msg .= "</li>";
        }
      }
    }
    $msg .= "</ul>";
    CRM_Core_Session::setStatus($msg, ts('Direct Debit'));
    return CRM_Core_Error::createAPIError($msg, $response);
  }

  /**
   * SmartDebit payment has returned a status we do not understand
   * @param $response
   * @param $params
   * @return array
   */
  private static function error($response, $params)
  {
    $msg = "Unfortunately, it seems there was a problem with your direct debit details – please double check your billing address and card details and try again";
    CRM_Core_Session::setStatus($msg, ts('Direct Debit'));
    return CRM_Core_Error::createAPIError($msg, $response);
  }

  /**
   * SmartDebit payment has failed
   * @param $response
   * @param $params
   * @return array
   */
  private static function rejected($response, $params)
  {
    $msg = "Unfortunately, it seems the authorisation was rejected – please double check your billing address and card details and try again.";
    CRM_Core_Session::setStatus($msg, ts('Direct Debit'));
    return CRM_Core_Error::createAPIError($msg, $response);
  }

  /**
   * Sets appropriate parameters for checking out to UCM Payment Collection
   *
   * @param array $params name value pair of contribution datat
   * @param $component
   * @access public
   *
   */
  function doTransferCheckout(&$params, $component)
  {
    CRM_Core_Error::fatal(ts('SmartDebit::doTransferCheckout: This function is not implemented'));
  }

  /**
   * Process incoming notification.
   */
  public static function handlePaymentNotification()
  {
    if (empty($_GET)) {
      $rpInvoiceArray = explode('&', $_POST['rp_invoice_id']);
      foreach ($rpInvoiceArray as $rpInvoiceValue) {
        $rpValueArray = explode('=', $rpInvoiceValue);
        if ($rpValueArray[0] == 'm') {
          $value = $rpValueArray[1];
        }
      }
      $SmartDebitIPN = new CRM_Core_Payment_SmartDebitIPN();
    } else {
      $value = CRM_Utils_Array::value('module', $_GET);
      $SmartDebitIPN = new CRM_Core_Payment_SmartDebitIPN();
    }

    switch (strtolower($value)) {
      case 'contribute':
        $SmartDebitIPN->main('contribute');
        break;
      case 'event':
        $SmartDebitIPN->main('event');
        break;
      default     :
        CRM_Core_Error::debug_log_message("Could not get module name from request url");
        echo "Could not get module name from request url<p>";
        break;
    }
  }

  /**
   * Change the subscription amount using the Smart Debit API
   * @param string $message
   * @param array $params
   * @return bool
   */
  function changeSubscriptionAmount(&$message = '', $params = array())
  {
    if ($this->_paymentProcessor['payment_processor_type'] == 'Smart_Debit') {
      $post = '';
      $serviceUserId = $this->_paymentProcessor['signature'];
      $username = $this->_paymentProcessor['user_name'];
      $password = $this->_paymentProcessor['password'];
      $url = $this->_paymentProcessor['url_api'];
      $accountHolder = $params['account_holder'];
      $accountNumber = $params['bank_account_number'];
      $sortcode = $params['bank_identification_number'];
      $bankName = $params['bank_name'];
      $amount = $params['amount'];
      $amount = $amount * 100;
      $reference = $params['subscriptionId'];
      $frequencyType = $params['frequency_unit'];
      $eDate = $params['end_date'];
      $sDate = $params['start_date'];

      if (!empty($eDate)) {
        $endDate = strtotime($eDate);
        $endDate = date("Y-m-d", $endDate);
      }

      if (!empty($sDate)) {
        $startDate = strtotime($sDate);
        $startDate = date("Y-m-d", $startDate);
      }

      $request_path = 'api/ddi/variable/' . $reference . '/update';

      $smartDebitParams = array(
        'variable_ddi[service_user][pslid]' => $serviceUserId,
        'variable_ddi[reference_number]' => $reference,
        'variable_ddi[regular_amount]' => $amount,
        'variable_ddi[first_amount]' => $amount,
        'variable_ddi[default_amount]' => $amount,
        'variable_ddi[start_date]' => $startDate,
        'variable_ddi[end_date]' => $endDate,
        'variable_ddi[account_name]' => $accountHolder,
        'variable_ddi[sort_code]' => $sortcode,
        'variable_ddi[account_number]' => $accountNumber,
        'variable_ddi[frequency_type]' => $frequencyType
      );

      foreach ($smartDebitParams as $key => $value) {
        if (!empty($value))
          $post .= ($key != 'variable_ddi[service_user][pslid]' ? '&' : '') . $key . '=' . ($key != 'variable_ddi[service_user][pslid]' ? urlencode($value) : $serviceUserId);
      }

      $response = CRM_DirectDebit_Base::requestPost($url, $post, $username, $password, $request_path);
      if (strtoupper($response["Status"]) != 'OK') {
        $msg = self::formatResponseError(isset($response['error']) ? $response['error'] : '');
        $msg .= '<br />Update Subscription Failed.';
        CRM_Core_Session::setStatus(ts($msg), 'Smart Debit', 'error');
        return FALSE;
      }
      return TRUE;
    }
  }

  /**
   * Cancel the Direct Debit Subscription using the Smart Debit API
   * @param string $message
   * @param array $params
   * @return bool
   */
  function cancelSubscription(&$message = '', $params = array())
  {
    if ($this->_paymentProcessor['payment_processor_type'] == 'Smart_Debit') {
      $post = '';
      $serviceUserId = $this->_paymentProcessor['signature'];
      $username = $this->_paymentProcessor['user_name'];
      $password = $this->_paymentProcessor['password'];
      $url = $this->_paymentProcessor['url_api'];
      $reference = $params['subscriptionId'];
      $request_path = 'api/ddi/variable/' . $reference . '/cancel';
      $smartDebitParams = array(
        'variable_ddi[service_user][pslid]' => $serviceUserId,
        'variable_ddi[reference_number]' => $reference,
      );
      foreach ($smartDebitParams as $key => $value) {
        $post .= ($key != 'variable_ddi[service_user][pslid]' ? '&' : '') . $key . '=' . ($key != 'variable_ddi[service_user][pslid]' ? urlencode($value) : $serviceUserId);
      }

      $response = CRM_DirectDebit_Base::requestPost($url, $post, $username, $password, $request_path);
      if (strtoupper($response["Status"]) != 'OK') {
        $msg = self::formatResponseError(isset($response['error']) ? $response['error'] : '');
        $msg .= '<br />Cancel Subscription Failed.';
        CRM_Core_Session::setStatus(ts($msg), 'Smart Debit', 'error');
        return FALSE;
      }
      return TRUE;
    }
  }

  function updateSubscriptionBillingInfo(&$message = '', $params = array())
  {
    if ($this->_paymentProcessor['payment_processor_type'] == 'Smart_Debit') {
      $post = '';
      $serviceUserId = $this->_paymentProcessor['signature'];
      $username = $this->_paymentProcessor['user_name'];
      $password = $this->_paymentProcessor['password'];
      $url = $this->_paymentProcessor['url_api'];
      $reference = $params['subscriptionId'];
      $firstName = $params['first_name'];
      $lastName = $params['last_name'];
      $streetAddress = $params['street_address'];
      $city = $params['city'];
      $postcode = $params['postal_code'];
      $state = $params['state_province'];
      $country = $params['country'];

      $request_path = 'api/ddi/variable/' . $reference . '/update';
      $smartDebitParams = array(
        'variable_ddi[service_user][pslid]' => $serviceUserId,
        'variable_ddi[reference_number]' => $reference,
        'variable_ddi[first_name]' => $firstName,
        'variable_ddi[last_name]' => $lastName,
        'variable_ddi[address_1]' => self::replaceCommaWithSpace($streetAddress),
        'variable_ddi[town]' => $city,
        'variable_ddi[postcode]' => $postcode,
        'variable_ddi[country]' => $country,
      );
      foreach ($smartDebitParams as $key => $value) {
        $post .= ($key != 'variable_ddi[service_user][pslid]' ? '&' : '') . $key . '=' . ($key != 'variable_ddi[service_user][pslid]' ? urlencode($value) : $serviceUserId);
      }

      $response = CRM_DirectDebit_Base::requestPost($url, $post, $username, $password, $request_path);
      if (strtoupper($response["Status"]) != 'OK') {
        $msg = self::formatResponseError(isset($response['error']) ? $response['error'] : '');
        $msg .= '<br />Please double check your billing address and try again.';
        CRM_Core_Session::setStatus(ts($msg), 'Smart Debit', 'error');
        return FALSE;
      }
      return TRUE;
    }
  }

  /**
   * Format response error for display to user
   * @param $responseErrors
   * @return string
   */
  static function formatResponseError($responseErrors)
  {
    $errorMsg = '';
    if (!is_array($responseErrors)) {
      $errorMsg = $responseErrors . '<br />';
      $errorMsg .= '<br />';
    } else {
      foreach ($responseErrors as $error) {
        $errorMsg .= $error . '<br />';
      }
      $errorMsg .= '<br />';
    }
    $errorMsg .= 'Please correct the errors and try again';
    return $errorMsg;
  }
}

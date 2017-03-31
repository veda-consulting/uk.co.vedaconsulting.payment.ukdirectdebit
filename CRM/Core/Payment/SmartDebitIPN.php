<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.2                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2012                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2012
 * $Id$
 *
 */
class CRM_Core_Payment_SmartDebitIPN extends CRM_Core_Payment_BaseIPN {

  static $_paymentProcessor = NULL;
  function __construct() {
    parent::__construct();
  }

  function getValue($name, $abort = TRUE) {
    if (!empty($_POST)) {
      $rpInvoiceArray = array();
      $value          = NULL;
      $rpInvoiceArray = explode('&', $_POST['rp_invoice_id']);
      foreach ($rpInvoiceArray as $rpInvoiceValue) {
        $rpValueArray = explode('=', $rpInvoiceValue);
        if ($rpValueArray[0] == $name) {
          $value = $rpValueArray[1];
        }
      }

      if ($value == NULL && $abort) {
        echo "Failure (getValue): Missing Parameter $name<p>";
        exit();
      }
      else {
        return $value;
      }
    }
    else {
      return NULL;
    }
  }

  static function retrieve($name, $type, $location = 'POST', $abort = TRUE) {
    static $store = NULL;
    $value = CRM_Utils_Request::retrieve($name, $type, $store,
      FALSE, NULL, $location
    );
    if ($abort && $value === NULL) {
      CRM_Core_Error::debug_log_message("Could not find an entry for $name in $location");
      echo "Failure (retrieve): Missing Parameter $name<p>";
      exit();
    }
    return $value;
  }

  function recur(&$input, &$ids, &$objects, $first) {
    if (!isset($input['txnType'])) {
      CRM_Core_Error::debug_log_message("Could not find txn_type in input request");
      echo "Failure: Invalid parameters<p>";
      return FALSE;
    }

    if ( $input['txnType']       == 'recurring_payment' &&
      $input['paymentStatus'] != 'Completed'
    ) {
      CRM_Core_Error::debug_log_message("Ignore all IPN payments that are not completed");
      echo "Failure: Invalid parameters<p>";
      return FALSE;
    }

    $recur = &$objects['contributionRecur'];

    // make sure the invoice ids match
    // make sure the invoice is valid and matches what we have in
    // the contribution record
    if ( $recur->invoice_id != $input['invoice'] ) {
      CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request recur is " . $recur->invoice_id . " input is " . $input['invoice']);
      echo "Failure: Invoice values dont match between database and IPN request recur is " . $recur->invoice_id . " input is " . $input['invoice'];
      return FALSE;
    }

    $now = date( 'YmdHis' );

    // fix dates that already exist
    $dates = array( 'create', 'start', 'end', 'cancel', 'modified' );
    foreach ( $dates as $date ) {
      $name = "{$date}_date";
      if ( $recur->$name ) {
        $recur->$name = CRM_Utils_Date::isoToMysql( $recur->$name );
      }
    }

    $sendNotification          = FALSE;
    $subscriptionPaymentStatus = NULL;

    //set transaction type
    $txnType = $input['txnType'];
    //Changes for paypal pro recurring payment

    switch ( strtolower( $txnType ) ) {
      case 'subscr_cancel':
        $recur->contribution_status_id = 3;
        $recur->cancel_date = $now;
        $objects['contribution']->source  = ts('Cancel Recurring Contribution: Smart Debit API');
        $objects['contribution']->receive_date  = $now;
        CRM_Activity_BAO_Activity::addActivity($objects['contribution'], NULL);
        break;
      case 'subscr_failed':
        $recur->contribution_status_id = 4;
        $recur->modified_date = $now;
        $objects['contribution']->source  = ts('Fail Recurring Contribution: Smart Debit API');
        $objects['contribution']->receive_date  = $now;
        CRM_Activity_BAO_Activity::addActivity($objects['contribution'], NULL);
        break;
      case 'recurring_payment_profile_created':
        CRM_Core_Error::debug_log_message("recurring_payment_profile_created");
        $recur->create_date            = $now;
        $recur->contribution_status_id = 2;
        $recur->processor_id           = $_POST['recurring_payment_id'];
        $recur->trxn_id                = $recur->processor_id;
        $subscriptionPaymentStatus     = CRM_Core_Payment::RECURRING_PAYMENT_START;
        break;
      case 'recurring_payment':
        CRM_Core_Error::debug_log_message("recurring_payment");
        if ( $first ) {
          $recur->payment_instrument_id = CRM_DirectDebit_Form_Main::getDDPaymentInstrumentID();
          $recur->trxn_id = $input['trxn_id'];
          $recur->processor_id = $input['trxn_id'];
          $recur->cycle_day = $input['collection_day'];
          $recur->start_date = $input['start_date'];

          //Create membership_id column in recur table if not exists.
          if($ids['membership']) {
            $columnExists = CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id');
            if(!$columnExists) {
              $query = "
                ALTER TABLE civicrm_contribution_recur
                ADD membership_id int(10) unsigned AFTER contact_id,
                ADD CONSTRAINT FK_civicrm_contribution_recur_membership_id
                FOREIGN KEY(membership_id) REFERENCES civicrm_membership(id) ON DELETE CASCADE ON UPDATE RESTRICT";

              CRM_Core_DAO::executeQuery($query);
            }

            // Populate membership_id
            $query = "
            UPDATE civicrm_contribution_recur
            SET membership_id = %1
            WHERE id = %2 ";

            $params = array( 1 => array( $ids['membership'][0], 'Int' ), 2 => array($ids['contributionRecur'], 'Int') );
            $dao = CRM_Core_DAO::executeQuery($query, $params);
          }
        }
        else {
          $recur->modified_date = $now;
        }

        $profile_status = ( isset( $_POST['profile_status'] ) ? $_POST['profile_status'] : 'xx' );
        //contribution installment is completed
        if ( $profile_status == 'Expired' ) {
          $recur->contribution_status_id = 1;
          $recur->end_date               = $now;
          $subscriptionPaymentStatus     = CRM_Core_Payment::RECURRING_PAYMENT_END;
        }

        // make sure the contribution status is not done
        // since order of ipn's is unknown
        if ( $recur->contribution_status_id != 1 ) {
          $recur->contribution_status_id = 5;
        }
        break;
    }

    $recur->save();

    if ( $sendNotification ) {
      $autoRenewMembership = FALSE;
      if ( $recur->id && isset( $ids['membership'] ) && $ids['membership'] ) {
        $autoRenewMembership = TRUE;
      }
      //send recurring Notification email for user
      CRM_Contribute_BAO_ContributionPage::recurringNotify( $subscriptionPaymentStatus,
        $ids['contact'],
        $ids['contributionPage'],
        $recur,
        $autoRenewMembership
      );
    }

    if ( $txnType != 'recurring_payment' ) {
      return;
    }

    if ( !$first ) {
      // create a contribution and then get it processed
      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->trxn_id = $input['trxn_id'];
      if ($contribution->trxn_id && $contribution->find()) {
        echo "Success: Contribution has already been handled<p>";
        return TRUE;
      }
      $contribution->contact_id = $ids['contact'];
      $contribution->contribution_type_id = $objects['contributionType']->id;
      $contribution->contribution_page_id = $ids['contributionPage'];
      $contribution->contribution_recur_id = $ids['contributionRecur'];

      /* TODO
       * This should probably be the date received, which is probably not now an a date passed in
       */
      $contribution->receive_date = $now;

      $contribution->currency = $objects['contribution']->currency;
      $contribution->payment_instrument_id = CRM_DirectDebit_Form_Main::getDDPaymentInstrumentID(); // $objects['contribution']->payment_instrument_id;
      $contribution->amount_level          = $objects['contribution']->amount_level;

      $objects['contribution']             = &$contribution;
    }

    $this->single( $input, $ids, $objects, TRUE, $first );
  }

  function single( &$input, &$ids, &$objects, $recur = FALSE, $first = FALSE ) {
    $contribution = &$objects['contribution'];

    // make sure the invoice is valid and matches what we have in the contribution record
    if ( ( !$recur ) || ( $recur && $first ) ) {
      if ( $contribution->invoice_id != $input['invoice'] ) {
        CRM_Core_Error::debug_log_message("Invoice values dont match between database and IPN request");
        echo "Failure: Invoice values dont match between database and IPN request<p>contribution is" . $contribution->invoice_id . " and input is " . $input['invoice'];
        return FALSE;
      }
    }
    else {
      $contribution->invoice_id = md5( uniqid( rand(), TRUE ) );
    }

    if ( !$recur ) {
      if ( $contribution->total_amount != $input['amount'] ) {
        CRM_Core_Error::debug_log_message("Amount values dont match between database and IPN request");
        echo "Failure: Amount values dont match between database and IPN request<p>";
        return FALSE;
      }
    }
    else {
      $contribution->total_amount = $input['amount'];
    }

    $contribution->payment_instrument_id = CRM_DirectDebit_Form_Main::getDDPaymentInstrumentID();

    if ($first) {
      // Set the received date to the date expected for DD payments
      $contribution->receive_date = $input['start_date'];
    }
    // KJ 13/07/2015 Fix for first payment's contribution receive_date as it takes today date 
    $contribution->save();
    $transaction = new CRM_Core_Transaction();

    $membership  = &$objects['membership'];
    if (!empty($membership)) {
      $first_membership_object = &$membership[key($membership)];

      // PS Set the recurring against the membership in case its not set already
      // Not sure why its not getting set - seems like a bug in core somewhere thats probably something to do with the payment instrument being credit card or something
      if ($recur && $first && empty($first_membership_object->contribution_recur_id)) {
        $first_membership_object->contribution_recur_id = $ids['contributionRecur'];
      }
    }

    $status = $input['paymentStatus'];
    if ( $status == 'Denied' || $status == 'Failed' || $status == 'Voided' ) {
      return $this->failed( $objects, $transaction );
    }
    elseif ( $status == 'Pending' ) {
      return $this->pending( $objects, $transaction );
    }
    elseif ( $status == 'Refunded' || $status == 'Reversed' ) {
      return $this->cancelled( $objects, $transaction );
    }
    elseif ( $status != 'Completed' ) {
      return $this->unhandled( $objects, $transaction );
    }

    // check if contribution is already completed, if so we ignore this ipn
    if ( $contribution->contribution_status_id == 1 ) {
      $transaction->commit();
      echo "Success: Contribution has already been handled<p>";
      return TRUE;
    }

    $this->completeTransaction( $input, $ids, $objects, $transaction, $recur );

    // PS Added
    // If its the first payment being recorded then we need to call the complete DD setup routine
    // All of the data required will now be in place
    if ($first) {
      CRM_DirectDebit_Form_Main::completeDirectDebitSetup( $objects );
    }
  }

  function main($component = 'contribute') {
    $objects            = $ids = $input = array();
    $input['component'] = $component;

    // get the contribution and contact ids from the GET params
    $ids['contact']      = self::retrieve( 'contactID'     , 'Integer', 'GET', TRUE );
    $ids['contribution'] = self::retrieve( 'contributionID', 'Integer', 'GET', TRUE );

    $this->getInput( $input, $ids );

    if ( $component == 'event' ) {
      $ids['event']             = self::getValue( 'e', TRUE  );
      $ids['participant']       = self::getValue( 'p', TRUE  );
      $ids['contributionRecur'] = self::getValue( 'r', FALSE );
    }
    else {
      // get the optional ids
      $ids['membership']          = self::retrieve( 'membershipID'       , 'Integer', 'GET', FALSE );
      $ids['contributionRecur']   = self::retrieve( 'contributionRecurID', 'Integer', 'GET', FALSE );
      $ids['contributionPage']    = self::retrieve( 'contributionPageID' , 'Integer', 'GET', FALSE );
      $ids['related_contact']     = self::retrieve( 'relatedContactID'   , 'Integer', 'GET', FALSE );
      $ids['onbehalf_dupe_alert'] = self::retrieve( 'onBehalfDupeAlert'  , 'Integer', 'GET', FALSE );
      $ids['financial_type_id']   = self::retrieve( 'financial_type_id'  , 'Integer', 'GET', FALSE );
    }

    if ( $ids['membership'] && !$ids['contributionRecur'] ) {
      $sql = <<<EOF
                SELECT m.contribution_recur_id
                FROM   civicrm_membership m
                INNER  JOIN civicrm_membership_payment mp ON m.id = mp.membership_id AND mp.contribution_id = %1
                WHERE  m.id = %2
                LIMIT 1
EOF;

      $sqlParams = array( 1 => array( $ids['contribution'], 'Integer' )
      , 2 => array( $ids['membership']  , 'Integer' )
      );

      $contributionRecurId = CRM_Core_DAO::singleValueQuery( $sql, $sqlParams );
      if ( !empty( $contributionRecurId ) ) {
        $ids['contributionRecur'] = $contributionRecurId;
      }
    }

    $paymentProcessorID = CRM_Core_DAO::getFieldValue(  'CRM_Financial_DAO_PaymentProcessorType'
      , 'Smart_Debit'
      , 'id'
      , 'name'
    );

    if ( !$this->validateData( $input, $ids, $objects, FALSE, $paymentProcessorID ) ) {
      return FALSE;
    }

    self::$_paymentProcessor = &$objects['paymentProcessor'];
    if ( $component == 'contribute' || $component == 'event' ) {
      if ( $ids['contributionRecur'] ) {
        // check if first contribution is completed, else complete first contribution
        $first = TRUE;
        //KJ 13/07/2015 membership contribution comes as status 'completed' before IPN fired.
        if ( ($objects['contribution']->contribution_status_id == 1) && empty($ids['membership']) ) {
          $first = FALSE;
        }
        return $this->recur( $input, $ids, $objects, $first );
      }
      else {
        return $this->single( $input, $ids, $objects, FALSE, FALSE );
      }
    }
    else {
      return $this->single( $input, $ids, $objects, FALSE, FALSE );
    }
  }

  function getInput(&$input, &$ids) {
    if ( !$this->getBillingID( $ids ) ) {
      return FALSE;
    }
    $input['txnType']           = self::retrieve( 'txn_type'        , 'String', 'GET',  FALSE );
    $input['paymentStatus']     = self::retrieve( 'payment_status'  , 'String', 'GET',  FALSE );
    $input['invoice']           = self::retrieve( 'invoice'         , 'String', 'GET',  TRUE  );
    $input['amount']            = self::retrieve( 'mc_gross'        , 'Money' , 'GET',  FALSE );
    $input['reasonCode']        = self::retrieve( 'ReasonCode'      , 'String', 'POST', FALSE );

    $billingID = $ids['billing'];
    $lookup    = array(
      "first_name"                  => 'first_name',
      "last_name"                   => 'last_name',
      "street_address-{$billingID}" => 'address_street',
      "city-{$billingID}"           => 'address_city',
      "state-{$billingID}"          => 'address_state',
      "postal_code-{$billingID}"    => 'address_zip',
      "country-{$billingID}"        => 'address_country_code'
    );
    foreach ( $lookup as $name => $paypalName ) {
      $value        = self::retrieve( $paypalName, 'String', 'POST', FALSE );
      $input[$name] = $value ? $value : NULL;
    }
    $input['collection_day']   = self::retrieve( 'collection_day' , 'Integer', 'GET',  FALSE );
    $input['start_date']   = self::retrieve( 'first_collection_date' , 'String', 'GET',  FALSE );

    $start_date = new DateTime($input['start_date']); // convert UNIX timestamp to PHP DateTime
    $input['start_date'] = $start_date->format('YmdHis');

    $input['is_test']    = self::retrieve( 'test_ipn'     , 'Integer', 'POST', FALSE );
    $input['fee_amount'] = self::retrieve( 'mc_fee'       , 'Money'  , 'POST', FALSE );
    $input['net_amount'] = self::retrieve( 'settle_amount', 'Money'  , 'POST', FALSE );
    $input['trxn_id']    = self::retrieve( 'txn_id'       , 'String' , 'GET', FALSE );
  }
}

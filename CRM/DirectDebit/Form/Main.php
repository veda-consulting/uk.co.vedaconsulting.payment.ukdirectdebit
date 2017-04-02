<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
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
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

/**
 * This class generates form components for processing Event
 *
 */
require_once 'CRM/Core/Form.php';

class CRM_DirectDebit_Form_Main extends CRM_Core_Form
{
  /** create all fields needed for direct debit transaction
   *
   * @return void
   * @access public
   */
  function setDirectDebitFields( &$form ) {
    $form->_paymentFields['account_holder'] = array(
      'htmlType'    => 'text',
      'name'        => 'account_holder',
      'title'       => ts( 'Account Holder' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 18
      , 'autocomplete' => 'on'
      ),
      'is_required' => TRUE
    );

    //e.g. IBAN can have maxlength of 34 digits
    $form->_paymentFields['bank_account_number'] = array(
      'htmlType'    => 'text',
      'name'        => 'bank_account_number',
      'title'       => ts( 'Bank Account Number' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 34
      , 'autocomplete' => 'off'
      ),
      'is_required' => TRUE
    );

    //e.g. SWIFT-BIC can have maxlength of 11 digits
    $form->_paymentFields['bank_identification_number'] = array(
      'htmlType'    => 'text',
      'name'        => 'bank_identification_number',
      'title'       => ts( 'Sort Code' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 11
      , 'autocomplete' => 'off'
      ),
      'is_required' => TRUE
    );

    $form->_paymentFields['bank_name'] = array(
      'htmlType'    => 'text',
      'name'        => 'bank_name',
      'title'       => ts( 'Bank Name' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 64
      , 'autocomplete' => 'off'
      ),
      'is_required' => TRUE
    );

    // Get the collection days options
    $collectionDaysArray = CRM_DirectDebit_Base::getCollectionDaysOptions();

    $form->_paymentFields['preferred_collection_day'] = array(
      'htmlType'    => 'select',
      'name'        => 'preferred_collection_day',
      'title'       => ts( 'Preferred Collection Day' ),
      'cc_field'    => TRUE,
      'attributes'  => $collectionDaysArray, // array('1' => '1st', '8' => '8th', '21' => '21st'),
      'is_required' => TRUE
    );

    $form->_paymentFields['confirmation_method'] = array(
      'htmlType'    => 'select',
      'name'        => 'confirmation_method',
      'title'       => ts( 'Confirm By' ),
      'cc_field'    => TRUE,
      'attributes'  => array( 'EMAIL' => 'Email'
      , 'POST' => 'Post'
      ),
      'is_required' => TRUE
    );

    $form->_paymentFields['payer_confirmation'] = array(
      'htmlType'    => 'checkbox',
      'name'        => 'payer_confirmation',
      'title'       => ts( 'Please confirm that you are the account holder and only person required to authorise Direct Debits from this account' ),
      'cc_field'    => TRUE,
      'is_required' => TRUE
    );

    $form->_paymentFields['ddi_reference'] = array(
      'htmlType'    => 'hidden',
      'name'        => 'ddi_reference',
      'title'       => ts('DDI Reference'),
      'cc_field'    => TRUE,
      'attributes'  => array( 'size'         => 20
      , 'maxlength'    => 64
      , 'autocomplete' => 'off'
      ),
      'is_required' => FALSE,
      'default'     => 'hello'
    );

    $telephoneNumber = CRM_DirectDebit_Base::getTelephoneNumber();
    $form->assign( 'telephoneNumber', $telephoneNumber );

    $companyName = CRM_DirectDebit_Base::getCompanyName();
    $form->assign( 'companyName', $companyName );
  }

  /**
   * Function to add all the direct debit fields
   *
   * @return None
   * @access public
   */
  function buildDirectDebit( &$form, $useRequired = FALSE ) {
    if ( $form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_FORM ) {
      self::setDirectDebitFields( $form );
      foreach ( $form->_paymentFields as $name => $field ) {
        if ( isset($field['cc_field'] ) &&
          $field['cc_field']
        ) {
          if ($field['htmlType'] == 'chainSelect') {
            $form->addChainSelect($field['name'], array('required' => $useRequired && $field['is_required']));
          }
          else {
            $form->add( $field['htmlType'],
              $field['name'],
              $field['title'],
              CRM_Utils_Array::value('attributes', $field),
              $useRequired ? $field['is_required'] : FALSE
            );
          }
        }
      }

      $form->addRule( 'bank_identification_number',
        ts( 'Please enter a valid Bank Identification Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );

      $form->addRule( 'bank_account_number',
        ts( 'Please enter a valid Bank Account Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );
    }

    if ( $form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_BUTTON ) {
      $form->_expressButtonName = $form->getButtonName( $form->buttonType(), 'express' );
      $form->add( 'image',
        $form->_expressButtonName,
        $form->_paymentProcessor['url_button'],
        array( 'class' => 'form-submit' )
      );
    }

    $defaults['ddi_reference'] = CRM_DirectDebit_Base::getDDIReference();
    $form->setDefaults($defaults);
  }

  function buildOfflineDirectDebit(&$form, $useRequired = FALSE) {
    if ( $form->_paymentProcessor['billing_mode'] & CRM_Core_Payment::BILLING_MODE_FORM ) {
      self::setDirectDebitFields( $form );
      self::setBillingDetailsFields($form);
      foreach ( $form->_paymentFields as $name => $field ) {
        if ( isset($field['cc_field'] ) &&
          $field['cc_field']
        ) {
          if ($field['htmlType'] == 'chainSelect') {
            $form->addChainSelect($field['name'], array('required' => $useRequired && $field['is_required']));
          }
          else {
            $form->add( $field['htmlType'],
              $field['name'],
              $field['title'],
              CRM_Utils_Array::value('attributes', $field),
              $useRequired ? $field['is_required'] : FALSE
            );
          }
        }
      }

      $form->addRule( 'bank_identification_number',
        ts( 'Please enter a valid Bank Identification Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );

      $form->addRule( 'bank_account_number',
        ts( 'Please enter a valid Bank Account Number (value must not contain punctuation characters).' ),
        'nopunctuation'
      );
    }

  }

  function setBillingDetailsFields(&$form) {
    $bltID =  $form->_bltID;
    $form->_paymentFields['billing_first_name'] = array(
      'htmlType' => 'text',
      'name' => 'billing_first_name',
      'title' => ts('Billing First Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields['billing_middle_name'] = array(
      'htmlType' => 'text',
      'name' => 'billing_middle_name',
      'title' => ts('Billing Middle Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => FALSE,
    );

    $form->_paymentFields['billing_last_name'] = array(
      'htmlType' => 'text',
      'name' => 'billing_last_name',
      'title' => ts('Billing Last Name'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_street_address-{$bltID}"] = array(
      'htmlType' => 'text',
      'name' => "billing_street_address-{$bltID}",
      'title' => ts('Street Address'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_city-{$bltID}"] = array(
      'htmlType' => 'text',
      'name' => "billing_city-{$bltID}",
      'title' => ts('City'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_state_province_id-{$bltID}"] = array(
      'htmlType' => 'select',
      'title' => ts('State/Province'),
      'name' => "billing_state_province_id-{$bltID}",
      'cc_field' => TRUE,
      'attributes' => array(
          '' => ts('- select -'),
        ) +
        CRM_Core_PseudoConstant::stateProvince(),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_postal_code-{$bltID}"] = array(
      'htmlType' => 'text',
      'name' => "billing_postal_code-{$bltID}",
      'title' => ts('Postal Code'),
      'cc_field' => TRUE,
      'attributes' => array('size' => 30, 'maxlength' => 60, 'autocomplete' => 'off'),
      'is_required' => TRUE,
    );

    $form->_paymentFields["billing_country_id-{$bltID}"] = array(
      'htmlType' => 'select',
      'name' => "billing_country_id-{$bltID}",
      'title' => ts('Country'),
      'cc_field' => TRUE,
      'attributes' => array(
          '' => ts('- select -'),
        ) +
        CRM_Core_PseudoConstant::country(),
      'is_required' => TRUE,
    );
  }
}

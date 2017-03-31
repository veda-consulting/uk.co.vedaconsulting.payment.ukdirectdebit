<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Member/Form/Task.php';
require_once 'CRM/Member/BAO/Membership.php';

/**
 * This class provides the functionality to create a batch
 */
class CRM_Member_Form_Task_RenewMembership extends CRM_Member_Form_Task {

  /**
   * Are we operating in "single mode", i.e. deleting one
   * specific contribution?
   *
   * @var boolean
   */
  protected $_single = false;

  /**
   * Build the form
   *
   * @access public
   * @return void
   */
  function buildQuickForm() {

    $this->add('text', 'description', ts('Description'), array('size' => 60, 'maxlength' => 60), TRUE);
    $this->add('text', 'activity_date', ts('Activity Date'), FALSE, TRUE);
    $this->addDefaultButtons(ts('Renew Membership(s)'), 'done');
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   * @return None
   */
  public function postProcess()
  {
    $expectedEntries = 0;
    $expectedValue = 0;
    $submittedValues = $this->_submitValues;
    $contributionSource = $submittedValues['description'];
    $activityDate      = $submittedValues['activity_date'];

    //Get Direct Debit Payment Instrument
    $paymentInstrument = civicrm_api("OptionValue"
      ,"get"
      , array (version           => '3'
      ,'sequential'      => '1'
      ,'option_group_id' => '10'
      ,'name'            => 'Direct Debit'
      )
    );

    $paymentInstrumentId = $paymentInstrument['values'][0]['value'];
    // Get all the membership types we
    foreach ($this->_memberIds as $memberId) {
      $member = civicrm_api("Membership","get", array ('version' => 3,'id' =>$memberId));
      if(!$member['count']>0) {
        throw new Exception("Membership #$memberId not loaded");
      }
      $numRenewTerms       = 1;
      $membershipTypeID    = $member['values'][$memberId]['membership_type_id'];
      $contributionRecurID = $member['values'][$memberId]['contribution_recur_id'];
      $membershipEndDate   = $member['values'][$memberId]['end_date'];
      $membershipType = civicrm_api("MembershipType","get", array ('version' => '3','id' => $membershipTypeID));
      $membershipFee = $membershipType['values'][$membershipTypeID]['minimum_fee'];

      if (is_null($contributionRecurID)) {
        $changeToday = date('YmdHis');
        unset($dates);
        // CRM-7297 Membership Upsell - calculate dates based on new membership type
        $dates = CRM_Member_BAO_MembershipType::getRenewalDatesForMembershipType($memberId,
          $changeToday,
          $membershipTypeID,
          $numRenewTerms
        );

        unset($memParams);
        // Insert renewed dates for CURRENT membership
        $memParams = array();
        $memParams['end_date'] = CRM_Utils_Array::value('end_date', $dates);
        $memParams['reminder_date'] = CRM_Utils_Array::value('reminder_date', $dates);

        $updatedMember = civicrm_api("Membership"
          ,"create"
          , array ('version'       => '3',
            'membership_id' => $memberId,
            'id'            => $memberId,
            'end_date'      => $memParams['end_date'],
            'reminder_date' => $memParams['reminder_date'],
          )
        );
      }

      $contributionReceiveDate = date('YmdHis', strtotime($activityDate));
      // Create the contribution
      $contribution = civicrm_api("Contribution"
        ,"create"
        ,array ( 'version'                => '3'
        , 'contact_id'             => $member['values'][$memberId]['contact_id']
        , 'total_amount'           => $membershipFee
        , 'source'                 => $contributionSource
        , 'payment_instrument_id'  => $paymentInstrumentId
        , 'contribution_type_id'   => '2'
        , 'receive_date'           => $contributionReceiveDate
        , 'contribution_status_id' => '1'));

      if ($contributionRecurID) {
        $contributionRecurring = civicrm_api("ContributionRecur"
          ,"get"
          , array ('version' => '3'
          ,'id'      => $contributionRecurID
          )
        );

        // Check if Membership End Date has been updated
        $getMembership = civicrm_api("Membership"
          ,"get"
          , array ('version'       => '3'
          ,'membership_id' => $memberId
          )
        );

        $currentMembershipEndDate = $getMembership['values'][$memberId]['end_date'];
        // If end date hasn't changed then renew it
        if ($currentMembershipEndDate == $membershipEndDate) {
          $frequencyUnit = $contributionRecurring['values'][$contributionRecurID]['frequency_unit'];
          if (!is_null($frequencyUnit)) {
            $newMembershipEndDate = date("Y-m-d",strtotime(date("Y-m-d", strtotime($membershipEndDate)) . " +1 $frequencyUnit"));
            $updatedMember = civicrm_api("Membership"
              ,"create"
              , array ('version'       => '3',
                'membership_id' => $memberId,
                'id'            => $memberId,
                'end_date'      => $newMembershipEndDate,
              )
            );
          }
        }
      }

      if (is_null($contributionRecurID)) {
        // Then link it to the membership
        $membershipPayment = civicrm_api("MembershipPayment"
          ,"create"
          , array ( 'version'         => '3'
          , 'membership_id'   => $memberId
          , 'contribution_id' => $contribution['id']));
      }
      $expectedValue += $membershipFee;
      $expectedEntries++;
    }

    $status = array('Completed Successfully',
      ts('Expected to Renew: %1', array(1 => $expectedEntries)),
      ts('Actual Renewed: %1', array(1 => $expectedEntries)),
      ts('Total Contribution(s) created: %1', array(1 => $expectedValue)),
    );
    CRM_Core_Session::setStatus($status);
  }
}

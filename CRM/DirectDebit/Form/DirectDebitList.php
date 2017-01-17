<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
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

require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/Session.php';

/**
 * This class provides the functionality to delete a group of
 * contacts. This class provides functionality for the actual
 * addition of contacts to groups.
 */

class CRM_DirectDebit_Form_DirectDebitList extends CRM_Core_Form {

    /**
     * build all the data structures needed to build the form
     *
     * @return void
     * @access public
     */
    function preProcess()
  {
        parent::preProcess( );
        return false;

    }

    /**
     * Build the form
     *
     * @access public
     * @return void
     */
    function buildQuickForm( ) {

        require_once 'CRM/Core/Config.php';
        $config = CRM_Core_Config::singleton();
        
        //get direct debit setting names
        $settingNames = self::getDirectDebitSettingNames();
        $directDebitArray = array();
        foreach ($settingNames as $settingName) {
          //get id and value for this setting name
          list($id, $value) = self::getIdAndValueFromSettingName($settingName);
          $directDebitArray[$id]['id'] = $id;
          $directDebitArray[$id]['name'] = $settingName;
          $directDebitArray[$id]['value'] = unserialize($value);
        }
        
        $this->assign( 'directDebitArray', $directDebitArray );

            $this->addButtons( array(
                                       array ( 'type'      => 'upload',
                                               'name'      => ts('Post'),
                                               'subName'   => 'post',
                                               'isDefault' => false   ),
                                                ) );

    }


    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    public function postProcess() {

    }//end of function
    
    
    public static function getDirectDebitSettingNames() {
      // As from civi 4.7, there is no 'group_name' in civicrm_setting table, we have to find it manually
      $settingNames = array(
        'activity_type',
        'activity_type_letter',
        'api_contact_key',
        'api_contact_val_regex',
        'api_contact_val_regex_index',
        'auto_renew_membership',
        'collection_days',
        'collection_interval',
        'company_address1',
        'company_address2',
        'company_address3',
        'company_address4',
        'company_county',
        'company_name',
        'company_postcode',
        'company_town',
        'domain_name',
        'email_address',
        'financial_type',
        'payment_instrument_id',
        'service_user_number',
        'telephone_number',
        'transaction_prefix',
        'notify_days');
      
      return $settingNames;
    }
    
    public static function getIdAndValueFromSettingName($settingName) {
      if (empty($settingName)) {
        CRM_Core_Error::debug_var('CRM_DirectDebit_Form_DirectDebitList getIdAndValueFromSettingName', 'Provided setting name is empty');
        return;
      }
      
      $sql  = " SELECT id ";
      $sql .= " ,      name ";
      $sql .= " ,      value ";
      $sql .= " FROM civicrm_setting ";
      $sql .= " WHERE name = %1 ";

      $params = array( 1 => array( $settingName, 'String' ) );
      $dao = CRM_Core_DAO::executeQuery( $sql, $params);
      if ($dao->fetch()) {
        return array($dao->id, $dao->value);
      } else {
        CRM_Core_Error::debug_var('CRM_DirectDebit_Form_DirectDebitList getIdAndValueFromSettingName', 'No data found for setting name'.$settingName);
      }
    }
        
}

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

        $group_name = "UK Direct Debit";

        $sql  = " SELECT id ";
        $sql .= " ,      name ";
        $sql .= " ,      value ";
        $sql .= " FROM civicrm_setting ";
        $sql .= " WHERE group_name = %1 ";

        $params = array( 1 => array( $group_name, 'String' ) );
        $dao = CRM_Core_DAO::executeQuery( $sql, $params);

        $directDebitArray = array();

        while($dao->fetch()) {

            $directDebitArray[$dao->id]['id'] = $dao->id;
            $directDebitArray[$dao->id]['name'] = $dao->name;
            $directDebitArray[$dao->id]['value'] = unserialize($dao->value);
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
        
}

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
require_once 'CRM/Core/SelectValues.php';
require_once 'CRM/Core/Form.php';

/**
 * This class generates form components for DirectDebit
 * 
 */
class CRM_DirectDebit_Form_DirectDebit extends CRM_Core_Form
{

    function preProcess( ) 
    {  

    }

    function buildQuickForm() { 
    
        $id = CRM_Utils_Request::retrieve( 'sid', 'Integer', $this );
        if (empty($id))
            $id = CRM_Utils_Array::value('sid', $_POST, '');

        CRM_Utils_System::setTitle( 'Edit Bank Account' );
        
        $sql  = " SELECT id ";
        $sql .= " ,      name ";
        $sql .= " ,      value ";
        $sql .= " FROM civicrm_setting ";
        $sql .= " WHERE id = %1 ";

        $params = array( 1 => array( $id, 'Int' ) );
        $dao = CRM_Core_DAO::executeQuery( $sql, $params);

        $directDebitArray = array();

        if($dao->fetch()) {
            $defaults = array(
                              'setting_id'=>$dao->id ,
                              'setting_name'=>$dao->name,
                              'setting_value'=>$dao->value,
                              );
        }

        $this->assign('id', $id );
        $this->setDefaults( $defaults );

        $this->add( 'text', 'setting_id', ts('Id'), $attributes['label'], true );

        $this->add( 'text', 'setting_name', ts('Name'), $attributes['label'], true );                    
                    
        $this->add( 'text', 'setting_value', ts('Value'), $attributes['label'], true );
            
        $this->setDefaults( $defaults );
                                   
        $this->addButtons(array( 
                                array ( 'type'      => 'next', 
                                        'name'      => ts('Save'), 
                                        'spacing'   => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 
                                        'isDefault' => true   ), 
                                ) 
                         );
    }
    
    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    public function postProcess() {

        $params = $this->controller->exportValues( );
        
        $settingId   = $params['setting_id'];
        $settingName = $params['setting_name'];
        $settingValue   = $params['setting_value'];
                
        $update_sql  = " UPDATE civicrm_setting ";
        $update_sql .= " SET name = %0 ";
        $update_sql .= " ,   value = %1 ";
        $update_sql .= " WHERE id = %2 ";

        CRM_Core_DAO::executeQuery($update_sql, array(
            array((string)$settingName,  'String'),
            array((string)$settingValue, 'String'),
            array((string)$settingId,    'Integer'),
        )); 

        $status = ts('Setting Updated');        
        CRM_Core_Session::setStatus( $status );

        drupal_goto( 'civicrm/directdebit/display' , 'reset=1' );

    } //end of function    

}



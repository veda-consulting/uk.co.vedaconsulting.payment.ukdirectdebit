<?php

/**
 * Smart Debit to CiviCRM Sync
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_ukdirectdebit_sync($params) {
  $result = array();
  $runner = CRM_DirectDebit_Form_Sync::getRunner($params);
  if ($runner) {
    $result = $runner->runAll();
  }

  if ($result['is_error'] == 0) {
    return civicrm_api3_create_success();
  }
  else {
    return civicrm_api3_create_error();
  }
}

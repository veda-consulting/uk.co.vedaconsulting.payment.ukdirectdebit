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
  $runner = CRM_DirectDebit_Sync::getRunner(FALSE);
  if ($runner) {
    $result = $runner->runAll();
  }

  if ($result && !isset($result['is_error'])) {
    return civicrm_api3_create_success();
  }
  else {
    $msg = '';
    if (isset($result)) {
      $msg .= $result['exception']->getMessage() . '; ';
    }
    if (isset($result['last_task_title'])) {
      $msg .= $result['last_task_title'] .'; ';
    }
    return civicrm_api3_create_error($msg);
  }
}

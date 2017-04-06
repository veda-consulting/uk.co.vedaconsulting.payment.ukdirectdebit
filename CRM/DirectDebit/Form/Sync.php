<?php

/**
 * Class CRM_DirectDebit_Form_Sync
 * This form is accessed at civicrm/directdebit/sync
 * It shows the results of the Smart Debit Sync Scheduled job
 */
class CRM_DirectDebit_Form_Sync extends CRM_Core_Form
{
  function preProcess()
  {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats = uk_direct_debit_civicrm_getSetting('sd_stats');
      $total = uk_direct_debit_civicrm_getSetting('total');
      $stats['Total'] = $total;
      $this->assign('stats', $stats);
    }
  }

  public function buildQuickForm()
  {
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Run Smart Debit Sync now'),
      ),
    );
    // Add the Buttons.
    $this->addButtons($buttons);

    $this->setTitle('Smart Debit Sync Scheduled Job');
  }

  public function postProcess()
  {
    $financialType = uk_direct_debit_civicrm_getSetting('financial_type');
    if (empty($financialType)) {
      CRM_Core_Session::setStatus(ts('Make sure financial Type is set in the setting'), 'UK Direct Debit', 'error');
      return FALSE;
    }
    $runner = CRM_DirectDebit_Sync::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to pull. Make sure smart debit settings are correctly configured in the payment processor setting page'));
    }
  }
}

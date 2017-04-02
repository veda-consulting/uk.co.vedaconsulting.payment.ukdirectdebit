<?php

class CRM_DirectDebit_Form_Auddisactivity extends CRM_Core_Form {
  public $_processedAuddis;

  function preProcess() {
    $auddisDetails = array();
    $auddisDates = array();

    // Get all auddis files from the API
    $auddisArray = CRM_DirectDebit_Auddis::getSmartDebitAuddis();
    // Get the auddis Dates from the Auddis Files
    if($auddisArray) {
      foreach ($auddisArray as $auddis) {
        if (array_key_exists('auddis_id', $auddis)) {
          $auddisDetails['auddis_id']              = $auddis['auddis_id'];
          $auddisDetails['report_generation_date'] = substr($auddis['report_generation_date'], 0, 10);
          $auddisDates[]                           = substr($auddis['report_generation_date'], 0, 10);
        }
      }
    }
    $processedAuddisDates = array();
    if($auddisDates) {
      foreach ($auddisDates as $auddisDate) {
        $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $auddisDate, 'details', 'subject');
        if($details) {
          $processedAuddisDates[] = $auddisDate;
        }
      }
    }

    // Show only the valid auddis dates in the multi select box
    $auddisDates = array_diff($auddisDates, $processedAuddisDates);
    $this->_processedAuddis = serialize($auddisDates);

    parent::preProcess();
  }

  public function buildQuickForm() {
    if(!empty($this->_processedAuddis)) {
      $auddisDates      = unserialize($this->_processedAuddis);

      if (count($auddisDates) <= 10) {
        // setting minimum height to 2 since widget looks strange when size (height) is 1
        $groupSize = max(count($auddisDates), 2);
      }
      else {
        $groupSize = 10;
      }

      $auddisDates = array_combine($auddisDates, $auddisDates);

      $this->addElement('advmultiselect', 'includeAuddisDate',
        ts('Include Auddis Date(s)') . ' ',
        $auddisDates,
        array(
          'size' => $groupSize,
          'style' => 'width:auto; min-width:240px;',
          'class' => 'advmultiselect',
        )
      );

      $this->assign('groupCount', count($auddisDates));
      $buttons = array();
      if (count($auddisDates) > 0) {
        $buttons[] = array(
          'type' => 'submit',
          'name' => ts('Process'),
          'isDefault' => TRUE,
        );
      }
      $buttons[] = array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      );
    }
    $this->addButtons($buttons);

    parent::buildQuickForm();
  }

  function postProcess() {
    $params = $this->controller->exportValues();
    $auddisDates = $params['includeAuddisDate'];

    // Get the already processed Auddis Dates
    foreach ($auddisDates as $auddisDate) {
      $params = array(
        'version' => 3,
        'sequential' => 1,
        'activity_type_id' => 6,
        'subject' => $auddisDate,
        'details' => 'Sync had been processed already for this date '.$auddisDate,
      );
      $result = civicrm_api('Activity', 'create', $params);
    }
    CRM_Core_Session::setStatus('The selected auddis dates have been processed','Auddis Activity', 'Info');
    CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/syncsd/activity', 'reset=1'));
  }
}

<?php
require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/Session.php';
require_once 'CRM/Core/PseudoConstant.php';

class CRM_DirectDebit_Form_SyncSd extends CRM_Core_Form {

  function preProcess() {
    parent::preProcess();
  }

  public function buildQuickForm() {
    $auddisDetails    = array();
    $aruddDetails    = array();
    $auddisDates      = array();
    $aruddDates      = array();

    // Get all auddis files from the API
    $auddisArray = CRM_DirectDebit_Auddis::getSmartDebitAuddis();
    $aruddArray = CRM_DirectDebit_Auddis::getSmartDebitArudd();

    // Get the auddis Dates from the Auddis Files
    if($auddisArray) {
      if (isset($auddisArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($auddisArray as $key => $auddis) {
          $auddisDetails['auddis_id']              = $auddis['auddis_id'];
          $auddisDetails['report_generation_date'] = date('Y-m-d', strtotime($auddis['report_generation_date']));
          $auddisDates[]                           = date('Y-m-d', strtotime($auddis['report_generation_date']));
          $auddisDetails['uri']                    = $auddis['@attributes']['uri'];
        }
      } else {
        // Only one result returned
        $auddisDetails['auddis_id']              = $auddisArray['auddis_id'];
        $auddisDetails['report_generation_date'] = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
        $auddisDates[]                           = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
        $auddisDetails['uri']                    = $auddisArray['@attributes']['uri'];
      }
    }

    // Get the arudd Dates from the Arudd Files
    if($aruddArray) {
      if (isset($aruddArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($aruddArray as $key => $arudd) {
          $aruddDetails['arudd_id']              = $arudd['arudd_id'];
          $aruddDetails['current_processing_date'] = date('Y-m-d', strtotime($arudd['current_processing_date']));
          $aruddDates[]                           = date('Y-m-d', strtotime($arudd['current_processing_date']));
          $aruddDetails['uri']                    = $arudd['@attributes']['uri'];
        }
      } else {
        // Only one result returned
        $aruddDetails['arudd_id']              = $aruddArray['arudd_id'];
        $aruddDetails['current_processing_date'] = date('Y-m-d', strtotime($aruddArray['current_processing_date']));
        $aruddDates[]                           = date('Y-m-d', strtotime($aruddArray['current_processing_date']));
        $aruddDetails['uri']                    = $aruddArray['@attributes']['uri'];
      }
    }

    // Get the already processed Auddis Dates
    $processedAuddisDates = array();
    if($auddisDates) {
      foreach ($auddisDates as $auddisDate) {
        $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $auddisDate, 'details', 'subject');
        if($details) {
          $processedAuddisDates[] = $auddisDate;
        }
      }
    }

    $processedAruddDates = array();
    if($aruddDates) {
      foreach ($aruddDates as $aruddDate) {
        $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', 'ARUDD'.$aruddDate, 'details', 'subject');
        if($details) {
          $processedAruddDates[] = $aruddDate;
        }
      }
    }

    // Show only the valid auddis dates in the multi select box
    $auddisDates = array_diff($auddisDates, $processedAuddisDates);
    $aruddDates = array_diff($aruddDates, $processedAruddDates);
    // Check if array to not empty, to avoid warning
    if (!empty($auddisDates)) {
      $auddisDates = array_combine($auddisDates, $auddisDates);
    }
    if (!empty($aruddDates)) {
      $aruddDates = array_combine($aruddDates, $aruddDates);
    }

    if (count($auddisDates) <= 10) {
      // setting minimum height to 2 since widget looks strange when size (height) is 1
      $groupSize = max(count($auddisDates), 2);
    }
    else {
      $groupSize = 10;
    }

    if (count($aruddDates) <= 10) {
      // setting minimum height to 2 since widget looks strange when size (height) is 1
      $groupSizeArudd = max(count($aruddDates), 2);
    }
    else {
      $groupSizeArudd = 10;
    }

    $inG = &$this->addElement('advmultiselect', 'includeAuddisDate',
      ts('Include Auddis Date(s)') . ' ',
      $auddisDates,
      array(
        'size' => $groupSize,
        'style' => 'width:auto; min-width:240px;',
        'class' => 'advmultiselect',
      )
    );

    $inGarudd = &$this->addElement('advmultiselect', 'includeAruddDate',
      ts('Include Arudd Date(s)') . ' ',
      $aruddDates,
      array(
        'size' => $groupSizeArudd,
        'style' => 'width:auto; min-width:240px;',
        'class' => 'advmultiselect',
      )
    );

    $this->assign('groupCount', count($auddisDates));
    $this->assign('groupCountArudd', count($aruddDates));

    $auddisDatesArray = array('' => ts('- select -'));
    $aruddDatesArray = array('' => ts('- select -'));
    if (!empty($auddisDates)) {
      $auddisDatesArray = $auddisDatesArray + $auddisDates;
    }
    if (!empty($aruddDates)) {
      $aruddDatesArray = $aruddDatesArray + $aruddDates;
    }
    $this->addElement('select', 'auddis_date', ts('Auddis Date'), $auddisDatesArray);
    $this->addElement('select', 'arudd_date', ts('Arudd Date'), $aruddDatesArray);
    $this->addDate('sync_date', ts('Sync Date'), FALSE, array('formatType' => 'custom'));
    $this->addButtons(array(
        array(
          'type' => 'next',
          'name' => ts('Continue'),
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'back',
          'name' => ts('Cancel'),
        )
      )
    );
    parent::buildQuickForm();
  }

  function postProcess() {
    $params = $this->controller->exportValues();
    $auddisDates = $params['includeAuddisDate'];
    $aruddDates = $params['includeAruddDate'];

    // Make the query string to send in the url for the next page
    $queryDates = '';
    foreach ($auddisDates as $value) {
      $queryDates .= "auddisDates[]=".$value."&";
    }

    // Make the query string to send in the url for the next page
    $queryDatesArudd = '';
    foreach ($aruddDates as $value) {
      $queryDatesArudd .= "aruddDates[]=".$value."&";
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/auddis', ''.$queryDates.$queryDatesArudd. '&reset=1'));
    parent::postProcess();
  }
}

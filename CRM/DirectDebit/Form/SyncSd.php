<?php
require_once 'CRM/Core/Form.php';
require_once 'CRM/Core/Session.php';
require_once 'CRM/Core/PseudoConstant.php';

class CRM_DirectDebit_Form_SyncSd extends CRM_Core_Form {
  // Form Path: civicrm/directdebit/syncsd
  // This form retrieves a list of AUDDIS / ARUDD dates and displays them for selection.

  private $_auddisArray = NULL;
  private $_aruddArray = NULL;
  /**
   * Retrieves a list of available AUDDIS / ARUDD dates and displays them for selection
   */
  public function buildQuickForm() {
    // Get all auddis files from the API
    $auddisDates      = array();
    $aruddDates      = array();

    // Get date of collection (or set to today if not set)
    $dateOfCollectionEnd = CRM_Utils_Request::retrieve('c_end', 'String', $this, false);
    if (empty($dateOfCollectionEnd)) {
      $now = new DateTime();
      $dateOfCollectionEnd = date('Y-m-d', (string)$now->getTimestamp()); // Today
    }
    $dateOfCollectionStart = date('Y-m-d', strtotime($dateOfCollectionEnd . '-1 month'));
    $this->assign('dateOfCollectionEnd', $dateOfCollectionEnd);
    $this->assign('dateOfCollectionStart', $dateOfCollectionStart);

    $this->_auddisArray = CRM_DirectDebit_Auddis::getSmartDebitAuddisList($dateOfCollectionStart, $dateOfCollectionEnd);
    $this->_aruddArray = CRM_DirectDebit_Auddis::getSmartDebitAruddList($dateOfCollectionStart, $dateOfCollectionEnd);

    if (($this->_auddisArray) && isset($this->_auddisArray['Status']) && ($this->_auddisArray['Status'] == 'OK')) {
    // Get the auddis Dates from the Auddis Files
      if (isset($this->_auddisArray['@attributes']['results']) && ($this->_auddisArray['@attributes']['results'] > 0)) {
        // Multiple results returned
        foreach ($this->_auddisArray['auddis'] as $key => $auddis) {
          $auddisDates[]                           = date('Y-m-d', strtotime($auddis['report_generation_date']));
        }
      } /*else {
        // Only one result returned
        // FIXME find an example where 1 result is returned and see if this is correct
        $auddisDates[]                           = date('Y-m-d', strtotime($auddisArray['report_generation_date']));
      }*/
    }

    // Get the arudd Dates from the Arudd Files
    if($this->_aruddArray) {
      if (isset($this->_aruddArray[0]['@attributes'])) {
        // Multiple results returned
        foreach ($this->_aruddArray as $key => $arudd) {
          $aruddDates[]                           = date('Y-m-d', strtotime($arudd['current_processing_date']));
        }
      } else {
        // Only one result returned
        // FIXME find an example where 1 result is returned and see if this is correct
        $aruddDates[]                           = date('Y-m-d', strtotime($this->_aruddArray['current_processing_date']));
      }
    }

    // Get the already processed Auddis Dates
    $processedAuddisDates = array();
    if($auddisDates) {
      foreach ($auddisDates as $auddisDate) {
        $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', 'SmartDebitAUDDIS'.$auddisDate, 'details', 'subject');
        if($details) {
          $processedAuddisDates[] = $auddisDate;
        }
      }
    }

    $processedAruddDates = array();
    if($aruddDates) {
      foreach ($aruddDates as $aruddDate) {
        $details    = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', 'SmartDebitARUDD'.$aruddDate, 'details', 'subject');
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
    $this->assign('dateOfCollectionStart', $dateOfCollectionStart);
    $this->assign('dateOfCollectionEnd', $dateOfCollectionEnd);

    $redirectUrlBack = CRM_Utils_System::url('civicrm/directdebit/syncsd/import', 'reset=1');

    $this->addButtons(array(
        array(
          'type' => 'back',
          'js' => array('onclick' => "location.href='{$redirectUrlBack}'; return false;"),
          'name' => ts('Change Date Range'),
        ),
        array(
          'type' => 'submit',
          'name' => ts('Continue'),
          'isDefault' => TRUE,
        ),
      )
    );
    CRM_Utils_System::setTitle('Synchronise CiviCRM with Smart Debit: Choose data');
    parent::buildQuickForm();
  }

  function postProcess() {
    $params = $this->controller->exportValues();
    $auddisDates = $params['includeAuddisDate'];
    $aruddDates = $params['includeAruddDate'];

    // Make the query string to send in the url for the next page
    $queryParams = '';
    foreach ($auddisDates as $date) {
      // Find auddis ID
      if (isset($this->_auddisArray['@attributes']['results']) && ($this->_auddisArray['@attributes']['results'] > 0)) {
        foreach ($this->_auddisArray['auddis'] as $key => $auddis) {
          if ($date == date('Y-m-d', strtotime($auddis['report_generation_date']))) {
            $auddisIDs[] = $auddis['auddis_id'];
            break;
          }
        }
      }
    }

    foreach ($aruddDates as $date) {
      // Find arudd ID
      if (isset($this->_aruddArray[0]['@attributes'])) {
        foreach ($this->_aruddArray as $key => $arudd) {
          if ($date == date('Y-m-d', strtotime($arudd['current_processing_date']))) {
            $aruddIDs[] = $arudd['arudd_id'];
            break;
          }
        }
      }
    }

    if (!empty($queryParams)) { $queryParams.='&'; }
    if (isset($auddisIDs)) {
      $queryParams .= "auddisID=" . urlencode(implode(',',$auddisIDs));
    }

    if (!empty($queryParams)) { $queryParams.='&'; }
    if (isset($aruddIDs)) {
      $queryParams .= "aruddID=" . urlencode(implode(',',$aruddIDs));
    }

    if (!empty($queryParams)) { $queryParams.='&'; }
    $queryParams .= 'reset=1';

    CRM_Utils_System::redirect(CRM_Utils_System::url( 'civicrm/directdebit/auddis', $queryParams));
    parent::postProcess();
  }
}

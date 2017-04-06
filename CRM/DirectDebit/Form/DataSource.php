<?php

class CRM_DirectDebit_Form_DataSource extends CRM_Core_Form {
  // DataSource Form
  // Path: civicrm/directdebit/syncsd
  // This is the first step of the import and allows the user to select a collection date window of one month (specifying the end date)

  public function buildQuickForm() {
    #MV: to get the collection details
    $this->add('datepicker', 'collection_date', ts('Collection Date'), NULL, FALSE, NULL);
    $this->addButtons(array(
        array(
          'type' => 'submit',
          'name' => ts('Continue'),
          'isDefault' => TRUE,
        ),
      )
    );
    CRM_Utils_System::setTitle('Synchronise CiviCRM with Smart Debit: Choose Date Range');
  }

  /**
   * Process the collection report
   *
   * @return void
   * @access public
   */
  public function postProcess() {
    $exportValues     = $this->controller->exportValues();
    $dateOfCollection = $exportValues['collection_date'];

    $queryParams='';
    // Set collection date, otherwise we'll default to todays date
    if (!empty($dateOfCollection)) {
      $dateOfCollection = date('Y-m-d', strtotime($dateOfCollection));
      $queryParams.='collection_date='.urlencode($dateOfCollection);
    }

    $collections = CRM_DirectDebit_Auddis::getSmartDebitCollectionReportForMonth( $dateOfCollection );
    if (!isset($collections['error'])) {
      CRM_DirectDebit_Auddis::saveSmartDebitCollectionReport($collections);
    }

    if (!empty($queryParams)){
      $queryParams.='&';
    }
    $queryParams.='reset=1';
    $url = CRM_Utils_System::url('civicrm/directdebit/syncsd/select', $queryParams); // SyncSD form
    CRM_Utils_System::redirect($url);
  }
}


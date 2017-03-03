<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_DirectDebit_Form_Settings extends CRM_Core_Form {
    private $_settingFilter = array('group' => 'ukdirectdebit');
    private $_submittedValues = array();
    private $_settings = array();

    public static function getSettingsPrefix() {
      return 'ukdirectdebit_';
    }

    function buildQuickForm() {
      parent::buildQuickForm();

      CRM_Utils_System::setTitle(ts('UK Direct Debit - Settings'));

      $settings = $this->getFormSettings();

      $this->addSelect('payment_instrument_id',
        array(
          'entity' => 'contribution',
          'label' => ts('Payment Method'),
          'placeholder'  => NULL,
        )
      );
      $this->addSelect('financial_type',
        array(
          'entity' => 'contribution',
          'label' => ts('Financial Type'),
          'placeholder'  => NULL,
        )
      );

      $this->addElement('select', 'activity_type', ts('Activity Type (Sign Up)'), array('' => ts('- select -')) + CRM_Core_OptionGroup::values('activity_type'));
      $this->addElement('select', 'activity_type_letter', ts('Activity Type (Letter)'), array('' => ts('- select -')) + CRM_Core_OptionGroup::values('activity_type'));
      $this->addElement('select', 'collection_frequency', ts('Collection Frequency'), array(0 => "Yearly", 1 => "Quarterly", 2 => "Monthly"));

      foreach ($settings['values'] as $name => $setting) {
            if (isset($setting['type'])) {
                Switch ($setting['type']) {
                  case 'String':
                    if ($name != 'ukdirectdebit_activity_type_letter') {
                    $this->addElement('text', self::getSettingName($name), ts($setting['description']), $setting['html_attributes'], array()); }
                    break;
                  case 'Boolean':
                    $this->addElement('checkbox', self::getSettingName($name), ts($setting['description']), '', '');
                    break;
                }
            }
        }
        $this->addButtons(array(
            array (
                'type' => 'submit',
                'name' => ts('Submit'),
                'isDefault' => TRUE,
            ),
            array (
                'type' => 'cancel',
                'name' => ts('Cancel'),
            )
        ));

        // export form elements
        $this->assign('elementNames', $this->getRenderableElementNames());

    }

    function postProcess() {
        $this->_submittedValues = $this->exportValues();
        // boolean fields are unset if false but we need them to save
        if (!isset($this->_submittedValues['auto_renew_membership'])) {
          $this->_submittedValues['auto_renew_membership'] = '0';
        }
        $this->saveSettings();
        parent::postProcess();
        CRM_Core_Session::singleton()->setStatus('Configuration Updated', 'UK Direct Debit', 'success');
    }

    /**
     * Get the fields/elements defined in this form.
     *
     * @return array (string)
     */
    function getRenderableElementNames() {
        // The _elements list includes some items which should not be
        // auto-rendered in the loop -- such as "qfKey" and "buttons". These
        // items don't have labels. We'll identify renderable by filtering on
        // the 'label'.
        $elementNames = array();
        foreach ($this->_elements as $element) {
            $label = $element->getLabel();
            if (!empty($label)) {
                $elementNames[] = $element->getName();
            }
        }
        return $elementNames;
    }

    /**
     * Get the settings we are going to allow to be set on this form.
     *
     * @return array
     */
    function getFormSettings() {
        $settings = civicrm_api3('setting', 'getfields', array('filters' => $this->_settingFilter));
        return $settings;
    }

    /**
     * Get the settings we are going to allow to be set on this form.
     *
     * @return array
     */
    function saveSettings() {
        $settings = $this->getFormSettings();

        $appendedValues=array();
        foreach ($this->_submittedValues as $key => $value) {
          $appendedValues[self::getSettingsPrefix().$key] = $value;
        }
        $values = array_intersect_key($appendedValues, $settings['values']);
        civicrm_api3('setting', 'create', $values);
    }

    /**
     * Set defaults for form.
     *
     * @see CRM_Core_Form::setDefaultValues()
     */
    function setDefaultValues() {
        $settings = $this->getFormSettings();
        $values = $settings['values'];
        $existing = civicrm_api3('setting', 'get', array('return' => array_keys($values)));
        $defaults = array();
        $domainID = CRM_Core_Config::domainID();
        foreach ($existing['values'][$domainID] as $name => $value) {
            $defaults[self::getSettingName($name)] = $value;
        }
        return $defaults;
    }

    /*
     * Get name of setting
     * @param: setting name
     * @prefix: Boolean
     */
    public static function getSettingName($name, $prefix = false) {
      $ret = str_replace(self::getSettingsPrefix(),'',$name);
      if ($prefix) {
        $ret = self::getSettingsPrefix().$ret;
      }
      return $ret;
    }
}
<?php

return array(

//payment_instrument_id
  'ukdirectdebit_payment_instrument_id' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_payment_instrument_id',
    'type' => 'Integer',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Direct Debit Payment Method',
    'html_attributes' => array(),
  ),

//financial_type
  'ukdirectdebit_financial_type' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_financial_type',
    'type' => 'Integer',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Direct Debit Financial Type',
    'html_attributes' => array(),
  ),

//activity_type
  'ukdirectdebit_activity_type' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_activity_type',
    'type' => 'Integer',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Direct Debit Activity Type (Sign Up)',
    'html_attributes' => array(),
  ),

//activity_type_letter
  'ukdirectdebit_activity_type_letter' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_activity_type_letter',
    'type' => 'String',
    'html_type' => 'Select',
    'default' => 0,
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Direct Debit Activity Type (Letter)',
    'html_attributes' => array(),
  ),

//collection_interval
  'ukdirectdebit_collection_interval' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_collection_interval',
    'type' => 'String',
    'html_type' => 'String',
    'default' => 15,
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Direct Debit Collection Interval',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//collection_days
  'ukdirectdebit_collection_days' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_collection_days',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '1,8,22',
    'description' => 'Direct Debit Collection Days',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//service_user_number
  'ukdirectdebit_service_user_number' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_service_user_number',
    'type' => 'String',
    'default' => '',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Direct Debit Service User Number (SUN)',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//company_name
  'ukdirectdebit_company_name' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_name',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Company Name',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),
//telephone_number
  'ukdirectdebit_telephone_number' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_telephone_number',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Telephone Number',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//email_address
  'ukdirectdebit_email_address' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_email_address',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Email Address',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//domain_name
  'ukdirectdebit_domain_name' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_domain_name',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Domain Name',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//company_address1
  'ukdirectdebit_company_address1' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_address1',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company Address (Line 1)',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),

  ),

//company_address2
  'ukdirectdebit_company_address2' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_address2',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company Address (Line 2)',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),

  ),

//company_address3
  'ukdirectdebit_company_address3' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_address3',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company Address (Line 3)',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),

  ),

//company_address4
  'ukdirectdebit_company_address4' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_address4',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company Address (Line 4)',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),

  ),

//company_town
  'ukdirectdebit_company_town' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_town',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company Town',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),

  ),

//company_county
  'ukdirectdebit_company_county' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_county',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company County',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),

  ),

//company_postcode
  'ukdirectdebit_company_postcode' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_company_postcode',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Company Postcode',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//transaction_prefix
  'ukdirectdebit_transaction_prefix' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_transaction_prefix',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => '',
    'description' => 'Direct Debit Transaction Prefix',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),

//api_contact_key
  'ukdirectdebit_api_contact_key' => array(
    'group_name' => 'UK Direct Debit Settings',
    'group' => 'ukdirectdebit',
    'name' => 'ukdirectdebit_api_contact_key',
    'type' => 'String',
    'add' => '4.7',
    'is_domain' => 1,
    'is_contact' => 0,
    'default' => 'payerReference',
    'description' => 'Direct Debit API Contact Key',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
  ),
);

{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
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
*}
{crmRegion name="billing-block"}
{if $paymentProcessor.payment_processor_type == 'Gocardless'}
  <div id="gocardless_payment_information">
    <fieldset class="billing_mode-group">
      <legend>
	{ts}Direct Debit Information{/ts}
      </legend>
      <div class="crm-section billing_mode-section gocardless-section">
	<div class="crm-section {$form.preferred_collection_day.name}-section">
	  <div class="label">{$form.preferred_collection_day.label}</div>
	  <div class="content">{$form.preferred_collection_day.html}</div>
	  <div class="clear"></div>
	</div>
      </div>
    </fieldset>
  </div>
{/if}
<div id="payment_information">
  {if $paymentFields|@count}
    <fieldset class="billing_mode-group {$paymentTypeName}_info-group">
      <legend>
        {$paymentTypeLabel}
      </legend>
      <!-- MV: Custom changes from older version,  -->
          {if $paymentProcessor.payment_type & 2}
          <div><span style="float: right;margin: 25px;"><img src="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit.gif}" alt="Direct Debit Logo" border="0"></span></div>
          <div style="clear: both;"></div>
          {ts}<p>All the normal Direct Debit safeguards and guarantees apply.
No changes in the amount, date or frequency to be debited can be made without notifying you at least 10 working days in advance of your account being debited.
In the event of any error, you are entitled to an immediate refund from your bank or building society.
You have the right to cancel a Direct Debit Instruction at any time simply by writing to your bank or building society, with a copy to us.</p>
<p>In order to set up your Direct Debit Instruction on-line you will need to provide the following information through the setting up procedure (your cheque book contains all the bank details that you require):</p>
<p>Bank or Building Society name and account number, sort code and branch address.</p>
<ul>
<li>If you are not the account holder, a paper Direct Debit Instruction will be sent for completion. Please click to end</li>
<li>If this is a personal account continue with the set-up procedure</li>
<li>If it is a business account and more than one person is required to authorise debits on this account, a paper Direct Debit Instruction will be sent to the Payers for completion.</li>
</ul>

<p>Alternatively you can print off your on-screen Direct Debit Instruction and post it to us: <b>{$company_address.company_name}</b>, {if ($company_address.address1 != '')} {$company_address.address1}, {/if}{if ($company_address.address2 != '')} {$company_address.address2}, {/if}{if ($company_address.address3 != '')} {$company_address.address3}, {/if}{if ($company_address.address4 != '')} {$company_address.address4}, {/if}{if ($company_address.town != '')} {$company_address.town}, {/if}{if ($company_address.county != '')} {$company_address.county}, {/if}{if ($company_address.postcode != '')} {$company_address.postcode}{/if}. If you are unable to print please contact us on {$telephoneNumber} (tel no) and we will post you a paper Direct Debit Instruction.
If you do not wish to proceed any further please <a href="/">click here</a> to end.</p>
<p>The details of your Direct Debit Instruction will be sent to you within 3 working days or no later than 10 working days before the first collection.</p>{/ts}
{/if}   
<!-- MV: end custom changes    -->
      {crmRegion name="billing-block-pre"}
      {/crmRegion}
      <div class="crm-section billing_mode-section {$paymentTypeName}_info-section">
        {foreach from=$paymentFields item=paymentField}
          {assign var='name' value=$form.$paymentField.name}
          <div class="crm-section {$form.$paymentField.name}-section">
            <div class="label">{$form.$paymentField.label}
              {if $requiredPaymentFields.$name}<span class="crm-marker" title="{ts}This field is required.{/ts}">*</span>{/if}
            </div>
            <div class="content">{$form.$paymentField.html}
              {if $paymentField == 'cvv2'}{* @todo move to form assignment*}
                <span class="cvv2-icon" title="{ts}Usually the last 3-4 digits in the signature area on the back of the card.{/ts}"> </span>
              {/if}
              {if $paymentField == 'credit_card_type'}
                <div class="crm-credit_card_type-icons"></div>
              {/if}
            </div>
            <div class="clear"></div>
          </div>
        {/foreach}
        <!--MV: Custom changes from older version civi41, ukdirectdebit -->
        <div class="crm-section {$form.ddi_reference.name}-section">
            <div class="label">{$form.ddi_reference.label}</div>
            <div class="content">{$form.ddi_reference.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section {$form.preferred_collection_day.name}-section">
            <div class="label">{$form.preferred_collection_day.label}</div>
            <div class="content">{$form.preferred_collection_day.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section {$form.confirmation_method.name}-section">
            <div class="label">{$form.confirmation_method.label}</div>
            <div class="content">{$form.confirmation_method.html}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section {$form.payer_confirmation.name}-section">
            <div class="label">{$form.payer_confirmation.label}</div>
            <div class="content">{$form.payer_confirmation.html}</div>
            <div class="clear"></div>
        </div>
        <!-- MV: end custom changes -->
      </div>
    </fieldset>
  {/if}
  {if $billingDetailsFields|@count && $paymentProcessor.payment_processor_type neq 'PayPal_Express'}
    {if $profileAddressFields && !$ccid}
      <input type="checkbox" id="billingcheckbox" value="0">
      <label for="billingcheckbox">{ts}My billing address is the same as above{/ts}</label>
    {/if}
    <fieldset class="billing_name_address-group">
      <legend>{ts}Billing Name and Address{/ts}</legend>
      <div class="crm-section billing_name_address-section">
        {foreach from=$billingDetailsFields item=billingField}
          {assign var='name' value=$form.$billingField.name}
          <div class="crm-section {$form.$billingField.name}-section">
            <div class="label">{$form.$billingField.label}
              {if $requiredPaymentFields.$name}<span class="crm-marker" title="{ts}This field is required.{/ts}">*</span>{/if}
            </div>
            {if $form.$billingField.type == 'text'}
              <div class="content">{$form.$billingField.html}</div>
            {else}
              <div class="content">{$form.$billingField.html|crmAddClass:big}</div>
            {/if}
            <div class="clear"></div>
          </div>
        {/foreach}
      </div>
    </fieldset>
  {/if}
</div>
{if $profileAddressFields}
  <script type="text/javascript">
    {literal}

    CRM.$(function ($) {
      // build list of ids to track changes on
      var address_fields = {/literal}{$profileAddressFields|@json_encode}{literal};
      var input_ids = {};
      var select_ids = {};
      var orig_id, field, field_name;

      // build input ids
      $('.billing_name_address-section input').each(function (i) {
        orig_id = $(this).attr('id');
        field = orig_id.split('-');
        field_name = field[0].replace('billing_', '');
        if (field[1]) {
          if (address_fields[field_name]) {
            input_ids['#' + field_name + '-' + address_fields[field_name]] = '#' + orig_id;
          }
        }
      });
      if ($('#first_name').length)
        input_ids['#first_name'] = '#billing_first_name';
      if ($('#middle_name').length)
        input_ids['#middle_name'] = '#billing_middle_name';
      if ($('#last_name').length)
        input_ids['#last_name'] = '#billing_last_name';

      // build select ids
      $('.billing_name_address-section select').each(function (i) {
        orig_id = $(this).attr('id');
        field = orig_id.split('-');
        field_name = field[0].replace('billing_', '').replace('_id', '');
        if (field[1]) {
          if (address_fields[field_name]) {
            select_ids['#' + field_name + '-' + address_fields[field_name]] = '#' + orig_id;
          }
        }
      });

      // detect if billing checkbox should default to checked
      var checked = true;
      for (var id in input_ids) {
        orig_id = input_ids[id];
        if ($(id).val() != $(orig_id).val()) {
          checked = false;
          break;
        }
      }
      for (var id in select_ids) {
        orig_id = select_ids[id];
        if ($(id).val() != $(orig_id).val()) {
          checked = false;
          break;
        }
      }
      if (checked) {
        $('#billingcheckbox').prop('checked', true).data('crm-initial-value', true);
        if (!CRM.billing || CRM.billing.billingProfileIsHideable) {
          $('.billing_name_address-group').hide();
        }
      }

      // onchange handlers for non-billing fields
      for (var id in input_ids) {
        orig_id = input_ids[id];
        $(id).change(function () {
          var id = '#' + $(this).attr('id');
          var orig_id = input_ids[id];

          // if billing checkbox is active, copy other field into billing field
          if ($('#billingcheckbox').prop('checked')) {
            $(orig_id).val($(id).val());
          }
        });
      }
      for (var id in select_ids) {
        orig_id = select_ids[id];
        $(id).change(function () {
          var id = '#' + $(this).attr('id');
          var orig_id = select_ids[id];

          // if billing checkbox is active, copy other field into billing field
          if ($('#billingcheckbox').prop('checked')) {
            $(orig_id + ' option').prop('selected', false);
            $(orig_id + ' option[value="' + $(id).val() + '"]').prop('selected', true);
            $(orig_id).change();
          }
        });
      }


      // toggle show/hide
      $('#billingcheckbox').click(function () {
        if (this.checked) {
          if (!CRM.billing || CRM.billing.billingProfileIsHideable) {
            $('.billing_name_address-group').hide(200);
          }

          // copy all values
          for (var id in input_ids) {
            orig_id = input_ids[id];
            $(orig_id).val($(id).val());
          }
          for (var id in select_ids) {
            orig_id = select_ids[id];
            $(orig_id + ' option').prop('selected', false);
            $(orig_id + ' option[value="' + $(id).val() + '"]').prop('selected', true);
            $(orig_id).change();
          }
        } else {
          $('.billing_name_address-group').show(200);
        }
      });

      // remove spaces, dashes from credit card number
      $('#credit_card_number').change(function () {
        var cc = $('#credit_card_number').val()
                .replace(/ /g, '')
                .replace(/-/g, '');
        $('#credit_card_number').val(cc);
      });
    });

  </script>
  {/literal}
{/if}

<!-- MV:custom Js, append old version custom changes into civi46 -->
{if $suppressSubmitButton}
{literal}
<style type="text/css">
  #multiple_block > input {
    border: 1px solid;
    width: 43px;
  }
</style>
<script type="text/javascript">
if(cj('tr').attr('id') !== "multiple_block") {

  cj("#bank_identification_number").parent().prepend('<div id ="multiple_block"></div>');
  cj("#multiple_block")

  .html('<input type = "text" size = "3" maxlength = "2" name = "block_1" id = "block_1"/>'
  +' - <input type = "text" size = "3" maxlength = "2" name = "block_2" id ="block_2"/>'
  +' - <input type = "text" size = "3" maxlength = "2" name = "block_3" id = "block_3"/>');

  cj('#block_1').change(function() {
    cj.fn.myFunction();
  });

  cj('#block_2').change(function() {
    cj.fn.myFunction();
  });

  cj('#block_3').change(function() {
    cj.fn.myFunction();
  });

  //function to get value of new title boxes and concatenate the values and display in mailing_title
  cj.fn.myFunction = function() {
    var field1 = cj("input#block_1").val();
    var field2 = cj("input#block_2").val();
    var field3 = cj("input#block_3").val();
    var finalFieldValue = field1 + field2 + field3;

    cj('input#bank_identification_number').val(finalFieldValue);
  };

  //hide the mailing title
  cj("#bank_identification_number").hide();

  //split the value of mailing_title
  //make it to appear on the new three title boxes
  var fieldValue = cj("#bank_identification_number").val();

  var fieldLength;
  if ( fieldValue !== undefined ) {
    fieldLength = fieldValue.length;
  } else {
    fieldLength = 0;
  }

  if (fieldLength !== 0) {

    var fieldSplit = (fieldValue+'').split('');

    cj('#block_1').val(fieldSplit[0]+fieldSplit[1]);

    if(!(fieldSplit[0]+fieldSplit[1])) {
      cj('#block_1').val("");
    }

    cj('#block_2').val(fieldSplit[2]+fieldSplit[3]);

    if(!(fieldSplit[2]+fieldSplit[3])) {
      cj('#block_2').val("");
    }

    cj('#block_3').val(fieldSplit[4]+fieldSplit[5]);

    if(!(fieldSplit[4]+fieldSplit[5])) {
      cj('#block_3').val("");
    }

  }
}

</script>
  <script type="text/javascript">
    CRM.$(function($) {
      $('.crm-submit-buttons', $('#billing-payment-block').closest('form')).hide();
    });
  </script>
{/literal}
{/if}
{/crmRegion}
{crmRegion name="billing-block-post"}
  {* Payment processors sometimes need to append something to the end of the billing block. We create a region for
     clarity  - the plan is to move to assigning this through the payment processor to this region *}
{/crmRegion}


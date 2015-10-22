<div class="crm-block crm-form-block crm-contribution-form-block">
  <div class="crm-section {$form.first_name.name}-section">
    <div class="label">{$form.first_name.label}</div>
    <div class="content">{$form.first_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section {$form.last_name.name}-section">
    <div class="label">{$form.last_name.label}</div>
    <div class="content">{$form.last_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section {$form.amount.name}-section">
    <div class="label">{$form.amount.label}</div>
    <div class="content">{$form.amount.html}</div>
    <div class="clear"></div>
  </div>
  {assign var=n value=email-$bltID}
  <div class="crm-section {$form.$n.name}-section">
    <div class="label">{$form.$n.label}</div>
    <div class="content">{$form.$n.html}</div>
    <div class="clear"></div>
  </div>    
  <div class="crm-section {$form.frequency_unit.name}-section">
    <div class="label">{$form.frequency_unit.label}</div>
    <div class="content">{$form.frequency_unit.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section {$form.financial_type_id.name}-section">
    <div class="label">{$form.financial_type_id.label}</div>
    <div class="content">{$form.financial_type_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section {$form.ddi_reference.name}-section">
    <div class="label">{$form.ddi_reference.label}</div>
    <div class="content">{$form.ddi_reference.html}</div>
    <div class="clear"></div>
  </div>
    
  <div id="payment_information">
    <fieldset class="billing_mode-group {if $paymentProcessor.payment_type & 2}direct_debit_info-group{else}credit_card_info-group{/if}">
      <legend>
	{ts}Direct Debit Information{/ts}
      </legend>
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


    <div class="crm-section billing_mode-section direct_debit_info-section">
      <div class="crm-section {$form.account_holder.name}-section">
	  <div class="label">{$form.account_holder.label}</div>
	  <div class="content">{$form.account_holder.html}
	  <div class="description">{ts}Restrictions: 3 to 18 characters{/ts}</div></div>
	  <div class="clear"></div>
      </div>
      <div class="crm-section {$form.bank_identification_number.name}-section">
	  <div class="label">{$form.bank_identification_number.label}</div>
	  <div class="content">{$form.bank_identification_number.html}
	  <div class="description">{ts}Sort Code – either 111111 or 11-11-11 format accepted.{/ts}</div></div>
	  <div class="clear"></div>
      </div>
      <div class="crm-section {$form.bank_account_number.name}-section">
	  <div class="label">{$form.bank_account_number.label}</div>
	  <div class="content">{$form.bank_account_number.html}
	  <div class="description">{ts}8 digit number.{/ts}</div></div>
	  <div class="clear"></div>
      </div>
      <div class="crm-section {$form.bank_name.name}-section">
	  <div class="label">{$form.bank_name.label}</div>
	  <div class="content">{$form.bank_name.html}</div>
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

    </div>
  </fieldset>
  <fieldset class="billing_name_address-group">
    <legend>{ts}Billing Name and Address{/ts}</legend>
    <div class="crm-section billing_name_address-section">
	<div class="crm-section billingNameInfo-section">
	  <div class="content description">
	       {ts}Enter the name of the account holder, and the corresponding billing address.{/ts}
	  </div>
	</div>
	<div class="crm-section {$form.billing_first_name.name}-section">
	  <div class="label">{$form.billing_first_name.label}</div>
	  <div class="content">{$form.billing_first_name.html}</div>
	  <div class="clear"></div>
	</div>
	<div class="crm-section {$form.billing_middle_name.name}-section">
	  <div class="label">{$form.billing_middle_name.label}</div>
	  <div class="content">{$form.billing_middle_name.html}</div>
	  <div class="clear"></div>
	</div>
	<div class="crm-section {$form.billing_last_name.name}-section">
	  <div class="label">{$form.billing_last_name.label}</div>
	  <div class="content">{$form.billing_last_name.html}</div>
	  <div class="clear"></div>
	</div>
	{assign var=n value=billing_street_address-$bltID}
	<div class="crm-section {$form.$n.name}-section">
	  <div class="label">{$form.$n.label}</div>
	  <div class="content">{$form.$n.html}</div>
	  <div class="clear"></div>
	</div>
	{assign var=n value=billing_city-$bltID}
	<div class="crm-section {$form.$n.name}-section">
	  <div class="label">{$form.$n.label}</div>
	  <div class="content">{$form.$n.html}</div>
	  <div class="clear"></div>
	</div>
	{assign var=n value=billing_country_id-$bltID}
	<div class="crm-section {$form.$n.name}-section">
	  <div class="label">{$form.$n.label}</div>
	  <div class="content">{$form.$n.html|crmReplace:class:big}</div>
	  <div class="clear"></div>
	</div>
	{assign var=n value=billing_state_province_id-$bltID}
	<div class="crm-section {$form.$n.name}-section" id="dd_billing_block_state_province_id">
	  <div class="label">{$form.$n.label}</div>
	  <div class="content">{$form.$n.html|crmReplace:class:big}</div>
	  <div class="clear"></div>
	</div>
	{assign var=n value=billing_postal_code-$bltID}
	<div class="crm-section {$form.$n.name}-section">
	  <div class="label">{$form.$n.label}</div>
	  <div class="content">{$form.$n.html}</div>
	  <div class="clear"></div>
	</div>
    </div>
    </fieldset>
  </div>
</div>

<div id="crm-submit-buttons" class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
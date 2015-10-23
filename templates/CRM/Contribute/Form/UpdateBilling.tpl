<!-- Customised Civi File .tpl file invoked: ukdirectdebit/templates/CRM/Contribute/Form/UpdateBilling.tpl. Call via form.tpl if we have a form in the page. -->
{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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

<div id="help">
  <div class="icon inform-icon"></div>&nbsp;
  {if $mode eq 'auto_renew'}
      {ts}Use this form to update the credit card and billing name and address used with the auto-renewal option for your {$membershipType} membership.{/ts}
  {else}
    <strong>{ts 1=$amount|crmMoney 2=$frequency_interval 3=$frequency_unit}Recurring Contribution Details: %1 every %2 %3{/ts}
    {if $installments}
      {ts 1=$installments}for %1 installments{/ts}.
    {/if}</strong>
    {if $paymentProcessor.payment_processor_type eq 'Smart Debit'}
      <div class="content">{ts}Use this form to update the direct debit billing name and address used for this recurring contribution.{/ts}</div>
    {else}
      <div class="content">{ts}Use this form to update the credit card and billing name and address used for this recurring contribution.{/ts}</div>
    {/if}
  {/if}
</div>
{if $paymentProcessor.payment_processor_type eq 'Smart Debit'}
  <div class="crm-block crm-form-block crm-contribution-form-block">
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
{else}  
  {include file="CRM/Core/BillingBlock.tpl"}
{/if}

<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>

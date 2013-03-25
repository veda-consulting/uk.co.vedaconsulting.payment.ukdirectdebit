{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
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
{if $form.credit_card_number or $form.bank_account_number}
    <div id="payment_information">
        <fieldset class="billing_mode-group {if $paymentProcessor.payment_type & 2}direct_debit_info-group{else}credit_card_info-group{/if}">
            <legend>
               {if $paymentProcessor.payment_type & 2}
                    {ts}Direct Debit Information{/ts}
               {else}
                   {ts}Credit Card Information{/ts}
               {/if}
            </legend> 
          {if $paymentProcessor.payment_type & 2}
          {ts}<p>All the normal Direct Debit safeguards and guarantees apply. 
No changes in the amount, date or frequency to be debited can be made without notifying you at least 10 working days in advance of your account being debited. 
In the event of any error, you are entitled to an immediate refund from your bank or building society. 
You have the right to cancel a Direct Debit Instruction at any time simply by writing to your bank or building society, with a copy to us.</p>
<p>In order to set up your Direct Debit Instruction on-line you will need to provide the following information through the setting up procedure (your cheque book contains all the bank details that you require):</p>
<p>Bank or Building Society name and account number, sort code and branch address.
<ul>
<li>If you are not the account holder, a paper Direct Debit Instruction will be sent for completion. Please click to end</li>
<li>If this is a personal account continue with the set-up procedure</li>
<li>If it is a business account and more than one person is required to authorise debits on this account, a paper Direct Debit Instruction will be sent to the Payers for completion.</li>
</ul>
</p>
<p>Alternatively you can print off your on-screen Direct Debit Instruction and post it to us: <b>{$company_address.company_name}</b>, {if ($company_address.address1 != '')} {$company_address.address1}, {/if}{if ($company_address.address2 != '')} {$company_address.address2}, {/if}{if ($company_address.address3 != '')} {$company_address.address3}, {/if}{if ($company_address.address4 != '')} {$company_address.address4}, {/if}{if ($company_address.town != '')    } {$company_address.town}, {/if}{if ($company_address.county != '')  } {$company_address.county}, {/if}{if ($company_address.postcode != '')} {$company_address.postcode}{/if}. If you are unable to print please contact us on {$telephoneNumber} (tel no) and we will post you a paper Direct Debit Instruction.
If you do not wish to proceed any further please <a href="/">click here</a> to end.</p>
<p>The details of your Direct Debit Instruction will be sent to you within 3 working days or no later than 10 working days before the first collection.</p>{/ts}
{/if}
            {if $paymentProcessor.billing_mode & 2 and !$hidePayPalExpress }
            <div class="crm-section no-label paypal_button_info-section">   
                <div class="content description">
                    {ts}If you have a PayPal account, you can click the PayPal button to continue. Otherwise, fill in the credit card and billing information on this form and click <strong>Continue</strong> at the bottom of the page.{/ts}
                </div>
            </div>
             <div class="crm-section no-label {$form.$expressButtonName.name}-section"> 
                <div class="content description">
                    {$form.$expressButtonName.html}
                    <div class="description">Save time. Checkout securely. Pay without sharing your financial information. </div>
                </div>
            </div>
            {/if} 

            {if $paymentProcessor.billing_mode & 1}
                <div class="crm-section billing_mode-section {if $paymentProcessor.payment_type & 2}direct_debit_info-section{else}credit_card_info-section{/if}">
                   {if $paymentProcessor.payment_type & 2}
                        <div class="crm-section {$form.account_holder.name}-section">   
                            <div class="label">{$form.account_holder.label}</div>
                            <div class="content">{$form.account_holder.html}</div>
                            <div class="clear"></div> 
                        </div>
                        <div class="crm-section {$form.bank_identification_number.name}-section">   
                            <div class="label">{$form.bank_identification_number.label}</div>
                            <div class="content">{$form.bank_identification_number.html}</div>
                            <div class="clear"></div> 
                        </div>
                        <div class="crm-section {$form.bank_account_number.name}-section">  
                            <div class="label">{$form.bank_account_number.label}</div>
                            <div class="content">{$form.bank_account_number.html}</div>
                            <div class="clear"></div> 
                        </div>
                        <div class="crm-section {$form.bank_name.name}-section">    
                            <div class="label">{$form.bank_name.label}</div>
                            <div class="content">{$form.bank_name.html}</div>
                            <div class="clear"></div> 
                        </div>
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
                   {else}
                        <div class="crm-section {$form.credit_card_type.name}-section"> 
                            <div class="label">{$form.credit_card_type.label}</div>
                            <div class="content">{$form.credit_card_type.html}</div>
                            <div class="clear"></div> 
                        </div>
                        <div class="crm-section {$form.credit_card_number.name}-section">   
                            <div class="label">{$form.credit_card_number.label}</div>
                            <div class="content">{$form.credit_card_number.html}
                                <div class="description">{ts}Enter numbers only, no spaces or dashes.{/ts}</div>
                            </div>
                            <div class="clear"></div> 
                        </div>
                        <div class="crm-section {$form.cvv2.name}-section"> 
                            <div class="label">{$form.cvv2.label}</div>
                            <div class="content">
                                {$form.cvv2.html}
                                <img src="{$config->resourceBase}i/mini_cvv2.gif" alt="{ts}Security Code Location on Credit Card{/ts}" style="vertical-align: text-bottom;" />
                                <div class="description">{ts}Usually the last 3-4 digits in the signature area on the back of the card.{/ts}</div>
                            </div>
                            <div class="clear"></div> 
                        </div>
                        <div class="crm-section {$form.credit_card_exp_date.name}-section"> 
                            <div class="label">{$form.credit_card_exp_date.label}</div>
                            <div class="content">{$form.credit_card_exp_date.html}</div>
                            <div class="clear"></div> 
                        </div>
                    {/if}
                </div>
                </fieldset>

                <fieldset class="billing_name_address-group">
                    <legend>{ts}Billing Name and Address Hello?{/ts}</legend>
                    <div class="crm-section billing_name_address-section">
                        <div class="crm-section billingNameInfo-section">   
                            <div class="content description">
                              {if $paymentProcessor.payment_type & 2}
                                 {ts}Enter the name of the account holder, and the corresponding billing address.{/ts}
                              {else}
                                 {ts}Enter the name as shown on your credit or debit card, and the billing address for this card.{/ts}
                              {/if}
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
            {else}
                </fieldset>
            {/if}
    </div>
{/if}


{literal}
<script>

cj(document).ready(function($) {

    if(cj('tr').attr('id') != "multiple_block") {

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

        }

        //hide the mailing title
        cj("#bank_identification_number").hide();

        //split the value of mailing_title
        //make it to appear on the new three title boxes
        var fieldValue = cj("#bank_identification_number").val();
        var fieldLength = fieldValue.length;

        if (fieldLength != 0) {

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

});

</script>
{/literal}

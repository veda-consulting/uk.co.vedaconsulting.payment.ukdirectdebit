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
{if $action & 1024}
    {include file="CRM/Contribute/Form/Contribution/PreviewHeader.tpl"}
{/if}

{include file="CRM/common/TrackingFields.tpl"}

<div class="crm-block crm-contribution-thankyou-form-block">
    {if $thankyou_text}
        <div id="thankyou_text" class="crm-section thankyou_text-section">
            {$thankyou_text}
        </div>
    {/if}

    {* Show link to Tell a Friend (CRM-2153) *}
    {if $friendText}
        <div id="tell-a-friend" class="crm-section friend_link-section">
            <a href="{$friendURL}" title="{$friendText}" class="button"><span>&raquo; {$friendText}</span></a>
       </div>{if !$linkText}<br /><br />{/if}
    {/if}
    {* Add button for donor to create their own Personal Campaign page *}
    {if $linkText}
    <div class="crm-section create_pcp_link-section">
        <a href="{$linkTextUrl}" title="{$linkText}" class="button"><span>&raquo; {$linkText}</span></a>
    </div><br /><br />
    {/if}

    {if $paymentProcessor.payment_type & 2}
      <div style="font-size:1.3em; text-align: center; margin: 0% 25% 20px 25%; border: 1px solid #000000; width: 50%; "><em><strong>Important : Confirmation of the set up of your Direct Debit Instruction including future payment schedule</strong></em></div>
    {/if}

    <div id="help">
        {* PayPal_Standard sets contribution_mode to 'notify'. We don't know if transaction is successful until we receive the IPN (payment notification) *}
        {if $is_pay_later}
        <div class="bold">{$pay_later_receipt}</div>
        {if $is_email_receipt}
                <div>
            {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            {ts 1=$email 2=$onBehalfEmail}An email confirmation with these payment instructions has been sent to %1 and to %2.{/ts}
            {else}
            {ts 1=$email}An email confirmation with these payment instructions has been sent to %1.{/ts}
            {/if}
        </div>
            {/if}
        {elseif $contributeMode EQ 'notify' OR ($contributeMode EQ 'direct' && $is_recur) }
            {if $paymentProcessor.payment_type & 2}
                <div>{ts}That completes the setting up of your Direct Debit Instruction and the confirmation of the Instruction will be sent to you within 3 working days or be received by you no later than 10 working days before the first collection. The company name that will appear on your bank statement against the Direct Debit will be "{$company_address.company_name}". Please print this page for your records.{/ts}</div>
            {else}
                <div>{ts 1=$paymentProcessor.processorName}Your contribution has been submitted to %1 for processing. Please print this page for your records.{/ts}</div>
            {/if}
            {if $is_email_receipt}
                <div>
            {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            {ts 1=$email 2=$onBehalfEmail}An email receipt will be sent to %1 and to %2 once the transaction is processed successfully.{/ts}
            {else}
            {ts 1=$email}An email receipt will be sent to %1 once the transaction is processed successfully.{/ts}
            {/if}
        </div>
            {/if}
        {else}
            <div>{ts}Your transaction has been processed successfully. Please print this page for your records.{/ts}</div>
            {if $is_email_receipt}
                <div>
            {if $onBehalfEmail AND ($onBehalfEmail neq $email)}
            {ts 1=$email 2=$onBehalfEmail}An email receipt has also been sent to %1 and to %2{/ts}
            {else}
            {ts 1=$email}An email receipt has also been sent to %1{/ts}
            {/if}
        </div>
            {/if}
        {/if}
    </div>
    <div class="spacer"></div>

    {include file="CRM/Contribute/Form/Contribution/MembershipBlock.tpl" context="thankContribution"}

    {if $amount GT 0 OR $minimum_fee GT 0 OR ( $priceSetID and $lineItem ) }
    <div class="crm-group amount_display-group">
       {if !$useForMember}
        <div class="header-dark">
            {if !$membershipBlock AND $amount OR ( $priceSetID and $lineItem )}{ts}Contribution Information{/ts}{else}{ts}Membership Fee{/ts}{/if}
        </div>
        {/if}
        <div class="display-block">
            {if !$useForMember}
            {if $lineItem and $priceSetID}
            {if !$amount}{assign var="amount" value=0}{/if}
            {assign var="totalAmount" value=$amount}
                {include file="CRM/Price/Page/LineItem.tpl" context="Contribution"}
            {elseif $membership_amount }
                {$membership_name} {ts}Membership{/ts}: <strong>{$membership_amount|crmMoney}</strong><br />
                {if $amount}
                    {if ! $is_separate_payment }
                {ts}Contribution Amount{/ts}: <strong>{$amount|crmMoney}</strong><br />
                {else}
                {ts}Additional Contribution{/ts}: <strong>{$amount|crmMoney}</strong><br />
                {/if}
                {/if}
                <strong> -------------------------------------------</strong><br />
                {ts}Total{/ts}: <strong>{$amount+$membership_amount|crmMoney}</strong><br />
            {else}
                {ts}Amount{/ts}: <strong>{$amount|crmMoney} {if $amount_level } - {$amount_level} {/if}</strong><br />
            {/if}
        {/if}
            {if $receive_date}
            {ts}Date{/ts}: <strong>{$receive_date|crmDate}</strong><br />
            {/if}
            {if $contributeMode ne 'notify' and $is_monetary and ! $is_pay_later and $trxn_id}
                {if $paymentProcessor.payment_type & 2}
                    {ts}Direct Debit Reference{/ts}: {$trxn_id}<br />
                {else}
                    {ts}Transaction #{/ts}: {$trxn_id}<br />
                {/if}
            {/if}
            {if $membership_trx_id}
            {ts}Membership Transaction #{/ts}: {$membership_trx_id}
            {/if}

            {* Recurring contribution / pledge information *}
            {if $is_recur}
                {if $membershipBlock} {* Auto-renew membership confirmation *}
                    <br />
                    <strong>{ts 1=$frequency_interval 2=$frequency_unit}This membership will be renewed automatically every %1 %2(s).{/ts}</strong>
                    <div class="description crm-auto-renew-cancel-info">({ts}You will be able to cancel automatic renewals at any time by logging in to your account or contacting us.{/ts})</div>
                {else}
                    {if $installments}
                        <p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}This recurring contribution will be automatically processed every %1 %2(s) for a total %3 installments (including this initial contribution).{/ts}</strong></p>
                    {else}
                        <p><strong>{ts 1=$frequency_interval 2=$frequency_unit}This recurring contribution will be automatically processed every %1 %2(s).{/ts}</strong></p>
                    {/if}
                    <p>{ts}First Collection Date{/ts}: <strong>{$direct_debit_details.first_collection_date|crmDate}</strong></p>
                    <p>
                    {if $contributeMode EQ 'notify'}
                        {ts 1=$cancelSubscriptionUrl}You can modify or cancel future contributions at any time by <a href='%1'>logging in to your account</a>.{/ts}
                    {/if}
                    {if $contributeMode EQ 'direct'}
                        {ts 1=$receiptFromEmail}To modify or cancel future contributions please contact us at %1.{/ts}
                    {/if}
                    {if $is_email_receipt}
                        {ts}You will receive an email receipt for each recurring contribution.{/ts}
                    {/if}
                    {if $contributeMode EQ 'notify'}
                        {ts}The receipts will also include a link you can use if you decide to modify or cancel your future contributions.{/ts}
                    {/if}
                    </p>
                {/if}
            {/if}
            {if $is_pledge}
                {if $pledge_frequency_interval GT 1}
                    <p><strong>{ts 1=$pledge_frequency_interval 2=$pledge_frequency_unit 3=$pledge_installments}I pledge to contribute this amount every %1 %2s for %3 installments.{/ts}</strong></p>
                {else}
                    <p><strong>{ts 1=$pledge_frequency_interval 2=$pledge_frequency_unit 3=$pledge_installments}I pledge to contribute this amount every %2 for %3 installments.{/ts}</strong></p>
                {/if}
                <p>
                {if $is_pay_later}
                    {ts 1=$receiptFromEmail}We will record your initial pledge payment when we receive it from you. You will be able to modify or cancel future pledge payments at any time by logging in to your account or contacting us at %1.{/ts}
                {else}
                    {ts 1=$receiptFromEmail}Your initial pledge payment has been processed. You will be able to modify or cancel future pledge payments at any time by logging in to your account or contacting us at %1.{/ts}
                {/if}
                {if $max_reminders}
                    {ts 1=$initial_reminder_day}We will send you a payment reminder %1 days prior to each scheduled payment date. The reminder will include a link to a page where you can make your payment online.{/ts}
                {/if}
                </p>
            {/if}
        </div>
    </div>
    {/if}

    {if $honor_block_is_active}
    <div class="crm-group honor_block-group">
      <div class="header-dark">
        {$soft_credit_type}
      </div>
      <div class="display-block">
       <div class="label-left crm-section honoree_profile-section">
          <strong>{$honorName}</strong></br>
          {include file="CRM/UF/Form/Block.tpl" fields=$honoreeProfileFields prefix='honor'}
        </div>
      </div>
   </div>
  {/if}

    {if $customPre}
            <fieldset class="label-left">
                {include file="CRM/UF/Form/Block.tpl" fields=$customPre}
            </fieldset>
    {/if}

    {if $pcpBlock}
    <div class="crm-group pcp_display-group">
        <div class="header-dark">
            {ts}Contribution Honor Roll{/ts}
        </div>
        <div class="display-block">
            {if $pcp_display_in_roll}
                {ts}List my contribution{/ts}
                {if $pcp_is_anonymous}
                    <strong>{ts}anonymously{/ts}.</strong>
                {else}
                    {ts}under the name{/ts}: <strong>{$pcp_roll_nickname}</strong><br/>
                    {if $pcp_personal_note}
                        {ts}With the personal note{/ts}: <strong>{$pcp_personal_note}</strong>
                    {else}
                     <strong>{ts}With no personal note{/ts}</strong>
                     {/if}
                {/if}
            {else}
                {ts}Don't list my contribution in the honor roll.{/ts}
            {/if}
            <br />
       </div>
    </div>
    {/if}

    {if $onbehalfProfile}
      <div class="crm-group onBehalf_display-group label-left crm-profile-view">
         {include file="CRM/UF/Form/Block.tpl" fields=$onbehalfProfile prefix='onbehalf'}
         <div class="crm-section organization_email-section">
            <div class="label">{ts}Organization Email{/ts}</div>
            <div class="content">{$onBehalfEmail}</div>
            <div class="clear"></div>
         </div>
      </div>
    {/if}

    {if $contributeMode ne 'notify' and ! $is_pay_later and $is_monetary and ( $amount GT 0 OR $minimum_fee GT 0 )}
    <div class="crm-group billing_name_address-group">
        <div class="header-dark">
            {ts}Billing Name and Address{/ts}
        </div>
        <div class="crm-section no-label billing_name-section">
            <div class="content">{$billingName}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section no-label billing_address-section">
            <div class="content">{$address|nl2br}</div>
            <div class="clear"></div>
        </div>
        <div class="crm-section no-label contributor_email-section">
            <div class="content">{$email}</div>
            <div class="clear"></div>
        </div>
    </div>
    {/if}

    {if $contributeMode eq 'direct' and ! $is_pay_later and $is_monetary and ( $amount GT 0 OR $minimum_fee GT 0 )}
    <div class="crm-group credit_card-group">
         {if $paymentProcessor.payment_type & 2}
            <div class="header-dark">
            {ts}Direct Debit Information{/ts}
            </div>
            <div>{ts}Thank you very much for your Direct Debit Instruction details. Below is the Direct Debit Guarantee for your information.{/ts}</div>
            <div>Please <a href="javascript:window.print()" title="Print this page.">PRINT THIS PAGE</a> for you records</div>
         {else}
            <div class="header-dark">
            {ts}Credit Card Information{/ts}
            </div>
         {/if}
        </div>
         {if $paymentProcessor.payment_type & 2}
                <div class="display-block">

{* Start of DDI *}
<div style="float: left;border: 1px solid #000000;background-color: #ffffff;width: 100%;">

    <div style="text-align: center;">
{*        <div><span id="logo1"><img src="client_logo.jpg" alt="Client Logo" border="0"></span></div> *}
        <div><span style="float: right;margin-right: 25px;"><img src="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit.gif}" alt="Direct Debit Logo" border="0"></span></div>
        <div style="clear: both;"></div>
    </div>

    <div style="float: left;margin-left: 5px;margin-right: 10px;width: 305px;">

        <p>
          <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
            <b>{$company_address.company_name}</b><br>
            {if ($company_address.address1 != '')} {$company_address.address1}<br/> {/if}
            {if ($company_address.address2 != '')} {$company_address.address2}<br/> {/if}
            {if ($company_address.address3 != '')} {$company_address.address3}<br/> {/if}
            {if ($company_address.address4 != '')} {$company_address.address4}<br/> {/if}
            {if ($company_address.town != '')    } {$company_address.town}<br/>     {/if}
            {if ($company_address.county != '')  } {$company_address.county}<br/>   {/if}
            {if ($company_address.postcode != '')} {$company_address.postcode}      {/if}
          </div>
        </p>

        <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Name(s) of Account Holder(s)</h2>

        <p>
          <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
            {$account_holder}<br />
          </div>
        </p>

        <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Bank/Building Society Account Number</h2>

        <p>
          <table style="background-color: #ffffff;border: 1px solid #000000;border-collapse: collapse;" summary="Branch Sort Code">
          <tr>
          <td style="border: 1px solid #000000;padding: 0;width: 240px;height: 30px;text-align: left;">{$bank_account_number}</td>
          <tr>
          </table>
        </p>

        <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Branch Sort Code</h2>

        <p>
          <table style="background-color: #ffffff;border: 1px solid #000000;border-collapse: collapse;" summary="Branch Sort Code">
          <tr>
          <td style="border: 1px solid #000000;padding: 0;width: 180px;height: 30px;text-align: left;">{$bank_identification_number}</td>
          <tr>
          </table>
        </p>

        <p>
        <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
          <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">To the Manager<span style="margin-left: 4em;">Bank/Building Society</span></span></div>
          <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><br />{$direct_debit_details.bank_name}</div>
          <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">Branch</span><span style="margin-left: 3em;">{$direct_debit_details.branch}</span></div>
          <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">Address</span></div>

            {if ($direct_debit_details.address1 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address1}<br/></div> {/if}
            {if ($direct_debit_details.address2 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address2}<br/></div> {/if}
            {if ($direct_debit_details.address3 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address3}<br/></div> {/if}
            {if ($direct_debit_details.address4 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address4}<br/></div> {/if}
            {if ($direct_debit_details.town != '')    } <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.town    }<br/></div> {/if}
            {if ($direct_debit_details.county != '')  } <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.county  }<br/></div> {/if}
            {if ($direct_debit_details.postcode != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.postcode}<br/></div> {/if}

        </div>
        </p>


    </div> <!-- <div id="column1"> -->

    <div style="float: right;margin-right: 5px;width: 305px;">

        <h1 style="font-size: 1.3em;margin-top: 0;text-align: left;margin: 0% 0%;">Instruction to your Bank or Building Society to pay by Direct Debit</h1>

        <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Service User Number</h2>

        <p>
          <table style="background-color: #ffffff;border: 1px solid #000000;border-collapse: collapse;" summary="Branch Sort Code">
          <tr>
          {foreach from=$service_user_number item=singleDigit}
               <td style="border: 1px solid #000000;padding: 0;width: 30px;height: 30px;text-align: center;">{$singleDigit}</td>
          {/foreach}
          <tr>
          </table>
        </p>

        <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Reference:</h2>

        <p>
          <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
            {$trxn_id}
          </div>
        </p>


        <h2 style="font-size: 1em;text-align: left;font-weight: bold;margin-bottom: 3px; margin-top: 15px;">Instruction to your Bank or Building Society</h2>

        <p>
        Please pay {$company_address.company_name} Direct Debits from the account detailed in this Instruction subject to the safeguards assured by the Direct Debit Guarantee. I understand that this Instruction may remain with {$company_address.company_name} and, if so, details will be passed electronically to my Bank / Building Society.
        </p>

          <p>
            <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
              <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">Date</span><span style="margin-left: 1em;">{$directDebitDate|crmDate}</span></div>
              <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"></div>
            </div>
          </p>

    </div> <!-- <div id="column2"> -->

    <div style="clear: both;"></div>

    <div>
        <p style="text-align: center;">
        Banks and Building Societies may not accept Direct Debit Instructions from some types of account.
        </p>
    </div>
</div> <!-- <div id="directDebitInstructions"> -->
{* End of DDI *}

 <div style="clear: both;"></div>

   <TABLE WIDTH="620" CELLPADDING="2" CELLSPACING="0" BORDER="1" RULES="NONE">
    <TR>
     <TD WIDTH="580" VALIGN=TOP>
      <P ALIGN=CENTER>
       <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="5"><B>The Direct Debit Guarantee</B></FONT><FONT SIZE="6"> </FONT></FONT><!-- $MVD$:picsz("894","306") --><IMG SRC="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit.gif}" ALIGN=TOP WIDTH="107" HEIGHT="37" VSPACE="0" HSPACE="0" ALT="direct debit logo" BORDER="0" LOOP="0"></TD>
     <TD WIDTH="20" VALIGN=TOP></TD>
    </TR>
    <TR>
     <TD WIDTH="94%" VALIGN=TOP>
      <P ALIGN=LEFT>
       <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
       {ts}This Guarantee is offered by all banks and building societies that accept instructions to pay Direct Debits.{/ts}
       </FONT></FONT><BR>
       <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
       {ts}If there are any changes to the amount, date or frequency of your Direct Debit {$company_address.company_name} will notify you 10 working days in advance of your account being debited or as otherwise agreed. If you request {$company_address.company_name} to collect a payment, confirmation of the amount and date will be given to you at the time of the request.{/ts}
       </FONT></FONT><BR>
       <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
       {ts}If an error is made in the payment of your Direct Debit, by {$company_address.company_name} or your bank or building society, you are entitled to a full and immediate refund of the amount paid from your bank or building society.{/ts}
       </FONT></FONT><BR>
       <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
       {ts}If you receive a refund you are not entitled to, you must pay it back when {$company_address.company_name} asks you to.{/ts}
       </FONT></FONT><BR>
       <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
       {ts}You can cancel a Direct Debit at any time by simply contacting your bank or building society. Written confirmation may be required. Please also notify us.{/ts}
       </FONT></FONT><BR>
     <TD WIDTH="20" VALIGN=TOP></TD>
    </TR>
   </TABLE>
                </div>

         {else}
             <div class="crm-section no-label credit_card_details-section">
                 <div class="content">{$credit_card_type}</div>
                <div class="content">{$credit_card_number}</div>
                <div class="content">{ts}Expires{/ts}: {$credit_card_exp_date|truncate:7:''|crmDate}</div>
                <div class="clear"></div>
             </div>
         {/if}
    </div>
    {/if}

    {include file="CRM/Contribute/Form/Contribution/PremiumBlock.tpl" context="thankContribution"}

    {if $customPost}
            <fieldset class="label-left">
                {include file="CRM/UF/Form/Block.tpl" fields=$customPost}
            </fieldset>
    {/if}

    <div id="thankyou_footer" class="contribution_thankyou_footer-section">
        <p>
        {$thankyou_footer}
        </p>
    </div>
    {if $isShare}
    {capture assign=contributionUrl}{crmURL p='civicrm/contribute/transact' q="$qParams" a=true fe=1 h=1}{/capture}
    {include file="CRM/common/SocialNetwork.tpl" url=$contributionUrl title=$title pageURL=$contributionUrl}
    {/if}
</div>

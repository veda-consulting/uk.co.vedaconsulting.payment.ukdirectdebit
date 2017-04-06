<h3>{ts}Select the AUDDIS and ARUDD dates that you wish to process now:{/ts}</h3>
<div class="help">
    {ts}The below tables summarise the data available in the selected AUDDIS/ARUDD files.{/ts}<br />
    {if $totalMatched == 0}
        <strong>{ts}As there are no matching contacts you cannot import any transactions.{/ts}</strong>
    {else}
        <strong>{ts}To import click continue.{/ts}</strong>
    {/if}

</div>
<div class="crm-block crm-form-block crm-export-form-block">
    <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
        <div class="crm-submit-buttons">
            {include file="CRM/common/formButtons.tpl" location="top"}
        </div>
    </div>
    <h3>{ts}Summary{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Description{/ts}</td>
            <td style ="text-align: right"><b>{ts}Number{/ts}</td>
            <td style ="text-align: right"><b>{ts}Amount{/ts}</td>
        </tr>
        {foreach from=$summary key=description item=sum}
            <tr>
                <td>{$description}</td>
                <td style ="text-align: right">{$sum.count}</td>
                <td style ="text-align: right">{$sum.total}</td>
            </tr>
        {/foreach}
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Total{/ts}</td>
            <td style ="text-align: right">{$summaryNumber}</td>
            <td style ="text-align: right">{$totalSummaryAmount}</td>
        </tr>
    </table>
    <br>
    <h3>{ts}Rejected Contribution in the AUDDIS{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Reference{/ts}</td>
            <td><b>{ts}Contact{/ts}</td>
            <td><b>{ts}Frequency{/ts}</td>
            <td><b>{ts}Reason code{/ts}</td>
            <td><b>{ts}Receive Date{/ts}</td>
            <td style ="text-align: right"><b>{ts}Total{/ts}</td>
        </tr>
        {foreach from=$newAuddisArray item=auddis}
            {assign var=reason value='reason-code'}
            <tr>
                <td>{$auddis.reference}</td>
                <td>
                    {if $auddis.contact_id gt 0}
                        {assign var=contactId value=$auddis.contact_id}
                        {capture assign=contactViewURL}{crmURL p='civicrm/contact/view' q="reset=1&cid=$contactId"}{/capture}
                        <a href="{$contactViewURL}">{$auddis.contact_name}</a>
                    {else}
                        {$auddis.contact_name}
                    {/if}
                </td>
                <td>{$auddis.frequency}</td>
                <td>{$auddis.$reason}</td>
                <td>{$auddis.start_date|crmDate}</td>
                <td style ="text-align: right">{$auddis.amount|crmMoney}</td>
            </tr>
        {/foreach}
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Rejected Contributions in AUDDIS{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalRejected|crmMoney}{/ts}</td>
        </tr>
    </table>
    <br>
    <h3>{ts}Rejected Contribution in the ARUDD{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Reference{/ts}</td>
            <td><b>{ts}Contact{/ts}</td>
            <td><b>{ts}Frequency{/ts}</td>
            <td><b>{ts}Reason code{/ts}</td>
            <td><b>{ts}Receive Date{/ts}</td>
            <td style ="text-align: right"><b>{ts}Total{/ts}</td>
        </tr>
        {foreach from=$newAruddArray item=arudd}
            {assign var=reason value='reason-code'}
            <tr>
                <td>{$arudd.reference}</td>
                <td>
                    {if $arudd.contact_id gt 0}
                        {assign var=contactId value=$arudd.contact_id}
                        {capture assign=contactViewURL}{crmURL p='civicrm/contact/view' q="reset=1&cid=$contactId"}{/capture}
                        <a href="{$contactViewURL}">{$arudd.contact_name}</a>
                    {else}
                        {$arudd.contact_name}
                    {/if}
                </td>
                <td>{$arudd.frequency}</td>
                <td>{$arudd.$reason}</td>
                <td>{$arudd.start_date|crmDate}</td>
                <td style ="text-align: right">{$arudd.amount|crmMoney}</td>
            </tr>
        {/foreach}
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Rejected Contributions in ARUDD{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalRejectedArudd|crmMoney}{/ts}</td>
        </tr>
    </table>
    <br>
    <h3>{ts}Contributions already processed{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Transaction ID{/ts}</td>
            <td><b>{ts}Contact{/ts}</td>
            <td><b>{ts}Frequency{/ts}</td>
            <td><b>{ts}Receive Date{/ts}</td>
            <td style ="text-align: right"><b>{ts}Total{/ts}</td>
        </tr>
        {foreach from=$existArray item=row}
            {assign var=id value=$row.id}
            <tr>
                <td>{$row.transaction_id}</td>
                <td>
                    {if $row.contact_id gt 0}
                        {assign var=contactId value=$row.contact_id}
                        {capture assign=contactViewURL}{crmURL p='civicrm/contact/view' q="reset=1&cid=$contactId"}{/capture}
                        <a href="{$contactViewURL}">{$row.contact_name}</a>
                    {else}
                        {$row.contact_name}
                    {/if}
                </td>
                <td>{$row.frequency}</td>
                <td>{$row.start_date|crmDate}</td>
                <td style ="text-align: right">{$row.amount|crmMoney}</td>
            </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Processed Contributions{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalExist}{/ts}</td>
        </tr>
    </table>
    <br>
    <h3>{ts}Contributions matched to contacts{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Transaction ID{/ts}</td>
            <td><b>{ts}Contact{/ts}</td>
            <td><b>{ts}Frequency{/ts}</td>
            <td><b>{ts}Receive Date{/ts}</td>
            <td style ="text-align: right"><b>{ts}Total{/ts}</td>
        </tr>
        {foreach from=$listArray item=row}
            {assign var=id value=$row.id}
            <tr>
                <td>{$row.transaction_id}</td>
                <td>
                    {if $row.contact_id gt 0}
                        {assign var=contactId value=$row.contact_id}
                        {capture assign=contactViewURL}{crmURL p='civicrm/contact/view' q="reset=1&cid=$contactId"}{/capture}
                        <a href="{$contactViewURL}">{$row.contact_name}</a>
                    {else}
                        {$row.contact_name}
                    {/if}
                </td>
                <td>{$row.frequency}</td>
                <td>{$row.start_date|crmDate}</td>
                <td style ="text-align: right">{$row.amount|crmMoney}</td>
            </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Matched Contributions{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalMatched}{/ts}</td>
        </tr>
    </table>
    <h3>{ts}Contributions not matched to contacts{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
            <td><b>{ts}Reference{/ts}</td>
            <td><b>{ts}Contact{/ts}</td>
            <td><b>{ts}Smart Debit Contact ID{/ts}</td>
            <td><b>{ts}Receive Date{/ts}</td>
            <td style ="text-align: right"><b>{ts}Total{/ts}</td>
        </tr>
        {foreach from=$missingArray item=row}
            {assign var=id value=$row.id}
            <tr>
                <td>{$row.transaction_id}</td>
                <td>
                    {$row.contact_name}
                </td>
                <td>{$row.contact_id}</td>
                <td>{$row.receive_date}</td>
                <td style ="text-align: right">{$row.amount|crmMoney}</td>
            </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Not Matched Contributions{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalMissing}{/ts}</td>
        </tr>
    </table>
    <br>
    <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
        <div class="crm-submit-buttons">
            {include file="CRM/common/formButtons.tpl" location="bottom"}
        </div>
    </div>
</div>

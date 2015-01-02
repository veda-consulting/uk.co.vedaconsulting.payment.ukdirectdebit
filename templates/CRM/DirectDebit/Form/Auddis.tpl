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
   <h3>{ts}Rejected Contribution in the auddis{/ts}</h3>
    <table class="form-layout">
         <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Reference{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Reason code{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td style ="text-align: right"><b>{ts}Total{/ts}</td>
        </tr>
        {foreach from=$newAuddisArray item=auddis}
             {assign var=reason value='reason-code'}
        <tr>
        <td>{$auddis.reference}</td>
        <td>
            {if $auddis.contact_id gt 0}
									<a href="/civicrm/contact/view?cid={$auddis.contact_id}">{$auddis.contact_name}</a>
							  {else}
									{$auddis.contact_name}
								{/if}</td>
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
            <td><b>{ts}Total Rejected Contribution{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalRejected|crmMoney}{/ts}</td>
        </tr>
    </table>
    <br>
    
    <h3>{ts}Contribution already processed{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Transaction ID{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td style ="text-align: right"><b>{ts}Total{/ts}</td>
           <td></td>
        </tr>
        {foreach from=$existArray item=row}
        {assign var=id value=$row.id}
        <tr>
            <td>{$row.transaction_id}</td>
            <td>
                {if $row.contact_id gt 0}
                    <a href="/civicrm/contact/view?cid={$row.contact_id}">{$row.contact_name}</a>
                {else}
                    {$row.contact_name}
                {/if}</td>
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
            <td><b>{ts}Total Processed Contribution{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalExist}{/ts}</td>
        </tr>

    </table>
            <br>
    <h3>{ts}Contribution not matched to contacts{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Reference{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td style ="text-align: right"><b>{ts}Total{/ts}</td>
           <td></td>
        </tr>
        {foreach from=$missingArray item=row}
        {assign var=id value=$row.id}
        <tr>
            <td>{$row.transaction_id}</td>
            <td>
                {if $row.contact_id gt 0}
                    <a href="/civicrm/contact/view?cid={$row.contact_id}">{$row.contact_name}</a>
                {else}
                    {$row.contact_name}
                {/if}</td>
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
            <td><b>{ts}Total Not Matched Contribution{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$totalMissing}{/ts}</td>
        </tr>

    </table>
        <h3>{ts}Contribution matched to contacts{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Transaction ID{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td style ="text-align: right"><b>{ts}Total{/ts}</td>
           <td><b>{ts}Message{/ts}</b></td>
        </tr>
        {foreach from=$listArray item=row}
        {assign var=id value=$row.id}
        <tr>
            <td>{$row.transaction_id}</td>
            <td>
                {if $row.contact_id gt 0}
                    <a href="/civicrm/contact/view?cid={$row.contact_id}">{$row.contact_name}</a>
                {else}
                    {$row.contact_name}
                {/if}</td>
            <td>{$row.frequency}</td>
            <td>{$row.start_date|crmDate}</td>
            <td style ="text-align: right">{$row.amount|crmMoney}</td>
            <td>{$row.message}</td>
        </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Matched Contribution{/ts}</td>
            <td style ="text-align: right"><b>{ts}{$total}{/ts}</td>
        </tr>

    </table>
         <br>
        <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
        </div>
</div>

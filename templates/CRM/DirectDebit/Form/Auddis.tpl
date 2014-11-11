
<div class="crm-block crm-form-block crm-export-form-block">
   <h3>{ts}Rejected Contribution in the auddis{/ts}</h3>
    <table class="form-layout">
         <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Reference{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Reason code{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td><b>{ts}Total{/ts}</td>
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
        <td>{$auddis.amount}</td>
        </tr>
        {/foreach}
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Rejected Contribution{/ts}</td>
            <td><b>{ts}{$totalRejected}{/ts}</td>
        </tr>
    </table>
    <br>
    <h3>{ts}Contacts for which contribution to be added{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Transaction ID{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td><b>{ts}Total{/ts}</td>
           <td></td>
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
            <td>{$row.amount}</td>
        </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td><b>{ts}Total Contribution{/ts}</td>
            <td><b>{ts}{$total}{/ts}</td>
        </tr>
        
    </table>
         <br>
    <h3>{ts}Contacts for which contribution already processed{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Transaction ID{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td><b>{ts}Total{/ts}</td>
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
            <td>{$row.amount}</td>
        </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        
    </table>
            <br>
    <h3>{ts}Contacts for which no record found in CiviCRM{/ts}</h3>
    <table class="form-layout">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Reference{/ts}</td>
           <td><b>{ts}Contact{/ts}</td>
           <td><b>{ts}Frequency{/ts}</td>
           <td><b>{ts}Start Date{/ts}</td>
           <td><b>{ts}Total{/ts}</td>
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
            <td>{$row.amount}</td>
        </tr>
        {/foreach}
        <br/>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        
    </table>
        <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
        </div>
</div>

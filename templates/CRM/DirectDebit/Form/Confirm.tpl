
{if $status eq 1}
    <h3>{ts}{$totalContribution} Contributions Added Into Civi{/ts}</h3>
    <div style="min-height:400px;"> 
        <table class="selector">

            {foreach from=$ids item=row}
                <tr>
                    <td>
                        <a href="/civicrm/contact/view/contribution?reset=1&id={$row.id}&cid={$row.cid}&action=view&context=contribution&selectedChild=contribute">{$row.id}</a>
                    </td>
                </tr>
            {/foreach}
        </table>
    </div>
        
{else}
    <div class="crm-block crm-form-block crm-export-form-block">
    <div class="messages status no-popup">
    <div class="icon inform-icon"></div>&nbsp;
        {ts}Are you sure you want to continue?{/ts} {ts}The contribution(s) and all related data will be added to CiviCRM.{/ts}
    </div>
    <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
    </div>
    </div>  
{/if}

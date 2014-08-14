<div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    {if $smarty.get.state eq 'done'}
    <div class="help">
      {ts}Sync completed with result counts as:{/ts}<br/> 
      <table class="form-layout-compressed bold">
      <tr><td>{ts}Added Contributions to CiviCRM{/ts}:</td><td>{$stats.Added}</td></tr>
      <tr><td>{ts}New Direct Debit{/ts}:</td><td>{$stats.New}</td></tr>
      <tr><td>{ts}Cancelled Direct Debit{/ts}:</td><td>{$stats.Canceled}</td></tr>
      <tr><td>{ts}Failed Direct Debit{/ts}:</td><td>{$stats.Failed}</td></tr>
      <tr><td>{ts}Not Handled smart debit record{/ts}:</td><td>{$stats.Not_Handled}&nbsp; (not found in civiCRM)</td></tr>
      <tr><td>{ts}Live Direct Debit And Matching record found in civiCRM{/ts}:</td><td>{$stats.Live}</td></tr>
      <tr colspan=2><td>{ts}Total{/ts}:</td><td>{$stats.Total}</td></tr>
      </table>
    </div>
  {/if}
  
    <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
  </div>
    </div>
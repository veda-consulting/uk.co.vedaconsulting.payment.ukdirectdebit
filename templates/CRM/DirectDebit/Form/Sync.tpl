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
{else}
    <div class="If you ran the SmartDebit Scheduled Sync Job interactively you would see the results here.  Click the button to run the job now."</div>
    <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
        <h2>Run Smart Debit Sync Job</h2>
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
  </div>
{/if}
    </div>
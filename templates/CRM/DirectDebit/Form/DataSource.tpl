<h3>{ts}Select the date you wish to import data for:{/ts}</h3>
<div class="crm-block crm-form-block crm-export-form-block">
  <div class="description">
    <p>{ts}CiviCRM will attempt to retrieve AUDDIS and ARUDD records for a 1 month period ending with the date you specify here.{/ts}</p>
  </div>
    <div class="help">
        {ts}This should not normally be necessary as the collection reports are retrieved daily by the SmartDebit scheduled job.
        If you specify a date here the collection report data will be cleared and one month of data re-synced from SmartDebit.
        If you don't specify a date the cached data will not be modified.{/ts}<br/>
        <strong>{ts}If you are importing latest payments (up to a month old) you should not enter a date here.{/ts}</strong>
    </div>
  <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl"}
    </div>
  </div>

<div class="crm-block crm-form-block" >
<div class="label">Collection Date: </div>
<div class="content">
      <input id="collection_date" name="collection_date" type="text" value="{$collection_date}"/>
</div>

      <script type="text/javascript">
          {literal}
      // Date picker
      var dateOptions = {
      dateFormat: 'yy-mm-dd',
      changeMonth: true,
      changeYear: true,
      };
      cj('#collection_date').addClass('dateplugin');
      cj('#collection_date').datepicker(dateOptions);
        </script>
        {/literal}
    </table>
</div><br />
<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
 </div>

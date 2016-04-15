<div class="crm-block crm-form-block crm-export-form-block">
    <div class="help">
        <p>
        {ts}Pull Smart Debit Payments into Civi{/ts}
        </p>
    </div>
   {if ($groupCount > 0)}
        <div id="id-additional" class="form-item">
        <div class="crm-accordion-wrapper ">
         <div class="crm-accordion-header">
         {ts}Include Auddis Date(s){/ts}
         </div><!-- /.crm-accordion-header -->
         <div class="crm-accordion-body">
          {strip}

          <table>
          {if $groupCount > 0}
            <tr class="crm-mailing-group-form-block-includeGroups"><td class="label">{$form.includeAuddisDate.label}</td></tr>
            <tr class="crm-mailing-group-form-block-includeGroups"><td>{$form.includeAuddisDate.html}</td></tr>
          {/if}

          </table>

          {/strip}
         </div><!-- /.crm-accordion-body -->
        </div><!-- /.crm-accordion-wrapper -->
    {/if}
    <br /><br />
    {if ($groupCountArudd > 0)}
        <div id="id-additional" class="form-item">
        <div class="crm-accordion-wrapper ">
         <div class="crm-accordion-header">
         {ts}Include Arudd Date(s){/ts}
         </div><!-- /.crm-accordion-header -->
         <div class="crm-accordion-body">
          {strip}

          <table>
          {if $groupCountArudd > 0}
            <tr class="crm-mailing-group-form-block-includeGroups"><td class="label">{$form.includeAruddDate.label}</td></tr>
            <tr class="crm-mailing-group-form-block-includeGroups"><td>{$form.includeAruddDate.html}</td></tr>
          {/if}

          </table>

          {/strip}
         </div><!-- /.crm-accordion-body -->
        </div><!-- /.crm-accordion-wrapper -->
    {/if}
    <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
        <div class="crm-submit-buttons">
          {include file="CRM/common/formButtons.tpl"}
        </div>
    </div>
</div>
    {literal}
    <script type="text/javascript">
    cj(function() {
       cj().crmAccordions();
    });
    </script>
    {/literal}
</div>
    
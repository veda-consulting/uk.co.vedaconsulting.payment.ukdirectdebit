{include file="CRM/DirectDebit/Form/SyncSd.tpl"}
{literal}
    <script type="text/javascript">
    cj(function() {
       cj('p').replaceWith(
           {/literal}
           {if $groupCount > 0}
           'Select auddis dates which you do not want to process during smart debit sync process'
           {else}
           'No auddis dates available'
           {/if}
           {literal}
       );
    });
    </script>
{/literal}
    
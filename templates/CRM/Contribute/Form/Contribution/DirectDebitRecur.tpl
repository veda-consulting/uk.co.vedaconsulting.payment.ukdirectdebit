{if $installments}
  {if $frequency_interval > 1}
    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit 3=$installments}I want to contribute this amount every %1 %2s for %3 installments.{/ts}</strong></p>
  {else}
    <p><strong>{ts 1=$frequency_unit 2=$installments}I want to contribute this amount every %1 for %2 installments.{/ts}</strong></p>
  {/if}
{else}
  {if $frequency_interval > 1}
    <p><strong>{ts 1=$frequency_interval 2=$frequency_unit}I want to contribute this amount every %1 %2s.{/ts}</strong></p>
  {else}
    <p><strong>{ts 1=$frequency_unit }I want to contribute this amount every %1.{/ts}</strong></p>
  {/if}
{/if}

<p><strong>{ts 1=$direct_debit_details.formatted_preferred_collection_day 2=$frequency_unit 3=$direct_debit_details.first_collection_date|crmDate}Your preferred collection day is the %1 of every %2 and your first collection will be on or after the %3.{/ts}</strong></p>
<p><strong>{ts 1=$direct_debit_details.confirmation_method}Your confirmation will be sent by %1.{/ts}</strong></p>
<p><strong>{ts 1=$direct_debit_details.company_name}The company name which will appear on your bank statement against the Direct Debit will be "%1".{/ts}</strong></p>
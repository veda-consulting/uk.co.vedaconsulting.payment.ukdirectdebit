<div class="crm-group credit_card-group">
    <div class="header-dark">
        {ts}Direct Debit Information{/ts}
    </div>
    <div class="display-block">
        <div><span style="float: right;margin: 25px;"><img src="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit_small.png}" alt="Direct Debit Logo" border="0"></span></div>
        <div class="clear"></div>
        <table>
            <tr><td>{ts}Account Holder{/ts}:</td><td>{$account_holder}</td></tr>
            <tr><td>{ts}Bank Account Number{/ts}:</td><td>{$bank_account_number}</td></tr>
            <tr><td>{ts}Bank Identification Number{/ts}:</td><td>{$bank_identification_number}</td></tr>
            <tr><td>{ts}Bank Name{/ts}:</td><td>{$direct_debit_details.bank_name}</td></tr>
            {if ((isset($direct_debit_details.branch)) && ($direct_debit_details.branch != ''))}
            <tr><td>{ts}Branch{/ts}:</td><td>{$direct_debit_details.branch}</td></tr>
            <tr><td>{ts}Address{/ts}:</td><td>
                    {if ($direct_debit_details.address1 != '')} {$direct_debit_details.address1}<br/> {/if}
                    {if ($direct_debit_details.address2 != '')} {$direct_debit_details.address2}<br/> {/if}
                    {if ($direct_debit_details.address3 != '')} {$direct_debit_details.address3}<br/> {/if}
                    {if ($direct_debit_details.address4 != '')} {$direct_debit_details.address4}<br/> {/if}
                    {if ($direct_debit_details.town != '')    } {$direct_debit_details.town    }<br/> {/if}
                    {if ($direct_debit_details.county != '')  } {$direct_debit_details.county  }<br/> {/if}
                    {if ($direct_debit_details.postcode != '')} {$direct_debit_details.postcode}      {/if}
                    {/if}
                </td></tr>
        </table>
    </div>
    <div class="crm-group debit_agreement-group">
        <div class="header-dark">
            {ts}Agreement{/ts}
        </div>
        <div class="display-block">
            {ts}Your account data will be used to charge your bank account via direct debit. While submitting this form you agree to the charging of your bank account via direct debit.{/ts}
        </div>
    </div>
</div>
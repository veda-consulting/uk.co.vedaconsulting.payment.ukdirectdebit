<!-- MV: Custom changes from older version,  -->
{if $paymentProcessor.payment_type & 2}
    <div><span style="float: right;margin: 25px;"><img src="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit_small.png}" alt="Direct Debit Logo" border="0"></span></div>
    <div style="clear: both;"></div>
    {ts}<p>All the normal Direct Debit safeguards and guarantees apply.
        No changes in the amount, date or frequency to be debited can be made without notifying you at least 10 working days in advance of your account being debited.
        In the event of any error, you are entitled to an immediate refund from your bank or building society.
        You have the right to cancel a Direct Debit Instruction at any time simply by writing to your bank or building society, with a copy to us.</p>
        <p>In order to set up your Direct Debit Instruction on-line you will need to provide the following information through the setting up procedure (your cheque book contains all the bank details that you require):</p>
        <p>Bank or Building Society name and account number, sort code and branch address.</p>
        <ul>
            <li>If you are not the account holder, a paper Direct Debit Instruction will be sent for completion. Please click to end</li>
            <li>If this is a personal account continue with the set-up procedure</li>
            <li>If it is a business account and more than one person is required to authorise debits on this account, a paper Direct Debit Instruction will be sent to the Payers for completion.</li>
        </ul>

        <p>Alternatively you can print off your on-screen Direct Debit Instruction and post it to us: <b>{$company_address.company_name}</b>, {if ($company_address.address1 != '')} {$company_address.address1}, {/if}{if ($company_address.address2 != '')} {$company_address.address2}, {/if}{if ($company_address.address3 != '')} {$company_address.address3}, {/if}{if ($company_address.address4 != '')} {$company_address.address4}, {/if}{if ($company_address.town != '')} {$company_address.town}, {/if}{if ($company_address.county != '')} {$company_address.county}, {/if}{if ($company_address.postcode != '')} {$company_address.postcode}{/if}. If you are unable to print please contact us on {$telephoneNumber} (tel no) and we will post you a paper Direct Debit Instruction.
            If you do not wish to proceed any further please <a href="/">click here</a> to end.</p>
        <p>The details of your Direct Debit Instruction will be sent to you within 3 working days or no later than 10 working days before the first collection.</p>{/ts}
{/if}
<!-- MV: end custom changes    -->
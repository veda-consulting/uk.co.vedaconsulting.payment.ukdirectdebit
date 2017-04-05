<div class="crm-group credit_card-group">
    <div class="header-dark">
            {ts}Direct Debit Information{/ts}
    </div>
    <div>{ts}Thank you very much for your Direct Debit Instruction details. Below is the Direct Debit Guarantee for your information.{/ts}</div>
    <div>Please <a href="javascript:window.print()" title="Print this page.">PRINT THIS PAGE</a> for you records</div>
    <div class="display-block">
        {* Start of DDI *}
        <div style="float: left;border: 1px solid #000000;background-color: #ffffff;width: 100%;">

            <div style="text-align: center;">
                {*        <div><span id="logo1"><img src="client_logo.jpg" alt="Client Logo" border="0"></span></div> *}
                <div><span style="float: right;margin: 25px;"><img src="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit_small.png}" alt="Direct Debit Logo" border="0"></span></div>
                <div style="clear: both;"></div>
            </div>

            <div style="float: left;margin-left: 5px;margin-right: 10px;width: 305px;">

                <p>
                <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
                    <b>{$company_address.company_name}</b><br>
                    {if ($company_address.address1 != '')} {$company_address.address1}<br/> {/if}
                    {if ($company_address.address2 != '')} {$company_address.address2}<br/> {/if}
                    {if ($company_address.address3 != '')} {$company_address.address3}<br/> {/if}
                    {if ($company_address.address4 != '')} {$company_address.address4}<br/> {/if}
                    {if ($company_address.town != '')    } {$company_address.town}<br/>     {/if}
                    {if ($company_address.county != '')  } {$company_address.county}<br/>   {/if}
                    {if ($company_address.postcode != '')} {$company_address.postcode}      {/if}
                </div>
                </p>

                <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Name(s) of Account Holder(s)</h2>

                <p>
                <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
                    {$account_holder}<br />
                </div>
                </p>

                <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Bank/Building Society Account Number</h2>

                <p>
                <table style="background-color: #ffffff;border: 1px solid #000000;border-collapse: collapse;" summary="Branch Sort Code">
                    <tr>
                        <td style="border: 1px solid #000000;padding: 0;width: 240px;height: 30px;text-align: left;">{$bank_account_number}</td>
                    <tr>
                </table>
                </p>

                <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Branch Sort Code</h2>

                <p>
                <table style="background-color: #ffffff;border: 1px solid #000000;border-collapse: collapse;" summary="Branch Sort Code">
                    <tr>
                        <td style="border: 1px solid #000000;padding: 0;width: 180px;height: 30px;text-align: left;">{$bank_identification_number}</td>
                    <tr>
                </table>
                </p>

                <p>
                <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
                    <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">To the Manager<span style="margin-left: 4em;">Bank/Building Society</span></span></div>
                    <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><br />{$direct_debit_details.bank_name}</div>
                    <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">Branch</span><span style="margin-left: 3em;">{$direct_debit_details.branch}</span></div>
                    <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">Address</span></div>

                    {if ($direct_debit_details.address1 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address1}<br/></div> {/if}
                    {if ($direct_debit_details.address2 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address2}<br/></div> {/if}
                    {if ($direct_debit_details.address3 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address3}<br/></div> {/if}
                    {if ($direct_debit_details.address4 != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.address4}<br/></div> {/if}
                    {if ($direct_debit_details.town != '')    } <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.town    }<br/></div> {/if}
                    {if ($direct_debit_details.county != '')  } <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.county  }<br/></div> {/if}
                    {if ($direct_debit_details.postcode != '')} <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;">{$direct_debit_details.postcode}<br/></div> {/if}

                </div>
                </p>


            </div> <!-- <div id="column1"> -->

            <div style="float: right;margin-right: 5px;width: 305px;">

                <h1 style="font-size: 1.3em;margin-top: 0;text-align: left;margin: 0% 0%;">Instruction to your Bank or Building Society to pay by Direct Debit</h1>

                <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Service User Number</h2>

                <p>
                <table style="background-color: #ffffff;border: 1px solid #000000;border-collapse: collapse;" summary="Branch Sort Code">
                    <tr>
                        {foreach from=$service_user_number item=singleDigit}
                            <td style="border: 1px solid #000000;padding: 0;width: 30px;height: 30px;text-align: center;">{$singleDigit}</td>
                        {/foreach}
                    <tr>
                </table>
                </p>

                <h2 style="font-size: 1em;margin-bottom: -5px; margin-top: 15px;text-align: left;font-weight: bold;">Reference:</h2>

                <p>
                <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
                    {$trxn_id}
                </div>
                </p>


                <h2 style="font-size: 1em;text-align: left;font-weight: bold;margin-bottom: 3px; margin-top: 15px;">Instruction to your Bank or Building Society</h2>

                <p>
                    Please pay {$company_address.company_name} Direct Debits from the account detailed in this Instruction subject to the safeguards assured by the Direct Debit Guarantee. I understand that this Instruction may remain with {$company_address.company_name} and, if so, details will be passed electronically to my Bank / Building Society.
                </p>

                <p>
                <div style="background-color: #ffffff;border: 1px solid #999999;padding: 0px 5px;">
                    <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"><span style="font-weight: bold;">Date</span><span style="margin-left: 1em;">{$directDebitDate|crmDate}</span></div>
                    <div style="border-bottom: 1px solid #dddddd;margin-top: 15px;"></div>
                </div>
                </p>

            </div> <!-- <div id="column2"> -->

            <div style="clear: both;"></div>

            <div>
                <p style="text-align: center;">
                    Banks and Building Societies may not accept Direct Debit Instructions from some types of account.
                </p>
            </div>
        </div> <!-- <div id="directDebitInstructions"> -->
        {* End of DDI *}

        <div style="clear: both;"></div>

        <TABLE WIDTH="620" CELLPADDING="2" CELLSPACING="0" BORDER="1" RULES="NONE">
            <TR>
                <TD WIDTH="580" VALIGN=TOP>
                    <P ALIGN=CENTER>
                        <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="5"><B>The Direct Debit Guarantee</B></FONT><FONT SIZE="6"> </FONT></FONT><!-- $MVD$:picsz("894","306") --><IMG SRC="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit_small.png}" ALIGN=TOP WIDTH="107" HEIGHT="37" VSPACE="0" HSPACE="0" ALT="direct debit logo" BORDER="0" LOOP="0"></TD>
                <TD WIDTH="20" VALIGN=TOP></TD>
            </TR>
            <TR>
                <TD WIDTH="94%" VALIGN=TOP>
                    <P ALIGN=LEFT>
                        <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
                                {ts}This Guarantee is offered by all banks and building societies that accept instructions to pay Direct Debits.{/ts}
                            </FONT></FONT><BR>
                        <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
                                {ts}If there are any changes to the amount, date or frequency of your Direct Debit {$company_address.company_name} will notify you 10 working days in advance of your account being debited or as otherwise agreed. If you request {$company_address.company_name} to collect a payment, confirmation of the amount and date will be given to you at the time of the request.{/ts}
                            </FONT></FONT><BR>
                        <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
                                {ts}If an error is made in the payment of your Direct Debit, by {$company_address.company_name} or your bank or building society, you are entitled to a full and immediate refund of the amount paid from your bank or building society.{/ts}
                            </FONT></FONT><BR>
                        <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
                                {ts}If you receive a refund you are not entitled to, you must pay it back when {$company_address.company_name} asks you to.{/ts}
                            </FONT></FONT><BR>
                        <FONT FACE="Arial,Helvetica,Monaco"><FONT SIZE="3">&#149;</FONT><FONT SIZE="1">
                                {ts}You can cancel a Direct Debit at any time by simply contacting your bank or building society. Written confirmation may be required. Please also notify us.{/ts}
                            </FONT></FONT><BR>
                <TD WIDTH="20" VALIGN=TOP></TD>
            </TR>
        </TABLE>
    </div>
</div>
CRM.$(function($) {
    var smartDetails = CRM.vars.ukdirectdebit.recurdetails;
    if (!CRM.$.isEmptyObject(smartDetails)) {
        var targetHtml = '<h3>View Smart Debit Payment</h3><table class = "crm-info-panel direct-debit">';
        for (var k in smartDetails) {
            if (smartDetails.hasOwnProperty(k)) {
                targetHtml = targetHtml.concat('<tr><td class="label">'+k+'</td><td>' +smartDetails[k] + '</td></tr>');
            }
        }
        targetHtml = targetHtml.concat('</table>');
        CRM.$( ".crm-info-panel" ).after( targetHtml );
    }
});
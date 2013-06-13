<div class="messages status">
    <p>{include file="CRM/Member/Form/Task.tpl"}</p>
    <p>Warning : This process will renew all the selected membrships and generate a contribution record for each member. The cost of the contribution will be taken from the membership at its present time.</p>
    <p>{$form.membership_contribution_date.label}  {$form.membership_contribution_date.html}</p>
    <p>{$form.description.label}  {$form.description.html}</p>
    <p>{$form.activity_date.label} {include file="CRM/common/jcalendar.tpl" elementName=activity_date}</p>
</div>
<div class="form-item crm-block crm-form-block crm-contribution-form-block">
    <p>
    {$form.buttons.html}
    </p>
</div>

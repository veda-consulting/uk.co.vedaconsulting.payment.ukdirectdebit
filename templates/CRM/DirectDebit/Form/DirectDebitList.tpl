{*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}

<h3>{ts}Title of Form{/ts}</h3>

<div style="min-height:400px;"> 
        
    <table class="selector">
        <tr style="background-color: #CDE8FE;">
           <td><b>{ts}Id{/ts}</b></td>
           <td><b>{ts}Name{/ts}</td>
           <td><b>{ts}Value{/ts}</td>
           <td></td>
        </tr>

        {foreach from=$directDebitArray item=row}
            {assign var=id value=$row.id}
            <tr>
                <td>{$row.id}</td>
                <td>{$row.name}</td>
                <td>{$row.value}</td>
                <td><a href="{crmURL p="civicrm/directdebit/process" q="sid=$id&reset=1"}">Edit</a></td>
             </tr>
         {/foreach}  

     </table>
   
</div>

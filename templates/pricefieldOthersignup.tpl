{* Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com> *}
{* Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt) *}

{crmScope extensionKey='com.aghstrategies.eventmembershipsignup'}
{crmScript ext=com.aghstrategies.eventmembershipsignup file=js/pricefieldOthersignup.js}
<table class="deleteme"><tr>
{foreach from=$selectors item=i}
  <td id="othersignup-group-{$i}" class='othersignup-group'>
    {$form.othersignup[$i].html}
    <span class="membershipselect">{$form.membershipselect[$i].html}</span>
    <span class="eventselect">{$form.eventselect[$i].html}</span>
  </td>
{/foreach}
</tr></table>
{/crmScope}

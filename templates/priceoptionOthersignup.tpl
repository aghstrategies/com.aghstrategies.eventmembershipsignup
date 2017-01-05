{* Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com> *}
{* Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt) *}

{crmScope extensionKey='com.aghstrategies.eventmembershipsignup'}
{crmScript ext=com.aghstrategies.eventmembershipsignup file=js/priceoptionOthersignup.js}

<table class="deleteme">
  <tbody id="other-sign-up">
  <tr id="other-sign-up-header"><td colspan=2><h3>{ts}Sign Up for Other Membership or Event{/ts}</h3></td></tr>
  <tr class="crm-price-option-other-sign-up">
      <td class="label"><label>{ts}Additional Signup?{/ts}</label></td>
    <td id="othersignup-td">{$form.othersignup.html}</td>
  </tr>
  <tr class="crm-price-option-membershiptype">
    <td class="label"><label>{ts}Membership Type{/ts}</label></td>
    <td>{$form.membershipselect.html}</td>
  </tr>
  <tr class="crm-price-option-event">
    <td class="label"><label>{ts}Event{/ts}</label></td>
    <td>{$form.eventselect.html}</td>
  </tr>
  </tbody>
</table>
{/crmScope}

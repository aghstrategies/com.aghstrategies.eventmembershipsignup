{* template block that contains the new field *}
<table id="other-sign-up">
<tr class="othersignup-field-tr">
{foreach from=$selectors item=i}
td
< class="label"><label>Other Sign Up?</label></td>
  <td>{$form.othersignup[$i].html}</td>
</tr>
<tr>
  <td class="label"><label>Membership Type</label></td>
  <td>{$form.membershipselect[$i].html}</td>
</tr>
</table>
{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
   cj('#membership_select_'+{$i}+'').insertAfter('#option_status['+{$i}+']');
</script>
{/foreach}

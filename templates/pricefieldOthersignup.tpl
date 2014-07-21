{* template block that contains the new field *}
<table id="other-sign-up">
<tr class="othersignup-field-tr">
  <th id="othersignup-header">Other Sign Up?</th>

{foreach from=$selectors item=i}
  <td>{$form.othersignup[$i].html}</td>
</tr>
<tr>
  <td id="membership_row[{$i}]">{$form.membershipselect[$i].html}</td>
</tr>
<tr>
  <td id="event_row[{$i}]">{$form.eventselect[$i].html}</td>
</tr>
{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
{literal}
CRM.$(function ($) {
{/literal}
  var i = {$i};
{literal}
   var weightHeaderSelector = cj('#optionField').children('tbody').children('tr').filter(':first').children('th').filter(':nth-child(8n)');
   cj('#othersignup-header').insertAfter(weightHeaderSelector);
   cj('#eventselect_'+i).insertAfter(cj('#option_weight_'+i).parent());
   cj('#membershipselect_'+i).insertAfter(cj('#option_weight_'+i).parent());
   cj('#othersignup_'+i).insertAfter(cj('#option_weight_'+i).parent());
   cj('#othersignup_'+i).change(function(){
      if (cj(this).val()=='Membership'){
       cj('#membershipselect_'+i).show();
       cj('#eventselect_'+i).hide();
      }
      else if (cj(this).val()=='Participant'){
       cj('#membershipselect_'+i).hide();
       cj('#eventselect_'+i).show();
      }
      else if(cj(this).val()==0){
       cj('#membershipselect_'+i).hide();
       cj('#eventselect_'+i).hide();
      }
   });
      if (cj('#othersignup_'+i).val()=='Membership'){
       cj('#membershipselect_'+i).show();
       cj('#eventselect_'+i).hide();
      }
      else if (cj('#othersignup_'+i).val()=='Participant'){
       cj('#membershipselect_'+i).hide();
       cj('#eventselect_'+i).show();
      }
      else if(cj('#othersignup_'+i).val()==0){
       cj('#membershipselect_'+i).hide();
       cj('#eventselect_'+i).hide();
      }
  });
{/literal}
</script>
{/foreach}
</table>

{crmScope extensionKey='com.aghstrategies.eventmembershipsignup'}
{* template block that contains the new field *}
<table id="other-sign-up">
<tr class="othersignup-field-tr">
  <th id="othersignup-header">{ts}Additional Signup?{/ts}</th>

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
cj(function ($) {
{/literal}
  var i = {$i};
{literal}
   var weightHeaderSelector = $('#optionField').children('tbody').children('tr').filter(':first').children('th').filter(':nth-child(8n)');
   $('#othersignup-header').insertAfter(weightHeaderSelector);
   $('#eventselect_'+i).insertAfter($('#option_weight_'+i).parent());
   $('#membershipselect_'+i).insertAfter($('#option_weight_'+i).parent());
   $('#othersignup_'+i).insertAfter($('#option_weight_'+i).parent());
   $('#othersignup_'+i).change(function(){
      if ($(this).val()=='Membership'){
       $('#membershipselect_'+i).show();
       $('#eventselect_'+i).hide();
      }
      else if ($(this).val()=='Participant'){
       $('#membershipselect_'+i).hide();
       $('#eventselect_'+i).show();
      }
      else if($(this).val()==0){
       $('#membershipselect_'+i).hide();
       $('#eventselect_'+i).hide();
      }
   });
      if ($('#othersignup_'+i).val()=='Membership'){
       $('#membershipselect_'+i).show();
       $('#eventselect_'+i).hide();
      }
      else if ($('#othersignup_'+i).val()=='Participant'){
       $('#membershipselect_'+i).hide();
       $('#eventselect_'+i).show();
      }
      else if($('#othersignup_'+i).val()==0){
       $('#membershipselect_'+i).hide();
       $('#eventselect_'+i).hide();
      }
  });
{/literal}
</script>
{/foreach}
</table>
{/crmScope}

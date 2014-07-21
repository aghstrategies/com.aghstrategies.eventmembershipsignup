<h3 id="other-sign-up-header">Sign Up for Other Membership or Event</h3>
<table id="other-sign-up-table">
  <tbody id="other-sign-up">
  <tr class="crm-price-option-other-sign-up">
      <td class="label"><label>Other Sign Up?</label></td>
    <td id="othersignup-td">{$form.othersignup.html}</td>
  </tr>
  <tr class="crm-price-option-membershiptype">
    <td class="label"><label>Membership Type</label></td>
    <td>{$form.membershipselect.html}</td>
  </tr>
  <tr class="crm-price-option-event">
    <td class="label"><label>Event</label></td>
    <td>{$form.eventselect.html}</td>
  </tr>
  </tbody>
</table>
<script type="text/javascript">
var optionSignUp = {$option_signup_id};
var signupselectvalue = "{$signupselectvalue}";
var eventmembershipvalue = {$eventmembershipvalue};
{literal}
var signupKeys = {MembershipType:'Membership', Event:'Participant'};
   if (optionSignUp){
     cj('select#othersignup').val(signupKeys[signupselectvalue]);
     if (signupKeys[signupselectvalue]=='Membership'){
       cj('#membershipselect').val(eventmembershipvalue);
     }
     else if (signupKeys[signupselectvalue]=='Participant'){
       cj('#eventselect').val(eventmembershipvalue);
     }
   }
   cj('#other-sign-up').insertAfter('.crm-price-option-form-block-is_default');
   cj('#other-sign-up-header').insertAfter('.crm-price-option-form-block-is_default');
   cj('#othersignup').change(function(){
      if (cj('#othersignup').val()=='Membership'){
       cj('.crm-price-option-membershiptype').show();
       cj('.crm-price-option-event').hide();
      }
      else if (cj('#othersignup').val()=='Participant'){
       cj('.crm-price-option-membershiptype').hide();
       cj('.crm-price-option-event').show();
      }
      else if(cj('#othersignup').val()==0){
       cj('.crm-price-option-membershiptype').hide();
       cj('.crm-price-option-event').hide();

      }
   });
      if (cj('#othersignup').val()=='Membership'){
       cj('.crm-price-option-membershiptype').show();
       cj('.crm-price-option-event').hide();
      }
      else if (cj('#othersignup').val()=='Participant'){
       cj('.crm-price-option-membershiptype').hide();
       cj('.crm-price-option-event').show();
      }
      else if(cj('#othersignup').val()==0){
       cj('.crm-price-option-membershiptype').hide();
       cj('.crm-price-option-event').hide();
      }
</script>
{/literal}

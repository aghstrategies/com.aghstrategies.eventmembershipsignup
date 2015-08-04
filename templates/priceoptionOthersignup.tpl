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
{literal}
cj(function($){
{/literal}
  var optionSignUp = {$option_signup_id};
  var signupselectvalue = "{$signupselectvalue}";
  var eventmembershipvalue = {$eventmembershipvalue};
{literal}
  var signupKeys = {MembershipType:'Membership', Event:'Participant'};
  if (optionSignUp){
   $('select#othersignup').val(signupKeys[signupselectvalue]);
   if (signupKeys[signupselectvalue]=='Membership'){
     $('#membershipselect').val(eventmembershipvalue);
   }
   else if (signupKeys[signupselectvalue]=='Participant'){
     $('#eventselect').val(eventmembershipvalue);
   }
  }
  $('#other-sign-up').insertAfter('.crm-price-option-form-block-is_default');
  $('#other-sign-up-header').insertAfter('.crm-price-option-form-block-is_default');
  $('#othersignup').change(function(){
    if ($('#othersignup').val()=='Membership'){
     $('.crm-price-option-membershiptype').show();
     $('.crm-price-option-event').hide();
    }
    else if ($('#othersignup').val()=='Participant'){
     $('.crm-price-option-membershiptype').hide();
     $('.crm-price-option-event').show();
    }
    else if($('#othersignup').val()==0){
     $('.crm-price-option-membershiptype').hide();
     $('.crm-price-option-event').hide();

    }
  });
  if ($('#othersignup').val()=='Membership'){
   $('.crm-price-option-membershiptype').show();
   $('.crm-price-option-event').hide();
  }
  else if ($('#othersignup').val()=='Participant'){
   $('.crm-price-option-membershiptype').hide();
   $('.crm-price-option-event').show();
  }
  else if($('#othersignup').val()==0){
   $('.crm-price-option-membershiptype').hide();
   $('.crm-price-option-event').hide();
  }
});
</script>
{/literal}

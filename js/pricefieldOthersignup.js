cj(function ($) {
  function showHide(obj) {
    if ($(obj).val() == 'Membership') {
      $(obj).siblings('.membershipselect').show();
      $(obj).siblings('.eventselect').hide();
    }
    else if ($(obj).val() == 'Participant') {
      $(obj).siblings('.membershipselect').hide();
      $(obj).siblings('.eventselect').show();
    }
    else {
      $(obj).siblings('.membershipselect').hide();
      $(obj).siblings('.eventselect').hide();
    }
  }
  $('#optionField tr:eq(0) th:nth-last-child(2)').after('<th id="othersignup-header">'+ts('Additional Signup?', {'domain': 'com.aghstrategies.eventmembershipsignup'})+'</th>');
  $('#optionField tr:gt(0)').each( function(index) {
    $(this).children('td:nth-last-child(2)').after($('#othersignup-group-'+(index+1)));
  });
  $('.othersignup-group select[name^="othersignup["]').each( function() {
    showHide($(this));
    $(this).change( function() {
      showHide($(this));
    });
  });
  $('.deleteme').remove();
});

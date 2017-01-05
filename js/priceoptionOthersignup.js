/**
 * Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

cj(function($){
  function showHide(obj) {
    if ($(obj).val() == 'Membership') {
      $('.crm-price-option-membershiptype').show();
      $('.crm-price-option-event').hide();
    }
    else if ($(obj).val() == 'Participant') {
      $('.crm-price-option-membershiptype').hide();
      $('.crm-price-option-event').show();
    }
    else {
      $('.crm-price-option-membershiptype').hide();
      $('.crm-price-option-event').hide();
    }
  }
  $('#other-sign-up>*').insertAfter('.crm-price-option-form-block-is_default');
  showHide($('#othersignup'));
  $('#othersignup').change(function(){
    showHide($('#othersignup'));
  });
  $('.deleteme').remove();
});

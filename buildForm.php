<?php
//edits forms for price option and price field so that other sign up can be selected
$entityOptions = array(
  0 => 'No',
  'Membership' => 'Membership',
  'Participant' => 'Participant',
);

if ($formName == 'CRM_Price_Form_Field' && is_null($form->getVar('_fid'))) {
  $membershipSelector = array();
  $eventSelector = array();
  $result = civicrm_api3('MembershipType', 'get', array(
    'sequential' => 1,
  ));
  foreach ($result['values'] as $membershipType) {
    $membershipSelector[$membershipType['id']] = $membershipType['name'] . ': ' . $membershipType['minimum_fee'];
  }
  $result = civicrm_api3('Event', 'get', array(
    'sequential' => 1,
    ));
  foreach ($result['values'] as $event) {
    $eventSelector[$event['id']] = $event['title'];
  }
  $numOptions = $form::NUM_OPTION;
  $selectors = array();
  for ($i = 1; $i <= $numOptions; $i++) {
    // Add the field element in the form
    $form->add('select', "othersignup[$i]", ts('Other Sign Up?'), $entityOptions);
    $form->add('select', "membershipselect[$i]", ts('Select Membership Type'), $membershipSelector);
    $form->add('select', "eventselect[$i]", ts('Select Event'), $eventSelector);
    $selectors[] = $i;
  }
  $form->assign('numOptions', $numOptions);
  $form->assign('selectors', $selectors);

  // Assumes templates are in a templates folder relative to this file
  $templatePath = realpath(dirname(__FILE__) . "/templates");
  // Add the field element in the form
  // dynamically insert a template block in the page
  $form->add('select', 'memberSelect', '');
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => "{$templatePath}/pricefieldOthersignup.tpl"
    ));

}
elseif ($formName == 'CRM_Price_Form_Option') {
  $id = $form->getVar('_oid');
  if (!is_null($id)) {
    $form->assign('option_signup_id', 0);
    $form->assign('signupselectvalue', 0);
    $form->assign('eventmembershipvalue', 0);
    $sql = "SELECT id, entity_table, entity_ref_id FROM civicrm_option_signup WHERE price_option_id = {$id};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $form->assign('option_signup_id', $dao->id);
      $form->assign('signupselectvalue', $dao->entity_table);
      $form->assign('eventmembershipvalue', $dao->entity_ref_id);
    }
  }

  $membershipSelector = array();
  $eventSelector = array();
  $result = civicrm_api3('MembershipType', 'get', array(
    'sequential' => 1,
    ));
  foreach ($result['values'] as $membershipType) {
    $membershipSelector[$membershipType['id']] = $membershipType['name'] . ': ' . $membershipType['minimum_fee'];
  }
  $result = civicrm_api3('Event', 'get', array(
    'sequential' => 1,
    ));
  foreach ($result['values'] as $event) {
    $eventSelector[$event['id']] = $event['title'];
  }
  // Add the field element in the form
  $form->add('select', 'othersignup', ts('Other Sign Up?'), $entityOptions);
  $form->add('select', 'membershipselect', ts('Select Membership Type'), $membershipSelector);
  $form->add('select', 'eventselect', ts('Select Event'), $eventSelector);
  // Assumes templates are in a templates folder relative to this file
  $templatePath = realpath(dirname(__FILE__) . "/templates");
  // dynamically insert a template block in the page
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => "{$templatePath}/priceoptionOthersignup.tpl"
    ));

}

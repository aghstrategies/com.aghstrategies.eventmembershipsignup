<?php
/**
 * @file
 * Handles saving the additional signups corresponding to price options.
 */

/**
 * Saves othersignup.
 */
function eventmembershipsignup_save_new_othersignup($price_option_id, $entity_table, $entityRefId) {
  $option_signup_id = 0;
  $sql = "SELECT id FROM civicrm_option_signup WHERE price_option_id = {$price_option_id};";
  $dao = CRM_Core_DAO::executeQuery($sql);
  if ($dao->fetch()) {
    $option_signup_id = $dao->id;
  }
  if ($option_signup_id) {
    $sql = "UPDATE civicrm_option_signup SET entity_ref_id={$entityRefId}, entity_table=\"{$entity_table}\" WHERE id={$option_signup_id};";
  }
  else {
    $sql = "INSERT INTO civicrm_option_signup (price_option_id, entity_table, entity_ref_id) VALUES ({$price_option_id}, \"{$entity_table}\", {$entityRefId});";
  }
  $dao = CRM_Core_DAO::executeQuery($sql);
}

/**
 * Provides option list for entities to add.
 */
function eventmembershipsignup_entity_options() {
  return array(
    0 => ts('No', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
    'Membership' => ts('Membership', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
    'Participant' => ts('Participant', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
  );
}

/**
 * Handle options created upon field creation.
 */
function eventmembershipsignup_field_admin_form(&$form) {
  $membershipSelector = array();
  $eventSelector = array();
  eventmembershipsignup_populate_selects($membershipSelector, $eventSelector);
  $entityOptions = eventmembershipsignup_entity_options();

  $numOptions = $form::NUM_OPTION;
  $selectors = array();
  for ($i = 1; $i <= $numOptions; $i++) {
    // Add the field element in the form.
    $form->add('select', "othersignup[$i]", ts('Additional Signup?', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $entityOptions);
    $form->add('select', "membershipselect[$i]", ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $membershipSelector);
    $form->add('select', "eventselect[$i]", ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $eventSelector);
    $selectors[] = $i;
  }
  $form->assign('numOptions', $numOptions);
  $form->assign('selectors', $selectors);

  // Assumes templates are in a templates folder relative to this file.
  $templatePath = realpath(dirname(__FILE__) . "/templates");
  // Add the field element in the form
  // Dynamically insert a template block in the page.
  $form->add('select', 'memberSelect', '');
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => "{$templatePath}/pricefieldOthersignup.tpl",
  ));
}

/**
 * PostProcess for options created upon field creation.
 */
function eventmembershipsignup_field_admin_postProcess(&$form) {
  // Assume that the only time options are added in bulk is if the field is
  // newly-created.
  $sql = "SELECT id FROM civicrm_price_field ORDER BY id DESC LIMIT 1;";
  $dao = CRM_Core_DAO::executeQuery($sql);
  while ($dao->fetch()) {
    $price_field_id = $dao->id;
  }
  $sql = "SELECT id FROM civicrm_price_field_value WHERE price_field_id=$price_field_id ORDER BY id ASC;";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $price_option_ids = array(0);
  while ($dao->fetch()) {
    $price_option_ids[] = $dao->id;
  }
  $numOptions = count($price_option_ids);
  $othersignups = $form->_submitValues['othersignup'];
  $membershipselects = $form->_submitValues['membershipselect'];
  $eventselects = $form->_submitValues['eventselect'];
  foreach ($form->_submitValues['othersignup'] as $price_option_key => $price_option_othersignup) {
    if ($price_option_othersignup) {
      switch ($price_option_othersignup) {
        case 'Membership':
          $entity_ref_id = $membershipselects[$price_option_key];
          $entity_table = 'MembershipType';
          break;

        case 'Participant':
          $entity_ref_id = $eventselects[$price_option_key];
          $entity_table = 'Event';
          break;

        default:
          continue 2;
      }
      if ($price_option_key <= $numOptions and !is_null($price_option_ids[$price_option_key]) and $price_option_othersignup) {
        eventmembershipsignup_save_new_othersignup($price_option_ids[$price_option_key], $entity_table, $entity_ref_id);
      }
    }
  }
}

/**
 * Handle single option form.
 */
function eventmembershipsignup_option_admin_form(&$form) {
  $id = $form->getVar('_oid');
  $form->assign('option_signup_id', 0);
  $form->assign('signupselectvalue', 0);
  $form->assign('eventmembershipvalue', 0);
  if (!is_null($id)) {
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
  eventmembershipsignup_populate_selects($membershipSelector, $eventSelector);
  $entityOptions = eventmembershipsignup_entity_options();

  // Add the field element in the form.
  $form->add('select', 'othersignup', ts('Other Sign Up?'), $entityOptions);
  $form->add('select', 'membershipselect', ts('Select Membership Type'), $membershipSelector);
  $form->add('select', 'eventselect', ts('Select Event'), $eventSelector);
  // Assumes templates are in a templates folder relative to this file.
  $templatePath = realpath(dirname(__FILE__) . "/templates");
  // Dynamically insert a template block in the page.
  CRM_Core_Region::instance('page-body')->add(array(
    'template' => "{$templatePath}/priceoptionOthersignup.tpl",
  ));
}

/**
 * PostProcess for single options.
 */
function eventmembershipsignup_option_admin_postProcess(&$form) {
  $id = $form->getVar('_oid');
  switch (CRM_Utils_Array::value('othersignup', $form->_submitValues)) {
    case 'Membership':
      eventmembershipsignup_save_new_othersignup($id, 'MembershipType', $form->_submitValues['membershipselect']);
      break;

    case 'Participant':
      eventmembershipsignup_save_new_othersignup($id, 'Event', $form->_submitValues['eventselect']);
      break;

    default:
      // Clean out additional signup that might be in there.
      $option_signup_id = 0;
      $sql = "SELECT id FROM civicrm_option_signup WHERE price_option_id = {$id};";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        $option_signup_id = $dao->id;
      }
      if ($option_signup_id) {
        $sql = "DELETE FROM civicrm_option_signup WHERE id={$option_signup_id};";
        CRM_Core_DAO::executeQuery($sql);
      }
      break;
  }
}

/**
 * Sets up the drop-down options for membership type and events.
 */
function eventmembershipsignup_populate_selects(&$membershipSelector, &$eventSelector) {
  $result = civicrm_api3('MembershipType', 'get', array(
    'sequential' => 1,
    'is_active' => 1,
    'options' => array('sort' => 'weight ASC'),
  ));
  foreach ($result['values'] as $membershipType) {
    $membershipSelector[$membershipType['id']] = $membershipType['name'] . ': ' . $membershipType['minimum_fee'];
  }
  $result = civicrm_api3('Event', 'get', array(
    'sequential' => 1,
    'options' => array('sort' => "start_date DESC"),
    'return' => "title,start_date",
    'is_active' => 1,
  ));
  foreach ($result['values'] as $event) {
    $formattedDate = CRM_Utils_Date::format($event['start_date']);
    $eventSelector[$event['id']] = "{$event['title']} ($formattedDate)";
  }
}

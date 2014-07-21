<?php

require_once 'eventmembershipsignup.civix.php';

/**
 * Implementation of hook_civicrm_buildForm
 */
function eventmembershipsignup_civicrm_buildForm( $formName, &$form ) {
  if ($formName=='CRM_Price_Form_Field'){
    $membershipSelector = array();
    $eventSelector = array();
    $result = civicrm_api3('MembershipType', 'get', array(
      'sequential' => 1,
      ));
    foreach ($result['values'] as $membershipType){
      $membershipSelector[$membershipType['id']] = $membershipType['name'];
    }
    $result = civicrm_api3('Event', 'get', array(
      'sequential' => 1,
      ));
    foreach ($result['values'] as $event){
      $eventSelector[$event['id']] = $event['title'];
    }
    $numOptions = $form::NUM_OPTION;
    $entityOptions = array(0 => 'No', 'Membership' => 'Membership', 'Participant' => 'Participant');
    $selectors = array();
    for ($i = 1; $i <= $numOptions; $i++){
    // Add the field element in the form
      $form->add('select', "othersignup[$i]", ts('Other Sign Up?'), $entityOptions);
      $form->add('select', "membershipselect[$i]", ts('Select Membership Type'), $membershipSelector);
      $form->add('select', "eventselect[$i]", ts('Select Event'), $eventSelector);
      $selectors[] = $i;
    }
    $form->assign('numOptions', $numOptions);
    $form->assign('selectors', $selectors);

    // Assumes templates are in a templates folder relative to this file
    $templatePath = realpath(dirname(__FILE__)."/templates");
    // Add the field element in the form
    // dynamically insert a template block in the page
    $form->add('select', 'memberSelect', '');
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "{$templatePath}/pricefieldOthersignup.tpl"
      ));

  }
  elseif ($formName=='CRM_Price_Form_Option'){
    $id = $form->getVar('_oid');
    $option_signup_id = 0;
    $sql = "SELECT id, entity_table, entity_ref_id FROM civicrm_option_signup WHERE price_option_id = {$id};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()){
      $option_signup_id = $dao->id;
      $signupselectdefault = $dao->entity_table;
      $defaultid = $dao->entity_ref_id;
    }
    $membershipSelector = array();
    $eventSelector = array();
    $result = civicrm_api3('MembershipType', 'get', array(
      'sequential' => 1,
      ));
    foreach ($result['values'] as $membershipType){
      $membershipSelector[$membershipType['id']] = $membershipType['name'];
    }
    $result = civicrm_api3('Event', 'get', array(
      'sequential' => 1,
      ));
    foreach ($result['values'] as $event){
      $eventSelector[$event['id']] = $event['title'];
    }
    $entityOptions = array(0 => 'No', 'Membership' => 'Membership', 'Participant' => 'Participant');
    // Add the field element in the form
    $form->add('select', 'othersignup', ts('Other Sign Up?'), $entityOptions);
    $form->add('select', 'membershipselect', ts('Select Membership Type'), $membershipSelector);
    $form->add('select', 'eventselect', ts('Select Event'), $eventSelector);
    if ($option_signup_id) {
      $defaults['othersignup'] = $signupselectdefault;
      switch ($signupselectdefault){
        case 'MembershipType':
        $defaults['membershipselect'] = $defaultid;
        break;
        case 'Event':
        $defaults['eventselect'] = $defaultid;
        break;
        default:
        break;
      }
      $form->setDefaults($defaults);
    }
    // Assumes templates are in a templates folder relative to this file
    $templatePath = realpath(dirname(__FILE__)."/templates");
    // dynamically insert a template block in the page
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "{$templatePath}/priceoptionOthersignup.tpl"
      ));

  }
}

/**
 * Implementation of hook_civicrm_postProcess
 */
function eventmembershipsignup_civicrm_postProcess( $formName, &$form ) {
  if ($formName=='CRM_Price_Form_Field'){
  }
  elseif ($formName=='CRM_Price_Form_Option'){
    $id = $form->getVar('_oid');
    $option_signup_id = 0;
    $sql = "SELECT id FROM civicrm_option_signup WHERE price_option_id = {$id};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()){
      $option_signup_id = $dao->id;
    }
    if ($form->_submitValues['othersignup']){
      switch ($form->_submitValues['othersignup']){
        case 'Membership':
        $entity_ref_id = $form->_submitValues['membershipselect'];
        $entity_table = 'MembershipType';
        break;
        case 'Participant':
        $entity_ref_id = $form->_submitValues['eventselect'];
        $entity_table = 'Event';
        break;
        default:
        break;
      }
      if ($option_signup_id){
        $sql = "UPDATE civicrm_option_signup SET entity_ref_id={$entity_ref_id}, entity_table=\"{$entity_table}\" WHERE id={$option_signup_id};";
      }
      else{
        $sql = "INSERT INTO civicrm_option_signup (price_option_id, entity_table, entity_ref_id) VALUES ({$id}, \"{$entity_table}\", {$entity_ref_id});";
      }
      $dao = CRM_Core_DAO::executeQuery($sql);
    }
  }
}

/**
 * Implementation of hook_civicrm_post
 */
function eventmembershipsignup_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  if ($op == 'create' && $objectName == 'LineItem') {
    $price_field_value_id = 0;
    $sql = "SELECT * FROM civicrm_option_signup WHERE price_option_id={$objectRef['price_field_value_id']};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()){
      $option_signup_id = $dao->id;
      $price_field_value_id = $dao->price_option_id;
      $entity_table = $dao->entity_table;
      $entity_ref_id = $dao->entity_ref_id;
    }
    if ($price_field_value_id){
      try{
        $participant = civicrm_api('participant', 'getSingle', array(
          'version' => 3,
          'id' => $objectRef['entity_id'],
          ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
      }
      if ($entity_table=='Event'){
       try{
        $newParticipant = civicrm_api('participant', 'create', array(
          'version' => 3,
          'event_id' => $entity_ref_id,
          'contact_id' => $participant['contact_id'],
          'participant_register_date' => $participant['participant_register_date'],
          'participant_source' => $participant['participant_source'],
         //   'participant_fee_amount' => $participant['participant_fee_amount'],
      //      'participant_fee_level' => $participant['participant_fee_level'],
         //   'participant_fee_currency' => $participant['participant_fee_currency'],
          'participant_status' => $participant['participant_status'],
          'participant_is_pay_later' => $participant['participant_is_pay_later'],
          'participant_registered_by_id' => $participant['participant_registered_by_id'],
          'participant_role_id' => $participant['participant_role_id'],
          ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
      }
    }
    else if ($entity_table=='MembershipType'){
       try{
        $newMembership = civicrm_api('Membership', 'create', array(
          'version' => 3,
          'membership_type_id' => $entity_ref_id,
          'contact_id' => $participant['contact_id'],
          'join_date' => $participant['participant_register_date'],
          'start_date' => $participant['participant_register_date'],
         //   'participant_fee_amount' => $participant['participant_fee_amount'],
      //      'participant_fee_level' => $participant['participant_fee_level'],
         //   'participant_fee_currency' => $participant['participant_fee_currency'],
          'status_id' => 1,
          'is_pay_later' >= $participant['participant_is_pay_later'],
          'source' => 'Event Sign Up',
          ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
      }
    }
  }
}
}

/**
 * Implementation of hook_civicrm_config
 */
function eventmembershipsignup_civicrm_config(&$config) {
  _eventmembershipsignup_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu

* * @param $files array(string)
 */
function eventmembershipsignup_civicrm_xmlMenu(&$files) {
  _eventmembershipsignup_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function eventmembershipsignup_civicrm_install() {
  $sql = "CREATE TABLE civicrm_option_signup (id INT NOT NULL AUTO_INCREMENT, price_option_id INT,  entity_table VARCHAR (255), entity_ref_id INT, PRIMARY KEY (id));";
  CRM_Core_DAO::executeQuery($sql);
  return _eventmembershipsignup_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function eventmembershipsignup_civicrm_uninstall() {
  $sql = "DROP TABLE civicrm_option_signup;";
  CRM_Core_DAO::executeQuery($sql);
  return _eventmembershipsignup_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function eventmembershipsignup_civicrm_enable() {
  return _eventmembershipsignup_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function eventmembershipsignup_civicrm_disable() {
  return _eventmembershipsignup_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function eventmembershipsignup_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _eventmembershipsignup_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function eventmembershipsignup_civicrm_managed(&$entities) {
  return _eventmembershipsignup_civix_civicrm_managed($entities);
}

<?php
// saves other sign up values for price options
if ($formName == 'CRM_Price_Form_Field') {
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
          break;
      }
      if ($price_option_key <= $numOptions and !is_null($price_option_ids[$price_option_key]) and $price_option_othersignup) {
        save_new_othersignup($price_option_ids[$price_option_key], $entity_table, $entity_ref_id);
      }
    }
  }
}
elseif ($formName == 'CRM_Price_Form_Option') {
  $id = $form->getVar('_oid');
  switch ($form->_submitValues['othersignup']) {
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
  if ($form->_submitValues['othersignup']) {
    save_new_othersignup($id, $entity_table, $entity_ref_id);
  }
  else {
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
  }
}

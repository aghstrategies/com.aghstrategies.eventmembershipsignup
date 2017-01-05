<?php
/**
 * @file
 * Event Additional Signup.
 *
 * A CiviCRM extension to add price options for registration for other events or
 * memberships to a CiviCRM event registration price set.
 *
 * Copyright (C) 2014-15, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

require_once 'eventmembershipsignup.civix.php';
require_once 'otherSignupAdmin.php';

/**
 * Implementation of hook_civicrm_buildForm
 */
function eventmembershipsignup_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Price_Form_Field' && is_null($form->getVar('_fid'))) {
    eventmembershipsignup_field_admin_form($form);
  }
  elseif ($formName == 'CRM_Price_Form_Option') {
    eventmembershipsignup_option_admin_form($form);
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 */
function eventmembershipsignup_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Price_Form_Field') {
    eventmembershipsignup_field_admin_postProcess($form);
  }
  elseif ($formName == 'CRM_Price_Form_Option') {
    eventmembershipsignup_option_admin_postProcess($form);
  }
}


/**
 * Implementation of hook_civicrm_post
 */
function eventmembershipsignup_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  // Save new registration or membership.
  if ($op == 'create' && $objectName == 'LineItem') {
    $price_field_value_id = 0;
    $objPFV = is_array($objectRef) ? $objectRef['price_field_value_id'] : $objectRef->price_field_value_id;
    $sql = "SELECT * FROM civicrm_option_signup WHERE price_option_id={$objPFV};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $option_signup_id = $dao->id;
      $price_field_value_id = $dao->price_option_id;
      $entity_table = $dao->entity_table;
      $entityRefId = $dao->entity_ref_id;
    }
    if (empty($price_field_value_id)) {
      return;
    }

    try {
      $objEntity = is_array($objectRef) ? $objectRef['entity_id'] : $objectRef->entity_id;
      $participant = civicrm_api3('participant', 'getSingle', array('id' => $objEntity));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Session::setStatus($error, ts('Error finding your registration for addons', array('domain' => 'com.aghstrategies.eventmembershipsignup')), 'error');
    }
    if ($entity_table == 'Event') {
      try {
        $newPartParams = array('event_id' => $entityRefId);
        $fillFields = array(
          'contact_id',
          'participant_register_date',
          'participant_source',
          'participant_status_id',
          'participant_is_pay_later',
          'participant_registered_by_id',
          'participant_role_id',
        );
        foreach ($fillFields as $fillField) {
          if (isset($participant[$fillField])) {
            $newPartParams[$fillField] = $participant[$fillField];
          }
        }
        $newParticipant = civicrm_api3('participant', 'create', $newPartParams);
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Error::debug_var('Problem registering for add-on event', $newPartParams);
        CRM_Core_Session::setStatus($error, ts('Problem registering for add-on event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), 'error');
      }
    }
    elseif ($entity_table == 'MembershipType') {
      try {
        $newMemParams = array(
          'membership_type_id' => $entityRefId,
          'contact_id' => $participant['contact_id'],
          'is_pay_later' => $participant['participant_is_pay_later'],
          'source' => ts('Event Sign Up', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
          'num_terms' => 1,
        );
        $memTypes = civicrm_api3('MembershipType', 'get', array('return' => "member_of_contact_id"));
        $memTypeOrg = CRM_Utils_Array::value('member_of_contact_id', CRM_Utils_Array::value($entityRefId, $memTypes['values'], array()));
        if ($memTypeOrg) {
          $currentMem = civicrm_api3('Membership', 'get', array(
            'sequential' => 1,
            'contact_id' => $participant['contact_id'],
            'options' => array('sort' => "end_date DESC"),
          ));
          if ($currentMem['count'] > 0) {
            foreach ($currentMem['values'] as $memV) {
              if (CRM_Utils_Array::value($memV['membership_type_id'], $memTypes['values'])) {
                if ($memTypeOrg == CRM_Utils_Array::value('member_of_contact_id', $memTypes['values'][$memV['membership_type_id']])) {
                  $newMemParams['id'] = $memV['id'];
                  $newMemParams['source'] = CRM_Utils_Array::value('source', $memV, $newMemParams['source']);
                  $newMemParams['skipStatusCal'] = 0;
                  break;
                }
              }
            }
          }
        }
        $newMembership = civicrm_api3('Membership', 'create', $newMemParams);
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Session::setStatus($error, ts('Add-on Membership Problem', array('domain' => 'com.aghstrategies.eventmembershipsignup')), 'error');
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
 *
 * @param $files array(string)
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

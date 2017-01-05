<?php
/**
 * @file
 * Event Additional Signup.
 *
 * A CiviCRM extension to add price options for registration for other events or
 * memberships to a CiviCRM event registration price set.
 *
 * Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

require_once 'eventmembershipsignup.civix.php';
require_once 'otherSignupAdmin.php';

/**
 * Implements hook_civicrm_buildForm().
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
 * Implements hook_civicrm_postProcess().
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
 * Implements hook_civicrm_post().
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
  // elseif ($op == 'edit' && $objectName == 'Contribution') {
  //   if ($objectRef->contribution_status_id == array_search('Cancelled', $contributionStatuses)) {}
  //   CRM_Core_Error::debug_var('participant edited', $objectRef);
  // }
}

/**
 * Implements hook_civicrm_pre().
 */
function eventmembershipsignup_civicrm_pre($op, $objectName, $id, &$params) {
  if ($op == 'edit' && $objectName == 'Participant') {
    if (empty($params['status_id'])) {
      CRM_Core_Error::debug_var('missing status_id', $params);
      return;
    }
    $participantStatuses = CRM_Event_PseudoConstant::participantStatus();
    $sql = <<<HERESQL
SELECT p.status_id, os.entity_table, os.entity_ref_id
FROM civicrm_participant p
JOIN civicrm_participant_payment pp
  on pp.participant_id = p.id
JOIN civicrm_contribution c
  ON c.id = pp.contribution_id
JOIN civicrm_line_item li
  ON li.contribution_id = c.id
JOIN civicrm_option_signup os
  ON os.price_option_id = li.price_field_value_id
WHERE p.id = $id
HERESQL;
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      if ($dao->entity_table == 'Event' && $dao->entity_ref_id == $id) {
        $dao->toArray();
        CRM_Core_Error::debug_var('resetting same participant id', $dao);
        continue;
      }
      if (!in_array(
        $participantStatuses[$params['status_id']],
        CRM_Utils_Array::value(
          $participantStatuses[$dao->status_id],
          CRM_Event_BAO_Participant::$_statusTransitionsRules,
          array()
        )
      )) {
        // We're not going from a pending status to a completed status, so no
        // need to update the other events
        $dao->toArray();
        CRM_Core_Error::debug_var('wrong status transition', $dao);
        return;
      }

      switch ($dao->entity_table) {
        case 'Event':
          try {
            $updateParams = array(
              'id' => $dao->entity_ref_id,
              'status_id' => $params['status_id'],
            );
            $result = civicrm_api3('Participant', 'create', $updateParams);
          }
          catch (CiviCRM_API3_Exception $e) {
            $error = $e->getMessage();
            CRM_Core_Error::debug_var('Problem updating pay-later add-on event', $updateParams);
            CRM_Core_Session::setStatus($error, ts('Problem updating pay-later add-on event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), 'error');
          }
          break;

        case 'MembershipType':
          // resolve pay-later membership
          break;
      }
    }
  }
  else {
    $vars = array($op, $objectName, $id, $params);
    // CRM_Core_Error::debug_var('not picked up by pre hook', $vars);
  }
}

/**
 * Implements hook_civicrm_config().
 */
function eventmembershipsignup_civicrm_config(&$config) {
  _eventmembershipsignup_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 */
function eventmembershipsignup_civicrm_xmlMenu(&$files) {
  _eventmembershipsignup_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 */
function eventmembershipsignup_civicrm_install() {
  $sql = <<<HERESQL
CREATE TABLE `civicrm_option_signup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `price_option_id` int(10) unsigned,
  `entity_table` varchar(64),
  `entity_ref_id` int(10) unsigned,
  PRIMARY KEY (`id`),
  KEY `price_option_id` (`price_option_id`),
  KEY `entity_table_entity_ref_id` (`entity_table`,`entity_ref_id`),
  CONSTRAINT `FK_civicrm_option_signup_price_option_id` FOREIGN KEY (`price_option_id`) REFERENCES `civicrm_price_field_value` (`id`) ON DELETE CASCADE
)
HERESQL;
  CRM_Core_DAO::executeQuery($sql);
  return _eventmembershipsignup_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 */
function eventmembershipsignup_civicrm_uninstall() {
  $sql = "DROP TABLE civicrm_option_signup;";
  CRM_Core_DAO::executeQuery($sql);
  return _eventmembershipsignup_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 */
function eventmembershipsignup_civicrm_enable() {
  return _eventmembershipsignup_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 */
function eventmembershipsignup_civicrm_disable() {
  return _eventmembershipsignup_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 */
function eventmembershipsignup_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _eventmembershipsignup_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function eventmembershipsignup_civicrm_managed(&$entities) {
  return _eventmembershipsignup_civix_civicrm_managed($entities);
}

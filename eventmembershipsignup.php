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

/**
 * Implements hook_civicrm_buildForm().
 */
function eventmembershipsignup_civicrm_buildForm($formName, &$form) {
  switch ($formName) {
    case 'CRM_Price_Form_Field':
      if (empty($form->getVar('_fid'))) {
        $admin = new CRM_Eventadditional_Admin();
        $admin->modFieldAdminForm($form);
      }
      break;

    case 'CRM_Price_Form_Option':
      $admin = new CRM_Eventadditional_Admin();
      $admin->modOptionAdminForm($form);
      break;

    case 'CRM_Event_Form_Registration_Register':
      // Note: this does NOT check if online registration is enabled for the
      // event.  Presumably if you add an event as an add-on, you want people to
      // be able to register for it unless it's too late.
      CRM_Eventadditional_Frontend::checkRegOpen($form);
      break;
  }
}

/**
 * Implements hook_civicrm_postProcess().
 */
function eventmembershipsignup_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Price_Form_Field') {
    CRM_Eventadditional_Admin::processFieldAdminForm($form);
  }
  elseif ($formName == 'CRM_Price_Form_Option') {
    CRM_Eventadditional_Admin::processOptionAdminForm($form);
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
  if ($op == 'edit' && $objectName == 'Contribution') {
    if (empty($params['prevContribution']->is_pay_later)
      || CRM_Utils_Array::value('is_pay_later', $params, 1)
      || empty($params['participant_id'])) {
      return;
    }

    $contribStatusAPI = civicrm_api3('Contribution', 'getoptions', array(
      'field' => "contribution_status_id",
      'context' => "validate",
    ));

    $participantStatusAPI = civicrm_api3('Participant', 'getoptions', array(
      'field' => "participant_status_id",
      'context' => "validate",
    ));

    $sql = <<<HERESQL
SELECT p.id as participant_id, p.status_id as participant_status_id
FROM civicrm_contribution c
JOIN civicrm_line_item li
  ON li.contribution_id = c.id
JOIN civicrm_option_signup os
  ON os.price_option_id = li.price_field_value_id
LEFT JOIN civicrm_participant p
  ON p.event_id = os.entity_ref_id
  AND p.contact_id = c.contact_id
WHERE c.id = $id
  AND os.entity_table = 'Event'
HERESQL;
    $dao = CRM_Core_DAO::executeQuery($sql);

    // FIXME: for now, no updating of memberships, just events.  The reason?
    // This:
    // https://github.com/civicrm/civicrm-core/blob/4.7.15/CRM/Contribute/BAO/Contribution.php#L1810
    // and the following 110 lines.  For now, add-on memberships are not held in
    // pending status so there is no need to activate them when pay-later is
    // resolved.
    switch (CRM_Utils_Array::value($params['contribution_status_id'], $contribStatusAPI['values'])) {
      case 'Cancelled':
      case 'Failed':
        $updatedStatusId = array_search('Cancelled', $participantStatusAPI['values']);
        while ($dao->fetch()) {
          CRM_Event_BAO_Participant::updateParticipantStatus($dao->participant_id, $dao->participant_status_id, $updatedStatusId, TRUE);
        }
        break;

      case 'Completed':
        $updatedStatusId = array_search('Registered', $participantStatusAPI['values']);
        while ($dao->fetch()) {
          CRM_Event_BAO_Participant::updateParticipantStatus($dao->participant_id, $dao->participant_status_id, $updatedStatusId, TRUE);
        }
        break;
    }
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
  return _eventmembershipsignup_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 */
function eventmembershipsignup_civicrm_uninstall() {
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

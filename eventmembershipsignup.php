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
        $admin = new CRM_Eventmembershipsignup_Admin();
        $admin->modFieldAdminForm($form);
      }
      break;

    case 'CRM_Price_Form_Option':
      $admin = new CRM_Eventmembershipsignup_Admin();
      $admin->modOptionAdminForm($form);
      break;

    case 'CRM_Event_Form_Registration_Register':
      // Note: this does NOT check if online registration is enabled for the
      // event.  Presumably if you add an event as an add-on, you want people to
      // be able to register for it unless it's too late.
      CRM_Eventmembershipsignup_Frontend::checkRegOpen($form);
      break;
  }
}

/**
 * Implements hook_civicrm_postProcess().
 */
function eventmembershipsignup_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Price_Form_Field') {
    CRM_Eventmembershipsignup_Admin::processFieldAdminForm($form);
  }
  elseif ($formName == 'CRM_Price_Form_Option') {
    CRM_Eventmembershipsignup_Admin::processOptionAdminForm($form);
  }
}

/**
 * Implements hook_civicrm_post().
 */
function eventmembershipsignup_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  // Save new registration or membership.
  if ($op == 'create' && $objectName == 'LineItem') {
    $priceFieldValueID = is_array($objectRef) ? $objectRef['price_field_value_id'] : $objectRef->price_field_value_id;
    // If we've been triggered via API3 Contribution.repeattransaction etc. we may end up with text "null" instead of actual NULL.
    if ($priceFieldValueID === 'null') $priceFieldValueID = NULL;
    if (empty($priceFieldValueID)) {
      // For example "percentagepricesetfield" creates percentage lineItem with NULL price_field_value_id
      // In this case there is nothing to do because there will be no linked event/membership for that lineItem
      // If we try to run the query it will crash.
      return;
    }
    $args = [
      1 => [$priceFieldValueID, 'Integer'],
    ];
    $sql = <<<'HERESQL'
SELECT id, price_option_id, entity_table, entity_ref_id
FROM civicrm_option_signup
WHERE price_option_id = %1
HERESQL;
    $dao = CRM_Core_DAO::executeQuery($sql, $args);
    while ($dao->fetch()) {
      if (!empty($dao->price_option_id)) {
        if ($dao->entity_table == 'Event') {
          CRM_Eventmembershipsignup_Frontend::registerForEventAddons($dao->price_option_id, $dao->entity_ref_id, $objectRef);
        }
        elseif ($dao->entity_table == 'MembershipType') {
          CRM_Eventmembershipsignup_Frontend::registerForMembershipAddons($dao->price_option_id, $dao->entity_ref_id, $objectRef);
        }
      }
    }
  }
  // elseif ($op == 'edit' && $objectName == 'Contribution') {
  //   if ($objectRef->contribution_status_id == array_search('Cancelled', $contributionStatuses)) {}
  //   CRM_Core_Error::debug_var('participant edited', $objectRef);
  // }
}

/**
 * Check if the change in the contribution status should trigger a change to the participant status
 * @param  int $contributionStatusBeforeId  Contribution Status id before this change
 * @param  int $contributionStatusGoingToId Contribution Status id going to
 * @param  int $participantStatusIdNow      Participant Status Id
 * @param  int $participantId               Participant Id
 */
function eventmembershipsignup_updateparticipantstatus($contributionStatusBeforeId, $contributionStatusGoingToId, $participantStatusIdNow, $participantId) {

  // Get status names from ids
  $contributionStatusBefore = CRM_Contribute_PseudoConstant::contributionStatus($contributionStatusBeforeId, 'name');
  $contributionStatusGoingTo = CRM_Contribute_PseudoConstant::contributionStatus($contributionStatusGoingToId, 'name');
  $participantStatus = CRM_Event_PseudoConstant::participantStatus($participantStatusIdNow, NULL, "name");
  $participantStatusOptions = CRM_Event_PseudoConstant::participantStatus();

  // We will only update participant ids if the contribution status is changing AND the participant status is listed here
  if ($contributionStatusBefore != $contributionStatusGoingTo && in_array($participantStatus, [
    'Pending from pay later',
    'Pending from incomplete transaction',
    'Pending in cart',
    'Partially paid',
    'Registered',
  ])) {

    $updatedStatusId = NULL;

    switch ($contributionStatusGoingTo) {
      // IF the contribution is being updated to one of these statuses the participant status should be the same as the contribution status
      case 'Pending refund':
      case 'Partially paid':
      case 'Cancelled':
        $updatedStatusId = array_search($contributionStatusGoingTo, $participantStatusOptions);
        break;

      // IF the contribution going to Completed the participant status should be Registered
      case 'Completed':
        $updatedStatusId = array_search('Registered', $participantStatusOptions);
        break;

      // IF the contribution status is going to one of these pending statuses the participant status should be set to pending as well.
      case 'Pending':
      case 'In Progress':
        $updatedStatusId = array_search('Pending from pay later', $participantStatusOptions);
        break;

      // IF the contribution status is going to one of these statuses do not update the participant status at all
      case 'Refunded':
      case 'Overdue':
      case 'Chargeback':
      case 'Failed':
        break;
    }
    // If the updated status has been set update the participant record
    if ($updatedStatusId) {
      CRM_Event_BAO_Participant::updateParticipantStatus($participantId, $participantStatusIdNow, $updatedStatusId, TRUE);
    }
  }
}

/**
 * Implements hook_civicrm_pre().
 */
function eventmembershipsignup_civicrm_pre($op, $objectName, $id, &$params) {
  if ($op == 'edit' && $objectName == 'Contribution') {
    // Get all Event Registrations added by the extension

    // FIXME: for now, no updating of memberships, just events.  The reason?
    // This:
    // https://github.com/civicrm/civicrm-core/blob/4.7.15/CRM/Contribute/BAO/Contribution.php#L1810
    // and the following 110 lines.  For now, add-on memberships are not held in
    // pending status so there is no need to activate them when pay-later is
    // resolved.
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
WHERE c.id = %1
  AND os.entity_table = 'Event'
HERESQL;
    $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($id, 'Integer')));

    while ($dao->fetch()) {
      eventmembershipsignup_updateparticipantstatus(
        $params['prevContribution']->contribution_status_id,
        $params['contribution_status_id'],
        $dao->participant_status_id,
        $dao->participant_id
      );
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

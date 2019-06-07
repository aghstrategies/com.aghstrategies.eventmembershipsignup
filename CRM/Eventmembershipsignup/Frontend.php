<?php
/**
 * @file
 * Tools for modifying the frontend event registration form.
 *
 * Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

class CRM_Eventmembershipsignup_Frontend {

  /**
   * Walk through price options and see if events are still eligible for signup.
   *
   * @param CRM_Event_Form_Registration_Register $form
   *   The event registration form to scan.
   */
  public static function checkRegOpen(&$form) {
    foreach ($form->_elementIndex as $elementName => $i) {
      if (strpos($elementName, 'price_') === 0) {
        $eGroup =& $form->getElement($elementName);
        if (is_a($eGroup, 'HTML_QuickForm_group')) {
          foreach ($eGroup->getElements() as $element) {
            if (is_a($element, 'HTML_QuickForm_checkbox')) {
              $option = $element->getName();
            }
            elseif (is_a($element, 'HTML_QuickForm_radio')) {
              $option = $element->getValue();
            }
            else {
              continue 2;
            }
            self::validateOption($option, $element);
          }
        }
        // TODO currently if one is using a select field options are not disabled properly
        // elseif (is_a($eGroup, 'HTML_QuickForm_select')) {
        //   if (!empty($eGroup->getValue()[0])) {
        //     self::validateOption($eGroup->getValue()[0], $element);
        //   }
        // }
      }
    }
  }

  /**
   * See if the price option has an associated event and disable it if full
   *
   * If registration is closed, event is over, or event is full, disable the
   * option.
   *
   * @param int $option
   *   The civicrm_price_field_value ID.
   * @param HTML_QuickForm_checkbox|HTML_QuickForm_radio $element
   *   The element to modify.
   */
  public static function validateOption($option, &$element) {
    $sql = <<<HERESQL
SELECT entity_ref_id
FROM civicrm_option_signup
WHERE price_option_id = %1
  AND entity_table = 'Event'
HERESQL;

    $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($option, 'Integer')));
    while ($dao->fetch()) {
      $eventId = $dao->entity_ref_id;
      try {
        $event = civicrm_api3('Event', 'getsingle', array(
          'sequential' => 1,
          'return' => array(
            'max_participants',
            'has_waitlist',
            'waitlist_text',
            'event_full_text',
            'registration_end_date',
            'end_date',
          ),
          'id' => $dao->entity_ref_id,
        ));
      }
      catch (CiviCRM_API3_Exception $e) {
        CRM_Core_Error::debug_var('Cannot find event', $e);
        return;
      }
      // Registration and/or event is over
      $endDates = array(
        'end_date',
        'registration_end_date',
      );
      foreach ($endDates as $dateField) {
        if (!empty($event[$dateField])) {
          if (time() > strtotime($event[$dateField])) {
            self::disableElement($element, ts(
              'Registration for one or more of the events in this option is closed.',
              array('domain' => 'com.aghstrategies.eventmembershipsignup')
            ));
            return;
          }
        }
      }

      // Event is full
      if (!empty($event['max_participants'])) {
        $full = CRM_Event_BAO_Participant::eventFull($eventId);
        if (!empty($full)) {
          $waitlistText = ts(
            '%1 You may visit <a href="%2" target="_blank">this form</a> to join the waiting list.',
            array(
              1 => $full,
              2 => CRM_Utils_System::url('civicrm/event/register', array('id' => $eventId, 'reset' => 1), FALSE, NULL, FALSE, TRUE),
              'domain' => 'com.aghstrategies.eventmembershipsignup',
            )
          );
          $message = empty($event['has_waitlist']) ? $full : $waitlistText;
          self::disableElement($element, $message);
        }
      }
    }
  }

  /**
   * Disable an price option and add a note why.
   *
   * @param HTML_QuickForm_radio|HTML_QuickForm_checkbox $element
   *   The element to disable.
   *
   * @param string $message
   *   The message to add below the option.
   */
  public static function disableElement(&$element, $message = NULL) {
    $element->freeze();
    $origVal = $element->getText();
    $element->setText("$origVal<br/><span class=\"description\">$message</span>");
  }

  /**
   * Process Event Add-ons
   * @param  int $price_field_value_id    Price Option Selected
   * @param  int $entityRefId             Id of Event Registration to Add on
   * @param  object $objectRef            Object being processed (Participant or Membership Record)
   */
  public static function registerForEventAddons($price_field_value_id, $entityRefId, $objectRef) {
    try {
      $objEntity = is_array($objectRef) ? $objectRef['entity_id'] : $objectRef->entity_id;
      $participant = civicrm_api3('participant', 'getSingle', array('id' => $objEntity));
    }
    catch (CiviCRM_API3_Exception $e) {
      $error = $e->getMessage();
      CRM_Core_Session::setStatus($error, ts('Error finding your registration for addons', array('domain' => 'com.aghstrategies.eventmembershipsignup')), 'error');
    }
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

  /**
   * Process Membership Add-ons
   * @param  int $price_field_value_id    Price Option Selected
   * @param  int $entityRefId             Id of Membership to Add on
   * @param  object $objectRef            Object being processed (Participant or Membership Record)
   */
  public static function registerForMembershipAddons($price_field_value_id, $entityRefId, $objectRef) {
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

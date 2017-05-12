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

    if ($eventId = CRM_Core_DAO::singleValueQuery($sql, array(1 => array($option, 'Integer')))) {
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
          'id' => $eventId,
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
              'Registration for this event is closed.',
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

}

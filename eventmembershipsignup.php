<?php
// TODO: Handle $error from catch

require_once 'eventmembershipsignup.civix.php';

/**
  * Saves othersignup
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
 * Implementation of hook_civicrm_buildForm
 */
function eventmembershipsignup_civicrm_buildForm($formName, &$form) {
  require_once 'buildForm.php';
}

/**
 * Implementation of hook_civicrm_postProcess
 */
function eventmembershipsignup_civicrm_postProcess($formName, &$form) {
  require_once 'postProcess.php';
}


/**
 * Implementation of hook_civicrm_post
 */
function eventmembershipsignup_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  // Save new registration or membership.
  if ($op == 'create' && $objectName == 'LineItem') {
    $price_field_value_id = 0;
    $sql = "SELECT * FROM civicrm_option_signup WHERE price_option_id={$objectRef['price_field_value_id']};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $option_signup_id = $dao->id;
      $price_field_value_id = $dao->price_option_id;
      $entity_table = $dao->entity_table;
      $entityRefId = $dao->entity_ref_id;
    }
    if ($price_field_value_id) {
      try {
        $participant = civicrm_api('participant', 'getSingle', array(
          'version' => 3,
          'id' => $objectRef['entity_id'],
          ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
      }
      if ($entity_table == 'Event') {
        try {
          $newParticipant = civicrm_api3('participant', 'create', array(
            'event_id' => $entityRefId,
            'contact_id' => $participant['contact_id'],
            'participant_register_date' => $participant['participant_register_date'],
            'participant_source' => $participant['participant_source'],
            'participant_status' => $participant['participant_status'],
            'participant_is_pay_later' => $participant['participant_is_pay_later'],
            'participant_registered_by_id' => $participant['participant_registered_by_id'],
            'participant_role_id' => $participant['participant_role_id'],
            ));
        }
        catch (CiviCRM_API3_Exception $e) {
          $error = $e->getMessage();
          CRM_Core_Session::setStatus($error, ts('Add-on Event Problem'), 'error');
        }
      }
      elseif ($entity_table == 'MembershipType') {
        try {
          $newMemParams = array(
            'membership_type_id' => $entityRefId,
            'contact_id' => $participant['contact_id'],
            // 'join_date' => $participant['participant_register_date'],
            // 'start_date' => $participant['participant_register_date'],
            'is_pay_later' => $participant['participant_is_pay_later'],
            'source' => 'Event Sign Up',
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
          CRM_Core_Session::setStatus($error, ts('Add-on Membership Problem'), 'error');
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

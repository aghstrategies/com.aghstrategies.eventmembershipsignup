<?php
/**
 * @file
 * Handles saving the additional signups corresponding to price options.
 *
 * Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

class CRM_Eventadditional_Admin {

  public $entityOptions = array();

  public $membershipSelector = array();

  public $eventSelector = array();

  public function __construct() {
    $this->entityOptions = array(
      0 => ts('No', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
      'Membership' => ts('Membership', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
      'Participant' => ts('Participant', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
    );

    $result = civicrm_api3('MembershipType', 'get', array(
      'sequential' => 1,
      'is_active' => 1,
      'options' => array('sort' => 'weight ASC'),
    ));
    foreach ($result['values'] as $membershipType) {
      $this->membershipSelector[$membershipType['id']] = $membershipType['name'] . ': ' . $membershipType['minimum_fee'];
    }
    $result = civicrm_api3('Event', 'get', array(
      'sequential' => 1,
      'options' => array('sort' => "start_date DESC"),
      'return' => "title,start_date",
      'is_active' => 1,
    ));
    foreach ($result['values'] as $event) {
      $formattedDate = CRM_Utils_Date::customFormat($event['start_date']);
      $this->eventSelector[$event['id']] = "{$event['title']} ($formattedDate)";
    }
  }

  /**
   * Saves add-on event or membership type.
   *
   * @param int $price_option_id
   *   The ID of the price field value.
   * @param string $entity_table
   *   Whether this is a membership type or event.
   * @param int $entityRefId
   *   The ID of the membership type or event.
   */
  public static function newOthersignup($price_option_id, $entity_table, $entityRefId) {
    $option_signup_id = 0;
    $sql = "SELECT id FROM civicrm_option_signup WHERE price_option_id = $price_option_id;";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $option_signup_id = $dao->id;
    }
    if ($option_signup_id) {
      $sql = <<<HERESQL
UPDATE civicrm_option_signup
SET entity_ref_id = $entityRefId, entity_table = "$entity_table"
WHERE id = $option_signup_id
HERESQL;
    }
    else {
      $sql = <<<HERESQL
INSERT INTO civicrm_option_signup (price_option_id, entity_table, entity_ref_id)
VALUES ($price_option_id, "$entity_table", $entityRefId)
HERESQL;
    }
    $dao = CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * Handle options created upon field creation.
   *
   * @param CRM_Price_Form_Field $form
   *   The form for adding a new price field.
   */
  public function modFieldAdminForm(&$form) {
    $select2version = version_compare(CRM_Utils_System::version(), '4.5.0', '>=');

    $numOptions = $form::NUM_OPTION;
    $selectors = array();
    for ($i = 1; $i <= $numOptions; $i++) {
      // Add the field element in the form.
      $form->add('select', "othersignup[$i]", ts('Additional Signup?', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->entityOptions);
      $form->add('select', "membershipselect[$i]", ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->membershipSelector);
      if ($select2version) {
        $form->addEntityRef("eventselect[$i]", ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), array(
          'entity' => 'event',
          'placeholder' => '- ' . ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')) . ' -',
          'select' => array('minimumInputLength' => 0),
        ));
      }
      else {
        $form->add('select', "eventselect[$i]", ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->eventSelector);
      }
      $selectors[] = $i;
    }
    $form->assign('numOptions', $numOptions);
    $form->assign('selectors', $selectors);

    $templateFile = CRM_Core_Resources::singleton()->getPath('com.aghstrategies.eventmembershipsignup', 'templates/pricefieldOthersignup.tpl');
    // Add the field element in the form
    // Dynamically insert a template block in the page.
    $form->add('select', 'memberSelect', '');
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => $templateFile,
    ));
  }

  /**
   * PostProcess for options created upon field creation.
   *
   * @param CRM_Price_Form_Field $form
   *   The price field form to process.
   */
  public function processFieldAdminForm(&$form) {
    // Assume that the only time options are added in bulk is if the field is
    // newly-created.
    $sql = "SELECT id FROM civicrm_price_field ORDER BY id DESC LIMIT 1;";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $price_field_id = $dao->id;
    }
    if (empty($price_field_id)) {
      // TODO: log or notice
      return;
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
          self::newOthersignup($price_option_ids[$price_option_key], $entity_table, $entity_ref_id);
        }
      }
    }
  }

  /**
   * Handle single option form.
   *
   * @param CRM_Price_Form_Option $form
   *   The form to modify.
   */
  public function modOptionAdminForm(&$form) {
    $select2version = version_compare(CRM_Utils_System::version(), '4.5.0', '>=');

    $id = $form->getVar('_oid');
    $form->assign('option_signup_id', 0);
    $form->assign('signupselectvalue', 0);
    $form->assign('eventmembershipvalue', 0);
    $defaults = array();
    if (!empty($id)) {
      $sql = "SELECT id, entity_table, entity_ref_id FROM civicrm_option_signup WHERE price_option_id = {$id};";
      $dao = CRM_Core_DAO::executeQuery($sql);
      if ($dao->fetch()) {
        if ($dao->entity_table == 'Event') {
          $defaults['othersignup'] = 'Participant';
          $defaults['eventselect'] = $dao->entity_ref_id;
        }
        elseif ($dao->entity_table == 'MembershipType') {
          $defaults['othersignup'] = 'Membership';
          $defaults['membershipselect'] = $dao->entity_ref_id;
        }
      }
    }

    // Add the field element in the form.
    $form->add('select', 'othersignup', ts('Other Sign Up?', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->entityOptions);
    $form->add('select', 'membershipselect', ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->membershipSelector);
    if ($select2version) {
      $form->addEntityRef('eventselect', ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), array(
        'entity' => 'event',
        'placeholder' => '- ' . ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')) . ' -',
        'select' => array('minimumInputLength' => 0),
      ));
    }
    else {
      $form->add('select', 'eventselect', ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->eventSelector);
    }

    $form->setDefaults($defaults);

    $templateFile = CRM_Core_Resources::singleton()->getPath('com.aghstrategies.eventmembershipsignup', 'templates/priceoptionOthersignup.tpl');
    // Dynamically insert a template block in the page.
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => $templateFile,
    ));
  }

  /**
   * PostProcess for single options.
   *
   * @param CRM_Price_Form_Option $form
   *   The option admin form being processed.
   */
  public function processOptionAdminForm(&$form) {
    $id = $form->getVar('_oid');
    if (empty($id)) {
      // TODO: log or notice?
      return;
    }
    switch (CRM_Utils_Array::value('othersignup', $form->_submitValues)) {
      case 'Membership':
        CRM_Eventadditional_Admin::newOthersignup($id, 'MembershipType', $form->_submitValues['membershipselect']);
        break;

      case 'Participant':
        CRM_Eventadditional_Admin::newOthersignup($id, 'Event', $form->_submitValues['eventselect']);
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

}

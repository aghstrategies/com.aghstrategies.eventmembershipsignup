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
    // Don't process if there are no values in the "othersignup" set of fields.
    if (empty($form->_submitValues['othersignup'])) {
      return;
    }

    // The arrays of values for the multiple options that were created.
    $optionMultiFields = array(
      'option_label',
      'option_amount',
      'option_financial_type_id',
      'option_count',
      'option_max_value',
      'option_weight',
    );

    // Walk through options and only deal with additional signup options.
    foreach ($form->_submitValues['othersignup'] as $price_option_key => $price_option_othersignup) {
      switch ($price_option_othersignup) {
        case 'Membership':
          $entityRefId = $form->_submitValues['membershipselect'][$price_option_key];
          $entityTable = 'MembershipType';
          break;

        case 'Participant':
          $entityRefId = $form->_submitValues['eventselect'][$price_option_key];
          $entityTable = 'Event';
          break;

        default:
          continue 2;
      }

      // Assemble information from the option fields.
      $vals = array();
      foreach ($optionMultiFields as $f) {
        if (!empty($form->_submitValues[$f][$price_option_key])) {
          $fShort = substr($f, 7);
          $vals[$fShort] = $form->_submitValues[$f][$price_option_key];
        }
      }
      if (empty($vals)) {
        continue;
      }
      else {
        $vals['fieldId'] = self::findOptionByValues($form->_submitValues, 'PriceField');
      }
      $priceOptionId = self::findOptionByValues($vals);

      self::newOthersignup($priceOptionId, $entityTable, $entityRefId);
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
      $id = self::findOptionByValues($form->_submitValues);
    }
    switch (CRM_Utils_Array::value('othersignup', $form->_submitValues)) {
      case 'Membership':
        self::newOthersignup($id, 'MembershipType', $form->_submitValues['membershipselect']);
        break;

      case 'Participant':
        self::newOthersignup($id, 'Event', $form->_submitValues['eventselect']);
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

  /**
   * Look up a price option or field by its values.
   *
   * hook_civicrm_postProcess gets called after a price option/field is created,
   * but nowhere in the form is the price_field_value_id recorded.
   *
   * @param array $values
   *   Submit values to search by.
   * @param string $fieldOrOption
   *   Whether to retrieve a PriceFieldValue or a PriceField.
   * @return int
   *   The ID of the found option.
   */
  public static function findOptionByValues($values, $fieldOrOption = 'PriceFieldValue') {
    switch ($fieldOrOption) {
      case 'PriceField':
        $fields = array(
          'sid' => 'price_set_id',
          'label' => 'label',
          'html_type' => 'html_type',
          'is_display_amounts' => 'is_display_amounts',
        );
        break;

      case 'PriceFieldValue':
      default:
        $fields = array(
          'fieldId' => 'price_field_id',
          'label' => 'label',
          'amount' => 'amount',
          'financial_type_id' => 'financial_type_id',
          'count' => 'count',
          'max_value' => 'max_value',
          'weight' => 'weight',
        );
        break;
    }

    $searchParams = array(
      'return' => 'id',
      'options' => array(
        // In case there are two identical values, pull the newest
        'sort' => "id DESC",
        'limit' => 1,
      ),
    );

    foreach ($fields as $val => $field) {
      if (!empty($values[$val])) {
        $searchParams[$field] = $values[$val];
      }
    }

    try {
      return civicrm_api3($fieldOrOption, 'getvalue', $searchParams);
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::debug_var('Failed to find price option/field just created', $e);
    }
  }

}

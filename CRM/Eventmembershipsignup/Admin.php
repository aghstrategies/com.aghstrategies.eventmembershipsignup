<?php
/**
 * @file
 * Handles saving the additional signups corresponding to price options.
 *
 * Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

class CRM_Eventmembershipsignup_Admin {

  public $entityOptions = array();

  public function __construct() {
    $this->entityOptions = array(
      0 => ts('No', array('domain' => 'com.aghstrategies.eventmembershipsignup')),
    );
    try {
      $components = civicrm_api3('Setting', 'getvalue', array(
        'group' => "CiviCRM Preferences",
        'name' => "enable_components",
      ));
      if (in_array('CiviMember', $components)) {
        $this->entityOptions['Membership'] = ts('Membership', array('domain' => 'com.aghstrategies.eventmembershipsignup'));
      }
      if (in_array('CiviEvent', $components)) {
        $this->entityOptions['Participant'] = ts('Participant', array('domain' => 'com.aghstrategies.eventmembershipsignup'));
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::debug_var('Cannot find enabled components', $e);
    }
  }

  public static function deleteAddOn($price_option_id, $entity_ref_id, $entity_table) {
    $sql = "DELETE FROM civicrm_option_signup WHERE price_option_id = %1 AND entity_ref_id = %2 AND entity_table = %3";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$price_option_id, 'Integer'],
      2 => [$entity_ref_id, 'Integer'],
      3 => [$entity_table, 'String'],
    ]);
  }

  /**
   * Updates add-on event or membership options
   *
   * @param int $price_option_id
   *   The ID of the price field value.
   * @param string $entity_table
   *   Whether this is a membership type or event.
   * @param int $entityRefId
   *   The IDs of the membership type or event.
   */
  public static function updateAdditionalSignups($price_option_id, $entity_table, $entityRefId) {
    // get existing events/or memberships options saved for this price option
    $lookup = "SELECT * FROM civicrm_option_signup WHERE price_option_id = %1";
    $existing = CRM_Core_DAO::executeQuery($lookup, [
      1 => [$price_option_id, 'Integer'],
    ]);
    $currentAdditional = [];
    while ($existing->fetch()) {
      if ($existing->entity_table == $entity_table) {
        $currentAdditional[] = $existing->entity_ref_id;
      }
      // If the user is switching what entity they are adding on delete the options for the old entity.
      else {
        self::deleteAddOn($existing->price_option_id, $existing->entity_ref_id, $existing->entity_table);
      }
    }

    // options as updated by the user
    $otherRefs = explode(',', $entityRefId);

    // Deal with add-ons that the user as removed
    $delete = array_diff($currentAdditional, $otherRefs);
    if (!empty($delete)) {
      foreach ($delete as $key => $ref) {
        self::deleteAddOn($price_option_id, $ref, $entity_table);
      }
    }

    // Deal with add-ons that the user has added
    $add = array_diff($otherRefs, $currentAdditional);
    if (!empty($add)) {
      foreach ($add as $key => $ref) {
        if (!empty($ref)) {
          $args = [
            1 => [
              $price_option_id,
              'Integer',
            ],
            2 => array(
              $entity_table,
              'String',
            ),
            3 => [
              $ref,
              'Integer',
            ],
          ];
          $sql = <<<'HERESQL'
INSERT INTO civicrm_option_signup (price_option_id, entity_table, entity_ref_id)
VALUES (%1, %2, %3)
HERESQL;
          CRM_Core_DAO::executeQuery($sql, $args);
        }
      }
    }
  }

  /**
   * Handle options created upon field creation.
   *
   * @param CRM_Price_Form_Field $form
   *   The form for adding a new price field.
   */
  public function modFieldAdminForm(&$form) {
    $numOptions = $form::NUM_OPTION;
    $selectors = array();
    for ($i = 1; $i <= $numOptions; $i++) {
      // Add the field element in the form.
      $form->add('select', "othersignup[$i]", ts('Additional Signup?', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->entityOptions);
      $form->addEntityRef("membershipselect[$i]", ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')), array(
        'entity' => 'membershipType',
        'multiple' => TRUE,
        'placeholder' => '- ' . ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')) . ' -',
        'select' => array('minimumInputLength' => 0),
      ));
      $form->addEntityRef("eventselect[$i]", ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), array(
        'entity' => 'event',
        'multiple' => TRUE,
        'placeholder' => '- ' . ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')) . ' -',
        'select' => array('minimumInputLength' => 0),
      ));
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
  public static function processFieldAdminForm(&$form) {
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

      self::updateAdditionalSignups($priceOptionId, $entityTable, $entityRefId);
    }
  }

  /**
   * Handle single option form.
   *
   * @param CRM_Price_Form_Option $form
   *   The form to modify.
   */
  public function modOptionAdminForm(&$form) {
    $id = $form->getVar('_oid');
    $form->assign('option_signup_id', 0);
    $form->assign('signupselectvalue', 0);
    $form->assign('eventmembershipvalue', 0);
    $defaults = array();
    if (!empty($id)) {
      $sql = "SELECT id, entity_table, entity_ref_id FROM civicrm_option_signup WHERE price_option_id = %1";
      $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($id, 'Integer')));
      while ($dao->fetch()) {
        if ($dao->entity_table == 'Event') {
          $defaults['othersignup'] = 'Participant';
          $defaults['eventselect'][] = $dao->entity_ref_id;
        }
        elseif ($dao->entity_table == 'MembershipType') {
          $defaults['othersignup'] = 'Membership';
          $defaults['membershipselect'][] = $dao->entity_ref_id;
        }
      }
    }

    // Add the field element in the form.
    $form->add('select', 'othersignup', ts('Other Sign Up?', array('domain' => 'com.aghstrategies.eventmembershipsignup')), $this->entityOptions);
    $form->addEntityRef("membershipselect", ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')), array(
      'entity' => 'membershipType',
      'multiple' => TRUE,
      'placeholder' => '- ' . ts('Select Membership Type', array('domain' => 'com.aghstrategies.eventmembershipsignup')) . ' -',
      'select' => array('minimumInputLength' => 0),
    ));
    $form->addEntityRef('eventselect', ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')), array(
      'entity' => 'event',
      'multiple' => TRUE,
      'placeholder' => '- ' . ts('Select Event', array('domain' => 'com.aghstrategies.eventmembershipsignup')) . ' -',
      'select' => array('minimumInputLength' => 0),
    ));

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
  public static function processOptionAdminForm(&$form) {
    $id = $form->getVar('_oid');
    if (empty($id)) {
      $id = self::findOptionByValues($form->_submitValues);
    }
    switch (CRM_Utils_Array::value('othersignup', $form->_submitValues)) {
      case 'Membership':
        self::updateAdditionalSignups($id, 'MembershipType', $form->_submitValues['membershipselect']);
        break;

      case 'Participant':
        self::updateAdditionalSignups($id, 'Event', $form->_submitValues['eventselect']);
        break;

      default:
        // Clean out additional signup that might be in there.
        $sql = "DELETE FROM civicrm_option_signup WHERE price_option_id = %1";
        CRM_Core_DAO::executeQuery($sql, array(1 => array($id, 'Integer')));
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

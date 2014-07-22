<?php
//save new registration or membership
  if ($op == 'create' && $objectName == 'LineItem') {
    $price_field_value_id = 0;
    $sql = "SELECT * FROM civicrm_option_signup WHERE price_option_id={$objectRef['price_field_value_id']};";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()){
      $option_signup_id = $dao->id;
      $price_field_value_id = $dao->price_option_id;
      $entity_table = $dao->entity_table;
      $entity_ref_id = $dao->entity_ref_id;
    }
    if ($price_field_value_id){
      try{
        $participant = civicrm_api('participant', 'getSingle', array(
          'version' => 3,
          'id' => $objectRef['entity_id'],
          ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
      }
      if ($entity_table=='Event'){
        try{
          $newParticipant = civicrm_api('participant', 'create', array(
            'version' => 3,
            'event_id' => $entity_ref_id,
            'contact_id' => $participant['contact_id'],
            'participant_register_date' => $participant['participant_register_date'],
            'participant_source' => $participant['participant_source'],
           //   'participant_fee_amount' => $participant['participant_fee_amount'],
        //      'participant_fee_level' => $participant['participant_fee_level'],
           //   'participant_fee_currency' => $participant['participant_fee_currency'],
            'participant_status' => $participant['participant_status'],
            'participant_is_pay_later' => $participant['participant_is_pay_later'],
            'participant_registered_by_id' => $participant['participant_registered_by_id'],
            'participant_role_id' => $participant['participant_role_id'],
            ));
        }
        catch (CiviCRM_API3_Exception $e) {
          $error = $e->getMessage();
        }
      }
      else if ($entity_table=='MembershipType'){
         try{
          $newMembership = civicrm_api('Membership', 'create', array(
            'version' => 3,
            'membership_type_id' => $entity_ref_id,
            'contact_id' => $participant['contact_id'],
            'join_date' => $participant['participant_register_date'],
            'start_date' => $participant['participant_register_date'],
           //   'participant_fee_amount' => $participant['participant_fee_amount'],
        //      'participant_fee_level' => $participant['participant_fee_level'],
           //   'participant_fee_currency' => $participant['participant_fee_currency'],
            'status_id' => 1,
            'is_pay_later' >= $participant['participant_is_pay_later'],
            'source' => 'Event Sign Up',
            ));
        }
        catch (CiviCRM_API3_Exception $e) {
          $error = $e->getMessage();
        }
      }
    }
  }

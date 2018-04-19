<?php
/**
 * @file
 * Installer and upgrader for Event Additional Signup extension.
 *
 * Copyright (C) 2014-17, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

/**
 * Collection of upgrade steps.
 */
class CRM_Eventmembershipsignup_Upgrader extends CRM_Eventmembershipsignup_Upgrader_Base {

  /**
   * Installer creates table to track addons for price options.
   */
  public function install() {
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
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE, FALSE);
  }

  public function uninstall() {
    $sql = "DROP TABLE civicrm_option_signup;";
    return $this->executeSql($sql);
  }

  /**
   * Clean up table structure for consistency.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_2000() {
    $this->ctx->log->info('Applying update 2000');

    // In some cases, the revision number wasn't getting set on install.  This
    // checks to see if an index that is added here has already been added.
    $checkIfRan = "SHOW INDEX FROM `civicrm_option_signup` where `Key_name` = 'price_option_id'";
    $dao = CRM_Core_DAO::executeQuery($checkIfRan);
    if ($dao->fetch()) {
      // We can presume the schema is good.
      CRM_Core_Error::debug_log_message("Didn't think the upgrade ran, but it did");
      return TRUE;
    }

    $sql = <<<HERESQL
ALTER TABLE `civicrm_option_signup`
MODIFY `id` int(10) unsigned AUTO_INCREMENT,
MODIFY `price_option_id` int(10) unsigned,
MODIFY `entity_table` varchar(64),
MODIFY `entity_ref_id` int(10) unsigned,
ADD INDEX `price_option_id` (`price_option_id`),
ADD INDEX `entity_table_entity_ref_id` (`entity_table`,`entity_ref_id`),
ADD CONSTRAINT `FK_civicrm_option_signup_price_option_id` FOREIGN KEY (`price_option_id`) REFERENCES `civicrm_price_field_value` (`id`) ON DELETE CASCADE
HERESQL;
    return $this->executeSql($sql);
  }

}

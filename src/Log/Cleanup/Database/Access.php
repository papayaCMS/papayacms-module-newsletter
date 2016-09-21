<?php
/**
 * Log cleanup database access
 *
 * @copyright 2010-2016 by dimensional GmbH - All rights reserved.
 * @link http://www.papaya-cms.com/
 * @license   papaya Commercial License (PCL)
 *
 * Redistribution of this script or derivated works is strongly prohibited!
 * The Software is protected by copyright and other intellectual property
 * laws and treaties. papaya owns the title, copyright, and other intellectual
 * property rights in the Software. The Software is licensed, not sold.
 *
 * @package Papaya-Modules
 * @subpackage Newsletter
 * @version $Id: cronjob_newsletter_send.php 2 2013-12-09 15:38:42Z weinert $
 */

/**
 * Log cleanup database access class
 *
 * @package Papaya-Modules
 * @subpackage Newsletter
 */
class PapayaModuleNewsletterLogCleanupDatabaseAccess extends PapayaDatabaseObject {
  /**
   * Delete unconfirmed log entries (subscriptions and unsubscriptions) older than the designated number of days
   *
   * @param integer $days
   * @param string $mode optional, default value 'unsubscriptions'
   * @return boolean TRUE on success, FALSE otherwise.
   */
  public function cleanup($days, $mode = 'unsubscriptions') {
    $databaseAccess = $this->getDatabaseAccess();
    $actionCondition = "protocol_action = 1";
    if ($mode == 'both') {
      $actionCondition = "protocol_action IN (0, 1)";
    }
    $time = time() - $days * 86400;
    $sql = "DELETE FROM %s
             WHERE $actionCondition
               AND protocol_created < $time
               AND protocol_confirmed = 0";
    $parameters = [$databaseAccess->getTableName('newsletter_protocol')];
    $result = $databaseAccess->queryFmtWrite($sql, $parameters);
    return FALSE !== $result;
  }
}
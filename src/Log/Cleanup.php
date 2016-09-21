<?php
/**
 * Log cleanup
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
 * Log cleanup class
 *
 * @package Papaya-Modules
 * @subpackage Newsletter
 */
class PapayaModuleNewsletterLogCleanup {
  /**
   * Database access object
   * @var PapayaModuleNewsletterLogCleanupDatabaseAccess
   */
  private $_databaseAccess = NULL;

  /**
   * Delete unconfirmed log entries (subscriptions and unsubscriptions) older than the designated number of days
   *
   * @param integer $days
   * @param string $mode optional, default value 'unsubscriptions'
   * @return boolean TRUE on success, FALSE otherwise.
   */
  public function cleanup($days, $mode = 'unsubscriptions') {
    $result = TRUE;
    if ($days > 0 && in_array($mode, ['unsubscriptions', 'both'])) {
      $result = $this->databaseAccess()->cleanup($days, $mode);
    }
    return $result;
  }

  /**
   * Get/set/initialize the database access object
   *
   * @param PapayaModuleNewsletterLogCleanupDatabaseAccess optional, default value NULL
   * @return PapayaModuleNewsletterLogCleanupDatabaseAccess
   */
  public function databaseAccess($databaseAccess = NULL) {
    if ($databaseAccess !== NULL) {
      $this->_databaseAccess = $databaseAccess;
    } elseif ($this->_databaseAccess === NULL) {
      include_once(__DIR__.'/../../src/Log/Cleanup/Database/Access.php');
      $this->_databaseAccess = new PapayaModuleNewsletterLogCleanupDatabaseAccess();
    }
    return $this->_databaseAccess;
  }
}
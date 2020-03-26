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
class PapayaModuleNewsletterLogCleanup extends Papaya\Database\BaseObject {

  const UNSUBSCRIBE_REQUEST = 'unsubscriptions';
  const ANY_REQUEST = 'both';

    /**
   * Delete unconfirmed log entries (subscriptions and unsubscriptions) older than the designated number of days
   *
   * @param integer $days
   * @param string $mode default value 'unsubscriptions'
   * @return FALSE|int Amount of deleted records or false
   */
  public function cleanupLog($days, $mode) {
    if (!($days > 0 && in_array($mode, [self::UNSUBSCRIBE_REQUEST, self::ANY_REQUEST]))) {
      return 0;
    }
    $statement = $this->getDatabaseAccess()->prepare(
  "DELETE FROM :table_protocol
         WHERE protocol_action IN :actions
           AND protocol_created < :created_before
           AND protocol_confirmed = 0"
    );
    $statement->addTableName('table_protocol', 'newsletter_protocol');
    $statement->addIntList('actions', ($mode == self::ANY_REQUEST) ? [0, 1] : [1]);
    $statement->addInt('created_before', time() - $days * 86400);
    return $statement->execute(Papaya\Database\Connection::USE_WRITE_CONNECTION);
  }

  public function cleanupSubscriptions() {
    $statement = $this->getDatabaseAccess()->prepare(
      'DELETE subscriptions 
             FROM :table_subscriptions as subscriptions 
             LEFT JOIN :table_protocol as protocol ON( 
                   protocol.subscriber_id = subscriptions.subscriber_id 
               AND protocol.newsletter_list_id = subscriptions.newsletter_list_id 
             ) 
             WHERE subscriptions.subscription_status IN (0, 1)
               AND protocol.subscriber_id IS NULL'
    );
    $statement->addTableName('table_subscriptions', 'newsletter_subscriptions');
    $statement->addTableName('table_protocol', 'newsletter_protocol');
    return $statement->execute(Papaya\Database\Connection::USE_WRITE_CONNECTION);
  }

  public function cleanupSubscribers() {
    $statement = $this->getDatabaseAccess()->prepare(
      'DELETE subscribers
            FROM :table_subscribers as subscribers
            LEFT JOIN :table_subscriptions as subscriptions USING(subscriber_id)
           WHERE subscriptions.subscriber_id IS NULL'
    );
    $statement->addTableName('table_subscribers', 'newsletter_subscribers');
    $statement->addTableName('table_subscriptions', 'newsletter_subscriptions');
    return $statement->execute(Papaya\Database\Connection::USE_WRITE_CONNECTION);
  }
}

<?php
/**
* Provides a set of methods for accessing the bounce handler tables of the database
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
* @version $Id: newsletter_bounce_db.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
 * Database access
 */
require_once(PAPAYA_INCLUDE_PATH.'/system/sys_base_db.php');

/**
* {%SHORT_CLASS_DESCRIPTION%}
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class newsletter_bounce_db extends base_db {

  var $tableMails = '';
  var $tableCategories = '';
  var $tableSubscribers = '';

  function __construct() {
    $this->tableMails = PAPAYA_DB_TABLEPREFIX.'_newsletter_bouncinghandler_mails';
    $this->tableSubscribers = PAPAYA_DB_TABLEPREFIX.'_newsletter_subscribers';
  }

  function setMailCategory($id, $category) {
    if ($id > 0 && !empty($category)) {
      return $this->databaseUpdateRecord(
        $this->tableMails,
        array('category_id' => $category),
        'mail_id',
        $id
      );
    }
    return FALSE;
  }

  /**
   * Return the subscribers belonging to given email addresses. Unknown addresses will be
   * skipped.
   *
   * @param array $addresses
   * @return array
   */
  function getSubscribersByMailAddress($addresses) {
    $subscribers = array();
    $condition = $this->databaseGetSQLCondition('subscriber_email', $addresses);
    $sql = "SELECT subscriber_id, subscriber_email, subscriber_bounces, subscriber_status
              FROM %s AS s
            WHERE ".str_replace('%', '%%', $condition);
    $params = array($this->tableSubscribers);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $email = papaya_strings::strtolower($row['subscriber_email']);
        $subscribers[$email] = array(
          'id'      => $row['subscriber_id'],
          'counter' => $row['subscriber_bounces'],
          'status'  => $row['subscriber_status']
        );
      }
    }
    return $subscribers;
  }

  /**
   * Update the subscribers value in the database
   *
   * @param array $subscribers
   */
  function updateSubscribers($subscribers) {
    if (is_array($subscribers)) {
      foreach ($subscribers as $subscriber) {
        $this->databaseUpdateRecord(
          $this->tableSubscribers,
          array(
            'subscriber_bounces'   => $subscriber['counter'],
            'subscriber_status'    => $subscriber['status']
          ),
          'subscriber_id',
          $subscriber['id']
        );
      }
    }
  }

  /**
   * Collecting all mails belonging to given category as array.
   *
   * @param int $cat
   * @return array
   */
  function getMailsByCategory($cat) {
    $mails = array();
    if ($cat >= 0) {
      $sql = "SELECT mail_id, content FROM %s
                WHERE category_id = %d
                ORDER BY mail_id";
      $params = array($this->tableMails, $cat);
      if ($res = $this->databaseQueryFmt($sql, $params)) {
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
          $mails[] = array('mail_id' => $row['mail_id'], 'content' => $row['content']);
        }
      }
    }
    return $mails;
  }

  /**
   * Collecting all mails belonging to given category as array.
   *
   * @param int $cat id of the mail category of the list
   * @param int $limit Maximum number of mails to fetch
   * @param int $offset Number of mails to skip before fetching
   * @return array
   */
  function getMailsMetadataByCategory($cat, $limit = 0, $offset = 0) {
    $mails = array();
    if ($cat >= 0) {
      $sql = "SELECT mail_id, date, subject, sender FROM %s
                WHERE category_id = %d
                ORDER BY mail_id DESC";
      $params = array($this->tableMails, $cat);
      if ($limit != 0) {
        $res = $this->databaseQueryFmt($sql, $params, $limit, $offset);
      } else {
        $res = $this->databaseQueryFmt($sql, $params);
      }
      if ($res) {
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
          $mails[] = array(
            'mail_id' => $row['mail_id'],
            'date'    => $row['date'],
            'subject' => $row['subject'],
            'sender'  => $row['sender']
          );
        }
      }
    }
    return $mails;
  }

  /**
  * Returns the number of mails of the given category.
  *
  * @param int $cat id of the mail category
  * @return mixed number of mails or FALSE if query failed
  */
  function getMailCountByCategory($cat) {
    $sql = "SELECT COUNT(*) AS count
              FROM %s
             WHERE category_id = %d";
    $params = array($this->tableMails, $cat);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      return $res->fetchField();
    }
    return FALSE;
  }

  /**
   * returns an associative array
   *
   * @return array
   */
  function getCategories() {
    $categories = array(
      array(
        'id' => 1,
        'name' => $this->_gt('New mails')
      ),
      array(
        'id' => 2,
        'name' => $this->_gt('Bounce mails')
      ),
      array(
        'id' => 3,
        'name' => $this->_gt('Regular mails/Spam')
      )
    );
    return $categories;
  }

  function getMailContent($id) {
    $sql = "SELECT mail_id, mail_uid, date, subject, sender, content
            FROM %s
            WHERE mail_id = %s";
    $params = array($this->tableMails, $id);
    $result = array();
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
      if (!empty($row)) {
        $result = array(
          'id'      => $row['mail_id'],
          'uid'     => $row['mail_uid'],
          'date'    => $row['date'],
          'subject' => $row['subject'],
          'sender'  => $row['sender'],
          'content' => $row['content']
        );
      }
    }
    return $result;
  }

  function updateMailStatus($mails) {
    $bounce = array();
    $ham = array();
    foreach ($mails as $mail) {
      if ($mail['category'] == 'bounce') {
        $bounce[] = $mail['mail_id'];
      } elseif ($mail['category'] == 'ham') {
        $ham[] = $mail['mail_id'];
      } else {
        $this->debugMsg('Unknown category: '.$mail['category']);
      }
    }
    $this->databaseUpdateRecord($this->tableMails, array('category_id' => 2), 'mail_id', $bounce);
    $this->databaseUpdateRecord($this->tableMails, array('category_id' => 3), 'mail_id', $ham);
  }

  function getBlockedSubscribers() {
    $sql = "SELECT subscriber_id, subscriber_email
              FROM %s
            WHERE subscriber_status = 0";
    $params = array($this->tableSubscribers);
    $result = array();
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result[] = $row;
      }
    }
    return $result;
  }

  function activateSubscriber($subscriberId) {
    $values = array('subscriber_status' => 1, 'subscriber_bounces' => 0);
    return $this->databaseUpdateRecord(
      $this->tableSubscribers,
      $values,
      'subscriber_id',
      $subscriberId
    );
  }

}

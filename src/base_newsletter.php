<?php
/**
* Newsletter administration
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
* @version $Id: base_newsletter.php 11 2014-02-19 18:24:09Z SystemVCS $
*/

/**
* Base class for database access
*/
require_once(PAPAYA_INCLUDE_PATH.'system/sys_base_db.php');

/**
* Newsletter administration
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class base_newsletter extends base_db {

  const FORMAT_TEXT = 0;
  const FORMAT_HTML = 1;

  const STATUS_DATAONLY = 0;
  const STATUS_SUBSCRIPTION_REQUESTED = 1;
  const STATUS_SUBSCRIBED = 2;
  const STATUS_UNSUBSCRIPTION_REQUESTED = 3;
  const STATUS_UNSUBSCRIBED = 4;

  const SALUTATION_MALE = 0;
  const SALUTATION_FEMALE = 1;

  /**
  * Lists table
  * @var string
  */
  var $tableLists = "";
  /**
  * Subscribers table
  * @var string
  */
  var $tableSubscribers = "";

  /**
  * Subscriptions table
  * @var string
  */
  var $tableSubscriptions = "";

  /**
  * Protocol table
  * @var string $tableProtocol
  */
  var $tableProtocol = "";

  /**
  * mailings
  * @var array $tableMailings
  */
  var $tableMailings = "";
  /**
  * mailing groups
  * @var array $tableMailingGroups
  */
  var $tableMailingGroups = "";
  /**
  * mailing contents
  * @var array $tableMailingContents
  */
  var $tableMailingContents = "";

  /**
  * newsletter lists
  * @var array
  */
  var $newsletterLists = NULL;

  /**
  * one subscribers list
  * @var array
  */
  var $subscribers = NULL;

  /**
  * one subscriber
  * @var array
  */
  var $subscriber = NULL;

  /**
  * subscriptions
  * @var array
  */
  var $subscriptions = NULL;

  /**
  * subscription detail
  * @var array
  */
  var $subscriptionDetail = NULL;
  /**
  * subscription details
  * @var array
  */
  var $subscriptionDetails = NULL;

  /**
  * One protocol
  * @var array
  */
  var $protocol = NULL;

  /**
  * Surfer count
  * @var integer
  */
  var $subscribersCount = NULL;

  var $tokenExpireHours = 14;
  var $tokenExpireDays = 24;

  /**
  * Surfer status
  * @var array
  *    0 = Data only
  *    1 = Subscription requested
  *    2 = Subscription confirmed
  *    3 = Unsubscription requested
  *    4 = Unsubscribed
  */
  var $status = array();

  /**
  * @var array $activeStatus holds list of status values that indicate active
  */
  var $activeStatus = array(2, 3);
  var $formats = array();
  var $salutations = array();
  var $queueEntries = array();

  /**
  * File max. uploadsize
  * @var integer
  */
  var $maxSize = 6291456;

  /**
  * Helper array to normalize different salutation values
  * @var array $salutationMapping
  */
  var $salutationMapping = array(
    '0'     => 0,
    'Herr'  => 0,
    'Herrn' => 0,
    'Hr'    => 0,
    'Hr.'   => 0,
    'Hrn.'  => 0,
    'Mr.'   => 0,
    'Mr'    => 0,
    'm'     => 0,
    'male'  => 0,

    '1'      => 1,
    'Frau'   => 1,
    'Fr'     => 1,
    'Fr.'    => 1,
    'Mrs.'   => 1,
    'Ms'     => 1,
    'Mrs'    => 1,
    'f'      => 1,
    'female' => 1
  );

  /**
  *
  *
  * @param string $paramName optional, default value 'nwl'
  * @access public
  */
  function __construct($paramName = 'nwl') {
    $this->paramName = $paramName;
    $this->sessionParamName = 'PAPAYA_SESS_'.$paramName;

    $this->tableLanguages = PAPAYA_DB_TBL_LNG;

    $this->tableLists = PAPAYA_DB_TABLEPREFIX.'_newsletter_lists';
    $this->tableSubscribers = PAPAYA_DB_TABLEPREFIX.'_newsletter_subscribers';
    $this->tableSubscriptions = PAPAYA_DB_TABLEPREFIX.'_newsletter_subscriptions';
    $this->tableProtocol = PAPAYA_DB_TABLEPREFIX.'_newsletter_protocol';
    $this->tableMailings = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailings';
    $this->tableMailingGroups = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailinggroups';
    $this->tableMailingOutput = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailingoutput';
    $this->tableMailingContents = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailingcontent';
    $this->tableMailingView = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailingview';
    $this->tableMailingQueue = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailingqueue';

    $this->status = array(
      self::STATUS_DATAONLY => $this->_gt('Data only'),
      self::STATUS_SUBSCRIPTION_REQUESTED => $this->_gt('Subscription requested'),
      self::STATUS_SUBSCRIBED => $this->_gt('Subscription confirmed'),
      self::STATUS_UNSUBSCRIPTION_REQUESTED => $this->_gt('Unsubscription requested'),
      self::STATUS_UNSUBSCRIBED => $this->_gt('Unsubscribed')
    );
    $this->formats = array(
      self::FORMAT_TEXT => $this->_gt('Text'),
      self::FORMAT_HTML => $this->_gt('HTML')
    );
    $this->salutations = array(
      self::SALUTATION_MALE => $this->_gt('Mr.'),
      self::SALUTATION_FEMALE => $this->_gt('Ms.')
    );
  }

  /**
  * Load mailing lists
  *
  */
  function loadNewsletterLists() {
    $this->newsletterLists = array();
    $sql = "SELECT newsletter_list_id, newsletter_list_name,
                   newsletter_list_description, newsletter_list_format, lng_id
              FROM %s ORDER BY newsletter_list_name";
    $params = array($this->tableLists);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->newsletterLists[$row['newsletter_list_id']] = $row;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Load all subscribers of one list
  *
  * @param integer $mailingListId mailing list id
  */
  function getSubscribersByListId($mailingListId) {
    $result = array();
    $sql = "SELECT sr.subscriber_id, sr.subscriber_email,
                   sr.subscriber_salutation, sr.subscriber_title,
                   sr.subscriber_firstname, sr.subscriber_lastname,
                   sr.subscriber_branch, sr.subscriber_company,
                   sr.subscriber_position, sr.subscriber_section,
                   sr.subscriber_street, sr.subscriber_housenumber,
                   sr.subscriber_postalcode, sr.subscriber_city,
                   sr.subscriber_phone, sr.subscriber_mobile, sr.subscriber_fax,
                   sn.newsletter_list_id, sn.subscribtion_status, sn.subscribtion_format
              FROM %s AS sr, %s AS sn
             WHERE sn.newsletter_list_id = '%d'
               AND sr.subscriber_id = sn.subscriber_id";
    $params = array($this->tableSubscribers, $mailingListId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result[$row['subscriber_id']] = $row;
      }
    }
    return $result;
  }

  /**
  * Load subscriber details
  *
  * @param integer $subscriberId
  * @access public
  */
  function loadSubscriber($subscriberId) {
    $sql = "SELECT subscriber_id, subscriber_email,
                   subscriber_salutation, subscriber_title,
                   subscriber_firstname, subscriber_lastname,
                   subscriber_branch, subscriber_company,
                   subscriber_position, subscriber_section,
                   subscriber_street, subscriber_housenumber,
                   subscriber_postalcode, subscriber_city,
                   subscriber_phone, subscriber_mobile, subscriber_fax,
                   subscriber_data, subscriber_status
              FROM %s
             WHERE subscriber_id = '%d'";
    $params = array($this->tableSubscribers, $subscriberId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->subscriber = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Load subscriber details by token and time
  *
  * @param string $token
  * @param integer $time
  * @access public
  */
  function loadSubscriberByTokenAndTime($token, $time) {
    $sql = "SELECT subscriber_id, subscriber_email,
                   subscriber_salutation, subscriber_title,
                   subscriber_firstname, subscriber_lastname,
                   subscriber_branch, subscriber_company,
                   subscriber_position, subscriber_section,
                   subscriber_street, subscriber_housenumber,
                   subscriber_postalcode, subscriber_city,
                   subscriber_phone, subscriber_mobile, subscriber_fax,
                   subscriber_data, subscriber_status
              FROM %s
             WHERE subscriber_chgtoken = '%s' AND subscriber_chgtime = '%d'";
    $params = array($this->tableSubscribers, $token, $time);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->subscriber = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Save surfer
  *
  * @access public
  * @return mixed FALSE or number of affected_rows or database result object
  */
  function saveSubscriber($subscriberId, $values) {
    $data = array(
      'subscriber_email'       => @(string)$values['subscriber_email'],
      'subscriber_salutation'  => $this->salutationMapping[@$values['subscriber_salutation']],
      'subscriber_title'       => @(string)$values['subscriber_title'],
      'subscriber_firstname'   => @(string)$values['subscriber_firstname'],
      'subscriber_lastname'    => @(string)$values['subscriber_lastname'],
      'subscriber_branch'      => @(string)$values['subscriber_branch'],
      'subscriber_company'     => @(string)$values['subscriber_company'],
      'subscriber_position'    => @(string)$values['subscriber_position'],
      'subscriber_section'     => @(string)$values['subscriber_section'],
      'subscriber_street'      => @(string)$values['subscriber_street'],
      'subscriber_postalcode'  => @(string)$values['subscriber_postalcode'],
      'subscriber_city'        => @(string)$values['subscriber_city'],
      'subscriber_phone'       => @(string)$values['subscriber_phone'],
      'subscriber_mobile'      => @(string)$values['subscriber_mobile'],
      'subscriber_fax'         => @(string)$values['subscriber_fax'],
      'subscriber_housenumber' => @(string)$values['subscriber_housenumber'],
      'subscriber_status'      => @(string)$values['subscriber_status']
    );
    $filter = array(
      'subscriber_id' => $subscriberId,
    );
    return (FALSE !== $this->databaseUpdateRecord($this->tableSubscribers, $data, $filter));
  }

  /**
  * Add new subscriber
  *
  * @access public
  * @return mixed FALSE or number of affected_rows or database result object
  */
  function addSubscriber($values) {
    $data = array(
      'subscriber_email' => @(string)$values['subscriber_email'],
      'subscriber_salutation' =>
        @(string)$this->salutationMapping[@$values['subscriber_salutation']],
      'subscriber_title' => @(string)$values['subscriber_title'],
      'subscriber_firstname' => @(string)$values['subscriber_firstname'],
      'subscriber_lastname' => @(string)$values['subscriber_lastname'],
      'subscriber_branch' => @(string)$values['subscriber_branch'],
      'subscriber_company' => @(string)$values['subscriber_company'],
      'subscriber_position' => @(string)$values['subscriber_position'],
      'subscriber_section' => @(string)$values['subscriber_section'],
      'subscriber_street' => @(string)$values['subscriber_street'],
      'subscriber_housenumber' => @(string)$values['subscriber_housenumber'],
      'subscriber_postalcode' => @(string)$values['subscriber_postalcode'],
      'subscriber_city' => @(string)$values['subscriber_city'],
      'subscriber_phone' => @(string)$values['subscriber_phone'],
      'subscriber_mobile' => @(string)$values['subscriber_mobile'],
      'subscriber_fax' => @(string)$values['subscriber_fax'],
      'subscriber_data' => '',
      'subscriber_status' => '1',
      'subscriber_chgtoken' => ''
    );
    return $this->databaseInsertRecord(
      $this->tableSubscribers, 'subscriber_id', $data
    );
  }

  /**
  * Set token for subscriber
  *
  * @param integer $subscriberId subscriber id
  * @param string $token token string
  * @param integer $time ???
  * @return boolean database query success
  */
  function setSubscriberToken($subscriberId, $token, $time = 0) {
    if ($time == 0) {
      $time = time();
    }
    $data = array(
      'subscriber_chgtoken' => $token,
      'subscriber_chgtime'  => $time,
    );
    $filter = array(
      'subscriber_id' => $subscriberId,
    );
    return (FALSE !== $this->databaseUpdateRecord($this->tableSubscribers, $data, $filter));
  }

  /**
  * load subscriptions of a subscriber to $this->subscriptions
  *
  * @param integer $subscriberId subscriber id
  */
  function loadSubscriptions($subscriberId) {
    unset($this->subscriptions);
    $sql = "SELECT subscriber_id, newsletter_list_id,
                   subscription_status, subscription_format
              FROM %s
             WHERE subscriber_id = %d";
    if ($res = $this->databaseQueryFmt($sql, array($this->tableSubscriptions, $subscriberId))) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->subscriptions[$row['newsletter_list_id']] = $row;
      }
    }
  }

  /**
  * add subscription to list for a subscriber
  *
  * @param integer $subscriberId subscriber id
  * @param integer|array $newsletterListId newsletter list id
  * @param integer $status subscriber status id
  * @param integer $format html/text format id
  */
  function addSubscription(
    $subscriberId, $newsletterListId, $status = self::STATUS_DATAONLY, $format = self::FORMAT_TEXT
  ) {
    $result = array();
    $data = array(
      'subscriber_id'       => $subscriberId,
      'subscription_status' => $status,
      'subscription_format' => $format
    );
    if (is_array($newsletterListId) && !empty($newsletterListId)) {
      $subscriptions = array();
      foreach ($newsletterListId as $currentListId) {
        $subscriptions[] = array_merge($data, array('newsletter_list_id' => $currentListId));
      }
      $result = (FALSE !== $this->databaseInsertRecords($this->tableSubscriptions, $subscriptions));
    } elseif (!empty($newsletterListId)) {
      $data['newsletter_list_id'] = $newsletterListId;
      $result = (FALSE !== $this->databaseInsertRecord($this->tableSubscriptions, NULL, $data));
    }
    return $result;
  }

  /**
  * modify existing subscription
  *
  * @param integer $subscriberId subscriber id
  * @param integer $newsletterListId newsletter list id
  * @param integer $status subscriber status id
  * @param integer $format html/text format id
  */
  function saveSubscription($subscriberId, $newsletterListId, $status = NULL, $format = NULL) {
    if (isset($format) || isset($status)) {
      $data = array();
      if (isset($format)) {
        $data['subscription_format'] = $format;
      }
      if (isset($status)) {
        $data['subscription_status'] = $status;
      }
      $filter = array(
        'newsletter_list_id'  => $newsletterListId,
        'subscriber_id'       => $subscriberId,
      );
      return (FALSE !== $this->databaseUpdateRecord($this->tableSubscriptions, $data, $filter));
    }
    return FALSE;
  }

  /**
  * delete subscription for subscriber
  *
  * @param integer $subscriberId subscriber id
  * @param integer $newsletterListId newsletter list id
  * @return boolean TRUE on success, FALSE otherwise
  */
  function deleteSubscription($subscriberId, $newsletterListId) {
    $filter = array(
      'newsletter_list_id'  => $newsletterListId,
      'subscriber_id'       => $subscriberId,
    );
    return (FALSE !== $this->databaseDeleteRecord($this->tableSubscriptions, $filter));
  }

  /**
  * Delete all subscriptions for a specific subscriber
  *
  * @param integer $subscriber id
  * @return boolean TRUE on success, FALSE otherwise
  */
  function deleteUserSubscriptions($subscriberId) {
    $result = TRUE;
    $this->loadSubscriptions($subscriberId);
    if (!empty($this->subscriptions)) {
      foreach ($this->subscriptions as $newsletterListId => $data) {
        $result = $this->deleteSubscription($subscriberId, $newsletterListId) && $result;
      }
    }
    return $result;
  }

  /**
  * Delete a subscriber (and their subscriptions)
  *
  * @param integer $subscriberId
  */
  function deleteSubscriber($subscriberId) {
    $this->deleteUserSubscriptions($subscriberId);
    return (
      FALSE !== $this->databaseDeleteRecord(
        $this->tableSubscribers,
        array('subscriber_id' => $subscriberId)
      )
    );
  }
  /**
  * get status of subscription for a subscriber
  *
  * @param integer $subscriberId subscriber id
  * @param integer $newsletterListId newsletter list id
  */
  function getSubscriptionStatus($subscriberId, $newsletterListId) {
    unset($this->subscriptions);
    $sql = "SELECT subscription_status
              FROM %s
             WHERE subscriber_id = %d
               AND newsletter_list_id = %d";
    $params = array($this->tableSubscriptions, $subscriberId, $newsletterListId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow()) {
        return $row[0];
      }
    }
    return FALSE;
  }

  /**
  * Returns newsletter_list_id corresponding to the activation code.
  *
  * @param string $email
  * @param string $code activation code
  * @param integer $action action id
  */
  function getNewsletterId($email, $code, $action) {
    if (isset($email) && $email != '' && isset($code) && $code != ''
        && isset($action) && $action) {
      $sql = "SELECT p.newsletter_list_id
                FROM %s AS p, %s AS s
               WHERE s.subscriber_email = '%s'
                 AND p.subscriber_id = s.subscriber_id
                 AND p.protocol_action = %d
                 AND p.activate_code = '%s'";
      $params = array($this->tableProtocol, $this->tableSubscribers, $email, $action, $code);
      if ($res = $this->databaseQueryFmt($sql, $params)) {
        $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
        return $row['newsletter_list_id'];
      }
    }
    return FALSE;
  }

  /**
  * Load subscription details by the subscriber id
  *
  * @param integer $subscriberId
  * @access public
  */
  function loadSubscriptionDetails($subscriberId) {
    $sql = "SELECT l.newsletter_list_id, l.lng_id,
                   l.newsletter_list_name, l.newsletter_list_description
            FROM %s AS l, %s AS s
            WHERE l.newsletter_list_id = s.newsletter_list_id
              AND s.subscriber_id = %d
              AND s.subscription_status = 2";
    $params = array($this->tableLists, $this->tableSubscriptions, $subscriberId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->subscriptionDetail[] = $row;
      }
    }
  }

  /**
  * Update surferstatus
  *
  * @param $email
  * @access public
  * @return mixed FALSE or number of affected_rows or database result object
  */
  function updateSurferStatus($email, $mailingListId, $status) {
    $data = array('surfer_status' => $status);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableSurfers,
      $data,
      array('email' => $email, 'newsletter_list_id' => $mailingListId)
    );
  }

  /**
  * Update email in protocol
  *
  * @param string $newEmail
  * @param string $oldEmail
  * @param integer $newsletterId
  * @access public
  */
  function updateProtocol ($newEmail, $oldEmail, $newsletterId) {
    $data = array('protocol_email' => $newEmail);
    $filter = array(
      'protocol_email' => $oldEmail,
      'newsletter_list_id' => $newsletterId
    );
    $this->databaseUpdateRecord($this->tableProtocol, $data, $filter);
  }

  /**
  * Check for existing subscriber per email
  *
  * @param string $email
  * @access public
  * @return integer
  */
  function subscriberExists($email) {
    $sql = "SELECT subscriber_id
              FROM %s
             WHERE subscriber_email = '%s'";
    $params = array($this->tableSubscribers, $email);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow()) {
        return $row[0];
      }
    }
    return FALSE;
  }

  /**
  * checks for a subscription to a list
  *
  * @param string $email
  * @param integer $newsletterListId
  * @access public
  * @return integer
  */
  function subscriptionExists($subscriberId, $newsletterListId) {
    $sql = "SELECT COUNT(*)
              FROM %s AS sn
             WHERE sn.subscriber_id = '%d'
               AND sn.newsletter_list_id = '%d'";
    $params = array($this->tableSubscriptions, $subscriberId, $newsletterListId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      return (bool)$res->fetchField();
    }
    return FALSE;
  }

  /**
  * Add new protocol entry
  *
  * @param integer $subscriberId
  * @param integer $newsletterListId
  * @param integer $action 0=Subscribe, 1=Unsubscribe, 2=Format->txt, 3=Format->html, 4=Import
  * @param string $activateCode optional, default value NULL
  * @param boolean $confirmed optional, default value NULL
  * @access public
  * @return boolean
  */
  function addProtocolEntry($subscriberId, $newsletterListId, $action,
    $activateCode = NULL, $confirmed = FALSE, $subscriberData = '') {
    $data = array(
      'subscriber_id' => (int)$subscriberId,
      'newsletter_list_id' => (int)$newsletterListId,
      'protocol_created' => time(),
      'protocol_action' => (int)$action,
      'subscriber_data' => (string)$subscriberData,
    );
    if ($confirmed) {
      $data['protocol_confirmed'] = $confirmed;
    }
    if (isset($activateCode) && trim($activateCode) != '') {
      $data['activate_code'] = trim($activateCode);
    }
    return (FALSE !== $this->databaseInsertRecord($this->tableProtocol, NULL, $data));
  }

  /**
  * Confirm protocol entry
  *
  * @param integer $subscriberEmail
  * @param integer $action 0=Subscribe, 1=Unsubscribe, 2=Format->txt, 3=Format->html, 4=Import
  * @param string $activateCode optional, default value NULL
  * @access public
  * @return boolean
  */
  function confirmProtocolEntry($subscriberEmail, $action, $activateCode) {
    $sql = "SELECT p.protocol_created, p.newsletter_list_id, p.protocol_action,
                   p.subscriber_id, p.subscriber_data
              FROM %s AS p, %s AS sr, %s AS sn
             WHERE p.subscriber_id = sr.subscriber_id
               AND sr.subscriber_email = '%s'
               AND p.protocol_action = %d
               AND p.activate_code = '%s' AND p.activate_code <> ''
               AND sn.subscriber_id = sr.subscriber_id
               AND sn.newsletter_list_id = p.newsletter_list_id";
    $params = array(
      $this->tableProtocol,
      $this->tableSubscribers,
      $this->tableSubscriptions,
      $subscriberEmail,
      $action,
      $activateCode
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $expire = time() - ($this->tokenExpireDays * 86400) - ($this->tokenExpireHours * 3600);
        if ((int)$row['protocol_created'] > 0 && $row['protocol_created'] > $expire) {
          $dataProtocol = array(
            'protocol_confirmed' => time(),
            'activate_code' => ''
          );
          $filterProtocol = array(
            'subscriber_id' => $row['subscriber_id'],
            'newsletter_list_id' => $row['newsletter_list_id'],
            'activate_code' => $activateCode
          );
          $filterSubscription = array(
            'subscriber_id' => $row['subscriber_id'],
            'newsletter_list_id' => $row['newsletter_list_id']
          );
          if ($row['protocol_action'] == 0) {
            //confirm subscribe
            $dataSubscription = array(
              'subscription_status' => self::STATUS_SUBSCRIBED,
            );
            $dataArr = array();
            $subscriberValues = array();
            if (trim($row['subscriber_data']) != '') {
              simple_xmltree::unserializeArrayFromXML('data', $dataArr, $row['subscriber_data']);
              if (is_array($dataArr) && count($dataArr) > 0) {
                if ($this->loadSubscriber($row['subscriber_id'])) {
                  $fields = array(
                    'subscriber_salutation', 'subscriber_title',
                    'subscriber_firstname', 'subscriber_lastname',
                    'subscriber_branch', 'subscriber_company',
                    'subscriber_position', 'subscriber_section',
                    'subscriber_street', 'subscriber_housenumber',
                    'subscriber_postalcode', 'subscriber_city',
                    'subscriber_phone', 'subscriber_mobile', 'subscriber_fax',
                    'subscriber_data', 'subscriber_status'
                  );
                  foreach ($fields as $fieldName) {
                    if (isset($dataArr[$fieldName])) {
                      $subscriberValues[$fieldName] = $dataArr[$fieldName];
                    } elseif (isset($this->subscriber[$fieldName])) {
                      $subscriberValues[$fieldName] = $this->subscriber[$fieldName];
                    }
                  }
                  $subscriberValues['subscriber_email'] = $this->subscriber['subscriber_email'];
                  $this->saveSubscriber($row['subscriber_id'], $subscriberValues);
                }
                if (isset($dataArr['subscriber_format'])) {
                  $dataSubscription['subscription_format'] =
                    $this->getFormatIndex($dataArr['subscriber_format']);
                }
              }
            }
          } elseif ($row['protocol_action'] == 1) {
            //confirm unsubscribe
            $dataSubscription = array(
              'subscription_status' => self::STATUS_UNSUBSCRIBED
            );
          } elseif ($row['protocol_action'] == 2) {
            //switch to text
            $dataSubscription = array(
              'subscription_status' => self::STATUS_SUBSCRIBED,
              'subscription_format' => self::FORMAT_TEXT,
            );
          } elseif ($row['protocol_action'] == 3) {
            //switch to html
            $dataSubscription = array(
              'subscription_status' => self::STATUS_SUBSCRIBED,
              'subscription_format' => self::FORMAT_HTML,
            );
          } else {
            return FALSE !== $this->databaseUpdateRecord(
              $this->tableProtocol, $dataProtocol, $filterProtocol
            );
          }

          return (
            FALSE !== $this->databaseUpdateRecord(
              $this->tableSubscriptions, $dataSubscription, $filterSubscription
            ) &&
            FALSE !== $this->databaseUpdateRecord(
              $this->tableProtocol, $dataProtocol, $filterProtocol
            )
          );
        }
      }
    }
    return FALSE;
  }

  /**
  * get index of format
  *
  * @param string $format
  * @access public
  */
  function getFormatIndex($format) {
    $format = strtoupper($format);
    foreach ($this->formats as $key => $value) {
      if (strtoupper($value) == $format) {
        return $key;
      }
    }
  }

  /**
  * Get a token for links in confirmation emails
  *
  * @return string
  */
  function getActionToken() {
    srand((double)microtime() * 1000000);
    return substr(crypt(uniqid(rand()), uniqid(rand())), 3, 10);
  }

  /**
  * Create code for (de)activate link
  * send email to surfer for validation
  *
  * @param integer $action
  * @param array $data
  * @access public
  * @return string
  */
  function requestConfirmation($data, $action, $newsletterListId = NULL) {
    if (isset($this->subscriber)) {
      $token = $this->getActionToken();
      if (isset($newsletterListId)) {
        $this->addProtocolEntry(
          $this->subscriber['subscriber_id'], $newsletterListId, $action, $token
        );
        if ($action == 3) {
          $command = 'unsubscribe';
        } else {
          $command = 'subscribe';
        }
        $data['URL'] = $this->getAbsoluteURL(
          $this->module->parentObj->topicId, 'newsletter', FALSE
        );
        $data['LINK'] = $this->getAbsoluteURL(
          $this->getWebLink(
            $this->module->parentObj->topicId,
            NULL,
            NULL,
            array($command => $token, 'email' => $this->subscriber['subscriber_email'])
          ),
          NULL,
          FALSE
        );
      } else {
        $this->setSubscriberToken($this->subscriber['subscriber_id'], $token);
      }

      include_once(PAPAYA_INCLUDE_PATH.'system/sys_email.php');
      $emailObj = new email();
      $emailObj->setSender($this->module->data['mail_from'], $this->module->data['addresser_name']);
      $emailObj->setSubject($data['mail_subject'], $data);
      $emailObj->setBody($data['mail_message'], $data, 80);

      $name = $this->subscriber['subscriber_firstname'].' '.
        $this->subscriber['subscriber_lastname'];
      if (trim($name) != '') {
        $emailObj->addAddress($this->subscriber['subscriber_email'], $name);
      } else {
        $emailObj->addAddress($this->subscriber['subscriber_email']);
      }
      return $emailObj->send();
    }
    return FALSE;
  }

  function loadQueue(
    $max = 20, $offset = 0, $done = FALSE, $newestFirst = FALSE, $scheduledFor = 0
  ) {
    $this->queueEntries = array();
    if ($scheduledFor > 0) {
      $scheduleFilter = " AND mailingqueue_scheduled <= '".((int)$scheduledFor)."'";
    } else {
      $scheduleFilter = '';
    }
    $sql = "SELECT mailingqueue_id, mailingqueue_email,
                   newsletter_list_id, mailingqueue_format,
                   mailingqueue_subject, mailingqueue_from,
                   mailingqueue_done,
                   mailingqueue_created, mailingqueue_scheduled, mailingqueue_sent,
                   mailingqueue_text_status, mailingqueue_html_status
              FROM %s
             WHERE mailingqueue_done = '%d' $scheduleFilter
             ORDER BY mailingqueue_created %s, mailingqueue_subject, mailingqueue_email";
    $params = array(
      $this->tableMailingQueue,
      ($done ? 1 : 0),
      ($newestFirst ? 'DESC' : 'ASC')
    );
    if ($res = $this->databaseQueryFmt($sql, $params, $max, $offset)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->queueEntries[$row['mailingqueue_id']] = $row;
      }
      $this->queueEntryCount = $res->absCount();
    }
  }

  /**
  * Returns the number of queue-entries of the
  * given mailingoutput.
  *
  * @parmam int $mailingoutputId
  */
  function getMailingQueueCount($mailingoutputId) {
    $result = array();
    $sql = "SELECT COUNT(*) AS count, mailingqueue_done
              FROM %s
             WHERE mailingoutput_id = '%d'
             GROUP BY mailingqueue_done
            ";
    $params = array($this->tableMailingQueue, $mailingoutputId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result[] = $row;
      }
    }
    return $result;
  }

  /**
  * Load a specified mailing output in detail.
  *
  * @param integer $mailingoutputId
  */
  function loadOneMailingOutput($mailingoutputId) {
    unset($this->oneMailingOutput);
    $sql = "SELECT mailingoutput_id, o.mailingoutput_title, o.mailingoutput_subject,
                   m.mailing_title, m.unsubscribe_url, o.mailing_id,
                   o.mailingoutput_sender, o.mailingoutput_sendermail,
                   o.mailingoutput_subscribers,
                   o.mailingoutput_text_view, o.mailingoutput_text_data,
                   o.mailingoutput_text_status,
                   o.mailingoutput_html_view, o.mailingoutput_html_data,
                   o.mailingoutput_html_status
              FROM %s AS o, %s AS m
             WHERE o.mailingoutput_id = '%d'
               AND m.mailing_id = o.mailing_id";
    $params = array($this->tableMailingOutput, $this->tableMailings, $mailingoutputId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->oneMailingOutput = $row;
      }
    }
  }
}


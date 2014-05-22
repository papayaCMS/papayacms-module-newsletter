<?php
/**
* Newsletter queue handling
*
* @copyright 2002-2007 by papaya Software GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license   papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package commercial
* @subpackage newsletter
* @version $Id: base_newsletter_queue.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Base class for database access
*/
require_once(dirname(__FILE__).'/base_newsletter.php');

/**
* Newsletter queue handling
*
* @package commercial
* @subpackage newsletter
*/
class base_newsletter_queue extends base_newsletter {

  /**
  * Moduleoptions reader subobject
  *
  * @var unknown_type
  */
  private $_moduleOptions = NULL;

  /**
  * sends up to n emails from queue (called from cronjob)
  *
  * @param integer $n processes n elements at a time
  */
  function processQueue($n = 20) {
    $sent = 0;
    //load list
    $this->loadQueue($n, 0, FALSE, FALSE, time());
    if (isset($this->queueEntries) &&
        is_array($this->queueEntries) &&
        count($this->queueEntries) > 0) {
      foreach ($this->queueEntries as $entry) {
        //load and send
        if ($this->sendQueueEMail($entry['mailingqueue_id'])) {
          //mark as done
          $this->markSentEMail($entry['mailingqueue_id']);
          //count
          ++$sent;
        }
      }
      return array($sent, $this->queueEntryCount - $sent);
    }
    return FALSE;
  }

  /**
  * sets state mailingqueue_done to 1 for mailingqueue_id
  *
  * @param integer $queueId mailingqueue_id
  * @return boolean
  */
  function markSentEMail($queueId) {
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingQueue,
      array('mailingqueue_done' => 1, 'mailingqueue_sent' => time()),
      'mailingqueue_id',
      $queueId
    );
  }

  /**
  * Send a newsletter to the specified and prepared mailing queue
  *
  * @param integer $queueId mailingqueue_id
  * @return boolean
  */
  function sendQueueEMail($queueId) {
    if ($entry = $this->prepareQueueEmail($queueId)) {
      $baseUrl = isset($this->fillValues['subscription.manage.link'])
        ? $this->fillValues['subscription.manage.link']
        : '';
      $newsletterListId = $entry['newsletter_list_id'];
      $activateCode = $this->getActionToken();
      if ($this->fillValues['subscription.format'] == 1) {
        //switch to text
        $this->addProtocolEntry(
          $this->fillValues['subscriber.id'], $newsletterListId, 2, $activateCode
        );
        $this->fillValues['subscription.switchformat'] = $this->getAccountManagerUrl(
          $baseUrl,
          array(
            'subscribetext' => $entry['mailingqueue_email'],
            'confirm' => $activateCode
          )
        );
        $this->fillValues['subscription.switchformat_query'] =
          $this->getAccountManagerQueryString(
            array(
              'subscribetext' => $entry['mailingqueue_email'],
              'confirm' => $activateCode
            )
          );
      } else {
        //switch to html
        $this->addProtocolEntry(
          $this->fillValues['subscriber.id'], $newsletterListId, 3, $activateCode
        );
        $this->fillValues['subscription.switchformat'] = $this->getAccountManagerUrl(
          $baseUrl,
          array(
            'subscribehtml' => $entry['mailingqueue_email'],
            'confirm' => $activateCode
          )
        );
        $this->fillValues['subscription.switchformat_query'] =
          $this->getAccountManagerQueryString(
            array(
              'subscribehtml' => $entry['mailingqueue_email'],
              'confirm' => $activateCode
            )
          );
      }
      $activateCode = $this->getActionToken();
      //unsubscribe
      $this->addProtocolEntry(
        $this->fillValues['subscriber.id'], $newsletterListId, 1, $activateCode
      );
      $this->fillValues['subscription.unsubscribe_link'] =
      $this->fillValues['subscription.unsubscribe'] = $this->getAccountManagerUrl(
        $baseUrl,
        array(
          'unsubscribe' => $entry['mailingqueue_email'],
          'confirm' => $activateCode
        )
      );
      $this->fillValues['subscription.unsubscribe_query'] = $this->getAccountManagerQueryString(
        array(
          'unsubscribe' => $entry['mailingqueue_email'],
          'confirm' => $activateCode
        )
      );
      return $this->sendNewsletterMail($this->mailData, $this->fillValues);
    }
    return FALSE;
  }

  function prepareQueueEmail($queueId) {
    if ($entry = $this->loadQueueEntry($queueId)) {
      $this->mailData = array(
        'email' => $entry['mailingqueue_email'],
        'subject' => $entry['mailingqueue_subject'],
        'from_email' => $entry['mailingqueue_from'],
        'text' => NULL,
        'html' => NULL
      );
      $newsletterListId = $entry['newsletter_list_id'];
      $this->queueData['unsubscribe_url'] = $entry['mailingqueue_url'];
      $this->fillValues = $this->getUserData($entry['mailingqueue_email'], $newsletterListId);
      if (!empty($entry['mailingqueue_url'])) {
        $this->fillValues['subscription.manage.link'] = $this->getAbsoluteUrl(
          $entry['mailingqueue_url'], '', FALSE
        );
      }
      if ($entry['mailingqueue_text_status'] > 0) {
        $this->mailData['text'] = $entry['mailingqueue_text_data'];
      }
      if ($entry['mailingqueue_format'] == 1 && $entry['mailingqueue_html_status'] > 0) {
        $this->mailData['html'] = $entry['mailingqueue_html_data'];
      }
      $this->mailData['name'] = @(string)(
        $this->fillValues['subscriber.firstname'].' '.$this->fillValues['subscriber.lastname']
      );
      $this->fillValues['mailfrom'] = $entry['mailingqueue_from'];
      return $entry;
    }
    return FALSE;
  }

  /**
  * Send a newsletter mail.
  *
  * @param array $data
  * @param $fillValues
  * @return boolean
  */
  function sendNewsletterMail($data, $fillValues) {
    include_once(PAPAYA_INCLUDE_PATH.'system/sys_email.php');
    $emailObj = new email;
    $emailObj->setSubject($data['subject'], $fillValues);
    if ($data['text']) {
      $emailObj->setBody(
        $data['text'],
        $fillValues,
        $this
          ->papaya()
          ->plugins
          ->options['96157ec2db3a16c368ff1d21e8a4824a']
          ->get('NEWSLETTER_TEXT_LINEBREAK', 0)
      );
    }
    if ($data['html']) {
      $emailObj->setBodyHTML($data['html'], $fillValues);
    }
    $emailObj->setSender($data['from_email'], @(string)$data['from']);
    $emailObj->addAddress($data['email'], @(string)$data['name']);
    $returnPath = $this->moduleOptions()->readOption(
      '96157ec2db3a16c368ff1d21e8a4824a', 'NEWSLETTER_RETURN_PATH', NULL
    );
    if (!empty($returnPath)) {
      $emailObj->setReturnPath($returnPath);
    }
    return $emailObj->send();
  }

  /**
  * Get/set module options object
  *
  * @param base_module_options $helper
  * @return base_module_options
  */
  public function moduleOptions(base_module_options $moduleOptions = NULL) {
    if (isset($moduleOptions)) {
      $this->_moduleOptions = $moduleOptions;
    }
    if (is_null($this->_moduleOptions)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_module_options.php');
      $this->_moduleOptions = base_module_options::getInstance();
    }
    return $this->_moduleOptions;
  }


  /**
  * Load user data from database.
  *
  * @param string $email address of the user, whose data has to be loaded.
  * @param int    $newsletterListId of the mailing list the user is registred at.
  *               It is possible that one user has been registered at several mailing lists
  *               at the same.
  * @return array  The user's data.
  */
  function getUserData($email, $newsletterListId) {
    $result = array();
    $sql = "SELECT sr.subscriber_id, sr.subscriber_email,
                   sr.subscriber_salutation, sr.subscriber_title,
                   sr.subscriber_firstname, sr.subscriber_lastname,
                   sr.subscriber_branch, sr.subscriber_company,
                   sr.subscriber_position, sr.subscriber_section,
                   sr.subscriber_street, sr.subscriber_housenumber,
                   sr.subscriber_postalcode, sr.subscriber_city,
                   sr.subscriber_phone, sr.subscriber_mobile, sr.subscriber_fax,
                   sr.subscriber_data, sr.subscriber_status,
                   sn.subscription_format, sn.subscription_status
              FROM %s AS sr, %s AS sn
             WHERE sr.subscriber_id = sn.subscriber_id
               AND sr.subscriber_email = '%s'
               AND sn.newsletter_list_id = %d
               AND sr.subscriber_status = 1";
    $params = array(
      $this->tableSubscribers, $this->tableSubscriptions, $email, (int)$newsletterListId
    );
    if ($res = $this->databaseQueryFmt($sql, $params, 1)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result = array(
          'subscription.format'    => (int)$row['subscription_format'],
          'subscription.status'    => (int)$row['subscription_status'],
          'subscriber.id'          => $row['subscriber_id'],
          'subscriber.email'       => $row['subscriber_email'],
          'subscriber.salutation'  => $row['subscriber_salutation'],
          'subscriber.firstname'   => $row['subscriber_firstname'],
          'subscriber.lastname'    => $row['subscriber_lastname'],
          'subscriber.branch'      => $row['subscriber_branch'],
          'subscriber.company'     => $row['subscriber_company'],
          'subscriber.position'    => $row['subscriber_position'],
          'subscriber.section'     => $row['subscriber_section'],
          'subscriber.title'       => $row['subscriber_title'],
          'subscriber.street'      => $row['subscriber_street'],
          'subscriber.housenumber' => $row['subscriber_housenumber'],
          'subscriber.postalcode'  => $row['subscriber_postalcode'],
          'subscriber.city'        => $row['subscriber_city'],
          'subscriber.phone'       => $row['subscriber_phone'],
          'subscriber.mobile'      => $row['subscriber_mobile'],
          'subscriber.fax'         => $row['subscriber_fax'],
          'subscriber.status'      => (int)$row['subscriber_status'],
          'newsletter.list_id'     => (int)$newsletterListId
        );
      }
    }
    return $result;
  }

  public function getAccountManagerUrl($url, $parameters) {
    $query = $this->getAccountManagerQueryString($parameters);
    $result = $this->getAbsoluteURL($url, NULL, FALSE);
    $result .= ((FALSE == strpos($result, '?')) ? '?' : '&').$query;
    return $result;
  }

  public function getAccountManagerQueryString($parameters) {
    $query = new PapayaRequestParameters($parameters);
    return $query->getQueryString('*');
  }


  /**
  * Load a queue entry from database.
  *
  * @param integer $queueId mailingqueue_id
  * @return array  The queue.
  */
  function loadQueueEntry($queueId) {
    $result = NULL;
    $sql = "SELECT mailingqueue_id, mailingqueue_email,
                   newsletter_list_id, mailingqueue_format,
                   mailingqueue_subject, mailingqueue_from,
                   mailingqueue_done, mailingqueue_created, mailingqueue_sent,
                   mailingqueue_text_status, mailingqueue_text_data,
                   mailingqueue_html_status, mailingqueue_html_data,
                   mailingqueue_url
              FROM %s
             WHERE mailingqueue_id = '%d'";
    $params = array($this->tableMailingQueue, $queueId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result = $row;
      }
    }
    return $result;
  }
}

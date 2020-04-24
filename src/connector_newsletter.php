<?php
/**
* Newsletter connector class
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
* @version $Id: connector_newsletter.php 6 2014-02-13 15:40:43Z SystemVCS $
*/

/**
* Basic class plugin
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_plugin.php');

/**
* Basic class newsletter
*/
require_once(dirname(__FILE__).'/base_newsletter.php');

/**
* Newsletter connector class
*
* Usage:
* $newsletters = $this->papaya()->plugins->get('bfde211a18056caca770c17f8eb4ceea', $this);
*
* For now, this connector pipes through a lot functionality to base_newsletter
* Verbose documentation for each of these method can be found there
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class connector_newsletter extends base_plugin {

  /**
   * @var base_newsletter
   */
  var $baseNewsletter = NULL;

  /**
  * constructor
  */
  function __construct($aOwner, $paramName = NULL) {
    parent::__construct($aOwner, $paramName);
    $this->baseNewsletter = new base_newsletter();
  }

  /**
  * Set the module to be used by the base object
  *
  * @param object $module
  */
  function setModule($module) {
    $this->baseNewsletter->module = $module;
  }

  /**
  * Check a subscription of a particular email address to a specific newsletter
  *
  * @param string $email
  * @param integer $listId
  * @return boolean TRUE if subscription, FALSE otherwise
  */
  function checkSubscription($email, $listId) {
    $result = FALSE;
    $subscriberId = $this->baseNewsletter->subscriberExists($email);
    if (FALSE !== $subscriberId) {
      $result = $this->baseNewsletter->subscriptionExists($subscriberId, $listId);
    }
    return $result;
  }

  // Methods piped through to base_newsletter class

  /**
   * @see base_newsletter::loadNewsletterLists
   */
  function loadNewsletterLists() {
    return $this->baseNewsletter->loadNewsletterLists();
  }

  /**
   * @see base_newsletter::addSubscriber
   * @return mixed FALSE or number of affected_rows or database result object
   */
  function addSubscriber($values) {
    if ($this->checkSubscriberData($values)) {
      return $this->baseNewsletter->addSubscriber($values);
    }
    return FALSE;
  }

  /**
   * @see checkit::isEmail
   */
  function checkSubscriberData($values) {
    if (!checkit::isEmail($values['subscriber_email'], TRUE)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * @see base_newsletter::addSubscription
   */
  function addSubscription($subscriberId, $newsletterListId, $status = 0, $format = 0) {
    return $this->baseNewsletter->addSubscription(
      $subscriberId, $newsletterListId, $status, $format
    );
  }

  /**
   * @see base_newsletter::saveSubscription
   */
  function saveSubscription($subscriberId, $newsletterListId, $status = NULL, $format = NULL) {
    return $this->baseNewsletter->saveSubscription(
      $subscriberId, $newsletterListId, $status, $format
    );
  }

  /**
   * @see base_newsletter::getSubscriptionStatus
   */
  function getSubscriptionStatus($subscriberId, $newsletterListId) {
    return $this->baseNewsletter->getSubscriptionStatus($subscriberId, $newsletterListId);
  }

  /**
   * @see base_newsletter::subscriberExists
   */
  function subscriberExists($email) {
    return $this->baseNewsletter->subscriberExists($email);
  }

  /**
   * @see base_newsletter::subscriptionExists
   */
  function subscriptionExists($subscriberId, $newsletterListId) {
    return $this->baseNewsletter->subscriptionExists($subscriberId, $newsletterListId);
  }

  /**
   * @see base_newsletter::addProtocolEntry
   */
  function addProtocolEntry($subscriberId, $newsletterListId, $action, $activateCode = NULL,
      $confirmed = FALSE, $subscriberData = '') {
    return $this->baseNewsletter->addProtocolEntry(
      $subscriberId,
      $newsletterListId,
      $action,
      $activateCode,
      $confirmed,
      $subscriberData
    );
  }

  /**
   * @see base_newsletter::confirmProtocolEntry
   */
  function confirmProtocolEntry($subscriberEmail, $action, $activateCode) {
    return $this->baseNewsletter->confirmProtocolEntry($subscriberEmail, $action, $activateCode);
  }

  /**
  * Create code for (de)activate link
  * send email to surfer for validation
  *
  * @param integer $action
  * @param integer $subscriberId
  * @param array $data
  * @access public
  * @return string
  */
  function requestConfirmation($subscriberId, $data, $action, $newsletterListId = NULL) {
    $this->baseNewsletter->loadSubscriber($subscriberId);
    return $this->baseNewsletter->requestConfirmation($data, $action, $newsletterListId);
  }

  /**
  * Callback method to be invoked by action dispatcher when a community surfer is deleted
  *
  * @param integer $surferId
  */
  function onDeleteSurfer($surferId) {
    $surfersConnector = $this->papaya()->plugins->get(
      '06648c9c955e1a0e06a7bd381748c4e4',
      $this
    );
    $email = $surfersConnector->getMailById($surferId, TRUE);
    $subscriberId = $this->baseNewsletter->subscriberExists($email);
    if ($subscriberId > 0) {
      $this->baseNewsletter->deleteSubscriber($subscriberId);
    }
  }

  /**
  * Callback method to be invoked by action dispatcher when a surfer's validity is modified
  *
  * @param array $data
  */
  function onSetSurferValid($data) {
    if (isset($data['valid']) && $data['valid'] == 4) {
      $surfersConnector = $this->papaya()->plugins->get(
        '06648c9c955e1a0e06a7bd381748c4e4',
        $this
      );
      $email = $surfersConnector->getMailById($data['surfer_id'], TRUE);
      $subscriberId = $this->baseNewsletter->subscriberExists($email);
      if ($subscriberId > 0) {
        $this->baseNewsletter->deleteUserSubscriptions($subscriberId);
      }
    }
  }

  /**
  * Callback method to be invoked by action dispatcher when a surfer's data is modified
  *
  * @param array $data
  */
  function onModifySurfer($data) {
    if (isset($data['data_before']) && isset($data['data_before']['surfer_email'])) {
      $email = $data['data_before']['surfer_email'];
      $subscriberId = $this->baseNewsletter->subscriberExists($email);
      $subscriberData = array();
      if ($subscriberId > 0) {
        if ($data['data_before']['surfer_email'] != $data['data_after']['surfer_email']) {
          if ($this->baseNewsletter->subscriberExists($data['data_after']['surfer_email'])) {
            return;
          }
        }
        if (!empty($data['data_after']['surfer_gender']) &&
            $data['data_before']['surfer_gender'] != $data['data_after']['surfer_gender']) {
          $subscriberData['subscriber_salutation'] =
            ($data['data_after']['surfer_gender'] == 'm') ? 0 : 1;
        }
        $fields = array(
          'surfer_givenname' => 'subscriber_firstname',
          'surfer_surname' => 'subscriber_lastname',
          'surfer_email' => 'subscriber_email'
        );
        foreach ($fields as $surferField => $subscriberField) {
          if (!empty($data['data_after'][$surferField]) &&
              $data['data_before'][$surferField] != $data['data_after'][$surferField]) {
            $subscriberData[$subscriberField] = $data['data_after'][$surferField];
          }
        }
        $this->baseNewsletter->loadSubscriber($subscriberId);
        $saveData = $this->baseNewsletter->subscriber;
        foreach ($subscriberData as $field => $value) {
          $saveData[$field] = $value;
        }
        $this->baseNewsletter->saveSubscriber($subscriberId, $saveData);
        if ($data['data_after']['surfer_valid'] == 4 &&
            $data['data_before']['surfer_valid'] != $data['data_after']['surfer_valid']) {
          $this->baseNewsletter->deleteUserSubscriptions($subscriberId);
        }
      }
    }
  }

  /**
  * A module provided callback method.
  * @access public
  * @param  $name
  * @param  $element
  * @param  $data
  */
  function callbackMailingListsCombo($name, $element, $data, $paramName = NULL) {
    if (!$paramName) {
      $paramName = $this->paramName;
    }
    $this->loadNewsletterLists();
    $result = '';
    $result .= sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
      papaya_strings::escapeHTMLchars($paramName),
      papaya_strings::escapeHTMLchars($name)
    );
    if (isset($this->baseNewsletter->newsletterLists) &&
        is_array($this->baseNewsletter->newsletterLists) &&
        count($this->baseNewsletter->newsletterLists) > 0) {
      if (!$element[2]) {
        $selected = ($data == 0) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="0" %s>%s</option>'.LF,
          $selected,
          papaya_strings::escapeHTMLchars($this->_gt('None'))
        );
      }
      foreach ($this->baseNewsletter->newsletterLists as $list) {
        $selected = ($data == $list['newsletter_list_id']) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%d" %s>%s</option>'.LF,
          (int)$list['newsletter_list_id'],
          $selected,
          papaya_strings::escapeHTMLchars($list['newsletter_list_name'])
        );
      }
    } else {
      $result .= sprintf(
        '<option value="" disabled="disabled">%s</option>'.LF,
        papaya_strings::escapeHTMLchars($this->_gt('No lists available'))
      );
    }
    $result .= '</select>'.LF;
    return $result;
  }
}


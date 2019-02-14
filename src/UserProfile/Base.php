<?php
/**
* Base class for newsletter user profile page module.
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
* @version $Id: Base.php 6 2014-02-13 15:40:43Z SystemVCS $
*/

/**
* Newsletter user profile page module base class.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class NewsletterUserProfileBase {

  /**
  * Owner object.
  * @var base_content
  */
  public $owner = NULL;

  /**
  * Page configuration data.
  * @var array
  */
  protected $data = array();

  /**
  * Page params.
  * @var array
  */
  protected $params = array();

  /**
  * Page param name.
  * @var string
  */
  protected $paramName = '';

  /**
  * Base newsletter object.
  * @var base_newsletter
  */
  protected $newsletterObject = NULL;

  /**
  * Configuration object for further instances.
  * @var PapayaConfiguration
  */
  private $_configuration = NULL;

  /**
  * Allowed message types.
  * @var array
  */
  private $_messageTypes = array('info', 'warning', 'error');

  /**
  * Collected messages during script running.
  * @var array
  */
  private $_messages = array();

  /**
  * Instance of papaya_page.
  * @var papaya_page
  */
  private $_page = NULL;

  /**
  * Constructor of the class.
  *
  * @param object $owner Caller object
  */
  public function __construct($owner = NULL) {
    $this->owner = $owner;
  }

  /**
  * Sets configuration option.
  *
  * @param PapayaConfiguration $configuration
  */
  public function setConfiguration($configuration) {
    $this->_configuration = $configuration;
  }

  /**
  * Returns the current configuration.
  *
  * @return PapayaConfiguration
  */
  public function getConfiguration() {
    return $this->_configuration;
  }

  /**
  * Sets the page params.
  *
  * @param array $params
  */
  public function setPageParams($params) {
    $this->params = $params;
  }

  /**
  * Sets the content modules param name.
  *
  * @param string $paramName
  */
  public function setPageParamName($paramName) {
    $this->paramName = $paramName;
  }

  /**
  * Sets page configuration data
  *
  * @param array $data current configuration data
  */
  public function setPageData($data) {
    $this->data = $data;
  }

  /**
  * This method sets the papaya_page object used for redirection
  *
  * @param papaya_page $page
  * @return boolean
  */
  public function setPageObject($page) {
    $result = FALSE;
    if ($page instanceof papaya_page) {
      $this->_page = $page;
      $result = TRUE;
    }
    return $result;
  }

  /**
  * This method retrieves the page object that was set or the one in global namespace or a new one
  *
  * @return papaya_page
  */
  public function getPageObject() {
    if ($this->_page instanceof papaya_page) {
      $result = $this->_page;
    } elseif (isset($GLOBALS['PAPAYA_PAGE']) && $GLOBALS['PAPAYA_PAGE'] instanceof papaya_page) {
      $result = $GLOBALS['PAPAYA_PAGE'];
    } else {
      include_once(PAPAYA_INCLUDE_PATH.'system/papaya_page.php');
      $result = new papaya_page;
    }
    return $result;
  }


  /**
  * Instantiate base_newsletter to content_newsletter::newsletterObject
  * @return base_newsletter
  */
  public function getNewsletterObject() {
    if (!is_object($this->newsletterObject)) {
      include_once(dirname(__FILE__).'/../base_newsletter.php');
      $this->newsletterObject = new base_newsletter;
      $this->newsletterObject->module = $this;
    }
    return $this->newsletterObject;
  }

  /**
  * Sets the newsletter object to use.
  * @param object $newsletterObject
  */
  public function setNewsletterObject($newsletterObject) {
    $this->newsletterObject = $newsletterObject;
  }

  /**
  * Adds a message to local message array for later output.
  *
  * @param string $type message tye, can be info|warning|error
  * @param string $text message content
  */
  public function addMessage($type, $text) {
    if (in_array($type, $this->_messageTypes)) {
      $this->_messages[] = array('type' => $type, 'text' => $text);
    }
  }

  /**
  * Returns xml output for all messages.
  *
  * @return string output xml
  */
  public function getMessages() {
    $result = '';
    if (!empty($this->_messages)) {
      $result .= '<messages>'.LF;
      foreach ($this->_messages as $currentMessage) {
        $result .= sprintf(
          '<message type="%s">%s</message>'.LF,
          PapayaUtilStringXml::escapeAttribute($currentMessage['type']),
          PapayaUtilStringXml::escape($currentMessage['text'])
        );
      }
      $result .= '</messages>'.LF;
    }
    return $result;
  }

  /**
  * Returns subscriber data from given surfer object.
  *
  * @param base_surfer $surfer
  * @return array
  */
  public function getSubscriberDataFromSurfer($surfer) {
    PapayaUtilConstraints::assertNotEmpty($surfer->surferEMail);
    $subscriber = array('subscriber_email' => $surfer->surferEMail);
    if (!empty($surfer->surfer['surfer_givenname'])) {
      $subscriber['subscriber_firstname'] = $surfer->surfer['surfer_givenname'];
    }
    if (!empty($surfer->surfer['surfer_surname'])) {
      $subscriber['subscriber_lastname'] = $surfer->surfer['surfer_surname'];
    }
    if (!empty($surfer->surfer['surfer_gender'])) {
      if ($surfer->surfer['surfer_gender'] == 'f') {
        $subscriber['subscriber_salutation'] = 1;
      } elseif ($surfer->surfer['surfer_gender'] == 'm') {
        $subscriber['subscriber_salutation'] = 0;
      }
    }
    return $subscriber;
  }

  /**
  * Sends a complete report of saved subscription settings.
  *
  * @param array $receiver with email and further fields
  * @param array $lists
  * @param array $newSubscriptions optional
  * @param array $reactivatedSubscriptions optional
  * @param array $removedSubscriptions optional
  * @return boolean result
  */
  public function sendEmailReport($receiver, $lists, $newSubscriptions = array(),
      $reactivatedSubscriptions = array(), $removedSubscriptions = array()) {
    if (empty($this->data['senderEMail']) || empty($this->data['senderName'])) {
      return FALSE;
    }
    $result = TRUE;
    if (!empty($newSubscriptions) || !empty($reactivatedSubscriptions) ||
        !empty($removedSubscriptions)) {
      // prepare email report text
      $subscriptionText = '';
      // check if there are any subscriptions left after saving settings
      $subscriptionsExist = FALSE;
      foreach ($lists as $listId => $listData) {
        if ($listData['__SUBSCRIBED'] === TRUE) {
          // subscription found, abort and store it
          $subscriptionsExist = TRUE;
          break;
        }
      }
      if ($subscriptionsExist) {
        // still there are subscriptions left
        // new subscriptions report
        if (!empty($newSubscriptions)) {
          $subscriptionText .= $this->data['mailTextNewSubscriptions'];
          $subscriptionText .= $this->getNewsletterNameList($newSubscriptions, $lists);
        }
        // reactivated subscriptions report
        if (!empty($reactivatedSubscriptions)) {
          $subscriptionText .= $this->data['mailTextReactivatedSubscriptions'];
          $subscriptionText .= $this->getNewsletterNameList($reactivatedSubscriptions, $lists);
        }
        // removed subscriptions report
        if (!empty($removedSubscriptions)) {
          $subscriptionText .= $this->data['mailTextRemovedSubscriptions'];
          $subscriptionText .= $this->getNewsletterNameList($removedSubscriptions, $lists);
        }
      } else {
        // no subscriptions left, everything removed
        $subscriptionText .= $this->data['mailTextNoSubscriptions'];
      }
      // prepare dynamic data
      $salutation = (
        isset($receiver['subscriber_salutation']) &&
        (int)$receiver['subscriber_salutation'] == 1
      ) ? $this->data['salutationFemale'] : $this->data['salutationMale'];
      $data = array(
        'LINK' => $this->owner->getAbsoluteURL($this->owner->getWebLink(), 'newsletter', FALSE),
        'SUBSCRIPTIONS' => $subscriptionText,
        'NAME' => trim($receiver['subscriber_firstname'].' '.$receiver['subscriber_lastname']),
        'SALUTATION' => $salutation
      );
      // generate email and send it
      include_once(PAPAYA_INCLUDE_PATH.'system/sys_email.php');
      $emailObj = new email();
      $emailObj->setSender($this->data['senderEMail'], $this->data['senderName']);
      $emailObj->setSubject($this->data['mailSubject']);
      $emailObj->setBody($this->data['mailText'], $data, 80);
      $receiverName = $receiver['subscriber_firstname'].' '.$receiver['subscriber_lastname'];
      $emailObj->addAddress($receiver['subscriber_email'], $receiverName);
      $result = $emailObj->send();
    }
    return $result;
  }

  /**
  * Generates a list text by given subscription ids.
  *
  * @param array $subscriptions
  * @param array $lists available newsletter lists
  * @return string list text
  */
  public function getNewsletterNameList($subscriptions, $lists) {
    $result = '';
    foreach ($subscriptions as $currentId) {
      $result .= sprintf(
        '- %s'.LF,
        PapayaUtilStringXml::escape(
          PapayaUtilArray::get($lists[$currentId], 'newsletter_list_name', '')
        )
      );
    }
    return (!empty($result)) ? LF.$result.LF : $result;
  }

  /**
  * Saves a new subscriber and its subscriptions by given surfer data and submitted params.
  *
  * @param base_surfer $surfer
  * @param base_newsletter $newsletterObject
  * @param array $lists available newsletter lists
  * @return integer
  */
  public function saveNewSubscriberWithSubscriptions($surfer, $newsletterObject, $lists) {
    // first add subscriber
    $subscriber = $this->getSubscriberDataFromSurfer($surfer);
    $subscriberId = $newsletterObject->addSubscriber($subscriber);
    // add subscriptions
    $newsletterListIds = array();
    foreach ($lists as $listId => $listData) {
      if (isset($this->params['newsletter_list_id'][$listId])) {
        $format = $this->params['newsletter_list_id'][$listId];
        if (isset($newsletterObject->formats[$format])) {
          $newsletterListIds[$format][] = $listId;
          // update lists for output
          $lists[$listId]['__SUBSCRIBED'] = TRUE;
        }
      }
    }
    $subscribedTo = array();
    foreach ($newsletterListIds as $format => $listIds) {
      $result = $newsletterObject->addSubscription(
        $subscriberId, $newsletterListIds[$format], base_newsletter::STATUS_SUBSCRIBED, $format
      );
      $subscribedTo = array_merge($subscribedTo, $newsletterListIds[$format]);
      if (FALSE === $result) {
        $this->addMessage(
          'error', $this->data['messageConfigurationNotSaved'].' (addSubscription)'
        );
        return;
      }
    }
    $this->addMessage('info', $this->data['messageConfigurationSaved']);
    // send email report
    $mailResult = $this->sendEmailReport($subscriber, $lists, $subscribedTo);
    if ($mailResult === FALSE) {
      $this->addMessage('error', $this->data['messageErrorSendingMail']);
    }
    return $subscriberId;
  }

  /**
  * Saves subscription settings for existing subscriber.
  *
  * @param base_surfer $surfer
  * @param integer $subscriberId
  * @param array $existingSubscriptions already existing subscription ids
  * @param base_newsletter $newsletterObject
  * @param array $lists available newsletter lists
  */
  public function saveExistingSubscriberSubscriptions($surfer, $subscriberId,
      $existingSubscriptions, $newsletterObject, $lists) {
    // collection of new, reactivated & removed subscriptions for email report
    $newSubscriptions = array();
    $reactivatedSubscriptions = array();
    $removedSubscriptions = array();
    // get submitted subscriptions; nothing submitted => all subscriptions removed
    $submittedSubscriptions = (!empty($this->params['newsletter_list_id'])) ?
      $this->params['newsletter_list_id'] : array();
    // remove subscriptions if ids not submitted but already exist
    foreach ($existingSubscriptions as $listId => $existingStatus) {
      if (!isset($submittedSubscriptions[$listId]) &&
          (int)$existingStatus == base_newsletter::STATUS_SUBSCRIBED) {
        // existing subscription removed; status = 4 means surfer unsubscribed
        if (FALSE !== $newsletterObject->saveSubscription(
            $subscriberId, $listId, base_newsletter::STATUS_UNSUBSCRIBED)) {
          // remove id from collection and update newletter list for output
          unset($existingSubscriptions[$listId]);
          $lists[$listId]['__SUBSCRIBED'] = FALSE;
          // collect id for email report
          $removedSubscriptions[] = $listId;
        }
      }
    }
    // reactivate old subscriptions
    // check each id if submitted but not already exists => add; if exists => reactivate
    foreach ($submittedSubscriptions as $listId => $format) {
      if (!isset($existingSubscriptions[$listId])) {
        // it's a new subscription
        $result = $newsletterObject->addSubscription(
            $subscriberId, $listId, base_newsletter::STATUS_SUBSCRIBED, $format
        );
        $newSubscriptions[] = $listId;
        // update lists for output
        $lists[$listId]['__SUBSCRIBED'] = TRUE;
      } elseif (!empty($existingSubscriptions[$listId]) &&
          (int)$existingSubscriptions[$listId] == base_newsletter::STATUS_UNSUBSCRIBED) {
        // it's an old deactived subscription
        $result = $newsletterObject->saveSubscription(
            $subscriberId, $listId, base_newsletter::STATUS_SUBSCRIBED, $format
        );
        if (FALSE !== $result) {
          // collect id for email report
          $reactivatedSubscriptions[] = $listId;
          // update lists for output
          $lists[$listId]['__SUBSCRIBED'] = TRUE;
        }
      }
    }
    if (!empty($newSubscriptions) || !empty($reactivatedSubscriptions) ||
        !empty($removedSubscriptions)) {
      $this->addMessage('info', $this->data['messageConfigurationSaved']);
    }
    // send email report
    $mailResult = $this->sendEmailReport(
      $this->getSubscriberDataFromSurfer($surfer),
      $lists,
      $newSubscriptions,
      $reactivatedSubscriptions,
      $removedSubscriptions
    );
    if ($mailResult === FALSE) {
      $this->addMessage('error', $this->data['messageErrorSendingMail']);
    }
  }

  /**
  * Returns the main output xml.
  */
  public function getXml() {
    $result = $this->getTitlesAndTextXml();
    $surfer = $this->owner->papaya()->surfer;
    if ($surfer->isValid) {
      $newsletterObject = $this->getNewsletterObject();
      $newsletterObject->loadNewsletterLists();
      $lists = $newsletterObject->newsletterLists;
      if (!empty($lists)) {
        // check if surfer is already a subscriber
        $subscriberId = $newsletterObject->subscriberExists($surfer->surferEMail);
        if ($subscriberId !== FALSE) {
          // yes, subscriber id exists, load subscriptions for current surfer
          $newsletterObject->loadSubscriptions((int)$subscriberId);
          $existingSubscriptions = array();
          if (!empty($newsletterObject->subscriptions)) {
            foreach ($newsletterObject->subscriptions as $listId => $listData) {
              if (!empty($lists[$listData['newsletter_list_id']])) {
                $existingSubscriptions[$listData['newsletter_list_id']] =
                  $listData['subscription_status'];
                if ((int)$listData['subscription_status'] == 2) {
                  $lists[$listData['newsletter_list_id']]['__SUBSCRIBED'] = TRUE;
                }
              }
            }
          }
          // check if dialog has been submitted
          if (!empty($this->params['confirm'])) {
            $this->saveExistingSubscriberSubscriptions(
              $surfer, $subscriberId, $existingSubscriptions, $newsletterObject, $lists
            );
          }
        } else {
          // no subscriber id found, check if dialog has been submitted
          if (!empty($this->params['confirm']) && !empty($this->params['newsletter_list_id'])) {
            $subscriberId = $this->saveNewSubscriberWithSubscriptions(
              $surfer, $newsletterObject, $lists
            );
          }
        }
        // show configuration dialog after reloading subscriptions
        $newsletterObject->loadSubscriptions((int)$subscriberId);
        if (!empty($newsletterObject->subscriptions)) {
          foreach ($lists as $listId => $listData) {
            if (array_key_exists($listId, $newsletterObject->subscriptions) &&
                $newsletterObject->subscriptions[$listId]['subscription_status'] == 2) {
              $lists[$listId]['__SUBSCRIBED'] = TRUE;
            } else {
              $lists[$listId]['__SUBSCRIBED'] = FALSE;
            }
          }
        }
        $result .= $this->getConfigurationDialogXml($lists, $subscriberId);
      } else {
        $this->addMessage('error', $this->data['messageNoLists']);
      }
    } elseif (!empty($this->data['pageLogin'])) {
      // surfer invalid, login page id set
      $this->getPageObject()->doRedirect(302, $this->owner->getWebLink($this->data['pageLogin']));
    } else {
      // surfer invalid and no login page id defined
      $this->addMessage('error', $this->data['messageNotLoggedIn']);
    }
    // finally show messages
    $result .= $this->getMessages();
    return $result;
  }

  /**
  * Returns the output xml for page titles.
  *
  * @return string output xml
  */
  public function getTitlesAndTextXml() {
    $result = sprintf('<title>%s</title>'.LF, PapayaUtilStringXml::escape($this->data['title']));
    if (!empty($this->data['subtitle'])) {
      $result .= sprintf(
        '<subtitle>%s</subtitle>'.LF, PapayaUtilStringXml::escape($this->data['subtitle'])
      );
    }
    if (!empty($this->data['text'])) {
      $result .= sprintf(
        '<text>%s</text>'.LF, PapayaUtilStringXml::escape($this->data['text'])
      );
    }
    return $result;
  }

  /**
  * Returns the configuration dialog output.
  *
  * @param array $lists available newsletter lists
  * @param integer $subscriberId for current surfer
  * @return string output xml
  */
  public function getConfigurationDialogXml($lists, $subscriberId) {
    $hidden = array('confirm' => 1);
    $result = sprintf('<dialog action="%s" method="post">'.LF, $this->owner->getWebLink());
    foreach ($hidden as $hiddenFieldKey => $hiddenFieldValue) {
      $result .= sprintf(
        '<input type="hidden" name="%s[%s]" value="%s"/>'.LF,
        $this->paramName,
        $hiddenFieldKey,
        $hiddenFieldValue
      );
    }
    foreach ($lists as $listId => $listData) {
      $caption = $listData['newsletter_list_name'];
      if (!empty($listData['newsletter_list_description'])) {
        $caption .= sprintf('<em>%s</em>', $listData['newsletter_list_description']);
      }
      $checked = '';
      if (isset($listData['__SUBSCRIBED']) && $listData['__SUBSCRIBED'] == TRUE) {
        $checked = ' checked="checked"';
      }
      $result .= sprintf(
        '<label for="newsletterList%1$d">%3$s</label>'.LF.
        '<input id="newsletterList%1$d" type="checkbox" name="%4$s[newsletter_list_id][%1$d]" '.
        'value="%2$d"%5$s/>'.LF,
        $listId,
        PapayaUtilArray::get($listData, 'newsletter_list_format', 0),
        $caption,
        $this->paramName,
        $checked
      );
    }
    $result .= sprintf(
      '<input type="submit" value="%s"/>'.LF,
      PapayaUtilStringXml::escapeAttribute($this->data['captionSubmitButton'])
    );
    $result .= '</dialog>'.LF;
    return $result;
  }
}

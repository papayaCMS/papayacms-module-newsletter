<?php
/**
* Page module for managing newsletter subscriptions as community user.
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
* @version $Id: UserProfile.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Basic class page module
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_content.php');

/**
* Newsletter user profile page module.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class NewsletterUserProfile extends base_content {

  /**
  * Preview flag.
  * @var boolean
  */
  public $preview = TRUE;

  /**
  * Parameter group name.
  * @var string
  */
  public $paramName = 'nws';

  /**
  * Grouped edit fields.
  * @var array
  */
  public $editGroups = array(
    array(
      'General',
      'categories-content',
      array(
        'Titles',
        'title' => array('Title', 'isNoHtml', TRUE, 'input', 150, '', 'Newsletter subscriptions'),
        'subtitle' => array('Subtitle', 'isNoHtml', FALSE, 'input', 150),
        'Text',
        'text' => array('Description', 'isSomeText', FALSE, 'textarea', 5),
        'salutationFemale' => array(
          'Salutation: female', 'isNoHtml', FALSE, 'input', 50, '', 'Mrs.'
        ),
        'salutationMale' => array(
          'Salutation: male', 'isNoHtml', FALSE, 'input', 50, '', 'Mr.'
        ),
        'Pages',
        'pageLogin' => array(
          'Login page',
          'isNum',
          TRUE,
          'pageid',
          3,
          'Page to redirect to, if surfer is not logged in.'
        ),
        'Dialog',
        'captionSubmitButton' => array(
          'Submit button caption', 'isNoHtml', TRUE, 'input', 50, '', 'Save configuration'
        )
      )
    ),
    array(
      'Mail',
      'items-mail',
      array(
        'senderEMail' => array(
          'Sender mail', 'isEmail', TRUE, 'input', 200, 'team@webpage.tld'
        ),
        'senderName' => array(
          'Sender name', 'isNoHTML', TRUE, 'input', 200, '', 'Webpage Team'
        ),
        'mailSubject' => array(
          'Mail subject', 'isNoHTML', TRUE, 'input', 200, '', 'Newsletter administration'
        ),
        'mailText' => array(
          'General mail text',
          'isSomeText',
          TRUE,
          'textarea',
          10,
          'EMail report text. Use placeholder {%LINK%} for linking this page, {%SALUTATION%} and
            {%NAME%} for addressing the receiver and {%SUBSCRIPTIONS%} for inserting detailed
            subscription report.',
          'Dear {%SALUTATION%} {%NAME%}, {%SUBSCRIPTIONS%} {%LINK%}'
        ),
        'mailTextNewSubscriptions' => array(
          'Text for new subscriptions',
          'isSomeText',
          TRUE,
          'textarea',
          2,
          '',
          'You have successfully subscribed to the following newletter(s):'
        ),
        'mailTextReactivatedSubscriptions' => array(
          'Text for reactivated subscriptions',
          'isSomeText',
          TRUE,
          'textarea',
          2,
          '',
          'You have successfully reactivated your subscription to the following newletter(s):'
        ),
        'mailTextRemovedSubscriptions' => array(
          'Text for removed subscriptions',
          'isSomeText',
          TRUE,
          'textarea',
          2,
          '',
          'You have successfully unsubscribed to the following newletter(s):'
        ),
        'mailTextNoSubscriptions' => array(
          'Text for no subscriptions',
          'isSomeText',
          TRUE,
          'textarea',
          2,
          '',
          'Your have successfully unsubscribed to all newsletters.'
        )
      )
    ),
    array(
      'Messages',
      'items-message',
      array(
        'Success',
        'messageConfigurationSaved' => array(
          'Configuration saved',
          'isNoHtml',
          TRUE,
          'input',
          255,
          'Success message after saving configuration',
          'Your newsletter subscription settings have been successfully saved.'
        ),
        'Error',
        'messageConfigurationNotSaved' => array(
          'Configuration saved',
          'isNoHtml',
          TRUE,
          'input',
          255,
          'Error message for saving configuration failed',
          'Error while saving your newsletter subscription settings.'
        ),
        'messageNotLoggedIn' => array(
          'User not logged in',
          'isNoHtml',
          TRUE,
          'input',
          255,
          'Error message for invalid surfer',
          'No access to newsletter subscriptions.
          Please log in to manage your newsletter subscriptions.'
        ),
        'messageInvalidSurferEmail' => array(
          'User with invalid EMail adress',
          'isNoHtml',
          TRUE,
          'input',
          255,
          'Error message for invalid surfer EMail adress',
          'Invalid surfer EMail adress.'
        ),
        'messageNoLists' => array(
          'No subscriber list',
          'isNoHtml',
          TRUE,
          'input',
          255,
          'Error message if no subscriber list was found',
          'No newsletter subscriber list found.'
        ),
        'messageErrorSendingMail' => array(
          'Sending EMail failed',
          'isNoHtml',
          TRUE,
          'input',
          255,
          'Error message when sending EMail report fails',
          'Sending EMail report failed.'
        )
      )
    )
  );

  /**
  * Instance of page base object object.
  * @var NewsletterUserProfileBase
  */
  protected $pageBaseObject = NULL;

  /**
  * Set page base object.
  *
  * @param object $object
  */
  public function setPageBaseObject($object) {
    if (is_object($object)) {
      $this->pageBaseObject = $object;
    }
  }

  /**
  * Get page base object.
  *
  * @return NewsletterUserProfileBase
  */
  public function getPageBaseObject($parseParams = NULL) {
    if (!is_object($this->pageBaseObject)) {
      include_once(dirname(__FILE__).'/UserProfile/Base.php');
      $this->pageBaseObject = new NewsletterUserProfileBase($this);
      $this->pageBaseObject->setPageParamName($this->paramName);
      $this->pageBaseObject->setPageParams($this->params);
      $this->pageBaseObject->setPageData($this->data);
      $this->pageBaseObject->setConfiguration($this->papaya()->options);
    }
    return $this->pageBaseObject;
  }

  /**
  * Return page XML
  *
  * @return string
  */
  public function getParsedData() {
    $this->setDefaultData();
    return $this->getPageBaseObject()->getXml();
  }

}

<?php
/**
* Page module newsletter
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
* @version $Id: content_newsletter_subscribe.php 6 2014-02-13 15:40:43Z SystemVCS $
*/

/**
* Page module newsletter
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class content_newsletter extends base_content {

  var $cacheable = FALSE;
  var $paramName = 'nws';

  /**
   * @var base_newsletter
   */
  var $newsletterObject = NULL;

  /**
  * Content edit fields
  * @var array
  */
  var $editFields = array(
    'newsletter_list_id' => array('List', 'isNum', TRUE, 'function',
      'callbackMailingListsCombo', ''),
    'newsletter_list_title' => array('List caption', 'isNoHTML', FALSE, 'input', 200),
    'newsletter_list_id2' => array('Optional List', 'isNum', FALSE, 'function',
      'callbackMailingListsCombo', ''),
    'newsletter_list_title2' => array('Optional list caption', 'isNoHTML', FALSE, 'input', 200),
    'checkbox' => array('Newsletter', 'isNum', TRUE, 'combo',
      array(0 => 'not optional', 1 => 'optional')),
    'Text',
    'nl2br' => array('Automatic linebreaks', 'isNum', TRUE, 'combo',
      array(0 => 'Yes', 1 => 'No'),
      'Apply linebreaks from input to the HTML output.'),
    'title' => array('Title', 'isNoHTML', TRUE, 'input', 200, ''),
    'subtitle' => array ('Subtitle', 'isSomeText', FALSE, 'input', 400),
    'teaser' => array('Teaser', 'isSomeText', FALSE, 'simplerichtext', 10),
    'text' => array('Text', 'isSomeText', FALSE, 'richtext', 20)
  );
  var $editFieldsMail = array(
    'mail_from' => array('Sender mail', 'isEmail', TRUE, 'input', 200,
      'team@webpage.tld', ''),
    'addresser_name' => array('Sender name', 'isNoHTML', TRUE, 'input', 200, '',
      'Webpage Team'),
    'mail_subject' => array('Mail subject', 'isNoHTML', TRUE, 'input', 200, '',
      'Newsletter administration'),
    'mail_message' => array('Mail message', 'isSomeText', TRUE, 'textarea', 10, '',
      "Please confirm your registration. \n\n {%LINK%}"),
  );
  var $editFieldsDialog = array(
    'Email',
    'cap_email' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '',
      'Email'),
    'Privacy',
    'cap_privacy' => array('Title', 'isNoHTML', FALSE, 'input', 200, '', ''),
    'text_privacy' => array('Text', 'isSomeText', FALSE, 'richtext', 10),
    'show_privacy' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_privacy' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'use_token' => array('Use dialog Token', 'isNum', TRUE, 'yesno', NULL, '', 1),
    'Format',
    'cap_format' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '',
      'Newsletter format'),
    'show_format' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 1),
    'default_format' => array('Default', 'isNum', TRUE, 'combo', array('text', 'html'), '', 1),
    'cap_format_html' => array('Option: HTML', 'isNoHTML', TRUE, 'input', 200, '',
      'HTML'),
    'cap_format_text' => array('Option: Text', 'isNoHTML', TRUE, 'input', 200, '',
      'Text'),
    'Confirmation',
    'cap_send' => array('Caption', 'isNoHTML', TRUE, 'input', 200,
      'Caption of the confirmation checkbox.', 'Please send me the newsletter.'),
    'show_send' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Button',
    'cap_submit' => array('Caption', 'isNoHTML', TRUE, 'input', 200,
      'Caption of submit button.', 'Subscribe'),
    'Salutation',
    'cap_salutation' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '',
      'Salutation'),
    'show_salutation' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'cap_salutation_male' => array('Option: Male', 'isNoHTML', TRUE, 'input',
      200, '', 'Mr.'),
    'cap_salutation_female' => array('Option: Female', 'isNoHTML', TRUE,
      'input', 200, '', 'Mrs.'),
    'Title',
    'cap_title' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Title'),
    'show_title' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_title' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Firstname',
    'cap_firstname' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Forename'),
    'show_firstname' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 1),
    'need_firstname' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 1),
    'Surname',
    'cap_lastname' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Surname'),
    'show_lastname' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 1),
    'need_lastname' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 1),
    'Branch',
    'cap_branch' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Branch'),
    'show_branch' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_branch' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Company',
    'cap_company' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Company'),
    'show_company' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_company' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Position',
    'cap_position' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Position'),
    'show_position' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_position' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Section',
    'cap_section' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Section'),
    'show_section' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_section' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Street',
    'cap_street' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Street'),
    'show_street' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_street' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'House number',
    'cap_housenumber' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'House number'),
    'show_housenumber' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_housenumber' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Postal code',
    'cap_postalcode' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Postal code'),
    'show_postalcode' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_postalcode' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'City',
    'cap_city' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'City'),
    'show_city' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_city' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Telephone',
    'cap_phone' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Telephone'),
    'show_phone' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_phone' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Mobile phone',
    'cap_mobile' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Mobile'),
    'show_mobile' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_mobile' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Fax',
    'cap_fax' => array('Caption', 'isNoHTML', TRUE, 'input', 200, '', 'Fax'),
    'show_fax' => array('Show', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'need_fax' => array('Needed', 'isNum', TRUE, 'yesno', NULL, '', 0),
  );
  var $editFieldsMessages = array(
    'confirmation_email' => array('Registration requested', 'isNoHTML', TRUE, 'textarea',
      4, '', 'Registration request email send, if email address is not already registered.'),
    'confirmation_email_failed' => array(
      'Registration request failed', 'isNoHTML', TRUE, 'textarea',
      4, '', 'Could not send registration request email.'
    ),
    'register_confirmed' => array('Registration confirmed', 'isNoHTML', TRUE, 'textarea',
      4, '', 'Registration confirmed'),
    'unregister_confirmed' => array('Deregistration confirmed', 'isNoHTML', TRUE, 'textarea',
      4, '', 'Deregistration confirmed'),
    'switched_to_html' => array('Switched to HTML', 'isNoHTML', TRUE, 'textarea',
      4, '', 'From now on you will receive the newsletter in HTML.'),
    'switched_to_plaintext' => array('Switched to plaintext', 'isNoHTML', TRUE,
      'textarea', 4, '',
      'From now on you will receive the newsletter as plaintext.'),
    'Error messages',
    'internal_error_subscriber' => array('Registration failed', 'isNoHTML', TRUE, 'input', 200, '',
      'Could not create or read a subscriber.'),
    'internal_error_newsletter' => array(
      'Reading list id failed', 'isNoHTML', TRUE, 'input', 200, '',
      'Could not read a newsletter list id.'
    ),
    'wrong_activate_code' => array('Wrong activation code', 'isNoHTML', TRUE, 'input',
      200, '', 'Wrong activation code'),
    'Input error handling',
    'detailed_errors' => array(
      'Report detailed input errors?', 'isNum', TRUE, 'yesno', NULL, '', 0
    ),
    'input_error' => array('Input error', 'isNoHTML', TRUE, 'input', 200, '',
      'Input error'),
    'error_privacy' => array('Privacy incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Privacy error'),
    'error_email' => array('Email incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Email error'),
    'error_salutation' => array('Salutation incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Salutation error'),
    'error_title' => array('Title incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Title error'),
    'error_firstname' => array('First name incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'First name error'),
    'error_lastname' => array('Last name incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Last name error'),
    'error_branch' => array('Branch incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Branch error'),
    'error_company' => array('Company incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Company error'),
    'error_position' => array('Position incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Salutation error'),
    'error_section' => array('Department incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Department error'),
    'error_street' => array('Street incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Street error'),
    'error_housenumber' => array('House number incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'House number error'),
    'error_postalcode' => array('Postal code incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Postal code error'),
    'error_city' => array('City incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'City error'),
    'error_phone' => array('Phone incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Phone error'),
    'error_mobile' => array('Mobile phone incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Mobile phone error'),
    'error_fax' => array('Fax incorrect', 'isNoHTML', FALSE, 'input', 200, '',
      'Fax error')
  );


  function initialize() {
    $this->initializeNewsletterObject();

    $this->sessionParamName = 'PAPAYA_SESS_'.get_class($this).'_'.$this->paramName;
    $this->sessionParams = $this->getSessionValue($this->sessionParamName);
    $this->initializeSessionParam('contentmode', 'cmd');

    switch(@$this->params['contentmode']) {
    case 3:
      $this->editFields = $this->editFieldsMessages;
      break;
    case 2:
      $this->editFields = $this->editFieldsDialog;
      break;
    case 1:
      $this->editFields = $this->editFieldsMail;
      break;
    }
    $this->setSessionValue($this->sessionParamName, $this->sessionParams);
  }

  /**
  * Get form
  *
  * @access public
  * @return string $result
  */
  function getForm($dialogTitlePrefix = '', $dialogIcon = '') {
    $result = $this->getContentToolbar();
    $result .= parent::getForm($dialogTitlePrefix, $dialogIcon);
    return $result;
  }

  /**
  * Get content toolbar
  *
  * @access public
  * @return string XML
  */
  function getContentToolbar() {
    $toolbar = new base_btnbuilder;
    $toolbar->images = $GLOBALS['PAPAYA_IMAGES'];
    $toolbar->addButton(
      'General',
      $this->getLink(array('contentmode' => 0)),
      'categories-content',
      '',
      $this->params['contentmode'] == 0
    );
    $toolbar->addButton(
      'Email',
      $this->getLink(array('contentmode' => 1)),
      'items-mail',
      '',
      $this->params['contentmode'] == 1
    );
    $toolbar->addButton(
      'Dialog',
      $this->getLink(array('contentmode' => 2)),
      'items-dialog',
      '',
      $this->params['contentmode'] == 2
    );
    $toolbar->addButton(
      'Messages',
      $this->getLink(array('contentmode' => 3)),
      'items-message',
      '',
      $this->params['contentmode'] == 3
    );
    if ($str = $toolbar->getXML()) {
      return '<toolbar>'.$str.'</toolbar>';
    }
    return '';
  }

  /**
  * A module provided callback method.
  * @access private
  * @param  $name
  * @param  $element
  * @param  $data
  */
  function callbackMailingListsCombo($name, $element, $data) {
    $result = '';
    content_newsletter::initializeNewsletterObject();
    $result .= sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
      papaya_strings::escapeHTMLchars($this->paramName),
      papaya_strings::escapeHTMLchars($name)
    );
    if (isset($this->newsletterObject->newsletterLists) &&
        is_array($this->newsletterObject->newsletterLists) &&
        count($this->newsletterObject->newsletterLists) > 0) {
      if (!$element[2]) {
        $selected = ($data == 0) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="0" %s>%s</option>'.LF,
          $selected,
          papaya_strings::escapeHTMLchars($this->_gt('None'))
        );
      }
      foreach ($this->newsletterObject->newsletterLists as $list) {
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

  /**
   * Instantiate base_newsletter to content_newsletter::newsletterObject
   * @return boolean Success
   */
  function initializeNewsletterObject() {
    if (!isset($this->newsletterObject) || !is_object($this->newsletterObject)) {
      include_once(dirname(__FILE__).'/base_newsletter.php');
      $this->newsletterObject = new base_newsletter;
      $this->newsletterObject->module = $this;
      $this->newsletterObject->loadNewsletterLists();
    }
    return is_object($this->newsletterObject);
  }

  /**
  * Get parsed teaser
  *
  * @access public
  * @return string
  */
  function getParsedTeaser($parseParams = NULL) {
    $result = sprintf(
      '<title>%s</title>'.LF,
      papaya_strings::escapeHTMLchars(@$this->data['title'])
    );
    $result .= sprintf(
      '<subtitle>%s</subtitle>'.LF,
      papaya_strings::escapeHTMLchars(@$this->data['subtitle'])
    );
    $result .= sprintf(
      '<text>%s</text>'.LF,
      $this->getXHTMLString(@$this->data['teaser'], !((bool)@$this->data['nl2br']))
    );
    return $result;
  }

  /**
  * Get parsed data
  *
  * @access public
  * @return string
  */
  function getParsedData($parseParams = NULL) {

    $this->setDefaultData();
    $this->setDefaultData(NULL, FALSE, $this->editFieldsMessages);
    $this->setDefaultData(NULL, FALSE, $this->editFieldsDialog);
    $this->setDefaultData(NULL, FALSE, $this->editFieldsMail);

    $result = sprintf(
      '<title>%s</title>'."\n",
      papaya_strings::escapeHTMLChars(@$this->data['title'])
    );
    $result .= sprintf(
      "<subtitle>%s</subtitle>",
      papaya_strings::escapeHTMLchars(@$this->data['subtitle'])
    );
    $result .= sprintf(
      '<teaser>%s</teaser>'.LF,
      $this->getXHTMLString(@$this->data['teaser'], !((bool)@$this->data['nl2br']))
    );
    $result .= sprintf(
      "<text>%s</text>",
      $this->getXHTMLString(@$this->data['text'], !((bool)@$this->data['nl2br']))
    );

    $this->initializeNewsletterObject();
    $this->newsletterObject->module = $this;

    $showForm = TRUE;
    if (isset($_GET['subscribe']) && trim($_GET['subscribe'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('subscribe', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['subscribe'], 0, $getParams['confirm']
          )) {
        $result .= '<message type="success">'
          .papaya_strings::escapeHTMLChars(@$this->data['register_confirmed'])
          .'</message>';
      } else {
        $result .= '<message type="error">'
          .papaya_strings::escapeHTMLChars(@$this->data['wrong_activate_code'])
          .'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($_GET['unsubscribe']) && trim($_GET['unsubscribe'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('unsubscribe', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['unsubscribe'], 1, $getParams['confirm']
          )) {
        $result .= '<message type="success">'
          .papaya_strings::escapeHTMLChars(@$this->data['unregister_confirmed'])
          .'</message>';
      } else {
        $result .= '<message type="error">'
          .papaya_strings::escapeHTMLChars(@$this->data['wrong_activate_code'])
          .'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($_GET['subscribetext']) && trim($_GET['subscribetext'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('subscribetext', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['subscribetext'], 2, $getParams['confirm']
          )) {
        $result .= '<message type="success">'
          .papaya_strings::escapeHTMLChars(@$this->data['switched_to_plaintext'])
          .'</message>';
      } else {
        $result .= '<message type="error">'
          .papaya_strings::escapeHTMLChars(@$this->data['wrong_activate_code'])
          .'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($_GET['subscribehtml']) && trim($_GET['subscribehtml'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('subscribehtml', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['subscribehtml'], 3, $getParams['confirm']
          )) {
        $result .= '<message type="success">'
          .papaya_strings::escapeHTMLChars(@$this->data['switched_to_html'])
          .'</message>';
      } else {
        $result .= '<message type="error">'
          .papaya_strings::escapeHTMLChars(@$this->data['wrong_activate_code'])
          .'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($this->params['cmd']) && $this->params['cmd'] == 'subscribe') {
      $this->initializeOutputForm();
      if ($this->subscribeDialog->checkDialogInput()) {
        $listId = isset($this->subscribeDialog->data['newsletter_list_id'])
          ? $this->subscribeDialog->data['newsletter_list_id'] : 0;
        if ($listId > 0 &&
            (
             $listId == $this->data['newsletter_list_id'] ||
             $listId == $this->data['newsletter_list_id2']
            )
           ) {
          $newsletterListId = $this->subscribeDialog->data['newsletter_list_id'];
        } else {
          $newsletterListId = $this->data['newsletter_list_id'];
        }
        if ($newsletterListId > 0) {
          $subscriberId = $this->newsletterObject->subscriberExists(
            $this->subscribeDialog->data['subscriber_email']
          );
          if (!$subscriberId) {
            //add new subscriber data
            $subscriberId = $this->newsletterObject->addSubscriber($this->subscribeDialog->data);
          }
          if ($subscriberId) {
            if (!in_array(
                  $this->newsletterObject->getSubscriptionStatus($subscriberId, $newsletterListId),
                  $this->newsletterObject->activeStatus
                )) {
              if (isset($this->subscribeDialog->data['subscriber_format']) &&
                  $this->subscribeDialog->data['subscriber_format'] == 'html') {
                $format = 1;
              } elseif (isset($this->subscribeDialog->data['subscriber_format']) &&
                  $this->subscribeDialog->data['subscriber_format'] == 'text') {
                $format = 0;
              } else {
                $format = @(int)$this->data['default_format'];
              }

              $sendMail = FALSE;
              if ($this->newsletterObject->subscriptionExists($subscriberId, $newsletterListId)) {
                //reactivate subscription
                $sendMail = $this->newsletterObject->saveSubscription(
                  $subscriberId, $newsletterListId, 1, $format
                );
              } else {
                //add subscription
                $sendMail = $this->newsletterObject->addSubscription(
                  $subscriberId, $newsletterListId, 1, $format
                );
              }

              if ($sendMail) {
                //save input data for later use
                $subscriberData = '';
                if (isset($this->data) && is_array($this->subscribeDialog->data)) {
                  $subscriberData = simple_xmltree::serializeArrayToXML(
                    $this->tagName, $this->subscribeDialog->data
                  );
                }

                //add protocol entry for confirmation email
                $token = $this->newsletterObject->getActionToken();
                $this->newsletterObject->addProtocolEntry(
                  $subscriberId, $newsletterListId, 0, $token, FALSE, $subscriberData);
                //send confirmation request email
                $email = new email();

                $email->setSender($this->data['mail_from'], $this->data['addresser_name']);
                // TODO: actually none of the need_* settings are honored yet
                $name = "";
                if ($this->data['show_firstname'] &&
                    isset($this->subscribeDialog->data['subscriber_firstname'])
                  ) {
                  $name .= $this->subscribeDialog->data['subscriber_firstname'];
                }
                if ($this->data['show_lastname'] &&
                    isset($this->subscribeDialog->data['subscriber_lastname'])
                  ) {
                  if (strlen($name) > 0) {
                    $name .= ' ';
                  }
                  $name .= $this->subscribeDialog->data['subscriber_lastname'];
                }
                $email->addAddress(
                  $this->subscribeDialog->data['subscriber_email'], $name
                );

                $replValues = $this->subscribeDialog->data;
                $params = array(
                  'subscribe' => $this->subscribeDialog->data['subscriber_email'],
                  'confirm' => $token
                );
                $replValues['link'] = $this->getAbsoluteURL(
                  $this->getWebLink(NULL, NULL, NULL, $params)
                );

                if (isset($this->data['mail_subject']) && trim($this->data['mail_subject']) != '') {
                  $email->setSubject($this->data['mail_subject'], $replValues);
                } else {
                  $email->setSubject($this->editFieldsMail['mail_subject'][6], $replValues);
                }
                if (isset($this->data['mail_message']) && trim($this->data['mail_message']) != '') {
                  $email->setBody($this->data['mail_message'], $replValues);
                } else {
                  $email->setBody($this->editFieldsMail['mail_message'][6], $replValues);
                }

                if ($email->send()) {
                  $result .= '<message type="success">'
                    .papaya_strings::escapeHTMLChars(@$this->data['confirmation_email'])
                    .'</message>';
                } else {
                  $result .= '<message type="error">'
                    .papaya_strings::escapeHTMLChars(@$this->data['confirmation_email_failed'])
                    .'</message>';
                }
              }
            } else {
              //gibt ne active subscription
              $result .= '<message type="success">'
                .papaya_strings::escapeHTMLChars(@$this->data['confirmation_email'])
                .'</message>';
            }
            $showForm = FALSE;
          } else {
            $result .= '<message type="error">'
              .papaya_strings::escapeHTMLChars(@$this->data['internal_error_subscriber'])
              .'</message>';
          }
        } else {
          $result .= '<message type="error">'
            .papaya_strings::escapeHTMLChars(@$this->data['internal_error_newsletter'])
            .'</message>';
        }
      } else {
        // Check whether we have/want detailed error messages
        if (isset($this->subscribeDialog->inputErrors) &&
            isset($this->data['detailed_errors']) &&
            $this->data['detailed_errors'] == TRUE) {
          // Yes, detailed error messages
          $genericErrorUsed = FALSE;
          foreach ($this->subscribeDialog->inputErrors as $field => $error) {
            if ($error == 1) {
              $errorMessageIndex = preg_replace('/^subscriber/', 'error', $field);
              if (isset($this->data[$errorMessageIndex])) {
                $result .= sprintf(
                  '<message type="error" for="%s">%s</message>',
                  papaya_strings::escapeHTMLChars($field),
                  papaya_strings::escapeHTMLChars($this->data[$errorMessageIndex])
                );
              } elseif ($genericErrorUsed == FALSE) {
                $errorMessages[] = @$this->data['error_input'];
                $result .= '<message type="error">'.
                  papaya_strings::escapeHTMLChars($this->data['error_input'] ?? '').'</message>';
                $genericErrorUsed = TRUE;
              }
            }
          }
        } else {
          // No just a generic error message for any input
          $result .= '<message type="error">'.
            papaya_strings::escapeHTMLChars(@$this->data['error_input']).'</message>';
        }
      }
    }
    if ($showForm) {
      $result .= $this->getOutputForm();
      // Add privacy text to the xml-output.
      $result .= sprintf(
        '<privacy caption="%s">%s</privacy>',
        papaya_strings::escapeHTMLChars(@$this->data['cap_privacy']),
        $this->getXHTMLString($this->applyFilterData(@$this->data['text_privacy']))
      );
    }
    return $result;
  }

  function getURLParams($paramNames) {
    $result = array();
    $request = $this->papaya()->getObject('Request');
    if (isset($paramNames) && is_array($paramNames) && count($paramNames) > 0) {
      foreach ($paramNames as $paramName) {
        $result[$paramName] = $request->getParameter($paramName);
      }
    }
    return $result;
  }

  function initializeOutputForm() {
    if (!(isset($this->subscribeDialog) && is_object($this->subscribeDialog))) {
      $data = array();
      $hidden = array(
        'cmd' => 'subscribe'
      );
      $editFields = array();
      $fields = array(
        'format' => array('isAlpha', 'radio', array('html', 'text')),
        'salutation' => array('isAlpha', 'radio', array('male', 'female')),
        'title' => array('isNoHTML', 'input', 100),
        'firstname' => array('isNoHTML', 'input', 100),
        'lastname' => array('isNoHTML', 'input', 100),
        'branch' => array('isNoHTML', 'input', 100),
        'company' => array('isNoHTML', 'input', 100),
        'position' => array('isNoHTML', 'input', 100),
        'section' => array('isNoHTML', 'input', 100),
        'street' => array('isNoHTML', 'input', 100),
        'housenumber' => array('isNoHTML', 'input', 20),
        'postalcode' => array('isNoHTML', 'input', 20),
        'city' => array('isNoHTML', 'input', 100),
        'phone' => array('isPhone', 'input', 40),
        'mobile' => array('isPhone', 'input', 40),
        'fax' => array('isPhone', 'input', 40),
        'privacy' => array('isNum', 'checkbox', '1')
      );

      if (isset($this->data['newsletter_list_id2']) && $this->data['newsletter_list_id2'] > 0) {
        $id1 = @(int)$this->data['newsletter_list_id'];
        if (isset($this->data['newsletter_list_title']) &&
            trim($this->data['newsletter_list_title']) != '') {
          $lists[$id1] = $this->data['newsletter_list_title'];
        } elseif (isset($this->newsletterObject->newsletterLists[$id1])) {
          $lists[$id1] =
            @(string)$this->newsletterObject->newsletterLists[$id1]['newsletter_list_title'];
        } else {
          $lists[$id1] = 'List 1';
        }
        $id2 = (int)$this->data['newsletter_list_id2'];
        if (isset($this->data['newsletter_list_title2']) &&
            trim($this->data['newsletter_list_title2']) != '') {
          $lists[$id2] = $this->data['newsletter_list_title2'];
        } elseif (isset($this->newsletterObject->newsletterLists[$id2])) {
          $lists[$id2] =
            @(string)$this->newsletterObject->newsletterLists[$id2]['newsletter_list_title'];
        } else {
          $lists[$id2] = 'List 2';
        }
        $editFields['newsletter_list_id'] = array(
          '', 'isNum', TRUE, 'radio', $lists, '', $id1
        );
      }

      if (isset($this->data['cap_email'])) {
        $caption = @(string)$this->data['cap_email'];
      } else {
        $caption = @(string)$this->editFieldsDialog['cap_email'][6];
      }
      $editFields['subscriber_email'] = array(
        $caption, 'isEmail', TRUE, 'input', '60'
      );

      foreach ($fields as $fieldName => $fieldParams) {
        if (isset($this->data['show_'.$fieldName])) {
          $showField = @(bool)$this->data['show_'.$fieldName];
        } else {
          $showField = @(bool)$this->editFieldsDialog['show_'.$fieldName][6];
        }
        if ($showField) {
          if (isset($this->data['cap_'.$fieldName])) {
            $caption = @(string)$this->data['cap_'.$fieldName];
          } else {
            $caption = @(string)$this->editFieldsDialog['cap_'.$fieldName][6];
          }
          if ($fieldParams[1] == 'radio' && isset($this->data['show_'.$fieldName])) {
            $needed = TRUE;
          } elseif (isset($this->data['need_'.$fieldName])) {
            $needed = @(bool)$this->data['need_'.$fieldName];
          } else {
            $needed = @(bool)$this->editFieldsDialog['need_'.$fieldName][6];
          }
          if ($fieldParams[1] == 'radio') {
            $optValues = array();
            foreach ($fieldParams[2] as $optName) {
              if (isset($this->data['cap_'.$fieldName])) {
                $optCaption = @(string)$this->data['cap_'.$fieldName.'_'.$optName];
              } else {
                $optCaption = @(string)$this->editFieldsDialog['cap_'.$fieldName.'_'.$optName][6];
              }
              $optValues[$optName] = $optCaption;
            }
            $defaultOpt = reset(array_keys($optValues));
            $editFields['subscriber_'.$fieldName] = array(
              $caption, $fieldParams[0], $needed, $fieldParams[1], $optValues, '', $defaultOpt
            );
          } else {
            $editFields['subscriber_'.$fieldName] = array(
              $caption, $fieldParams[0], $needed, $fieldParams[1], $fieldParams[2]
            );
          }
        }
      }

      $this->subscribeDialog = new base_dialog(
        $this, $this->paramName, $editFields, $data, $hidden
      );
      $this->subscribeDialog->loadParams();
      if (!$this->data['use_token']) {
        $this->subscribeDialog->useToken = FALSE;
      }
      if (isset($this->data['cap_submit'])) {
        $this->subscribeDialog->buttonTitle = @(string)$this->data['cap_submit'];
      } else {
        $this->subscribeDialog->buttonTitle = @(string)$this->editFieldsDialog['cap_submit'][6];
      }
    }
  }

  function getOutputForm() {
    $this->initializeOutputForm();
    return $this->subscribeDialog->getDialogXML();
  }

}

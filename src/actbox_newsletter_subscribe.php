<?php
/**
* Newsletter Box to subscribe to a Newsletter.
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
* @version $Id: actbox_newsletter_subscribe.php 6 2014-02-13 15:40:43Z SystemVCS $
*/

require_once(PAPAYA_INCLUDE_PATH.'system/base_actionbox.php');

/**
* Newsletter Box to subscribe to a Newsletter.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class actbox_newsletter_subscribe extends base_actionbox {

  var $cacheable = FALSE;
  var $paramName = 'nws';

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
    'target_pid' => array ('Target page id', 'isNum', FALSE, 'pageid', 200,
      'Form data will be redirected to the page specified by the given page id
      . The page has to be a newsletter subscribe/unsubscribe page. Leave it emp
      ty to stay on this box.'),
    'target_anchor' => array('Target anchor', 'isNoHTML', FALSE, 'input', 200, 'Jump to this an
    chor after submission.'),
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
      'team@example.org', ''),
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
    'confirmation_email' => array('Registration requested', 'isNoHTML', TRUE, 'input',
      200, '', 'Registration request email send, if email address is not already registered.'),
    'confirmation_email_failed' => array('Registration request failed', 'isNoHTML', TRUE, 'input',
      200, '', 'Could not send registration request email.'),
    'register_confirmed' => array('Registration confirmed', 'isNoHTML', TRUE, 'input',
      200, '', 'Registration confirmed'),
    'switched_to_html' => array('Switched to HTML', 'isNoHTML', TRUE, 'textarea',
      4, '', 'From now on you will receive the newsletter in HTML.'),
    'switched_to_plaintext' => array('Switched to plaintext', 'isNoHTML', TRUE,
      'textarea', 4, '',
      'From now on you will receive the newsletter as plaintext.'),
    'Error messages',
    'input_error' => array('Input error', 'isNoHTML', TRUE, 'input', 200, '',
      'Input error'),
    'internal_error_subscriber' => array('Registration failed', 'isNoHTML', TRUE, 'input', 200, '',
      'Could not create or read a subscriber.'),
    'internal_error_newsletter' => array(
      'Reading list id failed', 'isNoHTML', TRUE, 'input', 200, '',
      'Could not read a newsletter list id.'
    ),
    'wrong_activate_code' => array('Wrong activation code', 'isNoHTML', TRUE, 'input',
      200, '', 'Wrong activation code')
  );

  function onLoad() {
    $this->sessionParamName = 'PAPAYA_SESS_'.get_class($this).'_'.$this->paramName;
    $this->sessionParams = $this->getSessionValue($this->sessionParamName);
    $this->initializeSessionParam('contentmode', 'cmd');
    $contentMode = empty($this->params['contentmode']) ? 0 : (int)$this->params['contentmode'];

    switch($contentMode) {
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
   * @param string $dialogTitlePrefix
   * @param string $dialogIcon
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
    $contentMode = empty($this->params['contentmode']) ? 0 : (int)$this->params['contentmode'];
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $GLOBALS['PAPAYA_IMAGES'];
    $toolbar->addButton(
      'General',
      $this->getLink(array('contentmode' => 0)),
      'categories-content',
      '',
      $contentMode == 0
    );
    $toolbar->addButton(
      'Email',
      $this->getLink(array('contentmode' => 1)),
      'items-mail',
      '',
      $contentMode == 1
    );
    $toolbar->addButton(
      'Dialog',
      $this->getLink(array('contentmode' => 2)),
      'items-dialog',
      '',
      $contentMode == 2
    );
    $toolbar->addButton(
      'Messages',
      $this->getLink(array('contentmode' => 3)),
      'items-message',
      '',
      $contentMode == 3
    );
    if ($str = $toolbar->getXML()) {
      return '<toolbar>'.$str.'</toolbar>';
    }
    return '';
  }

  /**
  * A module provided callback method.
  *
  * @access private
  * @param  $name
  * @param  $element
  * @param  $data
  */
  function callbackMailingListsCombo($name, $element, $data) {
    $result = '';
    $this->initializeNewsletterObject();
    $result .= sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars($name)
    );
    if (isset($this->newsletterObject->newsletterLists) &&
        is_array($this->newsletterObject->newsletterLists) &&
        count($this->newsletterObject->newsletterLists) > 0) {
      if (!$element[2]) {
        $selected = ($data == 0) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%d" %s>%s</option>'.LF,
          0,
          $selected,
          papaya_strings::escapeHTMLChars($this->_gt('None'))
        );
      }
      foreach ($this->newsletterObject->newsletterLists as $list) {
        $selected = ($data == $list['newsletter_list_id']) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%d" %s>%s</option>'.LF,
          (int)$list['newsletter_list_id'],
          $selected,
          papaya_strings::escapeHTMLChars($list['newsletter_list_name'])
        );
      }
    } else {
      $result .= sprintf(
        '<option value="" disabled="disabled">%s</option>'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('No lists available'))
      );
    }
    $result .= '</select>'.LF;
    return $result;
  }

  function initializeNewsletterObject() {
    if (!isset($this->newsletterObject) || !is_object($this->newsletterObject)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_pluginloader.php');
      // instance of connector_newsletter
      $newsletterObj = base_pluginloader::getPluginInstance(
        'bfde211a18056caca770c17f8eb4ceea', $this
      );
      $this->newsletterObject = $newsletterObj->baseNewsletter;
      $this->newsletterObject->module = $this;
      $this->newsletterObject->loadNewsletterLists();
    }
  }

  /**
  * Get parsed teaser
  *
  * @access public
  * @return string
  */
  function getParsedTeaser($nodes = NULL) {
    $result = '';
    if ($nodes === NULL) {
      $nodes = array(
        'title'     => 'title',
        'subtitle'  => 'subtitle',
        'text'      => 'teaser',
      );
    }
    foreach ($nodes as $node => $content) {
      if (!empty($this->data[$content])) {
        $result .= sprintf(
          '<%1$s>%2$s</%1$s>'.LF,
          papaya_strings::escapeHTMLChars($node),
          $this->getXHTMLString($this->data[$content], !((bool)@$this->data['nl2br']))
        );
      }
    }

    return $result;
  }

  /**
  * Get parsed data
  *
  * @access public
  * @return string
  */
  function getParsedData() {

    $this->setDefaultData();
    $this->setDefaultData(NULL, FALSE, $this->editFieldsMessages);
    $this->setDefaultData(NULL, FALSE, $this->editFieldsDialog);
    $this->setDefaultData(NULL, FALSE, $this->editFieldsMail);

    // Set Target-PID for form, if it's set in Backend
    if (isset($this->data['target_pid']) && $this->data['target_pid'] > 0) {
      include_once(dirname(__FILE__).'/content_newsletter_subscribe.php');
      //$page = new content_newsletter_subscribe();
      //$this->paramName = $page->paramName;
      //$this->paramName = content_newsletter::paramName;
      //unset($page);
    }

    $result = '<newsletter>'.LF;

    $result .= $this->getParsedTeaser(
      array(
        'title' => 'title',
        'subtitle' => 'subtitle',
        'teaser' => 'teaser',
        'text' => 'text'
      )
    );

    include_once(PAPAYA_INCLUDE_PATH.'system/base_pluginloader.php');
    // instance of connector_newsletter
    $newsletterObj = base_pluginloader::getPluginInstance(
      'bfde211a18056caca770c17f8eb4ceea', $this
    );
    $this->newsletterObject = $newsletterObj->baseNewsletter;
    $this->newsletterObject->module = $this;

    $showForm = TRUE;
    if (isset($_GET['subscribe']) && trim($_GET['subscribe'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('subscribe', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['subscribe'], 0, $getParams['confirm']
          )) {
        $result .= '<message type="success">'.papaya_strings::escapeHTMLChars(
          @$this->data['register_confirmed']).'</message>';
      } else {
        $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
          @$this->data['wrong_activate_code']).'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($_GET['subscribetext']) && trim($_GET['subscribetext'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('subscribetext', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['subscribetext'], 2, $getParams['confirm']
          )) {
        $result .= '<message type="success">'.papaya_strings::escapeHTMLChars(
          @$this->data['switched_to_plaintext']).'</message>';
      } else {
        $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
          @$this->data['wrong_activate_code']).'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($_GET['subscribehtml']) && trim($_GET['subscribehtml'] != '') &&
        isset($_GET['confirm']) && trim($_GET['confirm'] != '')) {
      $getParams = $this->getURLParams(array('subscribehtml', 'confirm'));
      if ($this->newsletterObject->confirmProtocolEntry(
            $getParams['subscribehtml'], 3, $getParams['confirm']
          )) {
        $result .= '<message type="success">'.papaya_strings::escapeHTMLChars(
          @$this->data['switched_to_html']).'</message>';
      } else {
        $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
          @$this->data['wrong_activate_code']).'</message>';
      }
      $showForm = FALSE;
    } elseif (isset($this->params['cmd']) && $this->params['cmd'] == 'subscribe') {
      $this->initializeOutputForm();
      $this->subscribeDialog->useToken = FALSE;
      if ($this->subscribeDialog->checkDialogInput()) {
        $listId = isset($this->subscribeDialog->data['newsletter_list_id'])
          ? $this->subscribeDialog->data['newsletter_list_id'] : 0;
        if ($listId > 0 &&
            (
             $listId == $this->data['newsletter_list_id'] ||
             $listId == $this->data['newsletter_list_id2']
            )) {
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
                $name = '';
                if (! empty($this->subscribeDialog->data['subscriber_firstname'])) {
                  $name .= $this->subscribeDialog->data['subscriber_firstname'];
                }
                if (! empty($this->subscribeDialog->data['subscriber_lastname'])) {
                  if (!empty($name)) {
                    $name .= ' ' . $this->subscribeDialog->data['subscriber_lastname'];
                  } else {
                    $name = $this->subscribeDialog->data['subscriber_lastname'];
                  }
                }
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
                include_once(PAPAYA_INCLUDE_PATH.'system/sys_email.php');
                $email = new email();

                $email->setSender($this->data['mail_from'], $this->data['addresser_name']);
                $email->addAddress($this->subscribeDialog->data['subscriber_email'], $name);

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
                  $result .= '<message type="success">'.papaya_strings::escapeHTMLChars(
                    @$this->data['confirmation_email']).'</message>';
                } else {
                  $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
                    @$this->data['confirmation_email_failed']).'</message>';
                }
              }
            } else {
              //gibt ne active subscription
              $result .= '<message type="success">'.papaya_strings::escapeHTMLChars(
                @$this->data['confirmation_email']).'</message>';
            }
            $showForm = FALSE;
          } else {
            $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
              @$this->data['internal_error_subscriber']).'</message>';
          }
        } else {
          $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
            @$this->data['internal_error_newsletter']).'</message>';
        }
      } else {
        $result .= '<message type="error">'.papaya_strings::escapeHTMLChars(
          @$this->data['input_error']).'</message>';
      }
    }
    if ($showForm) {
      $result .= $this->getOutputForm();
      // Add privacy text to the xml-output.
      $result .= sprintf(
        '<privacy caption="%s">%s</privacy>',
        papaya_strings::escapeHTMLChars(@$this->data['cap_privacy']),
        $this->getXHTMLString(@$this->data['text_privacy'])
      );
    }
    if (!empty($this->data['target_anchor'])) {
      $result .= sprintf(
        '<anchor name="%s" />'.LF,
        papaya_strings::escapeHTMLChars($this->data['target_anchor'])
      );
    }
    $result .= '</newsletter>';
    return $result;
  }

  function getURLParams($paramNames) {
    $result = array();
    $request = $this->getApplication()->getObject('Request');
    if (isset($paramNames) && is_array($paramNames) && count($paramNames) > 0) {
      foreach ($paramNames as $paramName) {
        $result[$paramName] = $request->getParameter($paramName);
      }
    }
    return $result;
  }

  function initializeOutputForm() {
    if (!(isset($this->subscribeDialog) && is_object($this->subscribeDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
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
          '', 'isNum', FALSE, 'radio', $lists, '', $id1
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
          if ($fieldParams[1] == 'radio') {
            $needed = FALSE;
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
      // Set Target-PID for form, if it's set in Backend
      if (isset($this->data['target_pid']) && $this->data['target_pid'] > 0) {
        $this->subscribeDialog->baseLink = $this->getBaseLink($this->data['target_pid']);
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


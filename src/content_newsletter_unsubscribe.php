<?php
/**
* Page module newsletter
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
* @version $Id: content_newsletter_unsubscribe.php 8 2014-02-17 16:54:45Z SystemVCS $
*/

/**
* Basic class page module
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_content.php');

/**
* Page module LinkDB
*
* @package commercial
* @subpackage newsletter
*/
class content_newsletter_unsubscribe extends base_content {

  var $paramName = 'nws';

  /**
  * Content edit fields
  * @var array $editFields
  */
  var $editFields = array(
    'nl2br' => array('Automatic linebreaks', 'isNum', TRUE, 'combo',
      array(0 => 'Yes', 1 => 'No'), 'Apply linebreaks from input to the HTML output.'),
    'title' => array('Title', 'isNoHTML', TRUE, 'input', 200, ''),
    'subtitle' => array('Subtitle', 'isSomeText', FALSE, 'input', 400),
    'teaser' => array('Teaser', 'isSomeText', FALSE, 'simplerichtext', 10),
    'unsubscribe_text' => array('Text email request', 'isSomeText', FALSE, 'richtext', 10),
    'newsletter_list_text' => array('Text newsletter list', 'isSomeText', FALSE, 'richtext',
      10),
    'Options',
    'mail_from' => array('Sender mail', 'isEmail', TRUE, 'input', 200, '',
      'team@webpage.tld'),
    'addresser_name' => array('Sender name', 'isNoHTML', TRUE, 'input', 200, '',
      'Webpage Team'),
    'Unsubscription email',
    'mail_subject' => array('Mail subject', 'isNoHTML', TRUE, 'input', 200, '',
      'Newsletter unsubscription'),
    'mail_message' => array(
      'Mail message', 'isSomeText', TRUE, 'textarea', 10,
      'Must contain {%UNSUBSCRIBE_LINK%}',
      'Please choose the subscriptions you want to deregister.'
    ),
    'Captions',
    'cap_email' => array('E-Mail', 'isSomeText', FALSE, 'input', 400,
      '', 'Please enter your email address below:'),
    'cap_submit' => array('Submit', 'isNoHTML', FALSE, 'input', 400, '', 'Confirm'),
    'Messages',
    'unregister_email_sent' => array('Email for deregistration sent', 'isNoHTML', TRUE,
      'input', 200, '', 'Email for deregistration sent'),
    'unregister_confirmed' => array('Deregistration succeeded', 'isNoHTML', TRUE,
      'input', 200, '', 'Deregistration has been successful.'),
    'unregister_question' => array('Deregistration question', 'isNoHTML', TRUE,
      'input', 200, 'Question if user wants to unsubscribe.', ''),
    'Error messages',
    'input_error' => array('Input error', 'isNoHTML', TRUE, 'input', 200, '',
      'Input error'),
    'unregister_email_failed' => array('Email not sent', 'isNoHTML', TRUE, 'input',
      200, '', 'Could not send deregistration email.'),
    'email_exists' => array('Email not found', 'isNoHTML', TRUE, 'input', 200, '',
      'Email not found'),
    'no_newsletters' => array('No subscribed newsletters', 'isNoHTML', TRUE, 'input',
      200, '', 'You have not subscribed any newsletter currently.')
  );

  /**
  * Get parsed teaser
  *
  * @access public
  * @return string
  */
  function getParsedTeaser() {
    $result = sprintf(
      '<title>%s</title>'.LF,
      papaya_strings::escapeHTMLChars(@$this->data['title'])
    );
    $result .= sprintf(
      '<subtitle>%s</subtitle>'.LF,
      papaya_strings::escapeHTMLChars(@$this->data['subtitle'])
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
  function getParsedData() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
    include_once(dirname(__FILE__).'/base_newsletter.php');
    $newsletterObject = new base_newsletter();
    $newsletterObject->module = $this;
    $result = sprintf(
      '<title>%s</title>'.LF,
      papaya_strings::escapeHTMLChars(@$this->data['title'])
    );
    $result .= sprintf(
      "<subtitle>%s</subtitle>",
      papaya_strings::escapeHTMLChars(@$this->data['subtitle'])
    );
    $result .= sprintf(
      '<teaser>%s</teaser>'.LF,
      $this->getXHTMLString(@$this->data['teaser'], !((bool)@$this->data['nl2br']))
    );
    //send unsubscribe link
    if (isset($this->params['cmd']) &&
        $this->params['cmd'] == 'unsubscribe' &&
        isset($this->params['email'])) {
      if (trim($this->params['email']) != '' &&
          $newsletterObject->loadSubscriber(
            $newsletterObject->subscriberExists($this->params['email'])
          )
         ) {
        $token = $newsletterObject->getActionToken();
        $time = time();
        $replValues = $newsletterObject->subscriber;
        $params = array(
                  'token' => $token,
                  'time' => $time,
                  );
        $replValues['unsubscribe_link'] = $this->getAbsoluteURL(
          $this->getWebLink(NULL, NULL, NULL, $params)
        );
        $replValues['LINK'] = $this->getAbsoluteURL($this->getWebLink(NULL, NULL, NULL, $params));
        include_once(PAPAYA_INCLUDE_PATH.'system/sys_email.php');
        $email = new email();
        $email->setSender($this->data['mail_from'], $this->data['addresser_name']);
        $email->addAddress(
          $newsletterObject->subscriber['subscriber_email'],
          $newsletterObject->subscriber['subscriber_firstname'].' '.
          $newsletterObject->subscriber['subscriber_lastname']
        );
        $email->setSubject($this->data['mail_subject'], $replValues);
        $email->setBody($this->data['mail_message'], $replValues);
        if ($email->send()) {
          $newsletterObject->setSubscriberToken(
            $newsletterObject->subscriber['subscriber_id'], $token, $time
          );
          $result .= '<message type="success">'.
            papaya_strings::escapeHTMLChars(@$this->data['unregister_email_sent']).'</message>';
        } else {
          $result .= '<message type="error">'.
            papaya_strings::escapeHTMLChars(@$this->data['unregister_email_failed']).'</message>';
        }
      } else {
        $result .= '<message type="error">'.
          papaya_strings::escapeHTMLChars(@$this->data['email_exists']).'</message>';
      }
      //do unsubscription
    } elseif (isset($this->params['cmd']) && $this->params['cmd'] == 'confirm') {
      $error = FALSE;
      foreach ($this->params as $key => $param) {
        if ($key != 'cmd' && $key != 'subscriber_id') {
          if ($newsletterObject->saveSubscription($this->params['subscriber_id'], $param, 4)) {
            $newsletterObject->addProtocolEntry(
              $this->params['subscriber_id'], $param, 1, NULL, time()
            );
          } else {
            $error = TRUE;
          }
        }
      }
      if (!$error) {
        $result .= '<message type="success">'.
          papaya_strings::escapeHTMLChars(@$this->data['unregister_confirmed']).'</message>';
      } else {
        $result .= '<message type="error">'.
          papaya_strings::escapeHTMLChars(@$this->data['no_newsletters']).'</message>';
      }
      //get unsubscription confirmation page
    } elseif (isset($_GET['token']) &&
              trim($_GET['token'] != '') &&
              isset($_GET['time']) &&
              trim($_GET['time'] != '')) {
      $result .= sprintf(
        "<text>%s</text>",
        $this->getXHTMLString(@$this->data['newsletter_list_text'], !((bool)@$this->data['nl2br']))
      );
      $newsletterObject->loadSubscriberByTokenAndTime($_GET['token'], $_GET['time']);
      $newsletterObject->loadSubscriptionDetails($newsletterObject->subscriber['subscriber_id']);
      $result .= sprintf(
        '<dialog action="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->baseLink)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[cmd]" value="confirm"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[subscriber_id]" value="%d"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName),
        (int)$newsletterObject->subscriber['subscriber_id']
      );
      $result .= '<lines>'.LF;
      if (is_array($newsletterObject->subscriptionDetail)) {
        foreach ($newsletterObject->subscriptionDetail as $newsletter) {
          $result .= sprintf(
            '<line caption="%s" fid="newsletter_list_id">'.LF,
            papaya_strings::escapeHTMLChars($newsletter['newsletter_list_name'])
          );
          $name = sprintf(
            '%s[newsletter_list_id[%d]]', $this->paramName, $newsletter['newsletter_list_id']
          );
          $result .= sprintf(
            '<input type="checkbox" name="%s" value="%d">%s</input>'.LF,
            papaya_strings::escapeHTMLChars($name),
            (int)$newsletter['newsletter_list_id'],
            papaya_strings::escapeHTMLChars($newsletter['newsletter_list_name'])
          );
          $result .= '</line>'.LF;
        }
      }
      $result .= '</lines>'.LF;
      $result .= sprintf('<dlgbutton value="%s"/>'.LF, $this->data['cap_submit']);
      $result .= '</dialog>';
      // confirm plain unsubscription without double-opt-out
    } elseif (isset($_GET['cmd']) && $_GET['cmd'] == 'plain_unsubscription' &&
              !(isset($this->params['confirm']) && $this->params['confirm'] == 1)) {
      $result .= '<message type="question">'.papaya_strings::escapeHTMLChars(
        @$this->data['unregister_question']).'</message>';
      $result .= sprintf(
        '<dialog action="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->baseLink)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[cmd]" value="plain_unsubscription"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[confirm]" value="1"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[subscriber_id]" value="%s"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName),
        (int)$_GET['subscriber_id']
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[newsletter_id]" value="%s"/>'.LF,
        papaya_strings::escapeHTMLChars($this->paramName),
        (int)$_GET['newsletter_id']
      );
      $result .= sprintf(
        '<dlgbutton value="%s"/>'.LF,
        papaya_strings::escapeHTMLChars($this->data['cap_submit'])
      );
      $result .= '</dialog>';
      // do plain unsubscription without double-opt-out
    } elseif (isset($this->params['cmd']) && $this->params['cmd'] == 'plain_unsubscription' &&
              isset($this->params['confirm']) && $this->params['confirm'] == 1) {
      if ($newsletterObject->saveSubscription(
            $this->params['subscriber_id'], $this->params['newsletter_id'], 4
          )) {
        $newsletterObject->addProtocolEntry(
          $this->params['subscriber_id'], $this->params['newsletter_id'], 1, NULL, time()
        );
        $result .= '<message type="success">'.
          papaya_strings::escapeHTMLChars(@$this->data['unregister_confirmed']).'</message>';
      } else {
        $result .= '<message type="error">'.
          papaya_strings::escapeHTMLChars(@$this->data['no_newsletters']).'</message>';
      }
      //unsubscription page
    } else {
      $result .= sprintf(
        "<text>%s</text>",
        $this->getXHTMLString(@$this->data['unsubscribe_text'], !((bool)@$this->data['nl2br']))
      );
      $data = array();
      $hidden = array(
        'cmd' => 'unsubscribe'
      );
      $fields = array('email' => array($this->data['cap_email'], 'isEmail', TRUE, 'input', 100));

      $dialog = new base_dialog($this, $this->paramName, $fields, $data, $hidden);
      $dialog->loadParams();
      $dialog->buttonTitle = $this->data['cap_submit'];
      $result .= $dialog->getDialogXML();

    }
    return $result;
  }

}


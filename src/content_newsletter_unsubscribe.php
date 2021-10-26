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
* @version $Id: content_newsletter_unsubscribe.php 8 2014-02-17 16:54:45Z SystemVCS $
*/

/**
* Page module LinkDB
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class content_newsletter_unsubscribe extends base_content {

  var $paramName = 'nws';

  var $cacheable = false;

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
      200, '', 'You have not subscribed any newsletter currently.'),
    'no_newsletters_selected' => array('No newsletters selected', 'isNoHTML', TRUE, 'input',
      200, '', 'You need to select the newsletter you would like to unsubscribe.'),
    'unregister_failed' => array('Deregistration failed', 'isNoHTML', TRUE,
      'input', 200, '', 'Deregistration failed. Please contact us.'),
  );

  /**
  * Get parsed teaser
  *
  * @access public
  * @return string
  */
  function getParsedTeaser($parseParams = NULL) {
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
  function getParsedData($parseParams = NULL) {
    $this->setDefaultData();
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
      if (
        trim($this->params['email']) != '' &&
        $newsletterObject->loadSubscriber(
          $newsletterObject->subscriberExists($this->params['email'])
        )
      ) {
        $newsletterObject->loadSubscriptionDetails($newsletterObject->subscriber['subscriber_id']);
        if (
          isset($newsletterObject->subscriptionDetail) &&
          is_array($newsletterObject->subscriptionDetail)&&
          count($newsletterObject->subscriptionDetail) > 0
        ) {
          $token = $newsletterObject->getActionToken();
          $time = time();
          $replValues = $newsletterObject->subscriber;
          $params = [
            'token' => $token,
            'time' => $time,
          ];
          $replValues['unsubscribe_link'] = $this->getAbsoluteURL(
            $this->getWebLink(NULL, NULL, NULL, $params)
          );
          $replValues['LINK'] = $this->getAbsoluteURL($this->getWebLink(NULL, NULL, NULL, $params));

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
            papaya_strings::escapeHTMLChars(@$this->data['no_newsletters']).'</message>';
        }
      } else {
        $result .= '<message type="error">'.
          papaya_strings::escapeHTMLChars(@$this->data['email_exists']).'</message>';
      }
      //do unsubscription
    } elseif (
      $tokenParameters = $this->getTokenParameters()
    ) {
      $result .= sprintf(
        "<text>%s</text>",
        $this->getXHTMLString(@$this->data['newsletter_list_text'], !((bool)@$this->data['nl2br']))
      );
      $newsletterObject->loadSubscriberByTokenAndTime($tokenParameters['token'], $tokenParameters['time']);
      $newsletterObject->loadSubscriptionDetails($newsletterObject->subscriber['subscriber_id']);
      if (
        $newsletterObject->subscriber &&
        is_array($newsletterObject->subscriptionDetail) &&
        count($newsletterObject->subscriptionDetail) > 0
      ) {
        if (
          isset($this->params['cmd']) &&
          $this->params['cmd'] == 'confirm' &&
          isset($this->params['newsletter_list_id'])  &&
          is_array($this->params['newsletter_list_id'])  &&
          count($this->params['newsletter_list_id']) > 0
        ) {
          $error = FALSE;
          $newsletterObject->loadSubscriberByTokenAndTime($tokenParameters['token'], $tokenParameters['time']);
          $newsletterObject->loadSubscriptionDetails($newsletterObject->subscriber['subscriber_id']);
          if (
            isset($newsletterObject->subscriptionDetail) &&
            is_array($newsletterObject->subscriptionDetail)&&
            count($newsletterObject->subscriptionDetail) > 0
          ) {
            $count = 0;
            foreach ($newsletterObject->subscriptionDetail as $subscription) {
              if (
                isset($this->params['newsletter_list_id'][$subscription['newsletter_list_id']])
              ) {
                if ($newsletterObject->saveSubscription($this->params['subscriber_id'], $subscription['newsletter_list_id'], 4)) {
                  $count++;
                  $newsletterObject->addProtocolEntry(
                    $this->params['subscriber_id'], $subscription['newsletter_id'], 1, NULL, time()
                  );
                }
              }
            }
          } else {
            $result .= '<message type="error">'.
              papaya_strings::escapeHTMLChars(@$this->data['no_newsletters']).'</message>';
          }
          if ($count > 0) {
            $result .= '<message type="success">'.
              papaya_strings::escapeHTMLChars(@$this->data['unregister_confirmed']).'</message>';
          } else {
            $result .= '<message type="success">'.
              papaya_strings::escapeHTMLChars(@$this->data['unregister_failed']).'</message>';
          }
          //get unsubscription confirmation page
        } else {
          if ($this->params['cmd'] == 'confirm') {
            $result .= '<message type="error">'.
              papaya_strings::escapeHTMLChars(@$this->data['no_newsletters_selected']).'</message>';
          }
          $result .= sprintf(
            '<dialog action="%s" method="post">'.LF,
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
          $result .= sprintf(
            '<input type="hidden" name="%s[token]" value="%s"/>'.LF,
            papaya_strings::escapeHTMLChars($this->paramName),
            papaya_strings::escapeHTMLChars($tokenParameters['token'])
          );
          $result .= sprintf(
            '<input type="hidden" name="%s[time]" value="%d"/>'.LF,
            papaya_strings::escapeHTMLChars($this->paramName),
            papaya_strings::escapeHTMLChars($tokenParameters['time'])
          );
          $result .= '<lines>'.LF;
          if (is_array($newsletterObject->subscriptionDetail)) {
            foreach ($newsletterObject->subscriptionDetail as $newsletter) {
              $result .= sprintf(
                '<line caption="%s" fid="newsletter_list_id">'.LF,
                papaya_strings::escapeHTMLChars($newsletter['newsletter_list_name'])
              );
              $name = sprintf(
                '%s[newsletter_list_id][%d]', $this->paramName, $newsletter['newsletter_list_id']
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
        }
      } else {
        $result .= '<message type="question">'.papaya_strings::escapeHTMLChars(
          @$this->data['no_newsletters']).'</message>';
      }
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

  private function getTokenParameters() {
    if (isset($this->params['token']) && isset($this->params['time'])) {
      return ['token' => $this->params['token'], 'time' => $this->params['time']];
    }
    if (
      isset($_GET['token']) &&
      trim($_GET['token'] != '') &&
      isset($_GET['time']) &&
      trim($_GET['time'] != '')
    ) {
      return ['token' => $_GET['token'], 'time' => $_GET['time']];
    }
    return NULL;
  }
}

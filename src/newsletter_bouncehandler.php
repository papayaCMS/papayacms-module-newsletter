<?php
/**
* Bounce handler for the newsletter module.
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
* @version $Id: newsletter_bouncehandler.php 2 2013-12-09 15:38:42Z weinert $
*/

require_once(dirname(__FILE__).'/newsletter_bounce_db.php');
require_once(dirname(__FILE__).'/bayesian.php');

/**
* Bounce handler for the newsletter module.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class newsletter_bouncehandler extends base_object {

  /**
  * BounceHandler database module
  *
  * @var newsletter_bounce_db
  */
  var $bdb;

  /**
  * the bayesian filter module
  *
  * @var bayesian
  */
  var $bayesian;
  private $_maxBounces = NULL;
  var $statusIds = array('active' => 1, 'inactive' => 0);

  /**
  * Category IDs
  *
  * @var array
  */
  var $categoryIds = NULL;
  var $paramName = 'nwl';

  function __construct() {
    $this->bayesian = bayesian::getInstance();
    $this->bdb = new newsletter_bounce_db();
    $this->bayesian->initializeFilter();
    $this->categoryIds = $this->bdb->getCategories();
  }

  function getInstance() {
    static $newsletterBouncehandler;
    if (!(isset($newsletterBouncehandler) && is_object($newsletterBouncehandler))) {
      $newsletterBouncehandler = new newsletter_bouncehandler();
    }
    return $newsletterBouncehandler;
  }

  function getBounceMaximum() {
    if (is_null($this->_maxBounces)) {
      $moduleOptions = new papaya_module_options();
      $optionValues = $moduleOptions->getOptions($this->module->guid);
      $this->_maxBounces = empty($optionValues['NEWSLETTER_BOUNCE_COUNTER'])
        ? 5 : $optionValues['NEWSLETTER_BOUNCE_COUNTER'];
    }
    return $this->_maxBounces;
  }

  /**
  * extracts bounces email adress from content and return it
  *
  * @param string $content
  * @return string
  * @access private
  */
  function _getSubscriberAddress($content) {
    $pattern = '
      (^
        (?:Original|Final)-Recipient:\s*
        (RFC\d+;\s+)?
        (?P<email>[^\n\s]+)
        (\s*)$
      )uxm';
    $results = array();
    preg_match($pattern, $content, $results);
    if (count($results) > 0) {
      return papaya_strings::strtolower($results['email']);
    } else {
      return '';
    }
  }

  /**
  * extracts senders email adress from content and return it
  *
  * @param string $content
  * @return string
  * @access private
  */
  function _getSenderAddress($content) {
    $pattern = "{^From:\s[.\s<]|<?([-!\#$%&\.?'*+\\./0-9=?A-Z^_`a-z{|}~]+
      @[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+
      \.[-!\#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+)>?([^\r\n]|[^\n])$}umx";
    preg_match($pattern, $content, $results);
    return papaya_strings::strtolower($results[1]);
  }

  /**
  * returns new state
  *
  * @param array $subscribers
  * @param  array $content
  * @return string
  */
  function _calculateNewSubscriberStatus(&$subscribers, &$contents) {
    for ($i = 0; $i < count($contents); $i++) {
      if (is_array($subscribers) && array_key_exists($contents[$i]['email'], $subscribers)) {
        if ($contents[$i]['category'] == 'bounce') {
          // bounce
          if (empty($subscribers[$contents[$i]['email']]['counter'])) {
            $subscribers[$contents[$i]['email']]['counter'] = 0;
          }
          $subscribers[$contents[$i]['email']]['counter']++;
          if ($subscribers[$contents[$i]['email']]['counter'] > $this->getBounceMaximum()) {
            $subscribers[$contents[$i]['email']]['status'] = $this->statusIds['deactive'];
          }
        } else {
          // no bounce
          // reset bounce counter when a regular email is received.
          $subscribers[$contents[$i]['email']]['status'] = $this->statusIds['active'];
          $subscribers[$contents[$i]['email']]['counter'] = 0;
        }
      }
    } // end for
  }

  function processNewMails() {
    $addresses = array();
    $results = array();
    $mails = $this->bdb->getMailsByCategory(1);
    if (count($mails) > 0) {
      foreach ($mails as $mail) {
        $result = $this->bayesian->analyzeContent($mail['content']);
        if ($result == 'bounce') {
          $emailaddress = $this->_getSubscriberAddress($mail['content']);
        } else {
          $emailaddress = $this->_getSenderAddress($mail['content']);
        }
        $addresses[] = $emailaddress;
        $results[] = array(
          'mail_id'   => $mail['mail_id'],
          'email'     => $emailaddress,
          'category'  => $result
        );
      }
      if (!empty($results)) {
        $addresses = array_unique($addresses);
        $subscribers = $this->bdb->getSubscribersByMailAddress($addresses);
        $this->_calculateNewSubscriberStatus($subscribers, $results);
        $this->bdb->updateSubscribers($subscribers);
        $this->bdb->updateMailStatus($results);
      }
      return $results;
    }
    return FALSE;
  }

  function teachFilter() {
    return $this->bayesian->processTraining();
  }

  function getBounceMailsOverview() {
    $data = array();
    $hidden = array(
      'cmd'                => 'edit_subscriber',
      'save'               => 1,
      'offset'             => (int)@$this->params['offset'],
      'newsletter_list_id' => (int)$this->params['newsletter_list_id'],
      'subscriber_id'      => @$this->subscriber['subscriber_id'],
    );

    $fields = array(
      'subscriber_email'       =>
        array('Email', 'isEmail', TRUE, 'input', 200),
      'Contact data',
      'subscriber_salutation'  =>
        array('Salutation', 'isNum', TRUE, 'combo', $this->salutations),
    );
    $dialog = new base_dialog($this, $this->paramName, $fields, $data, $hidden);
    $dialog->dialogTitle = "Bounce handler";
    return $dialog;
  }

  /**
  * Get XML for mailinglists
  *
  * @access private
  */
  function getXMLMailsList($cat, $selected = NULL, $params = NULL, $images = NULL) {
    $result = '';
    $mails = $this->bdb->getMailsMetadataByCategory(
      $cat,
      $this->params['limit_bounce_mails'],
      $this->params['offset_bounce_mails']
    );
    if (!empty($mails)) {
      $result = sprintf(
        '<listview title="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Emails'))
      );
      if (isset($mails) && count($mails) > 0) {
        $result .= $this->getPagingBarXML($cat);
      }
      $result .= '<items>'.LF;
      foreach ($mails as $mail) {
        $image = $images['items-mail'];
        $result .= sprintf(
          '<listitem href="%s" title="%s" subtitle="%s: %s, %s: %s" image="%s" %s>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mail_id' => $mail['mail_id'],
                'cmd'     => 'bounces',
                'bcmd'    => 'mailcontent',
                'mode'    => 900,
                'cat_id'  => $cat
              )
            )
          ),
          papaya_strings::escapeHTMLChars(papaya_strings::truncate($mail['subject'], 80)),
          papaya_strings::escapeHTMLChars($this->_gt('Sender')),
          papaya_strings::escapeHTMLChars($mail['sender']),
          papaya_strings::escapeHTMLChars($this->_gt('Date')),
          papaya_strings::escapeHTMLChars($mail['date']),
          papaya_strings::escapeHTMLChars($image),
          isset($selected) && $selected == $mail['mail_id']?'selected="selected"':''
        );
        $result .= '</listitem>'.LF;
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
    } else {
      $this->addMsg(
        MSG_INFO, $this->_gt('No messages')
      );
    }
    return $result;
  }

  /**
  * Creates the xml string with the paging bar for the mail list.
  *
  * @return string xml
  */
  function getPagingBarXML($cat) {
    $maxCount = $this->bdb->getMailCountByCategory($cat);
    return papaya_paging_buttons::getPagingButtons(
      $this,
      array(
        'cmd'    => 'bounces',
        'mode'   => 900,
        'cat_id' => $cat
      ),
      $this->params['offset_bounce_mails'],
      $this->params['limit_bounce_mails'],
      $maxCount,
      9,
      'offset_bounce_mails'
    );
  }

  function getXMLMailCategoriesList($selected=NULL, $images=NULL) {
    $categories = $this->bdb->getCategories();
    $result = sprintf('<listview title="%s">'.LF, $this->_gt('Categories'));
    $result .= '<items>'.LF;
    foreach ($categories as $category) {
      if (isset($selected) && $selected == $category['id']) {
        $image = $images['status-folder-open'];
      } else {
        $image = $images['items-folder'];
      }
      $result .= sprintf(
        '<listitem href="%s" title="%s" image="%s" %s>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(
            array(
              'cat_id'  => $category['id'],
              'cmd'     => 'bounces',
              'mode'    => 900
            )
          )
        ),
        papaya_strings::escapeHTMLChars($this->_gt($category['name'])),
        papaya_strings::escapeHTMLChars($image),
        isset($selected) && $selected == $category['id'] ? 'selected="selected"' : ''
      );
      $result .= '</listitem>'.LF;
    }
    $result .= '</items>'.LF;
    $result .= '</listview>'.LF;
    return $result;
  }

  function getXMLMailContentById($id) {
    $mail = $this->bdb->getMailContent($id);
    $result = '<sheet width="100%" align="center">'.LF;
    $recipient = $this->_getSubscriberAddress($mail['content']);
    $content = '';
    $content .= '<div class="header">';
    $content .= sprintf(
      '<div class="headertitle">%s: %s</div>',
      papaya_strings::escapeHTMLChars($this->_gt('Recipient')),
      papaya_strings::escapeHTMLChars($recipient)
    );
    $content .= '</div>';
    if (!empty($mail)) {
      $content .= nl2br(papaya_strings::escapeHTMLChars($mail['content']));
    }
    $result .= sprintf('<text>%s</text>'.LF, $content);
    $result .= '</sheet>'.LF;
    return $result;
  }

  function reCategorizeMail($id, $category) {
    if (!empty($id) && !empty($category)) {
      $mail = $this->bdb->getMailContent($id);
      if ($category == 'bounce') {
        $cat = 'BOUNCE';
        $cat_id = 2;
      } else {
        $cat = 'HAM';
        $cat_id = 3;
      }
      if ($this->bayesian->rateMessage($mail['content'], $cat)) {
        return $this->bdb->setMailCategory($id, $cat_id);
      }
    }
    return FALSE;
  }

  /**
  * Returns an xml representation of a list with users that have been blocked.
  *
  * @return string XML listview document
  */
  function getXMLBlockedSubscribers() {
    $blockedSubscribers = $this->bdb->getBlockedSubscribers();
    if (empty($blockedSubscribers)) {
      return FALSE;
    }
    $result = sprintf(
      '<listview title="%s">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Deactivated users'))
    );
    $result .= sprintf(
      '<cols><col>%s</col><col/></cols>'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Email'))
    );
    $result .= '<items>'.LF;
    foreach ($blockedSubscribers as $subscriber) {
      $result .= sprintf(
        '<listitem title="%s">'.LF,
        papaya_strings::escapeHTMLChars($subscriber['subscriber_email'])
      );
      $result .= sprintf(
        '<subitem><a href="%s">%s</a></subitem>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(
            array(
              'cat_id'  => $category['id'],
              'cmd'     => 'bounces',
              'mode'    => 900,
              'bcmd'    => 'unblocksubscriber',
              'sub_id'  =>  $subscriber['subscriber_id']
            )
          )
        ),
        $this->_gt('unblock')
      );
      $result .= '</listitem>'.LF;
    }
    $result .= '</items>'.LF;
    $result .= '</listview>'.LF;
    return $result;
  }

  function unblockSubscriber($subscriberId) {
    if ($subscriberId > 0) {
      return $this->bdb->activateSubscriber($subscriberId);
    }
    return FALSE;
  }

}

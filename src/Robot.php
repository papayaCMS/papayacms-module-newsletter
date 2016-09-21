<?php
/**
* Cron job module for creating and sending mailings automatically.
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
* @version $Id: Robot.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Load necessary libraries
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_cronjob.php');

/**
* Cron job module for creating and sending mailings automatically.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class NewsletterRobot extends base_cronjob {
  /**
  * Parameter group name.
  * @var string
  */
  public $paramName = 'nws';

  /**
  * Instance of the base class.
  * @var NewsletterRobotBase
  */
  protected $baseObject = NULL;

  /**
  * Editfields.
  *
  * @var array $editFields
  */
  public $editFields = array(
    'Newsletter',
    'mailinggroup_id' => array(
      'Newsletter', 'isNum', TRUE, 'function', 'callbackGetNewsletters'),
    'newsletter_list_id' => array(
      'Subscriber list', 'isNum', TRUE, 'function', 'callbackGetSubscriberLists'
    ),
    'save_to_queue' => array(
      'Send newsletter automatically',
      'isNum',
      TRUE,
      'yesno',
      NULL,
      'Choose yes if you want to add mails automatically to send queue.',
      0
    ),
    'Pages',
    'mailing_url' => array(
      'Archive page', 'isNum', FALSE, 'pageid', 3, 'Please insert the id of the archive page.'
    ),
    'unsubscribe_url' => array(
      'Unsubscription page',
      'isNum',
      FALSE,
      'pageid',
      3,
      'Please insert the id of the unsubscription page.'
    ),
    'Language',
    'language' => array(
      'Language', 'isNum', FALSE, 'function', 'getContentLanguageCombo'
    )
  );

  /**
  * Get instance of the base class
  *
  * @return NewsletterRobotBase
  */
  public function getBaseObject() {
    if (!is_object($this->baseObject)) {
      include_once(dirname(__FILE__).'/Robot/Base.php');
      $this->baseObject = new NewsletterRobotBase($this);
      $this->baseObject->setPageData($this->data);
      $this->baseObject->setConfiguration($this->papaya()->options);
    }
    return $this->baseObject;
  }

  /**
  * Set the instance of a base object to be used instead the original one.
  *
  * @param object $mock
  */
  public function setBaseObject($mock) {
    $this->baseObject = $mock;
  }

  /**
  * Check execution parameters
  *
  * @access public
  * @return boolean Execution possible?
  */
  public function checkExecParams() {
    return (bool)(
      !empty($this->data['mailinggroup_id']) &&
      !empty($this->data['newsletter_list_id']) &&
      isset($this->data['save_to_queue']) &&
      ((int)$this->data['save_to_queue'] == 0 || (int)$this->data['save_to_queue'] == 1)
    );
  }

  /**
  * Main execution method.
  *
  * return integer or string with cronjob status
  */
  public function execute() {
    return $this->getBaseObject()->run();
  }

  /**
  * A module provided callback method for getting subscriber lists.
  *
  * @param string $name
  * @param array $element
  * @param string $data
  */
  public function callbackGetNewsletters($name, $element, $data) {
    $result = '';
    $newsletterLists = $this->getBaseObject()->getMailingGroups();
    $result .= sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
      PapayaUtilStringXml::escapeAttribute($this->paramName),
      PapayaUtilStringXml::escapeAttribute($name)
    );
    if (!empty($newsletterLists)) {
      if (!$element[2]) {
        $selected = ((int)$data == 0) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="0" %s>%s</option>'.LF,
          $selected,
          PapayaUtilStringXml::escape($this->_gt('None'))
        );
      }
      foreach ($newsletterLists as $list) {
        $selected = ((int)$data == $list['mailinggroup_id']) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%d" %s>%s</option>'.LF,
          (int)$list['mailinggroup_id'],
          $selected,
          PapayaUtilStringXml::escape($list['mailinggroup_title'])
        );
      }
    } else {
      $result .= sprintf(
        '<option value="" disabled="disabled">%s</option>'.LF,
        PapayaUtilStringXml::escape($this->_gt('No newsletters available'))
      );
    }
    $result .= '</select>'.LF;
    return $result;
  }

  /**
  * A module provided callback method for getting subscriber lists.
  *
  * @param string $name
  * @param array $element
  * @param string $data
  */
  public function callbackGetSubscriberLists($name, $element, $data) {
    $result = '';
    $subscriberLists = $this->getBaseObject()->getNewsletterLists();
    $result .= sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
      PapayaUtilStringXml::escapeAttribute($this->paramName),
      PapayaUtilStringXml::escapeAttribute($name)
    );
    if (!empty($subscriberLists)) {
      if (!$element[2]) {
        $selected = ((int)$data == 0) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="0" %s>%s</option>'.LF,
          $selected,
          PapayaUtilStringXml::escape($this->_gt('None'))
        );
      }
      foreach ($subscriberLists as $list) {
        $selected = ((int)$data == $list['newsletter_list_id']) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%d" %s>%s</option>'.LF,
          (int)$list['newsletter_list_id'],
          $selected,
          PapayaUtilStringXml::escape($list['newsletter_list_name'])
        );
      }
    } else {
      $result .= sprintf(
        '<option value="" disabled="disabled">%s</option>'.LF,
        PapayaUtilStringXml::escape($this->_gt('No subscriber lists available'))
      );
    }
    $result .= '</select>'.LF;
    return $result;
  }

  /**
  * Get language combo
  *
  * @param string $name
  * @param array $element
  * @param mixed $data
  * @access public
  * @return string XML
  */
  function getContentLanguageCombo($name, $element, $data) {
    $result = '';
    $languages = $this->papaya()->administrationLanguage->languages();
    if (count($languages) > 0) {
      $result .= sprintf(
        '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
        papaya_strings::escapeHTMLChars($this->paramName),
        papaya_strings::escapeHTMLChars($name)
      );
      foreach ($languages as $lngId => $lng) {
        $selected = ($data > 0 && $lngId == $data) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%d"%s>%s (%s)</option>'.LF,
          papaya_strings::escapeHTMLChars($lng['id']),
          $selected,
          papaya_strings::escapeHTMLChars($lng['title']),
          papaya_strings::escapeHTMLChars($lng['code'])
        );
      }
      $result .= '</select>'.LF;
    } else {
      $result = '<input type="text" disabled="disabled" value="No language found"/>';
    }
    return $result;
  }
}

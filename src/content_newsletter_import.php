<?php
/**
* Page module import newsletter
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
* @version $Id: content_newsletter_import.php 13 2014-02-20 12:13:35Z SystemVCS $
*/

/**
* Basic class page module
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_content.php');

/**
* Page module import newsletter
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class content_newsletter_import extends base_content {

  var $editObject = NULL;
  var $inputFieldSize = 'x-large';
  var $paramName = 'nws';
  var $fullPage = FALSE;
  var $noIndex = FALSE;

  function getParsedData() {
    $result = '';
    $result .= sprintf(
      '<title>%s</title>'.LF,
      papaya_strings::escapeHTMLChars(@$this->data['title'])
    );
    $result .= sprintf(
      '<subtitle>%s</subtitle>'.LF,
      papaya_strings::escapeHTMLChars(@$this->data['subtitle'])
    );
    $result .= '<mail>';
    $result .= '<content>';
    $result .= sprintf(
      '<intro>%s</intro>'.LF,
      $this->getXHTMLString(@$this->data['intro'], !((bool)@$this->data['intro_nl2br']))
    );
    $result .= sprintf(
      '<footer>%s</footer>'.LF,
      $this->getXHTMLString(@$this->data['footer'], !((bool)@$this->data['footer_nl2br']))
    );
    $result .= '<sections>';
    if (isset($this->data['sections']) && is_array($this->data['sections']) &&
        count($this->data['sections']) > 0) {
      foreach ($this->data['sections'] as $section) {
        $result .= sprintf(
          '<section>%s</section>'.LF,
          $this->getSection($section)
        );
      }
    }
    $result .= '</sections>';
    $result .= '</content>';
    $result .= '</mail>';
    return $result;
  }

  /**
  * Get xml string for one section
  *
  * @access public
  * @param array $section
  * @return string xml string
  */
  function getSection($section) {
    $result = '';
    $result .= sprintf(
      '<title>%s</title>'.LF,
      papaya_strings::escapeHTMLChars(@$section['title'])
    );
    $result .= sprintf(
      '<subtitle>%s</subtitle>'.LF,
      papaya_strings::escapeHTMLChars(@$section['subtitle'])
    );
    $result .= sprintf(
      '<text>%s</text>'.LF,
      $this->getXHTMLString(@$section['text'], !((bool)@$this->data['nl2br']))
    );
    return $result;
  }
  /**
  * Get parsed teaser
  *
  * @access public
  * @return string
  */
  function getParsedTeaser() {
    $result = '';
    $result .= sprintf(
      '<title>%s</title>'.LF,
      papaya_strings::escapeHTMLChars(@$this->data['title'])
    );
    $result .= sprintf(
      '<text>%s</text>'.LF,
      $this->getXHTMLString(@$this->data['teaser'], !((bool)@$this->data['nl2br']))
    );
    return $result;
  }

  /**
  * initializes edit object if not yet instantiated and passes members
  * @return boolean true if object didn't exist else false
  */
  function initEditObject() {
    if (!(isset($this->editObject) && is_object($this->editObject))) {
      include_once(dirname(__FILE__).'/admin_newsletter_import.php');
      $this->editObject = new admin_newsletter_import();
      $this->editObject->paramName = $this->paramName;
      $this->editObject->parentObj = $this->parentObj;
      $this->editObject->contentObj = $this;
      $this->editObject->images = $this->images;
      $this->editObject->data = &$this->data;
      $this->editFields = $this->editObject->editFields;
      return TRUE;
    }
    return FALSE;
  }

  function execute() {
    $this->initEditObject();
    return $this->editObject->execute();
  }

  function initialize() {
    $this->initEditObject();
    return $this->editObject->initialize();
  }

  function getForm() {
    $this->initEditObject();
    return $this->editObject->getForm();
  }

  /**
  * content is modified
  *
  * @return content is modified
  */
  function modified() {
    $this->initEditObject();
    $result = $this->editObject->modified();
    if ($result) {
      return $result;
    } else {
      return parent::modified();
    }
  }

  function checkData() {
    $this->initEditObject();
    $result = $this->editObject->checkData();
    if ($result) {
      return $result;
    } else {
      return parent::checkData();
    }
  }

  /**
  * get node by id or get all sections
  *
  * @param int $id
  * @return $node node
  */
  function &getItemRef($id = '') {
    if (isset($id) && $id != '') {
      $node = &$this->data['sections'][$id];
    } else {
      $node = &$this->data['sections'];
    }
    return $node;
  }
}

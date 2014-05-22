<?php
/**
* Aenderungsmodul Newsletterverwaltung
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
* @version $Id: edmodule_newsletter.php 13 2014-02-20 12:13:35Z SystemVCS $
*/

/**
* Basisklasse Aenderungsmodule
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_module.php');

/**
* Edit module for the newsletter application
*
* @package commercial
* @subpackage newsletter
*/
class edmodule_newsletter extends base_module {

  const PERM_MANAGE = 1;
  const PERM_MANAGE_OPTIONS = 2;
  const PERM_MANAGE_QUEUE = 3;
  const PERM_MANAGE_MAILINGS = 6;
  const PERM_MANAGE_MAILING_PERMISSIONS = 9;
  const PERM_MANAGE_SUBSCRIBERS = 7;
  const PERM_MANAGE_BOUNCES = 8;

  const PERM_PREVIEW_XML = 4;
  const PERM_IMPORT_CSV = 5;
  /**
  * permissions
  * @var array
  */
  var $permissions = array(
    self::PERM_MANAGE => 'Manage',
    self::PERM_MANAGE_OPTIONS => 'Options',
    self::PERM_MANAGE_QUEUE => 'Postbox',
    self::PERM_PREVIEW_XML => 'XML Preview',
    self::PERM_IMPORT_CSV => 'Import CSV',
    self::PERM_MANAGE_MAILINGS => 'Mailings',
    self::PERM_MANAGE_SUBSCRIBERS => 'Subscribers',
    self::PERM_MANAGE_BOUNCES => 'Bounces',
    self::PERM_MANAGE_MAILING_PERMISSIONS => 'Mailing Permissions'
  );

  var $pluginOptionFields = array(
    'TEMPLATE_PATH' => array(
      'Output filter',
      'isAlphaNum',
      FALSE,
      'function',
      'callbackOutputModes',
      '',
      'newsletter'
    ),
    'NEWSLETTER_RETURN_PATH' => array(
      'Email Return Path', 'isEmail', FALSE, 'input', 400, '', ''
    ),
    'NEWSLETTER_BOUNCE_COUNTER' => array(
      'Bounce Counter', 'isNum', TRUE, 'input', 2, 'Maximum bounce mails before deactivation.', 5
    ),
    'NEWSLETTER_TEXT_LINEBREAK' => array(
      'Text linebreak',
      'isNum',
      TRUE,
      'input',
      2,
      'RFC 2822 suggests a maximum line length of 76 characters. Options below or equal 10 will
       add only automatic (soft) linebreaks.',
      64
    ),
  );

  /**
  * Function for execute module
  *
  * @access public
  */
  function execModule() {
    if ($this->hasPerm(1, TRUE)) {
      $path = dirname(__FILE__);
      include_once($path.'/papaya_newsletter.php');
      $newsletter = new papaya_newsletter;
      $newsletter->module = $this;
      $newsletter->images = $this->images;
      $newsletter->layout = $this->layout;
      $newsletter->authUser = $this->authUser;

      $newsletter->initialize();
      $newsletter->execute();
      $newsletter->getXML();
    }
  }

  /**
  * Get XHTML for special edit field showing all currunt output modes in a selectbox
  *
  * @param string $name
  * @param array $field
  * @param string $data
  */
  function callbackOutputModes($name, $field, $data) {
    $result = sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">',
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars($name)
    );
    if (empty($data) && isset($this->parentObj) && isset($this->parentObj->viewLink)) {
      $data = $this->parentObj->viewLink['viewmode_ext'];
    }
    include_once(PAPAYA_INCLUDE_PATH.'system/base_viewlist.php');
    $viewList = new base_viewlist();
    $viewList->loadViewModesList();
    if (isset($viewList->viewModes) && is_array($viewList->viewModes)) {
      foreach ($viewList->viewModes as $viewMode) {
        $selected = ($viewMode['viewmode_ext'] == $data) ? ' selected="selected"' : '';
        $result .= sprintf(
          '<option value="%s"%s>%s</option>',
          papaya_strings::escapeHTMLChars($viewMode['viewmode_ext']),
          $selected,
          papaya_strings::escapeHTMLChars($viewMode['viewmode_ext'])
        );
      }
    }
    $result .= '</select>';
    return $result;
  }
}

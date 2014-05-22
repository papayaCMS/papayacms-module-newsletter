<?php
/**
* Main class for managing newsletter feed configurations.
*
* @copyright 2010 by papaya Software GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package Papaya-Commercial
* @subpackage Newsletter
*/

/**
* Load necessary libraries.
*/
require_once(dirname(__FILE__).'/Configuration/List.php');
require_once(dirname(__FILE__).'/Configuration/Item.php');

/**
* Main class for managing newsletter feed configurations.
*
* @package Papaya-Commercial
* @subpackage Newsletter
*/
class PapayaModuleNewsletterFeedConfiguration extends PapayaUiControlInteractive {

  /**
  * Identifier of current newsletter.
  * @var integer
  */
  protected $_newsletterId = 0;

  /**
  * Identifier of current feed.
  * @var integer
  */
  protected $_feedId = 0;

  /**
  * List of feed configuration.
  * @var PapayaModuleNewsletterFeedConfigurationList
  */
  protected $_feeds = NULL;

  /**
  * Dialog object.
  * @var base_dialog
  */
  protected $_dialog = NULL;

  /**
  * Owner object.
  * @var object
  */
  private $_owner = NULL;

  /**
   * Base module options object.
   * @var base_module_options
   */
  private $_baseModuleOptionsObject = NULL;

  /**
   * Papaya Xslt template handler object.
   * @var PapayaTemplateXsltHandler
   */
  private $_papayaTemplateXsltHandlerObject = NULL;

  /**
  * Constructor of the class.
  *
  * @param object $owner Caller object
  */
  public function __construct($owner) {
    PapayaUtilConstraints::assertObject($owner);
    $this->_owner = $owner;
  }

  /**
  * Returns a base module options object.
  *
  * @return base_module_options
  */
  public function getBaseModuleOptionsObject() {
    if (!is_object($this->_baseModuleOptionsObject)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_module_options.php');
      $this->_baseModuleOptionsObject = new base_module_options();
    }
    return $this->_baseModuleOptionsObject;
  }

  /**
  * Sets the base module options object.
  *
  * @param base_module_options $baseModuleOptionsObject
  */
  public function setBaseModuleOptionsObject($baseModuleOptionsObject) {
    PapayaUtilConstraints::assertObject($baseModuleOptionsObject);
    $this->_baseModuleOptionsObject = $baseModuleOptionsObject;
  }

  /**
  * Returns a papaya template xslt handler object.
  *
  * @return PapayaTemplateXsltHandler
  */
  public function getPapayaTemplateXsltHandler() {
    if (!is_object($this->_papayaTemplateXsltHandlerObject)) {
      $this->_papayaTemplateXsltHandlerObject = new PapayaTemplateXsltHandler();
    }
    return $this->_papayaTemplateXsltHandlerObject;
  }

  /**
  * Sets the papaya template xslt handler object.
  *
  * @param PapayaTemplateXsltHandler $papayaTemplateXsltHandler
  */
  public function setPapayaTemplateXsltHandler($papayaTemplateXsltHandler) {
    PapayaUtilConstraints::assertObject($papayaTemplateXsltHandler);
    $this->_papayaTemplateXsltHandlerObject = $papayaTemplateXsltHandler;
  }

  /**
  * Callback from form to get the defined template path for file listing.
  *
  * @return string
  */
  public function getTemplatePath() {
    $templatePath = $this->getBaseModuleOptionsObject()->readOption(
      '96157ec2db3a16c368ff1d21e8a4824a', 'TEMPLATE_PATH', 'newsletter'
    );
    return $this->getPapayaTemplateXsltHandler()->getLocalPath().$templatePath.'/';
  }

  /**
  * Sets properties for loading feeds and loads the configuration for current newsletter id.
  */
  public function prepare() {
    $this->_newsletterId = $this->parameters()->get('mailinggroup_id', 0);
    $this->_feedId = $this->parameters()->get('feed_id', 0);
    if ($this->_newsletterId > 0) {
      $this->feeds()->load($this->_newsletterId);
    }
  }

  /**
  * Executes requested actions depending on parameters.
  */
  public function execute() {
    if ($this->parameters()->get('confirm_delete') &&
        $this->parameters()->get('feed_delete') > 0) {
      if ($this->feeds()->delete($this->parameters()->get('feed_delete'))) {
        $this->_owner->addMsg(MSG_INFO, 'Feed deleted.');
      }
      $this->parameters()->set('feed_delete', 0);
    } else {
      $this->_executeEdit();
      $this->_executeMove();
    }
  }

  /**
  * Appends elements to given node.
  *
  * @param PapayaXmlElement $node
  */
  public function appendTo(PapayaXmlElement $node) {
    if ($this->parameters()->get('feed_delete') > 0) {
      $this->_appendDeleteConfirmationDialog($node);
    } else {
      $this->_appendDialog($node);
    }
    $this->_appendListView($node);
  }

  /**
  * Saves submitted settings after checking dialog inputs.
  */
  protected function _executeEdit() {
    if ($this->parameters()->get('confirm_edit', FALSE)) {
      $dialog = $this->_prepareDialog();
      if ($dialog->checkDialogInput()) {
        $feed = $this->feeds()->getItem($this->_feedId);
        if ($this->_feedId) {
          $feed->load($this->_feedId);
        } else {
          $feed->position = 99999;
        }
        $feed->assign($dialog->data);
        $feed->groupId = $this->_newsletterId;
        if ($feed->save()) {
          $this->_owner->addMsg(MSG_INFO, 'Feed saved.');
          $this->_feedId = $feed['id'];
          $this->feeds()->load($this->_newsletterId);
          unset($dialog);
        }
      }
    }
  }

  /**
  * Moves requested feed configuration up in the list.
  */
  protected function _executeMove() {
    if ($this->parameters()->get('feed_move_to', 0)) {
      $moved = $this->feeds()->move(
        $this->parameters()->get('feed_id', 0),
        $this->parameters()->get('feed_move_to', 0)
      );
    }
  }

  protected function _appendListView(PapayaXmlElement $parentNode) {
    $listView = $parentNode->appendElement(
      'listview',
      array('title' => new PapayaUiStringTranslated('Feeds'))
    );

    $items = $listView->appendElement('items');
    $position = 0;
    $positions = array();
    foreach ($this->feeds() as $feed) {
      $positions[++$position] = $feed['id'];
    }
    $position = 0;
    foreach ($this->feeds() as $feed) {
      $this->_appendListItem($items, $feed, ++$position, $positions);
    }
  }

  protected function _appendListItem(PapayaXmlElement $itemsNode, $feed,
                                     $position, $positions) {
    $item = $itemsNode->appendElement(
      'listitem',
      array(
        'title' => $feed['url'],
        'image' => $this->getApplication()->images['items-link'],
        'href' => PapayaUiReference::create()->setParameters(
          array(
            'mailinggroup_id' => $this->_newsletterId,
            'content_type' => 'feeds',
            'cmd' => 'edit_mailinggroup',
            'mode' => 1,
            'feed_id' => $feed['id']
          ),
          $this->parameterGroup()
        )
      )
    );
    if ($this->_feedId == $feed['id']) {
      $item->setAttribute('selected', 'selected');
    }
    $subItem = $item->appendElement(
      'subitem', array('align' => 'center')
    );
    if ($position > 1) {
      $subItem->appendElement(
        'glyph',
        array(
          'hint' => new PapayaUiStringTranslated('Move up'),
          'src' => $this->getApplication()->images['actions-go-up'],
          'href' => PapayaUiReference::create()->setParameters(
            array(
              'mailinggroup_id' => $this->_newsletterId,
              'content_type' => 'feeds',
              'cmd' => 'edit_mailinggroup',
              'mode' => 1,
              'feed_id' => $feed['id'],
              'feed_move_to' => $positions[$position - 1]
            ),
            $this->parameterGroup()
          )
        )
      );
    }
    $subItem = $item->appendElement(
      'subitem', array('align' => 'center')
    );
    if ($position < count($positions)) {
      $subItem->appendElement(
        'glyph',
        array(
          'hint' => new PapayaUiStringTranslated('Move down'),
          'src' => $this->getApplication()->images['actions-go-down'],
          'href' => PapayaUiReference::create()->setParameters(
            array(
              'mailinggroup_id' => $this->_newsletterId,
              'content_type' => 'feeds',
              'cmd' => 'edit_mailinggroup',
              'mode' => 1,
              'feed_id' => $feed['id'],
              'feed_move_to' => $positions[$position + 1]
            ),
            $this->parameterGroup()
          )
        )
      );
    }
  }

  /**
  * Returns newsletter feed configuration list.
  *
  * @param PapayaModuleNewsletterFeedConfigurationList $feeds
  * @return PapayaModuleNewsletterFeedConfigurationList
  */
  public function feeds(PapayaModuleNewsletterFeedConfigurationList $feeds = NULL) {
    if (isset($feeds)) {
      $this->_feeds = $feeds;
    }
    if (is_null($this->_feeds)) {
      $this->_feeds = new PapayaModuleNewsletterFeedConfigurationList();
    }
    return $this->_feeds;
  }

  protected function _prepareDialog() {
    if (is_null($this->_dialog)) {
      $editFields = array(
        'url' => array('Url', 'isHTTPX', TRUE, 'input', 2048, '', ''),
        'Limits',
        'minimum' => array('Minimum entries', 'isNum', TRUE, 'input', 3, '', 1),
        'maximum' => array('Maximum entries', 'isNum', TRUE, 'input', 3, '', 5),
        'period' => array('Maximum period', 'isNum', TRUE, 'input', 3, 'Maximum period in days', 7),
        'Template',
        'template' => array (
          'Convert to tinymce',
          'isFile',
          FALSE,
          'filecombo',
          array('callback:getTemplatePath', '/^\w+\.xsl$/i'),
        )
      );
      $hidden = array(
        'mailinggroup_id' => $this->_newsletterId,
        'content_type' => 'feeds',
        'cmd' => 'edit_mailinggroup',
        'confirm_edit' => 1,
        'mode' => 1,
        'feed_id' => 0
      );
      if ($this->_feedId > 0 &&
          ($feed = $this->feeds()->getItem($this->_feedId))) {
        $data = $feed->toArray();
        $hidden['feed_id'] = $feed['id'];
        $title = new PapayaUiStringTranslated('Edit');
      } else {
        $data = array();
        $title = new PapayaUiStringTranslated('Add');
      }
      $this->_dialog = new base_dialog(
        $this, $this->parameterGroup(), $editFields, $data, $hidden
      );
      $this->_dialog->loadParams();
      $this->_dialog->dialogTitle = $title;
    }
    return $this->_dialog;
  }

  protected function _appendDialog(PapayaXmlElement $parent) {
    $dialog = $this->_prepareDialog();
    $parent->appendXml($dialog->getDialogXML());
  }

  protected function _appendDeleteConfirmationDialog(PapayaXmlElement $parent) {
    $feed = $this->feeds()->getItem($this->_feedId);
    $messageDialog = new base_msgdialog(
      $this,
      $this->parameterGroup(),
      array(
        'mailinggroup_id' => $this->_newsletterId,
        'content_type' => 'feeds',
        'cmd' => 'edit_mailinggroup',
        'confirm_edit' => 1,
        'mode' => 1,
        'feed_id' => $feed['id'],
        'feed_delete' => $feed['id'],
        'confirm_delete' => 1
      ),
      new PapayaUiStringTranslated('Delete feed #%d "%s"?', array($feed['id'], $feed['url']))
    );
    $messageDialog->buttonTitle = 'Delete';
    $parent->appendXml($messageDialog->getMsgDialog());
  }

}
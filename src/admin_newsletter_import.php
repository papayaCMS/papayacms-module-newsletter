<?php
/**
* Page module - Newsletter admin part
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
* @version $Id: admin_newsletter_import.php 13 2014-02-20 12:13:35Z SystemVCS $
*/

/**
* Admin module - Newsletter admin part
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class admin_newsletter_import extends base_db {

  var $editFields = array(
    'title'    => array ('Title', 'isNoHTML', TRUE, 'input', 400),
    'subtitle' => array ('Subtitle', 'isNoHTML', FALSE, 'input', 400),
    'teaser'   => array ('Teaser', 'isSomeText', FALSE, 'simplerichtext', 10)
  );

  var $modified = FALSE;
  var $editDialog = NULL;
  var $delDialog = NULL;
  var $importDialog = NULL;
  var $inputFieldSize = 'x-large';

  /**
  * mailing groups
  * @var $mailingGroups
  */
  var $mailingGroups = NULL;

  /**
  * all mailings
  * @var $mailings
  */
  var $mailings = NULL;

  /**
  * one mailing details
  * @var $oneMailing
  */
  var $oneMailing = NULL;

  /**
  * contents of a mailing
  * @var $contents
  */
  var $contents = NULL;

  /**
  * count of contents of a mailing
  * @var $contentsCount
  */
  var $contentsCount = NULL;

  /**
  * mailing groups
  * @var array $tableMailingGroups
  */
  var $tableMailingGroups = '';

  /**
  * mailings
  * @var array $tableMailings
  */
  var $tableMailings = '';

  /**
  * mailing contents
  * @var array $tableMailingContents
  */
  var $tableMailingContents = '';

  function __construct($param = NULL) {
    $this->tableMailings = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailings';
    $this->tableMailingGroups = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailinggroups';
    $this->tableMailingContents = PAPAYA_DB_TABLEPREFIX.'_newsletter_mailingcontent';
    parent::__construct();
  }

  function admin_newsletter_import($param = NULL) {
    $this->__construct($param);
  }

  function initialize() {
    $this->sessionParamName = 'PAPAYA_SESS_'.get_class($this).'_'.$this->paramName;
    $this->initializeParams();
    $this->sessionParams = $this->getSessionValue($this->sessionParamName);
    $this->initializeSessionParam('contentmode', array('cmd'));
  }

  function execute() {
    if (isset($this->params['contentmode']) && $this->params['contentmode'] >= 0) {
      if ($this->params['contentmode'] == 1 && isset($this->params['cmd'])) {
        switch(@$this->params['cmd']) {
        case 'edit':
          if (isset($this->params['save']) && $this->params['save'] == 1) {
            if (isset($this->params['intro']) && $this->params['intro'] == 1) {
              $this->editIntro();
            } elseif (isset($this->params['footer']) && $this->params['footer'] == 1) {
              $this->editFooter();
            } else {
              $this->editContent();
            }
            $this->modified = TRUE;
          } else {
            if (isset($this->params['id'])) {
              $this->editDialog = $this->getDialog();
            } elseif ((isset($this->params['intro']) && $this->params['intro'] == 1) ||
                        (isset($this->params['footer']) && $this->params['footer'] == 1)) {
              $this->editDialog = $this->getIntroFooterDialog();
            }
          }
          break;
        case 'add':
          if (isset($this->params['save']) && $this->params['save'] == 1) {
            $this->addContent();
            $this->modified = TRUE;
          } else {
            $this->editDialog = $this->getDialog();
          }
          break;
        case 'del':
          if (isset($this->params['id'])) {
            if (isset($this->params['delete']) && $this->params['delete'] == 1) {
              $this->delContent();
              $this->modified = TRUE;
            } else {
              $this->delDialog = $this->getDelDialog();
            }
          }
          break;
        case 'move_down':
          if (isset($this->params['id'])) {
            $this->moveDown();
            $this->modified = TRUE;
          }
          break;
        case 'move_up':
          if (isset($this->params['id'])) {
            $this->moveUp();
            $this->modified = TRUE;
          }
          break;
        default:
          break;
        }
      } elseif ($this->params['contentmode'] == 2) {
        $this->loadImportData();
        if (isset($this->params['mailing_id']) && $this->params['mailing_id'] &&
            isset($this->params['cmd']) && $this->params['cmd'] == 'import') {
          if (isset($this->params['confirm_import']) && $this->params['confirm_import'] == 1) {
            $this->importNewsletter();
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('Newsletter "%s" successfully imported.'),
                $this->mailings[$this->params['mailing_id']]['mailing_title']
              )
            );
            $this->modified = TRUE;
          } else {
            $this->importDialog = $this->getImportDialog();
          }
        }
      }
    }
    $this->setSessionValue($this->sessionParamName, $this->sessionParams);
  }

  /**
  * Get content form
  *
  * @access public
  * @return string form
  */
  function getForm() {
    $result = '';
    $result .= $this->getContentToolbar();
    if (isset($this->params['contentmode']) && $this->params['contentmode'] >= 0) {
      switch (@$this->params['contentmode']) {
      case 1:
        if (isset($this->editDialog) && is_object($this->editDialog)) {
          $result .= $this->editDialog->getDialogXML();
        } elseif (isset($this->delDialog) && is_object($this->delDialog)) {
          $result .= $this->delDialog->getMsgDialog();
        }
        $result .= $this->getXMLContentTree();
        break;
      case 2:
        if (isset($this->importDialog) && is_object($this->importDialog)) {
          $result .= $this->importDialog->getMsgDialog();
        }
        $result .= $this->getXMLMailingList();
        break;
      default:
        $this->initializeDialog();
        $result .= $this->dialog->getDialogXML();
        break;
      }
    }
    return $result;
  }

  /**
  * generates toolbar for content
  *
  * @access public
  * @return toolbar string
  */
  function getContentToolbar() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $GLOBALS['PAPAYA_IMAGES'];

    $toolbar->addButton(
      'General',
      $this->getLink(array('contentmode' => 0)),
      $this->images['categories-properties'],
      '',
      @$this->params['contentmode'] == 0
    );
    $toolbar->addButton(
      'Contents',
      $this->getLink(array('contentmode' => 1)),
      $this->images['categories-content'],
      '',
      @$this->params['contentmode'] == 1 &&
      (
        !(@$this->params['cmd'] == 'add') || (isset($this->params['save']))
      )
    );
    $toolbar->addSeparator();
    if (isset($this->params['contentmode']) &&
        $this->params['contentmode'] == 1) {
      $toolbar->addButton(
        'Add content',
        $this->getLink(
          array('contentmode' => 1, 'cmd' => 'add')
        ),
        $this->images['actions-page-child-add'],
        '',
        @$this->params['cmd'] == 'add' && !(isset($this->params['save']))
      );
      $toolbar->addSeparator();
    }
    $toolbar->addButton(
      'Import newsletter',
      $this->getLink(array('contentmode' => 2)),
      $this->images['actions-download'],
      '',
      @$this->params['contentmode'] == 2
    );
    if ($str = $toolbar->getXML()) {
      return '<toolbar>'.$str.'</toolbar>';
    }
    return '';
  }

  /**
  * Initialize dialog
  *
  * @access public
  */
  function initializeDialog() {
    if (!@is_object($this->dialog)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if (isset($this->parentObj) && is_object($this->parentObj)) {
        $hidden['page_id'] = $this->parentObj->topicId;
      }
      $hidden['save'] = 1;
      $this->dialog = new base_dialog(
        $this->contentObj, $this->paramName, $this->editFields, $this->data, $hidden
      );
      $this->dialog->loadParams();
      $this->dialog->inputFieldSize = $this->inputFieldSize;
      $this->dialog->baseLink = $this->baseLink;
      $this->dialog->dialogTitle = $this->_gt('Edit content');
      $this->dialog->dialogDoubleButtons = TRUE;
      $this->dialog->expandPapayaTags = TRUE;
      $this->dialog->tableTopics = $this->parentObj->tableTopics;
    }
  }


  /**
  * Get content structure tree as XML listview for use as edit/modify table
  *
  * @access public
  * @return string $result XML listview
  */
  function getXMLContentTree() {
    $result = '';
    if (isset($this->data['sections']) && is_array($this->data['sections']) &&
        count($this->data['sections']) > 0) {
      $result = sprintf('<listview title="%s">'.LF, $this->_gt('Contents'));
      $result .= '<items>'.LF;
      $result .= $this->getXMLIntroListItem();
      $result .= $this->getXMLContentListitems();
      $result .= $this->getXMLFooterListItem();
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
    }
    return $result;
  }

  /**
  * Get content list items for listview
  *
  * @access public
  * @return string listitems
  */
  function getXMLContentListitems() {
    $result = '';
    $selected = '';
    foreach ($this->data['sections'] as $id => $section) {
      if (isset($this->params['id']) && $id == $this->params['id']) {
        $selected = 'selected="selected"';
      } else {
        $selected = '';
      }
      $result .= sprintf(
        '<listitem title="%s" href="%s" %s>'.LF,
        papaya_strings::escapeHTMLChars(@$section['title']),
        papaya_strings::escapeHTMLChars(
          $this->getLink(
            array('contentmode' => 1, 'cmd' => 'edit', 'id' => $id)
          )
        ),
        $selected
      );
      $result .= sprintf(
        '<subitem><a href="%s"><glyph src="%s" alt="%s" hint="%s" /></a></subitem>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(array('contentmode' => 1, 'cmd' => 'edit', 'id' => $id))
        ),
        papaya_strings::escapeHTMLChars($this->images['categories-content']),
        papaya_strings::escapeHTMLChars($this->_gt('Edit item')),
        papaya_strings::escapeHTMLChars($this->_gt('Edit item'))
      );
      if (isset($this->data['sections'][$id + 1])) {
        $result .= sprintf(
          '<subitem><a href="%s"><glyph src="%s" alt="%s"/></a></subitem>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(array('contentmode' => 1, 'cmd' => 'move_down', 'id' => $id))
          ),
          papaya_strings::escapeHTMLChars($this->images['actions-go-down']),
          papaya_strings::escapeHTMLChars($this->_gt('move item down'))
        );
      } else {
        $result .= '<subitem></subitem>'.LF;
      }
      if (isset($this->data['sections'][$id - 1])) {
        $result .= sprintf(
          '<subitem><a href="%s"><glyph src="%s" alt="%s"/></a></subitem>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(array('contentmode' => 1, 'cmd' => 'move_up', 'id' => $id))
          ),
          papaya_strings::escapeHTMLChars($this->images['actions-go-up']),
          papaya_strings::escapeHTMLChars($this->_gt('move item up'))
        );
      } else {
        $result .= '<subitem></subitem>'.LF;
      }
      $result .= sprintf(
        '<subitem><a href="%s"><glyph src="%s" alt="%s" hint="%s" /></a></subitem>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(array('contentmode' => 1, 'cmd' => 'del', 'id' => $id))
        ),
        papaya_strings::escapeHTMLChars($this->images['actions-page-delete']),
        papaya_strings::escapeHTMLChars($this->_gt('Delete item')),
        papaya_strings::escapeHTMLChars($this->_gt('Delete item'))
      );
      $result .= '</listitem>'.LF;
    }
    return $result;
  }

   /**
  * Get intro list item for listview
  *
  * @access public
  * @return string listitem
  */
  function getXMLIntroListitem() {
    $result = '';
    $selected = '';

    if (isset($this->params['intro']) && $this->params['intro'] == 1) {
      $selected = 'selected="selected"';
    }
    $result .= sprintf(
      '<listitem title="%s" href="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Intro')),
      papaya_strings::escapeHTMLChars(
        $this->getLink(array('contentmode' => 1, 'cmd' => 'edit', 'intro' => 1))
      ),
      $selected
    );
    $result .= sprintf(
      '<subitem><a href="%s"><glyph src="%s" alt="%s" hint="%s" /></a></subitem>'.LF,
      papaya_strings::escapeHTMLChars(
        $this->getLink(array('contentmode' => 1, 'cmd' => 'edit', 'intro' => 1))
      ),
      papaya_strings::escapeHTMLChars($this->images['categories-content']),
      papaya_strings::escapeHTMLChars($this->_gt('Edit item')),
      papaya_strings::escapeHTMLChars($this->_gt('Edit item'))
    );
    $result .= '<subitem></subitem>'.LF;
    $result .= '<subitem></subitem>'.LF;
    $result .= '<subitem></subitem>'.LF;
    $result .= '</listitem>'.LF;
    return $result;
  }

   /**
  * Get footer list item for listview
  *
  * @access public
  * @return string listitems
  */
  function getXMLFooterListitem() {
    $result = '';
    $selected = '';

    if (isset($this->params['footer']) && $this->params['footer'] == 1) {
      $selected = 'selected="selected"';
    }
    $result .= sprintf(
      '<listitem title="%s" href="%s" %s>'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Footer')),
      papaya_strings::escapeHTMLChars(
        $this->getLink(array('contentmode' => 1, 'cmd' => 'edit', 'footer' => 1))
      ),
      $selected
    );
    $result .= sprintf(
      '<subitem><a href="%s"><glyph src="%s" alt="%s" hint="%s" /></a></subitem>'.LF,
      papaya_strings::escapeHTMLChars(
        $this->getLink(array('contentmode' => 1, 'cmd' => 'edit', 'footer' => 1))
      ),
      papaya_strings::escapeHTMLChars($this->images['categories-content']),
      papaya_strings::escapeHTMLChars($this->_gt('Edit item')),
      papaya_strings::escapeHTMLChars($this->_gt('Edit item'))
    );
    $result .= '<subitem></subitem>'.LF;
    $result .= '<subitem></subitem>'.LF;
    $result .= '<subitem></subitem>'.LF;
    $result .= '</listitem>'.LF;
    return $result;
  }

  /**
  * content is modified
  *
  * @access public
  * @return bool content is modified
  */
  function modified() {
    if (@$this->params['contentmode'] > 0) {
      return $this->modified;
    } else {
      return FALSE;
    }
  }

  /**
  * check data
  *
  * @access public
  * @return bool contentmode
  */
  function checkData() {
    switch(@$this->params['contentmode']) {
    case 1  :
      return TRUE;
    case 2  :
      return TRUE;
    default :
      return FALSE;
    }
  }

  /**
  * get node by id or get all sections
  *
  * @access public
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

  /**
  * get add | edit dialog
  *
  * @access public
  * @return string dialog
  */
  function getDialog() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
    $hidden = array(
      'contentmode' => @$this->params['contentmode'],
      'cmd'         => @$this->params['cmd'],
      'id'          => @$this->params['id'],
      'save'        => 1,
    );
    $fields = array(
      'content_nl2br'     => array('Automatic linebreak', 'isNum', FALSE, 'combo',
                               array(0 => 'Yes', 1 => 'No'),
                               'Apply linebreaks from input to the HTML output.'),
      'content_title'     => array('Title', 'isNoHtml', FALSE, 'input', 400),
      'content_subtitle'  => array('Subtitle', 'isNoHtml', FALSE, 'input', 400),
      'content_text'      => array('Text', 'isSomeText', FALSE, 'richtext', 15)
    );
    if (isset($this->params['cmd']) && $this->params['cmd'] == 'edit') {
      $data = array(
        'content_nl2br'     => @$this->data['sections'][$this->params['id']]['nl2br'],
        'content_title'     => @$this->data['sections'][$this->params['id']]['title'],
        'content_subtitle'  => @$this->data['sections'][$this->params['id']]['subtitle'],
        'content_text'      => @$this->data['sections'][$this->params['id']]['text'],
      );
    }
    $dialog = new base_dialog($this, $this->paramName, $fields, $data, $hidden);
    $dialog->dialogTitle = $this->_gt('Add');
    $dialog->inputFieldSize = 'x-large';
    $dialog->loadParams();
    $dialog->buttonTitle = $this->_gt('Submit');
    return $dialog;
  }

  /**
  * get edit intro | footer dialog
  *
  * @access public
  * @return string dialog
  */
  function getIntroFooterDialog() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
    $hidden = array(
      'contentmode' => @$this->params['contentmode'],
      'cmd'         => @$this->params['cmd'],
      'intro'       => @$this->params['intro'],
      'footer'      => @$this->params['footer'],
      'save'        => 1
    );
    $fields = array(
      'content_nl2br'     => array ('Automatic linebreak', 'isNum', FALSE, 'combo',
                               array(0 => 'Yes', 1 => 'No'),
                               'Apply linebreaks from input to the HTML output.'),
      'content_text'      => array('Text', 'isSomeText', FALSE, 'richtext', 15)
    );
    if (isset($this->params['cmd']) && $this->params['cmd'] == 'edit') {
      if (isset($this->params['intro']) && $this->params['intro'] == 1) {
        $data = array(
          'content_nl2br'     => @$this->data['intro_nl2br'],
          'content_text'      => @$this->data['intro'],
        );
      } elseif (isset($this->params['footer']) && $this->params['footer'] == 1) {
        $data = array(
          'content_nl2br'     => @$this->data['footer_nl2br'],
          'content_text'      => @$this->data['footer'],
        );
      }
    }
    $dialog = new base_dialog($this, $this->paramName, $fields, $data, $hidden);
    $dialog->dialogTitle = $this->_gt('Add');
    $dialog->inputFieldSize = 'x-large';
    $dialog->loadParams();
    $dialog->buttonTitle = $this->_gt('Submit');
    return $dialog;
  }

  /**
  * get del dialog
  *
  * @access public
  * @return string dialog
  */
  function getDelDialog() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
    $hidden = array(
      'contentmode' => @$this->params['contentmode'],
      'cmd'         => 'del',
      'id'          => @$this->params['id'],
      'delete'      => 1
    );

    $msg = sprintf(
      $this->_gt('Delete content "%s"?'),
      $this->data['sections'][@$this->params['id']]['title']
    );
    $dialog = new base_msgdialog(
      $this, $this->paramName, $hidden, $msg, 'question'
    );
    $dialog->buttonTitle = $this->_gt('Delete');
    return $dialog;
  }

  /**
  * add new content
  *
  * @access public
  */
  function addContent() {
    $newNode = array(
      'nl2br'    => @$this->params['content_nl2br'],
      'title'    => @$this->params['content_title'],
      'subtitle' => @$this->params['content_subtitle'],
      'text'     => @$this->params['content_text']
    );
    $node = &$this->getItemRef();
    $node[] = $newNode;
    $node = array_values($node);
    $this->params['id'] = count($node) - 1;
  }

  /**
  * edit content
  *
  * @access public
  */
  function editContent() {
    $node = &$this->getItemRef($this->params['id']);
    $node['nl2br'] = @$this->params['content_nl2br'];
    $node['title'] = @$this->params['content_title'];
    $node['subtitle'] = @$this->params['content_subtitle'];
    $node['text'] = @$this->params['content_text'];
  }

  /**
  * edit intro
  *
  * @access public
  */
  function editIntro() {
    $this->data['intro_nl2br'] = @$this->params['content_nl2br'];
    $this->data['intro'] = @$this->params['content_text'];
  }

  /**
  * edit footer
  *
  * @access public
  */
  function editFooter() {
    $this->data['footer_nl2br'] = @$this->params['content_nl2br'];
    $this->data['footer'] = @$this->params['content_text'];
  }

  /**
  * delete content
  *
  * @access public
  */
  function delContent() {
    $node = &$this->getItemRef();
    unset($node[@$this->params['id']]);
    $node = array_values($node);
    if (count($node) == 0) {
      $node = NULL;
    }
  }

  /**
  * moves content down
  *
  * @access public
  */
  function moveDown() {
    $node = &$this->getItemRef();
    $tmp = array(
      'nl2br'    => @$node[$this->params['id'] + 1]['nl2br'],
      'title'    => @$node[$this->params['id'] + 1]['title'],
      'subtitle' => @$node[$this->params['id'] + 1]['subtitle'],
      'text'     => @$node[$this->params['id'] + 1]['text']
    );
    $node[@$this->params['id'] + 1]['nl2br'] = @$node[@$this->params['id']]['nl2br'];
    $node[@$this->params['id'] + 1]['title'] = @$node[@$this->params['id']]['title'];
    $node[@$this->params['id'] + 1]['subtitle'] = @$node[@$this->params['id']]['subtitle'];
    $node[@$this->params['id'] + 1]['text'] = @$node[@$this->params['id']]['text'];

    $node[@$this->params['id']]['nl2br'] = $tmp['nl2br'];
    $node[@$this->params['id']]['title'] = $tmp['title'];
    $node[@$this->params['id']]['subtitle'] = $tmp['subtitle'];
    $node[@$this->params['id']]['text'] = $tmp['text'];

    $node = array_values($node);
    $this->params['id']++;
  }

  /**
  * moves content up
  *
  * @access public
  */
  function moveUp() {
    $node = &$this->getItemRef();
    $tmp = array(
      'nl2br'    => @$node[$this->params['id'] - 1]['nl2br'],
      'title'    => @$node[$this->params['id'] - 1]['title'],
      'subtitle' => @$node[$this->params['id'] - 1]['subtitle'],
      'text'     => @$node[$this->params['id'] - 1]['text']
    );
    $node[@$this->params['id'] - 1]['nl2br'] = @$node[@$this->params['id']]['nl2br'];
    $node[@$this->params['id'] - 1]['title'] = @$node[@$this->params['id']]['title'];
    $node[@$this->params['id'] - 1]['subtitle'] = @$node[@$this->params['id']]['subtitle'];
    $node[@$this->params['id'] - 1]['text'] = @$node[@$this->params['id']]['text'];

    $node[@$this->params['id']]['nl2br'] = $tmp['nl2br'];
    $node[@$this->params['id']]['title'] = $tmp['title'];
    $node[@$this->params['id']]['subtitle'] = $tmp['subtitle'];
    $node[@$this->params['id']]['text'] = $tmp['text'];

    $node = array_values($node);

    $this->params['id']--;
  }

  /**
  * get import dialog
  *
  * @access public
  * @return string dialog
  */
  function getImportDialog() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
    $hidden = array(
      'contentmode'    => @$this->params['contentmode'],
      'cmd'            => 'import',
      'mailing_id'     => @$this->params['mailing_id'],
      'confirm_import' => 1
    );

    $msg = sprintf(
      $this->_gt('Import newsletter mailing "%s"? Your manual changes will get lost!'),
      @$this->mailings[@$this->params['mailing_id']]['mailing_title']
    );
    $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
    $dialog->buttonTitle = $this->_gt('Import');
    return $dialog;
  }

  /**
  * get xml mailing list
  *
  * @access public
  * @return string $result XML listview
  */
  function getXMLMailingList() {
    $result = '';
    $selected = '';
    $node = 'close';
    $nodeHref = '';

    if (isset($this->mailings) && is_array($this->mailings)) {
      $result = sprintf('<listview title="%s">'.LF, $this->_gt('Mailings'));
      $result .= '<items>'.LF;
      if (isset($this->mailingGroups) && $this->mailingGroups) {
        foreach ($this->mailingGroups as $mailingGroupId => $mailingGroup) {
          if ($mailingGroupId > 0) {
            if (@$this->params['mailinggroup_id'] == @$mailingGroup['mailinggroup_id']) {
              $selected = ' selected="selected"';
              $imgIdx = 'status-folder-open';
              if (count(@$mailingGroup['MAILINGS']) > 0) {
                $node = 'open';
                $nodeHref = sprintf(
                  'nhref="%s"',
                  papaya_strings::escapeHTMLChars(
                    $this->getLink(array('contentmode' => @$this->params['contentmode']))
                  )
                );
              } else {
                $node = 'empty';
                $nodeHref = '';
              }
            } else {
              $selected = '';
              $imgIdx = 'items-folder';
              if (count(@$mailingGroup['MAILINGS']) > 0) {
                $node = 'close';
                $nodeHref = sprintf(
                  'nhref="%s"',
                  papaya_strings::escapeHTMLChars(
                    $this->getLink(
                      array(
                        'contentmode' => @$this->params['contentmode'],
                        'mailinggroup_id' => @$mailingGroup['mailinggroup_id']
                      )
                    )
                  )
                );
              } else {
                $node = 'empty';
                $nodeHref = '';
              }
            }
            $result .= sprintf(
              '<listitem image="%s" href="%s" node="%s" %s title="%s"%s>'.LF,
              papaya_strings::escapeHTMLChars($this->images[$imgIdx]),
              papaya_strings::escapeHTMLChars(
                $this->getLink(
                  array(
                    'mailinggroup_id' => @$mailingGroup['mailinggroup_id'],
                    'contentmode' => @$this->params['contentmode']
                  )
                )
              ),
              $node,
              $nodeHref,
              papaya_strings::escapeHTMLChars(@$mailingGroup['mailinggroup_title']),
              $selected
            );
            $result .= '<subitem></subitem>'.LF;
            $result .= '</listitem>'.LF;
            if (@$this->params['mailinggroup_id'] == @$mailingGroup['mailinggroup_id'] &&
                isset($mailingGroup['MAILINGS']) && is_array($mailingGroup['MAILINGS']) &&
                count(@$mailingGroup['MAILINGS']) > 0) {
              foreach (@$mailingGroup['MAILINGS'] as $mailingId) {
                $mailing = $this->mailings[$mailingId];
                $selected = (@$this->params['mailing_id'] == $mailingId) ?
                  ' selected="selected"' : '';
                $result .= sprintf(
                  '<listitem image="%s" title="%s" indent="1" %s>'.LF,
                  papaya_strings::escapeHTMLChars($this->images['items-mail']),
                  papaya_strings::escapeHTMLChars(@$mailing['mailing_title']),
                  $selected
                );
                $result .= sprintf(
                  '<subitem><a href="%s"><glyph src="%s" alt="%s" hint="%s"/></a></subitem>'.LF,
                  papaya_strings::escapeHTMLChars(
                    $this->getLink(
                      array(
                        'contentmode' => @$this->params['contentmode'],
                        'mailing_id' => $mailingId,
                        'mailinggroup_id' => @$this->params['mailinggroup_id'],
                        'cmd' => 'import'
                      )
                    )
                  ),
                  papaya_strings::escapeHTMLChars($this->images['actions-upload']),
                  papaya_strings::escapeHTMLChars($this->_gt('Import')),
                  papaya_strings::escapeHTMLChars($this->_gt('Import'))
                );
                $result .= '</listitem>'.LF;
              }
            }
          }
        }
      }
      if (isset($this->mailingGroups[-1]) &&
          is_array($this->mailingGroups[-1]['MAILINGS']) &&
          count($this->mailingGroups[-1]['MAILINGS']) > 0) {
        $result .= sprintf(
          '<listitem image="%s" title="%s"><subitem></subitem></listitem>'.LF,
          papaya_strings::escapeHTMLChars($this->images['status-folder-open']),
          papaya_strings::escapeHTMLChars($this->_gt('Unknown'))
        );
        foreach ($this->mailingGroups[-1]['MAILINGS'] as $mailingId) {
          $mailing = $this->mailings[$mailingId];
          $selected = (@$this->params['mailing_id'] == $mailingId) ? ' selected="selected"' : '';
          $result .= sprintf(
            '<listitem image="%s" title="%s" indent="1" %s>'.LF,
            papaya_strings::escapeHTMLChars($this->images['items-mail']),
            papaya_strings::escapeHTMLChars(@$mailing['mailing_title']),
            $selected
          );
          $result .= sprintf(
            '<subitem><a href="%s"><glyph src="%s" alt="%s"/></a></subitem>'.LF,
            papaya_strings::escapeHTMLChars(
              $this->getLink(
                array(
                  'contentmode' => @$this->params['contentmode'],
                  'mailing_id' => $mailingId,
                  'mailinggroup_id' => @$this->params['mailinggroup_id'],
                  'cmd' => 'import'
                )
              )
            ),
            papaya_strings::escapeHTMLChars($this->images['actions-upload']),
            papaya_strings::escapeHTMLChars($this->_gt('Import'))
          );
          $result .= '</listitem>'.LF;
        }
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      return $result;
    }
  }

  /**
  * Imports a newsletter
  *
  * @access public
  */
  function importNewsletter() {
    $this->loadOneMailing(@$this->params['mailing_id']);
    $this->loadMailingContents(@$this->params['mailing_id']);
    $this->data['title'] = @$this->oneMailing['mailing_title'];
    $this->data['intro_nl2br'] = @$this->oneMailing['mailing_intro_nl2br'];
    $this->data['intro'] = @$this->oneMailing['mailing_intro'];
    $this->data['footer_nl2br'] = @$this->oneMailing['mailing_footer_nl2br'];
    $this->data['footer'] = @$this->oneMailing['mailing_footer'];
    $newNodes = NULL;
    $teaser = '';
    foreach ($this->contents as $content) {
      $newNodes[] = array(
        'nl2br'    => @$content['mailingcontent_nl2br'],
        'title'    => @$content['mailingcontent_title'],
        'subtitle' => @$content['mailingcontent_subtitle'],
        'text'     => @$content['mailingcontent_text'],
      );
      $teaser .= @$content['mailingcontent_title'];
      $teaser .= '<br />';
    }
    $node = &$this->getItemRef();
    $node = $newNodes;
    $node = array_values($node);
    $this->data['teaser'] = $teaser;
  }

  /**
  * Loads data for newsletter import
  *
  * @access public
  */
  function loadImportData() {
    $this->loadMailingGroups();
    $this->loadMailings();
  }

  /**
  * Loads a list containing all mailings.
  *
  * @access public
  * @return bool mailing groups loaded
  */
  function loadMailingGroups() {
    unset($this->mailingGroups);
    $sql = "SELECT mailinggroup_id, mailinggroup_title
              FROM %s
             ORDER BY mailinggroup_title";
    if ($res = $this->databaseQueryFmt($sql, $this->tableMailingGroups)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->mailingGroups[$row['mailinggroup_id']] = $row;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Loads a list containing all mailings.
  *
  * @access public
  * @return bool mailings loaded
  */
  function loadMailings() {
    unset($this->mailings);
    $sql = "SELECT mailing_id, mailinggroup_id, mailing_title
              FROM %s
          ORDER BY mailing_title";
    $params = array($this->tableMailings);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->mailings[$row['mailing_id']] = $row;
        if (isset($this->mailingGroups[$row['mailinggroup_id']])) {
          $this->mailingGroups[$row['mailinggroup_id']]['MAILINGS'][] = $row['mailing_id'];
        } else {
          $this->mailingGroups[-1]['MAILINGS'][] = $row['mailing_id'];
        }
      }
    }
    $sql = "SELECT COUNT(*) AS content_count, mailing_id
              FROM %s
             GROUP BY mailing_id";
    if ($res = $this->databaseQueryFmt($sql, $this->tableMailingContents)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        if (isset($this->mailings[$row['mailing_id']])) {
          $this->mailings[$row['mailing_id']]['contents'] = $row['content_count'];
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Loads one mailing in detail.
  *
  * @access public
  * @param  int  $mailingsId
  * @return bool one mailing loaded
  */
  function loadOneMailing($mailingsId) {
    unset($this->oneMailing);
    $sql = "SELECT mailing_id, mailinggroup_id, lng_id,
                   author_id, mailing_title, mailing_note,
                   mailing_intro, mailing_footer,
                   mailing_intro_nl2br, mailing_footer_nl2br
              FROM %s
             WHERE mailing_id = '%d'";
    $params = array($this->tableMailings, $mailingsId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->oneMailing = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Loads a list of mailing contents belonging to a specified mailing.
  *
  * @access public
  * @param  int  $mailingsId
  * @return bool mailing contents loaded
  */
  function loadMailingContents($mailingsId) {
    unset($this->contents);
    $sql = "SELECT mailingcontent_id, mailingcontent_pos,
                   mailingcontent_title, mailingcontent_subtitle,
                   mailingcontent_text, mailingcontent_nl2br
              FROM %s
             WHERE mailing_id = '%d'
             ORDER BY mailingcontent_pos ASC";
    $params = array($this->tableMailingContents, $mailingsId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $i = 0;
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->contents[$row['mailingcontent_id']] = $row;
      }
      $this->contentsCount = $res->count();
      return $this->contentsCount > 0;
    }
    return FALSE;
  }
}


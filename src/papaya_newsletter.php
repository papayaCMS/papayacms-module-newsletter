<?php
/**
* Newsletter - admin functionality
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
* @version $Id: papaya_newsletter.php 16 2014-02-21 16:41:58Z SystemVCS $
*/

/**
* Newsletter - base functionality
*/
require_once(dirname(__FILE__).'/export_xls.php');
require_once(dirname(__FILE__).'/base_newsletter.php');
require_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

/**
* Newsletter - general functionality
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class papaya_newsletter extends base_newsletter {

  var $oneMailing = NULL;
  var $oneMailingContent = NULL;
  var $mailings = NULL;
  var $mailingsCount = 0;
  var $mailingGroups = NULL;
  var $contents = NULL;
  var $contentsCount = 0;
  var $contentsOrder = NULL;
  var $outputModules = NULL;
  var $duplicates = array();
  var $tableModules = PAPAYA_DB_TBL_MODULES;
  var $csvImportLimit = 50;

  /**
  * number of subscribers per page
  * @var integer
  */
  var $subscribersPerPage = 20;

  /**
  * number of subscribers per page
  * @var integer
  */
  var $mailingsPerPage = 15;

  /**
  * number of entries to display as import preview
  * @var integer
  */
  var $importPreviewCount = 10;

  /**
  * List of available formats
  * @var array
  */
  var $subscriberFormats = NULL;

  /**
  * @var array $formatMapping maps possible values to formats
  */
  var $formatMapping = array(
    '0' => 0,
    'false' => 0,
    'Text' => 0,

    '1' => 1,
    'true' => 1,
    'HTML' => 1,
  );

  /**
  * List of duplicate handling options, set in constructor
  * @var array $dupsModes
  */
  var $dupsModes = array();

  /**
   * Bouncing Handler Object
   *
   * @var newsletter_bouncehandler
   */
  var $bounce = NULL;

  /**
   * @var string|base_dialog|base_msgdialog
   */
  private $importDialog = NULL;

  /**
  * Initialize parameters
  *
  * @access public
  */
  function initialize() {
    $this->initializeParams();
    $this->sessionParams = $this->getSessionValue($this->sessionParamName);
    $this->initializeSessionParam('mode');
    $this->initializeSessionParam('newsletter_list_id', array('filter', 'patt', 'status'));
    $this->initializeSessionParam('filter', array('patt', 'status'));
    $this->initializeSessionParam('patt', array('filter'));
    $this->initializeSessionParam('status', array('filter'));
    $this->initializeSessionParam('mailing_id');
    $this->initializeSessionParam('cache_id');
    $this->initializeSessionParam('mailinggroup_id', array('offset'));
    $this->initializeSessionParam('offset');
    $this->initializeSessionParam('mailingoutput_id');
    $this->initializeSessionParam('mailingoutput_mode');
    $this->initializeSessionParam('mailingqueue_mode', array('queue_offset'));
    $this->initializeSessionParam('queue_offset');
    $this->initializeSessionParam('subscriber_mode');
    $this->initializeSessionParam('import_newsletter_list_id');
    $this->initializeSessionParam('import_surfer_status');
    $this->initializeSessionParam('import_dups_mode');
    $this->setSessionValue($this->sessionParamName, $this->sessionParams);
    $this->dupsModes = array(
      'ignore' => $this->_gt('Ignore'),
      'update' => $this->_gt('Update'),
    );

    $this->initializeSessionParam('offset_bounce_mails');
    if (empty($this->params['offset_bounce_mails'])) {
      $this->params['offset_bounce_mails'] = 0;
    }
    $this->initializeSessionParam('limit_bounce_mails');
    if (empty($this->params['limit_bounce_mails'])) {
      $this->params['limit_bounce_mails'] = 20;
    }

    $this->bounceCategs = array(
      'bounce' => $this->_gt('Bounce email'),
      'regular' => $this->_gt('Regular email')
    );
  }

  /**
   * Processing command used by the bounce handler
   *
   * @author Jan Boerner <info@papaya-cms.com>
   */
  function processingBouncehandler() {
    include_once(dirname(__FILE__).'/newsletter_bouncehandler.php');
    $this->bounce = newsletter_bouncehandler::getInstance();
    $this->bounce->images = $this->images;
    $this->bounce->params = $this->params;
    $this->bounce->module = $this->module;
    $showMailOverview = TRUE;
    if (isset($this->params['bcmd'])) {
      switch ($this->params['bcmd']) {
      case 'mailcontent' :
        if (isset($this->params['mail_id'])) {
          $this->layout->add(
            $this->bounce->getXMLMailContentById($this->params['mail_id'])
          );
        }
        break;
      case 'processmails' :
        if ($this->bounce->processNewMails() !== FALSE) {
          $this->addMsg(MSG_INFO, $this->_gt('Mails rated.'));
        } else {
          $this->addMsg(MSG_INFO, $this->_gt('No emails to process.'));
        }
        break;
      case 'flag' :
        if (isset($this->params['flag']) && isset($this->params['mail_id'])) {
          if ($this->bounce->reCategorizeMail(
            $this->params['mail_id'], $this->params['flag']) !== FALSE) {
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('Category of undelivered email set to "%s".'),
                $this->bounceCategs[$this->params['flag']]
              )
            );
          } else {
            $this->addMsg(
              MSG_ERROR,
              $this->_gt('Change failed!')
            );
          }
        }
        break;
      case 'teachfilter' :
        if ($this->bounce->teachFilter() !== FALSE) {
          $this->addMsg(
            MSG_INFO,
            $this->_gt('Training successful.')
          );
        } else {
          $this->addMsg(
            MSG_ERROR,
            $this->_gt(
              'Error on training bounce handler.'.
              ' You need to mark some emails manually as bounces first.'
            )
          );
        }
        break;
      case 'blockedsubscribers' :
        $showMailOverview = FALSE;
        $result = $this->bounce->getXMLBlockedSubscribers();
        if (!$result) {
          $this->addMsg(MSG_INFO, $this->_gt('No users are currently blocked.'));
        } else {
          $this->layout->add($result);
        }
        break;
      case 'unblocksubscriber' :
        if (isset($this->params['sub_id'])) {
          if ($this->bounce->unblockSubscriber($this->params['sub_id']) !== FALSE) {
            $this->addMsg(MSG_INFO, $this->_gt('Subscriber activated.'));
          }
        }
        break;
      default :
        $this->addMsg(MSG_ERROR, $this->_gt('Command unknown.'));
        break;
      }
    }
    if ($showMailOverview) {
      $this->getXMLBounceForm();
    }

  }

  /**
  * Execute - basic function for handling parameters
  */
  function execute() {
    $this->loadData();
    if (!isset($this->params['mailingview_type']) ||
               $this->params['mailingview_type'] < 0 ||
               $this->params['mailingview_type'] > 1) {
      $this->params['mailingview_type'] = 1;
    }

    if (!isset($this->params['mailingview_mode'])) {
      $this->params['mailingview_mode'] = 1;
    }

    switch (@$this->params['cmd']) {
    case 'bounces' :
      if ($this->module->hasPerm(8)) {
        $this->processingBouncehandler();
      }
      break;
    case 'edit_subscriber' :
      if ($this->module->hasPerm(7)) {
        if ($this->loadSubscriber($this->params['subscriber_id'])) {
          $this->initializeSubscriberEditForm();
          if ($this->subscriberDialog->checkDialogInput()) {
            if ($this->saveSubscriber(
                  $this->params['subscriber_id'], $this->subscriberDialog->data
                )) {
              $this->addMsg(MSG_INFO, $this->_gt('Subscriber modified.'));
            } else {
              $this->addMsg(MSG_ERROR, $this->_gt('Database error! Changes not saved.'));
            }
          }
        }
      }
      break;
    case 'delete_subscriber' :
      if ($this->module->hasPerm(7)) {
        if (isset($this->params['confirm_delete']) && $this->params['confirm_delete']) {
          if (FALSE !== $this->deleteSubscriber($this->params['subscriber_id'])) {
            unset($this->subscriber);
            $this->params['cmd'] = NULL;
            $this->params['subscriber_id'] = '';
            $this->initializeSessionParam('subscriber_id', array('offset', 'cmd'));
            $this->addMsg(MSG_INFO, $this->_gt('Subscriber deleted.'));
          } else {
            $this->addMsg(MSG_ERROR, $this->_gt('Database error!'));
          }
        }
      }
      break;
    case 'delete_subscriptions' :
      if ($this->module->hasPerm(7)) {
        if (isset($this->params['confirm_delete']) && $this->params['confirm_delete']) {
          if (FALSE !== $this->deleteSubscriptions($this->params['newsletter_list_id'])) {
            $this->params['cmd'] = NULL;
            $this->params['subscriber_id'] = NULL;
            $this->initializeSessionParam('subscriber_id', array('offset', 'cmd'));
            $this->addMsg(MSG_INFO, $this->_gt('Subscriptions deleted.'));
          } else {
            $this->addMsg(MSG_ERROR, $this->_gt('Database error!'));
          }
        }
      }
      break;
    case 'export_list':
      if ($this->module->hasPerm(7)) {
        $this->exportSubscriptionList();
      }
      break;
    case 'export_data':
      if ($this->module->hasPerm(7)) {
        $this->exportSubscriptionList(TRUE);
      }
      break;
    case 'export_data_xls':
      if ($this->module->hasPerm(7)) {
        $this->exportSubscriptionListXls(TRUE);
      }
      break;
    case 'add_list':
      if ($this->module->hasPerm(7)) {
        $this->initializeNewsletterListForm(TRUE);
        if (isset($this->params['save']) &&
            $this->params['save'] &&
            $this->listDialog->checkDialogInput()) {
          if ($newId = $this->addNewsletterList($this->listDialog->data)) {
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('Mailing list "%s" (%d) added.'),
                $this->params['newsletter_list_name'],
                $newId
              )
            );
            unset($this->listDialog);
            $this->params['newsletter_list_id'] = $newId;
            $this->params['cmd'] = 'edit_list';
          }
        }
      }
      break;
    case 'edit_list':
      if ($this->module->hasPerm(7)) {
        $this->initializeNewsletterListForm(FALSE);
        if (@isset($this->params['save']) && $this->params['save'] &&
            $this->listDialog->checkDialogInput()) {
          $listId = $this->params['newsletter_list_id'];
          if ($this->saveNewsletterList($listId, $this->listDialog->data)) {
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('Mailing list "%s" (%d) updated.'),
                $this->params['newsletter_list_name'],
                $listId
              )
            );
          }
        }
      }
      break;
    case 'del_list':
      if ($this->module->hasPerm(7)) {
        if (isset($this->params['newsletter_list_id']) &&
            isset($this->newsletterLists[$this->params['newsletter_list_id']])) {
          if (isset($this->params['confirm_delete']) &&
              $this->params['confirm_delete'] != '') {
            $listId = $this->params['newsletter_list_id'];
            if ($this->deleteNewsletterList($listId)) {
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('List "%s" (%d) has been deleted.'),
                  $this->newsletterLists[$listId]['newsletter_list_name'],
                  $listId
                )
              );
            }
          }
        }
      }
      break;
    case 'edit_subscription' :
      if ($this->module->hasPerm(7)) {
        $this->initializeSubscriptionForm();
        if (@isset($this->params['save']) && $this->params['save'] &&
           $this->subscriptionDialog->checkDialogInput()) {
          if ($this->saveSubscription(
                $this->params['subscriber_id'],
                $this->params['newsletter_list_id'],
                $this->params['subscription_status'],
                $this->params['subscription_format'])) {
            $this->addMsg(MSG_INFO, $this->_gt('Subscription updated.'));
          }
        }
      }
      break;
    case 'new_mailinggroup' :
      if ($this->module->hasPerm(edmodule_newsletter::PERM_MANAGE_MAILINGS)) {
        $this->initializeMailingGroupForm(TRUE);
        if (@isset($this->params['save']) && $this->params['save'] &&
            $this->mailingGroupDialog->checkDialogInput()) {
          if ($newId = $this->addMailingGroup($this->mailingGroupDialog->data)) {
            $this->addMsg(MSG_INFO, $this->_gt('Newsletter added.'));
            $this->params['mailinggroup_id'] = $newId;
            $this->params['cmd'] = 'edit_mailinggroup';
            $this->initializeSessionParam('mailinggroup_id');
            unset($this->mailingGroupDialog);
          } else {
            $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
          }
        }
      }
      break;
    case 'edit_mailinggroup':
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['mailinggroup_id']) &&
            $this->params['mailinggroup_id'] > 0) {
          switch (@$this->params['content_type']) {
          case 'intro':
            $this->initializeMailingGroupContentForm('intro');
            if (@isset($this->params['save']) && $this->params['save'] &&
                $this->mailingGroupDialog->checkDialogInput()) {
              if ($this->saveMailingGroupIntro(
                   $this->params['mailinggroup_id'], $this->mailingGroupDialog->data
                 )) {
                $this->addMsg(MSG_INFO, $this->_gt('Newsletter updated.'));
              } else {
                $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
              }
            }
            break;
          case 'footer':
            $this->initializeMailingGroupContentForm('footer');
            if (@isset($this->params['save']) && $this->params['save'] &&
                $this->mailingGroupDialog->checkDialogInput()) {
              if ($this->saveMailingGroupFooter(
                    $this->params['mailinggroup_id'], $this->mailingGroupDialog->data
                  )) {
                $this->addMsg(MSG_INFO, $this->_gt('Newsletter updated.'));
              } else {
                $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
              }
            }
            break;
          case 'general' :
          default:
            $this->initializeMailingGroupForm();
            if (@isset($this->params['save']) && $this->params['save'] &&
                $this->mailingGroupDialog->checkDialogInput()) {
              if ($this->saveMailingGroup(
                    $this->params['mailinggroup_id'], $this->mailingGroupDialog->data
                  )) {
                $this->addMsg(MSG_INFO, $this->_gt('Newsletter updated.'));
              } else {
                $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
              }
            }
            break;
          }
        }
      }
      break;
    case 'del_mailinggroup' :
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['mailinggroup_id']) &&
            $this->params['mailinggroup_id'] > 0 &&
            isset($this->params['confirm_delete']) &&
            $this->params['confirm_delete']) {
          if ($this->deleteMailingGroup($this->params['mailinggroup_id'])) {
            $this->addMsg(MSG_INFO, $this->_gt('Newsletter deleted.'));
            $this->params['mailinggroup_id'] = 0;
            $this->params['cmd'] = '';
            $this->initializeSessionParam('mailinggroup_id');
          } else {
            $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
          }
        }
      }
      break;
    case 'new_mailing':
      if ($this->isEditableMailingGroup()) {
        if (isset($this->mailingGroups) && is_array($this->mailingGroups) &&
            count($this->mailingGroups) > 0) {
          $this->initializeMailingForm(TRUE);
          if (isset($this->params['save']) && $this->params['save'] &&
              $this->mailingDialog->checkDialogInput()) {
            if ($newId = $this->addMailing($this->mailingDialog->data)) {
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('Mailing "%s" (%d) added.'),
                  $this->params['mailing_title'],
                  $newId
                )
              );
              unset($this->params['mailingcontent_id']);
              unset($this->params['mailingoutput_id']);
              $this->params['mailing_id'] = $newId;
              $this->params['cmd'] = 'edit_mailing';
              unset($this->mailingDialog);
            }
          }
        } else {
          $this->addMsg(MSG_ERROR, $this->_gt('No newsletters found.'));
        }
      }
      break;
    case 'copy_mailing' :
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['mailing_id']) &&
            isset($this->oneMailing) && is_array($this->oneMailing) &&
            isset($this->params['confirm_copy']) && $this->params['confirm_copy']) {
          $this->intializeMailingCopyForm();
          if ($this->mailingDialog->checkDialogInput()) {
            if ($newId = $this->copyMailing($this->mailingDialog->data)) {
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('Mailing "%s" (%d) added.'),
                  $this->params['mailing_title'],
                  (int)$newId
                )
              );
              unset($this->params['mailingcontent_id']);
              unset($this->params['mailingoutput_id']);
              $this->params['mailing_id'] = $newId;
              $this->params['cmd'] = 'edit_mailing';
              unset($this->mailingDialog);
            } else {
              $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
            }
          }
        }
      }
      break;
    case 'edit_mailing':
      if ($this->isEditableMailingGroup()) {
        $this->initializeMailingForm();
        if (@isset($this->params['save']) && $this->params['save'] &&
            $this->mailingDialog->checkDialogInput()) {
          if ($this->saveMailing(
                $this->params['mailing_id'], $this->mailingDialog->data
              )) {
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('Mailing "%s" (%d) updated.'),
                $this->params['mailing_title'],
                $this->params['mailing_id']
              )
            );
          } else {
            $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
          }
        }
      }
      break;
    case 'edit_content':
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['mailingcontent_id'])) {
          $this->initializeMailingContentForm();
          if (@isset($this->params['save']) && $this->params['save'] &&
              $this->mailingContentDialog->checkDialogInput()) {
            if ($this->saveMailingContent(
                  $this->params['mailingcontent_id'], $this->mailingContentDialog->data
                )) {
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('Mailing Content "%s" (%d) updated.'),
                  $this->mailingContentDialog->data['mailingcontent_title'],
                  (int)$this->params['mailingcontent_id']
                )
              );
              $this->fixMailingContentPositions();
            } else {
              $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
            }
          }
        } elseif (isset($this->params['content_type'])) {
          switch ($this->params['content_type']) {
          case 'intro':
            $this->initializeMailingIntroForm();
            if (@isset($this->params['save']) && $this->params['save'] &&
                $this->mailingDialog->checkDialogInput()) {
              if ($this->saveMailingIntro(
                    $this->params['mailing_id'], $this->mailingDialog->data
                  )) {
                $this->addMsg(
                  MSG_INFO,
                  sprintf(
                    $this->_gt('Mailing #%d updated.'), $this->params['mailing_id']
                  )
                );
              } else {
                $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
              }
            }
            break;
          case 'footer':
            $this->initializeMailingFooterForm();
            if (@isset($this->params['save']) && $this->params['save'] &&
                $this->mailingDialog->checkDialogInput()) {
              if ($this->saveMailingFooter(
                    $this->params['mailing_id'], $this->mailingDialog->data
                  )) {
                $this->addMsg(
                  MSG_INFO,
                  sprintf(
                    $this->_gt('Mailing #%d updated.'), $this->params['mailing_id']
                  )
                );
              } else {
                $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
              }
            }
            break;
          }
        }
      }
      break;
    case 'del_mailing' :
    case 'del_mailing_older' :
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['mailing_id'])
            && isset($this->oneMailing)
            && is_array($this->oneMailing)
            && isset($this->params['confirm_delete'])
            && $this->params['confirm_delete']) {
          $title = $this->oneMailing['mailing_title'];
          $dateLimit = $this->oneMailing['mailing_created'];
          $groupId = $this->oneMailing['mailinggroup_id'];
          switch ($this->params['cmd']) {
          case 'del_mailing' :
            if ($this->deleteMailing($this->params['mailing_id'])) {
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('Mailing "%s" (%d) has been deleted.'),
                  $title,
                  $this->params['mailing_id']
                )
              );
              unset($this->sessionParams['mailing_id']);
              unset($this->params['mailing_id']);
              $this->setSessionValue($this->sessionParamName, $this->sessionParams);
            } else {
              $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
            }
            break;
          case 'del_mailing_older' :
            if (FALSE !== ($counter = $this->deleteMailingOlderThan($groupId, $dateLimit))) {
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('Mailings older then "%s" have been deleted. Deleted Mailings: %d'),
                  date('Y-m-d H:i:s', $dateLimit),
                  $counter
                )
              );
              $this->params['cmd'] = 'edit_mailing';
              $this->setSessionValue($this->sessionParamName, $this->sessionParams);
            } else {
              $this->addMsg(MSG_ERROR, $this->_gt('Database error.'));
            }
            break;
          }
        }
      }
      break;
    case 'del_content':
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['mailingcontent_id'])
          && isset($this->contents[$this->params['mailingcontent_id']])) {
          if (@$this->params['confirm_delete']) {
            $contentId = $this->params['mailingcontent_id'];
            if (FALSE !== $this->databaseDeleteRecord(
                  $this->tableMailingContents, array('mailingcontent_id' => $contentId)
                )) {
              unset($this->sessionParams['mailingcontent_id']);
              $this->setSessionValue($this->sessionParamName, $this->sessionParams);
              $this->addMsg(
                MSG_INFO,
                sprintf(
                  $this->_gt('Mailing content "%s" (%d) has been deleted.'),
                  $this->contents[$contentId]['mailingcontent_title'],
                  $this->params['mailingcontent_id']
                )
              );
              $this->fixMailingContentPositions();
            }
          }
        }
      }
      break;
    case 'content_up':
      if ($this->isEditableMailingGroup()) {
        $this->swapTwoContentPositions(
          (int)$this->params['contentlist_pos'],
          --$this->params['contentlist_pos'],
          $this->params['mailingcontent_id']
        );
        $this->fixMailingContentPositions();
      }
      break;
    case 'content_down':
      if ($this->isEditableMailingGroup()) {
        $this->swapTwoContentPositions(
          (int)$this->params['contentlist_pos'],
          ++$this->params['contentlist_pos'],
          $this->params['mailingcontent_id']
        );
        $this->fixMailingContentPositions();
      }
      break;
    case 'new_output':
      if ($this->isEditableMailingGroup()) {
        $this->initializeMailingOutputForm(TRUE);
        $this->params['mailingoutput_mode'] = 0;
        unset($this->params['mailingcontent_id']);
        unset($this->params['mailingoutput_id']);
        if (isset($this->params['save']) && $this->params['save']
            && $this->mailingOutputDialog->checkDialogInput()) {
          if ($newId = $this->addMailingOutput()) {
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('Mailing output (%d) added.'),
                $newId
              )
            );
            $this->params['mailingoutput_id'] = $newId;
            $this->params['cmd'] = 'edit_output';
            unset($this->mailingOutputDialog);
          }
        }
      }
      break;
    case 'del_output':
      if ($this->isEditableMailingGroup()) {
        $this->delMailingOutput();
      }
      break;
    case 'edit_output':
      if ($this->isEditableMailingGroup()) {
        $this->editMailingOutput();
      }
      break;
    case 'new_content':
      if ($this->isEditableMailingGroup()) {
        $this->addMailingContent();
      }
      break;
    case 'parse_mailing':
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['confirmation']) && $this->params['confirmation']) {
          $this->parseMailingOutput($this->params['mailingoutput_id']);
        } else {
          switch($this->params['mailingoutput_mode']) {
          case 1:
            if ($this->oneMailingOutput['mailingoutput_text_status'] == 2) {
              $this->getXMLParseMailingOutputForm();
            } else {
              $this->parseMailingOutput($this->params['mailingoutput_id']);
            }
            break;
          case 2:
            if ($this->oneMailingOutput['mailingoutput_html_status'] == 2) {
              $this->getXMLParseMailingOutputForm();
            } else {
              $this->parseMailingOutput($this->params['mailingoutput_id']);
            }
            break;
          }
        }
      }
      break;
    case 'new_view':
      if ($this->module->hasPerm(2)) {
        $this->addMailingView();
      }
      break;
    case 'edit_view':
      if ($this->module->hasPerm(2)) {
        $this->editMailingView();
      }
      break;
    case 'del_view':
      if ($this->module->hasPerm(2)) {
        $this->delMailingView();
      }
      break;
    case 'edit_viewproperties':
      if ($this->module->hasPerm(2)) {
        $this->editMailingViewProperties($this->params['mailingview_id']);
      }
      break;
    case 'output_mode':
      if ($this->params['mode'] >= 1 && $this->params['mode'] <= 5) {
        if ($this->params['mode'] == 3 && !$this->module->hasPerm(2)) {
          $this->params['mode'] = 0;
        } elseif ($this->params['mode'] == 4 && !$this->module->hasPerm(3)) {
          $this->params['mode'] = 0;
        } else {
          $this->params['mode'] = (int)$this->params['mode'];
        }
      } else {
        $this->params['mode'] = 0;
      }
      unset($this->sessionParams['mailing_id']);
      unset($this->sessionParams['mailingoutput_id']);
      $this->setSessionValue($this->sessionParamName, $this->sessionParams);
      break;
    case 'fill_queue' :
      if ($this->isEditableMailingGroup()) {
        if (empty($this->params['mailing_format'])) {
          $this->params['newsletter_list_id'] =
            $this->oneMailingOutput['mailingoutput_subscribers'];
          $this->params['mailing_format'] = 'all';
        }
        $dialog = $this->getFillQueueConfirmationDialog();
        if ($dialog->execute() &&
            $dialog->data()->get('mailing_format', 'all') != '' &&
            $dialog->data()->get('newsletter_list_id', 0) > 0 &&
            $dialog->data()->get('mailingoutput_id', 0) > 0) {
          if ($this->addToQueue(
                $dialog->data()->get('newsletter_list_id', 0),
                $dialog->data()->get('mailingoutput_id', 0),
                $dialog->data()->get('mailing_format', 'all'),
                $dialog->data()->get('schedule_for', 0)
              )) {
            unset($this->params['newsletter_list_id']);
          }
        }
      }
      break;
    case 'clear_queue' :
      if ($this->module->hasPerm(3)) {
        if (isset($this->params['confirm_clear_queue']) &&
            $this->params['confirm_clear_queue'] &&
            isset($this->params['mailingqueue_mode'])) {
          if ($this->deleteQueue(@(int)$this->params['mailingqueue_mode'])) {
            $this->addMsg(MSG_INFO, $this->_gt('Emails deleted.'));
            unset($this->params['cmd']);
          }
        }
      }
      break;
    case 'process_queue_rpc' :
      if ($this->module->hasPerm(3)) {
        $this->getProcessQueueRPCXML();
      }
      break;
    case 'send_testmail' :
      if ($this->isEditableMailingGroup()) {
        if (isset($this->params['confirm_sendmail']) &&
            checkit::isEmail(@$this->params['subscriber_email'], TRUE) &&
            @$this->params['mailingoutput_id']) {
          $this->loadOneMailingOutput($this->params['mailingoutput_id']);
          $this->initializeTestMailDialog();
          if (isset($this->oneMailingOutput) &&
              $this->dialogTestMail->checkDialogInput()) {
            if ($this->sendTestMail()) {
              $this->addMsg(MSG_INFO, $this->_gt('Test email sent.'));
            } else {
              $this->addMsg(MSG_INFO, $this->_gt('Could not send test email.'));
            }
          }
        }
      }
      break;
    case 'import_csv' :
      if ($this->module->hasPerm(7)) {
        $this->processCSVUpload();
      }
      break;
    case 'add_surfers' :
      if ($this->module->hasPerm(7)) {
        $this->addSurfersToList();
      }
      break;
    }
    $this->loadData();

    if (isset($this->params['mailingcontent_id'])) {
      unset($this->params['mailingoutput_id']);
    }

    $this->setSessionValue($this->sessionParamName, $this->sessionParams);
  }

  /**
  * Service method called by execute to load data from database
  * depending on application mode.
  *
  */
  function loadData() {
    if (isset($this->params['mailingoutput_id'])) {
      $this->loadOneMailingOutput($this->params['mailingoutput_id']);
    }
    if (!isset($this->params['mode'])) {
      $this->params['mode'] = 1;
    }
    switch ($this->params['mode']) {
    case 900:
      break;
    case 1 :
      $this->loadMailingGroups();
      $this->loadLanguages();
      if (!empty($this->params['mailinggroup_id'])) {
        $this->loadMailings(
          $this->params['mailinggroup_id'],
          $this->mailingsPerPage,
          empty($this->params['offset']) ? 0 : (int)$this->params['offset']
        );
        if (isset($this->params['mailing_id']) && $this->params['mailing_id'] > 0) {
          $this->loadMailingContents($this->params['mailing_id']);
          $this->loadMailingOutputs($this->params['mailing_id']);
          $this->loadMailingViews();
          if (!empty($this->oneMailing)) {
            $this->loadOneMailingGroup($this->oneMailing['mailinggroup_id']);
          }
        }
        if (isset($this->params['mailingcontent_id']) &&
            $this->params['mailingcontent_id'] > 0) {
          $this->loadOneMailingContent($this->params['mailingcontent_id']);
        } elseif (isset($this->params['mailing_id']) &&
                  $this->params['mailing_id'] > 0) {
          $this->loadOneMailing($this->params['mailing_id']);
        } elseif (isset($this->params['mailinggroup_id']) &&
                  $this->params['mailinggroup_id'] > 0) {
          $this->loadMailingViews();
          $this->loadOneMailingGroup($this->params['mailinggroup_id']);
        } elseif (isset($this->params['mailinggroup_id']) &&
                    $this->params['mailinggroup_id'] == 0) {
          $this->loadMailingViews();
        }
      }

      $this->loadNewsletterLists();
      break;
    case 3 :
      $this->loadMailingViews();
      $this->loadOutputModulesList();
      if (isset($this->params['mailingview_id'])) {
        $this->loadOneMailingView($this->params['mailingview_id']);
      }
      if (isset($this->params['mailingoutput_id'])) {
        $this->loadOneMailingOutput($this->params['mailingoutput_id']);
      }
      break;
    case 4 :
      $this->loadQueue(
        20,
        @(int)$this->params['queue_offset'],
        (@$this->params['mailingqueue_mode'] > 0),
        TRUE
      );
      break;
    case 0 :
    default :
      $this->params['mode'] = 0;
      if (isset($this->params['newsletter_list_id']) &&
          $this->params['newsletter_list_id'] > 0) {
        $this->loadSubscriberList($this->params['newsletter_list_id']);
        $this->loadNewsletterList($this->params['newsletter_list_id']);
        $this->loadMailingViews();
      } else {
        $this->loadSubscriberList();
      }
      if (isset($this->params['subscriber_id']) && $this->params['subscriber_id'] > 0) {
        $this->loadSubscriber($this->params['subscriber_id']);
        $this->loadSubscriptions($this->params['subscriber_id']);
        $this->loadProtocol(
          $this->params['subscriber_id'], $this->params['newsletter_list_id']
        );
      }
      $this->loadNewsletterLists();
      $this->loadLanguages();
      break;
    }
  }

  /**
  * Load subscription protocol
  *
  * @param string $email
  * @access public
  */
  function loadProtocol($subscriberId) {
    $filter = array(
      'subscriber_id' => $subscriberId,
      'protocol_action' => array(2, 3),
      'protocol_confirmed' => 0,
    );
    $expire = time() - ($this->tokenExpireDays * 86400) -
      ($this->tokenExpireHours * 3600);
    $sql = 'DELETE FROM '.$this->escapeStr($this->tableProtocol).' WHERE '.
      $this->databaseGetSQLCondition($filter).' AND protocol_created < '.$expire;
    $this->databaseQueryWrite($sql);
    $sql = "SELECT protocol_id, subscriber_id, newsletter_list_id,
                   protocol_created, protocol_confirmed, protocol_action
              FROM %s
             WHERE subscriber_id = '%d'
               AND protocol_action <> -1
          ORDER BY newsletter_list_id, protocol_created, protocol_confirmed";
    $params = array($this->tableProtocol, $subscriberId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->protocol[$row['newsletter_list_id']][$row['protocol_id']] = $row;
      }
    }
  }

  /**
  * Loads a list of output modules.
  */
  function loadOutputModulesList() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_viewlist.php');
    $viewList = new base_viewlist;
    $viewList->loadOutputModulesList();

    unset($this->outputModules);
    foreach ($viewList->outputModules as $module) {
      $this->outputModules[$module['module_guid']] = $module['module_title'];
    }
  }

  /**
   * Loads available mailing groups
   *
   * @return boolean TRUE, if any mailing group was found, else FALSE
   */
  function loadMailingGroups() {
    unset($this->mailingGroups);
    $sql = "SELECT mailinggroup_id, mailinggroup_title, mailinggroup_editor_group
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
  */
  function loadMailings($groupId, $limit = NULL, $offset = 0) {
    $this->mailings = array();
    $this->mailingsCount = 0;
    $sql = "SELECT mailing_id, mailinggroup_id, mailing_title, mailing_url,
                   mailing_created, mailing_modified, unsubscribe_url,
                   mailing_protected
              FROM %s
             WHERE mailinggroup_id = '%d'
          ORDER BY mailing_created DESC, mailing_title ASC";
    $params = array($this->tableMailings, $groupId);
    if ($res = $this->databaseQueryFmt($sql, $params, $limit, $offset)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->mailings[$row['mailing_id']] = $row;
        if (isset($this->mailingGroups[$row['mailinggroup_id']])) {
          $this->mailingGroups[$row['mailinggroup_id']]['MAILINGS'][] =
            $row['mailing_id'];
        }
      }
      $this->mailingsCount = $res->absCount();
    }
    $filter = str_replace(
      '%', '%%', $this->databaseGetSqlCondition('mailing_id', array_keys($this->mailings))
    );
    $sql = "SELECT COUNT(*) AS content_count, mailing_id
              FROM %s
             WHERE $filter
             GROUP BY mailing_id";
    if ($res = $this->databaseQueryFmt($sql, $this->tableMailingContents)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        if (isset($this->mailings[$row['mailing_id']])) {
          $this->mailings[$row['mailing_id']]['contents'] = $row['content_count'];
        }
      }
    }
  }

  /**
  * Loads a list of all currently known languages.
  *
  * @access public
  */
  function loadLanguages() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_language_select.php');
    $this->lngSelect = base_language_select::getInstance();
    unset($this->languages);
    $this->languages = array();
    foreach ($this->lngSelect->languages as $id => $lng) {
      $this->languages[$id] = $lng['lng_title'];
    }
  }

  /**
  * Loads a list of mailing contents belonging to a specified mailing.
  *
  * @access public
  * @param  bool $details (on/off)
  * @param  int  $mailingsId
  * @return boolean|integer Number of mainling contents, else FALSE
  */
  function loadMailingContents($mailingsId, $details = FALSE) {
    unset($this->contents);
    unset($this->contentsOrder);
    if ($details) {
      $sql = "SELECT mailingcontent_id, mailingcontent_pos,
                     mailingcontent_title, mailingcontent_subtitle,
                     mailingcontent_text, mailingcontent_nl2br
                FROM %s
               WHERE mailing_id = '%d'
               ORDER BY mailingcontent_pos ASC";
    } else {
      $sql = "SELECT mailingcontent_id, mailingcontent_pos, mailingcontent_title
                FROM %s
               WHERE mailing_id = '%d'
               ORDER BY mailingcontent_pos ASC";
    }
    $params = array($this->tableMailingContents, $mailingsId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $i = 0;
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->contents[$row['mailingcontent_id']] = $row;
        $this->contentsOrder[++$i] = $row['mailingcontent_id'];
      }
      $this->contentsCount = $res->count();
      return $this->contentsCount > 0;
    }
    return FALSE;
  }

  /**
  * Load a list of all mailing views and how ofter they are used
  *
  * @access public
  * @return boolean
  */
  function loadMailingViews() {
    unset($this->views);
    $sql = "SELECT mv.mailingview_id, mv.mailingview_title,
                   mv.mailingview_type,
                   COUNT(mo1.mailingoutput_text_view) AS count_text,
                   COUNT(mo2.mailingoutput_html_view) AS count_html
              FROM %s AS mv
              LEFT OUTER JOIN %s AS mo1
                ON (mo1.mailingoutput_text_view = mv.mailingview_id)
              LEFT OUTER JOIN %s AS mo2
                ON (mo2.mailingoutput_html_view = mv.mailingview_id)
             GROUP BY mv.mailingview_id, mv.mailingview_title, mv.mailingview_type
             ORDER BY mailingview_title ASC";
    $params = array(
      $this->tableMailingView,
      $this->tableMailingOutput,
      $this->tableMailingOutput
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $row['used_times'] = $row['count_text'] + $row['count_html'];
        $this->views[$row['mailingview_id']] = $row;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Loads a list of mailing outputs belonging to a specified mailing.
  *
  * @access public
  * @param  int $mailingsId
  * @param  int $mailingOutputId
  * @return boolean
  */
  function loadMailingOutputs($mailingsId, $mailingOutputId = NULL) {
    if (isset($mailingOutputId)) {
      unset($this->outputs[$mailingOutputId]);
      $sql = "SELECT o.mailingoutput_id, o.mailingoutput_title, o.mailingoutput_subject,
                     o.mailing_id, m.mailing_title,
                     o.mailingoutput_sender, o.mailingoutput_sendermail,
                     o.mailingoutput_subscribers,
                     o.mailingoutput_text_status, o.mailingoutput_text_view,
                     o.mailingoutput_html_status, o.mailingoutput_html_view
              FROM %s AS o, %s AS m
              WHERE m.mailing_id = o.mailing_id
                AND o.mailingoutput_id = '%d'";
      $params = array($this->tableMailingOutput, $this->tableMailings, $mailingOutputId);
    } else {
      unset($this->outputs);
      $sql = "SELECT o.mailingoutput_id, o.mailingoutput_title, o.mailingoutput_subject,
                     o.mailing_id, m.mailing_title,
                     o.mailingoutput_sender, o.mailingoutput_sendermail,
                     o.mailingoutput_subscribers,
                     o.mailingoutput_text_status, o.mailingoutput_text_view,
                     o.mailingoutput_html_status, o.mailingoutput_html_view
              FROM %s AS o, %s AS m
              WHERE m.mailing_id = o.mailing_id
                AND o.mailing_id = '%d'
              ORDER BY o.mailingoutput_id ASC";
      $params = array($this->tableMailingOutput, $this->tableMailings, $mailingsId);
    }
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->outputs[$row['mailingoutput_id']] = $row;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
  * load mailing group details
  *
  * @param $mailingGroupId
  * @access public
  * @return boolean
  */
  function loadOneMailingGroup($mailingGroupId) {
    unset($this->oneMailingGroup);
    $sql = "SELECT mailinggroup_id, mailinggroup_title, lng_id,
                   mailinggroup_default_subject,
                   mailinggroup_default_sender, mailinggroup_default_senderemail,
                   mailinggroup_default_subscribers,
                   mailinggroup_mode, mailinggroup_editor_group,
                   mailinggroup_default_textview, mailinggroup_default_htmlview,
                   mailinggroup_default_intro, mailinggroup_default_footer,
                   mailinggroup_default_intro_nl2br, mailinggroup_default_footer_nl2br,
                   mailinggroup_default_archive_url, mailinggroup_default_unsubscribe_url
              FROM %s
             WHERE mailinggroup_id = %d";
    $params = array($this->tableMailingGroups, $mailingGroupId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->oneMailingGroup = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Loads one mailing in detail.
  *
  * @access public
  * @param  int  $mailingsId
  * @return boolean
  */
  function loadOneMailing($mailingsId) {
    unset($this->oneMailing);
    $sql = "SELECT mailing_id, mailinggroup_id, lng_id, author_id,
                   mailing_title, mailing_note,
                   mailing_url, unsubscribe_url, mailing_intro, mailing_footer,
                   mailing_intro_nl2br, mailing_footer_nl2br,
                   mailing_created, mailing_modified, mailing_protected
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
  * Loads a specified mailing content.
  *
  * @access public
  * @param  int $mailingcontentId
  * @return boolean
  */
  function loadOneMailingContent($mailingcontentId) {
    unset($this->oneMailingContent);
    $sql = "SELECT mailingcontent_id, mailing_id, mailingcontent_pos,
                   mailingcontent_title, mailingcontent_subtitle,
                   mailingcontent_text, mailingcontent_nl2br
              FROM %s
             WHERE mailingcontent_id = '%d'";
    $params = array($this->tableMailingContents, $mailingcontentId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->oneMailingContent = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Loads a specified mailing view.
  *
  * @access public
  * @param  int $mailingViewId
  * @return boolean
  */
  function loadOneMailingView($mailingViewId) {
    unset($this->oneMailingView);
    $sql = "SELECT mailingview_id, mailingview_conf,
                   mailingview_title, mailingview_type
              FROM %s
             WHERE mailingview_id = '%d'";
    $params = array($this->tableMailingView, $mailingViewId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->oneMailingView = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Load subscriptions for export
  *
  * @access public
  * @param  integer $mailingListId
  * @param  boolean $details
  */
  function loadSubscriptionsListForExport($newsletterListId, $details) {
    unset($this->subscriptionsExport);
    if ($details) {
      $sql = "SELECT sr.subscriber_id, sr.subscriber_email,
                     sr.subscriber_salutation, sr.subscriber_title,
                     sr.subscriber_firstname, sr.subscriber_lastname,
                     sr.subscriber_branch, sr.subscriber_company,
                     sr.subscriber_position, sr.subscriber_section,
                     sr.subscriber_street, sr.subscriber_housenumber,
                     sr.subscriber_postalcode, sr.subscriber_city,
                     sr.subscriber_phone, sr.subscriber_mobile,
                     sr.subscriber_fax,
                     sn.subscription_status, sn.subscription_format,
                     sp.protocol_created, sp.protocol_confirmed
                FROM %s AS sn
               INNER JOIN %s AS sr
                   ON sn.subscriber_id = sr.subscriber_id
                 LEFT JOIN %s as sp
                   ON sn.subscriber_id = sp.subscriber_id
                  AND sp.protocol_action = 0
                WHERE sn.newsletter_list_id = '%d'
            ORDER BY sr.subscriber_firstname, sr.subscriber_lastname,
                     sr.subscriber_email";
    } else {
      $filter = $this->databaseGetSQLCondition(
        'sn.subscription_status', $this->activeStatus
      );
      $sql = "SELECT sr.subscriber_id, sr.subscriber_email,
                     sr.subscriber_salutation,
                     sr.subscriber_firstname, sr.subscriber_lastname,
                     sn.subscription_status, sn.subscription_format
                FROM %s AS sn
               INNER JOIN %s AS sr
                  ON sn.subscriber_id = sr.subscriber_id
                LEFT JOIN %s as sp
                  ON sn.subscriber_id = sp.subscriber_id
                 AND sp.protocol_action = 0
               WHERE sn.newsletter_list_id = '%d'
                 AND sn.subscriber_id = sr.subscriber_id
                 AND $filter
            ORDER BY sr.subscriber_firstname, sr.subscriber_lastname,
                     sr.subscriber_email";
    }
    $params = array(
      $this->tableSubscriptions,
      $this->tableSubscribers,
      $this->tableProtocol,
      $newsletterListId
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->subscriptionsExport[$row['subscriber_id']] = $row;
      }
    }
  }

  /**
  * Load subscribers list
  *
  * @access public
  * @param  int $mailingListId
  */
  function loadSubscriberList($newsletterListId = 0) {
    $this->subscribers = array();
    if (!empty($this->params['patt'])) {
      if (FALSE === strpos($this->params['patt'], '*') &&
          FALSE === strpos($this->params['patt'], '?')) {
        $this->params['patt'] = '*'.$this->params['patt'].'*';
      }
      $replaceChars = array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_');
      $pattern = strtr($this->params['patt'], $replaceChars);
    } else {
      $pattern = '%';
    }
    if ($pattern != '%') {
      $emailFilter = sprintf(
        " AND (sr.subscriber_email LIKE '%1\$s' OR
               sr.subscriber_lastname LIKE '%1\$s' OR
               sr.subscriber_firstname LIKE '%1\$s')",
        $this->escapeStr($pattern)
      );
    } else {
      $emailFilter = '';
    }
    if (isset($this->params['status']) && $this->params['status'] >= 0) {
      $statusFilter = ' AND '.$this->databaseGetSQLCondition(
        'sn.subscription_status', (int)$this->params['status']
      );
    } else {
      $statusFilter = '';
    }
    $emailFilter = str_replace('%', '%%', $emailFilter);
    $statusFilter = str_replace('%', '%%', $statusFilter);
    if ($newsletterListId > 0) {
      $sql = "SELECT sr.subscriber_id, sr.subscriber_email,
                     sr.subscriber_firstname, sr.subscriber_lastname,
                     sn.subscription_status, sn.subscription_format
                FROM %s AS sn, %s AS sr
               WHERE sn.newsletter_list_id = '%d'
                 AND sn.subscriber_id = sr.subscriber_id
                     $statusFilter
                     $emailFilter
            ORDER BY sr.subscriber_email, sr.subscriber_lastname,
                     sr.subscriber_firstname";
      $params = array(
        $this->tableSubscriptions, $this->tableSubscribers, $newsletterListId
      );
    } else {
      $sql = "SELECT sr.subscriber_id, sr.subscriber_email,
                     sr.subscriber_firstname, sr.subscriber_lastname
                FROM %s AS sr
               WHERE 1=1 $emailFilter
            ORDER BY sr.subscriber_email, sr.subscriber_lastname,
                     sr.subscriber_firstname";
      $params = array(
        $this->tableSubscribers
      );
    }
    $res = $this->databaseQueryFmt(
      $sql, $params, @(int)$this->subscribersPerPage, @(int)$this->params['offset']
    );
    if ($res) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->subscribers[$row['subscriber_id']] = $row;
      }
      $this->subscribersCount = $res->absCount();
    }
  }

  /**
  * Add mailing group (newsletter) to database
  *
  * @param array $values
  * @return integer
  */
  function addMailingGroup($values) {
    $data = array(
      'mailinggroup_title' =>
        (string)$values['mailinggroup_title'],
      'lng_id' =>
        (int)$values['lng_id'],
      'mailinggroup_default_subject' =>
        (string)$values['mailinggroup_default_subject'],
      'mailinggroup_default_sender' =>
        (string)$values['mailinggroup_default_sender'],
      'mailinggroup_default_senderemail' =>
        (string)$values['mailinggroup_default_senderemail'],
      'mailinggroup_mode' =>
        (int)$values['mailinggroup_mode'],
      'mailinggroup_default_textview' =>
        (int)$values['mailinggroup_default_textview'],
      'mailinggroup_default_htmlview' =>
        (int)$values['mailinggroup_default_htmlview'],
      'mailinggroup_default_intro' => '',
      'mailinggroup_default_footer' => '',
      'mailinggroup_default_archive_url' => '',
      'mailinggroup_default_unsubscribe_url' => '',
      'mailinggroup_default_subscribers' => 0

    );
    return $this->databaseInsertRecord(
      $this->tableMailingGroups, 'mailinggroup_id', $data
    );
  }

  /**
  * Add a new mailing.
  *
  * @param array values input values
  * @access public
  * @return mixed new mailing id or FALSE
  */
  function addMailing($values, $onlyWithContent = FALSE) {
    $time = time();
    $data = array(
      'mailing_title' => @(string)$values['mailing_title'],
      'mailing_note' => @(string)$values['mailing_note'],
      'author_id' => @(string)$this->authUser->userId,
      'lng_id' => @(int)$values['lng_id'],
      'mailing_created' => $time,
      'mailing_modified' => $time,
      'mailing_protected' => 0
    );
    $mailingGroupId = (int)$values['mailinggroup_id'];
    $group = @$this->oneMailingGroup;
    if (isset($this->oneMailingGroup)) {
      $data['mailinggroup_id'] = $mailingGroupId;
      $data['mailing_intro'] = @(string)$group['mailinggroup_default_intro'];
      $data['mailing_intro_nl2br'] = @(int)$group['mailinggroup_default_intro_nl2br'];
      $data['mailing_footer'] = @(string)$group['mailinggroup_default_footer'];
      $data['mailing_footer_nl2br'] = @(int)$group['mailinggroup_default_footer_nl2br'];
      $data['mailing_url'] = @(string)$group['mailinggroup_default_archive_url'];
      $data['unsubscribe_url'] = @(string)$group['mailinggroup_default_unsubscribe_url'];

      $content = $this->getMailingContentByFeeds($mailingGroupId);
      if (!$onlyWithContent || !empty($content)) {
        $mailingId = $this->databaseInsertRecord($this->tableMailings, 'mailing_id', $data);
        if ($mailingId !== FALSE) {
          foreach ($content as $index => $element) {
            $element['mailing_id'] = $mailingId;
            $element['mailingcontent_pos'] = $index;
            $this->databaseInsertRecord(
              $this->tableMailingContents, 'mailingcontent_id', $element
            );
          }
          return $mailingId;
        } else {
          return FALSE;
        }
      } else {
        return NULL;
      }
    }
    return FALSE;
  }

  /**
   * Copy existing mailing with all data into a new mailing
   *
   * @param array $values
   * @return integer
   */
  function copyMailing($values) {
    $this->loadOneMailingGroup($values['mailinggroup_id']);
    $time = time();
    $data = array(
      'mailing_title' => @(string)$values['mailing_title'],
      'mailing_url' => @(string)$this->oneMailingGroup['mailinggroup_default_archive_url'],
      'unsubscribe_url' => @(string)$this->oneMailingGroup['mailinggroup_default_unsubscribe_url'],
      'mailing_note' => @(string)$values['mailing_note'],
      'author_id' => @(string)$this->authUser->userId,
      'lng_id' => @(int)$values['lng_id'],
      'mailinggroup_id' => @(int)$values['mailinggroup_id'],
      'mailing_intro' => @(string)$this->oneMailingGroup['mailinggroup_default_intro'],
      'mailing_intro_nl2br' => @(int)$this->oneMailingGroup['mailinggroup_default_intro_nl2br'],
      'mailing_footer' => @(string)$this->oneMailingGroup['mailinggroup_default_footer'],
      'mailing_footer_nl2br' => @(int)$this->oneMailingGroup['mailinggroup_default_footer_nl2br'],
      'mailing_created' => $time,
      'mailing_modified' => $time,
      'mailing_protected' => 0
    );
    if (isset($values['contents']) && is_array($values['contents'])) {
      if (in_array('intro', $values['contents'])) {
        $data['mailing_intro'] = @(string)$this->oneMailing['mailing_intro'];
        $data['mailing_intro_nl2br'] = @(string)$this->oneMailing['mailing_intro_nl2br'];
      }
      if (in_array('footer', $values['contents'])) {
        $data['mailing_footer'] = @(string)$this->oneMailing['mailing_footer'];
        $data['mailing_footer_nl2br'] = @(string)$this->oneMailing['mailing_footer_nl2br'];
      }
      foreach ($values['contents'] as $contentId) {
        if (preg_match('~^\d+$~', $contentId)) {
          $contentIds[] = (int)$contentId;
        }
      }
    }
    if ($newId = $this->databaseInsertRecord($this->tableMailings, 'mailing_id', $data)) {
      if (isset($contentIds) && is_array($contentIds) && count($contentIds) > 0) {
        //copy the content records from the selected mailing
        $filter = $this->databaseGetSQLCondition('mailingcontent_id', $contentIds);
        //load them
        $sql = "SELECT mailingcontent_pos, mailingcontent_title, mailingcontent_subtitle,
                       mailingcontent_text, mailingcontent_nl2br
                  FROM %s
                 WHERE $filter
                 ORDER BY mailingcontent_pos";
        $i = 0;
        $data = array();
        if ($res = $this->databaseQueryFmt($sql, $this->tableMailingContents)) {
          while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
            //the new mailing
            $row['mailing_id'] = $newId;
            //new positions (if not alle content parts are copied)
            $row['mailingcontent_pos'] = $i;
            $data[] = $row;
            $i++;
          }
          $this->databaseInsertRecords($this->tableMailingContents, $data);
        }
      }
      return $newId;
    } else {
      return FALSE;
    }
  }

  /**
  * Add a new mailing content
  *
  * @access public
  */
  function addMailingContent() {
    $this->initializeMailingContentForm(TRUE);
    if (isset($this->params['save']) && $this->params['save'] &&
        $this->mailingContentDialog->checkDialogInput()) {
      $this->moveMailingContentsDown();
      if (!isset($this->params['mailingcontent_pos'])) {
        $this->params['mailingcontent_pos'] = count(@$this->contents) + 1;
      }
      $data = array(
        'mailing_id' => $this->params['mailing_id'],
        'mailingcontent_pos' => $this->params['mailingcontent_pos'],
        'mailingcontent_title' => $this->params['mailingcontent_title'],
        'mailingcontent_subtitle' => $this->params['mailingcontent_subtitle'],
        'mailingcontent_text' => papaya_strings::ensureUTF8($this->params['mailingcontent_text']),
        'mailingcontent_nl2br' => @(int)$this->params['nl2br'],
      );
      if ($mailingContentId = $this->databaseInsertRecord(
            $this->tableMailingContents, 'mailingcontent_id', $data)) {
        $this->addMsg(
          MSG_INFO,
          sprintf(
            $this->_gt('Mailing content "%s" (%d) added.'),
            $data['mailingcontent_title'],
            $mailingContentId
          )
        );
        $this->fixMailingContentPositions();
        $this->params['mailingcontent_id'] = $mailingContentId;
        unset($this->mailingContentDialog);
      }
    }
  }

  /**
  * Add a new mailing output.
  *
  * @access public
  * @return mixed mailingoutput id or FALSE
  */
  function addMailingOutput() {
    $data = array(
      'mailing_id' => @(int)$this->params['mailing_id'],
      'mailingoutput_title' => @(string)$this->params['mailingoutput_title'],
      'mailingoutput_subject' => @(string)$this->params['mailingoutput_subject'],
      'mailingoutput_sender' => @(string)$this->params['mailingoutput_sender'],
      'mailingoutput_sendermail' => @(string)$this->params['mailingoutput_sendermail'],
      'mailingoutput_subscribers' => @(string)$this->params['mailingoutput_subscribers'],
      'mailingoutput_text_view' => @(string)$this->params['mailingoutput_text_view'],
      'mailingoutput_text_status' => 0,
      'mailingoutput_text_data' => '',
      'mailingoutput_html_view' => @(string)$this->params['mailingoutput_html_view'],
      'mailingoutput_html_status' => 0,
      'mailingoutput_html_data' => ''
    );
    return $this->databaseInsertRecord(
      $this->tableMailingOutput, 'mailingoutput_id', $data);
  }

  /**
  * Add a new mailing view
  *
  * @access public
  */
  function addMailingView() {
    $this->initializeMailingViewForm(TRUE);
    if (isset($this->params['save']) && $this->params['save']
        && $this->mailingViewDialog->checkDialogInput()) {
      $data = array(
        'mailingview_conf' => '',
        'mailingview_title' => $this->params['mailingview_title'],
        'mailingview_type' => (int)$this->params['mailingview_type'],
      );
      if ($mailingViewId = $this->databaseInsertRecord(
            $this->tableMailingView, 'mailingview_id', $data)) {
        $this->addMsg(
          MSG_INFO,
          sprintf(
            $this->_gt('Mailing view "%s" (%d) added.'),
            $data['mailingview_title'],
            $mailingViewId
          )
        );
        $this->params['mailingview_id'] = $mailingViewId;
      }
    }
  }

  /**
  * delete a mailing group
  *
  * @param $mailingGroupId
  * @access public
  * @return boolean
  */
  function deleteMailingGroup($mailingGroupId) {
    $sql = "SELECT mailing_id
              FROM %s
             WHERE mailinggroup_id = %d";
    $params = array($this->tableMailings, $mailingGroupId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $ids = array();
      while ($row = $res->fetchRow()) {
        $ids[] = $row[0];
      }
      if (count($ids) > 0) {
        $condition = array('mailing_id' => $ids);
        return (
          FALSE !== $this->databaseDeleteRecord(
            $this->tableMailingContents, $condition) &&
          FALSE !== $this->databaseDeleteRecord(
            $this->tableMailingOutput, $condition) &&
          FALSE !== $this->databaseDeleteRecord(
            $this->tableMailings, $condition) &&
          FALSE !== $this->databaseDeleteRecord(
            $this->tableMailingGroups, 'mailinggroup_id', $mailingGroupId)
        );
      } else {
        return FALSE !== $this->databaseDeleteRecord(
          $this->tableMailingGroups, 'mailinggroup_id', $mailingGroupId
        );
      }
    }
    return FALSE;
  }

  /**
  * Deletes a mailing.
  *
  * @access public
  * @param integer|array $mailingId
  * @return boolean
  */
  function deleteMailing($mailingId) {
    $condition = array('mailing_id' => $mailingId);
    return (
      FALSE !== $this->databaseDeleteRecord(
        $this->tableMailingContents, $condition) &&
      FALSE !== $this->databaseDeleteRecord(
        $this->tableMailingOutput, $condition) &&
      FALSE !== $this->databaseDeleteRecord(
        $this->tableMailings, $condition));
  }

  /**
  * delete a mailings in a group older than the given limit
  *
  * @param integer $mailingGroupId
  * @param integer $dateLimit
  * @access public
  * @return boolean
  */
  function deleteMailingOlderThan($mailingGroupId, $dateLimit) {
    $sql = "SELECT mailing_id
              FROM %s
             WHERE mailinggroup_id = %d
               AND mailing_created < %d
               AND mailing_protected = 0";
    $params = array($this->tableMailings, $mailingGroupId, $dateLimit);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $ids = array();
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $ids[] = $row['mailing_id'];
      }
      if (count($ids) > 0) {
        $condition = array('mailing_id' => $ids);
        if (FALSE !== $this->databaseDeleteRecord($this->tableMailingContents, $condition) &&
            FALSE !== $this->databaseDeleteRecord($this->tableMailingOutput, $condition)) {
          return $this->databaseDeleteRecord($this->tableMailings, $condition);
        }
      } else {
        return 0;
      }
    }
    return FALSE;
  }

  /**
  * Deletes a mailing view.
  *
  * @access public
  */
  function delMailingView() {
    if (isset($this->params['mailingview_id']) && isset($this->oneMailingView)
        && is_array($this->oneMailingView)) {
      if (@$this->params['confirm_delete']) {
        $filter = array('mailingview_id' => $this->params['mailingview_id']);
        if (FALSE !== $this->databaseDeleteRecord($this->tableMailingView, $filter)) {
          unset($this->sessionParams['mailingview_id']);
          $this->setSessionValue($this->sessionParamName, $this->sessionParams);
          $this->addMsg(
            MSG_INFO,
            sprintf(
              $this->_gt('Mailingview "%s" (%d) has been deleted.'),
              $this->views[$this->params['mailingview_id']]['mailingview_title'],
              $this->params['mailingview_id']
            )
          );
        }
      }
    }
  }

  /**
  * Delete a mailing output.
  *
  * @access public
  */
  function delMailingOutput() {
    if (isset($this->params['mailingoutput_id']) && isset($this->oneMailingOutput)
        && is_array($this->oneMailingOutput)) {
      if (@$this->params['confirm_delete']) {
        $filter = array('mailingoutput_id' => $this->params['mailingoutput_id']);
        if (FALSE !== $this->databaseDeleteRecord($this->tableMailingOutput, $filter)) {
          $this->addMsg(
            MSG_INFO,
            sprintf(
              $this->_gt('Mailingoutput (%d) has been deleted.'),
              $this->params['mailingoutput_id']
            )
          );
          unset($this->sessionParams['mailingoutput_id']);
          $this->setSessionValue($this->sessionParamName, $this->sessionParams);
        }
      }
    }
  }

  /**
  * Delete a mailing list.
  *
  * @access public
  * @return boolean
  */
  function deleteNewsletterList($newletterListId) {
    $filter = array('newsletter_list_id' => $newletterListId);
    if (FALSE !== $this->databaseDeleteRecord($this->tableProtocol, $filter) &&
        FALSE !== $this->databaseDeleteRecord($this->tableSubscriptions, $filter) &&
        FALSE !== $this->databaseDeleteRecord($this->tableLists, $filter)) {
      $this->deleteUnsubscribed();
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Add a new mailing list
  *
  * @access public
  * @param array $values
  * @return mixed new newsletter_list_id or FALSE
  */
  function addNewsletterList($values) {
    $data = array(
      'newsletter_list_name' => empty($values['newsletter_list_name'])
         ? '' : (string)$values['newsletter_list_name'],
      'newsletter_list_description' => empty($values['newsletter_list_description'])
         ? '' : (string)$values['newsletter_list_description'],
      'newsletter_list_format' => empty($values['newsletter_list_format'])
         ? '' : (int)$values['newsletter_list_format']
    );
    // switch newsletter list selection to recent created one
    if ($newListId = $this->databaseInsertRecord($this->tableLists, 'newsletter_list_id', $data)) {
      $this->sessionParams['newsletter_list_id'] = $newListId;
      $this->setSessionValue($this->sessionParamName, $this->sessionParams);
    }
    return $newListId;
  }

  /**
  * save newsletter list data
  *
  * @param integer $newsletterListId
  * @param array $values
  * @access public
  * @return boolean
  */
  function saveNewsletterList($newsletterListId, $values) {
    $data = array(
      'newsletter_list_name' => @(string)$values['newsletter_list_name'],
      'newsletter_list_description' => @(string)$values['newsletter_list_description'],
      'newsletter_list_format' => @(int)$values['newsletter_list_format']
    );
    $condition = array('newsletter_list_id' => $newsletterListId);
    return (FALSE !== $this->databaseUpdateRecord($this->tableLists, $data, $condition));
  }

  /**
  * save mailing intro content
  *
  * @param integer $mailingId
  * @param array $values
  * @access public
  * @return boolean
  */
  function saveMailingIntro($mailingId, $values) {
    $data = array(
      'mailing_intro' => $this->params['mailing_intro'],
      'mailing_intro_nl2br' => @(int)$this->params['nl2br'],
      'mailing_modified' => time()
    );
    $condition = array('mailing_id' => $this->params['mailing_id']);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailings, $data, $condition
    );
  }

  /**
  * save mailing footer content
  *
  * @param $mailingId
  * @param $values
  * @access public
  * @return boolean
  */
  function saveMailingFooter($mailingId, $values) {
    $data = array(
      'mailing_footer' => $this->params['mailing_footer'],
      'mailing_footer_nl2br' => @(int)$this->params['nl2br'],
      'mailing_modified' => time()
    );
    $condition = array('mailing_id' => $this->params['mailing_id']);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailings, $data, $condition
    );
  }

  /**
  * Edit a mailing content
  *
  * @param integer $mailingcontentId
  * @param array $values
  * @access public
  * @return boolean
  */
  function saveMailingContent($mailingcontentId, $values) {
    $data = array(
      'mailingcontent_title' => $values['mailingcontent_title'],
      'mailingcontent_subtitle' => $values['mailingcontent_subtitle'],
      'mailingcontent_nl2br' => @(int)$this->params['nl2br'],
      'mailingcontent_text' => papaya_strings::ensureUTF8($values['mailingcontent_text'])
    );
    $condition = array('mailingcontent_id' => $this->params['mailingcontent_id']);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingContents, $data, $condition
    );
  }

  /**
  * Edit a mailing view
  *
  * @access public
  */
  function editMailingView() {
    $this->initializeMailingViewForm();
    if (@isset($this->params['save']) && $this->params['save']
        && $this->mailingViewDialog->checkDialogInput()) {
      $data = array(
        'mailingview_title' => $this->params['mailingview_title'],
        'mailingview_type' => $this->params['mailingview_type'],
      );
      $condition = array('mailingview_id' => $this->oneMailingView['mailingview_id']);
      if (FALSE !== $this->databaseUpdateRecord(
            $this->tableMailingView, $data, $condition)) {
        $this->addMsg(
          MSG_INFO,
          sprintf(
            $this->_gt('Mailing view "%s" (%d) updated.'),
            $this->oneMailingView['mailingview_title'],
            $this->oneMailingView['mailingview_id']
          )
        );
      }
    }
  }

  /**
  * Edit a mailing output
  *
  * @access public
  */
  function editMailingOutput() {
    $this->initializeMailingOutputForm();
    if (@isset($this->params['save']) && $this->params['save'] &&
        $this->mailingOutputDialog->checkDialogInput()) {
      if ((
           isset($this->params['mailingoutput_text_view']) &&
           (int)$this->params['mailingoutput_text_view'] == 0
          ) ||
          (
            isset($this->params['mailingoutput_html_view']) &&
           (int)$this->params['mailingoutput_html_view'] == 0
          )
         ) {
        $this->addMsg(
          MSG_WARNING,
          $this->_gt('You did not select an output filter yet.')
        );
      }
      if ($this->saveMailingOutput()) {
        $this->addMsg(MSG_INFO, $this->_gt('Mailing output updated.'));
      }
    }
  }

  /**
  * Edit a mailing view properties.
  *
  * @access public
  * @param  int $mailingViewId
  */
  function editMailingViewProperties($mailingViewId) {
    if (isset($mailingViewId)) {
      $this->initializeMailingViewPropertiesForm($mailingViewId);
      $moduleObj = $this->mailingViewPropertiesObj;

      if ($moduleObj->modified()) {
        if ($moduleObj->checkData()) {
          if (FALSE !== $this->saveMailingViewProperties(
               $mailingViewId, $moduleObj->getData())) {
            $this->addMsg(MSG_INFO, $this->_gt('Changes saved.'));
          }
        }
      }
    }
  }

  /**
  * Get administration XML for Bounce Handling
  *
  */
  function getXMLBounceForm() {
    if (!isset($this->params['cat_id'])) {
      $cat = 1;
    } else {
      $cat = $this->params['cat_id'];
    }
    if (!isset($this->params['mail_id'])) {
      $mid = 0;
    } else {
      $mid = $this->params['mail_id'];
    }
    $this->layout->addLeft($this->bounce->getXMLMailCategoriesList($cat, $this->images));
    $this->layout->addLeft(
      $this->bounce->getXMLMailsList(
        $cat, $mid, $this->params, $this->images
      )
    );
  }

  /**
  * Get XML - browser output function
  *
  * @access public
  */
  function getXML() {
    if (is_object($this->layout)) {
      $this->getXMLButtons();
      if (!isset($this->params['mode'])) {
        $this->params['mode'] = 1;
      }
      if ($this->params['mode'] == 900) {
        $this->layout->setParam('COLUMNWIDTH_LEFT', '500px');
        $this->layout->setParam('COLUMNWIDTH_CENTER', '100%');
      } else {
        $this->layout->setParam('COLUMNWIDTH_LEFT', '300px');
      }
      switch ($this->params['mode']) {
      case 900 :
        break;
      case 1:
        if ($this->module->hasPerm(edmodule_newsletter::PERM_MANAGE_MAILINGS)) {
          if ((isset($this->mailings) && is_array($this->mailings)) ||
              (isset($this->mailingGroups) && is_array($this->mailingGroups))) {
            $this->getXMLMailings();
          }
          switch (@$this->params['cmd']) {
          case 'new_content':
            if (@$this->params['save'] == 1) {
              $this->initializeMailingContentForm(FALSE);
            } else {
              $this->initializeMailingContentForm(TRUE);
            }
            $this->getXMLMailingContentForm();
            break;
          case 'content_up':
          case 'content_down':
          case 'edit_content':
            if (!$this->isEditableMailingGroup()) {
              break;
            }
            if (isset($this->params['mailingcontent_id'])) {
              $this->initializeMailingContentForm(FALSE);
              $this->getXMLMailingContentForm();
            } elseif (isset($this->params['content_type'])) {
              switch ($this->params['content_type']) {
              case 'intro':
                $this->initializeMailingIntroForm();
                $this->layout->add($this->mailingDialog->getDialogXML());
                break;
              case 'footer':
                $this->initializeMailingFooterForm();
                $this->layout->add($this->mailingDialog->getDialogXML());
                break;
              }
            }
            break;
          case 'del_content':
            $this->getXMLDelMailingContentForm();
            $this->initializeMailingContentForm(FALSE);
            if (@$this->oneMailingContent) {
              $this->getXMLMailingContentForm();
            }
            break;
          case 'new_mailinggroup':
            $this->getXMLMailingGroupForm();
            break;
          case 'edit_mailinggroup':
            $this->getXMLMailingGroupButtons();
            if ($this->isManualMailingGroup()) {
              $this->params['content_type'] = 'general';
            }
            switch (@$this->params['content_type']) {
            case 'intro':
              $this->initializeMailingGroupContentForm('intro');
              $this->getXMLMailingGroupForm();
              break;
            case 'feeds':
              $this->getXMLFeedsConfiguration();
              break;
            case 'footer':
              $this->initializeMailingGroupContentForm('footer');
              $this->getXMLMailingGroupForm();
              break;
            case 'general':
            default :
              $this->initializeMailingGroupForm(FALSE);
              $this->getXMLMailingGroupForm();
              break;
            }
            break;
          case 'del_mailinggroup':
            $this->getXMLDeleteMailingGroupForm();
            break;
          case 'new_mailing':
            if (isset($this->mailingGroups) && is_array($this->mailingGroups) &&
                count($this->mailingGroups) > 0) {
              $this->initializeMailingForm(TRUE);
              $this->getXMLMailingForm();
            }
            break;
          case 'copy_mailing':
            $this->intializeMailingCopyForm();
            $this->getXMLMailingForm();
            break;
          case 'edit_mailing':
            $this->initializeMailingForm(FALSE);
            $this->getXMLMailingForm();
            break;
          case 'del_mailing':
            if (!isset($this->params['confirm_delete'])) {
              $this->getXMLDelMailingForm();
            }
            break;
          case 'del_mailing_older':
            if (!isset($this->params['confirm_delete'])) {
              $this->getXMLDelMailingOlderForm();
            }
            break;
          case 'new_output':
            $this->initializeMailingOutputForm(TRUE);
            $this->getXMLMailingOutputForm();
            break;
          case 'parse_mailing':
            if ($this->params['mailingoutput_mode'] == 2) {
              $this->getXMLMailingOutputButtons();
              $this->getXMLMailingOutputShowIFrame('html');
              break;
            } elseif ($this->params['mailingoutput_mode'] == 1) {
              $this->getXMLMailingOutputButtons();
              $this->getXMLMailingOutputShowText();
              break;
            }
          case 'edit_output':
            $this->initializeMailingOutputForm(FALSE);
            $this->getXMLMailingOutputButtons();
            $this->getXMLMailingOutputForm();
            break;
          case 'show_output_text':
            $this->getXMLMailingOutputButtons();
            $this->getXMLMailingOutputShowText();
            break;
          case 'show_output_xml':
            $this->getXMLMailingOutputButtons();
            $this->getXMLMailingOutputShowIFrame('xml');
            break;
          case 'show_output_html':
            $this->getXMLMailingOutputButtons();
            $this->getXMLMailingOutputShowIFrame('html');
            break;
          case 'output_xml':
            $xml = $this->getMailingOutputXML(@(int)$this->params['mailingoutput_id']);
            header('Content-type: text/xml; charset=utf-8');
            echo $xml;
            exit;
          case 'output_html':
            $html = trim($this->oneMailingOutput['mailingoutput_html_data']);
            header('Content-type: text/html; charset=utf-8');
            echo $html;
            exit;
          case 'fill_queue':
            $this->getXMLMailingOutputButtons();
            $this->getXMLMailingOutputFillQueue();
            break;
          case 'del_output':
            if (!isset($this->params['confirm_delete'])) {
              $this->getXMLDelMailingOutputForm();
            }
            break;
          case 'send_testmail':
            $this->getXMLMailingOutputButtons();
            $this->getTestMailXML();
            break;
          default:
            if (isset($this->params['mailing_id']) && isset($this->oneMailing)
                && is_array($this->oneMailing)) {
              $this->initializeMailingForm(!isset($this->oneMailing));
              $this->getXMLMailingForm();
            }
            break;
          }
        }
        break;
      case 3:
        if ($this->module->hasPerm(2)) {
          if (isset($this->params['mailingview_id'])) {
            $this->getXMLMailingViewButtons();
          }
          switch (@$this->params['mailingview_mode']) {
          default:
          case 1:
            if (@$this->params['cmd'] == 'new_view') {
              if (@$this->params['save'] != 1) {
                $this->initializeMailingViewForm(TRUE);
              } else {
                $this->initializeMailingViewForm(FALSE);
              }
              $this->getXMLMailingViewForm();
            } else {
              if (isset($this->views) && is_array($this->views)) {
                if (isset($this->oneMailingView) && is_array($this->oneMailingView)) {
                  if (@$this->params['cmd'] == 'del_view'
                      && !isset($this->params['confirm_delete'])) {
                    $this->getXMLDelMailingViewForm();
                  } else {
                    $this->initializeMailingViewForm(FALSE);
                  }
                  $this->getXMLMailingViewForm();
                }
              }
            }
            break;
          case 2:
            if (isset($this->views) && is_array($this->views)) {
              $this->initializeMailingViewPropertiesForm($this->params['mailingview_id']);
              $this->getXMLMailingViewPropertiesForm();
            }
            break;
          }
          $this->getXMLMailingViewProperties();
        }
        break;
      case 4:
        if ($this->module->hasPerm(3)) {
          switch (@$this->params['cmd']) {
          case 'clear_queue' :
            $this->getDeleteQueueConfirmXML();
            break;
          }
          $this->getQueueListXML();
          if (isset($this->params['mailingqueue_id'])) {
            switch (@$this->params['cmd']) {
            case 'mailingqueue_view_html' :
              $this->getMailingQueueEntryContentHTML($this->params['mailingqueue_id']);
              break;
            default :
              $this->getMailingQueueEntryContentXML($this->params['mailingqueue_id']);
              break;
            }
          }
        }
        break;
      case 0:
      default:
        if ($this->module->hasPerm(7)) {
          $this->getXMLNewsletterLists();
          switch (@$this->params['cmd']) {
          case 'add_list':
            $this->initializeNewsletterListForm(TRUE);
            $this->getXMLNewsletterListForm();
            break;
          case 'edit_list':
            $this->getXMLNewsletterListForm();
            break;
          case 'del_list':
            if (!isset($this->params['confirm_delete'])) {
              $this->getXMLDelMailingListForm();
            }
            break;
          case 'delete_subscriber':
            if (!isset($this->params['confirm_delete'])) {
              $this->getXMLDeleteSubscriberForm();
            }
            break;
          case 'delete_subscriptions':
            if (!isset($this->params['confirm_delete'])) {
              $this->getXMLDeleteSubscriptionsForm();
            }
            break;
          case 'import_csv':
            if (isset($this->params['newsletter_list_id']) &&
                $this->params['newsletter_list_id'] > 0) {
              $this->getXMLImportDialog();
            } else {
              $this->addMsg(
                MSG_INFO,
                $this->_gt('You have to select a subscription first.')
              );
            }
            break;
          case 'filter_surfers' :
            if (isset($this->params['newsletter_list_id']) &&
                $this->params['newsletter_list_id'] > 0) {
              $this->filterSurfers();
            } else {
              $this->addMsg(
                MSG_INFO,
                $this->_gt('You have to select a subscription first.')
              );
            }
            break;
          case 'filter_results' :
            if (isset($this->params['newsletter_list_id']) &&
                $this->params['newsletter_list_id'] > 0) {
              $this->showFilterSurfersResults();
            } else {
              $this->addMsg(
                MSG_INFO,
                $this->_gt('You have to select a subscription first.')
              );
            }
            break;
          default:
            if (isset($this->subscriber) && is_array($this->subscriber)) {
              $this->getXMLSubscriberButtons();
              switch (@$this->params['subscriber_mode']) {
              default:
              case 0:
                $this->getXMLSubscriberForm();
                break;
              case 1:
                if (isset($this->subscriptions[$this->params['newsletter_list_id']])) {
                  $this->getXMLSubscriptionForm();
                }
                $this->getXMLSubscriptionsList();
                break;
              case 2:
                $this->getXMLProtocolList();
                break;
              }
            } elseif (isset($this->newsletterList) && is_array($this->newsletterList)) {
              $this->getXMLNewsletterListForm();
            } else {
              $this->getNewsletterStatusXML();
            }
            break;
          }
          $this->getXMLSearchFilter();
          $this->getXMLSubscriberList();
        }
        break;
      }
    }
  }

  /**
  * get subscribers list xml
  *
  * @access public
  * @return string
  */
  function getXMLSubscriptionsList() {
    if (isset($this->subscriptions) && is_array($this->subscriptions)
        && count($this->subscriptions) > 0) {
      $result = sprintf('<listview title="%s">'.LF, $this->_gt('Subscriptions'));
      $result .= '<items>'.LF;
      foreach ($this->subscriptions as $listId => $subscription) {
        $newsletterList = $this->newsletterLists[$listId];
        if ($subscription['newsletter_list_id'] == $this->params['newsletter_list_id']
            && $subscription['subscriber_id'] == $this->params['subscriber_id']) {
          $selected = ' selected="selected"';
        } else {
          $selected = '';
        }
        $image = $this->getSubscriptionStatusIcon($subscription['subscription_status']);
        $result .= sprintf(
          '<listitem href="%s" title="%s" image="%s"%s>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'newsletter_list_id' => @$subscription['newsletter_list_id'],
                'subscriber_id' => @$subscription['subscriber_id']
              )
            )
          ),
          papaya_strings::escapeHTMLChars($newsletterList['newsletter_list_name']),
          papaya_strings::escapeHTMLChars($image),
          $selected
        );
        $image = $this->getSubscriptionFormatIcon($subscription['subscription_format']);
        $result .= sprintf(
          '<subitem align="center"><glyph src="%s"/></subitem>',
          papaya_strings::escapeHTMLChars($image)
        );
        $result .= '</listitem>';
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->add($result);
    }
  }

  /**
  * initialize dialog for a subscription
  *
  * @access public
  */
  function initializeSubscriptionForm() {
    if (!(isset($this->subscriptionDialog) && is_object($this->subscriptionDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

      $data = $this->subscriptions[$this->params['newsletter_list_id']];
      $hidden = array(
        'cmd' => 'edit_subscription',
        'newsletter_list_id' => $data['newsletter_list_id'],
        'subscriber_id' => $data['subscriber_id'],
        'save' => 1,
      );

      $fields = array(
        'subscription_format' => array('Format', 'isNum', TRUE, 'combo', $this->formats),
        'subscription_status' => array('Status', 'isNum', TRUE, 'combo', $this->status),
      );

      $this->subscriptionDialog = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->subscriptionDialog->loadParams();
      $this->subscriptionDialog->dialogTitle =
        papaya_strings::escapeHTMLChars($this->_gt('Properties'));
    }
  }

  /**
  * get xml for the subecription dialog
  *
  * @access public
  * @return string
  */
  function getXMLSubscriptionForm() {
    $this->initializeSubscriptionForm();
    $this->layout->add($this->subscriptionDialog->getDialogXML());
  }

  /**
  * Get XML for subscribers list
  *
  * @access public
  */
  function getXMLSubscriberList() {
    if (!empty($this->subscribers)) {
      $listview = new PapayaUiListview();
      $listview->caption = new PapayaUiStringTranslated('Subscribers');

      $paging = new PapayaUiToolbarPaging(
        array($this->paramName, 'offset'),
        (int)$this->subscribersCount,
        PapayaUiToolbarPaging::MODE_OFFSET
      );
      $paging->itemsPerPage = $this->subscribersPerPage;
      $listview->toolbars->topLeft->elements[] = $paging;

      foreach ($this->subscribers as $subscriber) {
        $listview->items[] = $item = new PapayaUiListviewItem(
          $this->getSubscriptionStatusIcon(
            isset($subscriber['subscription_status'])
              ? $subscriber['subscription_status'] : self::STATUS_DATAONLY
          ),
          $subscriber['subscriber_email'],
          array(
            $this->paramName => array(
              'subscriber_id' => $subscriber['subscriber_id'],
              'offset' => (int)@$this->params['offset'],
              'mode' => @$this->params['mode']
            )
          ),
          isset($this->params['subscriber_id']) &&
          $this->params['subscriber_id'] == $subscriber['subscriber_id']
        );
        $subscriberName = trim(
          $subscriber['subscriber_firstname'].' '.$subscriber['subscriber_lastname']
        );
        if (!empty($subscriberName)) {
          $item->text = $subscriberName;
        }
      }
      $this->layout->addRight($listview->getXml());
    }
  }

  /**
  * get icon for a numeric subscription status
  *
  * @param integer $status
  * @access public
  * @return string
  */
  function getSubscriptionStatusIcon($status) {
    switch ($status) {
    case self::STATUS_DATAONLY:
      $image = $this->images['items-page'];
      break;
    case self::STATUS_SUBSCRIPTION_REQUESTED:
      $image = $this->images['actions-mail-add'];
      break;
    case self::STATUS_SUBSCRIBED:
      $image = $this->images['items-mail'];
      break;
    case self::STATUS_UNSUBSCRIPTION_REQUESTED:
      $image = $this->images['actions-mail-delete'];
      break;
    default :
      $image = '';
      break;
    }
    return $image;
  }

  /**
  * get icon for a numeric subscription format (text/html)
  *
  * @param $format
  * @access public
  * @return string
  */
  function getSubscriptionFormatIcon($format) {
    switch ($format) {
    case self::FORMAT_TEXT:
      $image = $this->images['items-page'];
      break;
    case self::FORMAT_HTML:
      $image = $this->images['categories-content'];
      break;
    }
    return $image;
  }

  /**
  * Get XML for mailinglists
  *
  * @access private
  */
  function getXMLNewsletterLists() {
    if (!empty($this->newsletterLists)) {
      $result = sprintf('<listview title="%s">'.LF, $this->_gt('Lists'));
      $result .= '<items>'.LF;
      if (0 < @(int)$this->params['newsletter_list_id']) {
        $result .= sprintf(
          '<listitem image="%s" href="%s" title="%s">'.LF,
          papaya_strings::escapeHTMLChars($this->images['actions-go-superior']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(array('newsletter_list_id' => 0, 'mode' => 0, 'cmd' => 'show'))
          ),
          papaya_strings::escapeHTMLChars($this->_gt('Overview'))
        );
        $result .= '</listitem>'.LF;
      }
      foreach ($this->newsletterLists as $list) {
        if ($list['newsletter_list_id'] == $this->params['newsletter_list_id']) {
          $selected = ' selected="selected"';
        } else {
          $selected = '';
        }
        $result .= sprintf(
          '<listitem image="%s" href="%s" title="%s" %s>'.LF,
          papaya_strings::escapeHTMLChars($this->images['items-table']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'newsletter_list_id' => $list['newsletter_list_id'],
                'mode' => @$this->params['mode'],
                'offset' => 0,
                'cmd' => 'edit_list',
              )
            )
          ),
          papaya_strings::escapeHTMLChars($list['newsletter_list_name']),
          $selected
        );
        $result .= '</listitem>'.LF;
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->addLeft($result);
    }
  }

  /**
  * add XML for mailing group dialog to layout object
  *
  * @access private
  */
  function getXMLMailingGroupForm() {
    $this->layout->add($this->mailingGroupDialog->getDialogXML());
  }

  /**
  * Add a listview for the existing feeds for a newsletter, allow to add/edit/delete a feed
  */
  function getXMLFeedsConfiguration() {
    /** implemented feeds listview and form */
    include_once(dirname(__FILE__).'/Feed/Configuration.php');
    $list = new PapayaModuleNewsletterFeedConfiguration($this);
    $list->parameterGroup($this->paramName);
    $list->prepare();
    $list->execute();
    $this->layout->add($list->getXml());
  }

  /**
  * add XML for mailing dialog to layout object
  *
  * @access private
  */
  function getXMLMailingForm() {
    $this->layout->add($this->mailingDialog->getDialogXML());
  }

  /**
  * add XML for content edit dialog to layout object
  *
  * @access private
  */
  function getXMLMailingContentForm() {
    $this->layout->add($this->mailingContentDialog->getDialogXML());
  }

  /**
  * add XML for output dialog to layout object
  *
  * @access private
  */
  function getXMLMailingOutputForm() {
    $this->layout->add($this->mailingOutputDialog->getDialogXML());
  }

  /**
  * add XML for view dialog to layout object
  *
  * @access private
  */
  function getXMLMailingViewForm() {
    $this->initializeMailingViewForm();
    $this->layout->add($this->mailingViewDialog->getDialogXML());
  }

  /**
  * add XML for mailing view properties dialog to layout object
  *
  * @access private
  */
  function getXMLMailingViewPropertiesForm() {
    if (isset($this->mailingViewPropertiesObj)) {
      $this->layout->add($this->mailingViewPropertiesObj->getForm());
    } else {
      $this->papaya()->messages->dispatch(
        new PapayaMessageDisplayTranslated(
          PapayaMessage::TYPE_ERROR,
          'Output filter module not found.'
        )
      );
    }
  }

  /**
  * Initialize editform for subscriber
  *
  * @access private
  */
  function initializeSubscriberEditForm() {
    if (!(isset($this->subscriberDialog) && is_object($this->subscriberDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      $data = $this->subscriber;
      $hidden = array(
        'cmd' => 'edit_subscriber',
        'save' => 1,
        'offset' => (int)@$this->params['offset'],
        'newsletter_list_id' => (int)$this->params['newsletter_list_id'],
        'subscriber_id' => @$this->subscriber['subscriber_id'],
      );

      $fields = array(
        'subscriber_email' =>
          array('Email', 'isEmail', TRUE, 'input', 200),
        'subscriber_status' =>
          array('Active', 'isNum', TRUE, 'yesno', 1,
            'Deactivated subscribers will not receive any mailings.',
            $this->subscriber['subscriber_status']),
        'Contact data',
        'subscriber_salutation' =>
          array('Salutation', 'isNum', TRUE, 'combo', $this->salutations),
        'subscriber_title' =>
          array('Title', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_firstname' =>
          array('First name', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_lastname' =>
          array('Last name', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_branch' =>
          array('Branch', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_company' =>
          array('Company', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_position' =>
          array('Position', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_section' =>
          array('Section', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_street' =>
          array('Street', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_housenumber' =>
          array('House number', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_postalcode' =>
          array('Zip code', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_city' =>
          array('City', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_phone' =>
          array('Phone', 'isPhone', FALSE, 'input', 200),
        'subscriber_mobile' =>
          array('Mobile phone', 'isPhone', FALSE, 'input', 200),
        'subscriber_fax' =>
          array('Fax', 'isPhone', FALSE, 'input', 200)
      );
      $this->subscriberDialog = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->subscriberDialog->dialogTitle = $this->_gt('Properties');
      $this->subscriberDialog->loadParams();
    }
  }

  /**
  * add XML for subscriber dialog to layout object
  *
  * @access private
  */
  function getXMLSubscriberForm() {
    $this->initializeSubscriberEditForm();
    $this->layout->add($this->subscriberDialog->getDialogXML());
  }

  /**
  * generates search filter by first letter, custom text (with wildcards) and
  * subscriber status (registered, confirmed, disabled)
  *
  * @access private
  */
  function getXMLSearchFilter() {
    $result = '';
    $result .= sprintf(
      '<dialog title="%s" action="%s" width="100%%">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Search')),
      $this->baseLink
    );
    $result .= sprintf(
      '<input type="hidden" name="%s[mode]" value="%d"/>',
      papaya_strings::escapeHTMLChars($this->paramName),
      @(int)$this->params['mode']
    );
    $result .= sprintf(
      '<input type="hidden" name="%s[offset]" value="0"/>',
      papaya_strings::escapeHTMLChars($this->paramName)
    );
    $result .= '<lines class="dialogSmall">'.LF;
    $result .= '<line align="center">'.$this->getCharBtns().'</line>'.LF;
    $result .= sprintf(
      '<line caption="%s">',
      papaya_strings::escapeHTMLChars($this->_gt('Name/Email'))
    );
    $result .= sprintf(
      '<input type="text" class="dialogScale dialogInput"'.
      ' name="%s[patt]" value="%s" /></line>'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['patt'])
    );
    $result .= sprintf(
      '<line caption="%s">',
      papaya_strings::escapeHTMLChars($this->_gt('Status'))
    );
    $result .= sprintf(
      '<select class="dialogScale dialogSelect" name="%s[status]">',
      papaya_strings::escapeHTMLChars($this->paramName)
    );
    $result .= sprintf(
      '<option value="-1">%s</option>',
      papaya_strings::escapeHTMLChars($this->_gt('All'))
    );
    foreach ($this->status as $statusId => $statusTitle) {
      if (isset($this->params['status']) && $this->params['status'] == $statusId) {
        $selected = ' selected="selected"';
      } else {
        $selected = '';
      }
      $result .= sprintf(
        '<option value="%d"%s>%s</option>',
        (int)$statusId,
        $selected,
        papaya_strings::escapeHTMLChars($statusTitle)
      );
    }
    $result .= '</select></line>'.LF;
    $result .= '</lines>'.LF;
    $result .= sprintf(
      '<dlgbutton value="%s" />'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Search'))
    );
    $result .= '</dialog>'.LF;
    $this->layout->addRight($result);
  }

  /**
  * Get XML for buttons
  *
  * @access private
  */
  function getXMLButtons() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $this->images;

    $toolbar->addSeperator();

    if ($this->module->hasPerm(7)) {
      $toolbar->addButton(
        'Subscribers',
        $this->getLink(array('cmd' => 'output_mode', 'mode' => 0)),
        'items-user',
        'Manage subscriber lists',
        (@$this->params['mode'] == 0)
      );
    }
    if ($this->module->hasPerm(edmodule_newsletter::PERM_MANAGE_MAILINGS)) {
      $toolbar->addButton(
        'Mailings',
        $this->getLink(array('cmd' => 'output_mode', 'mode' => 1)),
        'status-mail-open',
        'Edit mailings',
        (@$this->params['mode'] == 1)
      );
    }
    if ($this->module->hasPerm(3)) {
      $toolbar->addButton(
        'Shipping',
        $this->getLink(array('cmd' => 'output_mode', 'mode' => 4)),
        $this->images['actions-mail-send-receive'],
        'Manage postbox and send mails',
        (@$this->params['mode'] == 4)
      );
    }
    if ($this->module->hasPerm(2)) {
      $toolbar->addButton(
        'Options',
        $this->getLink(array('cmd' => 'output_mode', 'mode' => 3)),
        'items-option',
        'Edit views',
        (@$this->params['mode'] == 3)
      );
    }

    $toolbar->addSeperator();
    if ($this->module->hasPerm(8)) {
      $toolbar->addButton(
        'Bounces',
        $this->getLink(
          array('cmd' => 'bounces', 'mode' => 900)
        ),
        'categories-messages-inbox',
        'Manage bounce handler',
        (@$this->params['cmd'] == 'bounces')
      );
      if (isset($this->params['cmd']) && $this->params['cmd'] == 'bounces') {
        $toolbar->addButton(
          'Analyse new emails',
          $this->getLink(array('cmd' => 'bounces', 'bcmd' => 'processmails' , 'mode' => 900)),
          'items-mail',
          'Categorize new mails',
          FALSE
        );
        $toolbar->addButton(
          'Train filter',
          $this->getLink(array('cmd' => 'bounces', 'bcmd' => 'teachfilter' , 'mode' => 900)),
          'actions-database-refresh',
          'Train spam filter',
          (@$this->params['bcmd'] == 'teachfilter')
        );
        $toolbar->addButton(
          'Deactivated subscribers',
          $this->getLink(array('cmd' => 'bounces', 'bcmd' => 'blockedsubscribers' , 'mode' => 900)),
          'status-user-locked',
          'List of blocked subscribers',
          (@$this->params['bcmd'] == 'blockedsubscribers')
        );
        if (isset($this->params['mail_id'])) {
          $toolbar->addSeperator();
          $toolbar->addButton(
            'Bounce Email',
            $this->getLink(
              array(
                'cmd' => 'bounces',
                'mode' => 900,
                'bcmd' => 'flag',
                'flag' => 'bounce',
                'mail_id' => @(int)$this->params['mail_id'],
                'cat_id' => @(int)$this->params['cat_id']
              )
            ),
            'status-user-evil',
            'Email is bounced'
          );
          $toolbar->addButton(
            'Regular Email',
            $this->getLink(
              array(
                'cmd' => 'bounces',
                'mode' => 900,
                'bcmd' => 'flag',
                'flag' => 'regular',
                'mail_id' => @(int)$this->params['mail_id'],
                'cat_id' => @(int)$this->params['cat_id']
              )
            ),
            'status-user-angel',
            'Email is regular email/spam'
          );
        }
      }
    }

    $toolbar->addSeperator();
    switch (@$this->params['mode']) {
    case 900 :
      break;
    case 1:
      if ($this->module->hasPerm(edmodule_newsletter::PERM_MANAGE_MAILINGS)) {
        if (!isset($this->oneMailing)) {
          $toolbar->addButton(
            'Add newsletter',
            $this->getLink(
              array(
                'cmd' => 'new_mailinggroup',
                'mailinggroup_id' => 0,
                'mode' => (int)$this->params['mode'],
                'mailingoutput_mode' => 0,
              )
            ),
            'actions-folder-add',
            '',
            FALSE
          );
          if (isset($this->oneMailingGroup) && $this->isEditableMailingGroup()) {
            $toolbar->addButton(
              'Delete newsletter',
              $this->getLink(
                array(
                  'cmd' => 'del_mailinggroup',
                  'mailinggroup_id' => (int)$this->params['mailinggroup_id'],
                  'mode' => (int)$this->params['mode'],
                  'mailingoutput_mode' => 0,
                )
              ),
              'actions-folder-delete',
              '',
              FALSE
            );
          }
          $toolbar->addSeperator();
        }
        if ($this->isEditableMailingGroup()) {
          if (isset($this->params['mailing_id']) && $this->params['mailing_id'] > 0) {
            $toolbar->addButton(
              'Copy mailing',
              $this->getLink(
                array(
                  'cmd' => 'copy_mailing',
                  'mailing_id' => @$this->params['mailing_id'],
                  'mode' => (int)$this->params['mode'],
                )
              ),
              'actions-edit-copy',
              '',
              FALSE
            );
          } else {
            $toolbar->addButton(
              'Add mailing',
              $this->getLink(
                array(
                  'cmd' => 'new_mailing',
                  'mode' => (int)$this->params['mode'],
                  'mailingoutput_mode' => 0,
                )
              ),
              'actions-page-add',
              '',
              FALSE
            );
          }
          if (isset($this->params['feed_id']) && $this->params['feed_id'] > 0) {
            $toolbar->addSeparator();
            $toolbar->addButton(
              'Add feed',
              $this->getLink(
                array(
                  'mailinggroup_id' => (int)$this->params['mailinggroup_id'],
                  'content_type' => 'feeds',
                  'cmd' => 'edit_mailinggroup',
                  'mode' => 1,
                  'feed_id' => 0
                )
              ),
              'actions-page-add',
              '',
              FALSE
            );
            $toolbar->addButton(
              'Delete feed',
              $this->getLink(
                array(
                  'mailinggroup_id' => (int)$this->params['mailinggroup_id'],
                  'content_type' => 'feeds',
                  'cmd' => 'edit_mailinggroup',
                  'mode' => 1,
                  'feed_id' => (int)$this->params['feed_id'],
                  'feed_delete' => 1
                )
              ),
              'actions-page-delete',
              '',
              FALSE
            );
          }

          if (!(
                (isset($this->oneMailingContent) && is_array($this->oneMailingContent)) ||
                (isset($this->oneMailingOutput) && is_array($this->oneMailingOutput))
               )
             ) {
            if (isset($this->oneMailing) &&
                is_array($this->oneMailing) &&
                isset($this->params['mailing_id']) &&
                $this->params['mailing_id'] > 0) {
              $toolbar->addButton(
                'Delete mailing',
                $this->getLink(
                  array(
                    'cmd' => 'del_mailing',
                    'mailing_id' => @$this->params['mailing_id'],
                    'mode' => (int)$this->params['mode']
                  )
                ),
                'actions-page-delete',
                '',
                FALSE
              );
              $toolbar->addButton(
                'Delete old',
                $this->getLink(
                  array(
                    'cmd' => 'del_mailing_older',
                    'mailing_id' => @$this->params['mailing_id'],
                    'mode' => (int)$this->params['mode']
                  )
                ),
                'places-trash',
                '',
                FALSE
              );
            }
          }

          if (isset($this->params['mailing_id']) && $this->params['mailing_id'] > 0) {
            $toolbar->addSeperator();
            if (!$this->isManualMailingGroup()) {
              $toolbar->addButton(
                'Add content',
                $this->getLink(
                  array(
                    'cmd' => 'new_content',
                    'mode' => (int)$this->params['mode'],
                    'mailing_id' => $this->params['mailing_id'],
                    'mailingcontent_id' => @$this->params['mailingcontent_id']
                  )
                ),
                'actions-page-child-add',
                '',
                (@$this->params['cmd'] == 'new_content' && !@$this->params['save']) ? TRUE : FALSE
              );
            }
          }

          if (isset($this->oneMailingContent) && is_array($this->oneMailingContent)) {
            $toolbar->addButton(
              'Delete content',
              $this->getLink(
                array(
                  'cmd' => 'del_content',
                  'mode' => (int)$this->params['mode'],
                  'mailing_id' => @$this->params['mailing_id'],
                  'mailingcontent_id' => @$this->params['mailingcontent_id']
                )
              ),
              'actions-page-child-delete',
              '',
              FALSE
            );
          }
          if (isset($this->params['mailing_id']) &&
              $this->params['mailing_id'] > 0) {
            $toolbar->addSeperator();
            $toolbar->addButton(
              'Add output',
              $this->getLink(
                array(
                  'cmd' => 'new_output',
                  'mode' => (int)$this->params['mode'],
                  'mailing_id' => @$this->params['mailing_id'],
                )
              ),
              'actions-mail-add',
              '',
              (@$this->params['cmd'] == 'new_output') ? TRUE : FALSE
            );

            if (isset($this->oneMailingOutput) &&
                is_array($this->oneMailingOutput) &&
                (!(isset($this->oneMailingContent) && is_array($this->oneMailingContent)))
               ) {
              $toolbar->addButton(
                'Delete output',
                $this->getLink(
                  array(
                    'cmd' => 'del_output',
                    'mode' => (int)$this->params['mode'],
                    'mailingoutput_id' => @$this->oneMailingOutput['mailingoutput_id'],
                  )
                ),
                'actions-mail-delete',
                '',
                FALSE
              );
            }
          }
        }
      }
      break;
    case 3:
      if ($this->module->hasPerm(2)) {
        $toolbar->addButton(
          'Add view',
          $this->getLink(
            array(
              'cmd' => 'new_view',
              'mode' => (int)$this->params['mode'],
            )
          ),
          'actions-view-add',
          '',
          FALSE
        );

        if (isset($this->oneMailingView) && is_array($this->oneMailingView)) {
          $toolbar->addButton(
            'Delete view',
            $this->getLink(
              array(
                'cmd' => 'del_view',
                'mode' => (int)$this->params['mode'],
                'mailingview_id' => $this->params['mailingview_id'],
              )
            ),
            'actions-view-delete',
            '',
            FALSE
          );
        }
      }
      break;
    case 4 :
      if ($this->module->hasPerm(3)) {
        $toolbar->addButton(
          'Postbox',
          $this->getLink(
            array(
              'mailingqueue_mode' => 0,
              'mode' => 4,
            )
          ),
          'categories-messages-inbox',
          '',
          (@$this->params['mailingqueue_mode'] == 0)
        );
        $toolbar->addButton(
          'Sent',
          $this->getLink(
            array(
              'mailingqueue_mode' => 1,
              'mode' => 4,
            )
          ),
          'categories-messages-outbox',
          '',
          (@$this->params['mailingqueue_mode'] == 1)
        );
        $toolbar->addSeperator();
        if (isset($this->queueEntries) && is_array($this->queueEntries) &&
            count($this->queueEntries) > 0) {
          switch (@$this->params['mailingqueue_mode']) {
          case 1 :
            $toolbar->addButton(
              'Delete sent',
              $this->getLink(
                array(
                  'mailingqueue_mode' => 1,
                  'cmd' => 'clear_queue',
                  'mode' => 4,
                )
              ),
              'actions-edit-clear',
              'Delete sent mails'
            );
            break;
          case 0 :
          default :
            $toolbar->addButton(
              'Clear postbox',
              $this->getLink(
                array(
                  'mailingqueue_mode' => 0,
                  'cmd' => 'clear_queue',
                  'mode' => 4,
                )
              ),
              'actions-edit-clear',
              'Delete unsent mails'
            );
            $toolbar->addSeperator();
            $this->getProcessQueueXML();
            $toolbar->addButton(
              'Send',
              'javascript:processQueue();',
              'actions-mail-send-receive',
              'Send mails'
            );
            break;
          }
        }
      }
      break;
    case 0:
    default :
      if ($this->module->hasPerm(7)) {
        $toolbar->addButton(
          'Add list',
          $this->getLink(
            array(
              'cmd' => 'add_list',
              'mode' => @$this->params['mode']
            )
          ),
          $this->images['actions-table-add'],
          '',
          FALSE
        );

        if (isset($this->params['newsletter_list_id'])
            && $this->params['newsletter_list_id'] > 0) {
          $toolbar->addButton(
            'Delete List',
            $this->getLink(
              array(
                'cmd' => 'del_list',
                'mode' => @$this->params['mode'],
                'email' => $this->params['newsletter_list_id']
              )
            ),
            $this->images['actions-table-delete'],
            '',
            FALSE
          );

          if (isset($this->subscribers) && is_array($this->subscribers)) {
            $toolbar->addButton(
              'Empty list',
              $this->getLink(
                array(
                  'cmd' => 'delete_subscriptions',
                  'newsletter_list_id' => $this->params['newsletter_list_id']
                )
              ),
              'actions-edit-clear',
              'Delete all subscription of the current list.',
              FALSE
            );
          }
          $toolbar->addSeperator();
        }

        if (isset($this->subscribers) && is_array($this->subscribers)
            && count($this->subscribers) > 0) {
          $toolbar->addSeperator();
          $toolbar->addButton(
            'Export emails',
            $this->getLink(
              array(
                'cmd' => 'export_list',
                'newsletter_list_id' => $this->params['newsletter_list_id']
              )
            ),
            'actions-save-to-disk',
            '',
            FALSE
          );
          $toolbar->addButton(
            'Export data',
            $this->getLink(
              array(
                'cmd' => 'export_data',
                'newsletter_list_id' => $this->params['newsletter_list_id']
              )
            ),
            'actions-save-to-disk',
            '',
            FALSE
          );
          $toolbar->addButton(
            'Export data as XLS',
            $this->getLink(
              array(
                'cmd' => 'export_data_xls',
                'newsletter_list_id' => $this->params['newsletter_list_id']
              )
            ),
            'actions-save-to-disk',
            '',
            FALSE
          );
        }

        if ($this->module->hasPerm(5)) {
          $toolbar->addSeperator();
          if (isset($this->params['newsletter_list_id']) &&
              $this->params['newsletter_list_id'] > 0) {
            $toolbar->addButton(
              'Import CSV',
              $this->getLink(array('cmd' => 'import_csv')),
              'actions-upload',
              '',
              (@$this->params['cmd'] == 'import_csv')
            );
            $toolbar->addButton(
              'Import surfers',
              $this->getLink(array('cmd' => 'filter_surfers')),
              'actions-user-group-add',
              '',
              (@$this->params['cmd'] == 'filter_surfers')
            );
          }

          if (isset($this->params['subscriber_id']) &&
              $this->params['subscriber_id'] != '') {
            $toolbar->addSeperator();
            $toolbar->addButton(
              'Delete subscriber',
              $this->getLink(
                array(
                  'cmd' => 'delete_subscriber',
                  'subscriber_id' => $this->params['subscriber_id'],
                  'newsletter_list_id' => $this->params['newsletter_list_id']
                )
              ),
              'actions-user-delete',
              '',
              FALSE
            );
          }
        }
      }
      break;
    }
    if ($str = $toolbar->getXML()) {
      $this->layout->addMenu(sprintf('<menu ident="%s">%s</menu>'.LF, 'edit', $str));
    }
  }

  /**
  * add buttons xml for subscriber toolbar to layout object
  *
  * @access private
  */
  function getXMLSubscriberButtons() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $this->images;

    if (isset($this->subscriber)) {
      $toolbar->addButton(
        'Properties',
        $this->getLink(
          array(
            'subscriber_mode' => 0,
            'subscriber_id' => $this->subscriber['subscriber_id']
          )
        ),
        'categories-properties',
        '',
        (@$this->params['subscriber_mode'] == 0)
      );
      $toolbar->addButton(
        'Subscriptions',
        $this->getLink(
          array(
            'subscriber_mode' => 1,
            'subscriber_id' => $this->subscriber['subscriber_id']
          )
        ),
        'items-mail',
        '',
        (@$this->params['subscriber_mode'] == 1)
      );
      $toolbar->addButton(
        'Protocol',
        $this->getLink(
          array(
            'subscriber_mode' => 2,
            'subscriber_id' => $this->subscriber['subscriber_id']
          )
        ),
        'categories-protocol',
        '',
        (@$this->params['subscriber_mode'] == 2)
      );
    }
    if ($str = $toolbar->getXML()) {
      $this->layout->add(sprintf('<toolbar ident="%s">%s</toolbar>'.LF, 'toolbar', $str));
    }
  }

  /**
  * add buttons xml for mailing group toolbar to layout object
  *
  * @access private
  */
  function getXMLMailingGroupButtons() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $this->images;

    if (isset($this->oneMailingGroup)) {
      if (!$this->isManualMailingGroup()) {
        $toolbar->addButton(
          'Properties',
          $this->getLink(
            array(
              'mailinggroup_id' => $this->oneMailingGroup['mailinggroup_id'],
              'content_type' => 'general',
              'cmd' => 'edit_mailinggroup',
              'mode' => @(int)$this->params['mode']
            )
          ),
          'categories-properties',
          '',
          @!in_array($this->params['content_type'], array('intro', 'footer', 'feeds'))
        );
        $toolbar->addButton(
          'Intro',
          $this->getLink(
            array(
              'mailinggroup_id' => $this->oneMailingGroup['mailinggroup_id'],
              'content_type' => 'intro',
              'cmd' => 'edit_mailinggroup',
              'mode' => @(int)$this->params['mode']
            )
          ),
          'items-page',
          '',
          @$this->params['content_type'] == 'intro'
        );
        $toolbar->addButton(
          'Feeds',
          $this->getLink(
            array(
              'mailinggroup_id' => $this->oneMailingGroup['mailinggroup_id'],
              'content_type' => 'feeds',
              'cmd' => 'edit_mailinggroup',
              'mode' => @(int)$this->params['mode']
            )
          ),
          'categories-content',
          '',
          @$this->params['content_type'] == 'feeds'
        );
        $toolbar->addButton(
          'Footer',
          $this->getLink(
            array(
              'mailinggroup_id' => $this->oneMailingGroup['mailinggroup_id'],
              'content_type' => 'footer',
              'cmd' => 'edit_mailinggroup',
              'mode' => @(int)$this->params['mode']
            )
          ),
          'items-page',
          '',
          @$this->params['content_type'] == 'footer'
        );
      }
    }

    if ($str = $toolbar->getXML()) {
      $this->layout->add(sprintf('<toolbar ident="%s">%s</toolbar>'.LF, 'toolbar', $str));
    }
  }

  /**
  * add buttons xml for mailing output toolbar to layout object
  *
  * @access private
  */
  function getXMLMailingOutputButtons() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $this->images;

    if (isset($this->oneMailingOutput)) {
      $toolbar->addSeperator();
      $toolbar->addButton(
        'Properties',
        $this->getLink(
          array(
            'mailingoutput_mode' => 0,
            'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
            'mailing_id' => $this->oneMailingOutput['mailing_id'],
            'cmd' => 'edit_output',
            'mode' => $this->params['mode'],
          )
        ),
        'categories-properties',
        '',
        (@$this->params['mailingoutput_mode'] == 0)
      );

      if ($this->oneMailingOutput['mailingoutput_text_view'] > 0) {
        $toolbar->addButton(
          'Text',
          $this->getLink(
            array(
              'mailingoutput_mode' => 1,
              'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
              'mailing_id' => $this->oneMailingOutput['mailing_id'],
              'cmd' => 'show_output_text',
              'mode' => $this->params['mode'],
            )
          ),
          'items-page',
          '',
          (@$this->params['mailingoutput_mode'] == 1)
        );
      }
      if ($this->oneMailingOutput['mailingoutput_html_view'] > 0) {
        $toolbar->addButton(
          'HTML',
          $this->getLink(
            array(
              'mailingoutput_mode' => 2,
              'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
              'mailing_id' => $this->oneMailingOutput['mailing_id'],
              'cmd' => 'show_output_html',
              'mode' => $this->params['mode'],
            )
          ),
          'categories-content',
          '',
          (@$this->params['mailingoutput_mode'] == 2)
        );
      }
      $toolbar->addSeperator();

      switch (@$this->params['mailingoutput_mode']) {
      case 2 :
        if ($this->oneMailingOutput['mailingoutput_html_view'] > 0) {
          $toolbar->addButton(
            'Edit',
            $this->getLink(
              array(
                'mailingoutput_mode' => 2,
                'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                'mailing_id' => $this->oneMailingOutput['mailing_id'],
                'cmd' => 'edit_output',
                'mode' => $this->params['mode'],
              )
            ),
            'categories-content',
            'Edit email content',
            @$this->params['cmd'] == 'edit_output'
          );
          if (!$this->isManualMailingGroup()) {
            $toolbar->addButton(
              'Generate',
              $this->getLink(
                array(
                  'mailingoutput_mode' => 2,
                  'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                  'mailing_id' => $this->oneMailingOutput['mailing_id'],
                  'mode' => $this->params['mode'],
                  'cmd' => 'parse_mailing',
                )
              ),
              'actions-execute',
              'Generate email content using templates'
            );
            if ($this->module->hasPerm(4)) {
              $toolbar->addButton(
                'XML preview',
                $this->getLink(
                  array(
                    'mailingoutput_mode' => 2,
                    'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                    'mailing_id' => $this->oneMailingOutput['mailing_id'],
                    'cmd' => 'show_output_xml',
                    'mode' => $this->params['mode'],
                  )
                ),
                'categories-preview',
                '',
                @$this->params['cmd'] == 'show_output_xml'
              );
            }
          }
        }
        break;
      case 1 :
        if ($this->oneMailingOutput['mailingoutput_text_view'] > 0) {
          $toolbar->addButton(
            'Edit',
            $this->getLink(
              array(
                'mailingoutput_mode' => 1,
                'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                'mailing_id' => $this->oneMailingOutput['mailing_id'],
                'cmd' => 'edit_output',
                'mode' => $this->params['mode'],
              )
            ),
            'categories-content',
            'Edit email content',
            @$this->params['cmd'] == 'edit_output'
          );
          if (!$this->isManualMailingGroup()) {
            $toolbar->addButton(
              'Generate',
              $this->getLink(
                array(
                  'mailingoutput_mode' => 1,
                  'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                  'mailing_id' => $this->oneMailingOutput['mailing_id'],
                  'mode' => $this->params['mode'],
                  'cmd' => 'parse_mailing',
                )
              ),
              'actions-execute',
              'Generate email content using templates'
            );
            if ($this->module->hasPerm(4)) {
              $toolbar->addButton(
                'XML preview',
                $this->getLink(
                  array(
                    'mailingoutput_mode' => 1,
                    'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                    'mailing_id' => $this->oneMailingOutput['mailing_id'],
                    'cmd' => 'show_output_xml',
                    'mode' => $this->params['mode'],
                  )
                ),
                'categories-preview',
                '',
                @$this->params['cmd'] == 'show_output_xml'
              );
            }
          }
        }
        break;
      }

      $toolbar->addSeperator();
      $toolbar->addButton(
        'Test email',
        $this->getLink(
          array(
            'mailingoutput_mode' => 6,
            'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
            'mailing_id' => $this->oneMailingOutput['mailing_id'],
            'cmd' => 'send_testmail',
            'mode' => $this->params['mode'],
          )
        ),
        'items-bug',
        'Send test mail',
        (@$this->params['mailingoutput_mode'] == 6)
      );

      $toolbar->addSeperator();
      $toolbar->addButton(
        'Send',
        $this->getLink(
          array(
            'mailingoutput_mode' => 5,
            'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
            'mailing_id' => $this->oneMailingOutput['mailing_id'],
            'cmd' => 'fill_queue',
            'mode' => $this->params['mode'],
          )
        ),
        'actions-mail-send',
        'Add to postbox',
        (@$this->params['mailingoutput_mode'] == 5)
      );
    }

    if ($str = $toolbar->getXML()) {
      $this->layout->add(sprintf('<toolbar ident="%s">%s</toolbar>'.LF, 'toolbar', $str));
    }
  }

  /**
  * add buttons xml for mailing view toolbar to layout object
  *
  * @access private
  */
  function getXMLMailingViewButtons() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $toolbar = new base_btnbuilder;
    $toolbar->images = $this->images;

    if (isset($this->params['mailingview_id']) &&
        !(@$this->params['cmd'] == 'del_view')) {
      $toolbar->addButton(
        'General',
        $this->getLink(
          array(
            'mailingview_mode' => 1,
            'mailingview_id' => $this->params['mailingview_id'],
            'mode' => @$this->params['mode'],
            'cmd' => 'set_view_status',
          )
        ),
        'items-option',
        '',
        ($this->params['mailingview_mode'] == 1)
      );
      $toolbar->addButton(
        'Filter',
        $this->getLink(
          array(
            'mailingview_mode' => 2,
            'mailingview_id' => $this->oneMailingView['mailingview_id'],
            'mode' => @$this->params['mode'],
            'cmd' => 'set_view_status',
          )
        ),
        'items-filter-export',
        '',
        ($this->params['mailingview_mode'] == 2)
      );
    }

    if ($str = $toolbar->getXML()) {
      $this->layout->add(sprintf('<toolbar ident="%s">%s</toolbar>'.LF, 'toolbar', $str));
    }
  }

  /**
  * initialize mailing list delete dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDelMailingListForm() {
    if (isset($this->newsletterLists[$this->params['newsletter_list_id']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $listId = $this->params['newsletter_list_id'];
      $hidden = array(
        'cmd' => 'del_list',
        'newsletter_list_id' => $listId,
        'confirm_delete' => 1,
        'mode' => @$this->params['mode'],
      );
      $msg = sprintf(
        $this->_gt(
          'Delete mailing list "%s" (%d)? This will delete all related registrations as well!'
        ),
        $this->newsletterLists[$listId]['newsletter_list_name'],
        $listId
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing group delete dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDeleteMailingGroupForm() {
    if (isset($this->oneMailingGroup)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'del_mailinggroup',
        'mailinggroup_id' => $this->params['mailinggroup_id'],
        'mode' => @$this->params['mode'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt(
          'Delete newsletter "%s" (%d)?'.
          ' This will delete all related mailings, contents and outputs as well!'
        ),
        $this->oneMailingGroup['mailinggroup_title'],
        $this->params['mailinggroup_id']
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing delete dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDelMailingForm() {
    if (isset($this->mailings[$this->params['mailing_id']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'del_mailing',
        'mailing_id' => $this->params['mailing_id'],
        'mode' => @$this->params['mode'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt(
          'Delete mailing "%s" (%d)?'.
          ' This will delete all related contents and outputs as well!'
        ),
        $this->mailings[$this->params['mailing_id']]['mailing_title'],
        $this->params['mailing_id']
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing delete older than dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDelMailingOlderForm() {
    if (isset($this->mailings[$this->params['mailing_id']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'del_mailing_older',
        'mailing_id' => $this->params['mailing_id'],
        'mode' => @$this->params['mode'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt(
          'Delete mailings older than "%s"?'.
          ' This will delete all related contents and outputs as well!'
        ),
        date('Y-m-d H:i:s', $this->mailings[$this->params['mailing_id']]['mailing_created'])
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete old';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing content delete dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDelMailingContentForm() {
    if (isset($this->contents[$this->params['mailingcontent_id']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'del_content',
        'mailingcontent_id' => $this->params['mailingcontent_id'],
        'mailing_id' => $this->params['mailing_id'],
        'mode' => @$this->params['mode'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt('Delete content "%s" (%d)?'),
        $this->contents[$this->params['mailingcontent_id']]['mailingcontent_title'],
        $this->params['mailing_id']
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing view delete dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDelMailingViewForm() {
    if (isset($this->views[$this->params['mailingview_id']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'del_view',
        'mailingview_id' => $this->params['mailingview_id'],
        'mode' => @$this->params['mode'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt('Delete view "%s" (%d)? '),
        $this->views[$this->params['mailingview_id']]['mailingview_title'],
        $this->params['mailingview_id']
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing output delete dialog and add dialog xml to layout object
  *
  * @access private
  */
  function getXMLDelMailingOutputForm() {
    if (isset($this->oneMailingOutput)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'del_output',
        'mailingoutput_id' => $this->params['mailingoutput_id'],
        'mode' => @$this->params['mode'],
        'mailingoutput_mode' => @$this->params['mailingoutput_mode'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt('Delete output (%d)? '), $this->params['mailingoutput_id']
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * initialize mailing parse dialog and add dialog xml to layout object
  *
  * @access public
  */
  function getXMLParseMailingOutputForm() {
    if (isset($this->oneMailingOutput)) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'parse_mailing',
        'mailingoutput_id' => $this->params['mailingoutput_id'],
        'mode' => $this->params['mode'],
        'mailingoutput_mode' => @$this->params['mailingoutput_mode'],
        'confirmation' => 1,
      );

      $msg = sprintf(
        $this->_gt(
          'Generate output for "%s" (%d)? Your manual changes will get lost!'
        ),
        $this->oneMailingOutput['mailing_title'],
        $this->oneMailingOutput['mailingoutput_id']
      );
      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Overwrite';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * get iframe xml for previews
  *
  * @param string $mode optional, default value 'xml'
  * @access private
  */
  function getXMLMailingOutputShowIFrame($mode = 'xml') {
    switch ($mode) {
    case 'html' :
      $params = array(
        'mailingoutput_mode' => 7,
        'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
        'mailing_id' => $this->oneMailingOutput['mailing_id'],
        'cmd' => 'output_html',
        'mode' => $this->params['mode'],
      );
      $caption = 'HTML preview';
      break;
    default :
    case 'xml' :
      $params = array(
        'mailingoutput_mode' => 5,
        'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
        'mailing_id' => $this->oneMailingOutput['mailing_id'],
        'cmd' => 'output_xml',
        'mode' => $this->params['mode'],
      );
      $caption = 'XML preview';
      break;
    }

    $result = sprintf('<panel width="100%%" title="%s">', $this->_gt($caption));
    $result .= '<sheet width="100%" align="center">';
    $result .= '<header>'.LF;
    $result .= '<lines>'.LF;
    $result .= sprintf(
      '<line class="headertitle">%s</line>'.LF,
      papaya_strings::escapeHTMLChars($this->oneMailingOutput['mailingoutput_subject'])
    );
    $result .= sprintf(
      '<line class="headersubtitle">%s &lt;%s&gt;</line>'.LF,
      papaya_strings::escapeHTMLChars($this->oneMailingOutput['mailingoutput_sender']),
      papaya_strings::escapeHTMLChars($this->oneMailingOutput['mailingoutput_sendermail'])
    );
    $result .= '</lines>'.LF;
    $result .= '</header>'.LF;
    $result .= '<text>';
    $result .= sprintf(
      '<iframe width="100%%" noresize="noresize" hspace="0" vspace="0" align="center" '.
      'scrolling="auto" height="1400" src="%s" class="plane" id="preview" />',
      papaya_strings::escapeHTMLChars($this->getLink($params))
    );
    $result .= '</text>';
    $result .= '</sheet>';
    $result .= '</panel>';
    $this->layout->add($result);
  }

  /**
  * get xml for text preview and add to layout object
  *
  * @access private
  */
  function getXMLMailingOutputShowText() {
    $result = sprintf('<panel width="100%%" title="%s">', $this->_gt('Text preview'));
    $result .= '<sheet width="100%" align="center">';
    $result .= '<header>'.LF;
    $result .= '<lines>'.LF;
    $result .= sprintf(
      '<line class="headertitle">%s</line>'.LF,
      papaya_strings::escapeHTMLChars($this->oneMailingOutput['mailingoutput_subject'])
    );
    $result .= sprintf(
      '<line class="headersubtitle">%s &lt;%s&gt;</line>'.LF,
      papaya_strings::escapeHTMLChars($this->oneMailingOutput['mailingoutput_sender']),
      papaya_strings::escapeHTMLChars($this->oneMailingOutput['mailingoutput_sendermail'])
    );
    $result .= '</lines>'.LF;
    $result .= '</header>'.LF;
    $result .= '<text>';
    $result .= $this->formatTextMailContent($this->oneMailingOutput['mailingoutput_text_data']);
    $result .= '</text>';
    $result .= '</sheet>';
    $result .= '</panel>';
    $this->layout->add($result);
  }

  /**
   * Convert multiple spaces to fixed spaces/space groups and convert the linebreak to html.
   *
   * @param string $text
   * @return string
   */
  function formatTextMailContent($text) {
    $linebreak = $this
      ->papaya()
      ->plugins
      ->options['96157ec2db3a16c368ff1d21e8a4824a']
      ->get('NEWSLETTER_TEXT_LINEBREAK', 64);
    if ($linebreak > 10) {
      $text = wordwrap($text, $linebreak, "\n");
    }
    $result = nl2br(str_replace('  ', '&#160; ', papaya_strings::escapeHTMLChars($text)));
    return $result;
  }

  /**
  * get xml for fill queue select list
  *
  * @access private
  */
  function getXMLMailingOutputFillQueue() {
    $fillButton = FALSE;
    $hasRecipients = FALSE;
    $newsletterExists = FALSE;
    $hasMessage['text'] = (
      $this->oneMailingOutput['mailingoutput_text_status'] > 0 &&
      trim($this->oneMailingOutput['mailingoutput_text_data']) != ''
    );
    $hasMessage['html'] = (
      $this->oneMailingOutput['mailingoutput_html_status'] > 0 &&
      trim($this->oneMailingOutput['mailingoutput_html_data']) != ''
    );
    if (isset($this->newsletterLists) &&
        isset($this->params['newsletter_list_id']) &&
        isset($this->newsletterLists[$this->params['newsletter_list_id']]) &&
        isset($this->params['mailing_format'])) {
      $newsletterExists = TRUE;
      $listId = $this->params['newsletter_list_id'];
      $subscribers = $this->loadNewsletterStatus($listId);
      if (isset($subscribers[$listId]) && is_array($subscribers[$listId]) &&
          array_sum($subscribers[$listId]) > 0) {
        switch (@$this->params['mailing_format']) {
        case 'html' :
          if ($hasMessage['html']) {
            $hasRecipients = @(int)$subscribers[$listId][1];
          }
          break;
        case 'text' :
          if ($hasMessage['text']) {
            $hasRecipients = @(int)$subscribers[$listId][0];
          }
          break;
        case 'all' :
          if ($hasMessage['text'] || $hasMessage['html']) {
            $hasRecipients = array_sum($subscribers[$listId]);
          }
        }
      }
    }

    //check for message body
    if (!($hasMessage['text'] || $hasMessage['html'])) {
      //show error
      $this->addMsg(MSG_ERROR, $this->_gt('No message body.'));
      $fillButton = FALSE;
    } elseif (!$newsletterExists) {
      //no newsletter selected
      $fillButton = FALSE;
    } elseif (!$hasRecipients) {
      //show error
      $this->addMsg(MSG_ERROR, $this->_gt('No subscribers.'));
      $fillButton = FALSE;
    } elseif ($hasRecipients) {
      $this->getFillQueueConfirmXML();
    }

    // show recipient data for selected newsletter
    $this->getNewsletterStatusXML(TRUE, $hasMessage);
  }

  /**
  * add newsletter mailings to send queue
  *
  * @param integer $listId
  * @param integer $outputId
  * @param string $format
  * @param integer $scheduledFor
  * @return boolean
  */
  function addToQueue($listId, $outputId, $format, $scheduleFor = 0) {
    $now = time();
    $scheduleFor = $scheduleFor > $now ? $scheduleFor : $now;
    $subscribers = $this->loadNewsletterStatus($listId);
    $hasMessage['text'] = (
      $this->oneMailingOutput['mailingoutput_text_status'] > 0 &&
      trim($this->oneMailingOutput['mailingoutput_text_data']) != ''
    );
    $hasMessage['html'] = (
      $this->oneMailingOutput['mailingoutput_html_status'] > 0 &&
      trim($this->oneMailingOutput['mailingoutput_html_data']) != ''
    );
    $sendAs['html'] = ($hasMessage['html'] && @(int)$subscribers[$listId][1] > 0);
    $sendAs['text'] = (
      $hasMessage['text'] &&
      (@(int)$subscribers[$listId][0] > 0 || @(int)$subscribers[$listId][1] > 0)
    );
    $sendTo['html'] = FALSE;
    $sendTo['text'] = FALSE;
    switch ($format) {
    case 'html' :
      $sendTo['html'] = $sendAs['html'];
      break;
    case 'text' :
      $sendTo['text'] = $sendAs['text'];
      $sendAs['html'] = FALSE;
      break;
    case 'all' :
    default :
      $sendTo['html'] = $sendAs['html'];
      $sendTo['text'] = $sendAs['text'];
      break;
    }
    $sqlFieldsInto = array();
    $sqlFieldsFrom = array();
    if ($sendAs['html']) {
      $sqlFieldsInto[] = 'mailingqueue_html_data';
      $sqlFieldsInto[] = 'mailingqueue_html_status';
      $sqlFieldsFrom[] = 'mo.mailingoutput_html_data';
      $sqlFieldsFrom[] = "'1'";
    }
    if ($sendAs['text']) {
      $sqlFieldsInto[] = 'mailingqueue_text_data';
      $sqlFieldsInto[] = 'mailingqueue_text_status';
      $sqlFieldsFrom[] = 'mo.mailingoutput_text_data';
      $sqlFieldsFrom[] = "'1'";
    }
    $sqlToFormat = array();
    if ($sendTo['html']) {
      $sqlToFormat[] = 1;
    }
    if ($sendTo['text']) {
      $sqlToFormat[] = 0;
    }
    if (is_array($sqlFieldsInto) && count($sqlFieldsInto) > 0 &&
        is_array($sqlFieldsFrom) && count($sqlFieldsFrom) > 0 &&
        is_array($sqlToFormat) && count($sqlToFormat) > 0) {
      $filterFormat =
        $this->databaseGetSQLCondition('sn.subscription_format', $sqlToFormat);
      $filterStatus =
        $this->databaseGetSQLCondition('sn.subscription_status', $this->activeStatus);
      $sql = "INSERT INTO %s
                (mailingqueue_url, mailingqueue_email,
                 mailingqueue_created, mailingqueue_scheduled,
                 mailingqueue_format,
                 newsletter_list_id, mailingqueue_subject, mailingqueue_from,
                 ".implode(', ', $sqlFieldsInto).")
              SELECT '%s', sr.subscriber_email, '%d', '%d', sn.subscription_format,
                     sn.newsletter_list_id, mo.mailingoutput_subject, '%s',
                     ".implode(', ', $sqlFieldsFrom)."
                FROM %s AS sr, %s AS sn, %s AS mo
               WHERE sn.newsletter_list_id = '%d'
                 AND sr.subscriber_id = sn.subscriber_id
                 AND $filterFormat
                 AND $filterStatus
                 AND mo.mailingoutput_id = '%d'
                 AND sr.subscriber_status = 1
                ";
      $params = array(
        $this->tableMailingQueue,
        $this->oneMailingOutput['unsubscribe_url'],
        $now,
        $scheduleFor,
        $this->oneMailingOutput['mailingoutput_sender'].
          ' <'.$this->oneMailingOutput['mailingoutput_sendermail'].'>',
        $this->tableSubscribers,
        $this->tableSubscriptions,
        $this->tableMailingOutput,
        $listId, $outputId
      );
      if (FALSE !== ($resCount = $this->databaseQueryFmtWrite($sql, $params))) {
        $this->addMsg(
          MSG_INFO,
          sprintf($this->_gt('Added %s mails to postbox.'), $resCount)
        );
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * empty queue
  *
  * @param integer $sent queue item status - sent or not sent
  * @access private
  * @return boolean
  */
  function deleteQueue($sent = 0) {
    return FALSE !== $this->databaseDeleteRecord(
      $this->tableMailingQueue, 'mailingqueue_done', $sent
    );
  }

  /**
  * Delete subscriber form
  *
  * @access private
  */
  function getXMLDeleteSubscriberForm() {
    if (isset($this->subscriber) && is_array($this->subscriber) &&
      $this->subscriber['subscriber_id'] == $this->params['subscriber_id']) {

      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'delete_subscriber',
        'subscriber_id' => $this->subscriber['subscriber_id'],
        'newsletter_list_id' => $this->params['newsletter_list_id'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt('Delete subscriber "%s" (%s) from all lists?'),
        $this->subscriber['subscriber_firstname'].' '.$this->subscriber['subscriber_lastname'],
        $this->subscriber['subscriber_email']
      );

      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * Delete all subscribers form
  *
  * @access private
  */
  function getXMLDeleteSubscriptionsForm() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
    $hidden = array(
      'cmd' => 'delete_subscriptions',
      'newsletter_list_id' => $this->params['newsletter_list_id'],
      'confirm_delete' => 1,
    );
    $msg = sprintf($this->_gt('Delete all subscriptions of the current list?'));
    $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
    $dialog->baseLink = $this->baseLink;
    $dialog->buttonTitle = 'Delete';
    $this->layout->add($dialog->getMsgDialog());
  }

  /**
  * Get XML for protocolform
  *
  * @access private
  */
  function getXMLProtocolList() {
    if (isset($this->protocol) && is_array($this->protocol)) {
      $result = sprintf(
        '<listview title="%s">',
        papaya_strings::escapeHTMLChars($this->_gt('Protocol'))
      );
      $result .= '<cols>';
      $result .= sprintf(
        '<col></col>',
        papaya_strings::escapeHTMLChars($this->_gt('Action'))
      );
      $result .= sprintf(
        '<col align="center">%s</col>',
        papaya_strings::escapeHTMLChars($this->_gt('Created'))
      );
      $result .= sprintf(
        '<col align="center">%s</col>',
        papaya_strings::escapeHTMLChars($this->_gt('Confirmed'))
      );
      $result .= '</cols>';
      $result .= '<items>';
      foreach ($this->protocol as $listId => $listProtocol) {
        if (isset($this->newsletterLists[$listId])) {
          $image = @$this->getSubscriptionStatusIcon(
            $this->subscriptions[$listId]['subscription_status']);
          $result .= sprintf(
            '<listitem title="%s" image="%s" span="3">',
            papaya_strings::escapeHTMLChars(
              $this->newsletterLists[$listId]['newsletter_list_name']
            ),
            papaya_strings::escapeHTMLChars($image)
          );
          $result .= '</listitem>';

          foreach ($listProtocol as $line) {
            switch ($line['protocol_action']) {
            case 0:
              $image = $this->images['actions-user-add'];
              $action = $this->_gt('Subscribe');
              break;
            case 1:
              $image = $this->images['actions-user-delete'];
              $action = $this->_gt('Unsubscribe');
              break;
            case 2:
              $image = $this->images['items-page'];
              $action = $this->_gt('Change format to text');
              break;
            case 3:
              $image = $this->images['categories-content'];
              $action = $this->_gt('Change format to html');
              break;
            case 4:
              $image = $this->images['actions-upload'];
              $action = $this->_gt('Import');
            }

            $created = empty($line['protocol_created'])
              ? '' : date('Y-m-d H:i:s', $line['protocol_created']);
            $confirmed = empty($line['protocol_confirmed'])
              ? '' : date('Y-m-d H:i:s', $line['protocol_confirmed']);

            if (isset($action) && isset($image)) {
              $result .= sprintf(
                '<listitem title="%s" image="%s" indent="1">',
                papaya_strings::escapeHTMLChars($action),
                papaya_strings::escapeHTMLChars($image)
              );
              $result .= sprintf(
                '<subitem align="center">%s</subitem>',
                papaya_strings::escapeHTMLChars($created)
              );
              $result .= sprintf(
                '<subitem align="center">%s</subitem>',
                papaya_strings::escapeHTMLChars($confirmed)
              );
              $result .= '</listitem>';
            }
          }
        }
      }
      $result .= '</items>';
      $result .= '</listview>';
      $this->layout->add($result);
    }
  }

  /**
  * Get XML for mailingslistt
  *
  * @access private
  */
  function getXMLMailings() {
    $result = '';
    if ((
         isset($this->mailings) &&
         is_array($this->mailings) &&
         count($this->mailings) > 0
        ) ||
        (
         isset($this->mailingGroups) &&
         is_array($this->mailingGroups) &&
         count($this->mailingGroups) > 0
        )
       ) {
      $result = sprintf(
        '<listview title="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Mailings'))
      );

      if (isset($this->params['mailing_id']) && $this->params['mailing_id'] > 0 &&
          isset($this->mailings[$this->params['mailing_id']]) &&
          isset($this->mailings[$this->params['mailing_id']])) {
        $result .= '<items>'.LF;
        $result .= sprintf(
          '<listitem image="%s" href="%s" title="%s" span="3">'.LF,
          papaya_strings::escapeHTMLChars($this->images['actions-go-superior']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mailing_id' => 0,
                'mailingoutput_id' => 0,
                'mailingcontent_id' => 0,
                'mode' => (int)$this->params['mode'],
              )
            )
          ),
          papaya_strings::escapeHTMLChars($this->_gt('Other mailings ...'))
        );
        $result .= '</listitem>'.LF;
        if (@$this->params['mailingcontent_id'] == 0
            && @$this->params['mailingoutput_id'] == 0
            && !isset($this->params['content_type'])) {
          $selected = ' selected="selected"';
        } else {
          $selected = '';
        }
        $result .= sprintf(
          '<listitem image="%s" href="%s" title="%s" span="3" %s>'.LF,
          papaya_strings::escapeHTMLChars($this->images['status-folder-open']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mailing_id' => $this->params['mailing_id'],
                'mailingoutput_id' => 0,
                'mailingcontent_id' => 0,
                'offset' => (int)@$this->params['offset'],
                'cmd' => 'edit_mailing',
                'mode' => (int)$this->params['mode'],
              )
            )
          ),
          papaya_strings::escapeHTMLChars(
            $this->mailings[$this->params['mailing_id']]['mailing_title']
          ),
          $selected
        );
        $result .= '</listitem>'.LF;

        $result .= $this->getXMLMailingContents();

        if (isset($this->outputs) && is_array($this->outputs)) {
          $result .= sprintf(
            '<listitem image="%s" title="%s" span="3" />'.LF,
            papaya_strings::escapeHTMLChars($this->images['items-mail']),
            papaya_strings::escapeHTMLChars($this->_gt('Outputs'))
          );
          $result .= $this->getXMLMailingOutputs();
        }

      } else {

        $offset = (isset($this->params['offset'])) ? (int)$this->params['offset'] : 0;
        include_once(PAPAYA_INCLUDE_PATH.'system/papaya_paging_buttons.php');
        $result .= papaya_paging_buttons::getPagingButtons(
          $this,
          array(
            'cmd' => 'edit_mailinggroup',
            'mode' => (int)$this->params['mode']
          ),
          (int)$offset,
          $this->mailingsPerPage,
          $this->mailingsCount,
          9
        );
        $result .= '<items>'.LF;
        foreach ($this->mailingGroups as $mailingGroupId => $mailingGroup) {
          if ($mailingGroupId > 0) {
            if (!$this->isEditableMailingGroup($mailingGroup['mailinggroup_editor_group'])) {
              $result .= sprintf(
                '<listitem image="%s" title="%s"/>'.LF,
                papaya_strings::escapeHTMLChars($this->images['categories-access']),
                papaya_strings::escapeHTMLChars($mailingGroup['mailinggroup_title'])
              );
              continue;
            }
            if (@$this->params['mailinggroup_id'] == $mailingGroup['mailinggroup_id']) {
              $selected = ' selected="selected"';
              $imgIdx = 'status-folder-open';
            } else {
              $selected = '';
              $imgIdx = 'items-folder';
            }

            $result .= sprintf(
              '<listitem image="%s" href="%s" title="%s" %s/>'.LF,
              papaya_strings::escapeHTMLChars($this->images[$imgIdx]),
              papaya_strings::escapeHTMLChars(
                $this->getLink(
                  array(
                    'mailinggroup_id' => $mailingGroup['mailinggroup_id'],
                    'mailingoutput_id' => 0,
                    'mailingcontent_id' => 0,
                    'offset' => (int)@$this->params['offset'],
                    'cmd' => 'edit_mailinggroup',
                    'mode' => (int)$this->params['mode']
                  )
                )
              ),
              papaya_strings::escapeHTMLChars($mailingGroup['mailinggroup_title']),
              $selected
            );
            if (@$this->params['mailinggroup_id'] == $mailingGroup['mailinggroup_id']
                && isset($mailingGroup['MAILINGS'])
                && is_array($mailingGroup['MAILINGS'])
                && count($mailingGroup['MAILINGS']) > 0) {
              foreach ($mailingGroup['MAILINGS'] as $mailingId) {
                $mailing = $this->mailings[$mailingId];
                $selected = (@$this->params['mailing_id'] == $mailingId)
                  ? ' selected="selected"' : '';
                $result .=
                  sprintf(
                    '<listitem image="%s" href="%s" title="%s" indent="1" %s/>'.LF,
                    papaya_strings::escapeHTMLChars($this->images['items-page']),
                    papaya_strings::escapeHTMLChars(
                      $this->getLink(
                        array(
                          'mailing_id' => $mailing['mailing_id'],
                          'mailingoutput_id' => 0,
                          'mailingcontent_id' => 0,
                          'offset' => (int)@$this->params['offset'],
                          'cmd' => 'edit_mailing',
                          'mode' => (int)$this->params['mode']
                        )
                      )
                    ),
                    papaya_strings::escapeHTMLChars($mailing['mailing_title']),
                    $selected
                );
              }
            }
          }
        }
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->addLeft($result);
    }
  }

  /**
  * get listview xml for mailing views
  *
  * @access private
  */
  function getXMLMailingViewProperties() {
    $result = '';
    if (isset($this->views) && is_array($this->views)) {
      $result = sprintf(
        '<listview title="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Views'))
      );
      $result .= '<items>'.LF;

      $img = array('items-page', 'categories-content', 'items-page-error');

      foreach ($this->views as $view) {
        $selected = (@$this->params['mailingview_id'] == $view['mailingview_id'])
          ? ' selected="selected"' : '';
        $result .= sprintf(
          '<listitem href="%s" title="%s" image="%s" %s>'.
          '<subitem align="right">%d</subitem></listitem>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mailingview_id' => $view['mailingview_id'],
                'mailingview_mode' => (int)$this->params['mailingview_mode'],
                'mode' => (int)$this->params['mode']
              )
            )
          ),
          papaya_strings::escapeHTMLChars($view['mailingview_title']),
          papaya_strings::escapeHTMLChars($this->images[$img[$view['mailingview_type']]]),
          $selected,
          (int)$view['used_times']
        );
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->addLeft($result);
    }
  }

  /**
  * get xml for mailing outputs listitems
  *
  * @access public
  * @return string xml of MailingOutput listitems
  */
  function getXMLMailingOutputs() {
    $result = '';
    if (isset($this->outputs) && is_array($this->outputs)) {

      foreach ($this->outputs as $output) {
        $selected = (@$this->params['mailingoutput_id'] == $output['mailingoutput_id'])
          ? ' selected="selected"' : '';
        $result .= sprintf(
          '<listitem href="%s" title="%s" %s indent="1">'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mailingoutput_id' => $output['mailingoutput_id'],
                'mailing_id' => $output['mailing_id'],
                'mode' => (int)$this->params['mode'],
                'cmd' => 'edit_output',
                'mailingoutput_mode' => 0
              )
            )
          ),
          papaya_strings::escapeHTMLChars($output['mailingoutput_title']),
          $selected
        );

        $img = array(
          '0' => 'status-sign-off',
          '1' => 'status-sign-ok',
          '2' => 'status-sign-warning',
        );
        $hints = array(
          '0' => 'None',
          '1' => 'Generated',
          '2' => 'Modified',
        );
        $result .= sprintf(
          '<subitem align="right"><glyph src="%s" hint="%s: %s"/></subitem>'.
          '<subitem><glyph src="%s" hint="%s: %s"/></subitem>',
          papaya_strings::escapeHTMLChars(
            $this->images[$img[$output['mailingoutput_text_status']]]
          ),
          papaya_strings::escapeHTMLChars($this->_gt('Text')),
          papaya_strings::escapeHTMLChars(
            $this->_gt($hints[$output['mailingoutput_text_status']])
          ),
          papaya_strings::escapeHTMLChars(
            $this->images[$img[$output['mailingoutput_html_status']]]
          ),
          papaya_strings::escapeHTMLChars($this->_gt('HTML')),
          papaya_strings::escapeHTMLChars($this->_gt($hints[$output['mailingoutput_html_status']]))
        );

        $result .= '</listitem>'.LF;
      }
      return $result;
    }
  }

  /**
  * Get XML for contentlist
  *
  * @access public
  */
  function getXMLMailingContents() {
    if ($this->isManualMailingGroup()) {
      return '';
    }
    $result = '';
    $i = 1;
    $selected = (@$this->params['content_type'] == 'intro') ? ' selected="selected"' : '';
    $result = sprintf(
      '<listitem image="%s" href="%s" title="%s" indent="1" %s span="3" />'.LF,
      papaya_strings::escapeHTMLChars($this->images['items-page']),
      papaya_strings::escapeHTMLChars(
        $this->getLink(
          array(
            'mailing_id' => $this->params['mailing_id'],
            'mode' => @$this->params['mode'],
            'cmd' => 'edit_content',
            'content_type' => 'intro',
          )
        )
      ),
      papaya_strings::escapeHTMLChars($this->_gt('Intro')),
      $selected
    );
    if (isset($this->contents) && is_array($this->contents)) {
      foreach ($this->contentsOrder as $contentId) {
        $content = $this->contents[$contentId];
        $selected = (@$this->params['mailingcontent_id'] == $content['mailingcontent_id'])
          ? ' selected="selected"' : '';
        $title = $content['mailingcontent_title'];
        if (papaya_strings::strlen($title) > 40) {
          $title = papaya_strings::substr($title, 0, 38).'...';
        }

        $result .= sprintf(
          '<listitem image="%s" href="%s" title="%s" indent="1" %s>'.LF,
          papaya_strings::escapeHTMLChars($this->images['items-page-child']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mailingcontent_id' => $content['mailingcontent_id'],
                'mode' => (int)$this->params['mode'],
                'mailing_id' => @(int)$this->params['mailing_id'],
                'cmd' => 'edit_content'
              )
            )
          ),
          papaya_strings::escapeHTMLChars($title),
          $selected
        );

        if ($i > 1) {
          $result .= sprintf(
            '<subitem align="right"><a href="%s"><glyph src="%s" hint="%s" /></a></subitem>',
            papaya_strings::escapeHTMLChars(
              $this->getLink(
                array(
                  'mailingcontent_id' => (int)$content['mailingcontent_id'],
                  'mode' => @$this->params['mode'],
                  'mailing_id' => @(int)$this->params['mailing_id'],
                  'contentlist_pos' => (int)$i,
                  'cmd' => 'content_up'
                )
              )
            ),
            papaya_strings::escapeHTMLChars($this->images['actions-go-up']),
            papaya_strings::escapeHTMLChars($this->_gt('Move up'))
          );
        } else {
          $result .= '<subitem />';
        }

        if ($i < ($this->contentsCount)) {
          $result .= sprintf(
            '<subitem align="right"><a href="%s"><glyph src="%s" hint="%s" /></a></subitem>',
            papaya_strings::escapeHTMLChars(
              $this->getLink(
                array(
                  'mailingcontent_id' => (int)$content['mailingcontent_id'],
                  'mode' => @$this->params['mode'],
                  'mailing_id' => @(int)$this->params['mailing_id'],
                  'contentlist_pos' => (int)$i,
                  'cmd' => 'content_down'
                )
              )
            ),
            papaya_strings::escapeHTMLChars($this->images['actions-go-down']),
            papaya_strings::escapeHTMLChars($this->_gt('Move down'))
          );
        } else {
          $result .= '<subitem />';
        }

        $result .= '</listitem>';
        $i++;
      }
    }
    $selected = (@$this->params['content_type'] == 'footer')
      ? ' selected="selected"' : '';
    $result .= sprintf(
      '<listitem image="%s" href="%s" title="%s" indent="1" %s span="3" />'.LF,
      papaya_strings::escapeHTMLChars($this->images['items-page']),
      papaya_strings::escapeHTMLChars(
        $this->getLink(
          array(
            'mailing_id' => @(int)$this->params['mailing_id'],
            'mode' => @$this->params['mode'],
            'cmd' => 'edit_content',
            'content_type' => 'footer',
          )
        )
      ),
      papaya_strings::escapeHTMLChars($this->_gt('Footer')),
      $selected
    );
    return $result;
  }

  /**
  * initialize mailing group dialog
  *
  * @param boolean $newGroup optional, default value FALSE
  * @access private
  */
  function initializeMailingGroupForm($newGroup = FALSE) {
    if (!(isset($this->mailingGroupDialog) && is_object($this->mailingGroupDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if ($newGroup) {
        $data = array();
        $hidden = array(
          'cmd' => 'new_mailinggroup',
          'save' => 1,
          'mailinggroup_id' => 0,
          'mode' => @$this->params['mode'],
        );
      } else {
        $data = @$this->oneMailingGroup;
        $hidden = array(
          'cmd' => 'edit_mailinggroup',
          'save' => 1,
          'mailinggroup_id' => (int)$this->params['mailinggroup_id'],
          'mode' => @$this->params['mode'],
        );
      }
      $strNone = $this->_gt('None');
      $possibleViews = array(
        'text' => array(0 => $strNone),
        'html' => array(0 => $strNone)
      );
      if (isset($this->views) && is_array($this->views)) {
        foreach ($this->views as $view) {
          if ((isset($viewType) && $viewType == $view['mailingview_type']) ||
              !isset($viewType)) {
            switch ($view['mailingview_type']) {
            case 1 :
              $possibleViews['html'][$view['mailingview_id']] =
                $view['mailingview_title'];
              break;
            case 0 :
            default :
              $possibleViews['text'][$view['mailingview_id']] =
                $view['mailingview_title'];
              break;
            }
          }
        }
      }
      $groupModus = array(
        '0' => $this->_gt('Templates'),
        '1' => $this->_gt('Manually'),
      );
      $fields = array(
        'mailinggroup_title' =>
          array('Title', 'isSomeText', TRUE, 'input', 250),
        'lng_id' =>
          array('Language', 'isNum', TRUE, 'combo', $this->languages),
        'Email',
        'mailinggroup_default_subject' =>
          array('Subject', 'isNoHTML', TRUE, 'input', 200),
        'mailinggroup_default_sender' =>
          array('Sender', 'isNoHTML', TRUE, 'input', 200),
        'mailinggroup_default_senderemail' =>
          array('Sender email', 'isEMail', TRUE, 'input', 200),
        'mailinggroup_default_subscribers' =>
          array(
            'Subscribers list', 'isNum', TRUE, 'combo',
            PapayaUtilArray::merge(
              array(0 => $this->_gt('[None]')), $this->getNewsletterNames()
            )
          ),
        'Pages',
        'mailinggroup_default_archive_url' =>
          array('Archive page', 'isNoHTML', FALSE, 'pageid', 800,
                                   'Please insert the id or url of the archive page.'),
        'mailinggroup_default_unsubscribe_url' =>
          array('Unsubscription page', 'isNoHTML', FALSE, 'pageid', 800,
                                   'Please insert the id or url of the unsubscription page.'),
        'Views',
        'mailinggroup_mode' =>
          array('Modus', 'isNum', TRUE, 'combo', $groupModus),
        'mailinggroup_default_textview' =>
          array('Text', 'isNum', FALSE, 'combo', $possibleViews['text']),
        'mailinggroup_default_htmlview' =>
          array('HTML', 'isNum', FALSE, 'combo', $possibleViews['html'])
      );
      $changePermissions = $this->papaya()->administrationUser->hasModulePerm(
        edmodule_newsletter::PERM_MANAGE_MAILING_PERMISSIONS, $this->module->guid
      );
      if ($changePermissions) {
        $fields[] = $this->_gt('Permissions');
        $fields['mailinggroup_editor_group'] = array(
          'Group', 'isNum', FALSE, 'combo', iterator_to_array($this->editorGroups())
        );
      }

      $this->mailingGroupDialog =
        new base_dialog($this, $this->paramName, $fields, $data, $hidden);
      $this->mailingGroupDialog->loadParams();
      $this->mailingGroupDialog->inputFieldSize = 'x-large';
      $this->mailingGroupDialog->dialogDoubleButtons = TRUE;
      $this->mailingGroupDialog->expandPapayaTags = TRUE;
      $this->mailingGroupDialog->buttonTitle = ($newGroup) ? 'Add' : 'Save';
      $this->mailingGroupDialog->dialogTitle =
        papaya_strings::escapeHTMLChars($this->_gt('Properties'));
    }
  }

  public function editorGroups(PapayaContentAuthenticationGroups $groups = NULL) {
    $groups = new PapayaContentAuthenticationGroups();
    $groups->activateLazyLoad();
    return new PapayaIteratorMultiple(
      PapayaIteratorMultiple::MIT_KEYS_ASSOC,
      new ArrayIterator(array(0 => 'All')),
      new PapayaIteratorCallback($groups, array($this, 'mapRecordToTitle'))
    );
  }

  public function mapRecordToTitle($array) {
    return $array['title'];
  }

  /**
  * Get the names of the current loaded newsletters (key will be the newsletter id)
  *
  * @return array(integer=>string)
  */
  private function getNewsletterNames() {
    $result = array();
    foreach ($this->newsletterLists as $newsletter) {
      $result[$newsletter['newsletter_list_id']] = $newsletter['newsletter_list_name'];
    }
    return $result;
  }

  /**
  * initialize mailing group content dialog
  *
  * @param string $contentType optional, default value 'intro'
  * @access private
  */
  function initializeMailingGroupContentForm($contentType = 'intro') {
    if (!(isset($this->mailingGroupDialog) && is_object($this->mailingGroupDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

      $data = $this->oneMailingGroup;
      $hidden = array(
        'cmd' => 'edit_mailinggroup',
        'save' => 1,
        'mailinggroup_id' => (int)$this->params['mailinggroup_id'],
        'mode' => @$this->params['mode'],
        'content_type' => $contentType
      );
      $fields = array(
        'nl2br' => array('Automatic linebreak', 'isNum', TRUE, 'combo',
          array(0 => 'Yes', 1 => 'No'),
          'Apply linebreaks from input to the HTML output.')
      );
      if ($contentType == 'footer') {
        $data['nl2br'] = $data['mailinggroup_default_footer_nl2br'];
        $caption = 'Footer';
        $fields['mailinggroup_default_footer'] =
          array('Footer', 'isSomeText', FALSE, 'richtext', 25);
      } else {
        $data['nl2br'] = $data['mailinggroup_default_intro_nl2br'];
        $caption = 'Intro';
        $fields['mailinggroup_default_intro'] =
          array('Intro', 'isSomeText', FALSE, 'richtext', 25);
      }
      $this->mailingGroupDialog =
        new base_dialog($this, $this->paramName, $fields, $data, $hidden);
      $this->mailingGroupDialog->loadParams();
      $this->mailingGroupDialog->inputFieldSize = 'x-large';
      $this->mailingGroupDialog->dialogDoubleButtons = TRUE;
      $this->mailingGroupDialog->expandPapayaTags = TRUE;
      $this->mailingGroupDialog->buttonTitle = 'Save';
      $this->mailingGroupDialog->dialogTitle =
        papaya_strings::escapeHTMLChars($this->_gt($caption));
    }
  }

  /**
  * Initialize editform for mailings
  *
  * @access private
  */
  function initializeMailingForm($newMailing = FALSE) {
    if (!(isset($this->mailingDialog) && is_object($this->mailingDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if ($newMailing) {
        if (isset($this->mailingGroups[(int)$this->params['mailinggroup_id']])) {
          $group = $this->mailingGroups[(int)$this->params['mailinggroup_id']];
          $data = array(
            'mailing_title' =>
              $this->_gt('Mailing').' '.PapayaUtilDate::timestampToString(time(), FALSE),
            'mailinggroup_id' => $group['mailinggroup_id'],
          );
        }
        $hidden = array(
          'cmd' => 'new_mailing',
          'save' => 1,
          'mode' => @$this->params['mode'],
        );
      } else {
        $data = @$this->oneMailing;
        $hidden = array(
          'cmd' => 'edit_mailing',
          'save' => 1,
          'mailing_id' => (int)$this->params['mailing_id'],
          'mode' => @$this->params['mode'],
        );
      }

      $groups = array();
      foreach (@$this->mailingGroups as $groupId => $group) {
        $groups[$groupId] = $group['mailinggroup_title'];
      }

      $fields = array(
        'mailing_title' =>
          array('Title', 'isSomeText', TRUE, 'input', 250),
        'mailinggroup_id' =>
          array('Newsletter', 'isNum', TRUE, 'combo', $groups),
        'mailing_protected' =>
          array('Protected', 'isNum', TRUE, 'yesno', NULL, 'Protect against mass delete.', 0),
        'mailing_note' =>
          array('Note', 'isNoHTML', FALSE, 'textarea', 10),
        'lng_id' =>
          array('Language', 'isNum', TRUE, 'combo', $this->languages),
      );
      if (!$newMailing) {
        $fields[] = 'Pages';
        $fields['mailing_url'] = array(
          'Archive page', 'isNoHTML', FALSE, 'pageid', 800,
          'Please insert the id or url of the archive page.'
        );
        $fields['unsubscribe_url'] = array(
          'Unsubscription page', 'isNoHTML', FALSE, 'pageid', 800,
          'Please insert the id or url of the unsubscription page.'
        );
        $fields[] = 'Information';
        $fields['mailing_created_info'] = array(
          'Created', 'isNoHTML', FALSE, 'info', '', '',
          empty($data['mailing_created']) ? '' : date('Y-m-d H:i:s', $data['mailing_created'])
        );
        $fields['mailing_modififed_info'] = array(
          'Modified', 'isNoHTML', FALSE, 'info', '', '',
          empty($data['mailing_modified']) ? '' : date('Y-m-d H:i:s', $data['mailing_modified'])
        );
      }

      $this->mailingDialog =
        new base_dialog($this, $this->paramName, $fields, $data, $hidden);
      $this->mailingDialog->loadParams();
      $this->mailingDialog->inputFieldSize = 'x-large';
      $this->mailingDialog->dialogDoubleButtons = TRUE;
      $this->mailingDialog->expandPapayaTags = TRUE;
      $this->mailingDialog->buttonTitle = ($newMailing) ? 'Add' : 'Save';
      $this->mailingDialog->dialogTitle =
        papaya_strings::escapeHTMLChars($this->_gt('Properties'));
    }
  }

  /**
  * initialize copy mailing dialog
  *
  * @access private
  */
  function intializeMailingCopyForm() {
    if (!(isset($this->mailingDialog) && is_object($this->mailingDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

      if (isset($this->oneMailing)) {
        $data = $this->oneMailing;
        $hidden = array(
          'cmd' => 'copy_mailing',
          'confirm_copy' => 1,
          'mailing_id' => (int)$this->params['mailing_id'],
          'mode' => @$this->params['mode'],
        );

        $groups = array();
        foreach (@$this->mailingGroups as $groupId => $group) {
          $groups[$groupId] = $group['mailinggroup_title'];
        }

        $elements = array(
          'intro' => $this->_gt('Intro'),
        );
        if (isset($this->contents) && is_array($this->contents) &&
            isset($this->contentsOrder) && is_array($this->contentsOrder)) {
          foreach ($this->contentsOrder as $contentId) {
            $content = $this->contents[$contentId];
            $elements[$contentId] = $content['mailingcontent_title'];
            $data['contents'][$contentId] = TRUE;
          }
        }
        $elements['footer'] = $this->_gt('Footer');

        $fields = array(
          'mailing_title' =>
            array('Title', 'isSomeText', TRUE, 'input', 250),
          'mailing_url' =>
            array('Link target', 'isNoHTML', FALSE, 'pageid', 800,
                                     'Please input a page id or url.'),
          'unsubscribe_url' =>
            array('Unsubscription page', 'isNoHTML', FALSE, 'pageid', 800,
                                     'Please input a page id or url.'),
          'mailinggroup_id' =>
            array('Newsletter', 'isNum', TRUE, 'combo', $groups),
          'mailing_note' =>
            array('Note', 'isNoHTML', FALSE, 'textarea', 10),
          'lng_id' =>
            array('Language', 'isNum', TRUE, 'combo',
            $this->languages),
          'Copy',
          'contents' =>
            array('Content parts', 'isAlphaNum', FALSE,
              'checkgroup', $elements, '', NULL, 'left'),
        );

        $this->mailingDialog = new base_dialog(
          $this, $this->paramName, $fields, $data, $hidden
        );
        $this->mailingDialog->loadParams();
        $this->mailingDialog->inputFieldSize = 'x-large';
        $this->mailingDialog->dialogDoubleButtons = TRUE;
        $this->mailingDialog->expandPapayaTags = TRUE;
        $this->mailingDialog->buttonTitle = 'Copy';
        $this->mailingDialog->dialogTitle = $this->_gt('Copy mailing');
      }
    }
  }

  /**
  * initialize mailing intro dialog
  *
  * @access private
  */
  function initializeMailingIntroForm() {
    if (!(isset($this->mailingDialog) && is_object($this->mailingDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

      if (isset($this->oneMailing)) {
        $data = $this->oneMailing;
        $data['nl2br'] = $this->oneMailing['mailing_intro_nl2br'];
      } else {
        $data = array();
      }

      $hidden = array(
        'cmd' => 'edit_content',
        'content_type' => 'intro',
        'save' => 1,
        'mailing_id' => (int)$this->params['mailing_id'],
        'mode' => @$this->params['mode'],
      );

      $fields = array(
        'nl2br' => array(
          'Automatic linebreak', 'isNum', TRUE, 'combo', array(0 => 'Yes', 1 => 'No'),
          'Apply linebreaks from input to the HTML output.'
        ),
        'mailing_intro' => array('Intro', 'isSomeText', FALSE, 'richtext', 15),
      );

      $this->mailingDialog = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->mailingDialog->loadParams();
      $this->mailingDialog->inputFieldSize = 'x-large';
      $this->mailingDialog->dialogDoubleButtons = TRUE;
      $this->mailingDialog->expandPapayaTags = TRUE;
      $this->mailingDialog->buttonTitle = 'Save';
      $this->mailingDialog->dialogTitle = $this->_gt('Intro');
    }
  }

  /**
  * initalize mailing footer dialog
  *
  * @access private
  */
  function initializeMailingFooterForm() {
    if (!(isset($this->mailingDialog) && is_object($this->mailingDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

      if (isset($this->oneMailing)) {
        $data = $this->oneMailing;
        $data['nl2br'] = $this->oneMailing['mailing_footer_nl2br'];
      } else {
        $data = array();
      }

      $hidden = array(
        'cmd' => 'edit_content',
        'content_type' => 'footer',
        'save' => 1,
        'mailing_id' => (int)$this->params['mailing_id'],
        'mode' => @$this->params['mode'],
      );

      $fields = array(
        'nl2br' => array('Automatic linebreak', 'isNum', TRUE, 'combo',
          array(0 => 'Yes', 1 => 'No'),
          'Apply linebreaks from input to the HTML output.'),
        'mailing_footer' => array('Footer', 'isSomeText', FALSE, 'richtext', 15),
      );

      $this->mailingDialog = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->mailingDialog->loadParams();
      $this->mailingDialog->inputFieldSize = 'x-large';
      $this->mailingDialog->dialogDoubleButtons = TRUE;
      $this->mailingDialog->expandPapayaTags = TRUE;
      $this->mailingDialog->buttonTitle = 'Save';
      $this->mailingDialog->dialogTitle = $this->_gt('Footer');
    }
  }

  /**
  * Initialize editform for mailing content
  *
  * @access private
  */
  function initializeMailingContentForm($newContent = FALSE) {
    if (!(isset($this->mailingContentDialog) && is_object($this->mailingContentDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if ($newContent) {
        $data = array();
        $hidden = array(
          'cmd' => 'new_content',
          'save' => 1,
          'mailing_id' => (int)$this->params['mailing_id'],
          'mode' => @$this->params['mode']
        );
      } else {
        $data = @$this->oneMailingContent;
        $data['nl2br'] = @$this->oneMailingContent['mailingcontent_nl2br'];
        $hidden = array(
          'cmd' => 'edit_content',
          'save' => 1,
          'mailingcontent_id' => (int)$this->params['mailingcontent_id'],
          'mailing_id' => (int)$this->params['mailing_id'],
          'mode' => @$this->params['mode']
        );
      }

      $fields = array(
        'nl2br' => array('Automatic linebreak', 'isNum', FALSE, 'combo',
          array(0 => 'Yes', 1 => 'No'),
          'Apply linebreaks from input to the HTML output.'),
        'mailingcontent_title' => array('Title', 'isSomeText', TRUE, 'input', 400),
        'mailingcontent_subtitle' => array('Subtitle', 'isSomeText', FALSE, 'input', 400),
        'mailingcontent_text' => array('Text', 'isSomeText', TRUE, 'richtext', 20)
      );
      $this->mailingContentDialog = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->mailingContentDialog->loadParams();
      $this->mailingContentDialog->inputFieldSize = 'x-large';
      $this->mailingContentDialog->expandPapayaTags = TRUE;
      $this->mailingContentDialog->dialogDoubleButtons = TRUE;
      $this->mailingContentDialog->buttonTitle = ($newContent) ? 'Add' : 'Save';
      $this->mailingContentDialog->dialogTitle = $this->_gt('Properties');
    }
  }

  /**
  * initialize mailing output dialog
  *
  * @param boolean $newOutput optional, default value FALSE
  * @access public
  */
  function initializeMailingOutputForm($newOutput = FALSE) {
    if (!(isset($this->mailingOutputDialog) && is_object($this->mailingOutputDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');

      if ($newOutput) {
        if (!isset($this->oneMailingGroup) && isset($this->oneMailing)) {
          $this->loadOneMailingGroup($this->oneMailing['mailinggroup_id']);
        }
        $data = array(
          'mailingoutput_title' =>
            $this->_gt('Output').' '.PapayaUtilDate::timestampToString(time(), FALSE),
          'mailingoutput_text_view' =>
            $this->oneMailingGroup['mailinggroup_default_textview'],
          'mailingoutput_html_view' =>
            $this->oneMailingGroup['mailinggroup_default_htmlview'],
          'mailingoutput_subject' =>
            $this->oneMailingGroup['mailinggroup_default_subject'],
          'mailingoutput_sender' =>
            $this->oneMailingGroup['mailinggroup_default_sender'],
          'mailingoutput_sendermail' =>
            $this->oneMailingGroup['mailinggroup_default_senderemail'],
          'mailingoutput_subscribers' =>
            $this->oneMailingGroup['mailinggroup_default_subscribers']
        );
        $hidden = array(
          'cmd' => 'new_output',
          'save' => 1,
          'mailingoutput_mode' => (int)@$this->params['mailingoutput_mode'],
          'mailing_id' => (int)@$this->params['mailing_id'],
          'mode' => (int)@$this->params['mode'],
        );
      } else {
        $data = @$this->oneMailingOutput;
        $hidden = array(
          'cmd' => 'edit_output',
          'save' => 1,
          'mailing_id' => (int)@$this->oneMailingOutput['mailing_id'],
          'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
          'mailingoutput_mode' => (int)@$this->params['mailingoutput_mode'],
          'mode' => (int)$this->params['mode'],
        );
      }

      if (isset($this->params['mailingoutput_mode']) &&
          $this->params['mailingoutput_mode'] > 0) {
        $viewType = ($this->params['mailingoutput_mode'] - 1);
      } else {
        $viewType = NULL;
      }

      $possibleStatus = array(
        '0' => $this->_gt('empty'),
        '1' => $this->_gt('generated'),
        '2' => $this->_gt('modified')
      );

      switch (@$this->params['mailingoutput_mode']) {
      case 2 :
        $fields = array(
          'mailingoutput_html_data' => array('Data', 'isSomeText', TRUE, 'textarea', 32),
        );
        $title = 'HTML';
        break;
      case 1 :
        $fields = array(
          'mailingoutput_text_data' => array('Data', 'isSomeText', TRUE, 'textarea', 32),
        );
        $title = 'Text';
        break;
      case 0 :
      default :
        $strNone = $this->_gt('None');
        $possibleViews = array(
          'text' => array(0 => $strNone),
          'html' => array(0 => $strNone)
        );
        if (isset($this->views) && is_array($this->views)) {
          foreach ($this->views as $view) {
            if ((isset($viewType) && $viewType == $view['mailingview_type']) ||
                !isset($viewType)) {
              switch ($view['mailingview_type']) {
              case 1 :
                $possibleViews['html'][$view['mailingview_id']] =
                  $view['mailingview_title'];
                break;
              case 0 :
              default :
                $possibleViews['text'][$view['mailingview_id']] =
                  $view['mailingview_title'];
                break;
              }
            }
          }
        }
        $fields = array(
          'mailingoutput_title' =>
            array('Title', 'isNoHTML', TRUE, 'input', 200),
          'Email',
          'mailingoutput_subject' =>
            array('Subject', 'isNoHTML', TRUE, 'input', 200),
          'mailingoutput_sender' =>
            array('Sender', 'isNoHTML', TRUE, 'input', 200),
          'mailingoutput_sendermail' =>
            array('Address', 'isEMail', TRUE, 'input', 200),
          'mailingoutput_subscribers' =>
            array(
              'Subscribers list', 'isNum', TRUE, 'combo',
              PapayaUtilArray::merge(
                array(0 => $this->_gt('[None]')), $this->getNewsletterNames()
              )
            ),
          'Views',
          'mailingoutput_text_view' =>
            array('Text', 'isNum', FALSE, 'combo', $possibleViews['text']),
          'mailingoutput_html_view' =>
            array('HTML', 'isNum', FALSE, 'combo', $possibleViews['html']),
        );
        $title = 'Properties';
        break;
      }
      $this->mailingOutputDialog = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->mailingOutputDialog->loadParams();
      $this->mailingOutputDialog->inputFieldSize = 'x-large';
      $this->mailingOutputDialog->dialogDoubleButtons = TRUE;
      $this->mailingOutputDialog->buttonTitle = ($newOutput) ? 'Add' : 'Save';
      $this->mailingOutputDialog->dialogTitle = $this->_gt($title);
    }
  }

  /**
  * initialize mailing view dialog
  *
  * @param boolean $newView optional, default value FALSE
  * @access private
  */
  function initializeMailingViewForm($newView = FALSE) {
    if (!(isset($this->mailingViewDialog) && is_object($this->mailingViewDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if ($newView || !isset($this->oneMailingView)) {
        $data = array();
        $hidden = array(
          'cmd' => 'new_view',
          'save' => 1,
          'mailingview_mode' => (int)$this->params['mailingview_mode'],
          'mode' => @$this->params['mode']
        );
      } else {
        $data = $this->oneMailingView;
        $hidden = array(
          'cmd' => 'edit_view',
          'save' => 1,
          'mailingview_id' => (int)$this->params['mailingview_id'],
          'mailingview_mode' => (int)$this->params['mailingview_mode'],
          'mode' => @$this->params['mode']
        );
      }

      $fields = array(
        'mailingview_title' =>
          array('Title', 'isNoHTML', TRUE, 'input', 200),
        'mailingview_type' =>
          array('Type', 'isNum', TRUE, 'combo', $this->formats)
      );

      $this->mailingViewDialog =
        new base_dialog($this, $this->paramName, $fields, $data, $hidden);
      $this->mailingViewDialog->loadParams();
      $this->mailingViewDialog->inputFieldSize = 'x-large';
      $this->mailingViewDialog->buttonTitle = ($newView) ? 'Add' : 'Save';
      $this->mailingViewDialog->dialogTitle = $this->_gt('Properties');
    }
  }

  /**
  * initialize mailing view properties dialog
  *
  * @param $mailingViewId
  * @access private
  */
  function initializeMailingViewPropertiesForm($mailingViewId) {
    if (!(
          isset($this->mailingViewPropertiesObj) &&
          is_object($this->mailingViewPropertiesObj)
         )
       ) {
      $moduleObj = $this->getOutputFilter($mailingViewId);
      if (isset($moduleObj) && is_object($moduleObj)) {
        $moduleObj->images = $this->images;
        $moduleObj->paramName = $this->paramName;

        $hidden = array(
          'mailingview_id' => $mailingViewId,
          'mailingview_mode' => $this->params['mailingview_mode'],
          'mode' => $this->params['mode'],
          'cmd' => 'edit_viewproperties'
        );

        $moduleObj->initializeDialog($hidden);
        $this->mailingViewPropertiesObj = $moduleObj;
      }
    }
  }

  /**
  * initialize newsletter list dialog
  *
  * @param boolean $newList optional, default value FALSE
  * @access private
  */
  function initializeNewsletterListForm($newList = FALSE) {
    if (!(isset($this->listDialog) && is_object($this->listDialog))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if ($newList) {
        $data = array();
        $hidden = array(
          'cmd' => 'add_list',
          'save' => 1,
          'mode' => (int)$this->params['mode'],
        );
      } else {
        $data = $this->newsletterList;
        $hidden = array(
          'cmd' => 'edit_list',
          'save' => 1,
          'mode' => (int)$this->params['mode'],
        );
      }

      $strNone = $this->_gt('None');
      $possibleViews = array(
        'text' => array(0 => $strNone),
        'html' => array(0 => $strNone)
      );

      if (isset($this->views) && is_array($this->views)) {
        foreach ($this->views as $view) {
          if ((isset($viewType) && $viewType == $view['mailingview_type']) ||
              !isset($viewType)) {
            switch ($view['mailingview_type']) {
            case 1 :
              $possibleViews['html'][$view['mailingview_id']] =
                $view['mailingview_title'];
              break;
            case 0 :
            default :
              $possibleViews['text'][$view['mailingview_id']] =
                $view['mailingview_title'];
              break;
            }
          }
        }
      }

      $fields = array(
        'newsletter_list_name' =>
          array('Title', 'isNoHTML', TRUE, 'input', 200),
        'newsletter_list_description' =>
          array('Description', 'isNoHTML', FALSE, 'textarea', 5),
        'newsletter_list_format' =>
          array('Default format', 'isNum', TRUE, 'combo', $this->formats)
      );

      $this->listDialog = new base_dialog($this, $this->paramName, $fields, $data, $hidden);
      $this->listDialog->dialogTitle = $this->_gt('Properties');
      $this->listDialog->expandPapayaTags = TRUE;
      $this->listDialog->loadParams();
    }
  }

  /**
  * add newsletter list dialog to layout object
  *
  * @access public
  */
  function getXMLNewsletterListForm() {
    $this->initializeNewsletterListForm();
    $this->layout->add($this->listDialog->getDialogXML());
  }

  /**
  * Get pages navigation
  *
  * @param integer $offset current offset
  * @param integer $step offset step
  * @param integer $max max offset
  * @param integer $groupCount page link count
  * @param string $paramName offset param name
  * @return string button xml
  * @access private
  */
  function getListViewNav($offset, $step, $max, $groupCount = 9, $paramName = 'offset') {
    include_once(PAPAYA_INCLUDE_PATH.'system/papaya_paging_buttons.php');
    return papaya_paging_buttons::getPagingButtons(
      $this,
      array('cmd' => 'show', 'mode' => @$this->params['mode']),
      $offset,
      $step,
      $max,
      $groupCount,
      $paramName
    );
  }

  /**
  * Generates list of letters to filter list by.
  *
  * @return string Links with character as link label
  */
  function getCharBtns() {
    $result = '';
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $charCount = strlen($chars);
    for ($i = 0; $i < $charCount; $i++) {
      $result .= sprintf(
        '<a href="%s?%s[patt]=%s*&%s[mode]=%d">%s</a> '.LF,
        papaya_strings::escapeHTMLChars($this->baseLink),
        papaya_strings::escapeHTMLChars($this->paramName),
        papaya_strings::escapeHTMLChars($chars[$i]),
        papaya_strings::escapeHTMLChars($this->paramName),
        papaya_strings::escapeHTMLChars(@$this->params['mode']),
        papaya_strings::escapeHTMLChars($chars[$i])
      );
      if ($i == 13) {
        $result .= '<br />';
      }
    }
    $result .= sprintf(
      '<br /><a href="%s?%s[mode]=%d&%s[patt]=">%s</a> '.LF,
      papaya_strings::escapeHTMLChars($this->baseLink),
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars(@$this->params['mode']),
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars($this->_gt('All'))
    );
    return $result;
  }

  /**
  * Delete subscriber
  *
  * @param string $email
  * @access public
  * @return mixed FALSE or number of affected_rows or database result object
  */
  function deleteSubscriber($subcriberId) {
    $filter = array('subscriber_id' => $subcriberId);
    return
      FALSE !== $this->databaseDeleteRecord($this->tableSubscriptions, $filter) &&
      FALSE !== $this->databaseDeleteRecord($this->tableProtocol, $filter) &&
      FALSE !== $this->databaseDeleteRecord($this->tableSubscribers, $filter);
  }

  /**
  * Delete Subscriptions
  *
  * @access public
  * @return boolean
  */
  function deleteSubscriptions($newsletterListId) {
    $filter = array('newsletter_list_id' => $newsletterListId);
    if (FALSE !== $this->databaseDeleteRecord($this->tableProtocol, $filter) &&
        FALSE !== $this->databaseDeleteRecord($this->tableSubscriptions, $filter)) {
      return $this->deleteUnsubscribed();
    }
    return FALSE;
  }

  /**
  * Delete Subscribers without Subscriptions
  *
  * @access public
  * @return boolean
  */
  function deleteUnsubscribed() {
    $subscriberIds = array();
    $sql = "SELECT sr.subscriber_id FROM %s AS sr
              LEFT OUTER JOIN %s AS sn
                ON (sr.subscriber_id = sn.subscriber_id)
             WHERE sn.subscriber_id IS NULL";
    $params = array($this->tableSubscribers, $this->tableSubscriptions);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $subscriberIds[] = $row['subscriber_id'];
      }
      return FALSE !== $this->databaseDeleteRecord(
        $this->tableSubscribers, array('subscriber_id' => $subscriberIds)
      );
    }
  }

  /**
  * generate import dialog
  */
  function getXMLImportDialog() {
    if (isset($this->importDialog) && is_object($this->importDialog)) {
      if (get_class($this->importDialog) == 'base_dialog') {
        $this->layout->add($this->importDialog->getDialogXML());
      } elseif (get_class($this->importDialog) == 'base_msgdialog') {
        $this->layout->add($this->importDialog->getMsgDialog());
      } else {
        $this->getXMLImportCSVForm();
      }
    } elseif (isset($this->importDialog) && is_string($this->importDialog)) {
      $this->layout->add($this->importDialog);
    } elseif (@$this->params['step'] != 3) {
      $this->getXMLImportCSVForm();
    }
  }

  /**
  * create form to upload cvs file
  *
  * @access public
  * @return string XML of upload form for CSV files
  */
  function getXMLImportCSVForm() {
    $result = '';
    if ($this->module->hasPerm(5)) {
      $result .= sprintf(
        '<dialog action="%s" title="%s" width="100%%"'.
        ' type="file" enctype="multipart/form-data">'.LF,
        papaya_strings::escapeHTMLChars($this->baseLink),
        papaya_strings::escapeHTMLChars($this->_gt('CSV import'))
      );
      if (isset($this->selectedFile)) {
        $fileName = papaya_strings::escapeHTMLChars($this->selectedFile['file_id']);
      } else {
        $fileName = '';
      }
      $result .= sprintf(
        '<input type="hidden" name="%s[file]" value="%s" />'.LF,
        papaya_strings::escapeHTMLChars($this->paramName),
        papaya_strings::escapeHTMLChars($fileName)
      );
      $result .= sprintf(
        '<input type="hidden" name="MAX_FILE_SIZE" value="%s" />'.LF,
        papaya_strings::escapeHTMLChars($this->maxSize)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[step]" value="1" />'.LF,
        papaya_strings::escapeHTMLChars($this->paramName)
      );
      $result .= sprintf(
        '<input type="hidden" name="%s[cmd]" value="%s" />'.LF,
        papaya_strings::escapeHTMLChars($this->paramName),
        papaya_strings::escapeHTMLChars($this->params['cmd'])
      );
      $result .= '<lines>';
      $result .= sprintf(
        '<line caption="%s">',
        papaya_strings::escapeHTMLChars($this->_gt('Upload'))
      );
      $result .= sprintf(
        '<input type="file" size="40" class="file" name="%s[import_csv]" />'.LF,
        papaya_strings::escapeHTMLChars($this->paramName)
      );
      $result .= '</line>';
      $result .= '</lines>';
      $result .= sprintf(
        '<dlgbutton value="%s"/>'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Upload'))
      );
      $result .= '</dialog>'.LF;
    }
    $this->layout->add($result);
  }

  /**
  * get file extension
  *
  * @param string $fileName input filename
  * @access public
  * @return string $result file extension or '' if not found
  */
  function getExtension($fileName) {
    if (preg_match('/\.[^\.]+$/', $fileName, $match)) {
      return $match[0];
    } else {
      return '';
    }
  }

  /**
  * Check for valid file
  *
  * @access public
  * @return boolean
  */
  function checkFile() {
    if (isset($_FILES[$this->paramName]['tmp_name'])
        && isset($_FILES[$this->paramName]['name'])) {
      $tempFileName = $_FILES[$this->paramName]['tmp_name']['import_csv'];
      $tempFileTitle = $_FILES[$this->paramName]['name']['import_csv'];
      if (@file_exists($tempFileName) && @is_uploaded_file($tempFileName)) {
        $tempFileSize = @filesize($tempFileName);
        if ($tempFileSize > 0 && $tempFileSize < $this->maxSize) {
          $ext = $this->getExtension($tempFileTitle);
          if ($ext == '.csv') {
            return TRUE;
          } else {
            $this->addMsg(MSG_ERROR, $this->_gt('Invalid file type!'));
            @unlink($tempFileName);
          }
        } else {
          $this->addMsg(
            MSG_ERROR,
            sprintf($this->_gt('File "%s" is to large.'), $tempFileTitle)
          );
          @unlink($tempFileName);
        }
      }
    }
    return FALSE;
  }

  /**
  * perform the steps of a csv import: file upload, mapping, confirmation, import
  *
  * @since 2006-11-15
  */
  function processCSVUpload() {
    if (@$this->params['mode'] == 0) {
      switch (@$this->params['step']) {
      case 1:
        if (isset($_FILES[$this->paramName]['tmp_name']) &&
            isset($_FILES[$this->paramName]['name'])) {
          if ($this->checkFile()) {
            $tempFileName = $_FILES[$this->paramName]['tmp_name']['import_csv'];
            $fileData = file_get_contents($tempFileName);
            $cacheId = md5($tempFileName);
            $this->sessionParams['cache_id'] = $cacheId;
            $this->setSessionValue($this->sessionParamName, $this->sessionParams);
            $cacheFileName = PAPAYA_PATH_CACHE.'.nl_csv_'.$cacheId.'.csv';
            @unlink($tempFileName);
            if ($fp = fopen($cacheFileName, 'w+')) {
              fwrite($fp, $fileData);
              fclose($fp);
            } else {
              $this->addMsg(MSG_ERROR, $this->_gt('Couldn\'t copy temporary file.'));
            }
            $this->initializeImportMappingForm(
              $cacheId, $this->guessCSVStyle($cacheFileName)
            );
          }
        }
        break;
      case 2:
        $this->getXMLImportPreview();
        $this->initializeImportConfirmDialog();
        break;
      case 3:
        $this->getProcessImportRPCXML();
        break;
      }
    }
  }

  /**
  * generate form for mapping of csv fields to database fields
  *
  * @param string $cacheId cachefile id
  * @param array $csvStyle {@link papaya_newsletter::guessCSVStyle() }
  */
  function initializeImportMappingForm($cacheId, $csvStyle) {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
    $data = array();
    $hidden = array(
      'cmd' => 'import_csv',
      'step' => 2,
      'mode' => @$this->params['mode'],
    );

    if (isset($cacheId)) {
      if (file_exists(PAPAYA_PATH_CACHE.'.nl_csv_'.$cacheId.'.csv')) {
        $fp = fopen(PAPAYA_PATH_CACHE.'.nl_csv_'.$cacheId.'.csv', 'r');
        $line = fgetcsv($fp, 8096, $csvStyle['separator'], $csvStyle['delimiter']);
      }
    }
    $import = array(-1 => '-- '.$this->_gt('ignore').' --');

    $arrayPos = 0 - count($this->formats);
    $importFormat = NULL;
    foreach ($this->formats as $format) {
      $importFormat[$arrayPos] = '-- '.$format.' --';
      $arrayPos++;
    }

    foreach ($line as $v) {
      $import[] = $v;
      $importFormat[] = $v;
    }

    foreach ($this->newsletterLists as $mailingList) {
      $mailingLists[$mailingList['newsletter_list_id']] = $mailingList['newsletter_list_name'];
    }

    $fields = array(
      'Presets',
      'import_newsletter_list_id' => array('Mailing list', 'isNoHTML', TRUE,
        'combo', $mailingLists, '', @$this->params['newsletter_list_id']),
      'import_surfer_status' => array('Status', 'isNoHTML', TRUE,
        'combo', $this->status, '', 2),
      'import_dups_mode' => array('Duplicate mode', 'isNum', TRUE,
        'combo', $this->dupsModes, '', 'ignore'),
      'Mapping',
      'import_format' => array('Output format', 'isNoHTML', TRUE, 'combo',
        $importFormat, '', 0 - count($this->formats)),
      'import_email' => array('Email', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_salutation' => array('Salutation', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_first_name' => array('First name', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_last_name' => array('Last name', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_branch' => array('Branch', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_firm' => array('Firm', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_position' => array('Position', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_section' => array('Section', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_title' => array('title', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_street' => array('Street', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_house_number' => array('House number', 'isNoHTML', TRUE, 'combo',
        $import, '', -1),
      'import_zip' => array('Zip', 'isNoHTML', TRUE, 'combo', $import, '', -1),
      'import_city' => array('City', 'isNoHTML', TRUE, 'combo', $import, '', -1),
      'import_phone' => array('Phone', 'isNoHTML', TRUE, 'combo', $import, '', -1),
      'import_mobil' => array('Mobile', 'isNoHTML', TRUE, 'combo', $import, '', -1),
      'import_fax' => array('Fax', 'isNoHTML', TRUE, 'combo', $import, '', -1),
    );

    $this->importDialog = new base_dialog(
      $this, $this->paramName, $fields, $data, $hidden
    );
    $this->importDialog->loadParams();
    $this->importDialog->inputFieldSize = 'xlarge';
    $this->importDialog->buttonTitle = 'Import';
    $this->importDialog->dialogTitle =
      papaya_strings::escapeHTMLChars($this->_gt('Define mapping'));
  }

  /**
  * generate confirmation dialog for import
  */
  function initializeImportConfirmDialog() {
    /*include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
    $hidden = array(
      'cmd' => 'import_csv',
      'step' => 3,
      'mode' => isset($this->params['mode']) ? $this->params['mode'] : '',
      'import_newsletter_list_id' => $this->params['import_newsletter_list_id'],
      'import_surfer_status' => $this->params['import_surfer_status'],
      'import_dups_mode' => $this->params['import_dups_mode'],
    );
    $msg = '';
    $this->importDialog = new base_msgdialog(
      $this, $this->paramName, $hidden, $msg, 'question'
    );
    $this->importDialog->baseLink = $this->baseLink;
    $this->importDialog->buttonTitle = 'Yes';*/
    $this->importDialog = $this->getProcessImportScript();
    $this->importDialog .= sprintf(
      '<msgdialog title="%s" width="100%%" action="#" type="question">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Question'))
    );
    $this->importDialog .= sprintf(
      '<message>%s</message>'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Is this correct?'))
    );
    $this->importDialog .= sprintf(
      '<dlgbutton value="%s" onclick="processImport();return false;" />'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Yes'))
    );
    $this->importDialog .= '</msgdialog>'.LF;
  }

  /**
  * generate previes of what would be imported
  *
  * @return string Import preview xml
  */
  function getXMLImportPreview() {
    $result = '';
    $result .= sprintf(
      '<listview title="%s">'.LF,
      papaya_strings::escapeHTMLChars(
        sprintf(
          $this->_gt('This would be the result of an import (first %d entries):'),
          $this->importPreviewCount
        )
      )
    );

    $fieldNames = array(
      'import_email' => 'Email',
      'import_salutation' => 'Salutation',
      'import_first_name' => 'First name',
      'import_last_name' => 'Last name',
      'import_branch' => 'Branch',
      'import_firm' => 'Firm',
      'import_position' => 'Position',
      'import_section' => 'Section',
      'import_title' => 'Title',
      'import_street' => 'Street',
      'import_house_number' => 'House number',
      'import_zip' => 'Zip',
      'import_city' => 'City',
      'import_phone' => 'Phone',
      'import_mobil' => 'Mobile',
      'import_fax' => 'Fax',
    );

    $importFields = array();
    foreach ($this->params as $k => $v) {
      if (isset($fieldNames[$k]) && $v >= 0) {
        $importFields[$k] = $v;
      }
    }

    $this->sessionParams['import_data'] = $importFields;
    $this->sessionParams['import_format'] = $this->params['import_format'];
    $this->setSessionValue($this->sessionParamName, $this->sessionParams);

    if (isset($this->params['cache_id'])) {
      $cacheFileName = PAPAYA_PATH_CACHE.'.nl_csv_'.$this->params['cache_id'].'.csv';
      $emailInCSV = array();
      if (file_exists($cacheFileName)) {
        $csvStyle = $this->guessCSVStyle($cacheFileName);
        $result .= '<cols>'.LF;
        $result .= sprintf(
          '<col>%s</col>'.LF,
          papaya_strings::escapeHTMLChars($this->_gt('Id'))
        );
        $result .= sprintf(
          '<col>%s</col>'.LF,
          papaya_strings::escapeHTMLChars($this->_gt('Output format'))
        );
        foreach ($importFields as $field => $rowId) {
          $result .= sprintf('<col>%s</col>'.LF, $this->_gt($fieldNames[$field]));
        }
        $result .= '</cols>'.LF;
        if ($fp = fopen($cacheFileName, 'r')) {
          $result .= '<items>';
          $id = 1;
          for ($i = 0; $i < $this->importPreviewCount; $i++) {
            $row = fgetcsv($fp, 8192, $csvStyle['separator'], $csvStyle['delimiter']);
            if (is_array($row) && $row) {
              if (isset($row[$this->params['import_email']]) &&
                  checkit::isEmail($row[$this->params['import_email']], TRUE)
                  && !isset($emailInCSV[strtolower($row[$this->params['import_email']])])) {
                if ($this->params['import_format'] < 0) {
                  $format = $this->formats[$this->params['import_format'] +
                    count($this->formats)];
                } else {
                  $format = $row[$this->params['import_format']];
                }
                $emailInCSV[strtolower($row[$this->params['import_email']])] = 1;
                $result .= sprintf('<listitem title="%d">'.LF, $id++);
                $result .= sprintf('<subitem>%s</subitem>'.LF, $format);
                foreach ($importFields as $field => $rowId) {
                  if ($field == 'import_salutation') {
                    if (isset($this->salutationMapping[$row[$rowId]])) {
                      $salutation =
                        $this->salutations[$this->salutationMapping[$row[$rowId]]];
                    } else {
                      $salutation = $this->_gt('Unknown');
                    }
                    $result .= sprintf(
                      '<subitem>%s</subitem>'.LF,
                      papaya_strings::escapeHTMLChars($salutation)
                    );
                  } else {
                    $result .= sprintf(
                      '<subitem>%s</subitem>'.LF,
                      papaya_strings::escapeHTMLChars($row[$rowId])
                    );
                  }
                }
                $result .= '</listitem>';
              } else {
                $this->importPreviewCount++;
              }
            }
          }
          $result .= '</items>';
        }
      }
    }
    $result .= '</listview>'.LF;
    $result .= sprintf(
      '<listview title="%s">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Default values'))
    );
    $result .= '<items>';
    $result .= sprintf(
      '<listitem title="%s">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('List'))
    );
    $listId = $this->params['import_newsletter_list_id'];
    $result .= sprintf(
      '<subitem>%s</subitem>'.LF,
      papaya_strings::escapeHTMLChars($this->newsletterLists[$listId]['newsletter_list_name'])
    );
    $result .= '</listitem>'.LF;
    $result .= sprintf(
      '<listitem title="%s">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Status'))
    );
    $result .= sprintf(
      '<subitem>%s</subitem>'.LF,
      papaya_strings::escapeHTMLChars($this->status[$this->params['import_surfer_status']])
    );
    $result .= '</listitem>'.LF;
    $result .= sprintf(
      '<listitem title="%s">'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Duplicate mode'))
    );
    $result .= sprintf(
      '<subitem>%s</subitem>'.LF,
      papaya_strings::escapeHTMLChars($this->dupsModes[$this->params['import_dups_mode']])
    );
    $result .= '</listitem>'.LF;
    $result .= '</items>';
    $result .= '</listview>'.LF;
    $this->layout->add($result);
  }

  /**
  * generate import XML including javascript for rpc
  *
  * @return string script element with embedded javascript for rpc
  */
  function getProcessImportScript() {
    $result = '<script type="text/javascript" src="script/xmlrpc.js"></script>'.LF;
    $result .= '<script type="text/javascript">
var importPosition = 0;
var duplicates = 0;

function requestProcessImport(offset) {
  var url = \''.$this->baseLink.'?'.$this->paramName.'[cmd]=import_csv&'.$this->paramName.
    '[mode]=0&'.$this->paramName.'[step]=3&'.$this->paramName.'[import_offset]=\'+offset;
  loadXMLDoc(url, true);
}

function rpcSetProcessImport(data, params) {
  var responseArray = new Array();
  if (params) {
    for (var i = 0; i &lt; params.length; i++) {
      var paramName = \'\';
      var paramValue = \'\';
      for (var x = 0; x &lt; 2; x++) {
        if (params[i].attributes[x].name == \'name\') {
          paramName = params[i].attributes[x].value;
        } else if (params[i].attributes[x].name == \'value\') {
          paramValue = params[i].attributes[x].value;
        }
      }
      responseArray[paramName] = paramValue;
    }
    if (responseArray.countDone != null && responseArray.countTotal != null
        && responseArray.countDuplicates != null) {
      responseArray.countDone       = parseInt(responseArray.countDone);
      responseArray.countTotal      = parseInt(responseArray.countTotal);
      responseArray.countDuplicates = parseInt(responseArray.countDuplicates);
      if (isNaN(responseArray.countDone)) {
        responseArray.countDone = 0;
      }
      if (isNaN(responseArray.countTotal)) {
        responseArray.countTotal = 0;
      }
      if (isNaN(responseArray.countDuplicates)) {
        responseArray.countDuplicates = 0;
      }
      importPosition += responseArray.countDone;
      var importCount = responseArray.countTotal;
      duplicates     += responseArray.countDuplicates;
      updateImportStatus(importPosition.toString() + \'/\' +
        importCount.toString() + \'; \' +
        duplicates.toString() + \' '.$this->_gt('duplicates').'\',
        Math.floor(importPosition * 100 / importCount));
      if (importPosition &lt; importCount && importPosition &gt; 0) {
        window.setTimeout(\'requestProcessImport(\'+importPosition+\')\', 300);
      } else {
        updateImportStatus(\''.$this->_gt('Done').'! \', 100);
      }
    } else {
      updateImportStatus(\'Params Error\', 0);
    }
  } else {
    updateImportStatus(\'Error\', 0);
  }
}

function updateImportStatus(labelText, barPosition) {
  PapayaLightBox.update(labelText, barPosition);
}

function processImport() {
  // here goes the initialization stuff for the overlay dialog, i.e. what to do
  // on 100% status: reload newsletter admin page
  PapayaLightBox.init(\''.
    $this->_gt('Importing newsletter recipients').'\', \''.$this->_gt('Close').'\');
  PapayaLightBox.update(\''.$this->_gt('Requesting...').'\', 0);
  requestProcessImport(0);
}

</script>';
    $this->layout->addScript($result);

  }

  /**
  * generate import xml for rpc
  */
  function getProcessImportRPCXML() {
    $fieldMapping = array(
      'import_email' => 'subscriber_email',
      'import_salutation' => 'subscriber_salutation',
      'import_title' => 'subscriber_title',
      'import_first_name' => 'subscriber_firstname',
      'import_last_name' => 'subscriber_lastname',
      'import_branch' => 'subscriber_branch',
      'import_firm' => 'subscriber_company',
      'import_position' => 'subscriber_position',
      'import_section' => 'subscriber_section',
      'import_street' => 'subscriber_street',
      'import_house_number' => 'subscriber_housenumber',
      'import_zip' => 'subscriber_postalcode',
      'import_city' => 'subscriber_city',
      'import_phone' => 'subscriber_phone',
      'import_mobil' => 'subscriber_mobile',
      'import_fax' => 'subscriber_fax'
    );

    if (isset($this->sessionParams['cache_id'])
        && isset($this->sessionParams['import_data'])) {
      $importFields = $this->sessionParams['import_data'];
      $cacheFileName = PAPAYA_PATH_CACHE.'.nl_csv_'.$this->sessionParams['cache_id'].'.csv';
      $emailInCSV = array();
      $notInserted = 0;

      if (file_exists($cacheFileName)) {
        $csvStyle = $this->guessCSVStyle($cacheFileName);
        if ($fp = fopen($cacheFileName, 'r')) {
          $countDone = 0;
          $countTotal = 0;
          while ($row = fgetcsv($fp, 8192, $csvStyle['separator'], $csvStyle['delimiter'])) {
            if (isset($this->params['import_offset'])
                && $countTotal >= $this->params['import_offset']
                && $countTotal < $this->params['import_offset'] + $this->csvImportLimit) {
              $subscriberEmail = papaya_strings::strtolower($row[$importFields['import_email']]);
              $row[$importFields['import_email']] = $subscriberEmail;
              $entry = array(
                'subscriber_email' => '',
                'subscriber_salutation' => 0,
                'subscriber_title' => '',
                'subscriber_firstname' => '',
                'subscriber_lastname' => '',
                'subscriber_branch' => '',
                'subscriber_company' => '',
                'subscriber_position' => '',
                'subscriber_section' => '',
                'subscriber_street' => '',
                'subscriber_housenumber' => '',
                'subscriber_postalcode' => '',
                'subscriber_city' => '',
                'subscriber_phone' => '',
                'subscriber_mobile' => '',
                'subscriber_fax' => '',
                'subscriber_data' => ''
              );
              if (checkit::isEmail($subscriberEmail, TRUE)
                  && !isset($emailInCSV[$subscriberEmail])) {
                if ($this->sessionParams['import_format'] < 0) {
                  $this->subscriberFormats[$subscriberEmail] =
                    $this->formats[$this->sessionParams['import_format'] + count($this->formats)];
                } else {
                  $this->subscriberFormats[$subscriberEmail] =
                    $row[$this->sessionParams['import_format']];
                }
                foreach ($importFields as $field => $rowId) {
                  if ($field == 'import_salutation') {
                    if (isset($this->salutationMapping[$row[$rowId]])) {
                      $salutation = $this->salutationMapping[$row[$rowId]];
                    } else {
                      $salutation = -1;
                    }
                    $entry[$fieldMapping['import_salutation']] = $salutation;
                  } elseif (isset($fieldMapping[$field])) {
                    $entry[$fieldMapping[$field]] = papaya_strings::ensureUTF8($row[$rowId]);
                  } else {
                    $entry[$fieldMapping[$field]] = '';
                  }
                }
                $entry['subscriber_data'] = '';
                $entry['subscriber_chgtoken'] = '';
                $subscribersData[$subscriberEmail] = $entry;
              } elseif (isset($emailInCSV[$subscriberEmail])) {
                $this->duplicates[] = $subscriberEmail;
              }
              $countDone++;
            }
            if (isset($subscriberEmail)) {
              $emailInCSV[$subscriberEmail] = 1;
            }
            $countTotal++;
          }
          if (isset($subscribersData) && is_array($subscribersData)) {
            $this->addSubscribers($subscribersData);
          }
          // result xml
          $result = '<?xml version="1.0" encoding="utf-8"?>';
          $result .= '<response>';
          $result .= '<method>rpcSetProcessImport</method>';
          $result .= sprintf(
            '<param name="countDone" value="%d" />',
            (int)$countDone
          );
          $result .= sprintf(
            '<param name="countDuplicates" value="%d" />',
            count($this->duplicates)
          );
          $result .= sprintf(
            '<param name="countTotal" value="%d" />',
            (int)$countTotal
          );
          $result .= '<data></data>';
          $result .= '</response>';
          header('Content-type: text/xml; charset=utf-8');
          echo $result;
          exit;
        }
      }
    }
  }

  /**
  * add subscribers to database, add subscriptions and write protocol
  *
  * @param array $subscribersData
  */
  function addSubscribers($subscribersData) {
    if (isset($subscribersData) && is_array($subscribersData) &&
        count($subscribersData) > 0) {
      $notInserted = 0;
      $alreadyInList = 0;
      // find existing subscribers
      // get their email adresses first
      foreach ($subscribersData as $subscriber) {
        if (isset($subscriber['subscriber_email'])) {
          $subscribersEmail[] = $subscriber['subscriber_email'];
        }
      }
      // second create condition to find them
      $condition = $this->databaseGetSQLCondition('sb.subscriber_email', $subscribersEmail);
      $sql = "SELECT sb.subscriber_id, sb.subscriber_email, sp.newsletter_list_id
                FROM %s AS sb
                LEFT OUTER JOIN %s AS sp ON (sb.subscriber_id = sp.subscriber_id
                 AND sp.newsletter_list_id = %d)
               WHERE $condition
             ";
      $params = array($this->tableSubscribers, $this->tableSubscriptions,
        $this->params['import_newsletter_list_id']);
      if ($res = $this->databaseQueryFmt($sql, $params)) {
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
          $existingEmails[papaya_strings::strtolower($row['subscriber_email'])] = array(
            'subscriber_id' => $row['subscriber_id'],
            'not_in_list' => (NULL === $row['newsletter_list_id']),
          );
        }
      }

      foreach ($subscribersData as $subscriber) {
        // does the email address already exist?
        if (isset($existingEmails[$subscriber['subscriber_email']])) {
          // update subscriber data if requested
          $subscriberEmail = $subscriber['subscriber_email'];
          if (isset($this->params['import_dups_mode']) &&
              $this->params['import_dups_mode'] == 'update') {
            $condition = array(
              'subscriber_id' =>
                $existingEmails[$subscriberEmail]['subscriber_id']);
            $this->databaseUpdateRecord($this->tableSubscribers, $subscriber, $condition);
          }
          // is the subscriber already subscribed to this list?
          if ($existingEmails[$subscriber['subscriber_email']]['not_in_list']) {
            // if not, add subscriber to the list of subscribers to be subscribed
            $subscriberIds[] = $existingEmails[$subscriberEmail]['subscriber_id'];
            $subscriberEmails[$existingEmails[$subscriberEmail]['subscriber_id']] =
              $subscriberEmail;
          } else {
            // count those who are already subscribed to this list?
            $alreadyInList++;
          }
          // the email address does not yet exist
        } else {
          // add subscriber
          if ($subscriberId = $this->databaseInsertRecord(
                $this->tableSubscribers, 'subscriber_id', $subscriber)) {
            // keep the subscriberId for subscription below
            $subscriberIds[] = $subscriberId;
            $subscriberEmails[$subscriberId] = $subscriber['subscriber_email'];
          } else {
            // count failed insertions (shouldn't occur)
            $notInserted++;
          }
        }
      }

      // add subscriptions to subscribers
      if (isset($subscriberIds) && is_array($subscriberIds) &&
          count($subscriberIds) > 0) {
        foreach ($subscriberIds as $subscriberId) {
          $subscriberFormat =
            $this->getMappedFormatIndex(
              $this->subscriberFormats[$subscriberEmails[$subscriberId]]);
          $entry = array(
            'subscriber_id' => $subscriberId,
            'newsletter_list_id' => @$this->params['import_newsletter_list_id'],
            'subscription_status' => @$this->params['import_surfer_status'],
            'subscription_format' => $subscriberFormat
          );
          $subscriptionData[] = $entry;
        }
        if ($this->databaseInsertRecords($this->tableSubscriptions, $subscriptionData)) {
          $this->addMsg(
            MSG_INFO,
            sprintf($this->_gt('%d subscribers inserted.'), count($subscriberIds))
          );
          // add protocol entry
          foreach ($subscriberIds as $subscriberId) {
            $data = array(
              'subscriber_id' => $subscriberId,
              'newsletter_list_id' => $this->params['import_newsletter_list_id'],
              'protocol_action' => 4,
              'protocol_created' => time(),
              'protocol_confirmed' => time(),
              'subscriber_data' => ''

            );
            $protocolData[] = $data;
          }
          if (count($protocolData) > 0) {
            if ($this->databaseInsertRecords($this->tableProtocol, $protocolData)) {
              $this->addMsg(
                MSG_INFO,
                sprintf($this->_gt('%d protocol entries added.'), count($protocolData))
              );
            }
          }
          if (count($this->duplicates) > 0) {
            $this->addMsg(
              MSG_INFO,
              sprintf(
                $this->_gt('%d duplicates in CSV ignored.'),
                count($this->duplicates)
              )
            );
          }
        } else {
          $this->addMsg(MSG_ERROR, $this->_gt('Import failed!'));
        }
      }
      if ($notInserted > 0) {
        $this->addMsg(
          MSG_ERROR,
          sprintf($this->_gt('%d subscribers not inserted.'), $notInserted)
        );
      }
      if ($alreadyInList > 0) {
        $this->addMsg(
          MSG_INFO,
          sprintf($this->_gt('%d subscribers already in that list.'), $alreadyInList)
        );
      }
    }
  }

  /**
  * Import surfers into a mailing list
  *
  * Provides a form to filter and select surfers
  * you want to add to a mailing list.
  *
  * @access public
  * @author Sascha Kersken <info@papaya-cms.com>
  */
  function filterSurfers() {
    // Get surfers connector instance
    include_once(PAPAYA_INCLUDE_PATH.'system/base_pluginloader.php');
    $surfersObj = base_pluginloader::getPluginInstance('06648c9c955e1a0e06a7bd381748c4e4', $this);
    // Get the filter form
    $hidden = array(
      'cmd' => 'filter_results',
      'newsletter_list_id' => @$this->params['newsletter_list_id']
    );
    $surfersObj->getSearchDynamicForm($this->layout, $this->paramName, $hidden, 'Import surfers');
  }

  /**
  * Show filter surfers results
  *
  * Displays the result of the surfer filtering
  * and asks whether you want to add these surfers
  * to the current mailing list
  *
  * @access public
  * @author Sascha Kersken <info@papaya-cms.com>
  */
  function showFilterSurfersResults() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
    include_once(PAPAYA_INCLUDE_PATH.'system/base_pluginloader.php');
    $surfersObj = base_pluginloader::getPluginInstance('06648c9c955e1a0e06a7bd381748c4e4', $this);
    $surferIds = $surfersObj->showSurferResults($this->layout, $this->params, TRUE);
    if (!$surferIds) {
      // If there are no results, show a message
      $this->addMsg(MSG_INFO, 'No surfers found');
    } else {
      $listId = $this->params['newsletter_list_id'];
      // Otherwise, ask whether these surfers should be added to the mailing list
      $hidden = array(
        'cmd' => 'add_surfers',
        'import_newsletter_list_id' => $listId,
        'import_surfer_status' => 2,
        'surfer_ids' => $surferIds
      );
      $msg = sprintf(
        $this->_gt('Add these surfers to the mailing list "%s" (%d)?'),
        $this->newsletterLists[$listId]['newsletter_list_name'],
        $listId
      );
      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Yes';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * Add surfers to newsletter list
  *
  * Adds the surfers that were filtered
  * by arbitrary criteria to the current
  * newsletter list
  *
  * @access public
  * @author Sascha Kersken <info@papaya-cms.com>
  */
  function addSurfersToList() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_pluginloader.php');
    $surfersObj = base_pluginloader::getPluginInstance('06648c9c955e1a0e06a7bd381748c4e4', $this);
    // Check parameters
    $error = FALSE;
    if (!isset($this->params['import_newsletter_list_id']) ||
        trim($this->params['import_newsletter_list_id']) == '') {
      $error = TRUE;
      $this->addMsg(MSG_ERROR, $this->_gt('Please select a newsletter list'));
    }
    if (!isset($this->params['surfer_ids']) ||
        !is_array($this->params['surfer_ids']) ||
        empty($this->params['surfer_ids'])) {
      $error = TRUE;
      $this->addMsg(MSG_ERROR, $this->_gt('No surfers to add to list'));
    }
    if ($error) {
      return;
    }
    // Load relevant surfer data and add it to subscription array
    $surferIds = $this->params['surfer_ids'];
    $surferData = $surfersObj->loadSurferNames($surferIds);
    $subscriberData = array();
    $mails = array();
    foreach ($surferData as $record) {
      // Only add record if -- at least -- the email address is valid
      // and if the address is no duplicate
      if (isset($record['surfer_email']) &&
          trim($record['surfer_email']) != '' &&
          checkit::isEMail($record['surfer_email'])) {
        if (empty($subscriberData) || !in_array($record['surfer_email'], $mails)) {
          $subscriberData[] = array(
            'subscriber_email' => $record['surfer_email'],
            'subscriber_salutation' => (@$record['surfer_gender'] == 'm') ? 0 : 1,
            'subscriber_firstname' => trim(@$record['surfer_givenname']) != ''
              ? $record['surfer_givenname'] : $record['surfer_handle'],
            'subscriber_lastname' => @$record['surfer_surname']
          );
          $mails[] = $record['surfer_email'];
        }
      }
    }
    // Add the subscriptions
    if (!empty($subscriberData)) {
      $this->addSubscribers($subscriberData);
    } else {
      $this->addMsg(MSG_WARNING, $this->_gt('No valid subscriber email addresses'));
    }
  }

  /**
  * get index of format
  *
  * @param string $format
  * @access public
  */
  function getMappedFormatIndex($format) {
    $format = strtoupper($format);
    foreach ($this->formatMapping as $value => $key) {
      if (strtoupper($value) == $format) {
        return $key;
      }
    }
    return 0;
  }

  /**
  * Try to find out which csv separator and delimiter are used for a file
  *
  * @param string $fileName file location
  * @return array $result contains 'separator' and 'delimiter'
  */
  function guessCSVStyle($fileName) {
    $result = array(
      'separator' => ',',
      'delimiter' => '"',
    );
    if ($fp = fopen($fileName, 'r')) {
      $line1 = fgets($fp, 8096);
      $line2 = fgets($fp, 8096);
      $separator = $this->getFirstChar($line2, array(',', ';', "\t"));
      if (isset($separator) && $separator != '') {
        $result['separator'] = $separator;
      }
      $delimiter = $this->getFirstChar($line2, array('"', "'"));
      if (isset($delimiter) && $delimiter != '') {
        $result['delimiter'] = $delimiter;
      }
      fclose($fp);
    }
    return $result;
  }

  /**
  * get that char of a list of chars that occurs firstly in a string
  *
  * @param string $str string to check
  * @param array $chars array of chars
  * @return string $char that occurs firstly
  */
  function getFirstChar($str, $chars) {
    foreach ($chars as $i => $char) {
      $position = strpos($str, $char);
      // if string doesn't contain char, 0 is returned -> check if str[0] is char
      if ($position > 0 || $str[0] == $char) {
        $charPos[$position] = $char;
      }
    }
    if (isset($charPos) && is_array($charPos) && count($charPos) > 0) {
      // order chars by position
      ksort($charPos);
      // result is char with lowest position
      $result = (string)array_shift($charPos);
      return $result;
    }
    return '';
  }

  /**
  *
  * @param unknown_type $fileName
  */
  function outputExportHeaders($fileName) {
    $agentStr = strtolower(@$_SERVER["HTTP_USER_AGENT"]);
    if (strpos($agentStr, 'opera') !== FALSE) {
      $agent = 'OPERA';
    } elseif (strpos($agentStr, 'msie') !== FALSE) {
      $agent = 'IE';
    } else {
      $agent = 'STD';
    }
    $mimeType = ($agent == 'IE' || $agent == 'OPERA')
      ? 'application/octetstream'
      : 'application/octet-stream';
    if ($agent == 'IE') {
      header('Content-Disposition: inline; filename="'.$fileName.'"');
    } else {
      header('Content-Disposition: attachment; filename="'.$fileName.'"');
    }
    header('Content-type: ' . $mimeType);
  }

  /**
  * Excape some characters from string
  *
  * @param string $str
  * @access public
  * @return string
  */
  function escapeForCSV($str) {
    return '"'.str_replace('"', '""', $str).'"';
  }

  /**
   * Export subscription data to XLS
   *
   * @param boolean $details optional, default FALSE
   */
  function exportSubscriptionListXls($details = FALSE) {
    $fileName = 'listdata_'.@(int)$this->params['newsletter_list_id'].
      '_'.date('Y-m-d').'.xls';
    $csv = $this->generateCsv($details);
    $xls = new export_xls();
    $xls->fromCsv($csv);
    $xls->download($fileName);
    exit();
  }

  /**
   * Export subscription data to csv
   *
   * @param boolean $details optional, default FALSE
   */
  function exportSubscriptionList($details = FALSE) {
    $fileName = 'listdata_'.@(int)$this->params['newsletter_list_id'].
      '_'.date('Y-m-d').'.csv';
    $this->outputExportHeaders($fileName);
    $csvArr = $this->generateCsv($details);
    echo implode("\r\n", $csvArr);
    flush();
    exit;
  }

  /**
   * Generate CSV export data
   *
   * @param boolean $details optional, default FALSE
   * @return array
   */
  function generateCsv($details = FALSE) {
    $dataExport = '';
    $dataArray = array();
    $this->loadSubscriptionsListForExport(
      @(int)$this->params['newsletter_list_id'], $details);
    if (isset($this->subscriptionsExport) && is_array($this->subscriptionsExport)) {
      if ($details) {

        $fields = array(
          'subscriber_email' => $this->_gt('Email'),
          'subscription_format' => array($this->_gt('Format'), $this->formats),
          'subscription_status' => array($this->_gt('Status'), $this->status),
          'subscriber_salutation' => array($this->_gt('Salutation'), $this->salutations),
          'subscriber_title' => $this->_gt('Title'),
          'subscriber_firstname' => $this->_gt('First name'),
          'subscriber_lastname' => $this->_gt('Last name'),
          'subscriber_branch' => $this->_gt('Branch'),
          'subscriber_company' => $this->_gt('Company'),
          'subscriber_position' => $this->_gt('Position'),
          'subscriber_section' => $this->_gt('Section'),
          'subscriber_street' => $this->_gt('Street'),
          'subscriber_housenumber' => $this->_gt('House number'),
          'subscriber_postalcode' => $this->_gt('Postal code'),
          'subscriber_city' => $this->_gt('City'),
          'subscriber_phone' => $this->_gt('Phone'),
          'subscriber_mobile' => $this->_gt('Mobile'),
          'subscriber_fax' => $this->_gt('Fax'),
          'protocol_created' => $this->_gt('created'),
          'protocol_confirmed' => $this->_gt('confirmed'),
        );
      } else {
        $fields = array(
          'subscriber_email' => $this->_gt('Email'),
          'subscription_format' => array($this->_gt('Format'), $this->formats),
          'subscriber_salutation' => array($this->_gt('Salutation'), $this->salutations),
          'subscriber_firstname' => $this->_gt('First name'),
          'subscriber_lastname' => $this->_gt('Last name'),
        );
      }

      $first = TRUE;
      $dataExport = '';
      foreach ($fields as $field => $data) {
        if ($first) {
          $first = FALSE;
        } else {
          $dataExport .= ',';
        }
        if (is_array($data)) {
          $dataExport .= $this->escapeForCSV($data[0]);
        } else {
          $dataExport .= $this->escapeForCSV($data);
        }
      }
      $dataArray[] = $dataExport;

      $timestampFields = array();
      if ($details) {
        $timestampFields = array(18, 19);
      }
      foreach ($this->subscriptionsExport as $subscription) {
        $first = TRUE;
        $dataExport = '';
        $count = 0;
        foreach ($fields as $field => $data) {
          if ($first) {
            $first = FALSE;
          } else {
            $dataExport .= ',';
          }
          if (is_array($data)) {
            $dataExport .= $this->escapeForCSV($data[1][$subscription[$field]]);
          } else {
            if ($details && in_array($count, $timestampFields) &&
              $this->is_timestamp((int)$subscription[$field]) && (int)$subscription[$field] > 0) {
              $dataExport .= date("d.m.Y", $subscription[$field]);
            } else {
              $dataExport .= $this->escapeForCSV($subscription[$field]);
            }
          }
          $count++;
        }
        $dataArray[] = $dataExport;
      }
      return $dataArray;
    }
  }

  /**
   * Check whether an integer value is a valid timestamp
   *
   * @param mixed $timestamp The value to check for
   * @return boolean TRUE if timestamp, FALSE otherwise
   */
  function is_timestamp($timestamp) {
    $check = (is_int($timestamp) OR is_float($timestamp))
      ? $timestamp
      : (string)(int)$timestamp;

    return ($check === $timestamp)
    && ((int)$timestamp <= PHP_INT_MAX)
    && ((int)$timestamp >= ~PHP_INT_MAX);
  }

  /**
  * save mailing group data
  *
  * @param integer $mailingGroupId
  * @param array $values
  * @access private
  * @return boolean
  */
  function saveMailingGroup($mailingGroupId, $values) {
    $data = array(
      'mailinggroup_title' =>
        (string)$values['mailinggroup_title'],
      'lng_id' =>
        (int)$values['lng_id'],
      'mailinggroup_default_subject' =>
        (string)$values['mailinggroup_default_subject'],
      'mailinggroup_default_sender' =>
        (string)$values['mailinggroup_default_sender'],
      'mailinggroup_default_senderemail' =>
        (string)$values['mailinggroup_default_senderemail'],
      'mailinggroup_mode' =>
        (int)$values['mailinggroup_mode'],
      'mailinggroup_default_textview' =>
        (int)$values['mailinggroup_default_textview'],
      'mailinggroup_default_htmlview' =>
        (int)$values['mailinggroup_default_htmlview'],
      'mailinggroup_default_archive_url' =>
        (string)$values['mailinggroup_default_archive_url'],
      'mailinggroup_default_unsubscribe_url' =>
        (string)$values['mailinggroup_default_unsubscribe_url'],
      'mailinggroup_default_subscribers' =>
        (int)$values['mailinggroup_default_subscribers'],
      'mailinggroup_editor_group' =>
        (int)$values['mailinggroup_editor_group']
    );
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingGroups, $data, 'mailinggroup_id', $mailingGroupId
    );
  }

  /**
  * save mailing group intro
  *
  * @param integer $mailingGroupId
  * @param array $values
  * @access private
  * @return boolean
  */
  function saveMailingGroupIntro($mailingGroupId, $values) {
    $data = array(
      'mailinggroup_default_intro_nl2br' => (int)$values['nl2br'],
      'mailinggroup_default_intro' => (string)$values['mailinggroup_default_intro']
    );
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingGroups, $data, 'mailinggroup_id', $mailingGroupId
    );
  }

  /**
  * save mailing group footer
  *
  * @param integer $mailingGroupId
  * @param array $values
  * @access private
  * @return boolean
  */
  function saveMailingGroupFooter($mailingGroupId, $values) {
    $data = array(
      'mailinggroup_default_footer_nl2br' => (int)$values['nl2br'],
      'mailinggroup_default_footer' => (string)$values['mailinggroup_default_footer']
    );
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingGroups, $data, 'mailinggroup_id', $mailingGroupId
    );
  }

  /**
  * save mailing data
  *
  * @param integer $mailingId
  * @param array $values
  * @access private
  * @return boolean
  */
  function saveMailing($mailingId, $values) {
    $data = array(
      'mailing_title' => $values['mailing_title'],
      'mailing_url' => $values['mailing_url'],
      'unsubscribe_url' => $values['unsubscribe_url'],
      'mailinggroup_id' => $values['mailinggroup_id'],
      'mailing_note' => $values['mailing_note'],
      'author_id' => $this->authUser->userId,
      'lng_id' => $values['lng_id'],
      'mailing_protected' => (int)$values['mailing_protected'],
      'mailing_modified' => time()
    );
    $condition = array('mailing_id' => $mailingId);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailings, $data, $condition
    );
  }

  /**
  * exchange position of two mailing contents
  *
  * @param $firstContent
  * @param $secondContent
  * @access private
  */
  function swapTwoContentPositions($firstContent, $secondContent) {
    if (isset($this->contentsOrder[$firstContent]) &&
        isset($this->contentsOrder[$secondContent])
        && isset($this->params['mailing_id']) && $this->params['mailing_id'] > 0) {
      if ($firstContent != $secondContent) {
        $warp = $this->contentsOrder[$firstContent];
        $this->contentsOrder[$firstContent] = $this->contentsOrder[$secondContent];
        $this->contentsOrder[$secondContent] = $warp;
      }
    }
  }

  /**
  * move a mailing content down (position)
  *
  * @access private
  */
  function moveMailingContentsDown() {
    if (isset($this->params['mailingcontent_pos']) && isset($this->params['mailing_id'])) {
      $sql = "UPDATE %s SET mailingcontent_pos = mailingcontent_pos + 1
               WHERE mailingcontent_pos >= '%d'
                 AND mailing_id = '%d'";
      $params = array(
        $this->tableMailingContents,
        (int)$this->params['mailingcontent_pos'],
        (int)$this->params['mailing_id']
      );
      $this->databaseQueryFmtWrite($sql, $params);
    }
  }

  /**
  * check/repair mailing content positions
  *
  * @access private
  */
  function fixMailingContentPositions() {
    if (isset($this->contentsOrder) && is_array($this->contentsOrder)) {
      $i = 1;
      foreach ($this->contentsOrder as $contentId) {
        $content = &$this->contents[$contentId];
        if ($content['mailingcontent_pos'] != $i) {
          $condition = array('mailingcontent_id' => $content['mailingcontent_id']);
          $data = array('mailingcontent_pos' => $i);
          $this->databaseUpdateRecord($this->tableMailingContents, $data, $condition);
          $content['mailingcontent_pos'] = $i;
        }
        $i++;
      }
    }
  }

  /**
  * save mailing view properties
  *
  * @param integer $mailingViewId
  * @param array $configurationData
  * @access private
  * @return boolean
  */
  function saveMailingViewProperties($mailingViewId, $configurationData) {
    if (isset($mailingViewId) && isset($configurationData)) {
      $data = array(
        'mailingview_conf' => $configurationData
      );
      $condition = array('mailingview_id' => $mailingViewId);
      if (FALSE !== $this->databaseUpdateRecord(
            $this->tableMailingView, $data, $condition)) {
        return(TRUE);
      }
    }
    return(FALSE);
  }

  /**
  * save mailing output
  *
  * @access private
  * @return boolean
  */
  function saveMailingOutput() {
    if (!isset($this->params['mailingoutput_mode'])) {
      $this->params['mailingoutput_mode'] = 0;
    }
    switch (@$this->params['mailingoutput_mode']) {
    case 0:
      $data = array(
        'mailingoutput_title' => @(string)$this->params['mailingoutput_title'],
        'mailingoutput_subject' => @(string)$this->params['mailingoutput_subject'],
        'mailingoutput_sender' => @(string)$this->params['mailingoutput_sender'],
        'mailingoutput_sendermail' => @(string)$this->params['mailingoutput_sendermail'],
        'mailingoutput_subscribers' => @(int)$this->params['mailingoutput_subscribers'],
        'mailingoutput_text_view' => @(int)$this->params['mailingoutput_text_view'],
        'mailingoutput_html_view' => @(int)$this->params['mailingoutput_html_view']
      );
      if (@(int)$this->params['mailingoutput_text_view'] <= 0) {
        $data['mailingoutput_text_status'] = 0;
      };
      if (@(int)$this->params['mailingoutput_html_view'] <= 0) { // TEST?
        $data['mailingoutput_html_status'] = 0;
      };
      break;
    case 1:
      $data = array(
        'mailingoutput_text_data' => papaya_strings::ensureUTF8(
          @(string)$this->params['mailingoutput_text_data']
        ),
        'mailingoutput_text_status' => 2,
      );
      break;
    case 2:
      $data = array(
        'mailingoutput_html_data' => papaya_strings::ensureUTF8(
          @(string)$this->params['mailingoutput_html_data']
        ),
        'mailingoutput_html_status' => 2,
      );
      break;
    };
    $condition = array('mailingoutput_id' => $this->params['mailingoutput_id']);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingOutput, $data, $condition
    );
  }

  /**
  * save generated mailing output
  *
  * @param integer $outputId
  * @param integer $outputMode
  * @param string $data
  * @access private
  * @return boolean
  */
  function saveGeneratedMailingOutput($outputId, $outputMode, $data) {
    switch (@$outputMode) {
    case 1:
      $data = array(
        'mailingoutput_text_data' => @(string)$data,
        'mailingoutput_text_status' => 1,
      );
      break;
    case 2:
      $data = array(
        'mailingoutput_html_data' => @(string)$data,
        'mailingoutput_html_status' => 1,
      );
      break;
    default :
      return FALSE;
    };
    $condition = array('mailingoutput_id' => $outputId);
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableMailingOutput, $data, $condition
    );
  }

  /**
  * get short string for language id
  *
  * @param integer $languageId
  * @access private
  * @return string
  */
  function getLanguageShort($languageId) {
    $this->lngSelect = base_language_select::getInstance();
    return (!empty($this->lngSelect->languages[$languageId]['lng_short'])) ?
      $this->lngSelect->languages[$languageId]['lng_short'] :
      '';
  }

  /**
  * get output filter for view
  *
  * @param integer $mailingViewId
  * @access public
  * @return object
  */
  function getOutputFilter($mailingViewId) {
    $result = NULL;
    $outputMode = $this->papaya()->plugins->options['96157ec2db3a16c368ff1d21e8a4824a']->get(
      'TEMPLATE_PATH', 'newsletter'
    );
    if (!empty($outputMode)) {
      $output = new papaya_output();
      if ($output->loadViewModeData($outputMode)) {
        $result = $this->papaya()->plugins->get($output->viewMode['module_guid']);
        if ($result) {
          $sql = "SELECT mv.mailingview_conf
                    FROM %s AS mv
                   WHERE mv.mailingview_id = '%d'";
          $params = array($this->tableMailingView, $mailingViewId);
          if ($res = $this->databaseQueryFmt($sql, $params)) {
            if ($module = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
              $result->setData($module['mailingview_conf']);
            }
          }
          $result->templatePath = $output->viewMode['viewmode_path'];
        }
      }
    }
    return $result;
  }

  /**
  * convert newlines (\r\n, \n\r, \r, \n) to spaces
  *
  * @param string $xml
  * @access public
  * @return string
  */
  function newlineToSpace($xml) {
    $pieces = preg_split("~\r\n|\n\r|[\r\n]+~", $xml);
    $result = '';
    foreach ($pieces as $piece) {
      if (trim($piece) != '') {
        $result .= " ". trim($piece);
      }
    }
    return substr($result, 1);
  }

  /**
  * get mailing output xml
  *
  * @param integer $mailingOutputId
  * @access public
  * @return string
  */
  function getMailingOutputXML($mailingOutputId) {
    $result = FALSE;
    if ($this->loadMailingOutputs(0, $mailingOutputId)) {
      $mailingId = $this->outputs[$mailingOutputId]['mailing_id'];
      if ($this->loadOneMailing($mailingId)) {
        $this->loadMailingContents($mailingId, TRUE);
        $str = '<?xml version="1.0" encoding="utf-8" ?>'."\n";
        $str .= sprintf(
          '<mail id="%d">'.LF, (int)$this->oneMailing['mailing_id']
        );
        $str .= '<content>'.LF;
        $str .= sprintf(
          '<title>%s</title>'.LF,
          papaya_strings::escapeHTMLChars($this->oneMailing['mailing_title'])
        );
        if (isset($this->oneMailing['mailing_url']) && $this->oneMailing['mailing_url']) {
          $str .= sprintf(
            '<url>%s</url>'.LF,
            papaya_strings::escapeHTMLChars(
              $this->getAbsoluteURL($this->oneMailing['mailing_url'])
            )
          );
        }
        $str .= sprintf(
          '<sender>%s</sender>'.LF,
          papaya_strings::escapeHTMLChars($this->outputs[$mailingOutputId]['mailingoutput_sender'])
        );
        $str .= sprintf(
          '<sendermail>%s</sendermail>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->outputs[$mailingOutputId]['mailingoutput_sendermail']
          )
        );

        if ((bool)@$this->oneMailing['nl2br']) {
          $str .= sprintf(
            '<intro>%s</intro>'.LF,
            $this->newlineToSpace(
              $this->getXHTMLString(
                $this->oneMailing['mailing_intro'],
                !((bool)@$this->oneMailing['mailing_intro_nl2br'])
              )
            )
          );
          $str .= sprintf(
            '<footer>%s</footer>'.LF,
            $this->newlineToSpace(
              $this->getXHTMLString(
                $this->oneMailing['mailing_footer'],
                !((bool)@$this->oneMailing['mailing_footer_nl2br'])
              )
            )
          );
        } else {
          $str .= sprintf(
            '<intro>%s</intro>'.LF,
            $this->getXHTMLString(
              $this->oneMailing['mailing_intro'],
              !((bool)@$this->oneMailing['mailing_intro_nl2br'])
            )
          );
          $str .= sprintf(
            '<footer>%s</footer>'.LF,
            $this->getXHTMLString(
              $this->oneMailing['mailing_footer'],
              !((bool)@$this->oneMailing['mailing_footer_nl2br'])
            )
          );
        }

        if (isset($this->contents) && is_array($this->contents) &&
            count($this->contents) > 0) {
          $str .= '<sections>'.LF;
          foreach ($this->contents as $content) {
            $str .= '<section>'.LF;
            $str .= sprintf(
              '<title>%s</title>'.LF,
              $this->getXHTMLString($content['mailingcontent_title'])
            );
            $str .= sprintf(
              '<subtitle>%s</subtitle>'.LF,
              $this->getXHTMLString($content['mailingcontent_subtitle'])
            );
            if ((bool)$content['mailingcontent_nl2br']) {
              $str .= sprintf(
                '<text>%s</text>'.LF,
                $this->newlineToSpace(
                  $this->getXHTMLString(
                    $content['mailingcontent_text'], FALSE
                  )
                )
              );
            } else {
              $str .= sprintf(
                '<text>%s</text>'.LF,
                $this->getXHTMLString(
                  $content['mailingcontent_text'], TRUE
                )
              );
            }
            $str .= '</section>'.LF;
          }
          $str .= '</sections>'.LF;
        }
        $str .= '</content>'.LF;
        $str .= '</mail>'.LF;

        include_once(PAPAYA_INCLUDE_PATH.'system/papaya_parser.php');
        $parser = new papaya_parser;
        $parser->linkModeAbsolute = TRUE;
        $this->papaya()->pageReferences()->setPreview(FALSE);
        $result = $parser->parse($str, $this->oneMailing['lng_id']);
        $this->papaya()->pageReferences()->setPreview(TRUE);
      }
    }
    return papaya_strings::ensureUTF8($result);
  }

  /**
  * parse mailing output xml to output format
  *
  * @param integer $mailingOutputId
  * @access public
  * @return string
  */
  function parseMailingOutput($mailingOutputId) {
    $result = FALSE;
    if ($xml = $this->getMailingOutputXML($mailingOutputId)) {
      switch (@$this->params['mailingoutput_mode']) {
      case 1 :
        $mailingViewId = $this->outputs[$mailingOutputId]['mailingoutput_text_view'];
        break;
      case 2 :
        $mailingViewId = $this->outputs[$mailingOutputId]['mailingoutput_html_view'];
        break;
      default :
        $mailingViewId = 0;
      }

      if ($filterObj = $this->getOutputFilter($mailingViewId)) {
        if (class_exists('PapayaTemplateXslt')) {
          $layoutObj = new PapayaTemplateXslt();
        } else {
          include_once(PAPAYA_INCLUDE_PATH.'system/papaya_xsl.php');
          $layoutObj = new papaya_xsl();
        }
        if (method_exists($layoutObj, 'setXml')) {
          $layoutObj->setXml(papaya_strings::entityToXML($xml));
        } else {
          $layoutObj->xmlData = papaya_strings::entityToXML(papaya_strings::ensureUTF8($xml));
        }
        $layoutObj->setParam(
          'PAGE_LANGUAGE', $this->getLanguageShort($this->oneMailing['lng_id'])
        );
        $layoutObj->setParam('PAGE_TODAY', date('Y-m-d H:i:s'));
        /**
         * @todo
         * check module options theme property
         */
        $themeHandler = new PapayaThemeHandler();
        $layoutObj->setParam('PAGE_THEME', $themeHandler->getTheme());
        $layoutObj->setParam('PAGE_THEME_PATH', $themeHandler->getUrl());
        $layoutObj->setParam('PAGE_THEME_PATH_LOCAL', $themeHandler->getLocalThemePath());
        $layoutObj->setParam('PAGE_WEB_PATH', PAPAYA_PATH_WEB);
        if (!$filterObj->checkConfiguration()) {
          $this->papaya()->messages->dispatch(
            new PapayaMessageDisplay(PapayaMessage::TYPE_ERROR, $filterObj->errorMessage)
          );
        }
        $sandbox = $this->papaya()->messages->encapsulate(array($filterObj, 'parseXML'));
        if ($generatedData = call_user_func($sandbox, $layoutObj)) {
          $grepHref = '(href=(["\'])(.*?)(\\1))i';
          if (preg_match_all($grepHref, $generatedData, $matches, PREG_PATTERN_ORDER)) {
            $replaceChars = array('%7B', '%7D');
            $withChars = array('{', '}');
            foreach ($matches[0] as $match) {
              $replace[$match] = str_replace($replaceChars, $withChars, $match);
            }
            $generatedData = str_replace(
              array_keys($replace), array_values($replace), $generatedData
            );
          }
          switch (@$this->params['mailingoutput_mode']) {
          case 3:
            $result = $this->saveGeneratedMailingOutput(
              $mailingOutputId, 3, $generatedData);
            break;
          case 2:
            $this->params['mailingoutput_html_data'] = $generatedData;
            $result = $this->saveGeneratedMailingOutput(
              $mailingOutputId, 2, $generatedData);
            break;
          case 1:
            $this->params['mailingoutput_text_data'] = $generatedData;
            $result = $this->saveGeneratedMailingOutput(
              $mailingOutputId, 1, $generatedData);
            break;
          }
          if ($result) {
            $this->addMsg(MSG_INFO, $this->_gt('Mail output generated.'));
          }
        } else {
          $this->getXSLTTransFormErrors($layoutObj->lastError);
        }
      }
    }
    return $result;
  }

  /**
  * get xslt transform errors
  *
  * @param array $errors
  * @access public
  */
  function getXSLTTransFormErrors($errors) {
    if (isset($errors) && is_array($errors) && count($errors) > 0) {
      $result = sprintf(
        '<listview title="%s">',
        papaya_strings::escapeHTMLChars($this->_gt('Errors'))
      );
      $result .= '<items>';
      foreach ($errors as $error) {
        $result .= sprintf(
          '<listitem image="%s">',
          papaya_strings::escapeHTMLChars($this->images['status-dialog-error'])
        );
        $result .= '<subitem>';
        $result .= sprintf(
          '<b>%d: %s</b>',
          (int)$error['code'],
          papaya_strings::escapeHTMLChars($error['msg'])
        );
        if ($error['file'] != '') {
          $fileName = $error['file'];
          if (0 === strpos($fileName, PAPAYA_PATH_TEMPLATES)) {
            $fileName = '~/'.substr($fileName, strlen(PAPAYA_PATH_TEMPLATES));
          }
          $result .= sprintf('<br/>%s', papaya_strings::escapeHTMLChars($fileName));
          $result .= ':'.(int)$error['line'];
        }
        $result .= '</subitem>';
        $result .= '</listitem>';
      }
      $result .= '</items>';
      $result .= '</listview>';
      $this->layout->add($result);
    }
  }

  /**
  * get xml newsletter list status listview
  *
  * @param boolean $actions optional, default value FALSE
  * @param mixed $formats optional, default value NULL
  * @access private
  */
  function getNewsletterStatusXML($actions = FALSE, $formats = NULL) {
    if (!empty($this->newsletterLists)) {
      $subscribers = $this->loadNewsletterStatus();
      if (!empty($subscribers)) {
        $mailFormats = array(
          1 => array(
            'title' => 'HTML',
            'format' => 'html',
            'image' => 'categories-content'
          ),
          0 => array(
            'title' => 'TEXT',
            'format' => 'text',
            'image' => 'items-page'
          )
        );

        $listview = new PapayaUiListview();
        $listview->caption = new PapayaUiStringTranslated('Subscribers sdasa');
        $listview->reference->setParameters(
          array(
            'mode' => isset($this->params['mode']) ? $this->params['mode'] : '',
            'cmd' => 'edit_list'
          )
        );
        $listview->columns[] = new PapayaUiListviewColumn(
          new PapayaUiStringTranslated('List')
        );
        $listview->columns[] = new PapayaUiListviewColumn(
          new PapayaUiStringTranslated('Recipients'),
          PapayaUiOptionAlign::CENTER
        );
        if ($actions) {
          $listview->columns[] = new PapayaUiListviewColumn(
            new PapayaUiStringTranslated('Send'),
            PapayaUiOptionAlign::CENTER
          );
        }
        foreach ($this->newsletterLists as $list) {
          $listview->items[] = $item = new PapayaUiListviewItem(
            'items-table',
            $list['newsletter_list_name'].' #'.(int)$list['newsletter_list_id'],
            array(
              'newsletter_list_id' => $list['newsletter_list_id']
            ),
            $list['newsletter_list_id'] == @$this->params['newsletter_list_id'] &&
              !in_array(@$this->params['mailing_format'], array('text', 'html'))
          );
          if (isset($subscribers[$list['newsletter_list_id']]) &&
              is_array($subscribers[$list['newsletter_list_id']])) {
            $item->subitems[] = new PapayaUiListviewSubitemText(
              array_sum($subscribers[$list['newsletter_list_id']])
            );
            if ($actions) {
              if (($formats['text'] && @(int)$subscribers[$list['newsletter_list_id']][0] > 0) ||
                  ($formats['html'] && @(int)$subscribers[$list['newsletter_list_id']][1] > 0)) {
                $item->subitems[] = new PapayaUiListviewSubitemImage(
                  'actions-mail-send',
                  new PapayaUiStringTranslated('Add to postbox'),
                  array(
                    $this->paramName => array(
                      'mailing_format' => 'all',
                      'newsletter_list_id' => $list['newsletter_list_id'],
                      'mailingoutput_mode' => 5,
                      'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                      'mailing_id' => $this->oneMailingOutput['mailing_id'],
                      'cmd' => 'fill_queue',
                      'mode' => $this->params['mode']
                    )
                  )
                );
              } else {
                $item->subitems[] = new PapayaUiListviewSubitemText('');
              }
            }
            foreach ($mailFormats as $formatIndex => $mailFormat) {
              if (@(int)$subscribers[$list['newsletter_list_id']][$formatIndex] > 0) {
                $listview->items[] = $item = new PapayaUiListviewItem(
                  $mailFormat['image'],
                  new PapayaUiStringTranslated($mailFormat['title']),
                  array(),
                  $list['newsletter_list_id'] == @$this->params['newsletter_list_id'] &&
                    $mailFormat['format'] == @$this->params['mailing_format']
                );
                $item->indentation = 1;
                $item->subitems[] = new PapayaUiListviewSubitemText(
                  @(int)$subscribers[$list['newsletter_list_id']][$formatIndex]
                );
                if ($actions) {
                  $format = $mailFormat['format'];
                  if (isset($formats[$format]) && $formats[$format] &&
                      @(int)$subscribers[$list['newsletter_list_id']][$formatIndex] > 0) {
                    $item->subitems[] = new PapayaUiListviewSubitemImage(
                      'actions-mail-send',
                      new PapayaUiStringTranslated('Add to postbox'),
                      array(
                        $this->paramName => array(
                          'mailing_format' => $format,
                          'newsletter_list_id' => $list['newsletter_list_id'],
                          'mailingoutput_mode' => 5,
                          'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
                          'mailing_id' => $this->oneMailingOutput['mailing_id'],
                          'cmd' => 'fill_queue',
                          'mode' => $this->params['mode']
                        )
                      )
                    );
                  } else {
                    $item->subitems[] = new PapayaUiListviewSubitemText('');
                  }
                }
              }
            }
          } else {
            $item->columnSpan = 3;
          }
        }
        $this->layout->add($listview->getXml());
      }
    }
  }

  /**
  * get fill queue confirmation dialog
  *
  * @access private
  */
  function getFillQueueConfirmXML() {
    $this->layout->add($this->getFillQueueConfirmationDialog()->getXml());
  }

  function getFillQueueConfirmationDialog() {
    if (!(
          isset($this->_queueConfirmationDialog) &&
          $this->_queueConfirmationDialog instanceOf PapayaUiDialog
         )
       ) {
      $listId = @(int)$this->params['newsletter_list_id'];
      $this->_queueConfirmationDialog = $dialog = new PapayaUiDialog();
      $dialog->caption = new PapayaUiStringTranslated('Confirmation');
      $dialog->parameterGroup($this->paramName);
      $dialog->hiddenFields()->merge(
        array(
          'mailing_format' => @(string)$this->params['mailing_format'],
          'newsletter_list_id' => $listId,
          'mailingoutput_mode' => 5,
          'mailingoutput_id' => $this->oneMailingOutput['mailingoutput_id'],
          'mailing_id' => $this->oneMailingOutput['mailing_id'],
          'cmd' => 'fill_queue',
          'confirm_queue' => 1,
          'mode' => $this->params['mode'],
        )
      );
      if (isset($this->newsletterLists[$listId])) {
        $dialog->fields[] = $field = new PapayaUiDialogFieldInformation(
          new PapayaUiStringTranslated(
            'Send mailing in format "%s" to list "%s" (#%d)?',
            array(
              $this->params['mailing_format'],
              $this->newsletterLists[$listId]['newsletter_list_name'],
              $listId
            )
          ),
          'categories-messages-outbox'
        );
      }
      $dialog->fields[] = new PapayaUiDialogFieldInputTimestamp(
        new PapayaUiStringTranslated('Schedule date'),
        'schedule_for',
        time(),
        TRUE,
        PapayaFilterDate::DATE_MANDATORY_TIME
      );
      $dialog->buttons[] = new PapayaUiDialogButtonSubmit(
        new PapayaUiStringTranslated('Post mailing')
      );
    }
    return $this->_queueConfirmationDialog;
  }

  /**
  * load newsletter list data
  *
  * @param integer $newsletterListId
  * @access public
  * @return boolean
  */
  function loadNewsletterList($newsletterListId) {
    $this->newsletterList = array();
    $sql = "SELECT newsletter_list_id, newsletter_list_name,
                   newsletter_list_description, newsletter_list_format
              FROM %s
             WHERE newsletter_list_id = %d";
    $params = array($this->tableLists, $newsletterListId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->newsletterList = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * load newsletter list status
  *
  * @param integer $listId optional, default value 0
  * @access public
  * @return array
  */
  function loadNewsletterStatus($listId = 0) {
    $result = array();
    $filter = $this->databaseGetSQLCondition('sbs.subscription_status', $this->activeStatus);
    if ($listId > 0) {
      $sql = "SELECT sbs.newsletter_list_id, sbs.subscription_format,
                     COUNT(sbs.subscriber_id) AS subscribers
                FROM %s AS sbs, %s AS sbr
               WHERE newsletter_list_id = '%d'
                 AND $filter
                 AND sbs.subscriber_id = sbr.subscriber_id
                 AND sbr.subscriber_status = 1
               GROUP BY sbs.newsletter_list_id, sbs.subscription_format";
      $params = array($this->tableSubscriptions, $this->tableSubscribers, (int)$listId);
    } else {
      $sql = "SELECT sbs.newsletter_list_id, sbs.subscription_format,
                     COUNT(sbs.subscriber_id) AS subscribers
                FROM %s AS sbs, %s AS sbr
               WHERE $filter
                 AND sbs.subscriber_id = sbr.subscriber_id
                 AND sbr.subscriber_status = 1
               GROUP BY sbs.newsletter_list_id, sbs.subscription_format";
      $params = array($this->tableSubscriptions, $this->tableSubscribers);
    }
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result[(int)$row['newsletter_list_id']][(int)$row['subscription_format']] =
          (int)$row['subscribers'];
      }
    }
    return $result;
  }

  /**
  * get xml for queue listview
  *
  * @access private
  */
  function getQueueListXML($large = TRUE) {
    if (isset($this->queueEntries) && is_array($this->queueEntries) &&
        count($this->queueEntries) > 0) {
      $result = sprintf(
        '<listview title="%s">'.LF,
        papaya_strings::escapeHTMLchars($this->_gt('Emails'))
      );
      $result .= $this->getListViewNav(
        @(int)$this->params['queue_offset'],
        20,
        $this->queueEntryCount,
        25,
        'queue_offset'
      );
      $result .= '<cols>'.LF;
      $result .= sprintf(
        '<col>%s</col>'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Email'))
      );
      $result .= sprintf(
        '<col>%s</col>'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Subject'))
      );
      $result .= sprintf(
        '<col align="center">%s</col>'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Formats'))
      );
      if ($this->params['mailingqueue_mode'] > 0) {
        $result .= sprintf(
          '<col align="center">%s</col>'.LF,
          papaya_strings::escapeHTMLChars($this->_gt('Sent'))
        );
      } else {
        $result .= sprintf(
          '<col align="center">%s</col>'.LF,
          papaya_strings::escapeHTMLChars($this->_gt('Scheduled'))
        );
      }
      $result .= '</cols>'.LF;
      $result .= '<items>'.LF;

      foreach ($this->queueEntries as $entry) {
        $selected = (@$this->params['mailingqueue_id'] == $entry['mailingqueue_id'])
          ? ' selected="selected"' : '';
        $result .= sprintf(
          '<listitem title="%s" image="%s" href="%s"%s>'.LF,
          papaya_strings::escapeHTMLchars($entry['mailingqueue_email']),
          papaya_strings::escapeHTMLChars($this->images['items-mail']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(
              array(
                'mode' => 4,
                'cmd' => 'mailingqueue_view',
                'mailingqueue_id' => (int)$entry['mailingqueue_id']
              )
            )
          ),
          $selected
        );
        $result .= sprintf(
          '<subitem>%s</subitem>',
          papaya_strings::escapeHTMLchars($entry['mailingqueue_subject'])
        );
        $result .= '<subitem align="center">';

        $hints = array(
          'status-sign-ok' => $this->_gt('Email format'),
          'status-sign-warning' => $this->_gt('Alternative email format'),
          'status-sign-problem' => $this->_gt('Not included')
        );

        if (($entry['mailingqueue_text_status'] > 0) &&
            ($entry['mailingqueue_format'] == 0)) {
          $result .= sprintf(
            '<glyph src="%s" hint="%s: %s"/>',
            papaya_strings::escapeHTMLchars($this->images['status-sign-ok']),
            papaya_strings::escapeHTMLChars($hints['status-sign-ok']),
            papaya_strings::escapeHTMLChars($this->_gt('Text'))
          );
        } elseif ($entry['mailingqueue_text_status'] > 0) {
          $result .= sprintf(
            '<glyph src="%s" hint="%s: %s"/>',
            papaya_strings::escapeHTMLchars($this->images['status-sign-warning']),
            papaya_strings::escapeHTMLChars($hints['status-sign-warning']),
            papaya_strings::escapeHTMLChars($this->_gt('Text'))
          );
        } else {
          $result .= sprintf(
            '<glyph src="%s" hint="%s: %s"/>',
            papaya_strings::escapeHTMLchars($this->images['status-sign-problem']),
            papaya_strings::escapeHTMLChars($hints['status-sign-problem']),
            papaya_strings::escapeHTMLChars($this->_gt('Text'))
          );
        }
        if (($entry['mailingqueue_html_status'] > 0) &&
            ($entry['mailingqueue_format'] == 1)) {
          $result .= sprintf(
            '<glyph src="%s" hint="%s: %s"/>',
            papaya_strings::escapeHTMLchars($this->images['status-sign-ok']),
            papaya_strings::escapeHTMLChars($hints['status-sign-ok']),
            papaya_strings::escapeHTMLChars($this->_gt('HTML'))
          );
        } else {
          $result .= sprintf(
            '<glyph src="%s" hint="%s: %s"/>',
            papaya_strings::escapeHTMLchars($this->images['status-sign-problem']),
            papaya_strings::escapeHTMLChars($hints['status-sign-problem']),
            papaya_strings::escapeHTMLChars($this->_gt('HTML'))
          );
        }

        $result .= '</subitem>'.LF;
        if ($this->params['mailingqueue_mode'] > 0) {
          $result .= sprintf(
            '<subitem align="center">%s</subitem>',
            date('Y-m-d H:i:s', $entry['mailingqueue_sent'])
          );
        } else {
          $result .= sprintf(
            '<subitem align="center">%s</subitem>',
            date(
              'Y-m-d H:i:s',
              $entry['mailingqueue_scheduled'] > 0
                ? $entry['mailingqueue_scheduled'] : $entry['mailingqueue_created'])
          );
        }
        $result .= '</listitem>'.LF;
      }
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->add($result);
    } else {
      $this->addMsg(MSG_INFO, $this->_gt('This newsletter is empty.'));
    }
  }

  /**
  * get delete queue confirmation dialog
  *
  * @access private
  */
  function getDeleteQueueConfirmXML() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
    $hidden = array(
      'mailingqueue_mode' => $this->params['mailingqueue_mode'],
      'cmd' => 'clear_queue',
      'confirm_clear_queue' => 1,
      'mode' => $this->params['mode'],
    );
    $msg = $this->_gt('Delete emails?');
    $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
    $dialog->baseLink = $this->baseLink;
    $dialog->buttonTitle = 'Delete';
    $this->layout->add($dialog->getMsgDialog());
  }

  /**
  * get process queue xml - this contains some js for an rpc-call
  *
  * @access public
  */
  function getProcessQueueXML() {
    if (isset($this->queueEntries) && is_array($this->queueEntries) &&
        count($this->queueEntries) > 0) {
      $javascript = '<script type="text/javascript" src="script/xmlrpc.js"></script>';
      $javascript .= '<script type="text/javascript">
        var queuePosition = 0;
        function processQueue() {
          PapayaLightBox.init(\''.$this->_gt('Sending emails').'\', \''.$this->_gt('Close').'\');
          PapayaLightBox.update(\''.$this->_gt('Requesting...').'\', 0);
          queuePosition = 0;
          requestProcessQueue();
        }

        function requestProcessQueue() {
          var url = "'.$this->baseLink.'?'.$this->paramName.'[cmd]=process_queue_rpc";
          loadXMLDoc(url, true);
        }

        function rpcSetProcessQueue(data, params) {
          var responseArray = new Array();
          if (params) {
            responseArray = xmlParamNodesToArray(params);
            if (responseArray.countSent != null && responseArray.countQueue != null) {
              responseArray.countSent = parseInt(responseArray.countSent);
              responseArray.countQueue = parseInt(responseArray.countQueue);
              if (isNaN(responseArray.countSent)) {
                responseArray.countSent = 0;
              }
              if (isNaN(responseArray.countQueue)) {
                responseArray.countQueue = 0;
              }
              queuePosition += responseArray.countSent;
              var queueCount = queuePosition + responseArray.countQueue;
              PapayaLightBox.update(queuePosition.toString() + "/" + queueCount.toString(),
                Math.floor(queuePosition * 100 / queueCount));

              if (queuePosition &lt; queueCount) {
                requestProcessQueue();
              } else {
                PapayaLightBox.update("'.$this->_gt('Done').'! - " +
                  queuePosition.toString() + "/" + queueCount.toString(), 100);
              }
            } else {
              PapayaLightBox.update("'.$this->_gt('Params Error').'", 0);
            }
          } else {
            PapayaLightBox.update("'.$this->_gt('Error').'", 0);
          }
        }
        </script>';
      $this->layout->addScript($javascript);
    }
  }

  /**
  * get xml for the queue rpc call
  *
  * @access public
  * @return string xml for queue rpc call
  */
  function getProcessQueueRPCXML() {
    $countSent = 20;
    $countQueue = 40;

    include_once(dirname(__FILE__).'/base_newsletter_queue.php');
    $queue = new base_newsletter_queue();
    if ($counts = $queue->processQueue(20)) {
      $countSent = $counts[0];
      $countQueue = $counts[1];
    } else {
      $countSent = 0;
      $countQueue = 0;
    }

    // rueckgabe xml
    $result = '<?xml version="1.0" encoding="utf-8"?>';
    $result .= '<response>';
    $result .= '<method>rpcSetProcessQueue</method>';
    $result .= sprintf('<param name="countSent" value="%d" />', (int)$countSent);
    $result .= sprintf('<param name="countQueue" value="%d" />', (int)$countQueue);
    $result .= '<data></data>';
    $result .= '</response>';
    header('Content-type: text/xml; charset=utf-8');
    echo $result;
    exit;
  }

  /**
  * initialize the test mail dialog
  *
  * @access private
  */
  function initializeTestMailDialog() {
    if (!(isset($this->dialogTestMail) && is_object($this->dialogTestMail))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      $data = array(
        'subscriber_email' => $this->authUser->user['email'],
        'subscription_format' => 1,
        'subscriber_firstname' => $this->authUser->user['givenname'],
        'subscriber_lastname' => $this->authUser->user['surname'],
      );
      $hidden = array(
        'cmd' => 'send_testmail',
        'confirm_sendmail' => 1,
        'mode' => (int)$this->params['mode'],
      );

      $fields = array(
        'subscriber_email' =>
          array('Email', 'isEmail', TRUE, 'input', 200),
        'subscriber_format' =>
          array('Mode', 'isNum', FALSE, 'combo', array(0 => 'plaintext', 1 => 'html')),
        'newsletter_list_id' =>
          array('Newsletter list Id', 'isNum', FALSE, 'input', 200),
        'Contact data',
        'subscriber_salutation' =>
          array('Salutation', 'isNum', TRUE, 'combo', $this->salutations),
        'subscriber_title' =>
          array('Title', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_firstname' =>
          array('First name', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_lastname' =>
          array('Last name', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_branch' =>
          array('Branch', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_company' =>
          array('Company', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_position' =>
          array('Position', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_section' =>
          array('Section', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_street' =>
          array('Street', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_housenumber' =>
          array('House number', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_postalcode' =>
          array('Zip code', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_city' =>
          array('City', 'isNoHTML', FALSE, 'input', 200),
        'subscriber_phone' =>
          array('Phone', 'isPhone', FALSE, 'input', 200),
        'subscriber_mobile' =>
          array('Mobile phone', 'isPhone', FALSE, 'input', 200),
        'subscriber_fax' =>
          array('Fax', 'isPhone', FALSE, 'input', 200),
      );

      $this->dialogTestMail = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->dialogTestMail->loadParams();
      $this->dialogTestMail->dialogTitle = $this->_gt('Test data');
      $this->dialogTestMail->buttonTitle = 'Send';
      $this->dialogTestMail->inputFieldSize = 'x-large';
      $this->dialogTestMail->dialogDoubleButtons = TRUE;
    }
  }

  /**
  * add the test mail dialog to the layout object
  *
  * @access public
  */
  function getTestMailXML() {
    $this->initializeTestMailDialog();
    $this->layout->add($this->dialogTestMail->getDialogXML());
  }

  /**
  * send the test mail
  *
  * @access boolean
  */
  function sendTestMail() {
    include_once(dirname(__FILE__).'/base_newsletter_queue.php');
    $queue = new base_newsletter_queue();
    $data = array(
      'email' => $this->params['subscriber_email'],
      'name' => @(string)$this->params['subscriber_firstname'].' '.
        @(string)$this->params['subscriber_lastname'],
      'subject' => $this->oneMailingOutput['mailingoutput_subject'],
      'from_email' => $this->oneMailingOutput['mailingoutput_sendermail'],
      'from' => $this->oneMailingOutput['mailingoutput_sender'],
      'text' => NULL,
      'html' => NULL
    );

    $hasMessage['text'] = (
      $this->oneMailingOutput['mailingoutput_text_status'] > 0 &&
      trim($this->oneMailingOutput['mailingoutput_text_data']) != ''
    );
    $hasMessage['html'] = (
      $this->oneMailingOutput['mailingoutput_html_status'] > 0 &&
      trim($this->oneMailingOutput['mailingoutput_html_data']) != ''
    );

    switch ($this->params['subscriber_format']) {
    case 1 : //html
      if ($hasMessage['html']) {
        $data['html'] = $this->oneMailingOutput['mailingoutput_html_data'];
        if ($hasMessage['text']) {
          $data['text'] = $this->oneMailingOutput['mailingoutput_text_data'];
        }
      }
      break;
    case 0 : //plain text
    default :
      if ($hasMessage['text']) {
        $data['text'] = $this->oneMailingOutput['mailingoutput_text_data'];
      }
      break;
    }
    $fillValues = array(
      'subscriber.email' => @(string)$this->params['subscriber_email'],
      'subscriber.salutation' => @(string)$this->params['subscriber_salutation'],
      'subscriber.title' => @(string)$this->params['subscriber_title'],
      'subscriber.firstname' => @(string)$this->params['subscriber_firstname'],
      'subscriber.lastname' => @(string)$this->params['subscriber_lastname'],
      'subscriber.branch' => @(string)$this->params['subscriber_branch'],
      'subscriber.company' => @(string)$this->params['subscriber_company'],
      'subscriber.position' => @(string)$this->params['subscriber_position'],
      'subscriber.section' => @(string)$this->params['subscriber_section'],
      'subscriber.street' => @(string)$this->params['subscriber_street'],
      'subscriber.housenumber' => @(string)$this->params['subscriber_housenumber'],
      'subscriber.postalcode' => @(string)$this->params['subscriber_postalcode'],
      'subscriber.city' => @(string)$this->params['subscriber_city'],
      'subscriber.phone' => @(string)$this->params['subscriber_phone'],
      'subscriber.mobile' => @(string)$this->params['subscriber_mobile'],
      'subscriber.fax' => @(string)$this->params['subscriber_fax'],
      'subscription.format' => @(int)$this->params['subscription_format'],
      'newsletter.list_id' => @(int)$this->params['newsletter_list_id'],
      'subscription.unsubscribe_link' => $unsubscribe = $queue->getAccountManagerUrl(
        $this->oneMailing['unsubscribe_url'], array('sample_unsubscribe' => '1')
      ),
      'subscription.unsubscribe_query' => $queue->getAccountManagerQueryString(
        array('sample_unsubscribe' => '1')
      ),
      'subscription.unsubscribe' => $unsubscribe,
      'subscription.switchformat' => $unsubscribe = $queue->getAccountManagerUrl(
        $this->oneMailing['unsubscribe_url'], array('sample_switchformat' => '1')
      ),
      'subscription.switchformat_query' => $queue->getAccountManagerQueryString(
        array('sample_switchformat' => '1')
      )
    );
    return $queue->sendNewsletterMail($data, $fillValues);
  }

  /**
  *
  * @param unknown_type $queueId
  */
  function getMailingQueueEntryContentXML($queueId) {
    include_once(dirname(__FILE__).'/base_newsletter_queue.php');
    $queue = new base_newsletter_queue();
    $queue->prepareQueueEmail($queueId);
    $result = '';
    $buttons = '';

    if (isset($queue->mailData['html']) && isset($queue->mailData['text'])) {
      $buttons = '<buttons>';
      $buttons .= sprintf(
        '<button title="%s" href="%s" %s/>',
        papaya_strings::escapeHTMLChars($this->_gt('Text')),
        papaya_strings::escapeHTMLChars(
          $this->getLink(
            array(
              'format' => 'text',
              'mailingqueue_id' => $this->params['mailingqueue_id'],
              'mode' => @$this->params['mode'],
              'cmd' => 'mailingqueue_view',
            )
          )
        ),
        (@$this->params['format'] == 'text') ? ' down="down"' : ''
      );
      $buttons .= sprintf(
        '<button title="%s" href="%s" %s/>',
        papaya_strings::escapeHTMLChars($this->_gt('HTML')),
        papaya_strings::escapeHTMLChars(
          $this->getLink(
            array(
              'format' => 'html',
              'mailingqueue_id' => $this->params['mailingqueue_id'],
              'mode' => @$this->params['mode'],
              'cmd' => 'mailingqueue_view',
            )
          )
        ),
        (@$this->params['format'] != 'text') ? ' down="down"' : ''
      );
      $buttons .= '</buttons>';
    }

    if (isset($queue->mailData['html']) && !(@$this->params['format'] == 'text')) {
      $params = array(
        'format' => 'html',
        'mailingqueue_id' => $this->params['mailingqueue_id'],
        'mode' => @$this->params['mode'],
        'cmd' => 'mailingqueue_view_html',
      );

      $result .= sprintf(
        '<panel width="100%%" title="%s">',
        papaya_strings::escapeHTMLChars($this->_gt('Preview'))
      );
      $result .= $buttons;
      $result .= '<sheet width="100%" align="center">';
      $result .= '<header>'.LF;
      $result .= '<lines>'.LF;
      $result .= sprintf(
        '<line class="headertitle">%s</line>'.LF,
        papaya_strings::escapeHTMLChars($queue->mailData['subject'])
      );
      $result .= sprintf(
        '<line class="headersubtitle">%s</line>'.LF,
        papaya_strings::escapeHTMLChars($queue->mailData['from_email'])
      );
      $result .= '</lines>'.LF;
      $result .= '</header>'.LF;
      $result .= '<text>';
      $result .= sprintf(
        '<iframe width="100%%" '.
        'noresize="noresize" hspace="0" vspace="0" align="center" '.
        'scrolling="auto" height="1400" src="%s" class="plane" id="preview" />',
        papaya_strings::escapeHTMLChars($this->getLink($params))
      );
      $result .= '</text>';
      $result .= '</sheet>';
      $result .= '</panel>';
      $this->layout->add($result);
    } elseif (isset($queue->mailData['text'])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_simpletemplate.php');
      $template = new base_simpletemplate();
      $mailText = @$template->parse($queue->mailData['text'], $queue->fillValues);

      $result .= sprintf('<panel width="100%%" title="%s">', $this->_gt('Preview'));
      $result .= $buttons;
      $result .= '<sheet width="100%" align="center">';
      $result .= '<header>'.LF;
      $result .= '<lines>'.LF;
      $result .= sprintf(
        '<line class="headertitle">%s</line>'.LF,
        papaya_strings::escapeHTMLChars($queue->mailData['subject'])
      );
      $result .= sprintf(
        '<line class="headersubtitle">%s</line>'.LF,
        papaya_strings::escapeHTMLChars($queue->mailData['from_email'])
      );
      $result .= '</lines>'.LF;
      $result .= '</header>'.LF;
      $result .= '<text>';
      $result .= $this->formatTextMailContent($mailText);
      $result .= '</text>';
      $result .= '</sheet>';
      $result .= '</panel>';
      $this->layout->add($result);
    }
  }

  /**
  *
  * @param unknown_type $queueId
  */
  function getMailingQueueEntryContentHTML($queueId) {
    include_once(dirname(__FILE__).'/base_newsletter_queue.php');
    $queue = new base_newsletter_queue();
    $queue->prepareQueueEmail($queueId);
    if (isset($queue->mailData['html'])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_simpletemplate.php');
      $template = new base_simpletemplate();
      $mailText = @$template->parse($queue->mailData['html'], $queue->fillValues);
      echo $mailText;
      exit;
    }
  }

  /**
  * Adds mailing content by feed belonging to given mailing id.
  *
  * @param integer $mailingGroupId
  */
  public function getMailingContentByFeeds($mailingGroupId) {
    $result = array();
    // get feed configuration object
    include_once(dirname(__FILE__).'/Feed/Configuration.php');
    $feedConfiguration = new PapayaModuleNewsletterFeedConfiguration($this);
    // load feeds by mailing group id
    if ($feedConfiguration->feeds()->load($mailingGroupId)) {
      // request loaded feeds as array
      $feeds = iterator_to_array($feedConfiguration->feeds());
      // get fetcher object for reading feed properties and items
      include_once(dirname(__FILE__).'/Feed/Fetcher.php');
      $fetcherObject = new PapayaModuleNewsletterFeedFetcher;
      // xslt parser for transforming feed entries xml to editable html
      if (class_exists('PapayaTemplateXslt')) {
        $xsltObject = new PapayaTemplateXslt();
      } else {
        include_once(PAPAYA_INCLUDE_PATH.'system/papaya_xsl.php');
        $xsltObject = new papaya_xsl();
      }
      foreach ($feeds as $currentFeed) {
        if ($content = $this->getFeedContent($currentFeed, $fetcherObject, $xsltObject)) {
          $result[] = $content;
        }
      }
    }
    return $result;
  }

  /**
  * Saves feed entries as mailing content for given feed.
  *
  * @param array $currentFeed
  * @param PapayaModuleNewsletterFeedFetcher $fetcherObject
  * @param PapayaTemplateXslt|papaya_xsl $xsltObject
  * @return boolean result
  */
  public function getFeedContent($currentFeed, $fetcherObject, $xsltObject) {
    $result = FALSE;
    // create parent xml element
    $dom = new PapayaXmlDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->formatOutput = FALSE;
    $dom->appendElement('feed');
    // read feed by url using fetcher object
    $currentLoadedFeed = $fetcherObject->loadFeed($currentFeed['url']);
    // set published after timestamp
    $publishedAfter = (time() - (int)$currentFeed['period'] * 86400);
    if (!empty($currentLoadedFeed)) {
      // get titles from feed
      $title = $currentLoadedFeed->title->getValue();
      $subtitle = $currentLoadedFeed->subtitle->getValue();
      // get content for requested timeframe
      $content = $fetcherObject->fetchInto(
        $dom->documentElement,
        $currentLoadedFeed,
        $publishedAfter,
        $currentFeed['minimum'],
        $currentFeed['maximum']
      );
      // check if valid content found
      if ($content !== FALSE) {
        $generatedData = $this->parseFeedXML(
          $dom->saveXML(), $currentFeed['template'], $xsltObject
        );
        if ($generatedData) {
          return array(
            'mailingcontent_title' => $title,
            'mailingcontent_subtitle' => $subtitle,
            'mailingcontent_text' => $generatedData,
            'mailingcontent_nl2br' => 1,
          );
        }
      }
    }
    return $result;
  }

  /**
  * Parses the given feed xml using $xsltObject and filter methods.
  *
  * @param string $xml
  * @param string $xslFileName
  * @param PapayaTemplateXslt|papaya_xsl $xsltObject
  * @return string parsed xml
  */
  public function parseFeedXML($xml, $xslFileName, $xsltObject) {
    // save it as editable copy/content, filter GUID 20e80c718e5991d59e938bcdf4e020f2
    $filterObj = base_pluginloader::getPluginInstance('20e80c718e5991d59e938bcdf4e020f2', $this);
    $filterObj->data['xslfile'] = $xslFileName;
    include_once(PAPAYA_INCLUDE_PATH.'system/base_module_options.php');
    $filterObj->templatePath = base_module_options::readOption(
      '96157ec2db3a16c368ff1d21e8a4824a', 'TEMPLATE_PATH', 'newsletter'
    );
    if (method_exists($xsltObject, 'setXml')) {
      $xsltObject->setXml(papaya_strings::entityToXML($xml));
    } else {
      $xsltObject->xmlData = papaya_strings::entityToXML(papaya_strings::ensureUTF8($xml));
    }
    return $filterObj->parseXML($xsltObject);
  }

  private function isManualMailingGroup() {
    return (!empty($this->oneMailingGroup['mailinggroup_mode']));
  }

  private function isEditableMailingGroup($groupId = NULL) {
    if (!$this->module->hasPerm(edmodule_newsletter::PERM_MANAGE_MAILINGS)) {
      return FALSE;
    }
    if (is_null($groupId) && isset($this->oneMailingGroup['mailinggroup_editor_group'])) {
      $groupId = $this->oneMailingGroup['mailinggroup_editor_group'];
    }
    if (empty($groupId)) {
      return TRUE;
    }
    if ($this->papaya()->administrationUser->isAdmin()) {
      return TRUE;
    }
    $result = $this->papaya()->administrationUser->inGroup(
      $groupId
    );
    return $this->papaya()->administrationUser->inGroup($groupId);
  }
}

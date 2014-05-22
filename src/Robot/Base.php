<?php
/**
* Cron job base class for creating and sending mailings automatically.
*
* @copyright 2002-2010 by papaya Software GmbH - All rights reserved.
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
* @version $Id: Base.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Cron job base class for creating and sending mailings automatically.
*
* @package commercial
* @subpackage newsletter
*/
class NewsletterRobotBase extends PapayaObject {

  /**
  * Owner object.
  * @var NewsletterRobot
  */
  protected $owner = NULL;

  /**
  * Configuration object for further instances.
  * @var PapayaConfiguration
  */
  private $_configuration = NULL;

  /**
  * Page configuration data.
  * @var array
  */
  protected $data = array();

  /**
  * Base newsletter object.
  * @var papaya_newsletter
  */
  protected $newsletterObject = NULL;

  /**
  * Constructor of the class.
  *
  * @param object $owner Caller object
  */
  public function __construct($owner = NULL) {
    $this->owner = $owner;
  }

  /**
  * Set configuration option
  *
  * @param PapayaConfiguration $configuration
  */
  public function setConfiguration($configuration) {
    $this->_configuration = $configuration;
  }

  /**
  * Returns the current configuration.
  *
  * @return PapayaConfiguration
  */
  public function getConfiguration() {
    return $this->_configuration;
  }

  /**
  * Instantiate papaya_newsletter to content_newsletter::newsletterObject
  * @return papaya_newsletter
  */
  public function getNewsletterObject() {
    if (!is_object($this->newsletterObject)) {
      include_once(dirname(__FILE__).'/../papaya_newsletter.php');
      $this->newsletterObject = new papaya_newsletter;
      $this->newsletterObject->module = $this;
    }
    return $this->newsletterObject;
  }

  /**
  * Sets the newsletter object to use.
  * @param object $newsletterObject
  */
  public function setNewsletterObject($newsletterObject) {
    $this->newsletterObject = $newsletterObject;
  }

  /**
  * Sets page configuration data
  *
  * @param array $data current configuration data
  */
  public function setPageData($data) {
    $this->data = $data;
  }

  /**
  * Main execution method.
  *
  * @return mixed string error message or integer 0 for success
  */
  public function run() {
    $result = 0;
    if (!empty($this->data['mailinggroup_id']) && !empty($this->data['newsletter_list_id'])) {
      $shortMessage = 'Error while running newsletter robot cronjob';
      $newsletterObject = $this->getNewsletterObject();
      // Load selected mailing group
      $newsletterObject->loadOneMailingGroup((int)$this->data['mailinggroup_id']);
      if (!empty($newsletterObject->oneMailingGroup)) {
        $this->owner->cronOutput('Newsletter loaded.');
        // Add new mailing to selected mailing group
        $newMailingId = $this->addNewMailing();
        if ($newMailingId) {
          $this->owner->cronOutput('New mailing created.');
          // generate output, set params property because addMailingOutput use it directly
          $outputId = $this->addNewMailingOutput($newMailingId);
          if ($outputId !== FALSE) {
            $this->owner->cronOutput('Mailing output created.');
            // parse output with templates before sending
            if (FALSE !== $this->parseMailingOutputs($outputId)) {
              $this->owner->cronOutput('Mailing output parsed.');
              if ((bool)$this->data['save_to_queue'] !== FALSE) {
                // load current output after parsing
                $newsletterObject->loadOneMailingOutput($outputId);
                // add to queue for sending
                if (FALSE === $newsletterObject->addToQueue(
                    $this->data['newsletter_list_id'], $outputId, 'all')) {
                  $result = 'Error: could not add mailing output to queue'.
                    '(using papaya_newsletter::addToQueue, data: '.
                    $this->data['newsletter_list_id'].' (newsletter id), '.
                    $outputId.' (output id))';
                  $this->owner->logMsg(MSG_ERROR, PAPAYA_LOGTYPE_CRONJOBS, $shortMessage, $result);
                } else {
                  $this->owner->cronOutput('Mailing output added to queue.');
                }
              }
            } else {
              $result = 'Error: could not parse mailing output '.
                '(using papaya_newsletter::parseMailingOutput)';
              $this->owner->logMsg(MSG_ERROR, PAPAYA_LOGTYPE_CRONJOBS, $shortMessage, $result);
            }
          } else {
            $result = 'Error: could not add mailing output '.
              '(using papaya_newsletter::addMailingOutput)';
            $this->owner->logMsg(MSG_ERROR, PAPAYA_LOGTYPE_CRONJOBS, $shortMessage, $result);
          }
        } elseif (is_null($newMailingId)) {
          $this->owner->cronOutput('No content from feeds, stopping.');
        } else {
          $result = 'Error: could not add mailing (using papaya_newsletter::addMailing)';
          $this->owner->logMsg(MSG_ERROR, PAPAYA_LOGTYPE_CRONJOBS, $shortMessage, $result);
        }
      } else {
        $result = 'Error: could not load mailing group '.
          '(using papaya_newsletter::loadOneMailingGroup, '.
          'mailing group id: '.(int)$this->data['mailinggroup_id'].')';
        $this->owner->logMsg(MSG_ERROR, PAPAYA_LOGTYPE_CRONJOBS, $shortMessage, $result);
      }
    }
    return $result;
  }

  /**
  * Returns available mailing groups.
  *
  * @return array
  */
  public function getMailingGroups() {
    $newsletterObject = $this->getNewsletterObject();
    $newsletterObject->loadMailingGroups();
    return $newsletterObject->mailingGroups;
  }

  /**
  * Returns available newsletter lists.
  *
  * @return array
  */
  public function getNewsletterLists() {
    $newsletterObject = $this->getNewsletterObject();
    $newsletterObject->loadNewsletterLists();
    return $newsletterObject->newsletterLists;
  }

  /**
  * Add a new mailing.
  *
  * @return mixed new mailing id or FALSE
  */
  public function addNewMailing() {
    $newsletterObject = $this->getNewsletterObject();
    $mailingData = array(
      'mailinggroup_id' => (int)$this->data['mailinggroup_id'],
      'lng_id' => empty($this->data['language_id'])
        ? (int)$this->papaya()->options['PAPAYA_CONTENT_LANGUAGE']
        : (int)$this->data['language_id'],
      'mailing_title' => sprintf('Mailing %s', date('Y-m-d H:i')),
      'mailing_url' => (!empty($this->data['mailing_url'])) ? $this->data['mailing_url'] : '',
      'unsubscribe_url' => (!empty($this->data['unsubscribe_url'])) ?
        $this->data['unsubscribe_url'] :
        ''
    );
    return $newsletterObject->addMailing($mailingData, TRUE);
  }

  /**
  * Add a new mailing output and saves it with contents.
  *
  * @param integer $newMailingId
  * @return mixed mailingoutput id or FALSE
  */
  public function addNewMailingOutput($newMailingId) {
    $newsletterObject = $this->getNewsletterObject();
    $newsletterObject->params = array(
      'mailing_id' => $newMailingId,
      'mailingoutput_title' => sprintf('Output %s', date('Y-m-d H:i')),
      'mailingoutput_subject' =>
        $newsletterObject->oneMailingGroup['mailinggroup_default_subject'],
      'mailingoutput_sender' =>
        $newsletterObject->oneMailingGroup['mailinggroup_default_sender'],
      'mailingoutput_sendermail' =>
        $newsletterObject->oneMailingGroup['mailinggroup_default_senderemail'],
      'mailingoutput_text_view' =>
        $newsletterObject->oneMailingGroup['mailinggroup_default_textview'],
      'mailingoutput_html_view' =>
        $newsletterObject->oneMailingGroup['mailinggroup_default_htmlview'],
    );
    // add mailing output
    $result = $newsletterObject->addMailingOutput();
    $newsletterObject->params = array();
    // return mailing output id
    return $result;
  }

  /**
  * Parse text and html outputs for given output id.
  *
  * @param integer $outputId
  * @return boolean success
  */
  public function parseMailingOutputs($outputId) {
    $newsletterObject = $this->getNewsletterObject();
    $newsletterObject->params = array('mailingoutput_mode' => 1);
    $resultText = $newsletterObject->parseMailingOutput($outputId);
    $newsletterObject->params = array('mailingoutput_mode' => 2);
    $resultHtml = $newsletterObject->parseMailingOutput($outputId);
    $newsletterObject->params = array();
    return (!$resultText && !$resultHtml) ? FALSE : TRUE;
  }
}

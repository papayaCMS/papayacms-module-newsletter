<?php
/**
* Implements the access to an external mailbox account. Read-Only access. Mails of the
* account will be stored in the database
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
* @version $Id: cronjob_mailbox_reader.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Basic class cronjobs
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_cronjob.php');

/**
* Implements the access to an external mailbox account. Read-Only access.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class cronjob_mailbox_reader extends base_cronjob {

  /**
  * editFields array
  *
  * @var array $editFields
  */
  var $editFields = array(
    'mail_protocol' => array(
      'Protocol', 'isNoHTML', TRUE, 'combo', array('pop3' => 'POP3', 'imap' => 'IMAP')
    ),
    'mail_server' => array('Server', 'isSomeText', TRUE, 'input', 200, '',''),
    'mail_ssl'  => array('Use SSL', 'isNum', TRUE, 'yesno', '', '', 1),
    'mail_login_secure'  => array('Use secure login', 'isNum', TRUE, 'yesno', '', '', 0),
    'mail_port' => array('Port', 'isNum', TRUE, 'input', 6, '', 110),
    'mail_user' => array('Login', 'isSomeText', TRUE, 'input', 200, '', ''),
    'mail_password' => array('Password', 'isSomeText', TRUE, 'password', 200)
  );

  /**
  * mailbox object
  * @var mailbox_reader_pop3 $mailbox
  */
  var $mailbox = NULL;

  /**
  * setting up the mailbox reader
  *
  * @param string $method
  * @return boolean
  */
  function initialization() {
    $this->setDefaultData();
    if (extension_loaded('imap')) {
      include_once(dirname(__FILE__).'/mailbox_reader_imap.php');
      $this->mailbox = new mailbox_reader_imap;
      $this->mailbox->protocol = $this->data['mail_protocol'];
    } elseif ('pop3' == $this->data['mail_protocol']) {
      include_once(dirname(__FILE__).'/external/mailbox_reader_pop3.php');
      $this->mailbox = new mailbox_reader_pop3;
    } else {
      echo "unknown protocol";
    }
    $this->mailbox->setConnectionParameters(
      $this->data['mail_server'],
      $this->data['mail_port'],
      $this->data['mail_ssl'],
      $this->data['mail_user'],
      $this->data['mail_password'],
      $this->data['mail_login_secure']
    );
    return TRUE;
  }

  /**
  * get emails and store them in the database
  *
  */
  function getNextMail() {
    $this->mailbox->getNewMessages();
  }

  function checkExecParams() {
    if (isset($this->data) && is_array($this->data)) {
      $result = '';
      if (isset($this->data['mail_protocol']) && !empty($this->data['mail_protocol'])) {
        $result .= 'Used protocol: '.$this->data['mail_protocol'].' ';
      }
      if (isset($this->data['mail_server']) && !empty($this->data['mail_server'])) {
        $result .= 'Used server: '.$this->data['mail_server'];
      }
      if (isset($this->data['mail_port']) &&
          intval($this->data['mail_port']) >= 0 &&
          intval($this->data['mail_port']) < 65536) {
        $result .= ':'.$this->data['mail_port'];
      }
      if (
          isset($this->data['mail_login']) &&
          !empty($this->data['mail_login']) &&
          isset($this->data['mail_password'])) {
        $result .= 'OK';
      }
      return $result;
    }
    return FALSE;
  }

  function execute() {
    echo "executing mailbox reader cronjob.\n";
    $this->initialization();
    $this->getNextMail();
    echo $this->mailbox->getError();
    return 0;
  }
}

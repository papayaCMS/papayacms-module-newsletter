<?php
/**
* This code opens an imap mailbox and reads in the messages to store them in
* the database.
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
* @version $Id: mailbox_reader_imap.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* inclusion of mailbox_reader
*/
require_once(dirname(__FILE__).'/mailbox_reader.php');

/**
* Read inputs of a imap account and store it in the database
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class mailbox_reader_imap extends mailbox_reader {
  /**
  * Parameters for the imap connection
  * @var string
  */
  var $options = '';

  /**
  * if set to TRUE, only emails will be processed that are marked as recent
  * @var boolean
  */
  var $recentOnly = FALSE;

  /**
  * Set to true when the headers of a message should be part of the stored message
  * @var boolean
  */
  var $includeHeaders = TRUE;

  /**
  * protocol string
  *
  * @var string
  */
  var $protocol = 'imap';

  /**
  * Array of the MIME-Types that will be stored. It's useful for skipping
  * images. Leave NULL to accept all types.
  * @var array
  */
  //var $allowedMimeTypes = NULL;

  /**
  * Receiving new Mails
  *
  * @return boolean
  * @access public
  */
  function getNewMessages() {
    if (!extension_loaded('imap')) {
      $msg = $this->_gt('IMAP extension not loaded. Please consult you system administrator.').LF;
      echo $msg;
      $this->error .= $msg;
      return FALSE;
    } else {
      echo $this->_gt('IMAP Reader invoked.').LF;
      $mails = array();

      // setup connection
      $mbox = $this->_connect();
      if ($mbox === FALSE) {
        return FALSE;
      }

      $check = imap_check($mbox);
      printf(
        $this->_gt('Mailbox contains %s messages, %s are recent messages.').LF,
        $check->Nmsgs,
        $check->Recent
      );
      if ($this->recentOnly) {
        $count = $check->Recent;
      } else {
        $count = $check->Nmsgs;
      }
      if ($count > 0) {
        $uids = array();
        if (!$this->recentOnly) {
          // get uids of every mail
          for ($i = 1; $i <= $count; $i++) {
            $uids[] = imap_uid($mbox, $i);
            // imap_uid() do not return the expected uid
            // workaround:
            //$uids[] = $this->_getUid($mbox, $i);
            // but this is incompatible with imap functions
          }
        } /*else {
          // TODO get uids of recent mails
        } */
        $uids = $this->_checkForNewMessages($uids);
        $count = count($uids);
        printf($this->_gt("%s messages identified as new messages.").LF, $count);
        if ($count > 0) {
          $mails = $this->_getMessageContents($mbox, $uids);
          if (!empty($mails)) {
            if (!$this->_storeMails($mails)) {
              $this->error .= $this->_gt('Error by storing mails in the database.').LF;
              return FALSE;
            }
          }
        }
      }
      imap_close($mbox);
      return TRUE;
    }
  }

  /**
  * Gets the uid of given message number
  * @param $imap_Stream
  * @param $msg_no
  * @return string
  * @access private
  */
  function _getUid(&$imapStream, $msgNo) {
    $header = imap_headerinfo($imapStream, $msgNo);
    return $header->message_id;
  }

  /**
  * requests the imap server of the message contents of given uids and store
  * them in the returned array. subject, sender and date will be extracted.
  *
  * @param $imap ressource to imap mailbox
  * @param $uids array of uids
  * @return array of message contents
  */
  function _getMessageContents(&$imap, $uids) {
    $messages = array();
    foreach ($uids as $uid) {
      $content = '';
      if ($this->includeHeaders) {
        echo printf("Fetching message %d".LF, (int)$uid);
        $content = imap_fetchheader($imap, $uid, FT_UID | FT_PREFETCHTEXT);
      }
      $structure = imap_fetchstructure($imap, $uid, FT_UID);
      $parts = $this->_createPartArray($structure);
      foreach ($parts as $part) {
        $content .= imap_utf8(imap_fetchbody($imap, $uid, $part['part_number'], FT_UID));
      }
      $header = imap_headerinfo(
      $imap,
      imap_msgno($imap, $uid),
      255,
      255
      );
      $message = array(
        'mail_uid'      => $uid,
        'date'          => $header->date,
        'subject'       => imap_utf8($header->subject),
        'sender'        => imap_utf8($header->fromaddress),
        'content'       => $content,
        'category_id'   => 1  // 1 means new message... not rated yet
      );
      $messages[] = $message;
    }
    return $messages;
  }

  /**
  * Returns an array with the uids of the messages that has not been stored in the db
  *
  * @param array $uids List of UIDS to compare
  * @return array List of UIDS not present in the Database
  * @access private
  */
  function _checkForNewMessages($uids) {
    $oldUids = array();
    $sql = "SELECT mail_uid FROM %s";
    $params = array($this->tableMails);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $oldUids[] = $row['mail_uid'];
      }
    }
    return array_diff($uids, $oldUids);
  }

  /**
  * Opens a connection to the specified server.
  * Returns the ressource on success, false on fail
  *
  * @param string $server
  * @param integer $port
  * @return ressource
  * @access private
  */
  function _connect() {
    $parameters = 0;
    $options = '';
    if ($this->protocol == 'imap') {
      $parameters &= OP_READONLY;
    }
    if (!empty($this->protocol)) {
      $options .= '/'.$this->protocol;
    }
    if ($this->ssl) {
      $options .= '/ssl/novalidate-cert';
    }
    if ($this->secure) {
      $parameters &= OP_SECURE;
      $options .= '/secure';
    }
    $mailbox = sprintf('{%s:%s%s}INBOX', $this->server, $this->port, $options);
    if (FALSE !== ($mbox = imap_open($mailbox, $this->login, $this->pass, $parameters))) {
        return $mbox;
    } else {
      $this->error .= $this->_gt("Can't connect:")." " . imap_last_error() .LF;
    }
    return FALSE;
  }

  /**
  * Reorganize the object returned by imap_fetchstructure() ordered by parts
  *
  * @param $structure
  * @param $prefix
  * @return array
  * @access private
  */
  function _createPartArray($structure, $prefix="") {
    $part_array = array();
    if (isset($structure->parts) &&
        is_object($structure->parts) &&
        sizeof($structure->parts) > 0) {    // There some sub parts
      foreach ($structure->parts as $count => $part) {
        $this->_addPartToArray($part, $prefix.($count + 1), $part_array);
      }
    } else {
      // Email does not have a seperate mime attachment for text
      // I've changed 'part_object' => $obj to 'part_object => '' so as to
      // fix a notice, since $obj is undefined.
      $part_array[] = array('part_number' => $prefix.'1', 'part_object' => '');
    }
    return $part_array;
  }

  /**
  * called by _createPartArray and itself
  *
  * @param $obj
  * @param $partno
  * @param $part_array
  * @return unknown_type
  * @access private
  */
  function _addPartToArray($obj, $partno, &$part_array) {
    $part_array[] = array('part_number' => $partno, 'part_object' => $obj);
    if ($obj->type == 2) {
      // Check to see if the part is an attached email message, as in the RFC-822 type
      if (sizeof($obj->parts) > 0) {
        // Check to see if the email has parts
        foreach ($obj->parts as $count => $part) {
          /* Iterate here again to compensate for the broken way that
             imap_fetchbody() handles attachments */
          if (sizeof($part->parts) > 0) {
            foreach ($part->parts as $count2 => $part2) {
              $this->_addPartToArray($part2, $partno.".".($count2 + 1), $part_array);
            }
          } else {
            // Attached email does not have a seperate mime attachment for text
            $part_array[] = array('part_number' => $partno.'.'.($count + 1), 'part_object' => $obj);
          }
        }
      } else {    // Not sure if this is possible
        $part_array[] = array('part_number' => $prefix.'.1', 'part_object' => $obj);
      }
    } else {
      // If there are more sub-parts, expand them out.
      if (sizeof($obj->parts) > 0) {
        foreach ($obj->parts as $count => $p) {
          $this->_addPartToArray($p, $partno.".".($count + 1), $part_array);
        }
      }
    }
  }
}


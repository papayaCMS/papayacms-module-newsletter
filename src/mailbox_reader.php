<?php
/**
* This is an abstract class for the different mailbox reader implementations
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
* @version $Id: mailbox_reader.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Read inputs of an account and store it in the database
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class mailbox_reader extends base_db {

  /**
  * Error string.
  *
  * @var string $error
  */
  var $error = '';

  /**
  * address of the mailserver
  * @var string
  */
  var $server = '';

  /**
  * login name
  * @var string
  */
  var $login = '';

  /**
  * Passwort used at login
  * @var string
  */
  var $pass = '';

  /**
  * Portnumber on mailserver used for connection.
  * @var integer
  */
  var $port = 0;

  /**
  * 1 (=TRUE) when ssl is used, otherwise 0 (=FALSE).
  *
  * @var integer
  */
  var $ssl = 0;

  /**
  * 1 (=TRUE) when secure login is used, otherwise 0 (=FALSE).
  *
  * @var integer
  */
  var $secure = 0;

  /**
  * Name of the table where new message will be stored.
  * @var string
  */
  var $tableMails = NULL;

  function __construct() {
    $this->tableMails = PAPAYA_DB_TABLEPREFIX.'_newsletter_bouncinghandler_mails';
  }

  /**
  * Receiving new Mails
  *
  * @return mixed
  * @access public
  */
  function getNewMessages() {
    echo "Method getNewMessages() is not implemented.".LF;
    return FALSE;
  }

  /**
  * Stores given eMails in the Database. Returns TRUE on success.
  *
  * @param array $mails
  * @return boolean
  * @access private
  */
  function _storeMails($mails) {
    if (!$this->databaseInsertRecords($this->tableMails, $mails)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
  * returns error variable
  *
  * @return string
  * @access public
  */
  function getError() {
    return $this->error;
  }

  /**
  * Set the parameter values requierd for connection
  *
  * @param string $server
  * @param int $port
  * @param int $ssl
  * @param string $login
  * @param string $password
  * @access public
  */
  function setConnectionParameters($server, $port, $ssl, $login, $password, $secureLogin = FALSE) {
    $this->server = $server;
    $this->port = $port;
    $this->ssl = ($ssl === "1")? TRUE : FALSE;
    $this->secure = ($secureLogin === "1")? TRUE : FALSE;
    $this->login = $login;
    $this->pass = $password;
  }
}
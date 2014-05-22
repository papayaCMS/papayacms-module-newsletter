<?php
/**
 * This code opens a pop3 mailbox and reads in the messages stored there one at a time.
 *
 * @abstract Each message is read and checked for some kind of validity. Regular allowable
 * messages are then separated into a subject and a full body line (handles base64 encoded
 * messages as well). Once the message was processed, its flagged for deletion, which
 * happens when the server closes the connection to the mailbox.
 * @filesource http://www.weberdev.com/get_example-4015.html
 * @license N/A
 * @package module_newsletter
 * @version $Id: mailbox_reader_pop3.php 2 2013-12-09 15:38:42Z weinert $
 */

/**
 * Read inputs of a pop3 account and store it in the database
 *
 * @package module_newsletter
 *
 */
class mailbox_reader_pop3 extends base_db {
  /**
   * Error string.
   *
   * @var string $error
   */
  var $error     = '';
  /**
   * Default timeout before giving up on a network operation. In seconds
   *
   * @var int $timeout
   */
  var $timeout   = 90;
  /**
   * Mailbox msg count
   *
   * @var int $mbCount
   */
  var $mbCount     = -1;
  /**
   * Socket buffer for socket fgets() calls, max per RFC 1939 the returned line a POP3
   * server can send is 512 bytes.
   *
   * @var int $buffer
   */
  var $buffer    = 512;
  var $server    = '';
  var $login     = '';
  var $pass      = '';
  var $port      = 110;
  /**
   * Set by noop(). See rfc1939.txt
   *
   * @var boolean $RFC1939
   */
  var $rfc1939   = TRUE;
  /**
   * List of messages from server
   *
   * @var array $msg_list_array
   */
  var $msg_list_array = array();

  /**
   * connection
   *
   * @var resource $fp
   */
  var $fp = NULL;

  /**
   * Name of the table where the emails will be stored
   *
   * @var string $tableMails
   */
  var $tableMails = '';

  /**
   * get instance
   *
   * @return object
   */
  function getInstance() {
    static $mailboxReaderPOP3;
    if (!(isset($mailboxReaderPOP3) && is_object($mailboxReaderPOP3))) {
      $mailboxReaderPOP3 = new mailbox_reader_pop3();
    }
    return $mailboxReaderPOP3;
  }

  /**
   * Receiving new Mails
   *
   * @return mixed
   */
  function getNewMessages() {
    $this->tableMails = PAPAYA_DB_TABLEPREFIX.'_newsletter_bouncinghandler_mails';
    set_time_limit($this->timeout);
    $mails = array();

    $this->_connect();
    $this->mbCount = $this->_login();
    if (!$this->mbCount || $this->mbCount == -1) {
      $this->error .= "Check for messages: No Messages.".var_export($this->mbCount,TRUE).LF;
      return FALSE;
    }

    if ($this->mbCount < 1) {
      return FALSE;
    } else {
      echo "Check for messages: $this->mbCount Messages\n";
      $this->msg_list_array = $this->_uidl("");
      set_time_limit($this->timeout);
    }

    // check wich messages are not already stored in the database
    $this->_checkForNewMessages();
    // loop thru the array to get the new messages
    for ($i = 1; $i <= $this->mbCount; $i++) {
      if ($this->msg_list_array[$i] != 'old' && $this->msg_list_array[$i] != 'deleted') {
        set_time_limit($this->timeout);
        $msgOne = $this->_get($i);
        if (!$msgOne || gettype($msgOne) != 'array') {
          $this->error .= "oops, Message not returned by the server.<BR>\n";
          return FALSE;
        }
        /*
        call the function to read the message
        returns TRUE if access, breakdown and insertion
        in to db are completed sucessfully
        */
        $mails[] = $this->_message_details($msgOne, $i);
      }
    }
    // insert into database
    if (!empty($mails)) {
      if (!$this->_storeMails($mails)) {
        $this->error .= 'Error by storing mails in the database.'.LF;
        return FALSE;
      }
    } else {
      echo "no new messages.".LF;
      return FALSE;
    }
    //close the email box and delete all messages marked for deletion
    $this->_quit();
    return TRUE;
  }

  /**
   * Returns an array with the uids of the messages that has not been stored in the db
   *
   */
  function _checkForNewMessages() {
    $oldUids = array();
    $sql = "SELECT mail_uid FROM %s";
    $params = array($this->tableMails);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $oldUids[] = $row['mail_uid'];
      }
    }
    for ($i = 1; $i <= $this->mbCount; $i++) {
      if (in_array($this->msg_list_array[$i], $oldUids)) {
        $this->msg_list_array[$i] = 'old';
      }
    }
  }

  /**
   * Opens a socket to the specified server. Unless overridden, port defaults to 110.
   * Returns TRUE on success, FALSE on fail
   *
   * @param string $server
   * @param integer $port
   * @return boolean
   */
  function _connect() {
    $server = $this->server;
    $port = $this->port;
    // If MAILSERVER is set, override $server with its value
    $e_server = $server;

    if (!$this->fp = fsockopen($e_server, $port, $errno, $errstr)) {
      $this->error = "POP3 connect: Error [$errno] [$errstr]";
      return FALSE;
    }

    stream_set_blocking($this->fp, TRUE);
    $this->_update_timer();
    $reply = fgets($this->fp, $this->buffer);
    $reply = $this->_strip_clf($reply);
    if (!$this->_is_ok($reply)) {
      $this->error = "POP3 connect: Error [$reply]";
      return FALSE;
    }

    // $banner = parse_banner($reply); usage?
    $this->rfc1939 = $this->_noop();
    if ($this->rfc1939) {
      $this->error = "POP3: premature NOOP OK, NOT an RFC 1939 Compliant server";
      $this->_quit();
      return FALSE;
    }
    return TRUE;
  }

  /**
   * check if the mails server supports the NOOP-command
   *
   * @return boolean
   */
  function _noop () {
    $cmd = "NOOP";
    $reply = $this->_send_cmd($cmd);
    if (!$this->_is_ok($reply)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Sends the USER command, returns TRUE or FALSE
   *
   * @param unknown_type $user
   * @return boolean
   */
  function _user ($user) {
    if (empty($user)) {
      $error = "POP3 user: no user id submitted";
      return FALSE;
    }

    $reply = $this->_send_cmd("USER $user");
    if (!$this->_is_ok($reply)) {
      $this->error = "POP3 user: Error [$reply]";
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Sends the PASS command, returns # of msgs in mailbox,
   * returns FALSE (undef) on Auth failure
   *
   * @return mixed
   */
  function _pass () {
    if (empty($this->pass)) {
      $this->error .= "POP3 pass: no password submitted".LF;
      return FALSE;
    }

    $reply = $this->_send_cmd("PASS $this->pass");
    if (!$this->_is_ok($reply)) {
      $this->error = "POP3 pass: authentication failed [$reply]".LF;
      $this->_quit();
      return FALSE;
    }
    // Auth successful.
    echo "User Authenticated".LF;
    $count = $this->_last("count");
    $this->mbCount = $count;
    $this->rfc1939 = $this->_noop();
    if (!$this->rfc1939) {
      $this->error .= "POP3 pass: NOOP failed. Server not RFC 1939 compliant".LF;
      $this->_quit();
      return FALSE;
    }
    return $count;
  }

  /**
   * Sends both user and pass. Returns # of msgs in mailbox or
   * FALSE on failure (or -1, if the error occurs while getting
   * the number of messages.)
   *
   * @return mixed
   */
  function _login () {
    if (!$this->_user($this->login)) {
      // Preserve the error generated by user()
      $this->error .= "Login Error. $this->login".LF;
      return FALSE;
    }

    $count = $this->_pass();
    if(!$count || $count == -1) {
      // Preserve the error generated by last() and pass()
      return "-1";
    }
    return $count;
  }

  /**
   * Gets the header and first $numLines of the msg body
   * returns data in an array with each returned line being
   * an array element. If $numLines is empty, returns
   * only the header information, and none of the body.
   *
   * @param unknown_type $msgNum
   * @param unknown_type $numLines
   * @return unknown
   */
  function _top ($msgNum, $numLines = "0") {
    $this->_update_timer();
    $cmd = "TOP $msgNum $numLines";
    fwrite($this->fp, "$cmd\r\n");
    $reply = fgets($this->fp, $this->buffer);
    $reply = $this->_strip_clf($reply);

    if (!$this->_is_ok($reply)) {
      $this->error .= "POP3 top: Error [$reply]".LF;
      return FALSE;
    }

    $count = 0;
    $msgArray = array();

    $line = fgets($this->fp, $this->buffer);
    while (!ereg("^\.\r\n", $line) && !empty($line)) {
      $msgArray[$count] = $line;
      $count++;
      $line = fgets($this->fp, $this->buffer);
    }

    return $msgArray;
  }

  /**
   * If called with an argument, returns that msgs' size in octets
   * No argument returns an associative array of undeleted
   * msg numbers and their sizes in octets
   *
   * @param unknown_type $msgNum
   * @return mixed
   */
  function _pop_list ($msgNum = "") {
    $total = $this->mbCount;
    if (!$total || $total == -1) {
      return FALSE;
    }
    if ($total == 0) {
      return array("0","0");
    }

    $this->_update_timer();

    if (!empty($msgNum)) {
      $cmd = "LIST $msgNum";
      fwrite($this->fp,"$cmd\r\n");
      $reply = fgets($this->fp, $this->buffer);
      $reply = $this->_strip_clf($reply);
      if (!$this->_is_ok($reply)) {
        $this->error .= "POP3 pop_list: Error [$reply]".LF;
        return FALSE;
      }
      list($junk, $num, $size) = explode(" ", $reply);
      return $size;
    }
    $cmd = "LIST";
    $reply = $this->_send_cmd($cmd);
    if (!$this->_is_ok($reply)) {
      $reply = $this->_strip_clf($reply);
      $this->error .= "POP3 pop_list: Error [$reply]".LF;
      return FALSE;
    }
    $msgArray = array();
    $msgArray[0] = $total;
    for ($msgC = 1; $msgC <= $total; $msgC++) {
      $line = fgets($this->fp, $this->buffer);
      $line = $this->_strip_clf($line);
      if (ereg("^\.",$line)) {
        $this->error .= "POP3 pop_list: Premature end of list".LF;
        return FALSE;
      }
      list($thisMsg, $msgSize) = explode(" ",$line);
      settype($thisMsg, "integer");
      if ($thisMsg != $msgC) {
        $msgArray[$msgC] = "deleted";
      }
      else {
        $msgArray[$msgC] = $msgSize;
      }
    }
    return $msgArray;
  }

  /**
   * Retrieve the specified msg number. Returns an array
   * where each line of the msg is an array element.
   *
   * @param int $msgNum
   * @return array
   */
  function _get($msgNum) {
    $this->_update_timer();
    $cmd = "RETR $msgNum";
    $reply = $this->_send_cmd($cmd);

    if (!$this->_is_ok($reply)) {
      $this->error = "POP3 get: Error [$reply]";
      return FALSE;
    }
    $count = 0;
    $msgArray = array();
    $line = fgets($this->fp, $this->buffer);
    while (!preg_match("/^\.\r\n/", $line) && !empty($line)) {
      $msgArray[$count] = utf8_encode($line);
      $count++;
      $line = fgets($this->fp, $this->buffer);
    }
    return $msgArray;
  }

  /**
   * Returns the highest msg number in the mailbox.
   * returns -1 on error, 0+ on success, if type != count
   * results in a popstat() call (2 element array returned)
   *
   * @param string $type
   * @return array
   */
  function _last($type = "count") {
    $last = -1;
    $reply = $this->_send_cmd("STAT");
    if (!$this->_is_ok($reply)) {
      $this->error = "POP3 last: error [$reply]";
      return $last;
    }

    $replyVariables = explode(" ", $reply);
    $count = $replyVariables[1];
    $size = $replyVariables[2];
    settype($count, "integer");
    settype($size, "integer");
    if ($type != "count") {
      return array($count, $size);
    }
    return $count;
  }

  /**
   * Resets the status of the remote server. This includes
   * resetting the status of ALL msgs to not be deleted.
   * This method automatically closes the connection to the server.
   *
   * @return boolean
   */
  function _resets() {
    $reply = $this->_send_cmd("RSET");
    if (!$this->_is_ok($reply)) {
      // The POP3 RSET command -never- gives a -ERR
      // response - if it ever does, something TRUEly
      // wild is going on.
      $this->error = "POP3 reset: Error [$reply]";
    }
    $this->_quit();
    return TRUE;
  }

  /**
   * Sends a user defined command string to the POP server and returns the results.
   * Useful for non-compliant or custom POP servers. Do NOT include the \r\n as part of
   * your command string - it will be appended automatically.
   * The return value is a standard fgets() call, which will read up to $buffer bytes of
   * data, until it encounters a new line, or EOF, whichever happens first.
   * This method works best if $cmd responds with only one line of data.
   *
   * @param string $cmd
   * @return mixed
   */
  function _send_cmd($cmd) {
    if (!isset($this->fp)) {
      $this->error = "POP3 send_cmd: No connection to server";
      return FALSE;
    }
    if (empty($cmd)) {
      $this->error = "POP3 send_cmd: Empty command string";
      return FALSE;
    }

    $this->_update_timer();
    fwrite($this->fp, "$cmd\r\n");
    $reply = fgets($this->fp, $this->buffer);
    $reply = $this->_strip_clf($reply);
    return $reply;
  }

  /**
   * Closes the connection to the POP3 server, deleting any msgs marked as deleted.
   *
   * @return boolean
   */
  function _quit() {
    $cmd = "QUIT";
    fwrite($this->fp,"$cmd\r\n");
    $reply = fgets($this->fp, $this->buffer);
    $reply = $this->_strip_clf($reply);
    fclose($this->fp);
    return TRUE;
  }


  /**
   * Return TRUE or FALSE on +OK or -ERR
   *
   * @param string $cmd
   * @return boolean
   */
  function _is_ok ($cmd = "") {
    if(empty($cmd)) {
      return FALSE;
    }
    if (ereg ("^\+OK", $cmd )) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Strips \r\n from server responses
   *
   * @param string $text
   * @return string
   */
  function _strip_clf($text = "") {
    if (empty($text)) {
      return $text;
    }
    $stripped = ereg_replace("\r", "", $text);
    $stripped = ereg_replace("\n", "", $stripped);
    return $stripped;
  }

  /**
  * Obsolete?
  * Usage before: $this->_connect before noop-Error-Condition
  */
  function _parse_banner($server_text) {
    $outside = TRUE;
    $banner = "";
    $length = strlen($server_text);
    for ($count = 0; $count < $length; $count++) {
      $digit = substr($server_text, $count, 1);
      if (!empty($digit)) {
        if(!$outside && $digit != '<' && $digit != '>') {
          $banner .= $digit;
        }
        if ($digit == '<') {
          $outside = FALSE;
        }
        if($digit == '>') {
          $outside = TRUE;
        }
      }
    }
    $banner = $this->_strip_clf($banner); // Just in case
    return "<$banner>";
  }

  /**
   * Returns an array of 2 elements. The number of undeleted
   * msgs in the mailbox, and the size of the mbox in octets.
   *
   * @return mixed
   */
  function _popstat() {
    $popArray = $this->_last("array");
    if ($popArray == -1) {
      return FALSE;
    }
    if (!$popArray || empty($popArray)) {
      return FALSE;
    }
    return $popArray;
  }

  /**
   * Returns the UIDL of the msg specified. If called with
   * no arguments, returns an associative array where each
   * undeleted msg num is a key, and the msg's uidl is the element
   * Array element 0 will contain the total number of msgs
   *
   * @param int $msgNum
   * @return mixed
   */
  function _uidl ($msgNum = "") {
    if (!empty($msgNum)) {
      $cmd = "UIDL $msgNum";
      $reply = $this->_send_cmd($cmd);
      if(!$this->_is_ok($reply)) {
        $this->error = "POP3 uidl: Error [$reply]";
        return FALSE;
      }
      list($ok, $num, $myUidl) = explode(" ", $reply);
      return $myUidl;
    } else {
      $uidlArray = array();
      $total = $this->mbCount;
      $uidlArray[0] = $total;
      if ($total < 1) {
        return $uidlArray;
      }
      $cmd = "UIDL";
      fwrite($this->fp, "UIDL\r\n");
      $reply = fgets($this->fp, $this->buffer);
      $reply = $this->_strip_clf($reply);

      if(!$this->_is_ok($reply)) {
        $this->error = "POP3 uidl: Error [$reply]";
        return FALSE;
      }

      $line = "";
      $count = 1;
      $line = fgets($this->fp, $this->buffer);
      while (!preg_match("/^\.\r\n/", $line)) {
        list ($msg, $msgUidl) = explode(' ', $line);
        $msgUidl = $this->_strip_clf($msgUidl);
        if ($count == $msg) {
          $uidlArray[$msg] = $msgUidl;
        }
        else {
          $uidlArray[$count] = "deleted";
        }
        $count++;
        $line = fgets($this->fp, $this->buffer);
      }
    }
    return $uidlArray;
  }

  /**
   * Flags a specified msg as deleted. The msg will not
   * be deleted until a quit() method is called.
   *
   * @param int $msgNum
   * @return boolean
   */
  function _delete($msgNum = "") {
    /*
    if(empty($msgNum)) {
      $this->error = "POP3 delete: No msg number submitted";
      return FALSE;
    }
    $reply = $this->_send_cmd("DELE $msgNum");
    if(!$this->_is_ok($reply)) {
      $this->error = "POP3 delete: Command failed [$reply]";
      return FALSE;
    }
    return TRUE;*/
    return TRUE;
  }


  function _update_timer() {
    set_time_limit($this->timeout);
  }

  /**
   * extracts senders email adress from content and return it
   *
   * @param string $content
   * @return string
   * @access private
   */
  function _getSenderAddress($content) {
    $pattern = "(^
      From:(?:\s*)
      (?:
        (?:[^<]*<)?
        (?P<email>
          [-!\#$%&\.?'*+\\./0-9=?A-Z^_`a-z{|}~]+
          @[-!\#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+
          \.[-!\#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+
        )
        >?
       )
       (?:[\r\n]*)$)umx";
    if (preg_match($pattern, $content, $results) && isset($results['email'])) {
      return papaya_strings::strtolower($results['email']);
    } else {
      return 'root@localhost';
    }
  }

  /**
   * Get the Message Details
   *
   * @abstract Function to read the message and extract :
   * a. subject
   * b. date
   * c. split the body line by line
   *
   * @var array $MsgOne
   * @var int $msgNo
   * @return array
   */
  function _message_details($msgOne, $msgNo) {
    $content        = ''; // complete message as string
    $subject        = ''; // get the subject of the email
    $sender         = ''; // sender of the email
    $date           = ''; // get the date of the email
    // only allow text or html contents, skip other contens
    $contentType    = TRUE;

    foreach ($msgOne as $value) {
      if (preg_match('/^Content-Type:\s/i', $value)) {
        if (
             stristr($value, 'text/plain') === FALSE &&
             stristr($value, 'multipart/alternative') === FALSE &&
             stristr($value, 'multipart/mixed') === FALSE &&
             stristr($value, 'text/html') === FALSE &&
             stristr($value, 'multipart/report') === FALSE &&
             stristr($value, 'message/delivery-status') === FALSE
           ) {
          $contentType = FALSE;
        } else {
          $contentType = TRUE;
        }
      }
      if ($contentType) {
        $content .= $value;
        //get the subject line of the email
        if (empty($subject) && preg_match('/^Subject:\s/i', $value)) {
          $subject = trim(stristr($value, " "));
        }
        if (empty($sender) && preg_match('/^From:\s/i', $value)) {
          $sender = $this->_getSenderAddress($value);
        }
        //get the date of the email
        if (empty($date) && strlen(stristr($value, "Date")) > 1) {
          $date = trim(stristr($value, " "));
        }
      }
    }

    if (empty($subject)) {
      $subject = 'no subject';
    }
    if (empty($sender)) {
      $sender = 'unknown sender';
    }

    $message = array(
      'mail_uid' => $this->_uidl($msgNo),
      'date' => $date,
      'subject' => $subject,
      'sender' => $sender,
      'content' => $content,
      'category_id' => 1  // 1 means new message... not rated yet
    );
    return $message;
  }

  /**
   * Stores given eMails in the Database. Returns TRUE on success.
   *
   * @param array $mails
   * @return boolean
   * @access private
   */
  function _storeMails($mails) {
    if(!$this->databaseInsertRecords($this->tableMails, $mails)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
  * Returns error variable
  *
  * @return string
  * @access public
  */
  function getError() {
    return $this->error;
  }

  /**
  * Set the parameter values required for connection
  *
  * @param string $server
  * @param int $port
  * @param string $login
  * @param string $password
  * @access public
  */
  function setConnectionParameters($server, $port=110, $ssl, $login, $password,
                                   $secureLogin = FALSE) {
    $this->server = $server;
    $this->port = $port;
    $this->login = $login;
    $this->pass = $password;
  }
}


<?php
/**
* Cronjob-module, send queued newsletters
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
* @version $Id: cronjob_newsletter_send.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* Basic class cronjobs
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_cronjob.php');

/**
* Cronjob-module, send queued newsletters
*
* @package commercial
* @subpackage newsletter
*/
class cronjob_newsletter_send extends base_cronjob {

  var $editFields = array(
    'send_at_once' => array ('Send at once', 'isNum', TRUE, 'input', 200,
      'Determines how many newsletters are sent per call of the cronjob.', 100),
  );

  /**
  * Execute
  *
  * @access public
  * @return string
  */
  function execute() {
    $this->setDefaultData();
    include_once(dirname(__FILE__).'/base_newsletter_queue.php');
    $queue = new base_newsletter_queue;
    $queue->processQueue((int)$this->data['send_at_once']);
    return 0;
  }

  /**
  * Check execution parameters
  *
  * @access public
  * @return string or FALSE
  */
  function checkExecParams() {
    $this->setDefaultData();
    if (isset($this->data) && is_array($this->data)) {
      $result = '';
      if (isset($this->data['send_at_once']) && $this->data['send_at_once'] > 0) {
        $result .= sprintf(
          $this->_gt('%d newsletters will be sent at a time.').LF,
          $this->data['send_at_once']
        );
      }
      return $result;
    }
    return FALSE;
  }
}


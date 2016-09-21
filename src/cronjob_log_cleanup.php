<?php
/**
 * Cronjob module, clean up log
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
 * @version $Id: cronjob_newsletter_send.php 2 2013-12-09 15:38:42Z weinert $
 */

/**
 * Cronjob module class, clean up log
 *
 * @package Papaya-Modules
 * @subpackage Newsletter
 */
class cronjob_log_cleanup extends base_cronjob {
  /**
   * Configuration edit fields
   * @var array
   */
  public $editFields = [
    'age_days' => [
      'Minimum age in days',
      'isNum',
      TRUE,
      'input',
      10,
      'The cronjob will remove entries older than the designated number of days.',
      30
    ],
    'mode' => [
      'Mode',
      '(^(unsubscriptions|both)$)',
      TRUE,
      'radio',
      ['unsubscriptions' => 'Only unsubscriptions', 'both' => 'Subscriptions and unsubscriptions'],
      'Only remove unconfirmed unsubscriptions, or both subscriptions and unsubscriptions?',
      'unsubscriptions'
    ]
  ];

  /**
   * Log Cleanup object
   * @var LogCleanup
   */
  private $_logCleanup = NULL;

  /**
   * Execute
   *
   * @return mixed integer 0 on success, otherwise string error message
   */
  public function execute() {
    $this->setDefaultData();
    if ($this->logCleanup()->cleanup($this->data['age_days'], $this->data['mode'])) {
      return 0;
    }
    return "Newsletter log cleanup failed.";
  }

  /**
   * Get/set/initialize the Log Cleanup object
   *
   * @param PapayaModuleNewsletterLogCleanup $logCleanup optional, default value NULL
   * @return PapayaModuleNewsletterLogCleanup
   */
  public function logCleanup($logCleanup = NULL) {
    if ($logCleanup !== NULL) {
      $this->_logCleanup = $logCleanup;
    } elseif ($this->_logCleanup === NULL) {
      $this->_logCleanup = new PapayaModuleNewsletterLogCleanup();
    }
    return $this->_logCleanup;
  }
}
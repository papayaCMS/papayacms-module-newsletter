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
   *
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
    'Cleanup',
    'mode' => [
      'Unconfirmed Requests',
      '(^(unsubscriptions|both)$)',
      TRUE,
      'radio',
      ['unsubscriptions' => 'Only unsubscribe requests', 'both' => 'Subscribe and unsubscribe requests'],
      'Only remove unconfirmed unsubscribe requests, or both?',
      'unsubscriptions'
    ],
    'cleanup_subscriptions' => [
      'Subscriptions',
      'isNum',
      TRUE,
      'yesno',
      NULL,
      'Cleanup unconfirmed subscriptions without protocol entries',
      '0'
    ],
    'cleanup_subscribers' => [
      'Subscribers',
      'isNum',
      TRUE,
      'yesno',
      NULL,
      'Cleanup subscribers without subscriptions',
      '0'
    ],
  ];

  /**
   * Log Cleanup object
   *
   * @var LogCleanup
   */
  private $_logCleanup = NULL;

  /**
   * Execute
   *
   * @return mixed integer 0 on success, otherwise string error message
   */
  public function execute() {
    $sandbox = new Papaya\Message\Sandbox(
      function () {
        $this->setDefaultData();
        try {
          $this->notify(
            'Cleanup unconfirmed requests: %s and older %d days',
            $this->data['mode'],
            $this->data['age_days']
          );
          if (FALSE !== ($deleted = $this->logCleanup()->cleanupLog($this->data['age_days'], $this->data['mode']))) {
            $this->notify('Deleted %d unconfirmed requests.', $deleted === TRUE ? 0 : $deleted);
          } else {
            return "Newsletter cleanup failed at protocol step.";
          }
          if ($this->data['cleanup_subscriptions']) {
            $this->notify('Cleanup subscriptions');
            if (FALSE !== ($deleted = $this->logCleanup()->cleanupSubscriptions())) {
              $this->notify('Deleted %d unconfirmed subscriptions.', $deleted === TRUE ? 0 : $deleted);
            } else {
              return "Newsletter cleanup failed at subscriptions step.";
            }
          }
          if ($this->data['cleanup_subscribers']) {
            $this->notify('Cleanup subscribers');
            if (FALSE !== ($deleted = $this->logCleanup()->cleanupSubscribers())) {
              $this->notify('Deleted %d subscribers.', $deleted === TRUE ? 0 : $deleted);
            } else {
              return "Newsletter cleanup failed at subscribers step.";
            }
          }
        } catch (Exception $e) {
          return $e->getMessage();
        }
        return 0;
      }
    );
    $sandbox->papaya($this->papaya());
    return $sandbox();
  }

  public function notify($message, ...$parameters) {
    echo(count($parameters) > 0 ? vsprintf($message, $parameters) : $message), "\n";
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
      include_once(__DIR__.'/Log/Cleanup.php');
      $this->_logCleanup = new PapayaModuleNewsletterLogCleanup();
    }
    return $this->_logCleanup;
  }

  /**
   * Check execution parameters
   *
   * @return string|bool
   */
  public function checkExecParams() {
    $this->setDefaultData();
    $result = [];
    if (
      $this->data['age_days'] > 0 &&
      in_array($this->data['mode'], ['unsubscriptions', 'both'])
    ) {
      $result[] = sprintf(
        'Delete %s older than %d days.',
        $this->data['mode'],
        $this->data['age_days']
      );
    }
    if ($this->data['cleanup_subscriptions']) {
      $result[] = "Delete unconfirmed subscriptions without subscribe request.";
    }
    if ($this->data['cleanup_subscribers']) {
      $result[] = "Delete subscribers without subscriptions.";
    }
    return $result ? join("\n", $result) : FALSE;
  }
}

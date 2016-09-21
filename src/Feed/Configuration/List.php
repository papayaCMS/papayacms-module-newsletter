<?php
/**
* Database wrapper for a list of newsletter feed configurations
*
* This item allows to load a list of newsletter feed import configuration and delete one.
*
* @copyright 2010-2016 by dimensional GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/

/**
* Database wrapper for a list of newsletter feed configurations
*
* This item allows to load a list of newsletter feed import configuration and delete one.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class PapayaModuleNewsletterFeedConfigurationList
  extends PapayaDatabaseObjectList {

  /**
  * Map database fields to object properties
  *
  * @var array
  */
  protected $_fieldMapping = array(
    'mailingfeed_id' => 'id',
    'mailinggroup_id' => 'group_id',
    'mailingfeed_url' => 'url',
    'mailingfeed_minimum' => 'minimum',
    'mailingfeed_maximum' => 'maximum',
    'mailingfeed_period' => 'period',
    'mailingfeed_position' => 'position',
    'mailingfeed_template' => 'template'
  );

  /**
  * Load newsletter feed configurations for a newsletter
  *
  * @param integer $newsletterId
  * @param integer|NULL $limit
  * @param integer|NULL $offset
  */
  public function load($newsletterId, $limit = NULL, $offset = NULL) {
    $sql = "SELECT mailingfeed_id, mailinggroup_id, mailingfeed_url,
                   mailingfeed_minimum, mailingfeed_maximum,
                   mailingfeed_period, mailingfeed_position, mailingfeed_template
              FROM %s
             WHERE mailinggroup_id = '%d'
             ORDER BY mailingfeed_position, mailingfeed_id";
    $parameters = array(
      $this->databaseGetTableName('newsletter_feeds'), (int)$newsletterId
    );
    return $this->_loadRecords($sql, $parameters, 'mailingfeed_id', $limit, $offset);
  }

  /**
  * Delete an newsletter feed id from list
  *
  * @param integer $id
  * @return boolean
  */
  public function delete($id) {
    $deleted = (
      FALSE !== $this->databaseDeleteRecord(
        $this->databaseGetTableName('newsletter_feeds'), array('mailingfeed_id' => $id)
      )
    );
    if ($deleted && isset($this->_records[$id])) {
      unset($this->_records[$id]);
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Move a feed configuration up in the list
  *
  * @param integer $feedId
  * @return boolean updated
  */
  public function move($sourceId, $targetId) {
    $result = FALSE;
    if (isset($this->_records[$sourceId]) &&
        isset($this->_records[$targetId])) {
      $positions = array();
      $counter = 1;
      foreach ($this->_records as $id => $feed) {
        $positions[$id] = $counter++;
      }
      $sourcePosition = $positions[$sourceId];
      $targetPosition = $positions[$targetId];
      if ($sourcePosition != $targetPosition &&
          $sourcePosition > 0 &&
          $targetPosition > 0) {
        $positions[$sourceId] = $targetPosition;
        $positions[$targetId] = $sourcePosition;
      }
      foreach ($positions as $id => $position) {
        if ($this->_records[$id]['position'] != $position) {
          $this->databaseUpdateRecord(
            $this->databaseGetTableName('newsletter_feeds'),
            array('mailingfeed_position' => $position),
            array('mailingfeed_id' => $id)
          );
          $this->_records[$id]['position'] = $position;
          $result = TRUE;
        }
      }
      uasort($this->_records, array($this, 'comparePositions'));
    }
    return $result;
  }

  /**
  * Compare method, to allow sorting without an sql query
  *
  * @param integer $feedId
  * @return boolean updated
  */
  public function comparePositions($recordOne, $recordTwo) {
    if ($recordOne['position'] != $recordTwo['position']) {
      return (int)$recordOne['position'] < (int)$recordTwo['position'] ? -1 : 1;
    } else {
      return (int)$recordOne['id'] < (int)$recordTwo['id'] ? -1 : 1;
    }
  }

  /**
  * Get detail item for display/edit actions
  *
  * Because all data is already loaded into the list, an adiitional query is not nessessary.
  *
  * @param integer $feedId
  */
  public function getItem($feedId) {
    $item = new PapayaModuleNewsletterFeedConfigurationItem();
    if (isset($this->_records[$feedId])) {
      $item->assign($this->_records[$feedId]);
    }
    return $item;
  }
}
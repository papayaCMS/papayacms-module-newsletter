<?php
/**
* Database wrapper for a single newsletter feed configuration item
*
* This item allows to store and load the import configuration for a newsletter
* from a feed into a mailing.
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
* Database wrapper for a single newsletter feed configuration item
*
* This item allows to store and load the import configuration for a newsletter
* from a feed into a mailing.
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class PapayaModuleNewsletterFeedConfigurationItem
  extends PapayaDatabaseObjectRecord {

  /**
  * Field definition (properties and array keys the data is mapped to)
  *
  * @var array
  */
  protected $_fields = array(
    'id' => 'mailingfeed_id',
    'group_id' => 'mailinggroup_id',
    'url' => 'mailingfeed_url',
    'minimum' => 'mailingfeed_minimum',
    'maximum' => 'mailingfeed_maximum',
    'period' => 'mailingfeed_period',
    'position' => 'mailingfeed_position',
    'template' => 'mailingfeed_template'
  );

  protected $_tableName = 'newsletter_feeds';
}
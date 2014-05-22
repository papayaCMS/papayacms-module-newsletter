<?php
require_once(dirname(__FILE__).'/../../bootstrap.php');

require_once(dirname(__FILE__).'/../../../src/Feed/Configuration/List.php');
require_once(dirname(__FILE__).'/../../../src/Feed/Configuration/Item.php');

class PapayaModuleNewsletterFeedConfigurationListTest extends PapayaTestCase {

  /**
  * @covers PapayaModuleNewsletterFeedConfigurationList::load
  */
  public function testLoad() {
    $databaseResult = $this->getMock('PapayaDatabaseResult');
    $databaseResult
      ->expects($this->exactly(2))
      ->method('fetchRow')
      ->with($this->equalTo(PapayaDatabaseResult::FETCH_ASSOC))
      ->will(
        $this->onConsecutiveCalls(
          array(
            'mailingfeed_id' => '42',
            'mailinggroup_id' => '21',
            'mailingfeed_url' => 'http://example.tld/feed',
            'mailingfeed_minimum' => '1',
            'mailingfeed_maximum' => '30',
            'mailingfeed_period' => '86400'
          ),
          FALSE
        )
      );
    $databaseResult
      ->expects($this->any())
      ->method('absCount')
      ->will($this->returnValue(42));
    $databaseAccess = $this->getMock(
      'PapayaDatabaseAccess', array('getTableName', 'queryFmt'), array(new stdClass)
    );
    $databaseAccess
      ->expects($this->any())
      ->method('getTableName')
      ->with('newsletter_feeds')
      ->will($this->returnValue('papaya_newsletter_feeds'));
    $databaseAccess
      ->expects($this->once())
      ->method('queryFmt')
      ->with(
        $this->isType('string'),
        $this->equalTo(array('papaya_newsletter_feeds', 42)),
        $this->equalTo(10),
        $this->equalTo(5)
      )
      ->will($this->returnValue($databaseResult));
    $list = new PapayaModuleNewsletterFeedConfigurationList();
    $list->setDatabaseAccess($databaseAccess);
    $this->assertTrue($list->load(42, 10, 5));
    $this->assertAttributeEquals(
      array(
        42 => array(
          'id' => '42',
          'group_id' => '21',
          'url' => 'http://example.tld/feed',
          'minimum' => '1',
          'maximum' => '30',
          'period' => '86400'
        )
      ),
      '_records',
      $list
    );
    $this->assertAttributeEquals(
      42, '_recordCount', $list
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfigurationList::delete
  */
  public function testDelete() {
    $databaseAccess = $this->getMock(
      'PapayaDatabaseAccess', array('getTableName', 'deleteRecord'), array(new stdClass)
    );
    $databaseAccess
      ->expects($this->any())
      ->method('getTableName')
      ->with('newsletter_feeds')
      ->will($this->returnValue('papaya_newsletter_feeds'));
    $databaseAccess
      ->expects($this->once())
      ->method('deleteRecord')
      ->with(
        $this->equalTo('papaya_newsletter_feeds'),
        $this->equalTo(array('mailingfeed_id' => 42))
      )
      ->will($this->returnValue(1));
    $list = new PapayaModuleNewsletterFeedConfigurationList_TestProxy();
    $list->setDatabaseAccess($databaseAccess);
    $list->_records = array(42 => array('id' => '42'));
    $this->assertTrue(
      $list->delete(42)
    );
    $this->assertEquals(
      array(), $list->_records
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfigurationList::delete
  */
  public function testDeleteExpectingFalse() {
    $databaseAccess = $this->getMock(
      'PapayaDatabaseAccess', array('getTableName', 'deleteRecord'), array(new stdClass)
    );
    $databaseAccess
      ->expects($this->any())
      ->method('getTableName')
      ->with('newsletter_feeds')
      ->will($this->returnValue('papaya_newsletter_feeds'));
    $databaseAccess
      ->expects($this->once())
      ->method('deleteRecord')
      ->with(
        $this->equalTo('papaya_newsletter_feeds'),
        $this->equalTo(array('mailingfeed_id' => 42))
      )
      ->will($this->returnValue(FALSE));
    $list = new PapayaModuleNewsletterFeedConfigurationList_TestProxy();
    $list->setDatabaseAccess($databaseAccess);
    $list->_records = array(42 => array('id' => '42'));
    $this->assertFalse(
      $list->delete(42)
    );
    $this->assertEquals(
      array(42 => array('id' => '42')), $list->_records
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfigurationList::move
  * @covers PapayaModuleNewsletterFeedConfigurationList::comparePositions
  */
  public function testMove() {
    $records = array(
      21 => array(
        'id' => '21',
        'group_id' => '21',
        'url' => 'http://example.tld/feed',
        'minimum' => '1',
        'maximum' => '30',
        'period' => '86400',
        'position' => '10000'
      ),
      42 => array(
        'id' => '42',
        'group_id' => '21',
        'url' => 'http://example.tld/feed',
        'minimum' => '1',
        'maximum' => '30',
        'period' => '86400',
        'position' => '20000'
      )
    );
    $databaseAccess = $this->getMock(
      'PapayaDatabaseAccess', array('getTableName', 'updateRecord'), array(new stdClass)
    );
    $databaseAccess
      ->expects($this->any())
      ->method('getTableName')
      ->with('newsletter_feeds')
      ->will($this->returnValue('papaya_newsletter_feeds'));
    $databaseAccess
      ->expects($this->exactly(2))
      ->method('updateRecord')
      ->with(
        $this->equalTo('papaya_newsletter_feeds'),
        $this->logicalOr(
          array('mailingfeed_position' => 1),
          array('mailingfeed_position' => 2)
        ),
        $this->logicalOr(
          array('mailingfeed_id' => 21),
          array('mailingfeed_id' => 42)
        )
      )
      ->will($this->returnValue(TRUE));
    $list = new PapayaModuleNewsletterFeedConfigurationList_TestProxy();
    $list->setDatabaseAccess($databaseAccess);
    $list->_records = $records;
    $this->assertTrue($list->move(42, 21));
    $this->assertEquals(
      array(
        42 => array(
          'id' => '42',
          'group_id' => '21',
          'url' => 'http://example.tld/feed',
          'minimum' => '1',
          'maximum' => '30',
          'period' => '86400',
          'position' => 1
        ),
        21 => array(
          'id' => '21',
          'group_id' => '21',
          'url' => 'http://example.tld/feed',
          'minimum' => '1',
          'maximum' => '30',
          'period' => '86400',
          'position' => 2
        )
      ),
      $list->_records
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfigurationList::comparePositions
  * @dataProvider provideRecordComparsions
  */
  public function testCompareRecordsWithDifferentPositions($expected, $recordOne, $recordTwo) {
    $list = new PapayaModuleNewsletterFeedConfigurationList();
    $this->assertEquals(
      $expected, $list->comparePositions($recordOne, $recordTwo)
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfigurationList::getItem
  */
  public function testGetItem() {
    $record = array(
      'id' => '42',
      'group_id' => '21',
      'url' => 'http://example.tld/feed',
      'minimum' => '1',
      'maximum' => '30',
      'period' => '86400'
    );
    $item = new PapayaModuleNewsletterFeedConfigurationItem();
    $item->assign($record);
    $list = new PapayaModuleNewsletterFeedConfigurationList_TestProxy();
    $list->_records = array(42 => $record);
    $this->assertEquals($item, $list->getItem(42));
  }

  /*******************************
  * Data Provider
  *******************************/

  public static function provideRecordComparsions() {
    return array(
      'position smaller' => array(
        -1, array('position' => 1), array('position' => 2)
      ),
      'position larger' => array(
        1, array('position' => 3), array('position' => 2)
      ),
      'position equal, id smaller' => array(
        -1, array('position' => 1, 'id' => 1), array('position' => 1, 'id' => 2)
      ),
      'position equal, id larger' => array(
        1, array('position' => 1, 'id' => 3), array('position' => 1, 'id' => 2)
      )
    );
  }
}

class PapayaModuleNewsletterFeedConfigurationList_TestProxy extends
  PapayaModuleNewsletterFeedConfigurationList {

  public $_records = array();
}
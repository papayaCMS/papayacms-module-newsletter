<?php
require_once(dirname(__FILE__).'/../bootstrap.php');

PapayaTestCase::defineConstantDefaults('PAPAYA_DB_TBL_MODULEOPTIONS');

require_once(dirname(__FILE__).'/../../src/Feed/Configuration.php');

class PapayaModuleNewsletterFeedConfigurationTest extends PapayaTestCase {

  private function _getConfigurationObjectFixture($owner = NULL) {
    if (empty($owner)) {
      $owner = new stdClass;
    }
    return new PapayaModuleNewsletterFeedConfiguration($owner);
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::__construct
  */
  public function testConstructor() {
    $object = new stdClass();
    $configurationObject = new PapayaModuleNewsletterFeedConfiguration($object);
    $this->assertSame($object, $this->readAttribute($configurationObject, '_owner'));
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::getBaseModuleOptionsObject
  */
  public function testGetBaseModuleOptionsObject() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $this->assertTrue(
      $configurationObject->getBaseModuleOptionsObject() instanceof base_module_options
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::setBaseModuleOptionsObject
  */
  public function testSetBaseModuleOptionsObject() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $object = new stdClass;
    $configurationObject->setBaseModuleOptionsObject($object);
    $this->assertSame(
      $object,
      $this->readAttribute($configurationObject, '_baseModuleOptionsObject')
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::getPapayaTemplateXsltHandler
  */
  public function testGetPapayaTemplateXsltHandler() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $this->assertTrue(
      $configurationObject->getPapayaTemplateXsltHandler() instanceof PapayaTemplateXsltHandler
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::setPapayaTemplateXsltHandler
  */
  public function testSetPapayaTemplateXsltHandler() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $object = new stdClass;
    $configurationObject->setPapayaTemplateXsltHandler($object);
    $this->assertSame(
      $object,
      $this->readAttribute($configurationObject, '_papayaTemplateXsltHandlerObject')
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::getTemplatePath
  */
  public function testGetTemplatePath() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $optionValue = 'test';
    $localPath = 'any/path/';
    $expected = $localPath.$optionValue.'/';
    $baseModuleOptionsObject = $this->getMock('base_module_options');
    $baseModuleOptionsObject
      ->expects($this->once())
      ->method('readOption')
      ->will($this->returnValue($optionValue));
    $configurationObject->setBaseModuleOptionsObject($baseModuleOptionsObject);
    $papayaTemplateXsltHandler = $this->getMock('PapayaTemplateXsltHandler');
    $papayaTemplateXsltHandler
      ->expects($this->once())
      ->method('getLocalPath')
      ->will($this->returnValue($localPath));
    $configurationObject->setPapayaTemplateXsltHandler($papayaTemplateXsltHandler);
    $this->assertSame($expected, $configurationObject->getTemplatePath());
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::prepare
  */
  public function testPrepare() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $newsletterId = '10';
    $configurationObject->parameters(
      new PapayaRequestParameters(
        array(
          'mailinggroup_id' => $newsletterId,
          'feed_id' => '2'
        )
      )
    );
    $feeds = $this->getMock('PapayaModuleNewsletterFeedConfigurationList');
    $feeds
      ->expects($this->once())
      ->method('load')
      ->with($this->equalTo($newsletterId));
    $configurationObject->feeds($feeds);
    $configurationObject->prepare();
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::execute
  */
  public function testExecuteWithFeedDeletion() {
    $owner = $this->getMock('stdClass', array('addMsg'));
    $owner
      ->expects($this->once())
      ->method('addMsg')
      ->with($this->equalTo(0), $this->equalTo('Feed deleted.'));
    $configurationObject = $this->_getConfigurationObjectFixture($owner);
    $feedId = '10';
    $configurationObject->parameters(
      new PapayaRequestParameters(
        array(
          'confirm_delete' => '1',
          'feed_delete' => $feedId
        )
      )
    );
    $feeds = $this->getMock('PapayaModuleNewsletterFeedConfigurationList');
    $feeds
      ->expects($this->once())
      ->method('delete')
      ->with($this->equalTo($feedId))
      ->will($this->returnValue(TRUE));
    $configurationObject->feeds($feeds);
    $configurationObject->execute();
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::execute
  */
  public function testExecuteWithEditAndMove() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $configurationObject->parameters(new PapayaRequestParameters(array()));
    $configurationObject->execute();
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::feeds
  */
  public function testFeedsWithParameter() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $feeds = $this->getMock('PapayaModuleNewsletterFeedConfigurationList');
    $this->assertSame($feeds, $configurationObject->feeds($feeds));
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::feeds
  */
  public function testFeedsWithoutParameter() {
    $configurationObject = $this->_getConfigurationObjectFixture();
    $feeds = $configurationObject->feeds();
    $this->assertTrue($feeds instanceof PapayaModuleNewsletterFeedConfigurationList);
  }

  /**
  * @covers PapayaModuleNewsletterFeedConfiguration::_executeMove
  */
  public function testExecuteMove() {
    $moveTo = '2';
    $feedId = '12';
    $configurationObject = $this->getProxy(
      'PapayaModuleNewsletterFeedConfiguration', array('_executeMove'), array(new stdClass)
    );
    $configurationObject->parameters(
      new PapayaRequestParameters(
        array('feed_move_to' => $moveTo, 'feed_id' => $feedId)
      )
    );
    $feeds = $this->getMock('PapayaModuleNewsletterFeedConfigurationList');
    $feeds
      ->expects($this->once())
      ->method('move')
      ->with($this->equalTo($feedId), $this->equalTo($moveTo));
    $configurationObject->feeds($feeds);
    $configurationObject->_executeMove();
  }

  /*******************************
  * Data Provider
  *******************************/

}
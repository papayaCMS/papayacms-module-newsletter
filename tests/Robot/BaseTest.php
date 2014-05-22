<?php
require_once(dirname(__FILE__).'/../bootstrap.php');

// predefine constants
PapayaTestCase::defineConstantDefaults(
  'DB_FETCHMODE_ASSOC',
  'PAPAYA_DB_TBL_MODULES',
  'PAPAYA_DB_TBL_LNG',
  'PAPAYA_URL_EXTENSION',
  'PAPAYA_DB_TBL_TOPICS_PUBLIC',
  'PAPAYA_DB_TBL_TOPICS_PUBLIC_TRANS',
  'PAPAYA_DB_TBL_TOPICS_VERSIONS',
  'PAPAYA_DB_TBL_TOPICS_VERSIONS_TRANS',
  'PAPAYA_DB_TABLEPREFIX'
);

require_once(dirname(__FILE__).'/../../src/papaya_newsletter.php');
require_once(dirname(__FILE__).'/../../src/Robot.php');
require_once(dirname(__FILE__).'/../../src/Robot/Base.php');


class PapayaModuleNewsletterRobotBaseTest extends PapayaTestCase {

  /**
  * Load PageBase object fixture
  * @return NewsletterRobotBase
  */
  private function _getPageBaseObjectFixture($owner = NULL) {
    $options = $this->_getOptionsFixture();
    $configuration = $this->getMockConfigurationObject($options);
    $baseObject = new NewsletterRobotBase($owner);
    $baseObject->setConfiguration($configuration);
    return $baseObject;
  }

  /**
  * Returns an option array as fixture.
  * @return array
  */
  private function _getOptionsFixture() {
    return array('PAPAYA_DB_TABLEPREFIX' => 'papaya');
  }

  /***************************************************************************/
  /** Helper                                                                 */
  /***************************************************************************/

  /**
  * @covers NewsletterRobotBase::__construct
  */
  public function testConstructor() {
    $object = new stdClass();
    $baseObject = new NewsletterRobotBase($object);
    $this->assertSame($object, $this->readAttribute($baseObject, 'owner'));
  }

  /**
  * @covers NewsletterRobotBase::setConfiguration
  */
  public function testSetConfiguration() {
    $object = new stdClass();
    $baseObject = new NewsletterRobotBase($object);
    $options = $this->_getOptionsFixture();
    $configuration = $this->getMockConfigurationObject($options);
    $baseObject->setConfiguration($configuration);
    $this->assertAttributeSame($configuration, '_configuration', $baseObject);
  }

  /**
  * @covers NewsletterRobotBase::getConfiguration
  */
  public function testGetConfiguration() {
    $object = new stdClass();
    $baseObject = new NewsletterRobotBase($object);
    $baseObject->setConfiguration(TRUE);
    $this->assertTrue($baseObject->getConfiguration());
  }

  /**
  * @covers NewsletterRobotBase::setPageData
  */
  public function testSetPageData() {
    $baseObject = $this->_getPageBaseObjectFixture();
    $data = array('test' => 'test');
    $baseObject->setPageData($data);
    $this->assertAttributeSame($data, 'data', $baseObject);
  }

  /**
  * @covers NewsletterRobotBase::getNewsletterObject
  */
  public function testGetNewsletterObject() {
    $baseObject = $this->_getPageBaseObjectFixture();
    $newsletterObject = $baseObject->getNewsletterObject();
    $this->assertAttributeSame($newsletterObject, 'newsletterObject', $baseObject);
    $this->assertTrue($newsletterObject instanceof papaya_newsletter);
  }

  /**
  * @covers NewsletterRobotBase::setNewsletterObject
  */
  public function testSetNewsletterObject() {
    $baseObject = $this->_getPageBaseObjectFixture();
    $baseObject->setNewsletterObject(TRUE);
    $this->assertAttributeSame(TRUE, 'newsletterObject', $baseObject);
  }

  /***************************************************************************/
  /** Methods                                                                */
  /***************************************************************************/

  /**
  * @covers NewsletterRobotBase::run
  * @dataProvider providerRun
  */
  public function testRun($expected, $oneMailingGroup, $newMailingId, $outputId, $parseResult,
      $addToQueueResult) {
    $owner = $this->getMock('stdClass', array('logMsg', 'cronOutput'));
    $owner
      ->expects($this->any())
      ->method('logMsg');
    $owner
      ->expects($this->any())
      ->method('cronOutput');
    $baseObject = $this->_getPageBaseObjectFixture($owner);
    $baseObject->papaya($this->mockPapaya()->application());
    $baseObject->setPageData(
      array(
        'mailinggroup_id' => '2',
        'newsletter_list_id' => '1',
        'save_to_queue' => '1'
      )
    );
    $newsletterObject = $this->getMock('mockPapayaNewsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('loadOneMailingGroup')
      ->with($this->equalTo('2'));
    $newsletterObject
      ->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('oneMailingGroup'))
      ->will($this->returnValue($oneMailingGroup));
    $newsletterObject
      ->expects($this->any())
      ->method('__isset')
      ->with($this->equalTo('oneMailingGroup'))
      ->will($this->returnValue(TRUE));
    $newsletterObject
      ->expects($this->any())
      ->method('addMailing')
      ->will($this->returnValue($newMailingId));
    $newsletterObject
      ->expects($this->any())
      ->method('addMailingOutput')
      ->will($this->returnValue($outputId));
    $newsletterObject
      ->expects($this->any())
      ->method('loadOneMailingOutput')
      ->withAnyParameters();
    $newsletterObject
      ->expects($this->any())
      ->method('parseMailingOutput')
      ->will($this->returnValue($parseResult));
    $newsletterObject
      ->expects($this->any())
      ->method('addToQueue')
      ->will($this->returnValue($addToQueueResult));
    $baseObject->setNewsletterObject($newsletterObject);
    $this->assertSame($expected, $baseObject->run());
  }

  /**
  * @covers NewsletterRobotBase::getMailingGroups
  */
  public function testGetMailingGroups() {
    $baseObject = $this->_getPageBaseObjectFixture();
    $expected = array('any list');
    $newsletterObject = $this->getMock('mockPapayaNewsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('loadMailingGroups');
    $newsletterObject->mailingGroups = $expected;
    $baseObject->setNewsletterObject($newsletterObject);
    $this->assertSame($expected, $baseObject->getMailingGroups());
  }

  /**
  * @covers NewsletterRobotBase::getNewsletterLists
  */
  public function testGetNewsletterLists() {
    $baseObject = $this->_getPageBaseObjectFixture();
    $expected = array('any list');
    $newsletterObject = $this->getMock('mockPapayaNewsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('loadNewsletterLists');
    $newsletterObject->newsletterLists = $expected;
    $baseObject->setNewsletterObject($newsletterObject);
    $this->assertSame($expected, $baseObject->getNewsletterLists());
  }

  /**
  * @covers NewsletterRobotBase::addNewMailing
  * @dataProvider providerAddNewMailing
  */
  public function testAddNewMailing($expected, $data) {
    $baseObject = $this->_getPageBaseObjectFixture();
    $baseObject->papaya($this->mockPapaya()->application());
    $baseObject->setPageData($data);
    $newsletterObject = $this->getMock('mockPapayaNewsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('addMailing')
      ->will($this->returnValue($expected));
    $baseObject->setNewsletterObject($newsletterObject);
    $this->assertSame($expected, $baseObject->addNewMailing());
  }

  /**
  * @covers NewsletterRobotBase::addNewMailingOutput
  */
  public function testAddNewMailingOutput() {
    $baseObject = $this->_getPageBaseObjectFixture();
    $oneMailingGroup = array(
      'mailinggroup_default_subject' => 'Test',
      'mailinggroup_default_sender' => 'Test',
      'mailinggroup_default_senderemail' => 'test@domain.tld',
      'mailinggroup_default_textview' => '1',
      'mailinggroup_default_htmlview' => '1'
    );
    $newsletterObject = $this->getMock('mockPapayaNewsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('addMailingOutput')
      ->will($this->returnValue(1));
    $newsletterObject
      ->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('oneMailingGroup'))
      ->will($this->returnValue($oneMailingGroup));
    $baseObject->setNewsletterObject($newsletterObject);
    $this->assertSame(1, $baseObject->addNewMailingOutput(1));
  }

  /**
  * @covers NewsletterRobotBase::parseMailingOutputs
  * @dataProvider providerParseMailingOutputs
  */
  public function testParseMailingOutputs($expected, $resultText, $resultHtml) {
    $baseObject = $this->_getPageBaseObjectFixture();
    $newsletterObject = $this->getMock('mockPapayaNewsletter');
    $newsletterObject
      ->expects($this->at(0))
      ->method('parseMailingOutput')
      ->with($this->equalTo(1))
      ->will($this->returnValue($resultText));
    $newsletterObject
      ->expects($this->at(1))
      ->method('parseMailingOutput')
      ->with($this->equalTo(1))
      ->will($this->returnValue($resultHtml));
    $baseObject->setNewsletterObject($newsletterObject);
    $this->assertSame($expected, $baseObject->parseMailingOutputs(1));
  }

  /***************************************************************************/
  /** DataProvider                                                           */
  /***************************************************************************/

  public static function providerParseMailingOutputs() {
    return array(
      'text & html parsing successful' => array(TRUE, TRUE, TRUE),
      'text parsing failed & html parsing successful' => array(TRUE, FALSE, TRUE),
      'text parsing successful & html failed' => array(TRUE, TRUE, FALSE),
      'text & html parsing failed' => array(FALSE, FALSE, FALSE)
    );
  }

  public static function providerAddNewMailing() {
    return array(
      'with urls' => array(
        TRUE,
        array(
          'mailinggroup_id' => '2',
          'mailing_url' => 'http://www.papaya-cms.com',
          'unsubscribe_url' => 'http://www.papaya-cms.com'
        )
      ),
      'without urls' => array(
        TRUE,
        array('mailinggroup_id' => '2')
      )
    );
  }

  public static function providerRun() {
    $oneMailingGroup = array(
      'mailinggroup_default_subject' => 'Test',
      'mailinggroup_default_sender' => 'Test',
      'mailinggroup_default_senderemail' => 'test@domain.tld',
      'mailinggroup_default_textview' => '1',
      'mailinggroup_default_htmlview' => '1'
    );
    $newMailingId = 1;
    $outputId = 11;
    return array(
      'success' => array(0, $oneMailingGroup, $newMailingId, $outputId, TRUE, TRUE),
      'error while loading mailing group' => array(
        'Error: could not load mailing group (using papaya_newsletter::loadOneMailingGroup, '.
          'mailing group id: 2)',
        array(),
        $newMailingId,
        $outputId,
        FALSE,
        FALSE
      ),
      'error while adding new mailing' => array(
        'Error: could not add mailing (using papaya_newsletter::addMailing)',
        $oneMailingGroup,
        FALSE,
        $outputId,
        FALSE,
        FALSE
      ),
      'error while adding new mailing output' => array(
        'Error: could not add mailing output (using papaya_newsletter::addMailingOutput)',
        $oneMailingGroup,
        $newMailingId,
        FALSE,
        FALSE,
        FALSE
      ),
      'error while parsing mailing output' => array(
        'Error: could not parse mailing output (using papaya_newsletter::parseMailingOutput)',
        $oneMailingGroup,
        $newMailingId,
        $outputId,
        FALSE,
        FALSE
      ),
      'error while adding to queue' => array(
        'Error: could not add mailing output to queue(using papaya_newsletter::addToQueue, data: '.
          '1 (newsletter id), 11 (output id))',
        $oneMailingGroup,
        $newMailingId,
        $outputId,
        TRUE,
        FALSE
      )
    );
  }
}

class mockPapayaNewsletter extends papaya_newsletter {
  public function __get($propertyName) {
  }
  public function __isset($propertyName) {
  }
}
<?php
require_once(dirname(__FILE__).'/bootstrap.php');

require_once(dirname(__FILE__).'/../src/Robot.php');
require_once(dirname(__FILE__).'/../src/Robot/Base.php');


class PapayaModuleNewsletterRobotTest extends PapayaTestCase {

  /**
  * Gets a fixture for the application class.
  *
  * @return application class as mock object
  */
  private function _getApplicationObjectFixture($parameters = array()) {
    $request = $this->getMockRequestObject(array('nws' => $parameters));
    return $this->getMockApplicationObject(array('Request' => $request));
  }

  /**
  * Gets a fixture for the tests.
  *
  * @return NewsletterRobot
  */
  private function _getCronjobObjectFixture() {
    $cronjobObject = new NewsletterRobotProxy();
    $cronjobObject->setApplication($this->_getApplicationObjectFixture());
    return $cronjobObject;
  }

  /**
  * Gets a fixture for the tests.
  *
  * @param object $ownerObject
  * @return NewsletterRobotBase $baseObject
  */
  private function _getBaseObjectFixture($ownerObject) {
    $baseObject = $this->getMock('NewsletterRobotBase', array(), array($ownerObject));
    return $baseObject;
  }

  /***************************************************************************/
  /** Instance / Helper                                                      */
  /***************************************************************************/

  /**
  * @covers NewsletterRobot::setBaseObject
  */
  public function testSetBaseObject() {
    $cronjobObject = $this->_getCronjobObjectFixture();
    $object = new stdClass();
    $cronjobObject->setBaseObject($object);
    $this->assertAttributeSame($object, 'baseObject', $cronjobObject);
  }

  /**
  * @covers NewsletterRobot::getBaseObject
  */
  public function testGetBaseObject() {
    $cronjobObject = $this->_getCronjobObjectFixture();
    $baseObject = $cronjobObject->getBaseObject();
    $this->assertTrue($baseObject instanceof NewsletterRobotBase);
  }

  /***************************************************************************/
  /** Methods                                                                */
  /***************************************************************************/

  /**
  * @covers NewsletterRobot::checkExecParams
  * @dataProvider providerCheckExecParams
  */
  public function testCheckExecParams($expected, $data) {
    $cronjobObject = $this->_getCronjobObjectFixture();
    $cronjobObject->data = $data;
    $this->assertSame($expected, $cronjobObject->checkExecParams());
  }

  /**
  * @covers NewsletterRobot::execute
  */
  public function testExecute() {
    $cronjobObject = $this->_getCronjobObjectFixture();
    $result = 0;
    $baseObject = $this->_getBaseObjectFixture($cronjobObject);
    $baseObject
      ->expects($this->once())
      ->method('run')
      ->will($this->returnValue($result));
    $cronjobObject->setBaseObject($baseObject);
    $this->assertSame($result, $cronjobObject->execute());
  }

  /**
  * @covers NewsletterRobot::callbackGetNewsletters
  * @dataProvider providerCallbackGetNewsletters
  */
  public function testCallbackGetNewsletters($expected, $newsletterLists) {
    $cronjobObject = $this->_getCronjobObjectFixture();
    $baseObject = $this->_getBaseObjectFixture($cronjobObject);
    $baseObject
      ->expects($this->once())
      ->method('getMailingGroups')
      ->will($this->returnValue($newsletterLists));
    $cronjobObject->setBaseObject($baseObject);
    $this->assertSame($expected, $cronjobObject->callbackGetNewsletters('lists', NULL, '1'));
  }

  /**
  * @covers NewsletterRobot::callbackGetSubscriberLists
  * @dataProvider providerCallbackGetSubscriberLists
  */
  public function testCallbackGetSubscriberLists($expected, $subscriberLists) {
    $cronjobObject = $this->_getCronjobObjectFixture();
    $baseObject = $this->_getBaseObjectFixture($cronjobObject);
    $baseObject
      ->expects($this->once())
      ->method('getNewsletterLists')
      ->will($this->returnValue($subscriberLists));
    $cronjobObject->setBaseObject($baseObject);
    $this->assertSame($expected, $cronjobObject->callbackGetSubscriberLists('lists', NULL, '1'));
  }

  /***************************************************************************/
  /** DataProvider                                                           */
  /***************************************************************************/

  public static function providerCheckExecParams() {
    return array(
      'true' => array(
        TRUE,
        array('mailinggroup_id' => '2', 'newsletter_list_id' => '1', 'save_to_queue' => '1')
      ),
      'missing mailing group id' => array(
        FALSE,
        array('newsletter_list_id' => '1', 'save_to_queue' => '1')
      ),
      'missing newsletter list id' => array(
        FALSE,
        array('mailinggroup_id' => '2', 'save_to_queue' => '1')
      ),
      'missing save to queue flag' => array(
        FALSE,
        array('mailinggroup_id' => '2', 'newsletter_list_id' => '1')
      )
    );
  }

  public static function providerCallbackGetNewsletters() {
    return array(
      'no newsletter found' => array(
        '<select name="nws[lists]" class="dialogSelect dialogScale">'.LF.
        '<option value="" disabled="disabled">No newsletters available</option>'.LF.
        '</select>'.LF,
        array()
      ),
      'newsletter found' => array(
        '<select name="nws[lists]" class="dialogSelect dialogScale">'.LF.
        '<option value="0" >None</option>'.LF.
        '<option value="1"  selected="selected">Liste 1</option>'.LF.
        '<option value="2" >Liste 2</option>'.LF.
        '</select>'.LF,
        array(
          1 => array(
            'mailinggroup_id' => '1',
            'mailinggroup_title' => 'Liste 1',
          ),
          2 => array(
            'mailinggroup_id' => '2',
            'mailinggroup_title' => 'Liste 2',
          )
        )
      )
    );
  }

  public static function providerCallbackGetSubscriberLists() {
    return array(
      'no subscriber list found' => array(
        '<select name="nws[lists]" class="dialogSelect dialogScale">'.LF.
        '<option value="" disabled="disabled">No subscriber lists available</option>'.LF.
        '</select>'.LF,
        array()
      ),
      'subscriber list found' => array(
        '<select name="nws[lists]" class="dialogSelect dialogScale">'.LF.
        '<option value="0" >None</option>'.LF.
        '<option value="1"  selected="selected">Liste 1</option>'.LF.
        '<option value="2" >Liste 2</option>'.LF.
        '</select>'.LF,
        array(
          1 => array(
            'newsletter_list_id' => '1',
            'newsletter_list_name' => 'Liste 1'
          ),
          2 => array(
            'newsletter_list_id' => '2',
            'newsletter_list_name' => 'Liste 2'
          )
        )
      )
    );
  }
}

/**
* Proxy class to be able to test protected methods and override constructor.
*/
class NewsletterRobotProxy extends NewsletterRobot {
  public function __construct() {
  }
}
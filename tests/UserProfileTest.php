<?php
require_once(dirname(__FILE__).'/bootstrap.php');

require_once(dirname(__FILE__).'/../src/UserProfile.php');
require_once(dirname(__FILE__).'/../src/UserProfile/Base.php');


class PapayaModuleNewsletterUserProfileTest extends PapayaTestCase {

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
  * @return NewsletterUserProfile $pageObject
  */
  private function _getPageObjectFixture() {
    $pageObject = new NewsletterUserProfileProxy();
    $pageObject->setApplication($this->_getApplicationObjectFixture());
    return $pageObject;
  }

  /**
  * Gets a fixture for the tests.
  *
  * @param object $ownerObject
  * @return NewsletterUserProfileBase $pageBaseObject
  */
  private function _getPageBaseObjectFixture($ownerObject) {
    $pageBaseObject = $this->getMock('NewsletterUserProfileBase', array(), array($ownerObject));
    return $pageBaseObject;
  }

  /***************************************************************************/
  /** Instance / Helper                                                      */
  /***************************************************************************/

  /**
  * @covers NewsletterUserProfile::setPageBaseObject
  */
  public function testSetPageBaseObject() {
    $pageObject = $this->_getPageObjectFixture();
    $object = new stdClass();
    $pageObject->setPageBaseObject($object);
    $this->assertAttributeSame($object, 'pageBaseObject', $pageObject);
  }

  /**
  * @covers NewsletterUserProfile::getPageBaseObject
  */
  public function testGetPageBaseObject() {
    $pageObject = $this->_getPageObjectFixture();
    $baseObject = $pageObject->getPageBaseObject();
    $this->assertTrue($baseObject instanceof NewsletterUserProfileBase);
  }

  /***************************************************************************/
  /** Methods                                                                */
  /***************************************************************************/

  /**
  * @covers NewsletterUserProfile::getParsedData
  */
  public function testGetParsedData() {
    $pageObject = $this->_getPageObjectFixture();
    $result = '<xml />';
    $pageObject->params = array();
    $pageObject->data = array();

    $pageBaseObject = $this->_getPageBaseObjectFixture($pageObject);
    $pageBaseObject
      ->expects($this->once())
      ->method('getXml')
      ->will($this->returnValue($result));
    $pageObject->setPageBaseObject($pageBaseObject);
    $this->assertSame($result, $pageObject->getParsedData());
  }

  /***************************************************************************/
  /** DataProvider                                                           */
  /***************************************************************************/

}

/**
* Proxy class to be able to test protected methods and override constructor.
*/
class NewsletterUserProfileProxy extends NewsletterUserProfile {
  public function __construct() {
  }
}
?>
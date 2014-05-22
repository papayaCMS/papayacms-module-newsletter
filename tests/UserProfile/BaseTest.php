<?php
require_once(dirname(__FILE__).'/../bootstrap.php');

require_once(dirname(__FILE__).'/../../src/UserProfile.php');
require_once(dirname(__FILE__).'/../../src/UserProfile/Base.php');

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

class PapayaModuleNewsletterUserProfileBaseTest extends PapayaTestCase {

  /**
  * Load PageBase object fixture
  * @return NewsletterUserProfileBase
  */
  private function _getPageBaseObjectFixture() {
    $options = $this->_getOptionsFixture();
    $configuration = $this->getMockConfigurationObject($options);
    $pageBaseObject = new NewsletterUserProfileBase(new newsletterUserProfileOwnerProxy());
    $pageBaseObject->setConfiguration($configuration);
    return $pageBaseObject;
  }

  /**
  * Load PageBase object fixture with mock application.
  * @param boolean $surferIsValid
  * @return NewsletterUserProfileBase
  */
  private function _getPageBaseObjectFixtureWithMockApplication($surferIsValid = FALSE) {
    $options = $this->_getOptionsFixture();
    $configuration = $this->getMockConfigurationObject($options);
    $pageObject = $this->getMock('papaya_page');
    $pageObject
      ->expects($this->any())
      ->method('doRedirect');

    $objects = array(
      'Surfer' => new newsletterUserProfileSurferProxy($surferIsValid, 'm')
    );
    $owner = new newsletterUserProfileOwnerProxy($this->getMockApplicationObject($objects));
    $pageBaseObject = new NewsletterUserProfileBase($owner);
    $pageBaseObject->setConfiguration($configuration);
    $pageBaseObject->setPageObject($pageObject);
    return $pageBaseObject;
  }

  /**
  * Returns an option array as fixture.
  * @return array
  */
  private function _getOptionsFixture() {
    return array('PAPAYA_DB_TABLEPREFIX' => 'papaya');
  }

  /**
  * Returns a mock of papaya_page
  * @return papaya_page
  */
  private function _getPageMock() {
    return $this->getMock('papaya_page', array('doRedirect'));
  }

  /***************************************************************************/
  /** Helper                                                                 */
  /***************************************************************************/

  /**
  * @covers NewsletterUserProfileBase::__construct
  */
  public function testConstructor() {
    $object = new stdClass();
    $pageBaseObject = new NewsletterUserProfileBase($object);
    $this->assertSame($object, $this->readAttribute($pageBaseObject, 'owner'));
  }

  /**
  * @covers NewsletterUserProfileBase::setConfiguration
  */
  public function testSetConfiguration() {
    $object = new stdClass();
    $pageBaseObject = new NewsletterUserProfileBase($object);
    $options = $this->_getOptionsFixture();
    $configuration = $this->getMockConfigurationObject($options);
    $pageBaseObject->setConfiguration($configuration);
    $this->assertAttributeSame($configuration, '_configuration', $pageBaseObject);
  }

  /**
  * @covers NewsletterUserProfileBase::getConfiguration
  */
  public function testGetConfiguration() {
    $object = new stdClass();
    $pageBaseObject = new NewsletterUserProfileBase($object);
    $pageBaseObject->setConfiguration(TRUE);
    $this->assertTrue($pageBaseObject->getConfiguration());
  }

  /**
  * @covers NewsletterUserProfileBase::setPageParams
  */
  public function testSetPageParams() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $params = array('test' => 'test');
    $pageBaseObject->setPageParams($params);
    $this->assertAttributeSame($params, 'params', $pageBaseObject);
  }

  /**
  * @covers NewsletterUserProfileBase::setPageParamName
  */
  public function testSetPageParamName() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $paramName = 'test';
    $pageBaseObject->setPageParamName($paramName);
    $this->assertAttributeSame($paramName, 'paramName', $pageBaseObject);
  }

  /**
  * @covers NewsletterUserProfileBase::setPageData
  */
  public function testSetPageData() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $data = array('test' => 'test');
    $pageBaseObject->setPageData($data);
    $this->assertAttributeSame($data, 'data', $pageBaseObject);
  }

  /**
  * @covers NewsletterUserProfileBase::setPageObject
  */
  public function testSetPageObject() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $page = $this->_getPageMock();
    $this->assertTrue($pageBaseObject->setPageObject($page));
    $this->assertAttributeEquals($page, '_page', $pageBaseObject);
  }

  /**
  * @covers NewsletterUserProfileBase::getPageObject
  */
  public function testGetPageObject() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $page = $this->_getPageMock();
    $this->assertTrue($pageBaseObject->setPageObject($page));
    $this->assertSame($page, $pageBaseObject->getPageObject());
  }

  /**
  * @covers NewsletterUserProfileBase::getPageObject
  */
  public function testGetPageObjectFromGlobal() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $page = $this->_getPageMock();
    $GLOBALS['PAPAYA_PAGE'] = $page;
    $this->assertSame($page, $pageBaseObject->getPageObject());
  }

  /**
  * @covers NewsletterUserProfileBase::getPageObject
  */
  public function testGetPageObjectNew() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $this->assertInstanceOf('papaya_page', $pageBaseObject->getPageObject());
  }

  /**
  * @covers NewsletterUserProfileBase::getNewsletterObject
  */
  public function testGetNewsletterObject() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $newsletterObject = $pageBaseObject->getNewsletterObject();
    $this->assertAttributeSame($newsletterObject, 'newsletterObject', $pageBaseObject);
    $this->assertTrue($newsletterObject instanceof base_newsletter);
  }

  /**
  * @covers NewsletterUserProfileBase::setNewsletterObject
  */
  public function testSetNewsletterObject() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setNewsletterObject(TRUE);
    $this->assertAttributeSame(TRUE, 'newsletterObject', $pageBaseObject);
  }

  /***************************************************************************/
  /** Methods                                                                */
  /***************************************************************************/

  /**
  * @covers NewsletterUserProfileBase::addMessage
  * @dataProvider providerAddMessage
  */
  public function testAddMessage($expected, $type, $text) {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->addMessage($type, $text);
    $this->assertAttributeSame($expected, '_messages', $pageBaseObject);
  }

  /**
  * @covers NewsletterUserProfileBase::getMessages
  */
  public function testGetMessages() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $expected = '<messages>'.LF;
    $expected .= '<message type="error">error message text</message>'.LF;
    $expected .= '</messages>'.LF;
    $pageBaseObject->addMessage('error', 'error message text');
    $this->assertSame($expected, $pageBaseObject->getMessages());
  }

  /**
  * @covers NewsletterUserProfileBase::getSubscriberDataFromSurfer
  * @dataProvider providerGetSubscriberDataFromSurfer
  */
  public function testGetSubscriberDataFromSurfer($expected, $surfer) {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $this->assertSame($expected, $pageBaseObject->getSubscriberDataFromSurfer($surfer));
  }

  /**
  * @covers NewsletterUserProfileBase::sendEmailReport
  */
  public function testSendEmailReport() {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageData(
      array(
        'senderEMail' => 'test@domain.tld',
        'senderName' => 'Tester',
        'mailTextNewSubscriptions' => 'new subscriptions',
        'mailTextReactivatedSubscriptions' => 'reactivated subscriptions',
        'mailTextRemovedSubscriptions' => 'removed subscriptions',
        'mailTextNoSubscriptions' => 'no subscriptions',
        'salutationFemale' => 'Mrs.',
        'salutationMale' => 'Mr.',
        'mailSubject' => 'Subject',
        'mailText' => 'Text'
      )
    );
    $receiver = array(
      'subscriber_email' => 'test@domain.tld',
      'subscriber_firstname' => 'Eva',
      'subscriber_lastname' => 'Test',
      'subscriber_salutation' => 1,
    );
    $lists = array(
      1 => array('newsletter_list_name' => 'Liste 1', '__SUBSCRIBED' => FALSE),
      2 => array('newsletter_list_name' => 'Liste 2', '__SUBSCRIBED' => FALSE),
      3 => array('newsletter_list_name' => 'Liste 3', '__SUBSCRIBED' => TRUE)
    );
    $newSubscriptions = array(1);
    $reactivatedSubscriptions = array(2);
    $removedSubscriptions = array(3);
    $this->assertTrue(
      $pageBaseObject->sendEmailReport(
        $receiver, $lists, $newSubscriptions, $reactivatedSubscriptions, $removedSubscriptions
      )
    );
  }

  /**
  * @covers NewsletterUserProfileBase::sendEmailReport
  */
  public function testSendEmailExpectingFalse() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $this->assertFalse($pageBaseObject->sendEmailReport(array(), array()));
  }

  /**
  * @covers NewsletterUserProfileBase::sendEmailReport
  */
  public function testSendEmailReportWithMaleReceiverAndNoSubscriptions() {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageData(
      array(
        'senderEMail' => 'test@domain.tld',
        'senderName' => 'Tester',
        'mailTextNewSubscriptions' => 'new subscriptions',
        'mailTextReactivatedSubscriptions' => 'reactivated subscriptions',
        'mailTextRemovedSubscriptions' => 'removed subscriptions',
        'mailTextNoSubscriptions' => 'no subscriptions',
        'salutationFemale' => 'Mrs.',
        'salutationMale' => 'Mr.',
        'mailSubject' => 'Subject',
        'mailText' => 'Text'
      )
    );
    $receiver = array(
      'subscriber_email' => 'test@domain.tld',
      'subscriber_firstname' => 'Michael',
      'subscriber_lastname' => 'Test',
      'subscriber_salutation' => 0,
    );
    $lists = array(
      1 => array('newsletter_list_name' => 'Liste 1', '__SUBSCRIBED' => FALSE),
      2 => array('newsletter_list_name' => 'Liste 2', '__SUBSCRIBED' => FALSE),
      3 => array('newsletter_list_name' => 'Liste 3', '__SUBSCRIBED' => FALSE)
    );
    $newSubscriptions = array();
    $reactivatedSubscriptions = array();
    $removedSubscriptions = array(1, 2, 3);
    $this->assertTrue(
      $pageBaseObject->sendEmailReport(
        $receiver, $lists, $newSubscriptions, $reactivatedSubscriptions, $removedSubscriptions
      )
    );
  }

  /**
  * @covers NewsletterUserProfileBase::getNewsletterNameList
  */
  public function testGetNewsletterNameList() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $expected = LF.'- Liste 1'.LF.LF;
    $subscriptions = array(1);
    $lists = array(
      1 => array('newsletter_list_name' => 'Liste 1'),
    );
    $this->assertSame($expected, $pageBaseObject->getNewsletterNameList($subscriptions, $lists));
  }

  /**
  * @covers NewsletterUserProfileBase::saveNewSubscriberWithSubscriptions
  * @dataProvider providerNewSubscriberWithSubscriptions
  */
  public function testSaveNewSubscriberWithSubscriptions($resultAddSubscription) {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageParams(array('newsletter_list_id' => array('1', '2')));
    $pageBaseObject->setPageData(
      array(
        'senderEMail' => 'test@domain.tld',
        'senderName' => 'Tester',
        'mailTextNewSubscriptions' => 'new subscriptions',
        'salutationFemale' => 'Mrs.',
        'salutationMale' => 'Mr.',
        'mailSubject' => 'Subject',
        'mailText' => 'Text',
        'messageConfigurationSaved' => 'saved',
        'messageConfigurationNotSaved' => 'Not saved',
        'messageErrorSendingMail' => 'error'
      )
    );
    $surfer = new newsletterUserProfileSurferProxy(TRUE, 'm');
    $subscriber = array(
      'subscriber_email' => 'test@domain.tld',
      'subscriber_firstname' => 'Michael',
      'subscriber_lastname' => 'Test',
      'subscriber_salutation' => 0,
    );
    $lists = array(
      '1' => array('newsletter_list_name' => 'Liste1'),
      '2' => array('newsletter_list_name' => 'Liste2')
    );
    $newsletterObject = $this->getMock('base_newsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('addSubscriber')
      ->with($this->equalTo($subscriber))
      ->will($this->returnValue(1));
    $newsletterObject
      ->expects($this->once())
      ->method('addSubscription')
      ->with($this->equalTo(1), $this->equalTo(array('1', '2')), $this->equalTo(2))
      ->will($this->returnValue($resultAddSubscription));
    $result = $pageBaseObject->saveNewSubscriberWithSubscriptions(
      $surfer, $newsletterObject, $lists
    );
    $this->assertEquals(1, $result);
  }

  /**
  * @covers NewsletterUserProfileBase::saveNewSubscriberWithSubscriptions
  */
  public function testSaveNewSubscriberWithSubscriptionsExpectingFalseWhileSendingEmal() {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageParams(array('newsletter_list_id' => array('1', '2')));
    $pageBaseObject->setPageData(
      array(
        'messageConfigurationSaved' => 'saved',
        'messageErrorSendingMail' => 'error'
      )
    );
    $surfer = new newsletterUserProfileSurferProxy(TRUE, 'm');
    $subscriber = array(
      'subscriber_email' => 'test@domain.tld',
      'subscriber_firstname' => 'Michael',
      'subscriber_lastname' => 'Test',
      'subscriber_salutation' => 0,
    );
    $lists = array(
      '1' => array('__SUBSCRIBED' => FALSE),
      '2' => array('__SUBSCRIBED' => FALSE)
    );
    $newsletterObject = $this->getMock('base_newsletter');
    $newsletterObject
      ->expects($this->once())
      ->method('addSubscriber')
      ->with($this->equalTo($subscriber))
      ->will($this->returnValue(1));
    $newsletterObject
      ->expects($this->once())
      ->method('addSubscription')
      ->with($this->equalTo(1), $this->equalTo(array('1', '2')), $this->equalTo(2))
      ->will($this->returnValue(1));
    $pageBaseObject->saveNewSubscriberWithSubscriptions($surfer, $newsletterObject, $lists);
  }

  /**
  * @covers NewsletterUserProfileBase::saveExistingSubscriberSubscriptions
  */
  public function testSaveExistingSubscriberSubscriptions() {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageParams(array('newsletter_list_id' => array('1', '2')));
    $pageBaseObject->setPageData(
      array(
        'senderEMail' => 'test@domain.tld',
        'senderName' => 'Tester',
        'mailTextNewSubscriptions' => 'new subscriptions',
        'mailTextReactivatedSubscriptions' => 'reactivated subscriptions',
        'mailTextRemovedSubscriptions' => 'removed subscriptions',
        'mailTextNoSubscriptions' => 'no subscriptions',
        'salutationFemale' => 'Mrs.',
        'salutationMale' => 'Mr.',
        'mailSubject' => 'Subject',
        'mailText' => 'Text',
        'messageConfigurationSaved' => 'saved',
        'messageErrorSendingMail' => 'error'
      )
    );
    $existingSubscriptions = array('1' => 4, '3' => 2,);
    $surfer = new newsletterUserProfileSurferProxy(TRUE, 'm');
    $subscriberId = 1;
    $subscriber = array(
      'subscriber_email' => 'test@domain.tld',
      'subscriber_firstname' => 'Michael',
      'subscriber_lastname' => 'Test',
      'subscriber_salutation' => 0,
    );
    $lists = array(
      '1' => array('newsletter_list_name' => 'Liste1'),
      '2' => array('newsletter_list_name' => 'Liste2'),
      '3' => array('newsletter_list_name' => 'Liste3', '__SUBSCRIBED' => TRUE)
    );
    $newsletterObject = $this->getMock('base_newsletter');
    $newsletterObject
      ->expects($this->any())
      ->method('saveSubscription')
      ->will($this->returnValue(TRUE));
    $newsletterObject
      ->expects($this->once())
      ->method('addSubscription')
      ->with($this->equalTo($subscriberId), $this->equalTo(array('2')), $this->equalTo(2));
    $pageBaseObject->saveExistingSubscriberSubscriptions(
      $surfer, $subscriberId, $existingSubscriptions, $newsletterObject, $lists
    );
  }

  /**
  * @covers NewsletterUserProfileBase::saveExistingSubscriberSubscriptions
  */
  public function testSaveExistingSubscriberSubscriptionsExpectingFalseWhileSendingEmail() {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageParams(array('newsletter_list_id' => array('1', '2')));
    $pageBaseObject->setPageData(
      array(
        'messageConfigurationSaved' => 'saved',
        'messageErrorSendingMail' => 'error'
      )
    );
    $existingSubscriptions = array('1' => 4, '3' => 2,);
    $surfer = new newsletterUserProfileSurferProxy(TRUE, 'm');
    $subscriberId = 1;
    $subscriber = array(
      'subscriber_email' => 'test@domain.tld',
      'subscriber_firstname' => 'Michael',
      'subscriber_lastname' => 'Test',
      'subscriber_salutation' => 0,
    );
    $lists = array(
      '1' => array('newsletter_list_name' => 'Liste1'),
      '2' => array('newsletter_list_name' => 'Liste2'),
      '3' => array('newsletter_list_name' => 'Liste3', '__SUBSCRIBED' => TRUE)
    );
    $newsletterObject = $this->getMock('base_newsletter');
    $newsletterObject
      ->expects($this->any())
      ->method('saveSubscription')
      ->will($this->returnValue(TRUE));
    $newsletterObject
      ->expects($this->once())
      ->method('addSubscription')
      ->with($this->equalTo($subscriberId), $this->equalTo(array('2')), $this->equalTo(2));
    $pageBaseObject->saveExistingSubscriberSubscriptions(
      $surfer, $subscriberId, $existingSubscriptions, $newsletterObject, $lists
    );
  }

  /**
  * @covers NewsletterUserProfileBase::getXml
  * @dataProvider providerGetXml
  */
  public function testGetXml($expected, $surferIsValid, $subscriberExists, $listsExist,
      $loginPageId) {
    $this->markTestIncomplete();
    $pageBaseObject = $this->_getPageBaseObjectFixtureWithMockApplication($surferIsValid);
    $pageBaseObject->setPageData(
      array(
        'title' => 'Title',
        'subtitle' => 'Subtitle',
        'text' => 'Text',
        'captionSubmitButton' => 'Save',
        'messageConfigurationNotSaved' => 'Not saved',
        'messageConfigurationSaved' => 'saved',
        'messageErrorSendingMail' => 'error sending mail',
        'messageNoLists' => 'no lists',
        'messageNotLoggedIn' => 'not logged in',
        'senderEMail' => 'test@domain.tld',
        'senderName' => 'Tester',
        'mailTextNewSubscriptions' => 'new subscriptions',
        'mailTextReactivatedSubscriptions' => 'reactivated subscriptions',
        'mailTextRemovedSubscriptions' => 'removed subscriptions',
        'mailTextNoSubscriptions' => 'no subscriptions',
        'salutationFemale' => 'Mrs.',
        'salutationMale' => 'Mr.',
        'mailSubject' => 'Subject',
        'mailText' => 'Text',
        'pageLogin' => $loginPageId
      )
    );
    $pageBaseObject->setPageParams(
      array(
        'confirm' => '1',
        'newsletter_list_id' => array('2', '3')
      )
    );
    $expectedXml = '<title>Title</title>'.LF;
    $expectedXml .= '<subtitle>Subtitle</subtitle>'.LF;
    $expectedXml .= '<text>Text</text>'.LF;
    $expectedXml .= $expected;
    $newsletterObject = new newsletterUserProfileNewsletterBaseClassProxy(
      $subscriberExists, $listsExist
    );
    $pageBaseObject->setNewsletterObject($newsletterObject);
    $this->assertSame($expected, $pageBaseObject->getXml());
  }

  /**
  * @covers NewsletterUserProfileBase::getTitlesAndTextXml
  */
  public function testGetTitlesAndTextXml() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $pageBaseObject->setPageData(
      array(
        'title' => 'Title',
        'subtitle' => 'Subtitle',
        'text' => 'Text',
      )
    );
    $expected = '<title>Title</title>'.LF;
    $expected .= '<subtitle>Subtitle</subtitle>'.LF;
    $expected .= '<text>Text</text>'.LF;
    $this->assertSame($expected, $pageBaseObject->getTitlesAndTextXml());
  }

  /**
  * @covers NewsletterUserProfileBase::getConfigurationDialogXml
  */
  public function testGetConfigurationDialogXml() {
    $pageBaseObject = $this->_getPageBaseObjectFixture();
    $expected = '<dialog action="index.0" method="post">'.LF.
      '<input type="hidden" name="[confirm]" value="1"/>'.LF.
      '<label for="newsletterList1">Liste 1<em>Liste 1</em></label>'.LF.
      '<input id="newsletterList1" type="checkbox" name="[newsletter_list_id][1]" '.
      'value="0" checked="checked"/>'.LF.
      '<input type="submit" value="submit"/>'.LF.
      '</dialog>'.LF;
    $pageBaseObject->setPageData(array('captionSubmitButton' => 'submit'));
    $lists = array(
      1 => array(
        'newsletter_list_name' => 'Liste 1',
        'newsletter_list_description' => 'Liste 1',
        '__SUBSCRIBED' => TRUE
      )
    );
    $this->assertSame($expected, $pageBaseObject->getConfigurationDialogXml($lists, 3));
  }

  /***************************************************************************/
  /** DataProvider                                                           */
  /***************************************************************************/

  public static function providerAddMessage() {
    return array(
      'info message' => array(
        array(array('type' => 'info', 'text' => 'info message text')),
        'info',
        'info message text'
      ),
      'warning message' => array(
        array(array('type' => 'warning', 'text' => 'warning message text')),
        'warning',
        'warning message text'
      ),
      'error message' => array(
        array(array('type' => 'error', 'text' => 'error message text')),
        'error',
        'error message text'
      ),
      'invalid message' => array(
        array(),
        'invalid type',
        'message text'
      )
    );
  }

  public static function providerGetSubscriberDataFromSurfer() {
    return array(
      'female surfer' => array(
        array(
          'subscriber_email' => 'test@domain.tld',
          'subscriber_firstname' => 'Eva',
          'subscriber_lastname' => 'Test',
          'subscriber_salutation' => 1,
        ),
        new newsletterUserProfileSurferProxy(TRUE, 'f')
      ),
      'male surfer' => array(
        array(
          'subscriber_email' => 'test@domain.tld',
          'subscriber_firstname' => 'Michael',
          'subscriber_lastname' => 'Test',
          'subscriber_salutation' => 0,
        ),
        new newsletterUserProfileSurferProxy(TRUE, 'm')
      )
    );
  }

  public static function providerNewSubscriberWithSubscriptions() {
    return array(
      'success' => array(1),
      'failed' => array(FALSE)
    );
  }

  public static function providerGetXml() {
    $expected = '<title>Title</title>'.LF.
      '<subtitle>Subtitle</subtitle>'.LF.
      '<text>Text</text>'.LF;
    return array(
      'surfer is invalid' => array(
        $expected.
          '<messages>'.LF.
          '<message type="error">not logged in</message>'.LF.
          '</messages>'.LF,
        FALSE,
        FALSE,
        TRUE,
        0
      ),
      'surfer is invalid with redirect' => array(
        $expected,
        FALSE,
        FALSE,
        TRUE,
        666
      ),
      'surfer is valid & subscriber exists' => array(
        $expected.
          '<dialog action="index.0" method="post">'.LF.
          '<input type="hidden" name="[confirm]" value="1"/>'.LF.
          '<label for="newsletterList1">Liste1<em>Liste 1</em></label>'.LF.
          '<input id="newsletterList1" type="checkbox" name="[newsletter_list_id][1]" value="0" '.
          'checked="checked"/>'.LF.
          '<label for="newsletterList2">Liste2<em>Liste 2</em></label>'.LF.
          '<input id="newsletterList2" type="checkbox" name="[newsletter_list_id][2]" value="0"'.
          '/>'.LF.
          '<label for="newsletterList3">Liste3<em>Liste 1</em></label>'.LF.
          '<input id="newsletterList3" type="checkbox" name="[newsletter_list_id][3]" value="0"'.
          '/>'.LF.
          '<input type="submit" value="Save"/>'.LF.
          '</dialog>'.LF.
          '<messages>'.LF.
          '<message type="info">saved</message>'.LF.
          '</messages>'.LF,
        TRUE,
        1,
        TRUE,
        0
      ),
      'surfer is valid & subscriber does not exist' => array(
        $expected.
          '<dialog action="index.0" method="post">'.LF.
          '<input type="hidden" name="[confirm]" value="1"/>'.LF.
          '<label for="newsletterList1">Liste1<em>Liste 1</em></label>'.LF.
          '<input id="newsletterList1" type="checkbox" name="[newsletter_list_id][1]" value="0" '.
          'checked="checked"/>'.LF.
          '<label for="newsletterList2">Liste2<em>Liste 2</em></label>'.LF.
          '<input id="newsletterList2" type="checkbox" name="[newsletter_list_id][2]" '.
          'value="0"/>'.LF.
          '<label for="newsletterList3">Liste3<em>Liste 1</em></label>'.LF.
          '<input id="newsletterList3" type="checkbox" name="[newsletter_list_id][3]" '.
          'value="0"/>'.LF.
          '<input type="submit" value="Save"/>'.LF.
          '</dialog>'.LF.
          '<messages>'.LF.
          '<message type="info">saved</message>'.LF.
          '</messages>'.LF,
        TRUE,
        FALSE,
        TRUE,
        0
      ),
      'lists do not exist' => array(
        $expected.
          '<messages>'.LF.
          '<message type="error">no lists</message>'.LF.
          '</messages>'.LF,
        TRUE,
        1,
        FALSE,
        0
      )
    );
  }
}

/**
* Owner proxy class.
*/
class newsletterUserProfileOwnerProxy extends NewsletterUserProfile {
  public $paramName = 'nws';

  public function __construct($applicationObject = NULL) {
    if (!empty($applicationObject)) {
      $this->setApplication($applicationObject);
    }
  }

  public function getWebLink(
           $pageId = 0, $lng = NULL, $mode = NULL, $params = NULL, $paramName = NULL
         ) {
    return sprintf('index.%d', $pageId);
  }

  public function getAbsoluteURL($url, $text = '', $sid = TRUE) {
    return $url;
  }
}

/**
* Community surfer proxy class
*/
class newsletterUserProfileSurferProxy {
  public $surferEMail = 'test@domain.tld';
  public $isValid;
  public $surfer;

  public function __construct($surferIsValid, $gender) {
    $this->isValid = $surferIsValid;
    if ($surferIsValid) {
      $this->surfer = array(
        'surfer_givenname' => ($gender == 'f') ? 'Eva' : 'Michael',
        'surfer_surname' => 'Test',
        'surfer_gender' => $gender
      );
    }
  }
}

class newsletterUserProfileNewsletterBaseClassProxy extends base_newsletter {
  public $newsletterLists = array();
  public $subscriptions = array();
  private $_subscriberExists;
  private $_listsExist;

  public function __construct($subscriberExists = FALSE, $listsExist) {
    $this->_subscriberExists = $subscriberExists;
    $this->_listsExist = $listsExist;
  }

  public function loadNewsletterLists() {
    if ($this->_listsExist) {
      $this->newsletterLists = array(
        '1' => array(
          'newsletter_list_name' => 'Liste1',
          'newsletter_list_description' => 'Liste 1',
          '__SUBSCRIBED' => TRUE
        ),
        '2' => array(
          'newsletter_list_name' => 'Liste2',
          'newsletter_list_description' => 'Liste 2'
        ),
        '3' => array(
          'newsletter_list_name' => 'Liste3',
          'newsletter_list_description' => 'Liste 1'
        )
      );
    }
  }

  public function loadSubscriptions($subscriberId) {
    $this->subscriptions = array(
      1 => array(
        'newsletter_list_id' => 1,
        'subscription_status' => 2
      ),
      2 => array(
        'newsletter_list_id' => 1,
        'subscription_status' => 4
      )
    );
  }

  public function subscriberExists($surferEmail) {
    return $this->_subscriberExists;
  }

  public function addSubscriber($subscriber) {
    return 1;
  }

  public function addSubscription($subscriberId, $newsletterListIds, $status) {
    return TRUE;
  }

  public function saveSubscription($subscriberId, $newsletterListId, $status, $format) {
    return TRUE;
  }
}
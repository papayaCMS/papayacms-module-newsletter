<?php
require_once(dirname(__FILE__).'/../bootstrap.php');

require_once(dirname(__FILE__).'/../../src/Feed/Fetcher.php');

class PapayaModuleNewsletterFeedFetcherTest extends PapayaTestCase {

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::loadFeed
  * @covers PapayaModuleNewsletterFeedFetcher::_fetchFeedContents
  */
  public function testLoadFeed() {
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient(
      $this->getMockHttpClientFixture(TRUE, dirname(__FILE__).'/TestData/papaya-cms.de.rss')
    );
    $this->assertInstanceOf(
      'papaya_atom_feed', $fetcher->loadFeed('sample_feed')
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::loadFeed
  * @covers PapayaModuleNewsletterFeedFetcher::_fetchFeedContents
  */
  public function testLoadFeedWithNonExistingFileExpectingNull() {
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient(
      $this->getMockHttpClientFixture(FALSE, '')
    );
    $this->assertNull($fetcher->loadFeed('sample_feed'));
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::loadFeed
  * @covers PapayaModuleNewsletterFeedFetcher::_fetchFeedContents
  */
  public function testLoadFeedWithInvalidFeedExpectingNull() {
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient(
      $this->getMockHttpClientFixture(TRUE, dirname(__FILE__).'/TestData/some.xml')
    );
    $this->assertNull($fetcher->loadFeed('sample_feed'));
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::setHttpClient
  */
  public function testSetHttpClient() {
    $client = $this->getMock('PapayaHttpClient');
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient($client);
    $this->assertAttributeSame($client, '_httpClient', $fetcher);
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::getHttpClient
  */
  public function testGetHttpClientAfterSet() {
    $client = $this->getMock('PapayaHttpClient');
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient($client);
    $this->assertSame($client, $fetcher->getHttpClient());
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::getHttpClient
  */
  public function testGetHttpClientImplicitCreate() {
    $client = $this->getMock('PapayaHttpClient');
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $this->assertInstanceOf('PapayaHTTPClient', $fetcher->getHttpClient());
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::fetchInto
  */
  public function testFetchIntoLimitByMaximum() {
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient(
      $this->getMockHttpClientFixture(TRUE, dirname(__FILE__).'/TestData/papaya-cms.de.rss')
    );
    $feed = $fetcher->loadFeed('sample_feed');
    $dom = new PapayaXmlDocument();
    $dom->appendChild($dom->createElement('list'));
    $this->assertTrue(
      $fetcher->fetchInto($dom->documentElement, $feed, 0, 1, 1)
    );
    $this->assertXmlStringEqualsXmlString(
      file_get_contents(dirname(__FILE__).'/TestData/first-entry.xml'), $dom->saveXml()
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::fetchInto
  */
  public function testFetchIntoLimitByPeriod() {
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient(
      $this->getMockHttpClientFixture(TRUE, dirname(__FILE__).'/TestData/papaya-cms.de.rss')
    );
    $feed = $fetcher->loadFeed('sample_feed');
    $dom = new PapayaXmlDocument();
    $dom->appendChild($dom->createElement('list'));
    $this->assertTrue(
      $fetcher->fetchInto($dom->documentElement, $feed, 1291366801, 1, 5)
    );
    $this->assertXmlStringEqualsXmlString(
      file_get_contents(dirname(__FILE__).'/TestData/first-entry.xml'), $dom->saveXml()
    );
  }

  /**
  * @covers PapayaModuleNewsletterFeedFetcher::fetchInto
  */
  public function testFetchIntoLimitByPeriodReturnsNoEntry() {
    $fetcher = new PapayaModuleNewsletterFeedFetcher();
    $fetcher->setHttpClient(
      $this->getMockHttpClientFixture(TRUE, dirname(__FILE__).'/TestData/papaya-cms.de.rss')
    );
    $feed = $fetcher->loadFeed('sample_feed');
    $dom = new PapayaXmlDocument();
    $dom->appendChild($dom->createElement('list'));
    $this->assertFalse(
      $fetcher->fetchInto($dom->documentElement, $feed, 1292255401, 1, 5)
    );
    $this->assertXmlStringEqualsXmlString(
      '<?xml version="1.0"?><list/>', $dom->saveXml()
    );
  }

  /*********************************
  * Fixtures
  *********************************/

  private function getMockHttpClientFixture($valid, $fileName) {
    $client = $this->getMock(
      'PapayaHTTPClient', array('reset', 'setUrl', 'send', 'getResponseStatus', 'getResponseData')
    );
    $client
       ->expects($this->once())
       ->method('reset');
    $client
       ->expects($this->once())
       ->method('setUrl')
       ->with($this->equalTo('sample_feed'));
    $client
      ->expects($this->once())
      ->method('send')
      ->will($this->returnValue(TRUE));
    $client
      ->expects($this->once())
      ->method('getResponseStatus')
      ->will($this->returnValue($valid ? 200 : 404));
    if ($valid) {
      $client
        ->expects($this->once())
        ->method('getResponseData')
        ->will($this->returnValue(file_get_contents($fileName)));
    } else {
      $client
        ->expects($this->never())
        ->method('getResponseData');
    }
    return $client;
  }
}
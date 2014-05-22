<?php
/**
* Fetch rss/atom feeds entries  into an xml document
*
* This item allows to load a list of newsletter feed import configuration and delete one.
*
* @copyright 2010 by papaya Software GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package Papaya-Commercial
* @subpackage Newsletter
*/

/**
* Fetch rss/atom feeds entries  into an xml document
*
* @package Papaya-Commercial
* @subpackage Newsletter
*/
class PapayaModuleNewsletterFeedFetcher {

  private $_httpClient = NULL;

  /**
  * load feed defined by url
  *
  * @param string $url
  * @return papaya_atom_feed|NULL
  */
  public function loadFeed($url) {
    $result = NULL;
    $feedContents = $this->_fetchFeedContents($url);
    if (!empty($feedContents)) {
      $internalErrors = libxml_use_internal_errors(TRUE);
      libxml_clear_errors();
      $dom = new PapayaXmlDocument();
      if ($dom->loadXml($feedContents)) {
        include_once(PAPAYA_INCLUDE_PATH.'system/xml/feeds/atom/papaya_atom_feed.php');
        $feed = new papaya_atom_feed();
        if ($feed->load($dom, $url)) {
          $result = $feed;
        }
      }
      libxml_use_internal_errors($internalErrors);
    }
    return $result;
  }

  /**
  * Fetch feed contents using the PapayaHTTPClient
  *
  * @param string $url
  * @return string
  */
  protected function _fetchFeedContents($url) {
    $httpClient = $this->getHttpClient();
    $httpClient->reset();
    $httpClient->setUrl($url);
    if ($httpClient->send() &&
        200 == $httpClient->getResponseStatus()) {
      return $httpClient->getResponseData();
    }
    return '';
  }

  /**
  * Getter with implicit create for the http client
  *
  * @return PapayaHttpClient
  */
  public function getHttpClient() {
    if (is_null($this->_httpClient)) {
      $this->_httpClient = new PapayaHTTPClient();
    }
    return $this->_httpClient;
  }

  /**
  * Setter for http client
  *
  * @param PapayaHTTPClient $httpClient
  */
  public function setHttpClient(PapayaHTTPClient $httpClient) {
    $this->_httpClient = $httpClient;
  }

  /**
  * Fetch entries from feed into an xml document.
  *
  * The entries are limited by an minimum a maximum and a minimum publishing date.
  * The method will return TRUE if the limits are fullfilled.
  *
  * @param PapayaXmlElement $parent
  * @param papaya_atom_feed $feed
  * @param integer $publishedAfter
  * @param integer $min
  * @param integer $max
  * @return boolean
  */
  public function fetchInto(PapayaXmlElement $parent,
                            papaya_atom_feed $feed,
                            $publishedAfter, $min, $max) {
    $counter = 0;
    if (count($feed->entries) >= $min) {
      foreach ($feed->entries as $entry) {
        if ($entry->published > $publishedAfter) {
          $parent->appendXml($entry->saveXml('entry'));
          if (++$counter >= $max) {
            break;
          }
        }
      }
    }
    return ($counter >= $min && $counter > 0);
  }
}
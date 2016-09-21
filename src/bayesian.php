<?php
/**
* Implementation of a bayesian filter
*
* @copyright 2010-2016 by dimensional GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license   papaya Commercial License (PCL)
*
* Redistribution of this script or derivated works is strongly prohibited!
* The Software is protected by copyright and other intellectual property
* laws and treaties. papaya owns the title, copyright, and other intellectual
* property rights in the Software. The Software is licensed, not sold.
*
* @package Papaya-Modules
* @subpackage Newsletter
* @version $Id: bayesian.php 6 2014-02-13 15:40:43Z SystemVCS $
*/

require_once(dirname(__FILE__).'/bayesian_db.php');
require_once(PAPAYA_INCLUDE_PATH.'system/sys_base_object.php');

/**
* implementation of a bayesian filter
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class bayesian extends base_object {
  /**
  * bayesian database object
  *
  * @var bayesian_db $bdb
  * @access private
  */
  var $bdb = NULL;
  /**
   * Array of the tokens and their values
   *
   * @var array
   * @access private
   */
  //var $tokenValues = NULL;
  /**
   * Categroy of analyzed content
   *
   * @var unknown_type
   * @access private
   */
  var $contentCategory = 'unknown';
  /**
   * List of chars where tokens will be splitted up
   * This are: \0,  , &, (, /, :, [, ?, {, _
   * @var string
   */
  var $nonWordChars = '\\x00-\\x26\\x28-\\x2F\\x3A-\\x3F\\x5B-\\x5F\\x7B-\\x7F';
  /**
   * if the filter consider case
   *
   * @var boolean
   */
  var $caseSensitive = FALSE;

  var $minTokenLength = 3;
  var $maxTokenLength = 20;
  var $blockTokenLength = 60;
  var $ignoreWords = NULL;
  var $stopWords = NULL;
  var $categories = NULL;

  /**
   * Get instance of bayesian module if already instanciated, otherwise create
   * a new instance
   *
   * @return object
   */
  function getInstance() {
    static $bayesianFilter;
    if (!(isset($bayesianFilter) && is_object($bayesianFilter))) {
      $bayesianFilter = new bayesian();
    }
    return $bayesianFilter;
  }

  function initializeFilter() {
    $this->bdb = &bayesian_db::getInstance();
  }

  function _getHashes($tokens) {
    $hashes = array();
    foreach ($tokens['tokens'] as $token => $value) {
      $hashes['tokens'][md5($token)] = $value;
    }
    return $hashes;
  }

  /**
   * Analyzing the given content. Based on Bayesian rules.
   * Returns the category of the content
   *
   * @param string $content
   * @return sring
   * @access public
   */
  function analyzeContent($content) {
    if (!isset($this->ignoreWords)) {
      $this->ignoreWords = $this->bdb->loadIgnoreWords();
    }
    $tokens = $this->_getTokens($content);
    $hashes = $this->_getHashes($tokens);
    $scores = $this->_categorize($hashes['tokens']);
    if ($scores['BOUNCE'] > 0.5) {
      $this->contentCategory = 'bounce';
    } else {
      $this->contentCategory = 'ham';
    }
    return $this->contentCategory;
  }

  /**
   * split text into tokens
   *
   * @param string $content
   * @return array
   * @access private
   */

  function _getTokens($string) {
    $result = array(
      'tokens' => array(),
      'smalltokens' => 0,
      'largetokens' => 0,
      'blocktokens' => 0
    );
    if ($tokens = preg_split('~['.$this->nonWordChars.']+~', $string)) {
      foreach ($tokens as $token) {
        if ($this->caseSensitive) {
          $token = trim($token);
        } else {
          $token = papaya_strings::strtolower(trim($token));
        }
        $ignoreToken = !('' == $token || isset($this->ignoreWords[$token]));
        if ($ignoreToken && strlen($token) < $this->minTokenLength) {
          ++$result['smalltokens'];
        } else {
          if (strlen($token) > $this->blockTokenLength) {
            ++$result['blocktokens'];
          } elseif (strlen($token) > $this->maxTokenLength) {
            ++$result['largetokens'];
          }
          if (isset($result['tokens'][$token])) {
            ++$result['tokens'][$token];
          } else {
            $result['tokens'][$token] = 1;
          }
        }
      }
    }
    return $result;
  }

  /**
   * get category
   * @access private
   * @var array $tokens
   *
   */
  function _categorize($tokens) {
    $scores = array();
    if (is_array($tokens) && count($tokens) > 0) {
      if (empty($this->categories)) {
        //load category data
        $this->bdb->loadCategories($this->categories);
      }
      if (isset($this->categories)) {
        $categories = $this->categories;
        $categoryTotals = count($categories);
        $wordTotals = $this->categories['TOTALS'];

        //load word data
        $words = $this->bdb->_loadWords(array_keys($tokens));

        if (isset($categories) && is_array($categories)) {
          foreach ($categories as $categoryId => $categoryData) {
            if (is_array($categoryData)) {
              $scores[$categoryId] = $categoryData['category_probability'];
              // probability for an unknown word
              $unknownProbability = 1.0 / ($categoryData['category_words'] * 2);
              foreach ($tokens as $token => $tokenCount) {
                if (isset($words['EXISTS'][$token])) {
                  if (isset($words['DATA'][$categoryId][$token]) &&
                      ($wordCount = $words['DATA'][$categoryId][$token])
                     ) {
                    $probability = $wordCount / $categoryData['category_words'];
                  } else {
                    $probability = $unknownProbability;
                  }
                  $scores[$categoryId] *= pow($probability, $tokenCount) *
                    pow($wordTotals / $categoryTotals, $tokenCount);
                }
              }
            }
          }
        }
      }
    }
    return $this->_rescale($scores);
  }

  /**
   * The filter will lern to categorize the content the next time as given category
   *
   * @param string $content
   * @param string $category
   * @access private
   */
  function teachFilter() {
    $this->processTraining();
  }

  function _countStopWords($tokens, $lngId) {
    $counts = array();
    $summary = 0;
    foreach ($tokens as $token) {
      if (isset($this->stopWords[$lngId][$token])) {
        if (isset($counts[$token])) {
          ++$counts[$token];
        } else {
          $counts[$token] = 1;
        }
        ++$summary;
      }
    }
    return array($summary, $counts);
  }


  /** rescale the results between 0 and 1.
   * @return array normalized scores (keys => category, values => scores)
   * @param array scores (keys => category, values => scores)
   */
  function _rescale($scores) {
    // Scale everything back to a reasonable area in
    // logspace (near zero), un-loggify, and normalize
    $total = 0.0;
    $max = 0.0;
    foreach ($scores as $score) {
      if ($score >= $max) {
        $max = $score;
      }
    }
    foreach ($scores as $cat => $score) {
      $scores[$cat] = (float)exp($score - $max);
      $total += (float)pow($scores[$cat], 2);
    }
    $total = (float)sqrt($total);
    if ($total > 0) {
      foreach ($scores as $cat => $score) {
        $scores[$cat] = (float)$scores[$cat] / $total;
      }
    } else {
      foreach ($scores as $cat => $score) {
        $scores[$cat] = 0;
      }
    }
    return $scores;
  }

  function processTraining() {
    $this->bdb->emptyWordsTable();
    $references = $this->bdb->getReferences();
    if (!empty($references)) {
      foreach ($references as $reference) {
        $this->_train($reference['category_id'], $reference['reference_data']);
      }
      $this->bdb->updateProbabilities();
      return TRUE;
    }
    return FALSE;
  }

  function _train($category, $tokenStr) {
    $tokens = $this->_unserializeTokens($tokenStr);
    if (isset($tokens) && is_array($tokens) && count($tokens) > 0) {
      foreach ($tokens as $token => $tokenCount) {
        $this->bdb->updateToken($category, $token, $tokenCount);
      }
    }
  }


  function _unserializeTokens($tokenStr) {
    $result = array();
    if (preg_match_all('~^(.+):(\d+)$~m', $tokenStr, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $result[$match[1]] = (int)$match[2];
      }
    }
    return $result;
  }

  function _serializeTokens($tokens) {
    if (isset($tokens) && is_array($tokens) && count($tokens) > 0) {
      $result = '';
      foreach ($tokens as $token => $tokenCount) {
        $result .= $token.':'.$tokenCount."\n";
      }
      return substr($result, 0, -1);
    }
    return '';
  }

  function rateMessage($content, $category) {
    $tokenStr = '';
    if (!isset($this->ignoreWords)) {
      $this->ignoreWords = $this->bdb->loadIgnoreWords();
    }
    $tokenData = $this->_getHashes($this->_getTokens($content));
    $tokenStr = trim($this->_serializeTokens($tokenData['tokens']));
    if ($tokenStr != '') {
      $data = array('reference_data' => $tokenStr, 'category_id' => $category);
      if (FALSE !== $this->bdb->insertReference($data)) {
        return TRUE;
      } else {
        return FALSE;
      }
    } else {
      return FALSE;
    }
  }
}


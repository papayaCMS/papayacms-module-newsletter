<?php
/**
* Provides methods accessing the database of the bayesian filter.
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
* @version $Id: bayesian_db.php 2 2013-12-09 15:38:42Z weinert $
*/

/**
* {%SHORT_CLASS_DESCRIPTION%}
*
* @package Papaya-Modules
* @subpackage Newsletter
*/
class bayesian_db extends base_db {

  var $tableIgnoreWords = '';
  var $tableReferences = '';
  var $tableWords = '';
  var $tableCategories = '';
  var $tableBayesianValues = '';
  var $tableMails = '';

  function __construct() {
    $this->tableIgnoreWords = PAPAYA_DB_TABLEPREFIX.'_newsletter_bayesian_ignorewords';
    $this->tableReferences = PAPAYA_DB_TABLEPREFIX.'_newsletter_bayesian_references';
    $this->tableWords = PAPAYA_DB_TABLEPREFIX.'_newsletter_bayesian_words';
    $this->tableCategories = PAPAYA_DB_TABLEPREFIX.'_newsletter_bayesian_categories';
    $this->tableMails = PAPAYA_DB_TABLEPREFIX.'_newsletter_bouncinghandler_mails';
  }

  /**
   * PHP4 Default Constructor
   *
   * @return bayesian_db
   */
  function bayesian_db() {
    $this->__construct();
  }

  function getInstance() {
    static $bayesianDB;
    if (!(isset($bayesianDB) && is_object($bayesianDB))) {
      $bayesianDB = new bayesian_db();
    }
    return $bayesianDB;
  }

  /**
   * load categories
   *
   * @var array $categories
   * @return array
   */
  function loadCategories(&$categories) {
    $categories = array();
    $sql = "SELECT category_id, category_probability,
                   category_words
              FROM %s";
    if ($res = $this->databaseQueryFmt($sql, array($this->tableCategories))) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $categories[$row['category_id']] = $row;
        if (isset($categories['TOTALS'])) {
          $categories['TOTALS'] += $row['category_words'];
        } else {
          $categories['TOTALS'] = $row['category_words'];
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * collecting all stored rates of the given tokens (or their hashes) and returns them.
   * For unknown tokens, a default value is returned
   *
   * @param array $hashes
   * @return array
   * @access private
   */
  function _loadWords($words) {
    $filter = $this->databaseGetSQLCondition('token', $words);
    $sql = "SELECT token, word_count, category_id
              FROM %s
             WHERE $filter";
    if ($res = $this->databaseQueryFmt($sql, array($this->tableWords))) {
      $result = array(
        'EXISTS' => array(),
        'DATA' => array(),
      );
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $result['EXISTS'][$row['token']] = TRUE;
        $result['DATA'][$row['category_id']][$row['token']] = $row['word_count'];
      }
      return $result;
    }
    return FALSE;
  }

  /**
   * load ignore words
   *
   * @return array
   * @access public
   */
  function loadIgnoreWords() {
    $result = array();
    $sql = "SELECT ignoreword_hash
              FROM %s
             ORDER BY ignoreword_hash";
    $params = array($this->tableIgnoreWords);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow()) {
        $result[$row[0]] = TRUE;
      }
    }
    return $result;
  }

  function getReferences() {
    $sql = "SELECT reference_id, category_id, reference_data
              FROM %s
             ORDER BY reference_id";
    $references = array();
    if ($res = $this->databaseQueryFmt($sql, $this->tableReferences)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $references[] = $row;
      }
    }
    return $references;
  }

  function updateToken($category, $token, $tokenCount) {
    $sql = "SELECT COUNT(*)
              FROM %s
             WHERE token = '%s'
               AND category_id = '%s'";
    $params = array($this->tableWords, $token, $category);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($res->fetchField() > 0) {
        $sql = "UPDATE %s
                   SET word_count = word_count + %d
                 WHERE token = '%s'
                   AND category_id = '%s'";
        $params = array($this->tableWords, $tokenCount, $token, $category);
        $this->databaseQueryFmtWrite($sql, $params);
      } else {
        $data = array(
          'token' => $token,
          'word_count' => $tokenCount,
          'category_id' => $category
        );
        $this->databaseInsertRecord($this->tableWords, NULL, $data);
      }
    }
  }

  function updateProbabilities() {
    $this->databaseEmptyTable($this->tableCategories);
    $sql = "SELECT category_id, SUM(word_count) AS wordcount
              FROM %s
             GROUP BY category_id";
    if ($res = $this->databaseQueryFmt($sql, $this->tableWords)) {
      $probabilities = array();
      $total = 0;
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $probabilities[] = $row;
        $total += $row['wordcount'];
      }
    }
    $data = array();
    foreach ($probabilities as $probability) {
      if ($total > 0) {
        $data[] = array(
            'category_id' => $probability['category_id'],
            'category_probability' => @(float)$probability['wordcount'] / $total,
            'category_words' => $probability['wordcount']
        );
      }
    }
    if (count($data) > 0) {
      $this->databaseInsertRecords($this->tableCategories, $data);
    }
  }

  function insertReference($data) {
    return $this->databaseInsertRecord($this->tableReferences, NULL, $data);
  }

  function emptyWordsTable() {
    return $this->databaseEmptyTable($this->tableWords);
  }

}


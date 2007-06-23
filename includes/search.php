<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 3 (Druid)
 * Copyright (C) 2006-2007 Dan Fuhry
 * search.php - algorithm used to search pages
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Implementation of array_merge() that preserves key names. $arr2 takes precedence over $arr1.
 * @param array $arr1
 * @param array $arr2
 * @return array
 */
 
function enano_safe_array_merge($arr1, $arr2)
{
  $arr3 = $arr1;
  foreach($arr2 as $k => $v)
  {
    $arr3[$k] = $v;
  }
  return $arr3;
}

/**
 * Algorithm to actually do the searching. This system usually works pretty fast (tested and developed on a site with 22 pages) but one
 * caveat of this algorithm is that it has to load the entire index into memory. It also requires manual parsing of the search query
 * which can be quite CPU-intensive. On the flip side this algorithm is extremely flexible and can be adapted for other uses very easily.
 * 
 * Most of the time, this system is disabled. It is only used when MySQL can't or won't allow FULLTEXT indices.
 *
 * @package Enano
 * @subpackage Page management frontend
 * @license GNU General Public License http://www.enanocms.org/Special:GNU_General_Public_License
 */

class Searcher
{
  
  var $results;
  var $index;
  var $warnings;
  var $match_case = false;
  
  function __construct()
  {
    $this->warnings = Array();
  }
  
  function Searcher()
  {
    $this->__construct();
  }
  
  function warn($t)
  {
    if(!in_array($t, $this->warnings)) $this->warnings[] = $t;
  }
  
  function convertCase($text)
  {
    return ( $this->match_case ) ? $text : strtolower($text);
  }
  
  function buildIndex($texts)
  {
    $this->index = Array();

    foreach($texts as $i => $l)
    {
      $seed = md5(microtime(true) . mt_rand());
      $texts[$i] = str_replace("'", 'xxxApoS'.$seed.'xxx', $texts[$i]);
      $texts[$i] = preg_replace('#([\W_]+)#i', ' ', $texts[$i]);
      $texts[$i] = preg_replace('#([ ]+?)#', ' ', $texts[$i]);
      $texts[$i] = preg_replace('#([\']*){2,}#s', '', $texts[$i]);
      $texts[$i] = str_replace('xxxApoS'.$seed.'xxx', "'", $texts[$i]);
      $l = $texts[$i];
      $words = Array();
      $good_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789\' ';
      $good_chars = enano_str_split($good_chars, 1);
      $letters = enano_str_split($l, 1);
      foreach($letters as $x => $t)
      {
        if(!in_array($t, $good_chars))
          unset($letters[$x]);
      }
      $letters = implode('', $letters);
      $words = explode(' ', $letters);
      foreach($words as $c => $w)
      {
        if(strlen($w) < 4)
          unset($words[$c]);
        else
          $words[$c] = $w;
      }
      $words = array_values($words);
      foreach($words as $c => $w)
      {
        if(isset($this->index[$w]))
        {
          if(!in_array($i, $this->index[$w]))
            $this->index[$w][] = $i;
        }
        else
        {
          $this->index[$w] = Array();
          $this->index[$w][] = $i;
        }
      }
    }
    foreach($this->index as $k => $v)
    {
      $this->index[$k] = implode(',', $this->index[$k]);
    }
  }
  
  function search($query, $texts)
  {
    
    // OK, let's establish some basics here. Here is the procedure for performing the search:
    //   * search for items that matches all the terms in the correct order.
    //   * search for items that match in any order
    //   * eliminate one term and do the loop all over
    
    $this->results = Array();
    $query = $this->parseQuery($query);
    $querybak = $query;
    for($i = sizeof($query['any'])-1; $i >= 0; $i--)
    {
      $res = $this->performCoreSearch($query, $texts, true);
      $this->results = enano_safe_array_merge($this->results, $res);
      $res = $this->performCoreSearch($query, $texts, false);
      $this->results = enano_safe_array_merge($this->results, $res);
      unset($query['any'][$i]);
    }
    
    // Last resort - search for any of the terms instead of all of 'em
    $res = $this->performCoreSearch($querybak, $texts, false, true);
    $this->results = enano_safe_array_merge($this->results, $res);
    
    $this->highlightResults($querybak);
  }
  
  // $texts should be a textual MySQL query!
  // @todo document
  function searchMySQL($query, $texts)
  {
    global $db;
    // OK, let's establish some basics here. Here is the procedure for performing the search:
    //   * search for items that matches all the terms in the correct order.
    //   * search for items that match in any order
    //   * eliminate one term and do the loop all over
    
    $this->results = Array();
    $query = $this->parseQuery($query);
    $querytmp = $query;
    $querybak = $query;
    for($i = sizeof($querytmp['any'])-1; $i >= 0; $i--)
    {
      $res = $this->performCoreSearchMySQL($querytmp, $texts, true);
      $this->results = enano_safe_array_merge($this->results, $res);
      $res = $this->performCoreSearchMySQL($querytmp, $texts, false);
      $this->results = enano_safe_array_merge($this->results, $res);
      unset($querytmp['any'][$i]);
    }
    
    // Last resort - search for any of the terms instead of all of 'em
    $res = $this->performCoreSearchMySQL($querybak, $texts, false, true);
    $this->results = enano_safe_array_merge($this->results, $res);
    
    $this->highlightResults($querybak);
  }
  
  /**
   * This method assumes that $query is already parsed and $texts is an (associative) array of possible results
   * @param array $query A search query parsed with Searcher::parseQuery()
   * @param array $texts The list of possible results
   * @param bool $exact_order If true, only matches results with the terms in the same order as the terms in the query
   * @return array An associative array of results
   * @access private
   */
  function performCoreSearch($query, $texts, $exact_order = false, $any = false)
  {
    $textkeys = array_keys($texts);
    $results = Array();
    if($exact_order)
    {
      $query = $this->concatQueryTerms($query);
    }
    $query['trm'] = array_merge($query['any'], $query['req']);
    # Find all remotely possible results first
    // Single-word terms
    foreach($this->index as $term => $keys)
    {
      foreach($query['trm'] as $userterm)
      {
        if($this->convertCase($userterm) == $this->convertCase($term))
        {
          $k = explode(',', $keys);
          foreach($k as $idxkey)
          {
            if(isset($texts[$idxkey])) 
            {
              $results[$idxkey] = $texts[$idxkey];
            }
            else
            {
              if(preg_match('#^([0-9]+)$#', $idxkey))
              {
                $idxkey = intval($idxkey);
                if(isset($texts[$idxkey])) $results[$idxkey] = $texts[$idxkey];
              }
            }
          }
        }
      }
    }
    // Quoted terms
    foreach($query['trm'] as $userterm)
    {
      if(!preg_match('/[\s"\'~`!@#\$%\^&\*\(\)\{\}:;<>,.\/\?_-]/', $userterm)) continue;
      foreach($texts as $k => $t)
      {
        if(strstr($this->convertCase($t), $this->convertCase($userterm)))
        {
          // We have a match!
          if(!isset($results[$k])) $results[$k] = $t;
        }
      }
    }
    // Remove excluded terms
    foreach($results as $k => $r)
    {
      foreach($query['not'] as $not)
      {
        if(strstr($this->convertCase($r), $this->convertCase($not))) unset($results[$k]);
      }
    }
    if(!$any)
    {
      // Remove results not containing all terms
      foreach($results as $k => $r)
      {
        foreach($query['any'] as $term)
        {
          if(!strstr($this->convertCase($r), $this->convertCase($term))) unset($results[$k]);
        }
      }
    }
    // Remove results not containing all required terms
    foreach($results as $k => $r)
    {
      foreach($query['req'] as $term)
      {
        if(!strstr($this->convertCase($r), $this->convertCase($term))) unset($results[$k]);
      }
    }
    return $results;
  }
  
  /**
   * This is the same as performCoreSearch, but $texts should be a MySQL result resource. This can save tremendous amounts of memory on large sites.
   * @param array $query A search query parsed with Searcher::parseQuery()
   * @param string $texts A text MySQL query that selects the text as the first column and the index key as the second column
   * @param bool $exact_order If true, only matches results with the terms in the same order as the terms in the query
   * @return array An associative array of results
   * @access private
   */
  function performCoreSearchMySQL($query, $texts, $exact_order = false, $any = false)
  {
    global $db;
    $results = Array();
    if($exact_order)
    {
      $query = $this->concatQueryTerms($query);
    }
    $query['trm'] = array_merge($query['any'], $query['req']);
    # Find all remotely possible results first
    $texts = $db->sql_query($texts);
    if ( !$texts )
      $db->_die('The error is in the search engine.');
    if ( $r = $db->fetchrow_num($texts) )
    {
      do
      {
        foreach($this->index as $term => $keys)
        {
          foreach($query['trm'] as $userterm)
          {
            if($this->convertCase($userterm) == $this->convertCase($term))
            {
              $k = explode(',', $keys);
              foreach($k as $idxkey)
              {
                $row[0] = $r[0];
                $row[1] = $r[1];
                if(!isset($row[1]))
                {
                  echo('PHP PARSER BUG: $row[1] is set but not set... includes/search.php:'.__LINE__);
                  $GLOBALS['template']->footer();
                  exit;
                }
                if($row[1] == $idxkey)
                  $results[$idxkey] = $row[0];
                else
                {
                  if(preg_match('#^([0-9]+)$#', $idxkey))
                  {
                    $idxkey = intval($idxkey);
                    if($row[1] == $idxkey) $results[$idxkey] = $row[0];
                  }
                }
              }
            }
          }
        }
        // Quoted terms
        foreach($query['trm'] as $userterm)
        {
          if(!preg_match('/[\s"\'~`!@#\$%\^&\*\(\)\{\}:;<>,.\/\?_-]/', $userterm)) continue;
          if(strstr($this->convertCase($r[0]), $this->convertCase($userterm)))
          {
            // We have a match!
            if(!isset($results[$r[1]])) $results[$r[1]] = $r[0];
          }
        }
      } while( $r = $db->fetchrow_num($texts) );
    }
    // Remove excluded terms
    foreach($results as $k => $r)
    {
      foreach($query['not'] as $not)
      {
        if(strstr($this->convertCase($r), $this->convertCase($not))) unset($results[$k]);
      }
    }
    if(!$any)
    {
      // Remove results not containing all terms
      foreach($results as $k => $r)
      {
        foreach($query['any'] as $term)
        {
          if(!strstr($this->convertCase($r), $this->convertCase($term))) unset($results[$k]);
        }
      }
    }
    // Remove results not containing all terms
    foreach($results as $k => $r)
    {
      foreach($query['req'] as $term)
      {
        if(!strstr($this->convertCase($r), $this->convertCase($term))) unset($results[$k]);
      }
    }
    return $results;
  }
  
  function concatQueryTerms($query)
  {
    $tmp = implode(' ', $query['any']);
    unset($query['any']);
    $query['any'] = Array(0 => $tmp);
    return $query;
  }
  
  /**
   * Builds a basic assoc array with a more organized version of the query
   */
  
  function parseQuery($query)
  {
    $ret = array(
      'any' => array(),
      'req' => array(),
      'not' => array()
      );
    $terms = array();
    $in_quote = false;
    $start_term = 0;
    $just_finished = false;
    for ( $i = 0; $i < strlen($query); $i++ )
    {
      $chr = $query{$i};
      $prev = ( $i > 0 ) ? $query{ $i - 1 } : '';
      $next = ( ( $i + 1 ) < strlen($query) ) ? $query{ $i + 1 } : '';
      
      if ( ( $chr == ' ' && !$in_quote ) || ( $i + 1 == strlen ( $query ) ) )
      {
        $len = ( $next == '' ) ? $i + 1 : $i - $start_term;
        $word = substr ( $query, $start_term, $len );
        $terms[] = $word;
        $start_term = $i + 1;
      }
      
      elseif ( $chr == '"' && $in_quote && $prev != '\\' )
      {
        $word = substr ( $query, $start_term, $i - $start_term + 1 );
        $start_pos = ( $next == ' ' ) ? $i + 2 : $i + 1;
        $in_quote = false;
      }
      
      elseif ( $chr == '"' && !$in_quote )
      {
        $in_quote = true;
        $start_pos = $i;
      }
      
    }
    
    $ticker = 0;
    
    foreach ( $terms as $element => $__unused )
    {
      $atom =& $terms[$element];
      
      $ticker++;
      
      if ( $ticker == 20 )
      {
        $this->warn('Some of your search terms were excluded because searches are limited to 20 terms to prevent excessive server load.');
        break;
      }
      
      if ( substr ( $atom, 0, 2 ) == '+"' && substr ( $atom, ( strlen ( $atom ) - 1 ), 1 ) == '"' )
      {
        $word = substr ( $atom, 2, ( strlen( $atom ) - 3 ) );
        if ( strlen ( $word ) < 4 )
        {
          $this->warn('One or more of your search terms was excluded because terms must be at least 4 characters in length.');
          $ticker--;
          continue;
        }
        if(in_array($word, $ret['req']))
        {
          $this->warn('One or more of your search terms was excluded because duplicate terms were encountered.');
          $ticker--;
          continue;
        }
        $ret['req'][] = $word;
      }
      elseif ( substr ( $atom, 0, 2 ) == '-"' && substr ( $atom, ( strlen ( $atom ) - 1 ), 1 ) == '"' )
      {
        $word = substr ( $atom, 2, ( strlen( $atom ) - 3 ) );
        if ( strlen ( $word ) < 4 )
        {
          $this->warn('One or more of your search terms was excluded because terms must be at least 4 characters in length.');
          $ticker--;
          continue;
        }
        if(in_array($word, $ret['not']))
        {
          $this->warn('One or more of your search terms was excluded because duplicate terms were encountered.');
          $ticker--;
          continue;
        }
        $ret['not'][] = $word;
      }
      elseif ( substr ( $atom, 0, 1 ) == '+' )
      {
        $word = substr ( $atom, 1 );
        if ( strlen ( $word ) < 4 )
        {
          $this->warn('One or more of your search terms was excluded because terms must be at least 4 characters in length.');
          $ticker--;
          continue;
        }
        if(in_array($word, $ret['req']))
        {
          $this->warn('One or more of your search terms was excluded because duplicate terms were encountered.');
          $ticker--;
          continue;
        }
        $ret['req'][] = $word;
      }
      elseif ( substr ( $atom, 0, 1 ) == '-' )
      {
        $word = substr ( $atom, 1 );
        if ( strlen ( $word ) < 4 )
        {
          $this->warn('One or more of your search terms was excluded because terms must be at least 4 characters in length.');
          $ticker--;
          continue;
        }
        if(in_array($word, $ret['not']))
        {
          $this->warn('One or more of your search terms was excluded because duplicate terms were encountered.');
          $ticker--;
          continue;
        }
        $ret['not'][] = $word;
      }
      elseif ( substr ( $atom, 0, 1 ) == '"' && substr ( $atom, ( strlen($atom) - 1 ), 1 ) == '"' )
      {
        $word = substr ( $atom, 1, ( strlen ( $atom ) - 2 ) );
        if ( strlen ( $word ) < 4 )
        {
          $this->warn('One or more of your search terms was excluded because terms must be at least 4 characters in length.');
          $ticker--;
          continue;
        }
        if(in_array($word, $ret['any']))
        {
          $this->warn('One or more of your search terms was excluded because duplicate terms were encountered.');
          $ticker--;
          continue;
        }
        $ret['any'][] = $word;
      }
      else
      {
        $word = $atom;
        if ( strlen ( $word ) < 4 )
        {
          $this->warn('One or more of your search terms was excluded because terms must be at least 4 characters in length.');
          $ticker--;
          continue;
        }
        if(in_array($word, $ret['any']))
        {
          $this->warn('One or more of your search terms was excluded because duplicate terms were encountered.');
          $ticker--;
          continue;
        }
        $ret['any'][] = $word;
      }
    }
    return $ret;
  }
  
  function highlightResults($query, $starttag = '<b>', $endtag = '</b>')
  {
    $query['trm'] = array_merge($query['any'], $query['req']);
    //die('<pre>'.print_r($query, true).'</pre>');
    foreach($query['trm'] as $q)
    {
      foreach($this->results as $k => $r)
      {
        $startplace = 0;
        //$this->results[$k] = htmlspecialchars($this->results[$k]);
        for($i = 0; $i < strlen($r); $i++)
        {
          $word = substr($r, $i, strlen($q));
          if($this->convertCase($word) == $this->convertCase($q))
          {
            $word = $starttag . $word . $endtag;
            $this->results[$k] = substr($r, 0, $i) . $word . substr($r, $i + strlen($q), strlen($r)+999999);
            $startplace = $i - 75;
            if($startplace < 0) $startplace = 0;
            $this->results[$k] = '...'.trim(substr($this->results[$k], $startplace, strlen($word) + 150)).'...';
            continue 2;
          }
        }
      }
    }
  }
  
}

/**
 * Developer-friendly way to do searches. :-) Uses the MySQL FULLTEXT index type.
 * @package Enano
 * @subpackage Search
 */

class MySQL_Fulltext_Search {
  
  /**
   * Performs a search.
   * @param string The search query
   * @return resource MySQL result resource - this is an UNBUFFERED query.
   */
  
  function search($query)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $fulltext_col = 'MATCH(t.page_id,t.namespace,p.name,t.page_text) AGAINST (\'' . $db->escape($query) . '\' IN BOOLEAN MODE)';
    $sql = "SELECT t.page_text,CONCAT('ns=',t.namespace,';pid=',t.page_id) AS page_identifier, $fulltext_col AS score, CHAR_LENGTH(t.page_text) AS length FROM ".table_prefix."page_text AS t
              LEFT JOIN ".table_prefix."pages AS p
                ON ( p.urlname=t.page_id AND p.namespace=t.namespace)
              WHERE $fulltext_col > 0
                AND p.visible=1
              ORDER BY score DESC;";
    $q = $db->sql_unbuffered_query($sql);
    if ( !$q )
      $db->_die();
    
    return $q;
  }
  
  function highlight_result($query, $result)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    $parsed_query = $search->parseQuery($query);
    return $this->highlight_result_inner($query, $result);
  }
  
  function highlight_result_inner($query, $fulltext, $starttag = '<b>', $endtag = '</b>')
  {
    $result = false;
    $query['trm'] = array_merge($query['any'], $query['req']);
    //die('<pre>'.print_r($query, true).'</pre>');
    foreach($query['trm'] as $q)
    {
      $startplace = 0;
      //$this->results[$k] = htmlspecialchars($this->results[$k]);
      for($i = 0; $i < strlen($r); $i++)
      {
        $word = substr($r, $i, strlen($q));
        if($this->convertCase($word) == $this->convertCase($q))
        {
          $word = $starttag . $word . $endtag;
          $result = substr($fulltext, 0, $i) . $word . substr($r, $i + strlen($q), strlen($r)+99999999);
          $startplace = $i - 75;
          if($startplace < 0) $startplace = 0;
          $result = '...'.trim(substr($result, $startplace, strlen($word) + 150)).'...';
          continue 2;
        }
      }
    }
    return $result;
  }
  
}

?>

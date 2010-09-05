<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * search.php - algorithm used to search pages
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * In Enano versions prior to 1.0.2, this class provided a search function that was keyword-based and allowed boolean searches. It was
 * cut from Coblynau and replaced with perform_search(), later in this file, because of speed issues. Now mostly deprecated. The only
 * thing remaining is the buildIndex function, which is still used by the path manager and the new search framework.
 *
 * @package Enano
 * @subpackage Page management frontend
 * @license GNU General Public License <http://enanocms.org/Special:GNU_General_Public_License>
 */

class Searcher
{

	var $results;
	var $index;
	var $warnings;
	var $match_case = false;

	function buildIndex($texts)
	{
		$this->index = Array();
		$stopwords = get_stopwords();

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
				if(strlen($w) < 2 || in_array($w, $stopwords) || strlen($w) > 63 || preg_match('/[\']{2,}/', $w))
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
}

/**
 * Searches the site for the specified string and returns an array with each value being an array filled with the following:
 *   page_id: string, self-explanatory
 *   namespace: string, self-explanatory
 *   page_length: integer, the length of the full page in bytes
 *   page_text: string, the contents of the page (trimmed to ~150 bytes if necessary)
 *   score: numerical relevance score, 1-100, rounded to 2 digits and calculated based on which terms were present and which were not
 * @param string Search query
 * @param string|reference Will be filled with any warnings encountered whilst parsing the query
 * @param bool Case sensitivity - defaults to false
 * @param array|reference Will be filled with the parsed list of words.
 * @return array
 */

function perform_search($query, &$warnings, $case_sensitive = false, &$word_list)
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	global $lang;
	
	$warnings = array();
	
	//
	// STAGE 0: PARSE SEARCH QUERY
	// Identify all terms of the query. Separate between what is required and what is not, and what should be sent through the index as
	// opposed to straight-out LIKE-selected.
	//

	$query = parse_search_query($query, $warnings);

	// Segregate search terms containing spaces
	$query_phrase = array(
		'any' => array(),
		'req' => array()
		);

	foreach ( $query['any'] as $i => $_ )
	{
		$term =& $query['any'][$i];
		$term = trim($term);
		// the indexer only indexes words a-z with apostrophes
		if ( preg_match('/[^A-Za-z\']/', $term) )
		{
			$query_phrase['any'][] = $term;
			unset($term, $query['any'][$i]);
		}
	}
	unset($term);
	$query['any'] = array_values($query['any']);

	foreach ( $query['req'] as $i => $_ )
	{
		$term =& $query['req'][$i];
		$term = trim($term);
		if ( preg_match('/[^A-Za-z\']/', $term) )
		{
			$query_phrase['req'][] = $term;
			unset($term, $query['req'][$i]);
		}
	}
	unset($term);
	$query['req'] = array_values($query['req']);

	$results = array();
	$scores = array();
	$ns_list = '(' . implode('|', array_keys($paths->nslist)) . ')';

	// FIXME: Update to use FULLTEXT algo when available.

	// Build an SQL query to load from the index table
	if ( count($query['any']) < 1 && count($query['req']) < 1 && count($query_phrase['any']) < 1 && count($query_phrase['req']) < 1 )
	{
		// This is both because of technical restrictions and devastation that would occur on shared servers/large sites.
		$warnings[] = $lang->get('search_err_query_no_positive');
		return array();
	}

	//
	// STAGE 1
	// Get all possible result pages from the search index. Tally which pages have the most words, and later sort them by boolean relevance
	//

	// Skip this if no indexable words are included

	if ( count($query['any']) > 0 || count($query['req']) > 0 )
	{
		$where_any = array();
		foreach ( $query['any'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_any[] = $term;
		}
		foreach ( $query['req'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_any[] = $term;
		}

		$col_word = ( $case_sensitive ) ? 'word' : 'word_lcase';
		$where_any_str = ( count($where_any) > 0 ) ? '( ' . $col_word . ' LIKE \'%' . implode('%\' OR ' . $col_word . ' LIKE \'%', $where_any) . '%\' )' : '';

		// generate query
		$sql = "SELECT word, page_names FROM " . table_prefix . "search_index WHERE {$where_any_str}";
		if ( !($q = $db->sql_query($sql)) )
			$db->_die('Error is in perform_search(), includes/search.php, query 1');

		$word_tracking = array();
		if ( $row = $db->fetchrow($q) )
		{
			do
			{
				// get page list
				$pages =& $row['page_names'];
					
				// Find page IDs that contain commas
				// This should never happen because commas are escaped by sanitize_page_id(). Nevertheless for compatibility with older
				// databases, and to alleviate the concerns of hackers, we'll accommodate for page IDs with commas here by checking for
				// IDs that don't match the pattern for stringified page ID + namespace. If it doesn't match, that means it's a continuation
				// of the previous ID and should be concatenated to the previous entry.
				$matches = strpos($pages, ',') ? explode(',', $pages) : array($pages);
				$prev = false;
				foreach ( $matches as $i => $_ )
				{
					$match =& $matches[$i];
					if ( !preg_match("/^ns=$ns_list;pid=(.+)$/", $match) && $prev )
					{
						$matches[$prev] .= ',' . $match;
						unset($match, $matches[$i]);
						continue;
					}
					$prev = $i;
				}
				unset($match);

				// Iterate through each of the results, assigning scores based on how many times the page has shown up.
				// This works because this phase of the search is strongly word-based not page-based. If a page shows up
				// multiple times while fetching the result rows from the search_index table, it simply means that page
				// contains more than one of the terms the user searched for.

				foreach ( $matches as $match )
				{
					$word_cs = (( $case_sensitive ) ? $row['word'] : strtolower($row['word']));
					if ( isset($word_tracking[$match]) && in_array($word_cs, $word_tracking[$match]) )
					{
						continue;
					}
					if ( isset($word_tracking[$match]) )
					{
						if ( isset($word_tracking[$match]) )
						{
							$word_tracking[$match][] = $word_cs;
						}
					}
					else
					{
						$word_tracking[$match] = array($word_cs);
					}
					
					// echo '<pre>' . print_r($word_tracking, true) . '</pre>';
					
					$inc = 1;

					// Is this search term present in the page's title? If so, give extra points
					preg_match("/^ns=$ns_list;pid=(.+)$/", $match, $piecesparts);
					$title = get_page_title_ns($piecesparts[2], $piecesparts[1]);
					
					$test_func = ( $case_sensitive ) ? 'strstr' : 'stristr';
					if ( $test_func($title, $row['word']) || $test_func($piecesparts[2], $row['word']) )
					{
						$inc = 1.5;
					}
					
					// increase points if 2 or more words match a phrase in the title
					for ( $i = 0; $i < count($where_any) - 1; $i++ )
					{
						$phrase = "{$where_any[$i]} {$where_any[$i + 1]}";
						if ( $test_func($title, $phrase) )
						{
							$inc *= 1.25;
						}
					}
					
					// Deduct points if there are few similarities between the words
					$lev_array = array();
					foreach ( $where_any as $qword )
					{
						if ( strstr($word_cs, $qword) )
							$lev_array[ $qword ] = levenshtein($qword, $word_cs);
					}
					if ( min($lev_array) > 3 )
					{
						$inc /= array_sum($lev_array) / count($lev_array);
					}
					
					if ( isset($scores[$match]) )
					{
						$scores[$match] = $scores[$match] + $inc;
					}
					else
					{
						$scores[$match] = $inc;
					}
				}
			}
			while ( $row = $db->fetchrow($q) );
		}
		$db->free_result($q);
		
		//
		// STAGE 2: FIRST ELIMINATION ROUND
		// Iterate through the list of required terms. If a given page is not found to have the required term, eliminate it
		//

		foreach ( $query['req'] as $term )
		{
			foreach ( $word_tracking as $i => $page )
			{
				if ( !in_array($term, $page) )
				{
					unset($word_tracking[$i], $scores[$i]);
				}
			}
		}
	}
	
	//
	// STAGE 3: PHRASE SEARCHING
	// Use LIKE to find pages with specified phrases. We can do a super-picky single query without another elimination round because
	// at this stage we can search the full page_text column instead of relying on a word list.
	//

	// We can skip this stage if none of these special terms apply

	$text_col = ( $case_sensitive ) ? 'page_text' : ENANO_SQLFUNC_LOWERCASE . '(page_text)';
	$name_col = ( $case_sensitive ) ? 'name' : ENANO_SQLFUNC_LOWERCASE . '(name)';
	$text_col_join = ( $case_sensitive ) ? 't.page_text' : ENANO_SQLFUNC_LOWERCASE . '(t.page_text)';
	$name_col_join = ( $case_sensitive ) ? 'p.name' : ENANO_SQLFUNC_LOWERCASE . '(p.name)';
		
	$concat_column = ( ENANO_DBLAYER == 'MYSQL' ) ?
		'CONCAT(\'ns=\',t.namespace,\';pid=\',t.page_id)' :
		"'ns=' || t.namespace || ';pid=' || t.page_id";

	if ( count($query_phrase['any']) > 0 || count($query_phrase['req']) > 0 )
	{

		$where_any = array();
		foreach ( $query_phrase['any'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_any[] = "( $text_col LIKE '%$term%' OR $name_col LIKE '%$term%' )";
		}

		$where_any = ( count($where_any) > 0 ) ? implode(" OR\n  ", $where_any) : '';

		// Also do required terms, but use AND to ensure that all required terms are included
		$where_req = array();
		foreach ( $query_phrase['req'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_req[] = "( $text_col LIKE '%$term%' OR $name_col LIKE '%$term%' )";
		}
		$and_clause = ( $where_any != '' ) ? 'AND ' : '';
		$where_req = ( count($where_req) > 0 ) ? "{$and_clause}" . implode(" AND\n  ", $where_req) : '';

		$sql = 'SELECT ' . $concat_column . ' AS id, p.name, t.page_text FROM ' . table_prefix . "page_text AS t\n"
						. "  LEFT JOIN " . table_prefix . "pages AS p\n"
						. "    ON ( p.urlname = t.page_id AND p.namespace = t.namespace )\n"
						. "  WHERE p.visible = 1 AND (\n    $where_any\n    $where_req\n  );";
		if ( !($q = $db->sql_query($sql)) )
			$db->_die('Error is in perform_search(), includes/search.php, query 2. Parsed query dump follows:<pre>(indexable) ' . htmlspecialchars(print_r($query, true)) . '(non-indexable) ' . htmlspecialchars(print_r($query_phrase, true)) . '</pre>');

		if ( $row = $db->fetchrow() )
		{
			do
			{
				$id =& $row['id'];
				$inc = 0.0;

				$title = $row['name'];
				$test_func = ( $case_sensitive ) ? 'strstr' : 'stristr';
				
				// Is this search term present in the page's title? If so, give extra points
				$word_list = array_merge($query_phrase['any'], $query_phrase['req']);
				foreach ( $word_list as $word )
				{
					if ( $test_func($title, $word) )
						$inc += 1.5;
					else if ( $test_func($row['page_text'], $word) )
						$inc += 1.0;
				}
				
				// increase points if 2 or more words match a phrase in the title
				for ( $i = 0; $i < count($word_list) - 1; $i++ )
				{
					$phrase = "{$word_list[$i]} {$word_list[$i + 1]}";
					if ( $test_func($title, $phrase) )
						$inc *= 1.25;
					else if ( $test_func($row['page_text'], $phrase) )
						$inc *= 1.125;
				}
				
				if ( isset($scores[$id]) )
				{
					$scores[$id] = $scores[$id] + $inc;
				}
				else
				{
					$scores[$id] = $inc;
				}
			}
			while ( $row = $db->fetchrow() );
		}
		$db->free_result();
	}

	//
	// STAGE 4 - SELECT PAGE TEXT AND ELIMINATE NOTS
	// At this point, we have a complete list of all the possible pages. Now we want to obtain the page text, and within the same query
	// eliminate any terms that shouldn't be in there.
	//

	// Generate master word list for the highlighter
	$word_list = array_values(array_merge($query['any'], $query['req'], $query_phrase['any'], $query_phrase['req']));

	$text_where = array();
	foreach ( $scores as $page_id => $_ )
	{
		$text_where[] = $db->escape($page_id);
	}
	$text_where = '( ' . $concat_column . ' = \'' . implode('\' OR ' . $concat_column . ' = \'', $text_where) . '\' )';

	if ( count($query['not']) > 0 )
		$text_where .= ' AND';

	$where_not = array();
	foreach ( $query['not'] as $term )
	{
		$term = escape_string_like($term);
		if ( !$case_sensitive )
			$term = strtolower($term);
		$where_not[] = $term;
	}
	$where_not = ( count($where_not) > 0 ) ? "$text_col NOT LIKE '%" . implode("%' AND $text_col NOT LIKE '%", $where_not) . "%'" : '';

	$sql = 'SELECT ' . $concat_column . ' AS id, t.page_id, t.namespace, CHAR_LENGTH(t.page_text) AS page_length, t.page_text, p.name AS page_name FROM ' . table_prefix . "page_text AS t
						LEFT JOIN " . table_prefix . "pages AS p
							ON ( p.urlname = t.page_id AND p.namespace = t.namespace )
						WHERE p.visible = 1 AND ( $text_where $where_not );";
	if ( !($q = $db->sql_unbuffered_query($sql)) )
		$db->_die('Error is in perform_search(), includes/search.php, query 3');

	$page_data = array();
	if ( $row = $db->fetchrow() )
	{
		do
		{
			$row['page_text'] = htmlspecialchars(strip_tags($row['page_text']));
			$row['page_name'] = htmlspecialchars($row['page_name']);

			// Highlight results (this is wonderfully automated)
			$row['page_text'] = highlight_and_clip_search_result($row['page_text'], $word_list, $case_sensitive);
			if ( strlen($row['page_text']) > 250 && !preg_match('/^\.\.\.(.+)\.\.\.$/', $row['page_text']) )
			{
				$row['page_text'] = substr($row['page_text'], 0, 150) . '...';
			}
			$row['page_name'] = highlight_search_result($row['page_name'], $word_list, $case_sensitive);

			$page_data[$row['id']] = $row;
		}
		while ( $row = $db->fetchrow() );
	}
	$db->free_result();
	
	//
	// STAGE 5 - SPECIAL PAGE TITLE SEARCH
	// Iterate through $paths->pages and check the titles for search terms. Score accordingly.
	//

	foreach ( $paths->pages as $id => $page )
	{
		if ( $page['namespace'] != 'Special' || $page['visible'] == 0 )
			continue;
		$idstring = 'ns=' . $page['namespace'] . ';pid=' . $page['urlname_nons'];
		$any = array_values(array_unique(array_merge($query['any'], $query_phrase['any'])));
		foreach ( $any as $term )
		{
			if ( $case_sensitive )
			{
				if ( strstr($page['name'], $term) || strstr($page['urlname_nons'], $term) )
				{
					( isset($scores[$idstring]) ) ? $scores[$idstring] = $scores[$idstring] + 1.5 : $scores[$idstring] = 1.5;
				}
			}
			else
			{
				if ( stristr($page['name'], $term) || stristr($page['urlname_nons'], $term) )
				{
					( isset($scores[$idstring]) ) ? $scores[$idstring] = $scores[$idstring] + 1.5 : $scores[$idstring] = 1.5;
				}
			}
		}
		if ( isset($scores[$idstring]) )
		{
			$page_data[$idstring] = array(
					'page_name' => highlight_search_result($page['name'], $word_list, $case_sensitive),
					'page_text' => '',
					'page_id' => $page['urlname_nons'],
					'namespace' => $page['namespace'],
					'score' => $scores[$idstring],
					'page_length' => 1,
					'page_note' => '[' . $lang->get('search_result_tag_special') . ']'
				);
		}
	}
	
	//
	// STAGE 6 - SECOND ELIMINATION ROUND
	// Iterate through the list of required terms. If a given page is not found to have the required term, eliminate it
	//

	$required = array_merge($query['req'], $query_phrase['req']);
	foreach ( $required as $term )
	{
		foreach ( $page_data as $id => $page )
		{
			if ( ( $page['namespace'] == 'Special' || ( $page['namespace'] != 'Special' && !strstr($page['page_text'], $term) ) ) && !strstr($page['page_id'], $term) && !strstr($page['page_name'], $term) )
			{
				unset($page_data[$id]);
			}
		}
	}

	// At this point, all of our normal results are in. However, we can also allow plugins to hook into the system and score their own
	// pages and add text, etc. as necessary.
	// Plugins are COMPLETELY responsible for using the search terms and handling Boolean logic properly

	inject_custom_search_results($query, $query_phrase, $scores, $page_data, $case_sensitive, $word_list);
	
	$code = $plugins->setHook('search_global_inner');
	foreach ( $code as $cmd )
	{
		eval($cmd);
	}

	// a marvelous debugging aid :-)
	// die('<pre>' . htmlspecialchars(print_r($page_data, true)) . '</pre>');

	//
	// STAGE 7 - HIGHLIGHT, TRIM, AND SCORE RESULTS
	// We now have the complete results of the search. We need to trim text down to show only portions of the page containing search
	// terms, highlight any search terms within the page, and sort the final results array in descending order of score.
	//

	// Sort scores array
	arsort($scores);

	// Divisor for calculating relevance scores
	$divisor = ( count($query['any']) + count($query_phrase['any']) + count($query['req']) + count($query['not']) ) * 1.5;
	$divisor = max($divisor,
				count($scores) > 0 ? max($scores) : 0);
	
	foreach ( $scores as $page_id => $score )
	{
		if ( !isset($page_data[$page_id]) )
			// It's possible that $scores contains a score for a page that was later eliminated because it contained a disallowed term
			continue;

		// Make a copy of the datum, then delete the original (it frees up a LOT of RAM)
		$datum = $page_data[$page_id];
		unset($page_data[$page_id]);

		// This is an internal value used for sorting - it's no longer needed.
		unset($datum['id']);

		// Calculate score
		// if ( $score > $divisor )
		//   $score = $divisor;
		$datum['score'] = round($score / $divisor, 2) * 100;
		
		// Highlight the URL
		$datum['url_highlight'] = makeUrlComplete($datum['namespace'], $datum['page_id']);
		$datum['url_highlight'] = preg_replace('/\?.+$/', '', $datum['url_highlight']);
		$datum['url_highlight'] = highlight_search_result($datum['url_highlight'], $word_list, $case_sensitive);

		// Store it in our until-now-unused results array
		$results[] = $datum;
	}

	// Our work here is done. :-D
	return $results;
}

/**
 * Parses a search query into an associative array. The resultant array will be filled with the following values, each an array:
 *   any: Search terms that can optionally be present
 *   req: Search terms that must be present
 *   not: Search terms that should not be present
 * @param string Search query
 * @param array Will be filled with parser warnings, such as query too short, words too short, etc.
 * @return array
 */

function parse_search_query($query, &$warnings)
{
	global $lang;
	
	$stopwords = get_stopwords();
	$ret = array(
		'any' => array(),
		'req' => array(),
		'not' => array()
		);
	$warnings = array();
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
			$warnings[] = $lang->get('search_err_query_too_many_terms');
			break;
		}

		if ( substr ( $atom, 0, 2 ) == '+"' && substr ( $atom, ( strlen ( $atom ) - 1 ), 1 ) == '"' )
		{
			$word = substr ( $atom, 2, ( strlen( $atom ) - 3 ) );
			if ( strlen ( $word ) < 2 || in_array($word, $stopwords) )
			{
				$warnings[] = $lang->get('search_err_query_has_stopwords');
				$ticker--;
				continue;
			}
			if(in_array($word, $ret['req']))
			{
				$warnings[] = $lang->get('search_err_query_dup_terms');
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
				$warnings[] = $lang->get('search_err_query_term_too_short');
				$ticker--;
				continue;
			}
			if(in_array($word, $ret['not']))
			{
				$warnings[] = $lang->get('search_err_query_dup_terms');
				$ticker--;
				continue;
			}
			$ret['not'][] = $word;
		}
		elseif ( substr ( $atom, 0, 1 ) == '+' )
		{
			$word = substr ( $atom, 1 );
			if ( strlen ( $word ) < 2 || in_array($word, $stopwords) )
			{
				$warnings[] = $lang->get('search_err_query_has_stopwords');
				$ticker--;
				continue;
			}
			if(in_array($word, $ret['req']))
			{
				$warnings[] = $lang->get('search_err_query_dup_terms');
				$ticker--;
				continue;
			}
			$ret['req'][] = $word;
		}
		elseif ( substr ( $atom, 0, 1 ) == '-' )
		{
			$word = substr ( $atom, 1 );
			if ( strlen ( $word ) < 2 || in_array($word, $stopwords) )
			{
				$warnings[] = $lang->get('search_err_query_has_stopwords');
				$ticker--;
				continue;
			}
			if(in_array($word, $ret['not']))
			{
				$warnings[] = $lang->get('search_err_query_dup_terms');
				$ticker--;
				continue;
			}
			$ret['not'][] = $word;
		}
		elseif ( substr ( $atom, 0, 1 ) == '"' && substr ( $atom, ( strlen($atom) - 1 ), 1 ) == '"' )
		{
			$word = substr ( $atom, 1, ( strlen ( $atom ) - 2 ) );
			if ( strlen ( $word ) < 2 || in_array($word, $stopwords) )
			{
				$warnings[] = $lang->get('search_err_query_has_stopwords');
				$ticker--;
				continue;
			}
			if(in_array($word, $ret['any']))
			{
				$warnings[] = $lang->get('search_err_query_dup_terms');
				$ticker--;
				continue;
			}
			$ret['any'][] = $word;
		}
		else
		{
			$word = $atom;
			if ( strlen ( $word ) < 2 || in_array($word, $stopwords) )
			{
				$warnings[] = $lang->get('search_err_query_has_stopwords');
				$ticker--;
				continue;
			}
			if(in_array($word, $ret['any']))
			{
				$warnings[] = $lang->get('search_err_query_dup_terms');
				$ticker--;
				continue;
			}
			$ret['any'][] = $word;
		}
	}
	return $ret;
}

/**
 * Escapes a string for use in a LIKE clause.
 * @param string
 * @return string
 */

function escape_string_like($string)
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	$string = $db->escape($string);
	$string = str_replace(array('%', '_'), array('\%', '\_'), $string);
	return $string;
}

/**
 * Wraps <highlight></highlight> tags around all words in both the specified array. Does not perform any clipping.
 * @param string Text to process
 * @param array Word list
 * @param bool If true, searches case-sensitively when highlighting words
 * @return string
 */

function highlight_search_result($pt, $words, $case_sensitive = false)
{
	$words2 = array();
	for ( $i = 0; $i < sizeof($words); $i++)
	{
		if(!empty($words[$i]))
			$words2[] = preg_quote($words[$i]);
	}

	$flag = ( $case_sensitive ) ? '' : 'i';
	$regex = '/(' . implode('|', str_replace('/', '\\/', $words2)) . ')/' . $flag;
	$pt = preg_replace($regex, '<highlight>\\1</highlight>', $pt);

	return $pt;
}

/**
 * Wraps <highlight></highlight> tags around all words in both the specified array and the specified text and clips the text to
 * an appropriate length.
 * @param string Text to process
 * @param array Word list
 * @param bool If true, searches case-sensitively when highlighting words
 * @return string
 */

function highlight_and_clip_search_result($pt, $words, $case_sensitive = false)
{
	$cut_off = false;

	$space_chars = Array("\t", "\n", "\r", " ");

	$pt = highlight_search_result($pt, $words, $case_sensitive);

	foreach ( $words as $word )
	{
		// Boldface searched words
		$ptlen = strlen($pt);
		for ( $i = 0; $i < $ptlen; $i++ )
		{
			$len = strlen($word);
			if ( strtolower(substr($pt, $i, $len)) == strtolower($word) )
			{
				$chunk1 = substr($pt, 0, $i);
				$chunk2 = substr($pt, $i, $len);
				$chunk3 = substr($pt, ( $i + $len ));
				$pt = $chunk1 . $chunk2 . $chunk3;
				$ptlen = strlen($pt);
				// Cut off text to 150 chars or so
				if ( !$cut_off )
				{
					$cut_off = true;
					if ( $i - 75 > 0 )
					{
						// Navigate backwards until a space character is found
						$chunk = substr($pt, 0, ( $i - 75 ));
						$final_chunk = $chunk;
						for ( $j = strlen($chunk) - 1; $j > 0; $j = $j - 1 )
						{
							if ( in_array($chunk{$j}, $space_chars) )
							{
								$final_chunk = substr($chunk, $j + 1);
								break;
							}
						}
						$mid_chunk = substr($pt, ( $i - 75 ), 75);

						$clipped = '...' . $final_chunk . $mid_chunk . $chunk2;

						$chunk = substr($pt, ( $i + strlen($chunk2) + 75 ));
						$final_chunk = $chunk;
						for ( $j = 0; $j < strlen($chunk); $j++ )
						{
							if ( in_array($chunk{$j}, $space_chars) )
							{
								$final_chunk = substr($chunk, 0, $j);
								break;
							}
						}

						$end_chunk = substr($pt, ( $i + strlen($chunk2) ), 75 );

						$clipped .= $end_chunk . $final_chunk . '...';

						$pt = $clipped;
					}
					else if ( strlen($pt) > 200 )
					{
						$mid_chunk = substr($pt, ( $i - 75 ), 75);

						$clipped = $chunk1 . $chunk2;

						$chunk = substr($pt, ( $i + strlen($chunk2) + 75 ));
						$final_chunk = $chunk;
						for ( $j = 0; $j < strlen($chunk); $j++ )
						{
							if ( in_array($chunk{$j}, $space_chars) )
							{
								$final_chunk = substr($chunk, 0, $j);
								break;
							}
						}

						$end_chunk = substr($pt, ( $i + strlen($chunk2) ), 75 );

						$clipped .= $end_chunk . $final_chunk . '...';

						$pt = $clipped;

					}
					break 2;
				}
			}
		}
		$cut_off = false;
	}
	return $pt;
}

/**
 * Returns a list of words that shouldn't under most circumstances be indexed for searching.
 * @return array
 */

function get_stopwords()
{
	static $stopwords;
	if ( is_array($stopwords) )
		return $stopwords;

	$stopwords = array('I', 'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'in', 'is', 'it',
 										'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'and',
 										'the');
	
	return $stopwords;
}

/**
 * Private function to inject custom results into a search.
 */

function inject_custom_search_results(&$query, &$query_phrase, &$scores, &$page_data, &$case_sensitive, &$word_list)
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	global $lang;
	
	global $search_handlers;
	
	// global functions
	$terms = array(
			'any' => array_merge($query['any'], $query_phrase['any']),
			'req' => array_merge($query['req'], $query_phrase['req']),
			'not' => $query['not']
		);
	
	foreach ( $search_handlers as &$options )
	{
		$where = array('any' => array(), 'req' => array(), 'not' => array());
		$where_any =& $where['any'];
		$where_req =& $where['req'];
		$where_not =& $where['not'];
		$title_col = ( $case_sensitive ) ? $options['titlecolumn'] : ENANO_SQLFUNC_LOWERCASE . '(' . $options['titlecolumn'] . ')';
		if ( isset($options['datacolumn']) )
			$desc_col = ( $case_sensitive ) ? $options['datacolumn'] : ENANO_SQLFUNC_LOWERCASE . '(' . $options['datacolumn'] . ')';
		else
			$desc_col = "''";
		foreach ( $terms['any'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_any[] = "( $title_col LIKE '%{$term}%' OR $desc_col LIKE '%{$term}%' )";
		}
		foreach ( $terms['req'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_req[] = "( $title_col LIKE '%{$term}%' OR $desc_col LIKE '%{$term}%' )";
		}
		foreach ( $terms['not'] as $term )
		{
			$term = escape_string_like($term);
			if ( !$case_sensitive )
				$term = strtolower($term);
			$where_not[] = "$title_col NOT LIKE '%{$term}%' AND $desc_col NOT LIKE '%{$term}%'";
		}
		if ( empty($where_any) )
			unset($where_any, $where['any']);
		if ( empty($where_req) )
			unset($where_req, $where['req']);
		if ( empty($where_not) )
			unset($where_not, $where['not']);
		
		$where_any = '(' . implode(' OR ', $where_any) . '' . ( isset($where['req']) || isset($where['not']) ? ' OR 1 = 1' : '' ) . ')';
		
		if ( isset($where_req) )
			$where_req = implode(' AND ', $where_req);
		if ( isset($where_not) )
		$where_not = implode( 'AND ', $where_not);
		
		$where = implode(' AND ', $where);
		
		$columns = $options['titlecolumn'];
		if ( isset($options['datacolumn']) )
			$columns .= ", {$options['datacolumn']}";
		if ( isset($options['additionalcolumns']) )
			$columns .= ', ' . implode(', ', $options['additionalcolumns']);
		
		$additionalwhere = ( isset($options['additionalwhere']) ) ? $options['additionalwhere'] : '';
		
		$sql = "SELECT $columns FROM " . table_prefix . "{$options['table']} WHERE ( $where ) $additionalwhere;";
	
		if ( !($q = $db->sql_unbuffered_query($sql)) )
		{
			$db->_die('Automatically generated search query');
		}
		
		if ( $row = $db->fetchrow() )
		{
			do
			{
				$parser = $template->makeParserText($options['uniqueid']);
				$parser->assign_vars($row);
				$idstring = $parser->run();
				
				// Score this result
				foreach ( $word_list as $term )
				{
					if ( $case_sensitive )
					{
						if ( strstr($row[$options['titlecolumn']], $term) )
						{
							( isset($scores[$idstring]) ) ? $scores[$idstring] += 1.5 : $scores[$idstring] = 1.5;
						}
						else if ( isset($options['datacolumn']) && strstr($row[$options['datacolumn']], $term) )
						{
							( isset($scores[$idstring]) ) ? $scores[$idstring]++ : $scores[$idstring] = 1;
						}
					}
					else
					{
						if ( stristr($row[$options['titlecolumn']], $term) )
						{
							( isset($scores[$idstring]) ) ? $scores[$idstring] += 1.5 : $scores[$idstring] = 1.5;
						}
						else if ( isset($options['datacolumn']) && stristr($row[$options['datacolumn']], $term) )
						{
							( isset($scores[$idstring]) ) ? $scores[$idstring]++ : $scores[$idstring] = 1;
						}
					}
				}
				// Generate text...
				$text = '';
				if ( isset($options['datacolumn']) && !isset($options['formatcallback']) )
				{
					$text = highlight_and_clip_search_result(htmlspecialchars($row[$options['datacolumn']]), $word_list);
				}
				else if ( isset($options['formatcallback']) )
				{
					if ( is_callable($options['formatcallback']) )
					{
						$text = call_user_func($options['formatcallback'], $row, $word_list);
					}
					else
					{
						$parser = $template->makeParserText($options['formatcallback']);
						$parser->assign_vars($row);
						$text = $parser->run();
					}
				}
				
				// Inject result
				
				if ( isset($scores[$idstring]) )
				{
					$parser = $template->makeParserText($options['linkformat']['page_id']);
					$parser->assign_vars($row);
					$page_id = $parser->run();
					
					$parser = $template->makeParserText($options['linkformat']['namespace']);
					$parser->assign_vars($row);
					$namespace = $parser->run();
					
					$page_data[$idstring] = array(
						'page_name' => highlight_search_result(htmlspecialchars($row[$options['titlecolumn']]), $word_list),
						'page_text' => $text,
						'score' => $scores[$idstring],
						'page_id' => $page_id,
						'namespace' => $namespace,
					);
					
					// Any additional flags that need to be added to the result?
					// The small usually-bracketed text to the left of the title
					if ( isset($options['resultnote']) )
					{
						$page_data[$idstring]['page_note'] = $options['resultnote'];
					}
					// Should we include the length?
					if ( isset($options['datacolumn']) )
					{
						$page_data[$idstring]['page_length'] = strlen($row[$options['datacolumn']]);
					}
					else
					{
						$page_data[$idstring]['page_length'] = 0;
						$page_data[$idstring]['zero_length'] = true;
					}
					// Anything to append to result links?
					if ( isset($options['linkformat']['append']) )
					{
						$page_data[$idstring]['url_append'] = $options['linkformat']['append'];
					}
				}
			}
			while ( $row = $db->fetchrow($q) );
			$db->free_result($q);
		}
	}
}

?>

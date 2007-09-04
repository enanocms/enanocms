<?php
/*
Plugin Name: Search UI/frontend
Plugin URI: http://enanocms.org/
Description: Provides the page Special:Search, which is a frontend to the Enano search engine.
Author: Dan Fuhry
Version: 1.0.1
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 2
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

$plugins->attachHook('base_classes_initted', '
  global $paths;
    $paths->add_page(Array(
      \'name\'=>\'Rebuild search index\',
      \'urlname\'=>\'SearchRebuild\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Search\',
      \'urlname\'=>\'Search\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    ');

function page_Special_SearchRebuild()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!$session->get_permissions('mod_misc')) die_friendly('Unauthorized', '<p>You need to be an administrator to rebuild the search index</p>');
  $template->header();
  if($paths->rebuild_search_index())
    echo '<p>Index rebuilt!</p>';
  else
    echo '<p>Index was not rebuilt due to an error.';
  $template->footer();
}

function page_Special_Search()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!$q = $paths->getParam(0)) $q = ( isset($_GET['q']) ) ? $_GET['q'] : false;
  if(isset($_GET['words_any']))
  {
    $q = '';
    if(!empty($_GET['words_any']))
    {
      $q .= $_GET['words_any'] . ' ';
    }
    if(!empty($_GET['exact_phrase']))
    {
      $q .= '"' . $_GET['exact_phrase'] . '" ';
    }
    if(!empty($_GET['exclude_words']))
    {
      $not = explode(' ', $_GET['exclude_words']);
      foreach ( $not as $i => $foo )
      {
        $not[$i] = '-' . $not[$i];
      }
      $q .= implode(' ', $not) . ' ';
    }
    if(!empty($_GET['require_words']))
    {
      $req = explode(' ', $_GET['require_words']);
      foreach ( $req as $i => $foo )
      {
        $req[$i] = '+' . $req[$i];
      }
      $q .= implode(' ', $req) . ' ';
    }
  }
  $q = trim($q);
  
  if ( !empty($q) && !isset($_GET['search']) )
  {
    list($pid, $ns) = RenderMan::strToPageID($q);
    $pid = sanitize_page_id($pid);
    $key = $paths->nslist[$ns] . $pid;
    if ( isPage($key) )
    {
      redirect(makeUrl($key), 'Results', 'found page', 0);
    }
  }
  
  $template->header();
  if(!empty($q))
  {
    // See if any pages directly match the title
          
    for ( $i = 0; $i < count ( $paths->pages ) / 2; $i++ )
    {
      $pg =& $paths->pages[$i];
      $q_lc = strtolower( str_replace(' ', '_', $q) );
      $q_tl = strtolower( str_replace('_', ' ', $q) );
      $p_lc = strtolower($pg['urlname']);
      $p_tl = strtolower($pg['name']);
      if ( strstr($p_tl, $q_tl) || strstr($p_lc, $q_lc) && $pg['visible'] == 1 )
      {
        echo '<div class="usermessage">Perhaps you were looking for <b><a href="' . makeUrl($pg['urlname'], false, true) . '">' . htmlspecialchars($pg['name']) . '</a></b>?</div>';
        break;
      }
    }
          
    switch(SEARCH_MODE)
    {
      
      case "FULLTEXT":
        if ( isset($_GET['offset']) )
        {
          $offset = intval($_GET['offset']);
        }
        else
        {
          $offset = 0;
        }
        $sql = $db->sql_query('SELECT search_id FROM '.table_prefix.'search_cache WHERE query=\''.$db->escape($q).'\';');
        if(!$sql)
        {
          $db->_die('Error scanning search query cache');
        }
        if($db->numrows() > 0)
        {
          $row = $db->fetchrow();
          $db->free_result();
          search_fetch_fulltext_results(intval($row['search_id']), $offset);
        }
        else
        {
          // Perform search
          
          $search = new MySQL_Fulltext_Search();
          
          // Parse the query
          $parse = new Searcher();
          $query = $parse->parseQuery($q);
          unset($parse);
          
          // Send query to MySQL
          $sql = $search->search($q);
          $results = Array();
          if ( $row = $db->fetchrow($sql) )
          {
            do {
              $results[] = $row;
            } while ( $row = $db->fetchrow($sql) );
          }
          else
          {
            // echo '<div class="warning-box">No pages that matched your search criteria could be found.</div>';
          }
          $texts = Array();
          foreach ( $results as $result )
          {
            $texts[] = render_fulltext_result($result, $query);
          }
          
          // Store the result in the search cache...if someone makes the same query later we can skip searching and rendering
          // This cache is cleared when an affected page is saved.
          
          $results = serialize($texts);
          
          $sql = $db->sql_query('INSERT INTO '.table_prefix.'search_cache(search_time,query,results) VALUES('.time().', \''.$db->escape($q).'\', \''.$db->escape($results).'\');');
          if($sql)
          {
            search_render_fulltext_results(unserialize($results), $offset, $q);
          }
          else
          {
            $db->_die('Error inserting search into cache');
          }
          
        }
        break;

      case "BUILTIN":
        $titles = $paths->makeTitleSearcher(isset($_GET['match_case']));
        if ( isset($_GET['offset']) )
        {
          $offset = intval($_GET['offset']);
        }
        else
        {
          $offset = 0;
        }
        $sql = $db->sql_query('SELECT search_id FROM '.table_prefix.'search_cache WHERE query=\''.$db->escape($q).'\';');
        if(!$sql)
        {
          $db->_die('Error scanning search query cache');
        }
        if($db->numrows() > 0)
        {
          $row = $db->fetchrow();
          $db->free_result();
          search_show_results(intval($row['search_id']), $offset);
        }
        else
        {
          $titles->search($q, $paths->get_page_titles());
          $search = $paths->makeSearcher(isset($_GET['match_case']));
          $texts = $paths->fetch_page_search_resource();
          $search->searchMySQL($q, $texts);
          
          $results = Array();
          $results['text'] = $search->results;
          $results['page'] = $titles->results;
          $results['warn'] = $search->warnings;
          
          $results = serialize($results);
          
          $sql = $db->sql_query('INSERT INTO '.table_prefix.'search_cache(search_time,query,results) VALUES('.time().', \''.$db->escape($q).'\', \''.$db->escape($results).'\');');
          if($sql)
          {
            search_render_results(unserialize($results), $offset, $q);
          }
          else
          {
            $db->_die('Error inserting search into cache');
          }
        }
        break;
    }
    $code = $plugins->setHook('search_results'); // , Array('query'=>$q));
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    ?>
    <form action="<?php echo makeUrl($paths->page); ?>" method="get">
      <p>
        <?php if ( $session->sid_super ): ?>
          <input type="hidden" name="auth" value="<?php echo $session->sid_super; ?>" />
        <?php endif; ?>
        <?php if ( urlSeparator == '&' ): ?>
          <input type="hidden" name="title" value="<?php echo $paths->nslist['Special'] . 'Search'; ?>" />
        <?php endif; ?>
        <input type="text" name="q" size="40" value="<?php echo htmlspecialchars( $q ); ?>" /> <input type="submit" value="Go" style="font-weight: bold;" /> <input name="search" type="submit" value="Search" />  <small><a href="<?php echo makeUrlNS('Special', 'Search'); ?>">Advanced Search</a></small>
      </p>
    </form>
    <?php
  }
  else
  {
  ?>
    <br />
    <form action="<?php echo makeUrl($paths->page); ?>" method="get">
      <?php if ( urlSeparator == '&' ): ?>
        <input type="hidden" name="title" value="<?php echo $paths->nslist['Special'] . 'Search'; ?>" />
      <?php endif; ?>
      <div class="tblholder">
        <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Advanced Search</th></tr>
          <tr>
            <td class="row1">Search for pages with <b>any of these words</b>:</td>
            <td class="row1"><input type="text" name="words_any" size="40" /></td>
          </tr>
          <tr>
            <td class="row2">with <b>this exact phrase</b>:</td>
            <td class="row2"><input type="text" name="exact_phrase" size="40" /></td>
          </tr>
          <tr>
            <td class="row1">with <b>none of these words</b>:</td>
            <td class="row1"><input type="text" name="exclude_words" size="40" /></td>
          </tr>
          <tr>
            <td class="row2">with <b>all of these words</b>:</td>
            <td class="row2"><input type="text" name="require_words" size="40" /></td>
          </tr>
          <tr>
            <td class="row1">
              <label for="chk_case">Case-sensitive search:</label>
            </td>
            <td class="row1">
              <input type="checkbox" name="match_case" id="chk_case" />
            </td>
          </tr>
          <tr>
            <th colspan="2" class="subhead">
              <input type="submit" name="do_search" value="Search" />
            </td>
          </tr>
        </table>
      </div>
    </form>
  <?php
  }
  $template->footer();
}

function search_show_results($search_id, $start = 0)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $q = $db->sql_query('SELECT query,results,search_time FROM '.table_prefix.'search_cache WHERE search_id='.intval($search_id).';');
  if(!$q)
    return $db->get_error('Error selecting cached search results');
  $row = $db->fetchrow();
  $db->free_result();
  $results = unserialize($row['results']);
  search_render_results($results, $start, $row['query']);
}

function search_render_results($results, $start = 0, $q = '')
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $nr1 = sizeof($results['page']);
  $nr2 = sizeof($results['text']);
  $nr  = ( $nr1 > $nr2 ) ? $nr1 : $nr2;
  $results['page'] = array_slice($results['page'], $start, SEARCH_RESULTS_PER_PAGE);
  $results['text'] = array_slice($results['text'], $start, SEARCH_RESULTS_PER_PAGE);
  
  // Pagination
  $pagination = '';
  if ( $nr1 > SEARCH_RESULTS_PER_PAGE || $nr2 > SEARCH_RESULTS_PER_PAGE )
  {
    $pagination .= '<div class="tblholder" style="padding: 0; display: table; margin: 0 0 0 auto; float: right;">
          <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
          <tr>
          <th>Page:</th>';
    $num_pages = ceil($nr / SEARCH_RESULTS_PER_PAGE);
    $j = 0;
    for ( $i = 1; $i <= $num_pages; $i++ ) 
    {
      if ($j == $start)
        $pagination .= '<td class="row1"><b>' . $i . '</b></td>';
      else
        $pagination .= '<td class="row1"><a href="' . makeUrlNS('Special', 'Search', 'q=' . urlencode($q) . '&offset=' . $j, true) . '">' . $i . '</a></td>';
      $j = $j + SEARCH_RESULTS_PER_PAGE;
    }
    $pagination .= '</tr></table></div>';
  }
  
  echo $pagination;
  
  if ( $nr1 >= $start )
  {
    echo '<h3>Page title matches</h3>';
    if(count($results['page']) < 1)
    {
      echo '<div class="error-box">No pages with a title that matched your search criteria could be found.</div>';
    }
    else
    {
      echo '<p>';
      foreach($results['page'] as $page => $text)
      {
        echo '<a href="'.makeUrl($page).'">'.$paths->pages[$page]['name'].'</a><br />';
      }
      echo '</p>';
    }
  }
  if ( $nr2 >= $start )
  {
    echo '<h3>Page text matches</h3>';
    if(count($results['text']) < 1)
    {
      echo '<div class="error-box">No page text that matched your search criteria could be found.</div>';
    }
    else
    {
      foreach($results['text'] as $kpage => $text)
      {
        preg_match('#^ns=('.implode('|', array_keys($paths->nslist)).');pid=(.*?)$#i', $kpage, $matches);
        $page = $paths->nslist[$matches[1]] . $matches[2];
        echo '<p><span style="font-size: larger;"><a href="'.makeUrl($page).'">'.$paths->pages[$page]['name'].'</a></span><br />'.$text.'</p>';
      }
    }
  }
  if(count($results['warn']) > 0)
    echo '<div class="warning-box"><b>Your search may not include all results.</b><br />The following errors were encountered during the search:<br /><ul><li>'.implode('</li><li>', $results['warn']).'</li></ul></div>';
  echo $pagination;
}

function render_fulltext_result($result, $query)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  preg_match('#^ns=('.implode('|', array_keys($paths->nslist)).');pid=(.*?)$#i', $result['page_identifier'], $matches);
  $page = $paths->nslist[$matches[1]] . $matches[2];
  //$score = round($result['score'] * 100, 1);
  $score = number_format($result['score'], 2);
  $char_length = $result['length'];
  $result_template = <<<TPLCODE
  <div class="search-result">
    <h3><a href="{HREF}">{TITLE}</a></h3>
    <p>{TEXT}</p>
    <p>
      <span class="search-result-info">{NAMESPACE} - Relevance score: {SCORE} ({LENGTH} bytes)</span>
    </p>
  </div>
TPLCODE;
  $parser = $template->makeParserText($result_template);
  
  $pt =& $result['page_text'];
  $space_chars = Array("\t", "\n", "\r", " ");
  
  $words = array_merge($query['any'], $query['req']);
  $pt = htmlspecialchars($pt);
  $words2 = array();
  
  for ( $i = 0; $i < sizeof($words); $i++)
  {
    if(!empty($words[$i]))
      $words2[] = preg_quote($words[$i]);
  }
  
  $regex = '/(' . implode('|', $words2) . ')/i';
  $pt = preg_replace($regex, '<span class="search-term">\\1</span>', $pt);
  
  $title = preg_replace($regex, '<span class="title-search-term">\\1</span>', htmlspecialchars($paths->pages[$page]['name']));
  
  $cut_off = false;
  
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
            for ( $j = strlen($chunk); $j > 0; $j = $j - 1 )
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
  
  $parser->assign_vars(Array(
      'TITLE' => $title,
      'TEXT' => $pt,
      'NAMESPACE' => $matches[1],
      'SCORE' => $score,
      'LENGTH' => $char_length,
      'HREF' => makeUrl($page)
    ));
  
  return $parser->run();
  
}

function search_fetch_fulltext_results($search_id, $offset = 0)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $q = $db->sql_query('SELECT query,results,search_time FROM '.table_prefix.'search_cache WHERE search_id='.intval($search_id).';');
  if(!$q)
    return $db->get_error('Error selecting cached search results');
  $row = $db->fetchrow();
  $db->free_result();
  $results = unserialize($row['results']);
  search_render_fulltext_results($results, $offset, $row['query']);
}

function search_render_fulltext_results($results, $offset = 0, $query)
{
  $num_results = sizeof($results);
  $slice = array_slice($results, $offset, SEARCH_RESULTS_PER_PAGE);
  
  if ( $num_results < 1 )
  {
    echo '<div class="warning-box" style="margin-left: 0;">No page text that matched your search criteria could be found.</div>';
    return null;
  }
  
  $html = paginate_array($results, sizeof($results), makeUrlNS('Special', 'Search', 'q=' . urlencode($query) . '&offset=%s'), $offset, 10);
  echo $html . '<br />';
  
}

?>

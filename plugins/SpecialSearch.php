<?php
/*
Plugin Name: Search UI/frontend
Plugin URI: http://enanocms.org/
Description: Provides the page Special:Search, which is a frontend to the Enano search engine.
Author: Dan Fuhry
Version: 1.0.2
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.2
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
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
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
  @set_time_limit(0);
  if($paths->rebuild_search_index(true))
    echo '<p>Index rebuilt!</p>';
  else
    echo '<p>Index was not rebuilt due to an error.';
  $template->footer();
}

function page_Special_Search()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $aggressive_optimize_html;
  $aggressive_optimize_html = false;
  
  if ( !$q = $paths->getParam(0) )
    $q = ( isset($_GET['q']) ) ? $_GET['q'] : '';
  
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
  
  $template->header();
  
  $qin = ( isset($q) ) ? str_replace('"', '\"', htmlspecialchars($q)) : '';
  $search_form = '<form action="' . makeUrlNS('Special', 'Search') . '">
  <input type="text" tabindex="1" name="q" size="50" value="' . $qin . '" />&nbsp;<input tabindex="2" type="submit" value="Search" />&nbsp;<a href="' . makeUrlNS('Special', 'Search') . '">Advanced search</a>
  ' . ( $session->auth_level > USER_LEVEL_MEMBER ? '<input type="hidden" name="auth" value="' . $session->sid_super . '" />' : '' ) . '
  </form>';
  
  if ( !empty($q) )
  {
    $search_start = microtime_float();
    
    $results = perform_search($q, $warn, ( isset($_GET['match_case']) ));
    $warn = array_unique($warn);
    
    if ( file_exists( ENANO_ROOT . '/themes/' . $template->theme . '/search-result.tpl' ) )
    {
      $parser = $template->makeParser('search-result.tpl');
    }
    else
    {
      $tpl_code = <<<LONGSTRING
      
      <!-- Start search result -->
      
      <div class="search-result">
        <p>
         <h3><a href="{RESULT_URL}"><span class="search-result-annotation">{PAGE_NOTE}</span>{PAGE_TITLE}</a></h3>
          {PAGE_TEXT}
          <span class="search-result-url">{PAGE_URL}</span> - 
          <!-- BEGINNOT special_page --><span class="search-result-info">{PAGE_LENGTH} {PAGE_LENGTH_UNIT}</span> -<!-- END special_page --> 
          <span class="search-result-info">Relevance: {RELEVANCE_SCORE}%</span>
        </p>
      </div>
      
      <!-- Finish search result -->
      
LONGSTRING;
      $parser = $template->makeParserText($tpl_code);
    }
    foreach ( $results as $i => $_ )
    {
      $result =& $results[$i];
      $result['page_text'] = str_replace(array('<highlight>', '</highlight>'), array('<span class="search-term">', '</span>'), $result['page_text']);
      if ( !empty($result['page_text']) )
        $result['page_text'] .= '<br />';
      $result['page_name'] = str_replace(array('<highlight>', '</highlight>'), array('<span class="title-search-term">', '</span>'), $result['page_name']);
      if ( $result['page_length'] >= 1048576 )
      {
        $result['page_length'] = round($result['page_length'] / 1048576, 1);
        $length_unit = 'MB';
      }
      else if ( $result['page_length'] >= 1024 )
      {
        $result['page_length'] = round($result['page_length'] / 1024, 1);
        $length_unit = 'KB';
      }
      else
      {
        $length_unit = 'bytes';
      }
      $url = makeUrlComplete($result['namespace'], $result['page_id']);
      $url = preg_replace('/\?.+$/', '', $url);
      $parser->assign_vars(array(
         'PAGE_TITLE' => $result['page_name'],
         'PAGE_TEXT' => $result['page_text'],
         'PAGE_LENGTH' => $result['page_length'],
         'RELEVANCE_SCORE' => $result['score'],
         'RESULT_URL' => makeUrlNS($result['namespace'], $result['page_id'], false, true),
         'PAGE_LENGTH_UNIT' => $length_unit,
         'PAGE_URL' => $url,
         'PAGE_NOTE' => ( isset($result['page_note']) ? $result['page_note'] . ' ' : '' )
        ));
      $has_content = ( $result['namespace'] == 'Special' );
      
      $code = $plugins->setHook('search_global_results');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      
      $parser->assign_bool(array(
          'special_page' => $has_content
        ));
      $result = $parser->run();
    }
    unset($result);
    
    $per_page = 10;
    $start = ( isset($_GET['start']) ? intval($_GET['start']) : 0 );
    $start_string = $start + 1;
    $per_string = $start_string + $per_page - 1;
    $num_results = count($results);
    if ( $per_string > $num_results )
      $per_string = $num_results;
    
    $search_time = microtime_float() - $search_start;
    $search_time = round($search_time, 3);
    
    $q_trim = ( strlen($q) > 30 ) ? substr($q, 0, 27) . '...' : $q;
    $q_trim = htmlspecialchars($q_trim);
    
    $result_string = ( count($results) > 0 ) ? "Results <b>$start_string</b> - <b>$per_string</b> of about <b>$num_results</b> for <b>" . $q_trim . "</b> in {$search_time}s." : 'No results.';
    
    echo '<div class="search-hibar">
            <div style="float: right;">
              ' . $result_string . '
            </div>
            <b>Site search</b>
          </div>
          <div class="search-lobar">
            ' . $search_form . '
          </div>';
          
    if ( count($warn) > 0 )
    {
      echo '<div class="warning-box" style="margin: 10px 0 0 0;">';
      echo '<b>Some problems were encountered during your search.</b><br />
            There was a problem with your search query, and as a result there may be a reduced number of search results.';
      echo '<ul><li>' . implode('</li><li>', $warn) . '</li></ul>';
      echo '</div>';
    }
  
    if ( count($results) > 0 )
    {
      $html = paginate_array(
          $results,
          count($results),
          makeUrlNS('Special', 'Search', 'q=' . str_replace('%', '%%', htmlspecialchars(urlencode($q))) . '&start=%s'),
          $start,
          $per_page
        );
      echo $html;
    }
    else
    {
      // No results for the search
      echo '<h3 style="font-weight: normal;">Your search for <b>"' . htmlspecialchars($q) . '"</b> didn\'t turn up any results.</h3>';
      echo '<p>There are a few things you can try:</p>';
      echo '<ul>
              <li>Were you looking for a specific Special page? Special pages are not searchable. You may want to see a <a href="' . makeUrlNS('Special', 'SpecialPages') . '">list of special pages</a>.</li>
              <li>If you have the appropriate permissions, you can <a href="' . makeUrl($q) . '#do:edit">start the ' . htmlspecialchars($q) . ' page</a>.</li>
              <li>Try using fewer keywords. You can get broader results if you remove quotes from your search query.</li>
              <li>Did your search trigger any warnings? Sometimes a search can be cancelled if there aren\'t any terms in a search query that are 4 characters or greater in length.</li>
            </ul>';
    }
    $code = $plugins->setHook('search_results');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
  }
  else
  {
    ?>
    <form action="<?php echo makeUrl($paths->page); ?>" method="get">
      <?php if ( urlSeparator == '&' ): ?>
        <input type="hidden" name="title" value="<?php echo $paths->nslist['Special'] . 'Search'; ?>" />
      <?php 
      echo ( $session->auth_level > USER_LEVEL_MEMBER ? '<input type="hidden" name="auth" value="' . $session->sid_super . '" />' : '' );
      endif; ?>
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

?>

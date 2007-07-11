<?php
/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 (Banshee)
 * render.php - handles fetching pages and parsing them into HTML
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
class RenderMan {
  
  function strToPageID($string)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $k = array_keys($paths->nslist);
    for($i=0;$i<sizeof($paths->nslist);$i++)
    {
      $ln = strlen($paths->nslist[$k[$i]]);
      if(substr($string, 0, $ln) == $paths->nslist[$k[$i]])
      {
        $ns = $k[$i];
        $pg = substr($string, strlen($paths->nslist[$ns]), strlen($string));
      }
    }
    return Array($pg, $ns);
  }
  
  function getPage($page_id, $namespace, $wiki = 1, $smilies = true, $filter_links = true, $redir = true, $render = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    dc_here('render: page requested<br />ID/namespace: '."$page_id, $namespace<br />Wiki mode: $wiki<br />Smilies: ".(string)$smilies."<br />Allow redirects: ".(string)$redir);
    
    $perms =& $session;
    
    if ( $page_id != $paths->cpage['urlname_nons'] || $namespace != $paths->namespace )
    {
      unset($perms);
      unset($perms); // PHP <5.1.5 Zend bug
      $perms = $session->fetch_page_acl($page_id, $namespace);
    }
    
    if(!$perms->get_permissions('read'))
      return 'Access denied ('.$paths->nslist[$namespace].$page_id.')';
    
    if($wiki == 0 || $render == false)
    {
      if(!$perms->get_permissions('view_source'))
      {
        return 'Access denied ('.$paths->nslist[$namespace].$page_id.')';
      }
    }
    
    $q = $db->sql_query('SELECT page_text,char_tag FROM '.table_prefix.'page_text WHERE page_id=\''.$db->escape($page_id).'\' AND namespace=\''.$db->escape($namespace).'\';');
    if ( !$q )
    {
      $db->_die('Method called was: RenderMan::getPage(\''.$page_id.'\', \''.$namespace.'\');.');
    }
    if ( $db->numrows() < 1 )
    {
      return false;
    }
    $row = $db->fetchrow();
    $db->free_result();
    
    $message = $row['page_text'];
    $chartag = $row['char_tag'];
    unset($row); // Free some memory
    
    if ( preg_match('#^\#redirect \[\[(.+?)\]\]#', $message, $m) && $redir && !isset($_GET['redirect']) || ( isset($_GET['redirect']) && $_GET['redirect'] != 'no' ) )
    {
      dc_here('render: looks like a redirect page to me...');
      $old = $paths->cpage;
      $a = RenderMan::strToPageID($m[1]);
      $a[0] = str_replace(' ', '_', $a[0]);
      
      $pageid = str_replace(' ', '_', $paths->nslist[$a[1]] . $a[0]);
      $paths->page = $pageid;
      $paths->cpage = $paths->pages[$pageid];
      //die('<pre>'.print_r($paths->cpage,true).'</pre>');
      
      dc_here('render: wreckin\' $template, and reloading the theme vars to match the new page<br />This might get messy!');
      
      unset($template);
      unset($GLOBALS['template']);
      
      $GLOBALS['template'] = new template();
      global $template;
      
      $template->template(); // Tear down and rebuild the template parser
      $template->load_theme($session->theme, $session->style);
      
      $data = '<div><small>(Redirected from <a href="'.makeUrlNS($old['namespace'], $old['urlname_nons'], 'redirect=no', true).'">'.$old['name'].'</a>)</small></div>'.RenderMan::getPage($a[0], $a[1], $wiki, $smilies, $filter_links, false /* Enforces a maximum of one redirect */);
      
      return $data;
    }
    else if(preg_match('#^\#redirect \[\[(.+?)\]\]#', $message, $m) && isset($_GET['redirect']) && $_GET['redirect'] == 'no')
    {
      dc_here('render: looks like a redirect page to me...');
      dc_here('render: skipping redirect as requested on URI');
      preg_match('#^\#redirect \[\[(.+)\]\]#', $message, $m);
      $m[1] = str_replace(' ', '_', $m[1]);
      $message = preg_replace('#\#redirect \[\[(.+)\]\]#', '<nowiki><div class="mdg-infobox"><table border="0" width="100%" cellspacing="0" cellpadding="0"><tr><td valign="top"><img alt="Cute wet-floor icon" src="'.scriptPath.'/images/redirector.png" /></td><td valign="top" style="padding-left: 10px;"><b>This page is a <i>redirector</i>.</b><br />This means that this page will not show its own content by default. Instead it will display the contents of the page it redirects to.<br /><br />To create a redirect page, make the <i>first characters</i> in the page content <tt>#redirect [[Page_ID]]</tt>. For more information, see the Enano <a href="http://enanocms.org/Help:Wiki_formatting">Wiki formatting guide</a>.<br /><br />This page redirects to <a href="'.makeUrl($m[1]).'">'.$paths->pages[$m[1]]['name'].'</a>.</td></tr></table></div><br /><hr style="margin-left: 1em; width: 200px;" /></nowiki>', $message);
    }
    $session->disallow_password_grab();
    dc_here('render: alright, got the text, formatting...');
    return ($render) ? RenderMan::render($message, $wiki, $smilies, $filter_links) : $message;
  }
  
  function getTemplate($id, $parms)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    dc_here('render: template requested: '.$id);
    if(!isset($paths->pages[$paths->nslist['Template'].$id])) 
    {
      return '[['.$paths->nslist['Template'].$id.']]';
    }
    if(isset($paths->template_cache[$id]))
    {
      $text = $paths->template_cache[$id];
    }
    else
    {
      $text = RenderMan::getPage($id, 'Template', 0, true, true, 0);
      $paths->template_cache[$id] = $text;
    }
    
    $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '', $text);
    $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '\\1', $text);
    
    preg_match_all('#\(_([0-9]+)_\)#', $text, $matchlist);
    
    foreach($matchlist[1] as $m)
    {
      if(isset($parms[((int)$m)+1])) 
      {
        $p = $parms[((int)$m)+1];
      }
      else
      {
        $p = '<b>Notice:</b> RenderMan::getTemplate(): Parameter '.$m.' is not set';
      }
      $text = str_replace('(_'.$m.'_)', $p, $text);
    }
    $text = RenderMan::include_templates($text);
    return $text;
  }
  
  function fetch_template_text($id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    dc_here('render: template raw data requested: '.$id);
    if(!isset($paths->pages[$paths->nslist['Template'].$id])) 
    {
      return '[['.$paths->nslist['Template'].$id.']]';
    }
    if(isset($paths->template_cache[$id]))
    {
      $text = $paths->template_cache[$id];
    }
    else
    {
      $text = RenderMan::getPage($id, 'Template', 0, false, false, false, false);
      $paths->template_cache[$id] = $text;
    }
    
    if ( is_string($text) )
    {
      $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '', $text);
      $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '\\1', $text);
    }
    
    return $text;
  }
  
  function render($text, $wiki = 1, $smilies = true, $filter_links = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if($smilies)
    {
      $text = RenderMan::smilieyize($text);
    }
    if($wiki == 1)
    {
      $text = RenderMan::next_gen_wiki_format($text);
    }
    elseif($wiki == 2)
    {
      $text = $template->tplWikiFormat($text);
    }
    return $text;
  }
  
  function PlainTextRender($text, $wiki = 1, $smilies = false, $filter_links = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if($smilies)
    {
      $text = RenderMan::smilieyize($text);
    }
    if($wiki == 1)
    {
      $text = RenderMan::next_gen_wiki_format($text, true);
    }
    elseif($wiki == 2)
    {
      $text = $template->tplWikiFormat($text);
    }
    return $text;
  }
  
  function next_gen_wiki_format($text, $plaintext = false, $filter_links = true, $do_params = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $random_id = md5( time() . mt_rand() );
    
    // Strip out <nowiki> sections and PHP code
    
    $php = preg_match_all('#<\?php(.*?)\?>#is', $text, $phpsec);
    
    for($i=0;$i<sizeof($phpsec[1]);$i++)
    {
      $text = str_replace('<?php'.$phpsec[1][$i].'?>', '{PHP:'.$random_id.':'.$i.'}', $text);
    }
    
    $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $text, $nowiki);
    
    for($i=0;$i<sizeof($nowiki[1]);$i++)
    {
      $text = str_replace('<nowiki>'.$nowiki[1][$i].'</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $text);
    }
    
    $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '\\1', $text);
    if ( $paths->namespace == 'Template' )
    {
      $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '', $text);
    }
    
    if ( !$plaintext )
    {
      // Process images
      $text = RenderMan::process_image_tags($text, $taglist);
    }
    
    if($do_params)
    {
      preg_match_all('#\(_([0-9]+)_\)#', $text, $matchlist);
      foreach($matchlist[1] as $m)
      {
        $text = str_replace('(_'.$m.'_)', $paths->getParam((int)$m), $text);
      }
    }
    
    $template_regex = "/\{\{([^\]]+?)((\n([ ]*?)[A-z0-9]+([ ]*?)=([ ]*?)(.+?))*)\}\}/is";
    $i = 0;
    while ( preg_match($template_regex, $text) )
    {
      $i++;
      if ( $i == 5 )
        break;
      $text = RenderMan::include_templates($text);
    }
    
    $text = process_tables($text);
    
    $wiki =& Text_Wiki::singleton('Mediawiki');
    if($plaintext)
    {
      $wiki->setRenderConf('Plain', 'wikilink', 'view_url', contentPath);
      $result = $wiki->transform($text, 'Plain');
    }
    else
    {
      $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', contentPath);
      $wiki->setRenderConf('Xhtml', 'Url', 'css_descr', 'external');
      $result = $wiki->transform($text, 'Xhtml');
    }
    
    if ( !$plaintext )
    {
      $result = RenderMan::process_imgtags_stage2($result, $taglist);
    }
    
    // Reinsert <nowiki> sections
    for($i=0;$i<$nw;$i++)
    {
      $result = str_replace('{NOWIKI:'.$random_id.':'.$i.'}', $nowiki[1][$i], $result);
    }
    
    // Reinsert PHP
    for($i=0;$i<$php;$i++)
    {
      $result = str_replace('{PHP:'.$random_id.':'.$i.'}', '<?php'.$phpsec[1][$i].'?>', $result);
    }
    
    return $result;
    
  }
  
  function wikiFormat($message, $filter_links = true, $do_params = false, $plaintext = false) {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    return RenderMan::next_gen_wiki_format($message, $plaintext, $filter_links, $do_params);
    
    $random_id = md5( time() . mt_rand() );
    
    // Strip out <nowiki> sections
    $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $message, $nowiki);
    
    if(!$plaintext)
    {
    
      //return '<pre>'.print_r($nowiki,true).'</pre>';
      
      for($i=0;$i<sizeof($nowiki[1]);$i++)
      {
        $message = str_replace('<nowiki>'.$nowiki[1][$i].'</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $message);
      }
      
      $message = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '\\1', $message);
      
      //return '<pre>'.htmlspecialchars($message).'</pre>';
      
      $message = RenderMan::process_image_tags($message);
    
    }
    
    if($do_params)
    {
      preg_match_all('#\(_([0-9]+)_\)#', $message, $matchlist);
      foreach($matchlist[1] as $m)
      {
        $message = str_replace('(_'.$m.'_)', $paths->getParam((int)$m), $message);
      }
    }
    
    $message = RenderMan::include_templates($message);
    
    // Reinsert <nowiki> sections
    for($i=0;$i<$nw;$i++)
    {
      $message = str_replace('{NOWIKI:'.$random_id.':'.$i.'}', '<nowiki>'.$nowiki[1][$i].'</nowiki>', $message);
    }
    
    $message = process_tables($message);
    //if($message2 != $message) return '<pre>'.htmlspecialchars($message2).'</pre>';
    //$message = str_replace(array('<table>', '</table>'), array('<nowiki><table>', '</table></nowiki>'), $message);
    
    $wiki =& Text_Wiki::singleton('Mediawiki');
    if($plaintext)
    {
      $wiki->setRenderConf('Plain', 'wikilink', 'view_url', contentPath);
      $result = $wiki->transform($message, 'Plain');
    } else {
      $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', contentPath);
      $wiki->setRenderConf('Xhtml', 'Url', 'css_descr', 'external');
      $result = $wiki->transform($message, 'Xhtml');
    }
    
    // HTML fixes
    $result = preg_replace('#<tr>([\s]*?)<\/tr>#is', '', $result);
    $result = preg_replace('#<p>([\s]*?)<\/p>#is', '', $result);
    $result = preg_replace('#<br />([\s]*?)<table#is', '<table', $result);
    $result = str_replace("<pre><code>\n", "<pre><code>", $result);
    $result = preg_replace("/<p><table([^>]*?)><\/p>/", "<table\\1>", $result);
    $result = str_replace("<br />\n</td>", "\n</td>", $result);
    $result = str_replace("<p><tr>", "<tr>", $result);
    $result = str_replace("<tr><br />", "<tr>", $result);
    $result = str_replace("</tr><br />", "</tr>", $result);
    $result = str_replace("</table></p>", "</table>", $result);
    $result = str_replace("</table><br />", "</table>", $result);
    $result = preg_replace('/<\/table>$/', "</table><br /><br />", $result);
    
    $result = str_replace('<nowiki>',  '&lt;nowiki&gt;',  $result);
    $result = str_replace('</nowiki>', '&lt;/nowiki&gt;', $result);
    
    return $result;
  }
  
  function destroy_javascript($message, $_php = false)
  {
    $message = preg_replace('#<(script|object|applet|embed|iframe|frame|form|input|select)(.*?)>#is', '&lt;\\1\\2&gt;', $message);
    $message = preg_replace('#</(script|object|applet|embed|iframe|frame|form|input|select)(.*?)>#is', '&lt;/\\1\\2&gt;', $message);
    $message = preg_replace('#(javascript|script|activex|chrome|about|applet):#is', '\\1&#058;', $message);
    if ( $_php )
    {
      // Left in only for compatibility
      $message = preg_replace('#&lt;(.*?)>#is', '<\\1>', $message);
      $message = preg_replace('#<(.*?)&gt;#is', '<\\1>', $message);
      $message = preg_replace('#<(\?|\?php|%)(.*?)(\?|%)>#is', '&lt;\\1\\2\\3&gt;', $message);
      // strip <a href="foo" onclick="bar();">-type attacks
      $message = preg_replace('#<([a-zA-Z:\-]+) (.*?)on([A-Za-z]*)=(.*?)>#is', '&lt;\\1\\2on\\3=\\4&gt;', $message);
    }
    return $message;
  }
  
  function strip_php($message)
  {
    return RenderMan::destroy_javascript($message, true);
  }
  
  function sanitize_html($text)
  {
    $text = htmlspecialchars($text);
    $allowed_tags = Array('b', 'i', 'u', 'pre', 'code', 'tt', 'br', 'p', 'nowiki', '!--([^.]+)--');
    foreach($allowed_tags as $t)
    {
      $text = preg_replace('#&lt;'.$t.'&gt;(.*?)&lt;/'.$t.'&gt;#is', '<'.$t.'>\\1</'.$t.'>', $text);
      $text = preg_replace('#&lt;'.$t.' /&gt;#is', '<'.$t.' />', $text);
      $text = preg_replace('#&lt;'.$t.'&gt;#is', '<'.$t.'>', $text);
    }
    return $text;
  }
  
  /* *
   * Replaces template inclusions with the templates
   * @param string $message The text to format
   * @return string
   * /
   
  function old_include_templates($message)
  {
    $random_id = md5( time() . mt_rand() );
    preg_match_all('#\{\{(.+?)\}\}#s', $message, $matchlist);
    foreach($matchlist[1] as $m)
    {
      $mn = $m;
      // Strip out wikilinks and re-add them after the explosion (because of the "|")
      preg_match_all('#\[\[(.+?)\]\]#i', $m, $linklist);
      //echo '<pre>'.print_r($linklist, true).'</pre>';
      for($i=0;$i<sizeof($linklist[1]);$i++)
      {
        $mn = str_replace('[['.$linklist[1][$i].']]', '{WIKILINK:'.$random_id.':'.$i.'}', $mn);
      }
      
      $ar = explode('|', $mn);
      
      for($j=0;$j<sizeof($ar);$j++)
      {
        for($i=0;$i<sizeof($linklist[1]);$i++)
        {
          $ar[$j] = str_replace('{WIKILINK:'.$random_id.':'.$i.'}', '[['.$linklist[1][$i].']]', $ar[$j]);
        }
      }
      
      $tp = $ar[0];
      unset($ar[0]);
      $tp = str_replace(' ', '_', $tp);
      $message = str_replace('{{'.$m.'}}', RenderMan::getTemplate($tp, $ar), $message);
    }
    return $message;
  }
  */
  
  /**
   * Parses a partial template tag in wikitext, and return an array with the parameters.
   * @param string The portion of the template tag that contains the parameters.
   * @example
   * <code>
   foo = lorem ipsum
   bar = dolor sit amet
   * </code>
   * @return array Example:
   * [foo] => lorem ipsum
   * [bar] => dolor sit amet
   */
  
  function parse_template_vars($input)
  {
    $input = explode("\n", trim( $input ));
    $parms = Array();
    $current_line = '';
    $current_parm = '';
    foreach ( $input as $num => $line )
    {
      if ( preg_match('/^([ ]*?)([A-z0-9_]+?)([ ]*?)=([ ]*?)(.+?)$/i', $line, $matches) )
      {
        $parm =& $matches[2];
        $text =& $matches[5];
        if ( $parm == $current_parm )
        {
          $current_line .= $text;
        }
        else
        {
          // New parameter
          if ( $current_parm != '' )
            $parms[$current_parm] = $current_line;
          $current_line = $text;
          $current_parm = $parm;
        }
      }
      else if ( $num == 0 )
      {
        // Syntax error
        return false;
      }
      else
      {
        $current_line .= "\n$line";
      }
    }
    if ( !empty($current_parm) && !empty($current_line) )
    {
      $parms[$current_parm] = $current_line;
    }
    return $parms;
  }
  
  /**
   * Processes all template tags within a block of wikitext.
   * @param string The text to process
   * @return string Formatted text
   * @example
   * <code>
   $text = '{{Template
     parm1 = Foo
     parm2 = Bar
     }}';
   $text = include_templates($text);
   * </code>
   */
  
  function include_templates($text)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $template_regex = "/\{\{([^\]]+?)((\n([ ]*?)[A-z0-9]+([ ]*?)=([ ]*?)(.+?))*)\}\}/is";
    if ( $count = preg_match_all($template_regex, $text, $matches) )
    {
      for ( $i = 0; $i < $count; $i++ )
      {
        $matches[1][$i] = sanitize_page_id($matches[1][$i]);
        $parmsection = trim($matches[2][$i]);
        if ( !empty($parmsection) )
        {
          $parms = RenderMan::parse_template_vars($parmsection);
          foreach ( $parms as $j => $parm )
          {
            $parms[$j] = $parm;
          }
        }
        else
        {
          $parms = Array();
        }
        if ( $tpl_code = RenderMan::fetch_template_text($matches[1][$i]) )
        {
          $parser = $template->makeParserText($tpl_code);
          $parser->assign_vars($parms);
          $text = str_replace($matches[0][$i], $parser->run(), $text);
        }
      }
    }
    return $text;
  }
  
  /**
   * Preprocesses an HTML text string prior to being sent to MySQL.
   * @param string $text
   * @param bool $strip_all_php - if true, strips all PHP regardless of user permissions. Else, strips PHP only if user level < USER_LEVEL_ADMIN.
   */
  function preprocess_text($text, $strip_all_php = true, $sqlescape = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $random_id = md5( time() . mt_rand() );
    
    $can_do_php = ( $session->get_permissions('php_in_pages') && !$strip_all_php );
    
    if ( !$can_do_php )
    {
      $text = sanitize_html($text, true);
      // If we can't do PHP, we can't do Javascript either.
      $text = RenderMan::destroy_javascript($text);
    }
    
    // Strip out <nowiki> sections and PHP code
    
    $php = preg_match_all('#(<|&lt;)\?php(.*?)\?(>|&gt;)#is', $text, $phpsec);
    
    //die('<pre>'.htmlspecialchars(print_r($phpsec, true))."\n".htmlspecialchars(print_r($text, true)).'</pre>');
    
    for($i=0;$i<sizeof($phpsec[1]);$i++)
    {
      $text = str_replace($phpsec[0][$i], '{PHP:'.$random_id.':'.$i.'}', $text);
    }
    
    $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $text, $nowiki);
    
    for($i=0;$i<sizeof($nowiki[1]);$i++)
    {
      $text = str_replace('<nowiki>'.$nowiki[1][$i].'</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $text);
    }
    
    $text = str_replace('~~~~~', date('G:i, j F Y (T)'), $text);
    $text = str_replace('~~~~', "[[User:$session->username|$session->username]] ".date('G:i, j F Y (T)'), $text);
    $text = str_replace('~~~', "[[User:$session->username|$session->username]] ", $text);
    
    // Reinsert <nowiki> sections
    for($i=0;$i<$nw;$i++)
    {
      $text = str_replace('{NOWIKI:'.$random_id.':'.$i.'}', '<nowiki>'.$nowiki[1][$i].'</nowiki>', $text);
    }
    // Reinsert PHP
    for($i=0;$i<$php;$i++)
    {
      $phsec = ''.$phpsec[1][$i].'?php'.$phpsec[2][$i].'?'.$phpsec[3][$i].'';
      if ( $strip_all_php )
        $phsec = htmlspecialchars($phsec);
      $text = str_replace('{PHP:'.$random_id.':'.$i.'}', $phsec, $text);
    }
    
    $text = ( $sqlescape ) ? $db->escape($text) : $text;
    
    return $text;
  }
  
  function smilieyize($text, $complete_urls = false)
  {
    
    $random_id = md5( time() . mt_rand() );
    
    // Smileys array - eventually this will be fetched from the database by
    // RenderMan::initSmileys during initialization, but it will all be hardcoded for beta 2
    
    $smileys = Array(
      'O:-)'    => 'face-angel.png',
      'O:)'     => 'face-angel.png',
      'O=)'     => 'face-angel.png',
      ':-)'     => 'face-smile.png',
      ':)'      => 'face-smile.png',
      '=)'      => 'face-smile-big.png',
      ':-('     => 'face-sad.png',
      ':('      => 'face-sad.png',
      ';('      => 'face-sad.png',
      ':-O'     => 'face-surprise.png',
      ';-)'     => 'face-wink.png',
      ';)'      => 'face-wink.png',
      '8-)'     => 'face-glasses.png',
      '8)'      => 'face-glasses.png',
      ':-D'     => 'face-grin.png',
      ':D'      => 'face-grin.png',
      '=D'      => 'face-grin.png',
      ':-*'     => 'face-kiss.png',
      ':*'      => 'face-kiss.png',
      '=*'      => 'face-kiss.png',
      ':\'('    => 'face-crying.png',
      ':-|'     => 'face-plain.png',
      ':-\\'    => 'face-plain.png',
      ':-/'     => 'face-plain.png',
      ':joke:'  => 'face-plain.png',
      ']:-&gt;' => 'face-devil-grin.png',
      ':kiss:'  => 'face-kiss.png',
      ':-P'     => 'face-tongue-out.png',
      ':P'      => 'face-tongue-out.png',
      ':-p'     => 'face-tongue-out.png',
      ':p'      => 'face-tongue-out.png',
      ':-X'     => 'face-sick.png',
      ':X'      => 'face-sick.png',
      ':sick:'  => 'face-sick.png',
      ':-]'     => 'face-oops.png',
      ':]'      => 'face-oops.png',
      ':oops:'  => 'face-oops.png',
      ':-['     => 'face-embarassed.png',
      ':['      => 'face-embarassed.png'
      );
    /*
    $keys = array_keys($smileys);
    foreach($keys as $k)
    {
      $regex1 = '#([\W]+)'.preg_quote($k).'([\s\n\r\.]+)#s';
      $regex2 = '\\1<img alt="'.$k.'" title="'.$k.'" src="'.scriptPath.'/images/smilies/'.$smileys[$k].'" style="border: 0;" />\\2';
      $text = preg_replace($regex1, $regex2, $text);
    }                                                                      
    */
    
    // Strip out <nowiki> sections
    //return '<pre>'.htmlspecialchars($text).'</pre>';
    $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $text, $nowiki);
    
    for($i=0;$i<sizeof($nowiki[1]);$i++)
    {
      $text = str_replace('<nowiki>'.$nowiki[1][$i].'</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $text);
    }
    
    $keys = array_keys($smileys);
    foreach($keys as $k)
    {
      $t = str_hex($k);
      $t = explode(' ', $t);
      $s = '';
      foreach($t as $b)
      {
        $s.='&#x'.$b.';';
      }
      $pfx = ( $complete_urls ) ? 'http' . ( isset($_SERVER['HTTPS']) ? 's' : '' ) . '://'.$_SERVER['HTTP_HOST'] : '';
      $text = str_replace(' '.$k, ' <nowiki><img title="'.$s.'" alt="'.$s.'" src="'.$pfx.scriptPath.'/images/smilies/'.$smileys[$k].'" style="border: 0;" /></nowiki>', $text);
    }
    //*/
    
    // Reinsert <nowiki> sections
    for($i=0;$i<$nw;$i++)
    {
      $text = str_replace('{NOWIKI:'.$random_id.':'.$i.'}', '<nowiki>'.$nowiki[1][$i].'</nowiki>', $text);
    }
    
    return $text;
  }
  
  /*
   * **** DEPRECATED ****
   * Replaces some critical characters in a string with MySQL-safe equivalents
   * @param $text string the text to escape
   * @return array key 0 is the escaped text, key 1 is the character tag
   * /
   
  function escape_page_text($text)
  {
    $char_tag = md5(microtime() . mt_rand());
    $text = str_replace("'",  "{APOS:$char_tag}",  $text);
    $text = str_replace('"',  "{QUOT:$char_tag}",  $text);
    $text = str_replace("\\", "{SLASH:$char_tag}", $text);
    return Array($text, $char_tag);
  }
  */
  
  /* **** DEPRECATED ****
   * Reverses the result of RenderMan::escape_page_text().
   * @param $text string the text to unescape
   * @param $char_tag string the character tag
   * @return string
   * /
   
  function unescape_page_text($text, $char_tag)
  {
    $text = str_replace("{APOS:$char_tag}",  "'",  $text);
    $text = str_replace("{QUOT:$char_tag}",  '"',  $text);
    $text = str_replace("{SLASH:$char_tag}", "\\", $text);
    return $text;
  }
  */
  
  /**
   * Generates a summary of the differences between two texts, and formats it as XHTML.
   * @param $str1 string the first block of text
   * @param $str2 string the second block of text
   * @return string
   */
  function diff($str1, $str2)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $str1 = explode("\n", $str1);
    $str2 = explode("\n", $str2);
    $diff = new Diff($str1, $str2);
    $renderer = new TableDiffFormatter();
    return '<table class="diff">'.$renderer->format($diff).'</table>';
  }
  
  /**
   * Changes wikitext image tags to HTML.
   * @param string The wikitext to process
   * @param array Will be overwritten with the list of HTML tags (the system uses tokens for TextWiki compatibility)
   * @return string
   */
  
  function process_image_tags($text, &$taglist)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $s_delim = "\xFF";
    $f_delim = "\xFF";
    $taglist = array();
    
    // Wicked huh?
    $regex = '/\[\[:' . $paths->nslist['File'] . '([\w\s0-9_\(\)!@%\^\+\|\.-]+?)((\|thumb)|(\|([0-9]+)x([0-9]+)))?(\|left|\|right)?(\|(.+))?\]\]/i';
    
    preg_match_all($regex, $text, $matches);
    
    foreach ( $matches[0] as $i => $match )
    {
      
      $full_tag   =& $matches[0][$i];
      $filename   =& $matches[1][$i];
      $scale_type =& $matches[2][$i];
      $width      =& $matches[5][$i];
      $height     =& $matches[6][$i];
      $clear      =& $matches[7][$i];
      $caption    =& $matches[8][$i];
      
      if ( !isPage( $paths->nslist['File'] . $filename ) )
      {
        continue;
      }
      
      if ( $scale_type == '|thumb' )
      {
        $r_width  = 225;
        $r_height = 225;
        
        $url = makeUrlNS('Special', 'DownloadFile/' . $filename, 'preview&width=' . $r_width . '&height=' . $r_height, true);
      }
      else if ( !empty($width) && !empty($height) )
      {
        $r_width = $width;
        $r_height = $height;
        
        $url = makeUrlNS('Special', 'DownloadFile/' . $filename, 'preview&width=' . $r_width . '&height=' . $r_height, true);
      }
      else
      {
        $url = makeUrlNS('Special', 'DownloadFile/' . $filename);
      }
      
      $img_tag = '<img src="' . $url . '" ';
      
      // if ( isset($r_width) && isset($r_height) && $scale_type != '|thumb' )
      // {
      // $img_tag .= 'width="' . $r_width . '" height="' . $r_height . '" ';
      // }
      
      $img_tag .= 'style="border-width: 0px; background-color: white;" ';
      
      $img_tag .= '/>';
      
      $complete_tag = '';
      
      if ( !empty($scale_type) )
      {
        $complete_tag .= '<div class="thumbnail" ';
        $clear_text = '';
        if ( !empty($clear) )
        {
          $side = ( $clear == '|left' ) ? 'left' : 'right';
          $opposite = ( $clear == '|left' ) ? 'right' : 'left';
          $clear_text .= "float: $side; margin-$opposite: 20px;";
          $complete_tag .= 'style="' . $clear_text . '" ';
        }
        $complete_tag .= '>';
        
        $complete_tag .= '<a href="' . makeUrlNS('File', $filename) . '" style="display: block;">';
        $complete_tag .= $img_tag;
        $complete_tag .= '</a>';
        
        $mag_button = '<a href="' . makeUrlNS('File', $filename) . '" style="display: block; float: right; clear: right; margin: 0 0 10px 10px;"><img alt="[ + ]" src="' . scriptPath . '/images/thumbnail.png" style="border-width: 0px;" /></a>';
      
        if ( !empty($caption) )
        {
          $cap = substr($caption, 1);
          $complete_tag .= $mag_button . $cap;
        }
        
        $complete_tag .= '</div>';
      }
      else
      {
        $complete_tag .= '<a href="' . makeUrlNS('File', $filename) . '" style="display: block;">';
        $complete_tag .= $img_tag;
        $complete_tag .= '</a>';
      }
      
      $complete_tag .= "\n\n";
      $taglist[$i] = $complete_tag;
      
      $pos = strpos($text, $full_tag);
      
      while(true)
      {
        $check1 = substr($text, $pos, 3);
        $check2 = substr($text, $pos, 1);
        if ( $check1 == '<p>' || $pos == 0 || $check2 == "\n" )
        {
          // die('found at pos '.$pos);
          break;
        }
        $pos--;
      }
      
      $repl = "{$s_delim}e_img_{$i}{$f_delim}";
      $text = substr($text, 0, $pos) . $repl . substr($text, $pos);
      
      $text = str_replace($full_tag, '', $text);
      
      unset($full_tag, $filename, $scale_type, $width, $height, $clear, $caption, $r_width, $r_height);
      
    }
    
    return $text;
  }
  
  /**
   * Finalizes processing of image tags.
   * @param string The preprocessed text
   * @param array The list of image tags created by RenderMan::process_image_tags()
   */
   
  function process_imgtags_stage2($text, $taglist)
  {
    $s_delim = "\xFF";
    $f_delim = "\xFF";
    foreach ( $taglist as $i => $tag )
    {
      $repl = "{$s_delim}e_img_{$i}{$f_delim}";
      $text = str_replace($repl, $tag, $text);
    }               
    return $text;
  }
  
}
 
?>

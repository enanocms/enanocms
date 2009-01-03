<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 * render.php - handles fetching pages and parsing them into HTML
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
class RenderMan {
  
  public static function strToPageID($string)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $k = array_keys($paths->nslist);
    $proj_alt = 'Project:';
    if ( substr($string, 0, (strlen($proj_alt))) == $proj_alt )
    {
      $ns = 'Project';
      $pg = substr($string, strlen($proj_alt), strlen($string));
      return Array($pg, $ns);
    }
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
  
  public static function getPage($page_id, $namespace, $wiki = 1, $smilies = true, $filter_links = true, $redir = true, $render = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $perms =& $session;
    
    if ( $page_id != $paths->page_id || $namespace != $paths->namespace )
    {
      unset($perms);
      unset($perms); // PHP <5.1.5 Zend bug
      $perms = $session->fetch_page_acl($page_id, $namespace);
      if ( !$perms )
      {
        $session->init_permissions();
        $perms = $session->fetch_page_acl($page_id, $namespace);
      };
    }
    
    if(!$perms->get_permissions('read'))
      return 'Access denied ('.$paths->nslist[$namespace].$page_id.')';
    
    if($namespace != 'Template' && ($wiki == 0 || $render == false))
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
    
    if ( preg_match("#^\#redirect \[\[([^\]\r\n\a\t]+?)\]\]#", $message, $m) && $redir && ( !isset($_GET['redirect']) || ( isset($_GET['redirect']) && $_GET['redirect'] != 'no' ) ) )
    {
      $old = $paths->cpage;
      $a = RenderMan::strToPageID($m[1]);
      $a[0] = str_replace(' ', '_', $a[0]);
      
      $pageid = str_replace(' ', '_', $paths->nslist[$a[1]] . $a[0]);
      $paths->page = $pageid;
      $paths->cpage = $paths->pages[$pageid];
      //die('<pre>'.print_r($paths->cpage,true).'</pre>');
      
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
      preg_match('#^\#redirect \[\[(.+)\]\]#', $message, $m);
      $m[1] = str_replace(' ', '_', $m[1]);
      $message = preg_replace('#\#redirect \[\[(.+)\]\]#', '<nowiki><div class="mdg-infobox"><table border="0" width="100%" cellspacing="0" cellpadding="0"><tr><td valign="top"><img alt="Cute wet-floor icon" src="'.scriptPath.'/images/redirector.png" /></td><td valign="top" style="padding-left: 10px;"><b>This page is a <i>redirector</i>.</b><br />This means that this page will not show its own content by default. Instead it will display the contents of the page it redirects to.<br /><br />To create a redirect page, make the <i>first characters</i> in the page content <tt>#redirect [[Page_ID]]</tt>. For more information, see the Enano <a href="http://enanocms.org/Help:Wiki_formatting">Wiki formatting guide</a>.<br /><br />This page redirects to <a href="'.makeUrl($m[1]).'">'.$paths->pages[$m[1]]['name'].'</a>.</td></tr></table></div><br /><hr style="margin-left: 1em; width: 200px;" /></nowiki>', $message);
    }
    $session->disallow_password_grab();
    return ($render) ? RenderMan::render($message, $wiki, $smilies, $filter_links) : $message;
  }
  
  public static function getTemplate($id, $parms)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
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
      $text = str_replace('{{' . ( $m + 1 ) . '}}', $p, $text);
    }
    $text = RenderMan::include_templates($text);
    return $text;
  }
  
  public static function fetch_template_text($id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $fetch_ns = 'Template';
    if(!isset($paths->pages[$paths->nslist['Template'].$id])) 
    {
      // Transclusion of another page
      // 1.1.5: Now You, Too, Can Be A Template, Even If You're Just A Plain Old Article! (TM)
      $nssep = substr($paths->nslist['Special'], -1);
      $nslist = $paths->nslist;
      foreach ( $nslist as &$ns )
      {
        if ( $ns == '' )
          $ns = $nssep;
      }
      $prefixlist = array_flip($nslist);
      foreach ( $nslist as &$ns )
      {
        $ns = preg_quote($ns);
      }
      $nslist = implode('|', $nslist);
      if ( preg_match("/^($nslist)(.*?)$/", $id, $match) )
      {
        // in practice this should always be true but just to be safe...
        if ( isset($prefixlist[$match[1]]) )
        {
          $new_id = $paths->nslist[ $prefixlist[$match[1]] ] . sanitize_page_id($match[2]);
          if ( !isset($paths->pages[$new_id]) )
          {
            return "[[$new_id]]";
          }
          $fetch_ns = $prefixlist[$match[1]];
          $id = sanitize_page_id($match[2]);
        }
      }
      else
      {
        return '[['.$paths->nslist['Template'].$id.']]';
      }
    }
    if(isset($paths->template_cache[$id]))
    {
      $text = $paths->template_cache[$id];
    }
    else
    {
      $text = RenderMan::getPage($id, $fetch_ns, 0, false, false, false, false);
      $paths->template_cache[$id] = $text;
    }
    
    if ( is_string($text) )
    {
      $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '', $text);
      $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '\\1', $text);
    }
    
    return $text;
  }
  
  public static function render($text, $wiki = 1, $smilies = true, $filter_links = true)
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
  
  public static function PlainTextRender($text, $wiki = 1, $smilies = false, $filter_links = true)
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
  
  public static function next_gen_wiki_format($text, $plaintext = false, $filter_links = true, $do_params = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    require_once(ENANO_ROOT.'/includes/wikiformat.php');
    require_once(ENANO_ROOT.'/includes/wikiengine/Tables.php');
    
    profiler_log("RenderMan: starting wikitext render");
    
    $random_id = md5( time() . mt_rand() );
    
    // Strip out <nowiki> sections and PHP code
    
    $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $text, $nowiki);
    
    for($i=0;$i<sizeof($nowiki[1]);$i++)
    {
      $text = str_replace('<nowiki>'.$nowiki[1][$i].'</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $text);
    }
    
    $code = $plugins->setHook('render_wikiformat_veryearly');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $php = preg_match_all('#<\?php(.*?)\?>#is', $text, $phpsec);
    
    for($i=0;$i<sizeof($phpsec[1]);$i++)
    {
      $text = str_replace('<?php'.$phpsec[1][$i].'?>', '{PHP:'.$random_id.':'.$i.'}', $text);
    }
    
    $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '\\1', $text);
    if ( $paths->namespace == 'Template' )
    {
      $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '', $text);
    }
    
    preg_match_all('/<lang (?:code|id)="([a-z0-9_-]+)">([\w\W]+?)<\/lang>/', $text, $langmatch);
    foreach ( $langmatch[0] as $i => $match )
    {
      if ( $langmatch[1][$i] == $lang->lang_code )
      {
        $text = str_replace_once($match, $langmatch[2][$i], $text);
      }
      else
      {
        $text = str_replace_once($match, '', $text);
      }
    }
    
    $code = $plugins->setHook('render_wikiformat_pre');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    //$template_regex = "/\{\{([^\]]+?)((\n([ ]*?)[A-z0-9]+([ ]*?)=([ ]*?)(.+?))*)\}\}/is";
    $template_regex = "/\{\{(.+)((\n|\|[ ]*([A-z0-9]+)[ ]*=[ ]*(.+))*)\}\}/isU";
    $i = 0;
    while ( preg_match($template_regex, $text) )
    {
      $i++;
      if ( $i == 5 )
        break;
      $text = RenderMan::include_templates($text);
    }
    
    $code = $plugins->setHook('render_wikiformat_posttemplates');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if ( !$plaintext )
    {
      // Process images
      $text = RenderMan::process_image_tags($text, $taglist);
      $text = RenderMan::process_imgtags_stage2($text, $taglist);
    }
    
    if($do_params)
    {
      preg_match_all('#\(_([0-9]+)_\)#', $text, $matchlist);
      foreach($matchlist[1] as $m)
      {
        $text = str_replace('(_'.$m.'_)', $paths->getParam((int)$m), $text);
      }
    }
    
    // Before shipping it out to the renderer, replace spaces in between headings and paragraphs:
    $text = preg_replace('/<\/(h[0-9]|div|p)>([\s]+)<(h[0-9]|div|p)( .+?)?>/i', '</\\1><\\3\\4>', $text);
    
    $text = process_tables($text);
    $text = RenderMan::parse_internal_links($text);
    
    $wiki = Text_Wiki::singleton('Mediawiki');
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
    $result = str_replace("</table><br />", "</table>", $result);
    $result = preg_replace('/<\/table>$/', "</table><br /><br />", $result);
    $result = str_replace("<p></div></p>", "</div>", $result);
    $result = str_replace("<p></table></p>", "</table>", $result);
    
    $code = $plugins->setHook('render_wikiformat_post');
    foreach ( $code as $cmd )
    {
      eval($cmd);
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
    
    profiler_log("RenderMan: finished wikitext render");
    
    return $result;
    
  }
  
  public static function wikiFormat($message, $filter_links = true, $do_params = false, $plaintext = false)
  {
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
    $result = str_replace("<p></div></p>", "</div>", $result);
    $result = str_replace("<p></table></p>", "</table>", $result);
    
    $result = str_replace('<nowiki>',  '&lt;nowiki&gt;',  $result);
    $result = str_replace('</nowiki>', '&lt;/nowiki&gt;', $result);
    
    return $result;
  }
  
  public static function destroy_javascript($message, $_php = false)
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
  
  public static function strip_php($message)
  {
    return RenderMan::destroy_javascript($message, true);
  }
  
  public static function sanitize_html($text)
  {
    $text = htmlspecialchars($text);
    $allowed_tags = Array('b', 'i', 'u', 'pre', 'code', 'tt', 'br', 'p', 'nowiki', '!--([\w\W]+)--');
    foreach($allowed_tags as $t)
    {
      $text = preg_replace('#&lt;'.$t.'&gt;(.*?)&lt;/'.$t.'&gt;#is', '<'.$t.'>\\1</'.$t.'>', $text);
      $text = preg_replace('#&lt;'.$t.' /&gt;#is', '<'.$t.' />', $text);
      $text = preg_replace('#&lt;'.$t.'&gt;#is', '<'.$t.'>', $text);
    }
    return $text;
  }
  
  /**
   * Parses internal links (wikilinks) in a block of text.
   * @param string Text to process
   * @param string Optional. If included will be used as a template instead of using the default syntax.
   * @return string
   */
  
  public static function parse_internal_links($text, $tplcode = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( is_string($tplcode) )
    {
      $parser = $template->makeParserText($tplcode);
    }
    
    // stage 1 - links with alternate text
    preg_match_all('/\[\[([^\[\]<>\{\}\|]+)\|(.+?)\]\]/', $text, $matches);
    foreach ( $matches[0] as $i => $match )
    {
      list($page_id, $namespace) = RenderMan::strToPageID($matches[1][$i]);
      if ( ($pos = strrpos($page_id, '#')) !== false )
      {
        $hash = substr($page_id, $pos);
        $page_id = substr($page_id, 0, $pos);
      }
      else
      {
        $hash = '';
      }
      $pid_clean = $paths->nslist[$namespace] . sanitize_page_id($page_id);
      
      $url = makeUrl($pid_clean, false, true) . $hash;
      $inner_text = $matches[2][$i];
      $quot = '"';
      $exists = ( isPage($pid_clean) ) ? '' : ' class="wikilink-nonexistent"';
      
      if ( $tplcode )
      {
        $parser->assign_vars(array(
            'HREF' => $url,
            'FLAGS' => $exists,
            'TEXT' => $inner_text
          ));
        $link = $parser->run();
      }
      else
      {
        $link = "<a href={$quot}{$url}{$quot}{$exists}>{$inner_text}</a>";
      }
      
      $text = str_replace($match, $link, $text);
    }
    
    // stage 2 - links with no alternate text
    preg_match_all('/\[\[([^\[\]<>\{\}\|]+)\]\]/', $text, $matches);
    foreach ( $matches[0] as $i => $match )
    {
      list($page_id, $namespace) = RenderMan::strToPageID($matches[1][$i]);
      $pid_clean = $paths->nslist[$namespace] . sanitize_page_id($page_id);
      
      $url = makeUrl($pid_clean, false, true);
      $inner_text = ( isPage($pid_clean) ) ? htmlspecialchars(get_page_title($pid_clean)) : htmlspecialchars($matches[1][$i]);
      $quot = '"';
      $exists = ( isPage($pid_clean) ) ? '' : ' class="wikilink-nonexistent"';
      
      if ( $tplcode )
      {
        $parser->assign_vars(array(
            'HREF' => $url,
            'FLAGS' => $exists,
            'TEXT' => $inner_text
          ));
        $link = $parser->run();
      }
      else
      {
        $link = "<a href={$quot}{$url}{$quot}{$exists}>{$inner_text}</a>";
      }
      
      $text = str_replace($match, $link, $text);
    }
    
    return $text;
  }
  
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
  
  public static function parse_template_vars($input, $newlinemode = true)
  {
    $parms = array();
    $input = trim($input);
    if ( $newlinemode )
    {
      $result = preg_match_all('/
                                  (?:^|[\s]*)\|?    # start of parameter - string start or series of spaces
                                  [ ]*              
                                  (?:               
                                    ([A-z0-9_]+)    # variable name
                                    [ ]* = [ ]*     # assignment
                                  )?                # this is optional - if the parameter name is not given, a numerical index is assigned
                                  (.+)              # value
                                /x', trim($input), $matches);
    }
    else
    {
      $result = preg_match_all('/
                                  (?:^|[ ]*)\|         # start of parameter - string start or series of spaces
                                  [ ]*
                                  (?:
                                    ([A-z0-9_]+)       # variable name
                                    [ ]* = [ ]*        # assignment
                                  )?                   # name section is optional - if the parameter name is not given, a numerical index is assigned
                                  ([^\|]+|.+?\n[ ]*\|) # value
                                /x', trim($input), $matches);
    }                   
    if ( $result )
    {
      $pi = 0;
      for ( $i = 0; $i < count($matches[0]); $i++ )
      {
        $matches[1][$i] = trim($matches[1][$i]);
        $parmname = !empty($matches[1][$i]) ? $matches[1][$i] : strval(++$pi);
        $parms[ $parmname ] = $matches[2][$i];
      }
    }
    return $parms;
  }
  
  /**
   * Processes all template tags within a block of wikitext.
   * Updated in 1.0.2 to also parse template tags in the format of {{Foo |a = b |b = c |c = therefore, a}}
   * @param string The text to process
   * @return string Formatted text
   * @example
   * <code>
   $text = '{{Template
       | parm1 = Foo
       | parm2 = Bar
     }}';
   $text = RenderMan::include_templates($text);
   * </code>
   */
  
  public static function include_templates($text)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // $template_regex = "/\{\{([^\]]+?)((\n([ ]*?)[A-z0-9]+([ ]*?)=([ ]*?)(.+?))*)\}\}/is";
    // matches:
    //  1 - template name
    //  2 - parameter section
    $template_regex = "/
                         \{\{                     # opening
                           ([^\n\t\a\r]+)         # template name
                           ((?:(?:[\s]+\|?)[ ]*(?:[A-z0-9_]+)[ ]*=[ ]*?(?:.+))*) # parameters
                         \}\}                     # closing
                       /isxU";
    if ( $count = preg_match_all($template_regex, $text, $matches) )
    {
      //die('<pre>' . print_r($matches, true) . '</pre>');
      for ( $i = 0; $i < $count; $i++ )
      {
        $matches[1][$i] = sanitize_page_id($matches[1][$i]);
        $newlinemode = ( substr($matches[2][$i], 0, 1) == "\n" );
        $parmsection = trim($matches[2][$i]);
        if ( !empty($parmsection) )
        {
          $parms = RenderMan::parse_template_vars($parmsection, $newlinemode);
          if ( !is_array($parms) )
            // Syntax error
            $parms = array();
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
  public static function preprocess_text($text, $strip_all_php = true, $sqlescape = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $random_id = md5( time() . mt_rand() );
    
    $code = $plugins->setHook('render_sanitize_pre');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $can_do_php = ( $session->get_permissions('php_in_pages') && !$strip_all_php );
    $can_do_html = $session->get_permissions('html_in_pages');
    
    if ( $can_do_html && !$can_do_php )
    {
      $text = preg_replace('#<(\?|\?php|%)(.*?)(\?|%)>#is', '&lt;\\1\\2\\3&gt;', $text);
    }
    else if ( !$can_do_html && !$can_do_php )
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
    
    $text = str_replace('~~~~~', enano_date('G:i, j F Y (T)'), $text);
    $text = str_replace('~~~~', "[[User:$session->username|$session->username]] ".enano_date('G:i, j F Y (T)'), $text);
    $text = str_replace('~~~', "[[User:$session->username|$session->username]] ", $text);
    
    $code = $plugins->setHook('render_sanitize_post');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
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
  
  public static function smilieyize($text, $complete_urls = false)
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
      ']:->'    => 'face-devil-grin.png',
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
      $t = hexencode($k, ' ', '');
      $t = trim($t);
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
   
  public static function escape_page_text($text)
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
   
  public static function unescape_page_text($text, $char_tag)
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
  public static function diff($str1, $str2)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    require_once(ENANO_ROOT.'/includes/diff.php');
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
  
  public static function process_image_tags($text, &$taglist)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $s_delim = "\xFF";
    $f_delim = "\xFF";
    $taglist = array();
    
    // Wicked huh?
    $ns_file = str_replace('/', '\\/', preg_quote($paths->nslist['File']));
    $regex = '/
           \[\[                                                                  # starting delimiter 
           :' . $ns_file . '([\w\s0-9_\(\)!@%\^\+\|\.-]+?\.(?:png|gif|jpg|jpeg)) # image filename
           (?:(?:\|(?:.+?))*)                                                    # parameters
           \]\]                                                                  # ending delimiter
           /ix';
    
    preg_match_all($regex, $text, $matches);
    
    foreach ( $matches[0] as $i => $match )
    {
      
      $full_tag   =& $matches[0][$i];
      $filename   =& $matches[1][$i];
      
      // apply recursion (hack? @todo could this be done with (?R) in PCRE?)
      $tag_pos = strpos($text, $full_tag);
      $tag_end_pos = $tag_pos + strlen($full_tag);
      while ( get_char_count($full_tag, ']') < get_char_count($full_tag, '[') && $tag_end_pos < strlen($text) )
      {
        $full_tag .= substr($text, $tag_end_pos, 1);
        $tag_end_pos++;
      }
      if ( $tag_end_pos > strlen($text) )
      {
        // discard tag, not closed fully
        continue;
      }
      
      // init the various image parameters
      $width = null;
      $height = null;
      $scale_type = null;
      $raw_display = false;
      $clear = null;
      $caption = null;
      
      // trim tag and parse particles
      $tag_trim = rtrim(ltrim($full_tag, '['), ']');
      // trim off the filename from the start of the tag
      $filepart_len = 1 + strlen($paths->nslist['File']) + strlen($filename) + 1;
      $tag_trim = substr($tag_trim, $filepart_len);
      // explode and we should have parameters
      $tag_parts = explode('|', $tag_trim);
      
      // for each of the parameters, see if it matches a known option. If so, apply it;
      // otherwise, see if a plugin reserved that parameter and if not treat it as the caption
      foreach ( $tag_parts as $param )
      {
        switch($param)
        {
          case 'left':
          case 'right':
            $clear = $param;
            break;
          case 'thumb':
            $scale_type = 'thumb';
            break;
          case 'raw':
            $raw_display = true;
            break;
          default:
            // height specification
            if ( preg_match('/^([0-9]+)x([0-9]+)$/', $param, $dims) )
            {
              $width = intval($dims[1]);
              $height = intval($dims[2]);
              break;
            }
            // not the height, so see if a plugin took this over
            // this hook requires plugins to return true if they modified anythin
            $code = $plugins->setHook('img_tag_parse_params');
            foreach ( $code as $cmd )
            {
              if ( eval($cmd) )
                break 2;
            }
            // we would have broken out by now if a plugin properly handled this,
            // so just set the caption now.
            $caption = $param;
            break;
        }
      }
      
      if ( !isPage( $paths->nslist['File'] . $filename ) )
      {
        $text = str_replace($full_tag, '[[' . makeUrlNS('File', $filename) . ']]', $text);
        continue;
      }
      
      if ( $scale_type == 'thumb' )
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
      //   $img_tag .= 'width="' . $r_width . '" height="' . $r_height . '" ';
      // }
      
      $img_tag .= 'style="border-width: 0px; /* background-color: white; */" ';
      
      $code = $plugins->setHook('img_tag_parse_img');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      
      $img_tag .= '/>';
      
      $complete_tag = '';
      
      if ( !empty($scale_type) && !$raw_display )
      {
        $complete_tag .= '<div class="thumbnail" ';
        $clear_text = '';
        if ( !empty($clear) )
        {
          $side = ( $clear == 'left' ) ? 'left' : 'right';
          $opposite = ( $clear == 'left' ) ? 'right' : 'left';
          $clear_text .= "float: $side; margin-$opposite: 20px; width: {$r_width}px;";
          $complete_tag .= 'style="' . $clear_text . '" ';
        }
        $complete_tag .= '>';
        
        $complete_tag .= '<a href="' . makeUrlNS('File', $filename) . '" style="display: block;">';
        $complete_tag .= $img_tag;
        $complete_tag .= '</a>';
        
        $mag_button = '<a href="' . makeUrlNS('File', $filename) . '" style="display: block; float: right; clear: right; margin: 0 0 10px 10px;"><img alt="[ + ]" src="' . scriptPath . '/images/thumbnail.png" style="border-width: 0px;" /></a>';
      
        if ( !empty($caption) )
        {
          $complete_tag .= $mag_button . $caption;
        }
        
        $complete_tag .= '</div>';
      }
      else if ( $raw_display )
      {
        $complete_tag .= "$img_tag";
        $taglist[$i] = $complete_tag;
        
        $repl = "{$s_delim}e_img_{$i}{$f_delim}";
        $text = str_replace($full_tag, $repl, $text);
        continue;
      }
      else
      {
        $complete_tag .= '<a href="' . makeUrlNS('File', $filename) . '" style="display: block;"';
        $code = $plugins->setHook('img_tag_parse_link');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        $complete_tag .= '>';
        $complete_tag .= $img_tag;
        $complete_tag .= '</a>';
      }
      
      $complete_tag .= "\n\n";
      $taglist[$i] = $complete_tag;
      
      $pos = strpos($text, $full_tag);
      
      /*
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
      */
      
      /*
      $repl = "{$s_delim}e_img_{$i}{$f_delim}";
      $text = substr($text, 0, $pos) . $repl . substr($text, $pos);
      
      $text = str_replace($full_tag, '', $text);
      */
      $text = str_replace_once($full_tag, $complete_tag, $text);
      
      unset($full_tag, $filename, $scale_type, $width, $height, $clear, $caption, $r_width, $r_height);
      
    }
    
    // if ( count($matches[0]) > 0 )
    //   die('<pre>' . htmlspecialchars($text) . '</pre>');
    
    return $text;
  }
  
  /**
   * Finalizes processing of image tags.
   * @param string The preprocessed text
   * @param array The list of image tags created by RenderMan::process_image_tags()
   */
   
  public static function process_imgtags_stage2($text, $taglist)
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

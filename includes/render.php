<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
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
    
    $page = new PageProcessor($page_id, $namespace);
    $text = $page->fetch_text();
    
    if ( !$render )
      return $text;
    
    $text = self::render($text, $wiki, $smilies, $filter_links);
    return $text;
  }
  
  public static function getTemplate($id, $parms)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !isPage($paths->get_pathskey($id, 'Template')) ) 
    {
      return '[['.$paths->nslist['Template'].$id.']]';
    }
    if(isset($paths->template_cache[$id]))
    {
      $text = $paths->template_cache[$id];
    }
    else
    {
      $page = new PageProcessor($id, 'Template');
      $text = $page->fetch_text();
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
    if ( !isPage($paths->get_pathskey($id, 'Template')) ) 
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
          if ( !isPage($new_id) )
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
  
  /**
   * Renders a glob of text. Note that this is PHP-safe, so if returned text (or rather, "?>" . $returned) has PHP it can be eval'ed.
   * @param string Text to render
   * @param int Render parameters - see constants.php
   * @return string Rendered text
   */
  
  public static function render($text, $flags = RENDER_WIKI_DEFAULT, $smilies = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !$smilies )
      $flags |= RENDER_NOSMILIES;
    
    if ( $flags & ~RENDER_NOSMILIES )
    {
      $text = RenderMan::smilieyize($text);
    }
    if ( $flags & RENDER_WIKI_DEFAULT )
    {
      $text = RenderMan::next_gen_wiki_format($text, $flags);
    }
    else if ( $flags & RENDER_WIKI_TEMPLATE )
    {
      $text = $template->tplWikiFormat($text);
    }           
    return $text;
  }
  
  private static function next_gen_wiki_format($text, $flags = 0)
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
    
    if ( !($flags & RENDER_BLOCKONLY) )
    {
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
      
      // Process images
      $text = RenderMan::process_image_tags($text, $taglist);
      $text = RenderMan::process_imgtags_stage2($text, $taglist);
    }
    
    // Before shipping it out to the renderer, replace spaces in between headings and paragraphs:
    $text = preg_replace('/<\/(h[0-9]|div|p)>([\s]+)<(h[0-9]|div|p)( .+?)?>/i', '</\\1><\\3\\4>', $text);
    
    $text = process_tables($text);
    
    if ( !($flags & RENDER_BLOCKONLY) )
      $text = RenderMan::parse_internal_links($text);
    
    $wiki = Text_Wiki::singleton('Mediawiki');
    $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', contentPath);
    $wiki->setRenderConf('Xhtml', 'Url', 'css_descr', 'external');
    if ( $flags & RENDER_BLOCKONLY )
    {
      $wiki->disableRule('Freelink');
      $wiki->disableRule('Url');
      $wiki->disableRule('Toc');
      $wiki->disableRule('Image');
    }
    else if ( $flags & RENDER_INLINEONLY )
    {
      foreach ( array('code', 'html', 'raw', 'include', 'embed', 'horiz', 'break', 'blockquote', 'list', 'newline', 'paragraph', 'revise', 'tighten') as $rule )
      {
        $wiki->disableRule($rule);
      }
    }
    $result = $wiki->transform($text, 'Xhtml');
    
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
    
    if ( !($flags & RENDER_BLOCKONLY) )
    {
      $code = $plugins->setHook('render_wikiformat_post');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
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
   * Reverse-renders a blob of text (converts it from XHTML back to wikitext) by using parser hints and educated guesses.
   * @param string XHTML
   * @return string Wikitext
   */
  
  public static function reverse_render($text)
  {
    // convert \r\n to \n
    $text = str_replace("\r\n", "\n", $text);
    
    // Separate certain block level elements onto their own lines. This tidies up the tag
    // soup that TinyMCE sometimes produces.
    $block_elements = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'table', 'ul', 'pre');
    $block_elements = implode('|', $block_elements);
    $regex = "#(</(?:$block_elements)>)\n?<($block_elements)(>| .+?>)#i";
    $text = preg_replace($regex, "$1\n\n<$2$3", $text);
    
    $text = self::reverse_process_parser_hints($text);
    $text = self::reverse_process_headings($text);
    $text = self::reverse_process_lists($text);
    $text = self::reverse_process_tables($text);
    
    // Lastly, strip out paragraph tags.
    $text = preg_replace('|^ *<p>(.+?)</p> *$|m', "\\1", $text);
    
    return $text;
  }
  
  public static function reverse_process_parser_hints($text)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !preg_match_all('|<!--#([a-z0-9_]+)(?: (.+?))?-->([\w\W]*?)<!--#/\\1-->|s', $text, $matches) )
      return $text;
    
    foreach ( $matches[0] as $i => $match )
    {
      $tag =& $matches[1][$i];
      $attribs =& $matches[2][$i];
      $inner =& $matches[3][$i];
      
      $attribs = self::reverse_process_hint_attribs($attribs);
      switch($tag)
      {
        case 'smiley':
        case 'internallink':
        case 'imagelink':
          if ( isset($attribs['code']) )
          {
            $text = str_replace($match, $attribs['code'], $text);
          }
          else if ( isset($attribs['src']) )
          {
            $text = str_replace($match, $attribs['src'], $text);
          }
          break;
      }
    }
    
    return $text;
  }
  
  public static function reverse_process_hint_attribs($attribs)
  {
    $return = array();
    if ( !preg_match_all('/([a-z0-9_-]+)="([^"]+?)"/', $attribs, $matches) )
      return array();
    
    foreach ( $matches[0] as $i => $match )
    {
      $name =& $matches[1][$i];
      $value =& $matches[2][$i];
      
      $value = base64_decode($value);
      
      $return[$name] = $value;
    }
    
    return $return;
  }
  
  /**
   * Escapes a string so that it's safe to use as an attribute in a parser hint.
   * @param string
   * @return string
   */
  
  public static function escape_parser_hint_attrib($text)
  {
    return base64_encode($text);
  }
  
  public static function reverse_process_headings($text)
  {
    if ( !preg_match_all('|^<h([1-6])(?: id="toc[0-9]+")?>(.*?)</h\\1>$|m', $text, $matches) )
      return $text;
    
    foreach ( $matches[0] as $i => $match )
    {
      // generate heading tag
      $heading_size = intval($matches[1][$i]);
      $eq = '';
      for ( $j = 0; $j < $heading_size; $j++ )
        $eq .= '=';
      
      $heading =& $matches[2][$i];
      
      $tag = "$eq $heading $eq";
      $text = str_replace($match, $tag, $text);
    }
    
    return $text;
  }
  
  public static function reverse_process_lists($text)
  {
    if ( !preg_match('!(</?(?:ul|ol|li)>)!', $text) )
      return $text;
    
    $split = preg_split('!(</?(?:ul|ol|li)>)!', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $stack_height = 0;
    $current_list = '';
    $old_current_list = '';
    $spaces = '';
    $marker = '*';
    $list_id = 0;
    $just_terminated = false;
    foreach ( $split as $tag )
    {
      switch($tag) 
      {
        case '<ul>':
        case '<ol>':
          $stack_height++;
          $just_terminated = false;
          if ( $stack_height > 1 )
            $spaces .= $marker;
          
          $marker = ( $tag == 'ol' ) ? '#' : '*';
          if ( $stack_height > 1 )
            $current_list .= "\n";
          
          break;
        case '</ul>':
        case '</ol>':
          $stack_height--;
          $spaces = substr($spaces, 1);
          
          if ( $stack_height == 0 )
          {
            // rotate
            $text = str_replace_once("{$old_current_list}{$tag}", trim($current_list), $text);
            $current_list = '';
            $old_current_list = '';
          }
          $just_terminated = true;
          break;
        case '<li>':
          if ( $stack_height < 1 )
            break;
          
          $current_list .= "{$spaces}{$marker} ";
          break;
        case '</li>':
          if ( $stack_height < 1 )
            break;
          
          if ( !$just_terminated )
            $current_list .= "\n";
          
          $just_terminated = false;
          break;
        default:
          if ( $stack_height > 0 )
          {
            $current_list .= trim($tag);
          }
          break;
      }
      if ( $stack_height > 0 )
      {
        $old_current_list .= $tag;
      }
    }
    
    return $text;
  }
  
  public static function reverse_process_tables($text)
  {
    return $text;
  }
  
  /**
   * Parses internal links (wikilinks) in a block of text.
   * @param string Text to process
   * @param string Optional. If included will be used as a template instead of using the default syntax.
   * @param bool If false, does not add wikilink-nonexistent or check for exsistence of pages. Can reduce DB queries; defualts to true.
   * @return string
   */
  
  public static function parse_internal_links($text, $tplcode = false, $do_exist_check = true)
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
      $exists = ( ($do_exist_check && isPage($pid_clean)) || !$do_exist_check ) ? '' : ' class="wikilink-nonexistent"';
      
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
        $omatch = self::escape_parser_hint_attrib($match);
        $link = "<!--#internallink src=\"$omatch\" --><a href={$quot}{$url}{$quot}{$exists}>{$inner_text}</a><!--#/internallink-->";
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
      $exists = ( ($do_exist_check && isPage($pid_clean)) || !$do_exist_check ) ? '' : ' class="wikilink-nonexistent"';
      
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
        $omatch = self::escape_parser_hint_attrib($match);
        $link = "<!--#internallink src=\"$omatch\" --><a href={$quot}{$url}{$quot}{$exists}>{$inner_text}</a><!--#/internallink-->";
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
    
    // gently apply some reverse-processing to allow Text_Wiki to do magic with TOCs and stuff
    $text = self::reverse_process_headings($text);
    
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
    
    // Strip out <nowiki> sections
    //return '<pre>'.htmlspecialchars($text).'</pre>';
    $nw = preg_match_all('#<nowiki>(.*?)<\/nowiki>#is', $text, $nowiki);
    
    for ( $i = 0; $i < count($nowiki[1]); $i++ )
    {
      $text = str_replace('<nowiki>' . $nowiki[1][$i] . '</nowiki>', '{NOWIKI:'.$random_id.':'.$i.'}', $text);
    }
    
    foreach ( $smileys as $smiley => $smiley_path )
    {
      $hex_smiley = hexencode($smiley, '&#x', ';');
      $pfx = ( $complete_urls ) ? get_server_url() : '';
      $text = str_replace(' ' . $smiley,
          ' <!--#smiley code="' . self::escape_parser_hint_attrib($smiley) . '"--><nowiki>
           <!-- The above is a reverse-parser hint -->
             <img title="' . $hex_smiley . '" alt="' . $hex_smiley . '" src="' . $pfx . scriptPath . '/images/smilies/' . $smiley_path . '"
              style="border: 0;" />
           </nowiki><!--#/smiley-->', $text);
    }
    //*/
    
    // Reinsert <nowiki> sections
    for ( $i = 0; $i < $nw; $i++ )
    {
      $text = str_replace('{NOWIKI:'.$random_id.':'.$i.'}', '<nowiki>'.$nowiki[1][$i].'</nowiki>', $text);
    }
    
    return $text;
  }
  
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
            // this hook requires plugins to return true if they modified anything
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
        $text = str_replace($full_tag, '[[' . $paths->nslist['File'] . $filename . ']]', $text);
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
      
      $s_full_tag = self::escape_parser_hint_attrib($full_tag);
      $complete_tag = '<!--#imagelink src="' . $s_full_tag . '" -->';
      
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
      
      $complete_tag .= "<!--#/imagelink-->";
      $taglist[$i] = $complete_tag;
      
      /*
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

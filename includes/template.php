<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
class template
{
  var $tpl_strings, $tpl_bool, $vars_assign_history, $theme, $style, $no_headers, $additional_headers, $sidebar_extra, $sidebar_widgets, $toolbar_menu, $theme_list, $named_theme_list, $default_theme, $default_style, $plugin_blocks, $namespace_string, $style_list, $theme_loaded, $initted_to_page_id, $initted_to_namespace;
  
  var $initted_to_theme = array(
      'theme' => false,
      'style' => false
    );
  
  /**
   * The list of themes that are critical for Enano operation. This doesn't include oxygen which
   * remains a user theme. By default this is admin and printable which have to be loaded on demand.
   * @var array
   */
  
  var $system_themes = array('admin', 'printable');
  
  /**
   * Set to true if the site is disabled and thus a message needs to be shown. This should ONLY be changed by common.php.
   * @var bool
   * @access private
   */
  
  var $site_disabled = false;
  
  /**
   * One of the absolute best parts of Enano :-P
   * @var string
   */
  
  var $fading_button = '';
  
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->tpl_bool    = Array();
    $this->tpl_strings = Array();
    $this->sidebar_extra = '';
    $this->toolbar_menu = '';
    $this->additional_headers = '';
    $this->plugin_blocks = Array();
    $this->theme_loaded = false;
    
    $this->theme_list = Array();
    $this->named_theme_list = Array();
    
    $this->vars_assign_history = array(
        'strings' => array(),
        'bool' => array()
      );
    
    if ( defined('IN_ENANO_UPGRADE') )
    {
      return $this->construct_compat();
    }
    
    $q = $db->sql_query('SELECT theme_id, theme_name, enabled, default_style, group_policy, group_list FROM ' . table_prefix . 'themes;');
    if ( !$q )
      $db->_die('template.php selecting theme list');
    
    $i = 0;
    while ( $row = $db->fetchrow() )
    {
      $this->theme_list[$i] = $row;
      $i++;
    }
    unset($theme);
    $this->theme_list = array_values($this->theme_list);
    // Create associative array of themes
    foreach ( $this->theme_list as $i => &$theme )
      $this->named_theme_list[ $theme['theme_id'] ] =& $this->theme_list[$i];
    
    $this->default_theme = ( $_ = getConfig('theme_default') ) ? $_ : $this->theme_list[0]['theme_id'];
    $this->named_theme_list[ $this->default_theme ]['css'] = $this->get_theme_css_files($this->default_theme);
    // Come up with the default style. If the CSS file specified in default_style exists, we're good, just
    // use that. Otherwise, use the first stylesheet that comes to mind.
    $df_data =& $this->named_theme_list[ $this->default_theme ];
    $this->default_style = ( in_array($df_data['default_style'], $df_data['css']) ) ? $df_data['default_style'] : $df_data['css'][0];
  }
  
  /**
   * Gets the list of available CSS files (styles) for the specified theme.
   * @param string Theme ID
   * @return array
   */
  
  function get_theme_css_files($theme_id)
  {
    $css = array();
    $dir = ENANO_ROOT . "/themes/{$theme_id}/css";
    if ( $dh = @opendir($dir) )
    {
      while ( ( $file = @readdir($dh) ) !== false )
      {
        if ( preg_match('/\.css$/', $file) )
          $css[] = preg_replace('/\.css$/', '', $file);
      }
      closedir($dh);
    }
    // No CSS files? If so, nuke it.
    if ( count($css) < 1 )
    {
      unset($this->theme_list[$theme_id]);
    }
    return $css;
  }
  
  /**
   * Failsafe constructor for upgrades.
   */
  
  function construct_compat()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->tpl_bool    = Array();
    $this->tpl_strings = Array();
    $this->sidebar_extra = '';
    $this->toolbar_menu = '';
    $this->additional_headers = '';
    $this->plugin_blocks = Array();
    $this->theme_loaded = false;
    
    $this->fading_button = '<div style="background-image: url('.scriptPath.'/images/about-powered-enano-hover.png); background-repeat: no-repeat; width: 88px; height: 31px; margin: 0 auto 5px auto;">
                              <a style="background-image: none; padding-right: 0;" href="http://enanocms.org/" onclick="window.open(this.href); return false;"><img style="border-width: 0;" alt=" " src="'.scriptPath.'/images/about-powered-enano.png" onmouseover="domOpacity(this, 100, 0, 500);" onmouseout="domOpacity(this, 0, 100, 500);" /></a>
                            </div>';
    
    $this->theme_list = Array();
    $this->named_theme_list = Array();
    
    $q = $db->sql_query('SELECT theme_id, theme_name, enabled, default_style FROM ' . table_prefix . 'themes;');
    if ( !$q )
      $db->_die('template.php selecting theme list');
    
    $i = 0;
    while ( $row = $db->fetchrow() )
    {
      $this->theme_list[$i] = $row;
      $i++;
    }
    // List out all CSS files for this theme
    foreach ( $this->theme_list as $i => &$theme )
    {
      $theme['css'] = array();
      $dir = ENANO_ROOT . "/themes/{$theme['theme_id']}/css";
      if ( $dh = @opendir($dir) )
      {
        while ( ( $file = @readdir($dh) ) !== false )
        {
          if ( preg_match('/\.css$/', $file) )
            $theme['css'][] = preg_replace('/\.css$/', '', $file);
        }
        closedir($dh);
      }
      // No CSS files? If so, nuke it.
      if ( count($theme['css']) < 1 )
      {
        unset($this->theme_list[$i]);
      }
    }
    $this->theme_list = array_values($this->theme_list);
    // Create associative array of themes
    foreach ( $this->theme_list as $i => &$theme )
      $this->named_theme_list[ $theme['theme_id'] ] =& $this->theme_list[$i];
    
    $this->default_theme = ( $_ = getConfig('theme_default') ) ? $_ : $this->theme_list[0]['theme_id'];
    // Come up with the default style. If the CSS file specified in default_style exists, we're good, just
    // use that. Otherwise, use the first stylesheet that comes to mind.
    $df_data =& $this->named_theme_list[ $this->default_theme ];
    $this->default_style = ( in_array($df_data['default_style'], $df_data['css']) ) ? $df_data['default_style'] : $df_data['css'][0];
  }
  
  /**
   * Systematically deletes themes if they're blocked by theme security settings. Called when session->start() finishes.
   */
  
  function process_theme_acls()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    // generate the fading button - needs to be done after sessions are started
    $admintitle = ( $session->user_level >= USER_LEVEL_ADMIN && is_object(@$lang) ) ? ' title="' . $lang->get('sidebar_btn_enanopowered_admin_tip') . '"' : '';
    $this->fading_button = '<div style="background-image: url('.cdnPath.'/images/about-powered-enano-hover.png); background-repeat: no-repeat; width: 88px; height: 31px; margin: 0 auto 5px auto;">
                              <a style="background-image: none; padding-right: 0;" href="http://enanocms.org/" onclick="window.open(this.href); return false;"' . $admintitle . '><img style="border-width: 0;" alt=" " src="'.cdnPath.'/images/about-powered-enano.png" onmouseover="domOpacity(this, 100, 0, 500);" onmouseout="domOpacity(this, 0, 100, 500);" /></a>
                            </div>';
    
    // For each theme, check ACLs and delete from RAM if not authorized
    foreach ( $this->theme_list as $i => $theme )
    {
      if ( !@$theme['group_list'] )
        continue;
      if ( $theme['theme_id'] === getConfig('theme_default') )
        continue;
      switch ( $theme['group_policy'] )
      {
        case 'allow_all':
          // Unconditionally allowed
          continue;
          break;
        case 'whitelist':
          // If we're not on the list, off to the left please
          $list = enano_json_decode($theme['group_list']);
          $allowed = false;
          foreach ( $list as $acl )
          {
            if ( !preg_match('/^(u|g):([0-9]+)$/', $acl, $match) )
              // Invalid list entry, silently allow (maybe not a good idea but
              // really, these things are checked before they're inserted)
              continue 2;
            $mode = $match[1];
            $id = intval($match[2]);
            switch ( $mode )
            {
              case 'u':
                $allowed = ( $id == $session->user_id );
                if ( $allowed )
                  break 2;
                break;
              case 'g':
                $allowed = ( isset($session->groups[$id]) );
                if ( $allowed )
                  break 2;
            }
          }
          if ( !$allowed )
          {
            unset($this->theme_list[$i]);
          }
          break;
        case 'blacklist':
          // If we're ON the list, off to the left please
          $list = enano_json_decode($theme['group_list']);
          $allowed = true;
          foreach ( $list as $acl )
          {
            if ( !preg_match('/^(u|g):([0-9]+)$/', $acl, $match) )
              // Invalid list entry, silently allow (maybe not a good idea but
              // really, these things are checked before they're inserted)
              continue 2;
            $mode = $match[1];
            $id = intval($match[2]);
            switch ( $mode )
            {
              case 'u':
                $allowed = ( $id != $session->user_id );
                if ( !$allowed )
                  break 2;
                break;
              case 'g':
                $allowed = ( !isset($session->groups[$id]) );
                if ( !$allowed )
                  break 2;
            }
          }
          if ( !$allowed )
          {
            unset($this->theme_list[$i]);
          }
          break;
      }
    }
    
    $this->theme_list = array_values($this->theme_list);
    
    // Rebuild associative theme list
    $this->named_theme_list = array();
    foreach ( $this->theme_list as $i => &$theme )
      $this->named_theme_list[ $theme['theme_id'] ] =& $this->theme_list[$i];
  }
  
  function sidebar_widget($t, $h, $use_normal_section = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!defined('ENANO_TEMPLATE_LOADED'))
    {
      $this->load_theme($session->theme, $session->style);
    }
    if(!$this->sidebar_widgets)
      $this->sidebar_widgets = '';
    $tplvars = $this->extract_vars('elements.tpl');
    
    if ( $use_normal_section )
    {
      $parser = $this->makeParserText($tplvars['sidebar_section']);
    }
    else
    {
      $parser = $this->makeParserText($tplvars['sidebar_section_raw']);
    }
    
    $parser->assign_vars(Array('TITLE' => '{TITLE}','CONTENT' => $h));
    $this->plugin_blocks[$t] = $parser->run();
    $this->sidebar_widgets .= $parser->run();
  }
  function add_header($html)
  {
    /* debug only **
    $bt = debug_backtrace();
    $bt = $bt[1];
    $this->additional_headers .= "\n    <!-- {$bt['file']}:{$bt['line']} -->\n    " . $html;
    */
    $this->additional_headers .= "\n   " . $html;
  }
  function get_css($s = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!defined('ENANO_TEMPLATE_LOADED'))
      $this->load_theme($session->theme, $session->style);
    $path = ( $s ) ? 'css/'.$s : 'css/'.$this->style.'.css';
    if ( !file_exists(ENANO_ROOT . '/themes/' . $this->theme . '/' . $path) )
    {
      echo "/* WARNING: Falling back to default file because file $path does not exist */\n";
      $path = 'css/' . $this->style_list[0] . '.css';
    }
    return '<enano:no-opt>' . $this->process_template($path) . '</enano:no-opt>';
  }
  function load_theme($name = false, $css = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->theme = ( $name ) ? $name : $session->theme;
    $this->style = ( $css ) ? $css : $session->style;
    if ( !$this->theme )
    {
      $this->theme = $this->theme_list[0]['theme_id'];
      $this->style = preg_replace('/\.css$/', '', $this->theme_list[0]['default_style']);
    }
    // Make sure we're allowed to use this theme.
    if ( (
        // If it was removed, it's probably blocked by an ACL, or it was uninstalled
        !isset($this->named_theme_list[$this->theme]) ||
        // Check if the theme is disabled
        ( isset($this->named_theme_list[$this->theme]) && $this->named_theme_list[$this->theme]['enabled'] == 0 ) )
        // Above all, if it's a system theme, don't inhibit the loading process.
        && !in_array($this->theme, $this->system_themes)
      )
    {
      // No, something is preventing it - fall back to site default
      $this->theme = $this->default_theme;
      
      // Come up with the default style. If the CSS file specified in default_style exists, we're good, just
      // use that. Otherwise, use the first stylesheet that comes to mind.
      $df_data =& $this->named_theme_list[ $this->theme ];
      $this->style = ( in_array($df_data['default_style'], $df_data['css']) ) ? $df_data['default_style'] : $df_data['css'][0];
    }
    // The list of styles for the currently selected theme
    $this->style_list =& $this->named_theme_list[ $this->theme ]['css'];
    $this->theme_loaded = true;
  }
  
  /**
   * Initializes all variables related to on-page content. This includes sidebars and what have you.
   * @param object Optional PageProcessor object to use for passing metadata and permissions on. If omitted, uses information from $paths and $session.
   * @param bool If true, re-inits even if already initted with this page_id and namespace
   */
  
  function init_vars($page = false, $force_init = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $email;
    global $lang;
    
    if(!$this->theme || !$this->style)
    {
      $this->load_theme();
    }
    
    if ( defined('ENANO_TEMPLATE_LOADED') )
    {
      // trigger_error("\$template->init_vars() called more than once", E_USER_WARNING);
      // die_semicritical('Illegal call', '<p>$template->init_vars() was called multiple times, this is not supposed to happen. Exiting with fatal error.</p>');
    }
    else
    {
      @define('ENANO_TEMPLATE_LOADED', '');
    }
    
    if ( is_object($page) && @get_class($page) == 'PageProcessor' )
    {
      $page_append = substr($paths->fullpage, strlen($paths->page));
      if ( isset($paths->nslist[$page->namespace]) )
      {
        $local_page = $paths->nslist[$page->namespace] . $page->page_id;
      }
      else
      {
        $local_page = $page->namespace . substr($paths->nslist['Special'], -1) . $page->page_id . $page_append;
      }
      $local_fullpage = $local_page . $page_append;
      $local_page_id =& $page->page_id;
      $local_namespace =& $page->namespace;
      $local_page_exists =& $page->page_exists;
      $perms =& $page->perms;
    }
    else
    {
      $local_page =& $paths->page;
      $local_page_id =& $paths->page_id;
      $local_fullpage =& $paths->fullpage;
      $local_namespace =& $paths->namespace;
      $local_page_exists =& $paths->page_exists;
      $local_page_protected =& $paths->page_protected;
      $perms =& $session;
    }
    
    if ( $local_page_id === $this->initted_to_page_id && $local_namespace === $this->initted_to_namespace && $this->theme === $this->initted_to_theme['theme'] && $this->style === $this->initted_to_theme['style'] && !$force_init )
    {
      // we're already initted with this page.
      return true;
    }
    
    profiler_log("template: starting var init");
    
    $this->initted_to_page_id = $local_page_id;
    $this->initted_to_namespace = $local_namespace;
    $this->initted_to_theme = array(
        'theme' => $this->theme,
        'style' => $this->style
      );
    
    if ( $local_page_exists && isset($paths->pages[$local_page]) )
    {
      $local_cdata =& $paths->pages[$local_page];
    }
    else
    {
      // if the page doesn't exist but we're trying to load it, it was requested manually and $paths->cpage should match it.
      if ( $paths->page_id == $local_page_id )
      {
        // load metadata from cpage
        $local_cdata =& $paths->cpage;
      }
      else
      {
        // generate our own failsafe metadata
        $local_cdata = array(
            'urlname' => $local_page,
            'urlname_nons' => $local_page_id,
            'namespace' => $local_namespace,
            'name' => get_page_title_ns($local_page_id, $local_namespace),
            'comments_on' => 0,
            'protected' => 0,
            'wiki_mode' => 2,
            'delvotes' => 0,
            'delvote_ips' => serialize(array())
          );
      }
    }
    
    // calculate protection
    if ( !isset($local_page_protected) )
    {
      if ( $local_cdata['protected'] == 0 )
      {
        $local_page_protected = false;
      }
      else if ( $local_cdata['protected'] == 1 )
      {
        $local_page_protected = true;
      }
      else if ( $local_cdata['protected'] == 2 )
      {
        if (
             ( !$session->user_logged_in || // Is the user logged in?
               ( $session->user_logged_in && $session->reg_time + ( 4 * 86400 ) >= time() ) ) // If so, have they been registered for 4 days?
             && !$perms->get_permissions('even_when_protected') ) // And of course, is there an ACL that overrides semi-protection?
        {
          $local_page_protected = true;
        }
        else
        {
          $local_page_protected = false;
        }
      }
    }
    
    $tplvars = $this->extract_vars('elements.tpl');
    
    if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE'))
    {
      $this->add_header('
        <!--[if lt IE 7]>
        <script language="JavaScript">
        function correctPNG() // correctly handle PNG transparency in Win IE 5.5 & 6.
        {
           var arVersion = navigator.appVersion.split("MSIE");
           var version = parseFloat(arVersion[1]);
           if (version >= 5.5 && typeof(document.body.filters) == "object")
           {
              for(var i=0; i<document.images.length; i++)
              {
                 var img = document.images[i];
                 continue;
                 var imgName = img.src.toUpperCase();
                 if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
                 {
                    var imgID = (img.id) ? "id=\'" + img.id + "\' " : "";
                    var imgClass = (img.className) ? "class=\'" + img.className + "\' " : "";
                    var imgTitle = (img.title) ? "title=\'" + img.title + "\' " : "title=\'" + img.alt + "\' ";
                    var imgStyle = "display:inline-block;" + img.style.cssText;
                    if (img.align == "left") imgStyle = "float:left;" + imgStyle;
                    if (img.align == "right") imgStyle = "float:right;" + imgStyle;
                    if (img.parentElement.href) imgStyle = "cursor:hand;" + imgStyle;
                    var strNewHTML = "<span " + imgID + imgClass + imgTitle + " style=\\"" + "width:" + img.width + "px; height:" + img.height + "px;" + imgStyle + ";" + "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader" + "(src=\\\'" + img.src + "\\\', sizingMethod=\'scale\');\\"></span>";
                    img.outerHTML = strNewHTML;
                    i = i-1;
                 }
              }
           }   
        }
        window.attachEvent("onload", correctPNG);
        </script>
        <![endif]-->
        ');
    }
    
    // Get the "article" button text (depends on namespace)
    switch($local_namespace) {
      case "Article":
      default:
        $ns = $lang->get('onpage_lbl_page_article');
        break;
      case "Admin":
        $ns = $lang->get('onpage_lbl_page_admin');
        break;
      case "System":
        $ns = $lang->get('onpage_lbl_page_system');
        break;
      case "File":
        $ns = $lang->get('onpage_lbl_page_file');
        break;
      case "Help":
        $ns = $lang->get('onpage_lbl_page_help');
        break;
      case "User":
        $ns = $lang->get('onpage_lbl_page_user');
        break;
      case "Special":
        $ns = $lang->get('onpage_lbl_page_special');
        break;
      case "Template":
        $ns = $lang->get('onpage_lbl_page_template');
        break;
      case "Project":
        $ns = $lang->get('onpage_lbl_page_project');
        break;
      case "Category":
        $ns = $lang->get('onpage_lbl_page_category');
        break;
      case "API":
        $ns = $lang->get('onpage_lbl_page_external');
        break;
    }
    $this->namespace_string = $ns;
    unset($ns);
    $code = $plugins->setHook('page_type_string_set');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    $ns =& $this->namespace_string;
    
    //
    // PAGE TOOLBAR (on-page controls/actions)
    //
    
    // Initialize the toolbar
    $tb = '';
    
    // Create "xx page" button
    
    $btn_selected = ( isset($tplvars['toolbar_button_selected'])) ? $tplvars['toolbar_button_selected'] : $tplvars['toolbar_button'];
    $parser = $this->makeParserText($btn_selected);
    
    $parser->assign_vars(array(
        'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxReset()); return false; }" title="' . $lang->get('onpage_tip_article') . '" accesskey="a"',
        'PARENTFLAGS' => 'id="mdgToolbar_article"',
        'HREF' => makeUrl($local_page, null, true),
        'TEXT' => $this->namespace_string
      ));
    
    $tb .= $parser->run();
    
    $button = $this->makeParserText($tplvars['toolbar_button']);
    
    // Page toolbar
    // Comments button
    if ( $perms->get_permissions('read') && getConfig('enable_comments')=='1' && $local_cdata['comments_on'] == 1 )
    {
      
      $e = $db->sql_query('SELECT approved FROM '.table_prefix.'comments WHERE page_id=\''.$local_page_id.'\' AND namespace=\''.$local_namespace.'\';');
      if ( !$e )
      {
        $db->_die();
      }
      $nc = $db->numrows();
      $nu = 0;
      $na = 0;
      
      while ( $r = $db->fetchrow() )
      {  
        if ( !$r['approved'] )
        {
          $nu++;
        }
        else
        {
          $na++;
        }
      }
      
      $db->free_result();
      $n = ( $session->check_acl_scope('mod_comments', $local_namespace) && $perms->get_permissions('mod_comments') ) ? (string)$nc : (string)$na;
      if ( $session->check_acl_scope('mod_comments', $local_namespace) && $perms->get_permissions('mod_comments') && $nu > 0 )
      {
        $subst = array(
            'num_comments' => $nc,
            'num_unapp' => $nu
          );
        $btn_text = $lang->get('onpage_btn_discussion_unapp', $subst);
      }
      else
      {
        $subst = array(
          'num_comments' => $nc
        );
        $btn_text = $lang->get('onpage_btn_discussion', $subst);
      }
      
      $button->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxComments()); return false; }" title="' . $lang->get('onpage_tip_comments') . '" accesskey="c"',
          'PARENTFLAGS' => 'id="mdgToolbar_discussion"',
          'HREF' => makeUrl($local_page, 'do=comments', true),
          'TEXT' => $btn_text,
        ));
      
      $tb .= $button->run();
    }
    // Edit button
    if($perms->get_permissions('read') && $session->check_acl_scope('edit_page', $local_namespace) && ( $perms->get_permissions('edit_page') && ( ( $paths->page_protected && $perms->get_permissions('even_when_protected') ) || !$paths->page_protected ) ) )
    {
      $button->assign_vars(array(
        'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxEditor()); return false; }" title="' . $lang->get('onpage_tip_edit') . '" accesskey="e"',
        'PARENTFLAGS' => 'id="mdgToolbar_edit"',
        'HREF' => makeUrl($local_page, 'do=edit', true),
        'TEXT' => $lang->get('onpage_btn_edit')
        ));
      $tb .= $button->run();
    // View source button
    }
    else if ( $session->check_acl_scope('view_source', $local_namespace) && $perms->get_permissions('view_source') && ( !$perms->get_permissions('edit_page') || !$perms->get_permissions('even_when_protected') && $paths->page_protected ) && $local_namespace != 'API') 
    {
      $button->assign_vars(array(
        'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxEditor()); return false; }" title="' . $lang->get('onpage_tip_viewsource') . '" accesskey="e"',
        'PARENTFLAGS' => 'id="mdgToolbar_edit"',
        'HREF' => makeUrl($local_page, 'do=viewsource', true),
        'TEXT' => $lang->get('onpage_btn_viewsource')
        ));
      $tb .= $button->run();
    }
    // History button
    if ( $perms->get_permissions('read') && $session->check_acl_scope('history_view', $local_namespace) && $local_page_exists && $perms->get_permissions('history_view') )
    {
      $button->assign_vars(array(
        'FLAGS'       => 'onclick="if ( !KILL_SWITCH ) { void(ajaxHistory()); return false; }" title="' . $lang->get('onpage_tip_history') . '" accesskey="h"',
        'PARENTFLAGS' => 'id="mdgToolbar_history"',
        'HREF'        => makeUrl($local_page, 'do=history', true),
        'TEXT'        => $lang->get('onpage_btn_history')
        ));
      $tb .= $button->run();
    }
    
    $menubtn = $this->makeParserText($tplvars['toolbar_menu_button']);
    
    // Additional actions menu
    // Rename button
    if ( $perms->get_permissions('read') && $session->check_acl_scope('rename', $local_namespace) && $local_page_exists && ( $perms->get_permissions('rename') && ( $paths->page_protected && $perms->get_permissions('even_when_protected') || !$paths->page_protected ) ) )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxRename()); return false; }" title="' . $lang->get('onpage_tip_rename') . '" accesskey="r"',
          'HREF'  => makeUrl($local_page, 'do=rename', true),
          'TEXT'  => $lang->get('onpage_btn_rename'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Vote-to-delete button
    if ( $paths->wiki_mode && $session->check_acl_scope('vote_delete', $local_namespace) && $perms->get_permissions('vote_delete') && $local_page_exists)
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxDelVote()); return false; }" title="' . $lang->get('onpage_tip_delvote') . '" accesskey="d"',
          'HREF'  => makeUrl($local_page, 'do=delvote', true),
          'TEXT'  => $lang->get('onpage_btn_votedelete'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Clear-votes button
    if ( $perms->get_permissions('read') && $session->check_acl_scope('vote_reset', $local_namespace) && $paths->wiki_mode && $local_page_exists && $perms->get_permissions('vote_reset') && $local_cdata['delvotes'] > 0)
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxResetDelVotes()); return false; }" title="' . $lang->get('onpage_tip_resetvotes') . '" accesskey="y"',
          'HREF'  => makeUrl($local_page, 'do=resetvotes', true),
          'TEXT'  => $lang->get('onpage_btn_votedelete_reset'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Printable page button
    if ( $local_page_exists )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'title="' . $lang->get('onpage_tip_printable') . '"',
          'HREF'  => makeUrl($local_page, 'printable=yes', true),
          'TEXT'  => $lang->get('onpage_btn_printable'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Protect button
    if($perms->get_permissions('read') && $session->check_acl_scope('protect', $local_namespace) && $paths->wiki_mode && $local_page_exists && $perms->get_permissions('protect'))
    {
      
      $label = $this->makeParserText($tplvars['toolbar_label']);
      $label->assign_vars(array('TEXT' => $lang->get('onpage_lbl_protect')));
      $t0 = $label->run();
      
      $ctmp = ''; 
      if ( $local_cdata['protected'] == 1 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'accesskey="i" onclick="if ( !KILL_SWITCH ) { ajaxProtect(1); return false; }" id="protbtn_1" title="' . $lang->get('onpage_tip_protect_on') . '"'.$ctmp,
          'HREF'  => makeUrl($local_page, 'do=protect&level=1', true),
          'TEXT'  => $lang->get('onpage_btn_protect_on')
        ));
      $t1 = $menubtn->run();
      
      $ctmp = '';
      if ( $local_cdata['protected'] == 0 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'accesskey="o" onclick="if ( !KILL_SWITCH ) { ajaxProtect(0); return false; }" id="protbtn_0" title="' . $lang->get('onpage_tip_protect_off') . '"'.$ctmp,
          'HREF'  => makeUrl($local_page, 'do=protect&level=0', true),
          'TEXT'  => $lang->get('onpage_btn_protect_off')
        ));
      $t2 = $menubtn->run();
      
      $ctmp = '';
      if ( $local_cdata['protected'] == 2 )
      {
        $ctmp = ' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'accesskey="p" onclick="if ( !KILL_SWITCH ) { ajaxProtect(2); return false; }" id="protbtn_2" title="' . $lang->get('onpage_tip_protect_semi') . '"'.$ctmp,
          'HREF'  => makeUrl($local_page, 'do=protect&level=2', true),
          'TEXT'  => $lang->get('onpage_btn_protect_semi')
        ));
      $t3 = $menubtn->run();
      
      $this->toolbar_menu .= '        <table border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td>'.$t0.'</td>
            <td>'.$t1.'</td>
            <td>'.$t2.'</td>
            <td>'.$t3.'</td>
          </tr>
        </table>';
    }
    
    // Wiki mode button
    if($perms->get_permissions('read') && $session->check_acl_scope('set_wiki_mode', $local_namespace) && $local_page_exists && $perms->get_permissions('set_wiki_mode'))
    {
      // label at start
      $label = $this->makeParserText($tplvars['toolbar_label']);
      $label->assign_vars(array('TEXT' => $lang->get('onpage_lbl_wikimode')));
      $t0 = $label->run();
      
      // on button
      $ctmp = '';
      if ( $local_cdata['wiki_mode'] == 1 )
      {
        $ctmp = ' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => /* 'onclick="if ( !KILL_SWITCH ) { ajaxSetWikiMode(1); return false; }" id="wikibtn_1" title="Forces wiki functions to be allowed on this page."'. */ $ctmp,
          'HREF' => makeUrl($local_page, 'do=setwikimode&level=1', true),
          'TEXT' => $lang->get('onpage_btn_wikimode_on')
        ));
      $t1 = $menubtn->run();
      
      // off button
      $ctmp = '';
      if ( $local_cdata['wiki_mode'] == 0 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => /* 'onclick="if ( !KILL_SWITCH ) { ajaxSetWikiMode(0); return false; }" id="wikibtn_0" title="Forces wiki functions to be disabled on this page."'. */ $ctmp,
          'HREF' => makeUrl($local_page, 'do=setwikimode&level=0', true),
          'TEXT' => $lang->get('onpage_btn_wikimode_off')
        ));
      $t2 = $menubtn->run();
      
      // global button
      $ctmp = ''; 
      if ( $local_cdata['wiki_mode'] == 2 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => /* 'onclick="if ( !KILL_SWITCH ) { ajaxSetWikiMode(2); return false; }" id="wikibtn_2" title="Causes this page to use the global wiki mode setting (default)"'. */ $ctmp,
          'HREF' => makeUrl($local_page, 'do=setwikimode&level=2', true),
          'TEXT' => $lang->get('onpage_btn_wikimode_global')
        ));
      $t3 = $menubtn->run();
      
      // Tack it onto the list of buttons that are already there...
      $this->toolbar_menu .= '        <table border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td>'.$t0.'</td>
            <td>'.$t1.'</td>
            <td>'.$t2.'</td>
            <td>'.$t3.'</td>
          </tr>
        </table>';
    }
    
    // Clear logs button
    if ( $perms->get_permissions('read') && $session->check_acl_scope('clear_logs', $local_namespace) && $perms->get_permissions('clear_logs') )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxClearLogs()); return false; }" title="' . $lang->get('onpage_tip_flushlogs') . '" accesskey="l"',
          'HREF'  => makeUrl($local_page, 'do=flushlogs', true),
          'TEXT'  => $lang->get('onpage_btn_clearlogs'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Delete page button
    if ( $perms->get_permissions('read') && $session->check_acl_scope('delete_page', $local_namespace) && $perms->get_permissions('delete_page') && $local_page_exists )
    {
      $s = $lang->get('onpage_btn_deletepage');
      if ( $local_cdata['delvotes'] == 1 )
      {
        $subst = array(
          'num_votes' => $local_cdata['delvotes'],
          'plural' => ''
          );
        $s .= $lang->get('onpage_btn_deletepage_votes', $subst);
      }
      else if ( $local_cdata['delvotes'] > 1 )
      {
        $subst = array(
          'num_votes' => $local_cdata['delvotes'],
          'plural' => $lang->get('meta_plural')
          );
        $s .= $lang->get('onpage_btn_deletepage_votes', $subst);
      }
      
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxDeletePage()); return false; }" title="' . $lang->get('onpage_tip_deletepage') . '" accesskey="k"',
          'HREF'  => makeUrl($local_page, 'do=deletepage', true),
          'TEXT'  => $s,
        ));
      $this->toolbar_menu .= $menubtn->run();
      
    }
    
    // Password-protect button
    if(isset($local_cdata['password']) && $session->check_acl_scope('password_set', $local_namespace) && $session->check_acl_scope('password_reset', $local_namespace))
    {
      if ( $local_cdata['password'] == '' )
      {
        $a = $perms->get_permissions('password_set');
      }
      else
      {
        $a = $perms->get_permissions('password_reset');
      }
    }
    else if ( $session->check_acl_scope('password_set', $local_namespace) )
    {
      $a = $perms->get_permissions('password_set');
    }
    else
    {
      $a = false;
    }
    if ( $a && $perms->get_permissions('read') && $local_page_exists )
    {
      // label at start
      $label = $this->makeParserText($tplvars['toolbar_label']);
      $label->assign_vars(array('TEXT' => $lang->get('onpage_lbl_password')));
      $t0 = $label->run();
      
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxSetPassword()); return false; }" title="' . $lang->get('onpage_tip_password') . '"',
          'HREF'  => '#',
          'TEXT'  => $lang->get('onpage_btn_password_set'),
        ));
      $t = $menubtn->run();
      
      $this->toolbar_menu .= '<table border="0" cellspacing="0" cellpadding="0"><tr><td>'.$t0.'</td><td><input type="password" id="mdgPassSetField" size="10" /></td><td>'.$t.'</td></tr></table>';
    }
    
    // Manage ACLs button
    if ( !$paths->external_api_page && $session->check_acl_scope('edit_acl', $local_namespace) && ( $perms->get_permissions('edit_acl') || ( defined('ACL_ALWAYS_ALLOW_ADMIN_EDIT_ACL') &&  $session->user_level >= USER_LEVEL_ADMIN ) ) )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { var s = ajaxOpenACLManager(); console.debug(s); return false; }" title="' . $lang->get('onpage_tip_aclmanager') . '" accesskey="m"',
          'HREF'  => makeUrl($local_page, 'do=aclmanager', true),
          'TEXT'  => $lang->get('onpage_btn_acl'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Administer page button
    if ( $session->user_level >= USER_LEVEL_ADMIN && $local_page_exists )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="if ( !KILL_SWITCH ) { void(ajaxAdminPage()); return false; }" title="' . $lang->get('onpage_tip_adminoptions') . '" accesskey="g"',
          'HREF'  => makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'PageManager', true),
          'TEXT'  => $lang->get('onpage_btn_admin'),
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    if ( strlen($this->toolbar_menu) > 0 )
    {
      $button->assign_vars(array(
        'FLAGS'       => 'id="mdgToolbar_moreoptions" onclick="if ( !KILL_SWITCH ) { return false; }" title="' . $lang->get('onpage_tip_moreoptions') . '"',
        'PARENTFLAGS' => '',
        'HREF'        => makeUrl($local_page, 'do=moreoptions', true),
        'TEXT'        => $lang->get('onpage_btn_moreoptions')
        ));
      $tb .= $button->run();
    }
    
    //
    // OTHER SWITCHES
    //
    
    $is_opera = (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'Opera')) ? true : false;
    $is_msie = (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) ? true : false;
    
    $this->tpl_bool = Array(
      'auth_admin' => $session->user_level >= USER_LEVEL_ADMIN ? true : false,
      'user_logged_in' => $session->user_logged_in,
      'opera' => $is_opera,
      'msie' => $is_msie
      );
    
    if ( $session->sid_super )
    {
      $ash = '&amp;auth=' . $session->sid_super;
      $asq = "?auth=" . $session->sid_super;
      $asa = "&auth=" . $session->sid_super;
      $as2 = htmlspecialchars(urlSeparator) . 'auth='.$session->sid_super;
    }
    else
    {
      $asq = '';
      $asa = '';
      $as2 = '';
      $ash = '';
    }
    
    // Set up javascript includes
    // these depend heavily on whether we have a CDN to work with or not
    if ( getConfig('cdn_path') )
    {
      // we're on a CDN, point to static includes
      // probably should have a way to compress stuff like this before uploading to CDN
      $js_head = '<script type="text/javascript" src="' . cdnPath . '/includes/clientside/static/enano-lib-basic.js"></script>';
      $js_foot = <<<JSEOF
    <script type="text/javascript">
      // This initializes the Javascript runtime when the DOM is ready - not when the page is
      // done loading, because enano-lib-basic still has to load some 15 other script files
      // check for the init function - this is a KHTML fix
      // This doesn't seem to work properly in IE in 1.1.x - there are some problems with
      // tinyMCE and l10n.
      if ( typeof ( enano_init ) == 'function' && !IE )
      {
        enano_init();
        window.onload = function(e) {  };
      }
    </script>
JSEOF;
    }
    else
    {
      $cdnpath = cdnPath;
      // point to jsres compressor
      $js_head = <<<JSEOF
      <!-- Only load a basic set of functions for now. Let the rest of the API load when the page is finished. -->
      <script type="text/javascript" src="$cdnpath/includes/clientside/jsres.php?early"></script>
JSEOF;
      $js_foot = <<<JSEOF
    <!-- jsres.php is a wrapper script that compresses and caches single JS files to minimize requests -->
    <script type="text/javascript" src="$cdnpath/includes/clientside/jsres.php"></script>
    <script type="text/javascript">
      // This initializes the Javascript runtime when the DOM is ready - not when the page is
      // done loading, because enano-lib-basic still has to load some 15 other script files
      // check for the init function - this is a KHTML fix
      // This doesn't seem to work properly in IE in 1.1.x - there are some problems with
      // tinyMCE and l10n.
      if ( typeof ( enano_init ) == 'function' && !IE )
      {
        enano_init();
        window.onload = function(e) {  };
      }
    </script>
JSEOF;
    }
    
    $code = $plugins->setHook('compile_template');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    // Some additional sidebar processing
    if ( $this->sidebar_extra != '' )
    {
      $se = $this->sidebar_extra;
      $parser = $this->makeParserText($tplvars['sidebar_section_raw']);
      $parser->assign_vars(array(
          'TITLE' => 'Links', // FIXME: l10n
          'CONTENT' => $se
        ));
      
      $this->sidebar_extra = $parser->run();
    }
    
    $this->sidebar_extra = $this->sidebar_extra . $this->sidebar_widgets;
    
    $this->tpl_bool['fixed_menus'] = false;
    $this->tpl_bool['export'] = false;
    $this->tpl_bool['right_sidebar'] = true;
    $this->tpl_bool['auth_rename'] = ( $local_page_exists && $session->check_acl_scope('rename', $local_namespace) && ( $perms->get_permissions('rename') && ( $paths->page_protected && $perms->get_permissions('even_when_protected') || !$paths->page_protected ) ));
    $this->tpl_bool['enable_uploads'] = ( getConfig('enable_uploads') == '1' && $session->get_permissions('upload_files') ) ? true : false;
    $this->tpl_bool['stupid_mode'] = false;
    $this->tpl_bool['in_admin'] = ( ( $local_page_id == 'Administration' && $local_namespace == 'Special' ) || $local_namespace == 'Admin' );
    
    // allows conditional testing of the theme ID (a bit crude, came from my NSIS days)
    $this->tpl_bool["theme_is_{$this->theme}"] = true;
    
    $p = ( isset($_GET['printable']) ) ? '/printable' : '';
    
    // Add the e-mail address client code to the header
    $this->add_header($email->jscode());
    
    // Generate the code for the Log out and Change theme sidebar buttons
    // Once again, the new template parsing system can be used here
    
    $parser = $this->makeParserText($tplvars['sidebar_button']);
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', "Logout/{$session->csrf_token}/{$local_page}"),
        'FLAGS'=>'onclick="if ( !KILL_SWITCH ) { mb_logout(); return false; }"',
        'TEXT'=>$lang->get('sidebar_btn_logout'),
      ));
    
    $logout_link = $parser->run();
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', 'Login/' . $local_page),
        'FLAGS'=>'onclick="if ( !KILL_SWITCH ) { ajaxStartLogin(); return false; }"',
        'TEXT'=>$lang->get('sidebar_btn_login'),
      ));
    
    $login_link = $parser->run();
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', 'ChangeStyle/'.$local_page),
        'FLAGS'=>'onclick="if ( !KILL_SWITCH ) { ajaxChangeStyle(); return false; }"',
        'TEXT'=>$lang->get('sidebar_btn_changestyle'),
      ));
    
    $theme_link = $parser->run();
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', 'Administration'),
        'FLAGS'=>'onclick="if ( !KILL_SWITCH ) { void(ajaxStartAdminLogin()); return false; }"',
        'TEXT'=>$lang->get('sidebar_btn_administration'),
      ));
    
    $admin_link = $parser->run();
    
    $SID = ($session->sid_super) ? $session->sid_super : '';
    
    $urlname_clean = str_replace('\'', '\\\'', str_replace('\\', '\\\\', dirtify_page_id($local_fullpage)));
    $urlname_clean = strtr( $urlname_clean, array( '<' => '&lt;', '>' => '&gt;' ) );
    
    $urlname_jssafe = sanitize_page_id($local_fullpage);
    $physical_urlname_jssafe = sanitize_page_id($paths->fullpage);
    
    if ( $session->check_acl_scope('even_when_protected', $local_namespace) )
    {
      $protected = $paths->page_protected && !$perms->get_permissions('even_when_protected');
    }
    else
    {
      $protected = false;
    }
    
    // Generate the dynamic javascript vars
    $js_dynamic = '    <script type="text/javascript">// <![CDATA[
      // This section defines some basic and very important variables that are used later in the static Javascript library.
      // SKIN DEVELOPERS: The template variable for this code block is {JS_DYNAMIC_VARS}. This MUST be inserted BEFORE the tag that links to the main Javascript lib.
      var title = \''. $urlname_jssafe .'\';
      var physical_title = \'' . $physical_urlname_jssafe . '\';
      var page_exists = '. ( ( $local_page_exists) ? 'true' : 'false' ) .';
      var scriptPath = \'' . addslashes(scriptPath) . '\';
      var contentPath = \'' . addslashes(contentPath) . '\';
      var cdnPath = \'' . addslashes(cdnPath) . '\';
      var ENANO_SID = \'' . $SID . '\';
      var user_level = ' . $session->user_level . ';
      var auth_level = ' . $session->auth_level . ';
      var USER_LEVEL_GUEST = ' . USER_LEVEL_GUEST . ';
      var USER_LEVEL_MEMBER = ' . USER_LEVEL_MEMBER . ';
      var USER_LEVEL_CHPREF = ' . USER_LEVEL_CHPREF . ';
      var USER_LEVEL_MOD = ' . USER_LEVEL_MOD . ';
      var USER_LEVEL_ADMIN = ' . USER_LEVEL_ADMIN . ';
      var disable_redirect = ' . ( isset($_GET['redirect']) && $_GET['redirect'] == 'no' ? 'true' : 'false' ) . ';
      var pref_disable_js_fx = ' . ( @$session->user_extra['disable_js_fx'] == 1 ? 'true' : 'false' ) . ';
      var csrf_token = "' . $session->csrf_token . '";
      var editNotice = \'' . ( (getConfig('wiki_edit_notice')=='1') ? str_replace("\n", "\\\n", RenderMan::render(getConfig('wiki_edit_notice_text'))) : '' ) . '\';
      var prot = ' . ( ($protected) ? 'true' : 'false' ) .'; // No, hacking this var won\'t work, it\'s re-checked on the server
      var ENANO_SPECIAL_CREATEPAGE = \''. makeUrl($paths->nslist['Special'].'CreatePage') .'\';
      var ENANO_CREATEPAGE_PARAMS = \'_do=&pagename='. $urlname_clean .'&namespace=' . $local_namespace . '\';
      var ENANO_SPECIAL_CHANGESTYLE = \''. makeUrlNS('Special', 'ChangeStyle') .'\';
      var namespace_list = new Array();
      var msg_loading_component = \'' . addslashes($lang->get('ajax_msg_loading_component')) . '\';
      var AES_BITS = '.AES_BITS.';
      var AES_BLOCKSIZE = '.AES_BLOCKSIZE.';
      var pagepass = \''. ( ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '' ) .'\';
      var ENANO_THEME_LIST = \'';
          foreach($this->theme_list as $t) {
            if($t['enabled'])
            {
              $js_dynamic .= '<option value="'.$t['theme_id'].'"';
              // if($t['theme_id'] == $session->theme) $js_dynamic .= ' selected="selected"';
              $js_dynamic .= '>'.$t['theme_name'].'</option>';
            }
          }
      $js_dynamic .= '\';
      var ENANO_CURRENT_THEME = \''. $session->theme .'\';
      var ENANO_LANG_ID = ' . $lang->lang_id . ';
      var ENANO_PAGE_TYPE = "' . addslashes($this->namespace_string) . '";';
      foreach($paths->nslist as $k => $c)
      {
        $js_dynamic .= "namespace_list['{$k}'] = '$c';";
      }
      $js_dynamic .= "\n    //]]>\n    </script>";
      
    $tpl_strings = Array(
      'PAGE_NAME'=>htmlspecialchars($local_cdata['name']),
      'PAGE_URLNAME'=> $urlname_clean,
      'SITE_NAME'=>htmlspecialchars(getConfig('site_name')),
      'USERNAME'=>$session->username,
      'SITE_DESC'=>htmlspecialchars(getConfig('site_desc')),
      'TOOLBAR'=>$tb,
      'SCRIPTPATH'=>scriptPath,
      'CONTENTPATH'=>contentPath,
      'CDNPATH' => cdnPath,
      'ADMIN_SID_QUES'=>$asq,
      'ADMIN_SID_AMP'=>$asa,
      'ADMIN_SID_AMP_HTML'=>$ash,
      'ADMIN_SID_AUTO'=>$as2,
      'ADMIN_SID_RAW'=> ( is_string($session->sid_super) ? $session->sid_super : '' ),
      'COPYRIGHT'=>RenderMan::parse_internal_links(getConfig('copyright_notice')),
      'TOOLBAR_EXTRAS'=>$this->toolbar_menu,
      'REQUEST_URI'=>( defined('ENANO_CLI') ? '' : $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ),
      'STYLE_LINK'=>makeUrlNS('Special', 'CSS'.$p, null, true), //contentPath.$paths->nslist['Special'].'CSS' . $p,
      'LOGIN_LINK'=>$login_link,
      'LOGOUT_LINK'=>$logout_link,
      'ADMIN_LINK'=>$admin_link,
      'THEME_LINK'=>$theme_link,
      'SEARCH_ACTION'=>makeUrlNS('Special', 'Search'),
      'INPUT_TITLE'=>( urlSeparator == '&' ? '<input type="hidden" name="title" value="' . htmlspecialchars( $paths->nslist[$local_namespace] . $local_page_id ) . '" />' : ''),
      'INPUT_AUTH'=>( $session->sid_super ? '<input type="hidden" name="auth"  value="' . $session->sid_super . '" />' : ''),
      'TEMPLATE_DIR'=>scriptPath.'/themes/'.$this->theme,
      'THEME_ID'=>$this->theme,
      'STYLE_ID'=>$this->style,
      'JS_HEADER' => $js_head,
      'JS_FOOTER' => $js_foot,
      'JS_DYNAMIC_VARS'=>$js_dynamic,
      'UNREAD_PMS'=>$session->unread_pms,
      'URL_ABOUT_ENANO' => makeUrlNS('Special', 'About_Enano', '', true),
      'REPORT_URI' => makeUrl($local_fullpage, 'do=sql_report', true)
      );
    
    foreach ( $paths->nslist as $ns_id => $ns_prefix )
    {
      $tpl_strings[ 'NS_' . strtoupper($ns_id) ] = $ns_prefix;
    }
    
    $this->assign_vars($tpl_strings, true);
    
    profiler_log('template: var init: finished toolbar building and initial assign()');
    
    //
    // COMPILE THE SIDEBAR
    //
    
    // This is done after the big assign_vars() so that sidebar code has access to the newly assigned variables
    
    list($this->tpl_strings['SIDEBAR_LEFT'], $this->tpl_strings['SIDEBAR_RIGHT'], $min) = $this->fetch_sidebar();
    $this->tpl_bool['sidebar_left']  = ( $this->tpl_strings['SIDEBAR_LEFT']  != $min) ? true : false;
    $this->tpl_bool['sidebar_right'] = ( $this->tpl_strings['SIDEBAR_RIGHT'] != $min) ? true : false;
    $this->tpl_bool['right_sidebar'] = $this->tpl_bool['sidebar_right']; // backward compatibility
    
    // and finally one string value that needs to be symlinked...
    if ( !isset($this->tpl_strings['ADDITIONAL_HEADERS']) )
    {
      $this->tpl_strings['ADDITIONAL_HEADERS'] =& $this->additional_headers;
    }
    
    // done!
    $code = $plugins->setHook('template_var_init_end');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    profiler_log("template: finished var init");
  }
  
  /**
   * Performs var init that is common to all pages (IOW, called only once)
   * @access private
   */
  
  function init_vars_global()
  {
    
  }
  
  function header($simple = false) 
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    ob_start();
    
    if(!$this->theme_loaded)
    {
      $this->load_theme($session->theme, $session->style);
    }
    
    // I feel awful doing this.
    if ( preg_match('/^W3C_Validator/', @$_SERVER['HTTP_USER_AGENT']) )
    {
      header('Content-type: application/xhtml+xml');
    }
    
    $headers_sent = true;
    if(!defined('ENANO_HEADERS_SENT'))
      define('ENANO_HEADERS_SENT', '');
    if ( !$this->no_headers )
    {
      $header = ( $simple ) ?
        $this->process_template('simple-header.tpl') :
        $this->process_template('header.tpl');
      echo $header;
    }
    if ( !$simple && $session->user_logged_in && $session->unread_pms > 0 )
    {
      echo $this->notify_unread_pms();
    }
    if ( !$simple && $session->sw_timed_out )
    {
      $login_link = makeUrlNS('Special', 'Login/' . $paths->fullpage, 'level=' . $session->user_level, true);
      echo '<div class="usermessage">';
      echo $lang->get('user_msg_elev_timed_out', array( 'login_link' => $login_link ));
      echo '</div>';
    }
    if ( $this->site_disabled && $session->user_level >= USER_LEVEL_ADMIN && ( $paths->page != $paths->nslist['Special'] . 'Administration' ) )
    {
      $admin_link = makeUrlNS('Special', 'Administration', 'module=' . $paths->nslist['Admin'] . 'GeneralConfig', true);
      echo '<div class="usermessage"><b>' . $lang->get('page_sitedisabled_admin_msg_title') . '</b><br />
            ' . $lang->get('page_sitedisabled_admin_msg_body', array('admin_link' => $admin_link)) . '
            </div>';
    }
  }
  
  function footer($simple = false)
  {
    echo $this->getFooter($simple);
    ob_end_flush();
  }
  
  function getHeader()
  {
    $headers_sent = true;
    if(!defined('ENANO_HEADERS_SENT'))
      define('ENANO_HEADERS_SENT', '');
    if(!$this->no_headers) return $this->process_template('header.tpl');
  }
  function getFooter($simple = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    if ( !$this->no_headers )
    {
      
      if(!defined('ENANO_HEADERS_SENT'))
        $this->header();
      
      global $_starttime;
      if(isset($_GET['sqldbg']) && $session->get_permissions('mod_misc'))
      {
        echo '<h3>' . $lang->get('page_heading_sql_list') . '</h3><pre style="margin-left: 1em">';
        echo htmlspecialchars($db->sql_backtrace());
        echo '</pre>';
      }
      
      $t = ( $simple ) ? $this->process_template('simple-footer.tpl') : $this->process_template('footer.tpl');
      
      $f = microtime_float();
      $f = $f - $_starttime;
      $f = round($f, 2);
      
      $t_loc = $lang->get('page_msg_stats_gentime_short', array('time' => $f));
      $t_loc_long = $lang->get('page_msg_stats_gentime_long', array('time' => $f));
      $q_loc = '<a href="' . $this->tpl_strings['REPORT_URI'] . '">' . $lang->get('page_msg_stats_sql', array('nq' => $db->num_queries)) . '</a>';
      $dbg = $t_loc;
      $dbg_long = $t_loc_long;
      if ( $session->user_level >= USER_LEVEL_ADMIN )
      {
        $dbg .= "&nbsp;&nbsp;|&nbsp;&nbsp;$q_loc";
        $dbg_long .= "&nbsp;&nbsp;|&nbsp;&nbsp;$q_loc";
      }
      
      $t = str_replace('[[Stats]]', $dbg, $t);
      $t = str_replace('[[StatsLong]]', $dbg_long, $t);
      $t = str_replace('[[NumQueries]]', (string)$db->num_queries, $t);
      $t = str_replace('[[GenTime]]', (string)$f, $t);
      $t = str_replace('[[NumQueriesLoc]]', $q_loc, $t);
      $t = str_replace('[[GenTimeLoc]]', $t_loc, $t);
      $t = str_replace('[[EnanoPoweredLink]]', $lang->get('page_enano_powered', array('about_uri' => $this->tpl_strings['URL_ABOUT_ENANO'])), $t);
      $t = str_replace('[[EnanoPoweredLinkLong]]', $lang->get('page_enano_powered_long', array('about_uri' => $this->tpl_strings['URL_ABOUT_ENANO'])), $t);
      
      if ( defined('ENANO_DEBUG') )
      {
        $t = str_replace('</body>', '<div id="profile" style="margin: 10px;">' . profiler_make_html() . '</div></body>', $t);
        // ob_end_clean();
        // return profiler_make_html();
      }
      
      return $t;
    }
    else
    {
      return '';
    }
  }
  
  /**
   * Assigns an array of string values to the template. Strings can be accessed from the template by inserting {KEY_NAME} in the template file.
   * @param $vars array
   * @param $from_internal bool Internal switch, just omit (@todo document)
   */
  
  function assign_vars($vars, $from_internal = false)
  {
    foreach ( $vars as $key => $value )
    {
      $replace = true;
      if ( isset($this->vars_assign_history['strings'][$key]) )
      {
        if ( $this->vars_assign_history['strings'][$key] == 'api' )
        {
          $replace = false;
        }
      }
      if ( $replace )
      {
        $this->tpl_strings[$key] = $value;
        $this->vars_assign_history['strings'][$key] = ( $from_internal ) ? 'internal' : 'api';
      }
    }
  }
  
  /**
   * Assigns an array of boolean values to the template. These can be used for <!-- IF ... --> statements.
   * @param $vars array
   * @param $from_internal bool Internal switch, just omit (@todo document)
   */
  
  function assign_bool($vars)
  {
    foreach ( $vars as $key => $value )
    {
      $replace = true;
      if ( isset($this->vars_assign_history['bool'][$key]) )
      {
        if ( $this->vars_assign_history['bool'][$key] == 'api' )
        {
          $replace = false;
        }
      }
      if ( $replace )
      {
        $this->tpl_bool[$key] = $value;
        $this->vars_assign_history['bool'][$key] = ( $from_internal ) ? 'internal' : 'api';
      }
    }
  }
  
  #
  # COMPILER
  #
  
  /**
   * Compiles and executes a template based on the current variables and booleans. Loads
   * the theme and initializes variables if needed. This mostly just calls child functions.
   * @param string File to process
   * @return string
   */
  
  function process_template($file)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!defined('ENANO_TEMPLATE_LOADED'))
    {
      $this->load_theme();
      $this->init_vars();
    }
    
    $cache_file = ENANO_ROOT . '/cache/' . $this->theme . '-' . str_replace('/', '-', $file) . '.php';
    if ( file_exists($cache_file) )
    {
      // this is about the time of the breaking change to cache file format
      if ( ($m = filemtime($cache_file)) > 1215038089 )
      {
        $result = @include($cache_file);
        if ( isset($md5) )
        {
          if ( $m >= filemtime(ENANO_ROOT . "/themes/{$this->theme}/$file") )
          {
            $result = $this->compile_template_text_post($result);
            return $result;
          }
        }
      }
    }
    
    $compiled = $this->compile_template($file);
    $result = eval($compiled);
    
    return $result;
  }
  
  /**
   * Loads variables from the specified template file. Returns an associative array containing the variables.
   * @param string Template file to process (elements.tpl)
   * @return array
   */
  
  function extract_vars($file)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Sometimes this function gets called before the theme is loaded
    // This is a bad coding practice so this function will always be picky.
    if ( !$this->theme )
    {
      die('$template->extract_vars(): theme not yet loaded, so we can\'t open template files yet...this is a bug and should be reported.<br /><br />Backtrace, most recent call first:<pre>'.enano_debug_print_backtrace(true).'</pre>');
    }
    
    // Full pathname of template file
    $tpl_file_fullpath = ENANO_ROOT . '/themes/' . $this->theme . '/' . $file;
    
    // Make sure the template even exists
    if ( !is_file($tpl_file_fullpath) )
    {
      die_semicritical('Cannot find template file',
                       '<p>The template parser was asked to load the file "' . htmlspecialchars($tpl_file_fullpath) . '", but that file couldn\'t be found in the directory for
                           the current theme.</p>
                        <p>Additional debugging information:<br />
                           <b>Theme currently in use: </b>' . $this->theme . '<br />
                           <b>Requested file: </b>' . $file . '
                           </p>');
    }
    // Retrieve file contents
    $text = file_get_contents($tpl_file_fullpath);
    if ( !$text )
    {
      return false;
    }
    
    // Get variables, regular expressions FTW
    preg_match_all('#<\!-- VAR ([A-z0-9_-]*) -->(.*?)<\!-- ENDVAR \\1 -->#is', $text, $matches);
    
    // Initialize return values
    $tplvars = Array();
    
    // Loop through each match, setting $tplvars[ $first_subpattern ] to $second_subpattern
    for ( $i = 0; $i < sizeof($matches[1]); $i++ )
    {
      $tplvars[ $matches[1][$i] ] = $matches[2][$i];
    }
    
    // All done!
    return $tplvars;
  }
  
  /**
   * Compiles a block of template code.
   * @param string The text to process
   * @return string
   */
  
  function compile_tpl_code($text)
  {
    return template_compiler_core($text);  
  }
  
  /**
   * Compiles the contents of a given template file, possibly using a cached copy, and returns the compiled code.
   * @param string Filename of template (header.tpl)
   * @return string
   */
  
  function compile_template($filename)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Full path to template file
    $tpl_file_fullpath = ENANO_ROOT . '/themes/' . $this->theme . '/' . $filename;
    
    // Make sure the file exists
    if ( !is_file($tpl_file_fullpath) )
    {
      die_semicritical('Cannot find template file',
                       '<p>The template parser was asked to load the file "' . htmlspecialchars($filename) . '", but that file couldn\'t be found in the directory for
                           the current theme.</p>
                        <p>Additional debugging information:<br />
                           <b>Theme currently in use: </b>' . $this->theme . '<br />
                           <b>Requested file: </b>' . $file . '
                           </p>');
    }
    
    // We won't use the cached copy here.
    $text = file_get_contents($tpl_file_fullpath);
    
    // This will be used later when writing the cached file
    $md5 = md5($text);
    
    // Preprocessing and checks complete - compile the code
    $text = $this->compile_tpl_code($text);
    
    // Generate cache filename
    $cache_file = ENANO_ROOT . '/cache/' . $this->theme . '-' . str_replace('/', '-', $filename) . '.php';
    
    // Perhaps caching is enabled and the admin has changed the template?
    if ( is_writable( ENANO_ROOT . '/cache/' ) && getConfig('cache_thumbs') == '1' )
    {
      $h = fopen($cache_file, 'w');
      if ( !$h )
      {
        // Couldn't open the file - silently ignore and return
        return $text;
      }
      
      // Final contents of cache file
      $file_contents = <<<EOF
<?php

/*
 * NOTE: This file was automatically generated by Enano and is based on compiled code. Do not edit this file.
 * If you edit this file, any changes you make will be lost the next time the associated source template file is edited.
 */

\$md5 = '$md5';

$text
EOF;
      // This is really just a normal PHP file that sets a variable or two and exits.
      // $tpl_text actually will contain the compiled code
      fwrite($h, $file_contents);
      fclose($h);
    }
    
    return $this->compile_template_text_post($text); //('<pre>'.htmlspecialchars($text).'</pre>');
  }
  
  
  /**
   * Compiles (parses) some template code with the current master set of variables and booleans.
   * @param string Text to process
   * @return string
   */
  
  function compile_template_text($text)
  {
    // this might do something else in the future, possibly cache large templates
    return $this->compile_template_text_post($this->compile_tpl_code($text));
  }
  
  /**
   * For convenience - compiles AND parses some template code.
   * @param string Text to process
   * @return string
   */
  
  function parse($text)
  {
    $text = $this->compile_template_text($text);
    $text = $this->compile_template_text_post($text);
    return eval($text);
  }
  
  /**
   * Post-processor for template code. Basically what this does is it localizes {lang:foo} blocks.
   * @param string Mostly-processed TPL code
   * @return string
   */
  
  function compile_template_text_post($text)
  {
    global $lang;
    preg_match_all('/\{lang:([a-z0-9]+_[a-z0-9_]+)\}/', $text, $matches);
    foreach ( $matches[1] as $i => $string_id )
    {
      $string = $lang->get($string_id);
      $string = str_replace('\\', '\\\\', $string);
      $string = str_replace('\'', '\\\'', $string);
      $text = str_replace_once($matches[0][$i], $string, $text);
    }
    return $text;
  }
  
  // n00bish comments removed from here. 2008-03-13 @ 12:02AM when I had nothing else to do.
  
  /**
   * Takes a blob of HTML with the specially formatted template-oriented wikitext and formats it. Does not use eval().
   * This function butchers every coding standard in Enano and should eventually be rewritten. The fact is that the
   * code _works_ and does a good job of checking for errors and cleanly complaining about them.
   * @param string Text to process
   * @param bool Ignored for backwards compatibility
   * @param string File to get variables for sidebar data from
   * @return string
   */
  
  function tplWikiFormat($message, $filter_links = false, $filename = 'elements.tpl')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $START = microtime_float();
    
    // localize the whole string first
    preg_match_all('/\{lang:([a-z0-9]+_[a-z0-9_]+)\}/', $message, $matches);
    foreach ( $matches[1] as $i => $string_id )
    {
      $string = $lang->get($string_id);
      $string = str_replace('\\', '\\\\', $string);
      $string = str_replace('\'', '\\\'', $string);
      $message = str_replace_once($matches[0][$i], $string, $message);
    }
    
    // first: the hackish optimization -
    // if it's only a bunch of letters, numbers and spaces, just skip this sh*t.
    
    if ( preg_match('/^[\w\s\.]*$/i', $message) )
    {
      return $message;
    }
    
    $filter_links = false;
    $tplvars = $this->extract_vars($filename);
    if($session->sid_super) $as = htmlspecialchars(urlSeparator).'auth='.$session->sid_super;
    else $as = '';
    error_reporting(E_ALL);
    $random_id = sha1(microtime().''); // A temp value
    
    /*
     * PREPROCESSOR
     */
    
    // Variables
    
    preg_match_all('#\$([A-Z_-]+)\$#', $message, $links);
    $links = $links[1];
    
    for($i=0;$i<sizeof($links);$i++)
    {
      $message = str_replace('$'.$links[$i].'$', $this->tpl_strings[$links[$i]], $message);
    }
    
    // Conditionals
    
    $message = $this->twf_parse_conditionals($message);
    
    /*
     * HTML RENDERER
     */
     
    // Images
    $message = RenderMan::process_image_tags($message, $taglist);
    $message = RenderMan::process_imgtags_stage2($message, $taglist);
    
    // Internal links
    $message = RenderMan::parse_internal_links($message, $tplvars['sidebar_button']);
    
    // External links
    
    $url_regexp = <<<EOF
(
  (?:https?|ftp|irc):\/\/                            # protocol
  (?:[^@\s\]"\':]+@)?                                # username (FTP only but whatever)
  (?:(?:(?:[a-z0-9-]+\.)*)[a-z0-9-]+)                # hostname
  (?:\/[A-z0-9_%\|~`!\!@#\$\^&?=\*\(\):;\.,\/-]*)? # path
)
EOF;

    $text_parser = $this->makeParserText($tplvars['sidebar_button']);

    preg_match_all('/\[' . $url_regexp . '[ ]([^\]]+)\]/isx', $message, $ext_link);
    
    for ( $i = 0; $i < count($ext_link[0]); $i++ )
    {
      $text_parser->assign_vars(Array(  
          'HREF'  => $ext_link[1][$i],
          'FLAGS' => '',
          'TEXT'  => $ext_link[2][$i]
        ));
      $message = str_replace($ext_link[0][$i], $text_parser->run(), $message);
    }
    
    preg_match_all('/\[' . $url_regexp . '\]/is', $message, $ext_link);
    
    for ( $i = 0; $i < count($ext_link[0]); $i++ )
    {
      $text_parser->assign_vars(Array(  
          'HREF'  => $ext_link[1][$i],
          'FLAGS' => '',
          'TEXT'  => htmlspecialchars($ext_link[1][$i])
        ));
      $message = str_replace($ext_link[0][$i], $text_parser->run(), $message);
    }
    
    $TIME = microtime_float() - $START;
    
    /*
    if ( $TIME > 0.02 )
    {
      echo 'template: tplWikiFormat took a while for this one. string dump:<pre>';
      echo htmlspecialchars($message);
      echo '</pre>';
    }
    */
    
    return $message;
  }
  
  /**
   * Parses conditional {if} blocks in sidebars and other tplWikiFormatted things
   * @param string A string potentially containing conditional blocks
   * @return string Processed string
   */
  
  function twf_parse_conditionals($message)
  {
    if ( !preg_match_all('/\{(!?)if ([a-z0-9_\(\)\|&! ]+)\}(.*?)(?:\{else\}(.*?))?\{\/if\}/is', $message, $matches) )
    {
      return $message;
    }
    foreach ( $matches[0] as $match_id => $full_block )
    {
      // 1 = "not" flag
      // 2 = condition
      // 3 = if true
      // 4 = else
      $condresult = $this->process_condition($matches[2][$match_id]);
      if ( !empty($matches[1][$match_id]) )
      {
        if ( $condresult == 1 )
          $condresult = 2;
        else if ( $condresult == 2 )
          $condresult = 1;
      }
      switch($condresult)
      {
        case 1:
          // evaluated to false
          $message = str_replace_once($full_block, $matches[4][$match_id], $message);
          break;
        case 2:
          // evaluated to true
          $message = str_replace_once($full_block, $matches[3][$match_id], $message);
          break;
        case 3:
          $message = str_replace_once($full_block, "Syntax error: mismatched parentheses (" . htmlspecialchars($matches[2][$match_id]) . ")<br />\n", $message);
          break;
        case 4:
          $message = str_replace_once($full_block, "Syntax error: illegal character (" . htmlspecialchars($matches[2][$match_id]) . ")<br />\n", $message);
          break;
        case 5:
          $message = str_replace_once($full_block, "Syntax error: illegal sequence (" . htmlspecialchars($matches[2][$match_id]) . ")<br />\n", $message);
          break;
      }
    }
    return $message;
  }
  
  /**
   * Inner-loop parser for a conditional block. Verifies a string condition to make sure it's syntactically correct, then returns what it evaluates to.
   * Return values:
   *   1 - string evaluates to true
   *   2 - string evaluates to false
   *   3 - Syntax error - mismatched parentheses
   *   4 - Syntax error - unknown token
   *   5 - Syntax error - invalid sequence
   * @param string
   * @return int
   * 
   */
  
  function process_condition($condition)
  {
    // make sure parentheses are matched
    $parentheses = preg_replace('/[^\(\)]/', '', $condition);
    if ( !empty($parentheses) )
    {
      $i = 0;
      $parentheses = enano_str_split($parentheses);
      foreach ( $parentheses as $chr )
      {
        $inc = ( $chr == '(' ) ? 1 : -1;
        $i += $inc;
      }
      if ( $i != 0 )
      {
        // mismatched parentheses
        return 3;
      }
    }
    // sequencer check
    // first, pad all sequences of characters with spaces
    $seqcheck = preg_replace('/([a-z0-9_]+)/i', '\\1 ', $condition);
    $seqcheck = preg_replace('/([&|()!])/i', '\\1 ', $seqcheck);
    // now shrink all spaces to one space each
    $seqcheck = preg_replace('/[ ]+/', ' ', $seqcheck);
    
    // explode it. the allowed sequences are:
    //   - TOKEN_NOT + TOKEN_VARIABLE
    //   - TOKEN_NOT + TOKEN_PARENTHLEFT
    //   - TOKEN_BOOLOP + TOKEN_NOT
    //   - TOKEN_PARENTHRIGHT + TOKEN_NOT
    //   - TOKEN_VARIABLE + TOKEN_BOOLOP
    //   - TOKEN_BOOLOP + TOKEN_PARENTHLEFT
    //   - TOKEN_PARENTHLEFT + TOKEN_VARIABLE
    //   - TOKEN_BOOLOP + TOKEN_VARIABLE
    //   - TOKEN_VARIABLE + TOKEN_PARENTHRIGHT
    //   - TOKEN_PARENTHRIGHT + TOKEN_BOOLOP
    $seqcheck = explode(' ', trim($seqcheck));
    $last_item = TOKEN_BOOLOP;
    foreach ( $seqcheck as $i => $token )
    {
      // determine type
      if ( $token == '(' )
      {
        $type = TOKEN_PARENTHLEFT;
      }
      else if ( $token == ')' )
      {
        $type = TOKEN_PARENTHRIGHT;
      }
      else if ( $token == '!' )
      {
        $type = TOKEN_NOT;
      }
      else if ( strtolower($token) == 'and' || strtolower($token) == 'or' || $token == '&&' || $token == '||' )
      {
        $type = TOKEN_BOOLOP;
      }
      else if ( preg_match('/^[a-z0-9_]+$/i', $token) )
      {
        $type = TOKEN_VARIABLE;
        // at this point it's considered safe to wrap it
        $seqcheck[$i] = "( isset(\$this->tpl_bool['$token']) && \$this->tpl_bool['$token'] )";
      }
      else
      {
        // syntax error - doesn't match known token types
        return 4;
      }
      // inner sequence check
      if (
           ( $last_item == TOKEN_BOOLOP && $type == TOKEN_NOT ) ||
           ( $last_item == TOKEN_PARENTHRIGHT && $type == TOKEN_NOT ) ||
           ( $last_item == TOKEN_NOT && $type == TOKEN_VARIABLE ) ||
           ( $last_item == TOKEN_NOT && $type == TOKEN_PARENTHLEFT ) ||
           ( $last_item == TOKEN_VARIABLE && $type == TOKEN_BOOLOP ) ||
           ( $last_item == TOKEN_BOOLOP && $type == TOKEN_PARENTHLEFT ) ||
           ( $last_item == TOKEN_PARENTHLEFT && $type == TOKEN_VARIABLE ) ||
           ( $last_item == TOKEN_BOOLOP && $type == TOKEN_VARIABLE ) ||
           ( $last_item == TOKEN_VARIABLE && $type == TOKEN_PARENTHRIGHT ) ||
           ( $last_item == TOKEN_PARENTHRIGHT && $type == TOKEN_BOOLOP )
         )
      {
        // sequence is good, continue
      }
      else
      {
        // sequence is invalid, break out
        return 5;
      }
      $last_item = $type;
    }
    // passed all checks
    $seqcheck = implode(' ', $seqcheck);
    $result = eval("return ( $seqcheck ) ? true : false;");
    return ( $result ) ? 2 : 1;
  }
  
  /**
   * Print a text field that auto-completes a username entered into it.
   * @param string $name - the name of the form field
   * @return string
   */
   
  function username_field($name, $value = false)
  {
    $randomid = md5( time() . microtime() . mt_rand() );
    $text = '<input name="'.$name.'" class="autofill username" type="text" size="30" id="userfield_'.$randomid.'"';
    if($value) $text .= ' value="'.$value.'"';
    $text .= ' />';
    return $text;
  }
  
  /**
   * Print a text field that auto-completes a page name entered into it.
   * @param string $name - the name of the form field
   * @return string
   */
   
  function pagename_field($name, $value = false)
  {
    $randomid = md5( time() . microtime() . mt_rand() );
    $text = '<input name="'.$name.'" class="autofill page" type="text" size="30" id="pagefield_'.$randomid.'"';
    if($value) $text .= ' value="'.$value.'"';
    $text .= ' />';
    return $text;
  }
  
  /**
   * Sends a textarea that can be converted to and from a TinyMCE widget on the fly.
   * @param string The name of the form element
   * @param string The initial content. Optional, defaults to blank
   * @param int Rows in textarea
   * @param int Columns in textarea
   * @return string HTML and Javascript code.
   */
  
  function tinymce_textarea($name, $content = '', $rows = 20, $cols = 60)
  {
    global $lang;
    $randomid = md5(microtime() . mt_rand());
    $html = '';
    $html .= '<textarea name="' . $name . '" rows="'.$rows.'" cols="'.$cols.'" style="width: 100%;" id="toggleMCEroot_'.$randomid.'">' . $content . '</textarea>';
    $html .= '<div style="float: right; display: table;" id="mceSwitchAgent_' . $randomid . '">' . $lang->get('etc_tinymce_btn_text') . '&nbsp;&nbsp;|&nbsp;&nbsp;<a href="#" onclick="if ( !KILL_SWITCH ) { toggleMCE_'.$randomid.'(); return false; }">' . $lang->get('etc_tinymce_btn_graphical') . '</a></div>';
    $html .= '<script type="text/javascript">
                // <![CDATA[
                function toggleMCE_'.$randomid.'()
                {
                  var the_obj = document.getElementById(\'toggleMCEroot_' . $randomid . '\');
                  var panel = document.getElementById(\'mceSwitchAgent_' . $randomid . '\');
                  var text_editor = $lang.get("etc_tinymce_btn_text");
                  var graphical_editor = $lang.get("etc_tinymce_btn_graphical");
                  if ( the_obj.dnIsMCE == "yes" )
                  {
                    $dynano(the_obj).destroyMCE();
                    panel.innerHTML = text_editor + \'&nbsp;&nbsp;|&nbsp;&nbsp;<a href="#" onclick="if ( !KILL_SWITCH ) { toggleMCE_'.$randomid.'(); return false; }">\' + graphical_editor + \'</a>\';
                  }
                  else
                  {
                    $dynano(the_obj).switchToMCE();
                    panel.innerHTML = \'<a href="#" onclick="if ( !KILL_SWITCH ) { toggleMCE_'.$randomid.'(); return false; }">\' + text_editor + \'</a>&nbsp;&nbsp;|&nbsp;&nbsp;\' + graphical_editor;
                  }
                }
                // ]]>
              </script>';
    return $html;
  }
  
  /**
   * Allows individual parsing of template files. Similar to phpBB but follows the spirit of object-oriented programming ;)
   * Returns on object of class templateIndividual. Usage instructions can be found in the inline docs for that class.
   * @param $filename the filename of the template to be parsed
   * @return object
   */
   
  function makeParser($filename)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $filename = ENANO_ROOT.'/themes/'.$template->theme.'/'.$filename;
    if(!file_exists($filename)) die('templateIndividual: file '.$filename.' does not exist');
    $code = file_get_contents($filename);
    $parser = new templateIndividual($code);
    return $parser;
  }
  
  /**
   * Same as $template->makeParser(), but takes a string instead of a filename.
   * @param $text the text to parse
   * @return object
   */
   
  function makeParserText($code)
  {
    $parser = new templateIndividual($code);
    return $parser;
  }
  
  /**
   * Fetch the HTML for a plugin-added sidebar block
   * @param $name the plugin name
   * @return string
   */
   
  function fetch_block($id)
  {
    if(isset($this->plugin_blocks[$id])) return $this->plugin_blocks[$id];
    else return false;
  }
  
  /**
   * Fetches the contents of both sidebars.
   * @return array - key 0 is left, key 1 is right
   * @example list($left, $right) = $template->fetch_sidebar();
   */
   
  function fetch_sidebar()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $cache;
    
    $left = '';
    $right = '';
    
    // check the cache
    if ( !$session->user_logged_in && $data = $cache->fetch('anon_sidebar') )
    {
      if ( @$data['_theme_'] === $this->theme )
      {
        unset($data['_theme_']);
        foreach ( $data as &$md )
        {
          $md = str_replace('$USERNAME$', $session->username, $md);
          $md = str_replace('$PAGEID$', $paths->page, $md);
        }
        return $data;
      }
    }
    
    if ( !$this->fetch_block('Links') )
      $this->initLinksWidget();
    
    $q = $db->sql_query('SELECT item_id,sidebar_id,block_name,block_type,block_content FROM '.table_prefix.'sidebar' . "\n"
                           . '  WHERE item_enabled=1 ORDER BY sidebar_id ASC, item_order ASC;');
    if(!$q) $db->_die('The sidebar text data could not be selected.');
    
    $vars = $this->extract_vars('elements.tpl');
    
    if(isset($vars['sidebar_top'])) 
    {
      $top = $this->parse($vars['sidebar_top']);
      $left  .= $top;
      $right .= $top;
    }
    
    while($row = $db->fetchrow())
    {
      switch($row['block_type'])
      {
        case BLOCK_WIKIFORMAT:
        default:
          $parser = $this->makeParserText($vars['sidebar_section']);
          $c = RenderMan::render($row['block_content']);
          break;
        case BLOCK_TEMPLATEFORMAT:
          $parser = $this->makeParserText($vars['sidebar_section']);
          $c = $this->tplWikiFormat($row['block_content']);
          break;
        case BLOCK_HTML:
          $parser = $this->makeParserText($vars['sidebar_section_raw']);
          $c = $row['block_content'];
          break;
        case BLOCK_PHP:
          $parser = $this->makeParserText($vars['sidebar_section_raw']);
          ob_start();
          @eval($row['block_content']);
          $c = ob_get_contents();
          ob_end_clean();
          break;
        case BLOCK_PLUGIN:
          $parser = $this->makeParserText('{CONTENT}');
          $c = '<!-- PLUGIN -->' . (gettype($this->fetch_block($row['block_content'])) == 'string') ? $this->fetch_block($row['block_content']) : /* This used to say "can't find plugin block" but I think it's more friendly to just silently hide it. */ '';
          break;
      }
      // is there a {restrict} or {hideif} block?
      if ( preg_match('/\{(restrict|hideif) ([a-z0-9_\(\)\|&! ]+)\}/', $c, $match) )
      {
        // we have one, check the condition
        $type =& $match[1];
        $cond =& $match[2];
        $result = $this->process_condition($cond);
        if ( ( $type == 'restrict' && $result == 1 ) || ( $type == 'hideif' && $result == 2 ) )
        {
          // throw out this block, it's hidden for whatever reason by the sidebar script
          continue;
        }
        // didn't get a match, so hide the conditional logic
        $c = str_replace_once($match[0], '', $c);
      }
      
      $parser->assign_vars(Array( 'TITLE'=>$this->tplWikiFormat($row['block_name']), 'CONTENT'=>$c ));
      $run = $parser->run();
      if ( $row['block_type'] == BLOCK_PLUGIN )
      {
        $run = str_replace('{TITLE}', $this->tplWikiFormat($row['block_name']), $run);
      }
      if    ($row['sidebar_id'] == SIDEBAR_LEFT ) $left  .= $run;
      elseif($row['sidebar_id'] == SIDEBAR_RIGHT) $right .= $run;
      unset($parser);
    }
    $db->free_result();
    if(isset($vars['sidebar_bottom'])) 
    {
      $bottom = $this->parse($vars['sidebar_bottom']);
      $left  .= $bottom;
      $right .= $bottom;
    }
    $min = '';
    if(isset($vars['sidebar_top'])) 
    {
      $min .= $top;
    }
    if(isset($vars['sidebar_bottom']))
    {
      $min .= $bottom;
    }
    $return = Array($left, $right, $min);
    if ( getConfig('cache_thumbs') == '1' && !$session->user_logged_in )
    {
      $cachestore = enano_json_encode($return);
      $cachestore = str_replace($session->username, '$USERNAME$', $cachestore);
      $cachestore = str_replace($paths->page, '$PAGEID$', $cachestore);
      $cachestore = enano_json_decode($cachestore);
      $cachestore['_theme_'] = $this->theme;
      $cache->store('anon_sidebar', $cachestore, 10);
    }
    return $return;
  }
  
  function initLinksWidget()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // SourceForge/W3C buttons
    $ob = Array();
    if(getConfig('sflogo_enabled')=='1')
    {
      $sflogo_secure = ( isset($_SERVER['HTTPS']) ) ? 'https' : 'http';
      $ob[] = '<a style="text-align: center;" href="http://sourceforge.net/" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border-width: 0px;" alt="SourceForge.net Logo" src="' . $sflogo_secure . '://sflogo.sourceforge.net/sflogo.php?group_id='.getConfig('sflogo_groupid').'&amp;type='.getConfig('sflogo_type').'" /></a>';
    }
    if(getConfig('w3c_v32')     =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 3.2"  src="http://www.w3.org/Icons/valid-html32" /></a>';
    if(getConfig('w3c_v40')     =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 4.0"  src="http://www.w3.org/Icons/valid-html40" /></a>';
    if(getConfig('w3c_v401')    =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 4.01" src="http://www.w3.org/Icons/valid-html401" /></a>';
    if(getConfig('w3c_vxhtml10')=='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="Valid XHTML 1.0" src="http://www.w3.org/Icons/valid-xhtml10" /></a>';
    if(getConfig('w3c_vxhtml11')=='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="Valid XHTML 1.1" src="http://www.w3.org/Icons/valid-xhtml11" /></a>';
    if(getConfig('w3c_vcss')    =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="Valid CSS"       src="http://www.w3.org/Icons/valid-css" /></a>';
    if(getConfig('dbd_button')  =='1') $ob[] = '<a style="text-align: center;" href="http://www.defectivebydesign.org/join/button" onclick="if ( !KILL_SWITCH ) { window.open(this.href);return false; }"><img style="border: 0px solid #FFFFFF;" alt="DRM technology restricts what you can do with your computer" src="http://defectivebydesign.org/sites/nodrm.civicactions.net/files/images/dbd_sm_btn.gif" /><br /><small>Protect your freedom >></small></a>';
    
    $code = $plugins->setHook('links_widget');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if(count($ob) > 0 || getConfig('powered_btn') == '1') $sb_links = '<div style="text-align: center; padding: 5px 0;">'. ( ( getConfig('powered_btn') == '1' ) ? $this->fading_button : '' ) . implode('<br />', $ob).'</div>';
    else $sb_links = '';
    
    $this->sidebar_widget('Links', $sb_links);
  }
  
  /**
   * Builds a box showing unread private messages.
   */
  
  function notify_unread_pms()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( ( $paths->page_id == 'PrivateMessages' || $paths->page_id == 'Preferences' ) && $paths->namespace == 'Special' )
    {
      return '';
    }
    $ob = '<div class="usermessage">'."\n";
    $s = ( $session->unread_pms == 1 ) ? '' : 's';
    $ob .= "  <b>You have $session->unread_pms <a href=" . '"' . makeUrlNS('Special', 'PrivateMessages' ) . '"' . ">unread private message$s</a>.</b><br />\n  Messages: ";
    $q = $db->sql_query('SELECT message_id,message_from,subject,date FROM '.table_prefix.'privmsgs WHERE message_to=\'' . $session->username . '\' AND message_read=0 ORDER BY date DESC;');
    if ( !$q )
      $db->_die();
    $messages = array();
    while ( $row = $db->fetchrow() )
    {
      $messages[] = '<a href="' . makeUrlNS('Special', 'PrivateMessages/View/' . $row['message_id']) . '" title="Sent ' . enano_date('F d, Y h:i a', $row['date']) . ' by ' . $row['message_from'] . '">' . $row['subject'] . '</a>';
    }
    $ob .= implode(",\n    " , $messages)."\n";
    $ob .= '</div>'."\n";
    return $ob;
  }
  
} // class template

/**
 * The core of the template compilation engine. Independent from the Enano API for failsafe operation.
 * @param string text to process
 * @return string Compiled PHP code
 * @access private
 */

function template_compiler_core($text)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  // A random seed used to salt tags
  $seed = md5 ( microtime() . mt_rand() );
  
  // Strip out PHP sections
  preg_match_all('/<\?php(.+?)\?>/is', $text, $php_matches);
  
  foreach ( $php_matches[0] as $i => $match )
  {
    // Substitute the PHP section with a random tag
    $tag = "{PHP:$i:$seed}";
    $text = str_replace_once($match, $tag, $text);
  }
  
  // Escape slashes and single quotes in template code
  $text = str_replace('\\', '\\\\', $text);
  $text = str_replace('\'', '\\\'', $text);
  
  // Initialize the PHP compiled code
  $text = 'ob_start(); echo \''.$text.'\'; $tpl_code = ob_get_contents(); ob_end_clean(); return $tpl_code;';
  
  ##
  ## Main rules
  ##
  
  //
  // Conditionals
  //
  
  $keywords = array('BEGIN', 'BEGINNOT', 'IFSET', 'IFPLUGIN');
  
  // only do this if the plugins API is loaded
  if ( is_object(@$plugins) )
  {
    $code = $plugins->setHook('template_compile_logic_keyword');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
  }
  
  $keywords = implode('|', $keywords);
  
  // Matches
  //          1     2                 3                 4   56                       7     8        9
  $regexp = '/(<!-- ?(' . $keywords . ') ([A-z0-9_-]+) ?-->)([\w\W]*)((<!-- ?BEGINELSE \\3 ?-->)([\w\W]*))?(<!-- ?END(IF)? \\3 ?-->)/isU';
  
  /*
  The way this works is: match all blocks using the standard form with a different keyword in the block each time,
  and replace them with appropriate PHP logic. Plugin-extensible now. :-)
  */
  
  // This is a workaround for what seems like a PCRE bug
  while ( preg_match_all($regexp, $text, $matches) )
  {
  
  for ( $i = 0; $i < count($matches[0]); $i++ )
  {
    $start_tag =& $matches[1][$i];
    $type =& $matches[2][$i];
    $test =& $matches[3][$i];
    $particle_true  =& $matches[4][$i];
    $else_tag =& $matches[6][$i];
    $particle_else =& $matches[7][$i];
    $end_tag =& $matches[8][$i];
    
    switch($type)
    {
      case 'BEGIN':
        $cond = "isset(\$this->tpl_bool['$test']) && \$this->tpl_bool['$test']";
        break;
      case 'BEGINNOT':
        $cond = "!isset(\$this->tpl_bool['$test']) || ( isset(\$this->tpl_bool['$test']) && !\$this->tpl_bool['$test'] )";
        break;
      case 'IFPLUGIN':
        $cond = "getConfig('plugin_$test') == '1'";
        break;
      case 'IFSET':
        $cond = "isset(\$this->tpl_strings['$test'])";
        break;
      default:
        // only do this if the plugins API is loaded
        if ( is_object(@$plugins) )
        {
          $code = $plugins->setHook('template_compile_logic_cond');
          foreach ( $code as $cmd )
          {
            eval($cmd);
          }
        }
        break;
    }
    
    if ( !isset($cond) || ( isset($cond) && !is_string($cond) ) )
      continue;
    
    $tag_complete = <<<TPLCODE
';
    /* START OF CONDITION: $type ($test) */
    if ( $cond )
    {
      echo '$particle_true';
    /* ELSE OF CONDITION: $type ($test) */
    }
    else
    {
      echo '$particle_else';
    /* END OF CONDITION: $type ($test) */
    }
    echo '
TPLCODE;
    
    $text = str_replace_once($matches[0][$i], $tag_complete, $text);
  }
  }
  
  // For debugging ;-)
  // die("<pre>&lt;?php\n" . htmlspecialchars($text."\n\n".print_r($matches,true)) . "\n\n?&gt;</pre>");
  
  //
  // Data substitution/variables
  //
  
  // System messages
  $text = preg_replace('/<!-- SYSMSG ([A-z0-9\._-]+?) -->/is', '\' . $template->tplWikiFormat($paths->sysMsg(\'\\1\')) . \'', $text);
  
  // only do this if the plugins API is loaded
  if ( is_object(@$plugins) )
  {
    $code = $plugins->setHook('template_compile_subst');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
  }
  
  // Template variables
  $text = preg_replace('/\{([A-z0-9_-]+?)\}/is', '\' . $this->tpl_strings[\'\\1\'] . \'', $text);
  
  // Reinsert PHP
  
  foreach ( $php_matches[1] as $i => $match )
  {
    // Substitute the random tag with the "real" PHP code
    $tag = "{PHP:$i:$seed}";
    $text = str_replace_once($tag, "'; $match echo '", $text);
  }
  
  // echo('<pre>' . htmlspecialchars($text) . '</pre>');
  
  return $text;
}

/**
 * Handles parsing of an individual template file. Instances should only be created through $template->makeParser(). To use:
 *   - Call $template->makeParser(template file name) - file name should be something.tpl, css/whatever.css, etc.
 *   - Make an array of strings you want the template to access. $array['STRING'] would be referenced in the template like {STRING}
 *   - Make an array of boolean values. These can be used for conditionals in the template (<!-- IF something --> whatever <!-- ENDIF something -->)
 *   - Call assign_vars() to pass the strings to the template parser. Same thing with assign_bool().
 *   - Call run() to parse the template and get your fully compiled HTML.
 * @access private
 */

class templateIndividual extends template
{
  var $tpl_strings, $tpl_bool, $tpl_code;
  var $compiled = false;
  /**
   * Constructor.
   */
  function __construct($text)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->tpl_code = $text;
    $this->tpl_strings = $template->tpl_strings;
    $this->tpl_bool = $template->tpl_bool;
  }
  /**
   * PHP 4 constructor. Deprecated in 1.1.x.
   */
  /*
  function templateIndividual($text)
  {
    $this->__construct($text);
  }
  */
  
  /**
   * Assigns an array of string values to the template. Strings can be accessed from the template by inserting {KEY_NAME} in the template file.
   * @param $vars array
   */
  
  function assign_vars($vars)
  {
    $this->tpl_strings = array_merge($this->tpl_strings, $vars);
  }
  
  /**
   * Assigns an array of boolean values to the template. These can be used for <!-- IF ... --> statements.
   * @param $vars array
   */
  
  function assign_bool($vars)
  {
    $this->tpl_bool = array_merge($this->tpl_bool, $vars);
  }
  
  /**
   * Compiles and executes the template code.
   * @return string
   */
  function run()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->compiled)
    {
      $this->tpl_code = $this->compile_template_text($this->tpl_code);
      $this->compiled = true;
    }
    return eval($this->tpl_code);
  }
}

/**
 * A version of the template compiler that does not rely at all on the other parts of Enano. Used during installation and for showing
 * "critical error" messages. ** REQUIRES ** the Oxygen theme.
 */

class template_nodb
{
  var $fading_button, $tpl_strings, $tpl_bool, $theme, $style, $no_headers, $additional_headers, $sidebar_extra, $sidebar_widgets, $toolbar_menu, $theme_list, $named_theme_list;
  function __construct()
  {
    $this->tpl_bool    = Array();
    $this->tpl_strings = Array();
    $this->sidebar_extra = '';
    $this->sidebar_widgets = '';
    $this->toolbar_menu = '';
    $this->additional_headers = '<style type="text/css">div.pagenav { border-top: 1px solid #CCC; padding-top: 7px; margin-top: 10px; }</style>';
    
    $this->fading_button = '<div style="background-image: url('.scriptPath.'/images/about-powered-enano-hover.png); background-repeat: no-repeat; width: 88px; height: 31px; margin: 0 auto 5px auto;">
                              <a href="http://enanocms.org/" onclick="window.open(this.href); return false;"><img style="border-width: 0;" alt=" " src="'.scriptPath.'/images/about-powered-enano.png" onmouseover="domOpacity(this, 100, 0, 500);" onmouseout="domOpacity(this, 0, 100, 500);" /></a>
                            </div>';
    
    // get list of themes
    $this->theme_list = array();
    $this->named_theme_list = array();
    $order = 0;
    
    if ( $dir = @opendir( ENANO_ROOT . '/themes' ) )
    {
      while ( $dh = @readdir($dir) )
      {
        if ( $dh == '.' || $dh == '..' || !is_dir( ENANO_ROOT . "/themes/$dh" ) )
          continue;
        $theme_dir = ENANO_ROOT . "/themes/$dh";
        if ( !file_exists("$theme_dir/theme.cfg") )
          continue;
        $data = array(
            'theme_id' => $dh,
            'theme_name' => ucwords($dh),
            'enabled' => 1,
            'theme_order' => ++$order,
            'default_style' => $this->get_default_style($dh)
          );
        $this->named_theme_list[$dh] = $data;
        $this->theme_list[] =& $this->named_theme_list[$dh];
      }
      @closedir($dir);
    }
  }
  function template() {
    $this->__construct();
  }
  function get_default_style($theme_id)
  {
    if ( !is_dir( ENANO_ROOT . "/themes/$theme_id/css" ) )
      return false;
    $ds = false;
    if ( $dh = @opendir( ENANO_ROOT . "/themes/$theme_id/css" ) )
    {
      while ( $dir = @readdir($dh) )
      {
        if ( !preg_match('/\.css$/', $dir) )
          continue;
        if ( $dir == '_printable.css' )
          continue;
        $ds = preg_replace('/\.css$/', '', $dir);
        break;
      }
      closedir($dh);
    }
    else
    {
      return false;
    }
    return $ds;
  }
  function get_css($s = false) {
    if($s)
      return $this->process_template('css/'.$s);
    else
      return $this->process_template('css/'.$this->style.'.css');
  }
  function load_theme($name, $css, $auto_init = true)
  {
    if ( !isset($this->named_theme_list[$name]) )
      $name = $this->theme_list[0]['theme_id'];
    
    if ( !file_exists(ENANO_ROOT . "/themes/$name/css/$css.css") )
      $css = $this->named_theme_list[$name]['default_style'];
    
    $this->theme = $name;
    $this->style = $css;
    
    $this->tpl_strings['SCRIPTPATH'] = scriptPath;
    if ( $auto_init )
      $this->init_vars();
  }
  function add_header($html)
  {
    $this->additional_headers .= "\n<!-- ----------------------------------------------------------- -->\n\n    " . $html;
  }
  function init_vars()
  {
    global $sideinfo;
    global $this_page;
    global $lang;
    global $db, $session, $paths, $template, $plugins; // Common objects
    $tplvars = $this->extract_vars('elements.tpl');
    $tb = '';
    // Get the "article" button text (depends on namespace)
    if(defined('IN_ENANO_INSTALL') && is_object($lang)) $ns = $lang->get('meta_btn_article');
    else $ns = 'system error page';
    $t = str_replace('{FLAGS}', 'onclick="return false;" title="Hey! A button that doesn\'t do anything. Clever..." accesskey="a"', $tplvars['toolbar_button']);
    $t = str_replace('{HREF}', '#', $t);
    $t = str_replace('{TEXT}', $ns, $t);
    $tb .= $t;
    
    // Page toolbar
    
    $this->tpl_bool = Array(
      'auth_admin'=>true,
      'user_logged_in'=>true,
      'right_sidebar'=>false,
      );
    $this->tpl_bool['in_sidebar_admin'] = false;
    
    $this->tpl_bool['auth_rename'] = false;
    
    $asq = $asa = '';
    
    $this->tpl_bool['fixed_menus'] = false;
    $slink = defined('IN_ENANO_INSTALL') ? scriptPath.'/install.php?mode=css' : makeUrlNS('Special', 'CSS');
    
    $title = ( is_object($paths) ) ? $paths->page : 'Critical error';
    
    $headers = '<style type="text/css">div.pagenav { border-top: 1px solid #CCC; padding-top: 7px; margin-top: 10px; }</style>';
    
    $js_dynamic = '';
    if ( defined('IN_ENANO_INSTALL') )
    {
      $js_dynamic .= '<script type="text/javascript" src="install.php?mode=langjs"></script>';
    }
    $js_dynamic .= '<script type="text/javascript">var title="'. $title .'"; var scriptPath="'.scriptPath.'"; var cdnPath="'.scriptPath.'"; var ENANO_SID=""; var AES_BITS='.AES_BITS.'; var AES_BLOCKSIZE=' . AES_BLOCKSIZE . '; var pagepass=\'\'; var ENANO_LANG_ID = 1;</script>';
    
    global $site_name, $site_desc;
    $site_default_name = ( !empty($site_name) ) ? $site_name : 'Critical error';
    $site_default_desc = ( !empty($site_desc) ) ? $site_desc : 'This site is experiencing a problem and cannot load.';
    
    $site_name_final = ( defined('IN_ENANO_INSTALL') && is_object($lang) ) ? $lang->get('meta_site_name') : $site_default_name;
    $site_desc_final = ( defined('IN_ENANO_INSTALL') && is_object($lang) ) ? $lang->get('meta_site_desc') : $site_default_desc;
    
    // The rewritten template engine will process all required vars during the load_template stage instead of (cough) re-processing everything each time around.
    $tpl_strings = Array(
      'PAGE_NAME'=>$this_page,
      'PAGE_URLNAME'=>'Null',
      'SITE_NAME' => $site_name_final,
      'USERNAME'=>'admin',
      'SITE_DESC' => $site_desc_final,
      'TOOLBAR'=>$tb,
      'SCRIPTPATH'=>scriptPath,
      'CONTENTPATH'=>contentPath,
      'CDNPATH' => scriptPath,
      'JS_HEADER' => '<script type="text/javascript" src="' . scriptPath . '/includes/clientside/static/enano-lib-basic.js"></script>',
      'JS_FOOTER' => '',
      'ADMIN_SID_QUES'=>$asq,
      'ADMIN_SID_AMP'=>$asa,
      'ADMIN_SID_AMP_HTML'=>'',
      'ADDITIONAL_HEADERS'=>$this->additional_headers,
      'SIDEBAR_EXTRA'=>'',
      'COPYRIGHT'=>( defined('IN_ENANO_INSTALL') && is_object($lang) ) ? $lang->get('meta_enano_copyright') : ( defined('ENANO_CONFIG_FETCHED') ? getConfig('copyright_notice') : '' ),
      'TOOLBAR_EXTRAS'=>'',
      'REQUEST_URI'=>( isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '' ).$_SERVER['REQUEST_URI'],
      'STYLE_LINK'=>$slink,
      'LOGOUT_LINK'=>'',
      'THEME_LINK'=>'',
      'TEMPLATE_DIR'=>scriptPath.'/themes/'.$this->theme,
      'THEME_ID'=>$this->theme,
      'STYLE_ID'=>$this->style,
      'JS_DYNAMIC_VARS'=>$js_dynamic,
      'SIDEBAR_RIGHT'=>'',
      'REPORT_URI' => '',
      'URL_ABOUT_ENANO' => 'http://enanocms.org/'
      );
    $this->tpl_strings = array_merge($tpl_strings, $this->tpl_strings);
    
    $sidebar = ( is_array(@$sideinfo) ) ? $sideinfo : '';
    if ( $sidebar != '' )
    {
      if ( isset($tplvars['sidebar_top']) )
      {
        $text = $this->makeParserText($tplvars['sidebar_top']);
        $top = $text->run();
      }
      else
      {
        $top = '';
      }
      
      $p = $this->makeParserText($tplvars['sidebar_section']);
      $b = $this->makeParserText($tplvars['sidebar_button']);
      $sidebar_text = '';
      
      foreach ( $sidebar as $title => $links )
      {
        $p->assign_vars(array(
          'TITLE' => $title
        ));
        // build content
        $content = '';
        foreach ( $links as $link_text => $url )
        {
          $b->assign_vars(array(
            'HREF' => htmlspecialchars($url),
            'FLAGS' => '',
            'TEXT' => $link_text
          ));
          $content .= $b->run();
        }
        $p->assign_vars(array(
          'CONTENT' => $content
        ));
        $sidebar_text .= $p->run();
      }
      
      if ( isset($tplvars['sidebar_bottom']) )
      {
        $text = $this->makeParserText($tplvars['sidebar_bottom']);
        $bottom = $text->run();
      }
      else
      {
        $bottom = '';
      }
      $sidebar = $top . $sidebar_text . $bottom;
    }
    $this->tpl_strings['SIDEBAR_LEFT'] = $sidebar;
    
    $this->tpl_bool['sidebar_left']  = ( $this->tpl_strings['SIDEBAR_LEFT']  != '') ? true : false;
    $this->tpl_bool['sidebar_right'] = ( $this->tpl_strings['SIDEBAR_RIGHT'] != '') ? true : false;
    $this->tpl_bool['right_sidebar'] = $this->tpl_bool['sidebar_right']; // backward compatibility
    $this->tpl_bool['stupid_mode'] = true;
  }
  function header($simple = false) 
  {
    $filename = ( $simple ) ? 'simple-header.tpl' : 'header.tpl';
    if ( !$this->no_headers )
    {
      echo $this->process_template($filename);
    }
  }
  function footer($simple = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if(!$this->no_headers) {
      global $_starttime;
      
      $filename = ( $simple ) ? 'simple-footer.tpl' : 'footer.tpl';
      $t = $this->process_template($filename);
      
      $f = microtime_float();
      $f = $f - $_starttime;
      $f = round($f, 4);
      
      if ( is_object($lang) )
      {
        $t_loc = $lang->get('page_msg_stats_gentime_short', array('time' => $f));
        $t_loc_long = $lang->get('page_msg_stats_gentime_long', array('time' => $f));
        $q_loc = '<a href="' . $this->tpl_strings['REPORT_URI'] . '">' . $lang->get('page_msg_stats_sql', array('nq' => ( is_object($db) ? $db->num_queries : 'N/A' ))) . '</a>';
        $dbg = $t_loc;
        $dbg_long = $t_loc_long;
        if ( $session->user_level >= USER_LEVEL_ADMIN )
        {
          $dbg .= "&nbsp;&nbsp;|&nbsp;&nbsp;$q_loc";
          $dbg_long .= "&nbsp;&nbsp;|&nbsp;&nbsp;$q_loc";
        }
        $t = str_replace('[[EnanoPoweredLink]]', $lang->get('page_enano_powered', array('about_uri' => $this->tpl_strings['URL_ABOUT_ENANO'])), $t);
        $t = str_replace('[[EnanoPoweredLinkLong]]', $lang->get('page_enano_powered_long', array('about_uri' => $this->tpl_strings['URL_ABOUT_ENANO'])), $t);
      }
      else
      {
        $t_loc = "Time: {$f}s";
        $t_loc_long = "Generated in {$f}sec";
        $q_loc = '<a href="' . $this->tpl_strings['REPORT_URI'] . '">' . ( is_object($db) ? "{$db->num_queries} SQL" : 'Queries: N/A' ) . '</a>';
        $dbg = $t_loc;
        $dbg_long = $t_loc_long;
        if ( is_object($session) )
        {
          if ( $session->user_level >= USER_LEVEL_ADMIN )
          {
            $dbg .= "&nbsp;&nbsp;|&nbsp;&nbsp;$q_loc";
            $dbg_long .= "&nbsp;&nbsp;|&nbsp;&nbsp;$q_loc";
          }
        }
        $t = str_replace('[[EnanoPoweredLink]]', 'Powered by <a href="http://enanocms.org/" onclick="window.open(this.href); return false;">Enano</a>', $t);
        $t = str_replace('[[EnanoPoweredLinkLong]]', 'Website engine powered by <a href="http://enanocms.org/" onclick="window.open(this.href); return false;">Enano</a>', $t);
      }
      
      $t = str_replace('[[Stats]]', $dbg, $t);
      $t = str_replace('[[StatsLong]]', $dbg_long, $t);
      $t = str_replace('[[NumQueries]]', ( is_object($db) ? (string)$db->num_queries : '0' ), $t);
      $t = str_replace('[[GenTime]]', (string)$f, $t);
      $t = str_replace('[[NumQueriesLoc]]', $q_loc, $t);
      $t = str_replace('[[GenTimeLoc]]', $t_loc, $t);
      
      if ( defined('ENANO_DEBUG') )
        $t = str_replace('</body>', '<div id="profile" style="margin: 10px;">' . profiler_make_html() . '</div></body>', $t);
      
      echo $t;
    }
    else return '';
  }
  function getHeader()
  {
    if(!$this->no_headers) return $this->process_template('header.tpl');
    else return '';
  }
  function getFooter()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->no_headers) {
      global $_starttime;
      $f = microtime(true);
      $f = $f - $_starttime;
      $f = round($f, 4);
      if(defined('IN_ENANO_INSTALL')) $nq = 'N/A';
      else $nq = $db->num_queries;
      if($nq == 0) $nq = 'N/A';
      $dbg = 'Time: '.$f.'s  |  Queries: '.$nq;
      if($nq == 0) $nq = 'N/A';
      $t = $this->process_template('footer.tpl');
      $t = str_replace('[[Stats]]', $dbg, $t);
      return $t;
    }
    else return '';
  }
  
  function process_template($file)
  {
    $compiled = $this->compile_template($file);
    $result = eval($compiled);
    return $result;
  }
  
  function extract_vars($file) {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!is_file(ENANO_ROOT . '/themes/'.$this->theme.'/'.$file)) die('Cannot find '.$file.' file for style "'.$this->theme.'", exiting');
    $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$file);
    preg_match_all('#<\!-- VAR ([A-z0-9_-]*) -->(.*?)<\!-- ENDVAR \\1 -->#is', $text, $matches);
    $tplvars = Array();
    for($i=0;$i<sizeof($matches[1]);$i++)
    {
      $tplvars[$matches[1][$i]] = $matches[2][$i];
    }
    return $tplvars;
  }
  function compile_template($text)
  {
    $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$text);
    return $this->compile_template_text_post(template_compiler_core($text));
  }
  
  function compile_template_text($text)
  {
    return $this->compile_template_text_post(template_compiler_core($text));
  }
  
  /**
   * Post-processor for template code. Basically what this does is it localizes {lang:foo} blocks.
   * @param string Mostly-processed TPL code
   * @return string
   */
  
  function compile_template_text_post($text)
  {
    global $lang;
    preg_match_all('/\{lang:([a-z0-9]+_[a-z0-9_]+)\}/', $text, $matches);
    foreach ( $matches[1] as $i => $string_id )
    {
      if ( is_object(@$lang) )
      {
        $string = $lang->get($string_id);
      }
      else
      {
        $string = '[language not loaded]';
      }
      $string = str_replace('\\', '\\\\', $string);
      $string = str_replace('\'', '\\\'', $string);
      $text = str_replace_once($matches[0][$i], $string, $text);
    }
    return $text;
  }
  
  /**
   * Allows individual parsing of template files. Similar to phpBB but follows the spirit of object-oriented programming ;)
   * Returns on object of class templateIndividual. Usage instructions can be found in the inline docs for that class.
   * @param $filename the filename of the template to be parsed
   * @return object
   */
   
  function makeParser($filename)
  {
    $filename = ENANO_ROOT.'/themes/'.$this->theme.'/'.$filename;
    if(!file_exists($filename)) die('templateIndividual: file '.$filename.' does not exist');
    $code = file_get_contents($filename);
    $parser = new templateIndividualSafe($code, $this);
    return $parser;
  }
  
  /**
   * Same as $template->makeParser(), but takes a string instead of a filename.
   * @param $text the text to parse
   * @return object
   */
   
  function makeParserText($code)
  {
    $parser = new templateIndividualSafe($code, $this);
    return $parser;
  }
  
  /**
   * Assigns an array of string values to the template. Strings can be accessed from the template by inserting {KEY_NAME} in the template file.
   * @param $vars array
   */
  function assign_vars($vars)
  {
    if(is_array($this->tpl_strings))
      $this->tpl_strings = array_merge($this->tpl_strings, $vars);
    else
      $this->tpl_strings = $vars;
  }
   
} // class template_nodb

/**
 * Identical to templateIndividual, except extends template_nodb instead of template
 * @see class template
 */
 
class templateIndividualSafe extends template_nodb
{
  var $tpl_strings, $tpl_bool, $tpl_code;
  var $compiled = false;
  /**
   * Constructor.
   */
  function __construct($text, $parent)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->tpl_code = $text;
    $this->tpl_strings = $parent->tpl_strings;
    $this->tpl_bool = $parent->tpl_bool;
  }
  /**
   * PHP 4 constructor.
   */
  function templateIndividual($text)
  {
    $this->__construct($text);
  }
  /**
   * Assigns an array of string values to the template. Strings can be accessed from the template by inserting {KEY_NAME} in the template file.
   * @param $vars array
   */
  function assign_vars($vars)
  {
    if(is_array($this->tpl_strings))
      $this->tpl_strings = array_merge($this->tpl_strings, $vars);
    else
      $this->tpl_strings = $vars;
  }
  /**
   * Assigns an array of boolean values to the template. These can be used for <!-- IF ... --> statements.
   * @param $vars array
   */
  function assign_bool($vars)
  {
    $this->tpl_bool = array_merge($this->tpl_bool, $vars);
  }
  /**
   * Compiles and executes the template code.
   * @return string
   */
  function run()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->compiled)
    {
      $this->tpl_code = $this->compile_template_text($this->tpl_code);
      $this->compiled = true;
    }
    return eval($this->tpl_code);
  }
}

?>

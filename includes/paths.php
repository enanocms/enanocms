<?php

/**
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 * paths.php - The part of Enano that actually manages content. Everything related to page handling and namespaces is in here.
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 * @package Enano
 * @subpackage PathManager
 * @see http://enanocms.org/Help:API_Documentation
 */
 
class pathManager
{
  public $pages, $custom_page, $cpage, $page, $fullpage, $page_exists, $page_id, $namespace, $nslist, $admin_tree, $wiki_mode, $page_protected, $template_cache, $external_api_page;
  
  /**
   * List of custom processing functions for namespaces. This is protected so trying to do anything with it will throw an error.
   * @access private
   * @var array
   */
  
  protected $namespace_processors;
  
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $GLOBALS['paths'] =& $this;
    $this->pages = Array();
    
    // DEFINE NAMESPACES HERE
    // The key names should NOT EVER be changed, or Enano will be very broken
    $this->nslist = Array(
      'Article' =>'',
      'User'    =>'User:',
      'File'    =>'File:',
      'Help'    =>'Help:',
      'Admin'   =>'Admin:',
      'Special' =>'Special:',
      'System'  =>'Enano:',
      'Template'=>'Template:',
      'Category'=>'Category:',
      'API'=>'SystemAPI:',
      'Project' =>sanitize_page_id(getConfig('site_name')).':',
      );
    
    // ACL types
    // These can also be added from within plugins
    
    $session->register_acl_type('read',                   AUTH_ALLOW,    'perm_read');
    $session->register_acl_type('post_comments',          AUTH_ALLOW,    'perm_post_comments',          Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_comments',          AUTH_ALLOW,    'perm_edit_comments',          Array('post_comments'),                                   'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_page',              AUTH_WIKIMODE, 'perm_edit_page',              Array('view_source'),                                     'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_wysiwyg',           AUTH_ALLOW,    'perm_edit_wysiwyg',           Array(),                                                  'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('view_source',            AUTH_WIKIMODE, 'perm_view_source',            Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category'); // Only used if the page is protected
    $session->register_acl_type('mod_comments',           AUTH_DISALLOW, 'perm_mod_comments',           Array('edit_comments'),                                   'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_view',           AUTH_WIKIMODE, 'perm_history_view',           Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_rollback',       AUTH_DISALLOW, 'perm_history_rollback',       Array('history_view'),                                    'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_rollback_extra', AUTH_DISALLOW, 'perm_history_rollback_extra', Array('history_rollback'),                                'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('protect',                AUTH_DISALLOW, 'perm_protect',                Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('rename',                 AUTH_WIKIMODE, 'perm_rename',                 Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('clear_logs',             AUTH_DISALLOW, 'perm_clear_logs',             Array('read', 'protect', 'even_when_protected'),          'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('vote_delete',            AUTH_ALLOW,    'perm_vote_delete',            Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('vote_reset',             AUTH_DISALLOW, 'perm_vote_reset',             Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('delete_page',            AUTH_DISALLOW, 'perm_delete_page',            Array(),                                                  'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_create',             AUTH_ALLOW,    'perm_tag_create',             Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_delete_own',         AUTH_ALLOW,    'perm_tag_delete_own',         Array('read', 'tag_create'),                              'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_delete_other',       AUTH_DISALLOW, 'perm_tag_delete_other',       Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('set_wiki_mode',          AUTH_DISALLOW, 'perm_set_wiki_mode',          Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('password_set',           AUTH_DISALLOW, 'perm_password_set',           Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('password_reset',         AUTH_DISALLOW, 'perm_password_reset',         Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('mod_misc',               AUTH_DISALLOW, 'perm_mod_misc',               Array(),                                                  'All');
    $session->register_acl_type('edit_cat',               AUTH_WIKIMODE, 'perm_edit_cat',               Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('even_when_protected',    AUTH_DISALLOW, 'perm_even_when_protected',    Array('edit_page', 'rename', 'mod_comments', 'edit_cat'), 'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('upload_files',           AUTH_DISALLOW, 'perm_upload_files',           Array('create_page'),                                     'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('upload_new_version',     AUTH_WIKIMODE, 'perm_upload_new_version',     Array('upload_files'),                                    'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('create_page',            AUTH_WIKIMODE, 'perm_create_page',            Array(),                                                  'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('html_in_pages',          AUTH_DISALLOW, 'perm_html_in_pages',          Array('edit_page'),                                       'Article|User|Project|Template|File|Help|System|Category|Admin');
    $session->register_acl_type('php_in_pages',           AUTH_DISALLOW, 'perm_php_in_pages',           Array('edit_page', 'html_in_pages'),                      'Article|User|Project|Template|File|Help|System|Category|Admin');
    $session->register_acl_type('custom_user_title',      AUTH_DISALLOW, 'perm_custom_user_title',      Array(),                                                  'User|Special');
    $session->register_acl_type('edit_acl',               AUTH_DISALLOW, 'perm_edit_acl',               Array());
    
    // DO NOT add new admin pages here! Use a plugin to call $paths->addAdminNode();
    $this->addAdminNode('adm_cat_general',    'adm_page_general_config', 'GeneralConfig',          array(2, 2));
    $this->addAdminNode('adm_cat_general',    'adm_page_file_uploads',   'UploadConfig',           array(2, 5));
    $this->addAdminNode('adm_cat_general',    'adm_page_file_types',     'UploadAllowedMimeTypes', array(1, 5));
    $this->addAdminNode('adm_cat_content',    'adm_page_manager',        'PageManager',            array(1, 4));
    $this->addAdminNode('adm_cat_content',    'adm_page_editor',         'PageEditor',             array(3, 3));
    $this->addAdminNode('adm_cat_content',    'adm_page_pg_groups',      'PageGroups',             array(4, 3));
    $this->addAdminNode('adm_cat_appearance', 'adm_page_themes',         'ThemeManager',           array(4, 4));
    $this->addAdminNode('adm_cat_appearance', 'adm_page_plugins',        'PluginManager',          array(2, 4));
    $this->addAdminNode('adm_cat_appearance', 'adm_page_db_backup',      'DBBackup',               array(1, 2));
    $this->addAdminNode('adm_cat_appearance', 'adm_page_lang_manager',   'LangManager',            array(1, 3));
    $this->addAdminNode('adm_cat_appearance', 'adm_page_cache_manager',  'CacheManager',           array(3, 1));
    $this->addAdminNode('adm_cat_users',      'adm_page_users',          'UserManager',            array(3, 5));
    $this->addAdminNode('adm_cat_users',      'adm_page_user_groups',    'GroupManager',           array(3, 2));
    $this->addAdminNode('adm_cat_users',      'adm_page_coppa',          'COPPA',                  array(4, 1));
    $this->addAdminNode('adm_cat_users',      'adm_page_mass_email',     'MassEmail',              array(2, 3));
    $this->addAdminNode('adm_cat_users',      'adm_page_user_ranks',     'UserRanks',              array(4, 5));
    $this->addAdminNode('adm_cat_security',   'adm_page_security_log',   'SecurityLog',            array(3, 4));
    $this->addAdminNode('adm_cat_security',   'adm_page_ban_control',    'BanControl',             array(2, 1));
    
    $code = $plugins->setHook('acl_rule_init');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $this->wiki_mode = ( getConfig('wiki_mode') == '1' ) ? 1 : 0;
    $this->template_cache = Array();
  }
  
  function parse_url($sanitize = true)
  {
    $title = '';
    if ( isset($_GET['title']) )
    {
      $title = $_GET['title'];
    }
    else if ( isset($_SERVER['PATH_INFO']) )
    {
      // fix for apache + CGI (occurred on a GoDaddy server, thanks mm3)
      if ( @substr(@$_SERVER['GATEWAY_INTERFACE'], 0, 3) === 'CGI' && $_SERVER['PATH_INFO'] == scriptPath . '/index.php' )
      {
        // do nothing; ignore PATH_INFO
      }
      else
      {
        $title = substr($_SERVER['PATH_INFO'], ( strpos($_SERVER['PATH_INFO'], '/') ) + 1 );
      }
    }
    else
    {
      // This method really isn't supported because apache has a habit of passing dots as underscores, thus corrupting the request
      // If you really want to try it, the URI format is yoursite.com/?/Page_title
      if ( count($_GET) > 0 )
      {
        list($getkey) = array_keys($_GET);
        if ( substr($getkey, 0, 1) == '/' )
        {
          $title = substr($getkey, 1);
        }
      }
    }
    return ( $sanitize ) ? sanitize_page_id($title) : $title;
  }
  
  function init()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $cache;
    
    $code = $plugins->setHook('paths_init_before');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if ( $page_cache = $cache->fetch('page_meta') )
    {
      $this->pages = array_merge($this->pages, $page_cache);
    }
    else
    {
      $e = $db->sql_query('SELECT name,urlname,namespace,special,visible,comments_on,protected,delvotes,' . "\n"
                          . '  delvote_ips,wiki_mode,password FROM '.table_prefix.'pages ORDER BY name;');
      
      if( !$e )
      {
        $db->_die('The error seems to have occured while selecting the page information. File: includes/paths.php; line: '.__LINE__);
      }
      while($r = $db->fetchrow())
      {
        $r = $this->calculate_metadata_from_row($r);
        
        $this->pages[$r['urlname']] = $r;
        $this->pages[] =& $this->pages[$r['urlname']];
      }
      
      $this->update_metadata_cache();
    }
    $db->free_result();
    if ( defined('ENANO_INTERFACE_INDEX') || defined('ENANO_INTERFACE_AJAX') || defined('IN_ENANO_UPGRADE') )
    {
      $title = $this->parse_url(false);
      if ( empty($title) && get_main_page() != '' )
      {
        $this->main_page();
      }
      if ( strstr($title, ' ') || strstr($title, '+') || strstr($title, '%20') )
      {
        $title = sanitize_page_id($title);
        redirect(makeUrl($title), '', '', 0);
      }
      $title = sanitize_page_id($title);
      // We've got the title, pull the namespace from it
      $namespace = 'Article';
      $page_id = $title;
      foreach ( $this->nslist as $ns => $prefix )
      {
        $prefix_len = strlen($prefix);
        if ( substr($title, 0, $prefix_len) == $prefix )
        {
          $page_id = substr($title, $prefix_len);
          $namespace = $ns;
        }
      }
      $this->namespace = $namespace;
      $this->fullpage = $title;
      if ( $namespace == 'Special' || $namespace == 'Admin' )
      {
        list($page_id) = explode('/', $page_id);
      }
      $this->page = $this->nslist[$namespace] . $page_id;
      $this->page_id = $page_id;
      // die("All done setting parameters. What we've got:<br/>namespace: $namespace<br/>fullpage: $this->fullpage<br/>page: $this->page<br/>page_id: $this->page_id");
    }
    else
    {
      // Starting up Enano with the API from a page that wants to do its own thing. Generate
      // metadata for an anonymous page and avoid redirection at all costs.
      if ( isset($GLOBALS['title']) )
      {
        $title =& $GLOBALS['title'];
      }
      else
      {
        $title = basename($_SERVER['SCRIPT_NAME']);
      }
      $base_uri = str_replace( scriptPath . '/', '', $_SERVER['SCRIPT_NAME'] );
      $this->page = $this->nslist['API'] . sanitize_page_id($base_uri);
      $this->fullpage = $this->nslist['API'] . sanitize_page_id($base_uri);
      $this->namespace = 'API';
      $this->cpage = array(
          'name' => $title,
          'urlname' => sanitize_page_id($base_uri),
          'namespace' => 'API',
          'special' => 1,
          'visible' => 1,
          'comments_on' => 1,
          'protected' => 1,
          'delvotes' => 0,
          'delvote_ips' => ''
        );
      $this->external_api_page = true;
      $code = $plugins->setHook('paths_external_api_page');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
    }
    
    $this->page = sanitize_page_id($this->page);
    $this->fullpage = sanitize_page_id($this->fullpage);
    
    if(isset($this->pages[$this->page]))
    {
      $this->page_exists = true;
      $this->cpage = $this->pages[$this->page];
      $this->page_id =& $this->cpage['urlname_nons'];
      $this->namespace = $this->cpage['namespace'];
      if(!isset($this->cpage['wiki_mode'])) $this->cpage['wiki_mode'] = 2;
      
      // Determine the wiki mode for this page, now that we have this->cpage established
      if($this->cpage['wiki_mode'] == 2)
      {
        $this->wiki_mode = (int)getConfig('wiki_mode');
      }
      else
      {
        $this->wiki_mode = $this->cpage['wiki_mode'];
      }
      // Allow the user to create/modify his user page uncondtionally (admins can still protect the page)
      if($this->page == $this->nslist['User'].str_replace(' ', '_', $session->username))
      {
        $this->wiki_mode = true;
      }
      // And above all, if the site requires wiki mode to be off for non-logged-in users, disable it now
      if(getConfig('wiki_mode_require_login')=='1' && !$session->user_logged_in)
      {
        $this->wiki_mode = false;
      }
      if($this->cpage['protected'] == 2)
      {
        // The page is semi-protected, determine permissions
        if($session->user_logged_in && $session->reg_time + 60*60*24*4 < time()) 
        {
          $this->page_protected = 0;
        }
        else
        {
          $this->page_protected = 1;
        }
      }
      else
      {
        $this->page_protected = $this->cpage['protected'];
      }
    }
    else
    {
      $this->page_exists = false;
      $page_name = dirtify_page_id($this->page);
      $page_name = str_replace('_', ' ', $page_name);
      
      $pid_cleaned = sanitize_page_id($this->page);
      if ( $pid_cleaned != $this->page )
      {
        redirect(makeUrl($pid_cleaned), 'Sanitizer message', 'page id sanitized', 0);
      }
      
      if ( !is_array($this->cpage) )
      {
        $this->cpage = Array(
          'name'=>$page_name,
          'urlname'=>$this->page,
          'namespace'=>'Article',
          'special'=>0,
          'visible'=>0,
          'comments_on'=>1,
          'protected'=>0,
          'delvotes'=>0,
          'delvote_ips'=>'',
          'wiki_mode'=>2,
          );
      }
      // Look for a namespace prefix in the urlname, and assign a different namespace, if necessary
      $k = array_keys($this->nslist);
      for($i=0;$i<sizeof($this->nslist);$i++)
      {
        $ln = strlen($this->nslist[$k[$i]]);
        if( substr($this->page, 0, $ln) == $this->nslist[$k[$i]] )
        {
          $this->cpage['namespace'] = $k[$i];
          $this->cpage['urlname_nons'] = substr($this->page, strlen($this->nslist[$this->cpage['namespace']]), strlen($this->page));
          if(!isset($this->cpage['wiki_mode'])) 
          {
            $this->cpage['wiki_mode'] = 2;
          }
        }
      }
      $this->namespace = $this->cpage['namespace'];
      $this->page_id =& $this->cpage['urlname_nons'];
      
      if($this->namespace=='System') 
      {
        $this->cpage['protected'] = 1;
      }
      if($this->namespace == 'Special' && !$this->external_api_page)
      {
        // Can't load nonexistent pages
        if( is_string(get_main_page()) )
        {
          $main_page = makeUrl(get_main_page());
        }
        else
        {
          $main_page = makeUrl($this->pages[0]['urlname']);
        }
        redirect($main_page, $lang->get('page_msg_special_404_title'), $lang->get('page_msg_special_404_body', array('sp_link' => makeUrlNS('Special', 'SpecialPages'))), 15);
        exit;
      }
      // Allow the user to create/modify his user page uncondtionally (admins can still protect the page)
      if($this->page == $this->nslist['User'].str_replace(' ', '_', $session->username)) 
      {
        $this->wiki_mode = true;
      }
    }
    // This is used in the admin panel to keep track of form submission targets
    $this->cpage['module'] = $this->cpage['urlname'];
    
    // Page is set up, call any hooks
    $code = $plugins->setHook('page_set');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
   
    profiler_log('Paths and CMS core initted');
    $session->init_permissions();
  }
  
  function add_page($flags)
  {
    global $lang;
    $flags['urlname_nons'] = $flags['urlname'];
    $flags['urlname'] = $this->nslist[$flags['namespace']] . $flags['urlname']; // Applies the User:/File:/etc prefixes to the URL names
    
    if ( is_object($lang) )
    {
      if ( preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $flags['name']) )
        $flags['name'] = $lang->get($flags['name']);
    }
    
    $pages_len = sizeof($this->pages) / 2;
    $this->pages[$pages_len] = $flags;
    $this->pages[$flags['urlname']] =& $this->pages[$pages_len];
  }
  
  function main_page()
  {
    if( is_string(get_main_page()) )
    {
      $main_page = makeUrl(get_main_page());
    }
    else
    {
      $main_page = makeUrl($this->pages[0]['urlname']);
    }
    redirect($main_page, 'Redirecting...', 'Invalid request, redirecting to main page', 0);
    exit;
  }
  
  function sysmsg($n)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $q = $db->sql_query('SELECT page_text, char_tag FROM '.table_prefix.'page_text WHERE page_id=\''.$db->escape(sanitize_page_id($n)).'\' AND namespace=\'System\'');
    if( !$q )
    {
      $db->_die('Error during generic selection of system page data.');
    }
    if($db->numrows() < 1)
    {
      return false;
      //$db->_die('Error during generic selection of system page data: there were no rows in the text table that matched the page text query.');
    }
    $r = $db->fetchrow();
    $db->free_result();
    $message = $r['page_text'];
    
    $message = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '', $message);
    $message = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '\\1', $message);
    
    return $message;
  }
  function get_pageid_from_url()
  {
    $url = $this->parse_url();
    if ( substr($url, 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] ||
         substr($url, 0, strlen($this->nslist['Admin']))   == $this->nslist['Admin'])
    {
      list(, $ns) = RenderMan::strToPageID($url);
      $upart = substr($url, strlen($this->nslist[$ns]));
      list($upart) = explode('/', $upart);
      $url = $this->nslist[$ns] . $upart;
    }
    return $url;
  }
  // Parses a (very carefully formed) array into Javascript code compatible with the Tigra Tree Menu used in the admin menu
  function parseAdminTree() 
  {
    global $lang;
    
    $k = array_keys($this->admin_tree);
    $i = 0;
    $ret = '';
    $icon = $this->make_sprite_icon(4, 2);
    $icon = addslashes($icon);
    $ret .= "var TREE_ITEMS = [\n  ['$icon" . $lang->get('adm_btn_home') . "', 'javascript:ajaxPage(\'".$this->nslist['Admin']."Home\');',\n    ";
    foreach($k as $key)
    {
      $i++;
      $name = ( preg_match('/^[a-z0-9_]+$/', $key) ) ? $lang->get($key) : $key;
      $ret .= "['".$name."', 'javascript:trees[0].toggle($i)', \n";
      foreach($this->admin_tree[$key] as $c)
      {
        $i++;
        $name = ( preg_match('/^[a-z0-9_]+$/', $key) ) ? $lang->get($c['name']) : $c['name'];
        if ( $c['icon'] && $c['icon'] != cdnPath . '/images/spacer.gif' )
        {
          if ( is_array($c['icon']) )
          {
            // this is a sprite reference
            list($ix, $iy) = $c['icon'];
            $icon = $this->make_sprite_icon($ix, $iy);
          }
          else
          {
            $icon = "<img alt=\"\" src=\"{$c['icon']}\" style=\"border-width: 0; margin-right: 3px;\" /> ";
          }
        }
        else
        {
          $icon = '';
        }
        $icon = addslashes($icon);
        $ret .= "        ['$icon$name', 'javascript:ajaxPage(\\'".$this->nslist['Admin'].$c['pageid']."\\');'],\n";
      }
      $ret .= "      ],\n";
    }
    $icon = $this->make_sprite_icon(1, 1);
    $icon = addslashes($icon);
    $ret .= "    ['$icon" . $lang->get('adm_btn_logout') . "', 'javascript:ajaxPage(\\'".$this->nslist['Admin']."AdminLogout\\');'],\n";
    $ret .= "    ['<span id=\\'keepalivestat\\'>" . $lang->get('adm_btn_keepalive_loading') . "</span>', 'javascript:ajaxToggleKeepalive();', 
                   ['" . $lang->get('adm_btn_keepalive_about') . "', 'javascript:aboutKeepAlive();']
                 ],\n";
    // I used this while I painstakingly wrote the Runt code that auto-expands certain nodes based on the value of a bitfield stored in a cookie. *shudders*
    // $ret .= "    ['(debug) Clear menu bitfield', 'javascript:createCookie(\\'admin_menu_state\\', \\'1\\', 365);'],\n";
    $ret .= "]\n];";
    return $ret;
  }
  
  /**
   * Internal function to generate HTML code for an icon in the admin panel tree which is sprited.
   * @param int X index of icon
   * @param int Y index of icon
   * @return string
   */
  
  function make_sprite_icon($ix, $iy)
  {
    $xpos = 16 * ( $ix - 1 );
    $ypos = 16 * ( $iy - 1 );
    return "<img alt=\"\" src=\"" . cdnPath . "/images/spacer.gif\" class=\"adminiconsprite\" style=\"border-width: 0; margin-right: 3px; background-position: -{$xpos}px -{$ypos}px;\" /> ";
  }
  
  /**
   * Creates a new entry in the administration panel's navigation tree.
   * @param string Section name - if this is a language string identifier, it will be sent through $lang->get()
   * @param string The title of the page, also may be a language string identifier
   * @param string The page ID of the admin page, the namespace Admin is assumed
   * @param string Optional. The path to a 16x16 image that will be displayed as the icon for this admin page
   */
  
  function addAdminNode($section, $page_title, $url, $icon = false)
  {
    if ( !$icon )
    {
      $icon = cdnPath . '/images/spacer.gif';
    }
    if(!isset($this->admin_tree[$section]))
    {
      $this->admin_tree[$section] = Array();
    }
    $this->admin_tree[$section][] = Array(
        'name' => $page_title,
        'pageid' => $url,
        'icon' => $icon
      );
  }
  function getParam($id = 0)
  {
    $title = $this->parse_url(false);
    list(, $ns) = RenderMan::strToPageID($title);
    $title = substr($title, strlen($this->nslist[$ns]));
    $regex = '/^' . str_replace('/', '\\/', preg_quote($this->nslist[$this->namespace])) . '\\/?/';
    $title = preg_replace($regex, '', $title);
    $title = explode('/', $title);
    $id = $id + 1;
    return ( isset($title[$id]) ) ? $title[$id] : false;
  }
  
  function getAllParams()
  {
    $title = $this->parse_url(false);
    $regex = '/^' . str_replace('/', '\\/', preg_quote($this->nslist[$this->namespace])) . '\\/?/';
    $title = preg_replace($regex, '', $title);
    $title = explode('/', $title);
    unset($title[0]);
    return implode('/', $title);
  }
  
  /**
   * Creates a new namespace in memory
   * @param string $id the namespace ID
   * @param string $prefix the URL prefix, must not be blank or already used
   * @return bool true on success false on failure
   */
  
  function create_namespace($id, $prefix)
  {
    if(in_array($prefix, $this->nslist))
    {
      // echo '<b>Warning:</b> pathManager::create_namespace: Prefix "'.$prefix.'" is already taken<br />';
      return false;
    }
    if( isset($this->nslist[$id]) )
    {
      // echo '<b>Warning:</b> pathManager::create_namespace: Namespace ID "'.$prefix.'" is already taken<br />';
      return false;
    }
    $this->nslist[$id] = $prefix;
  }
  
  /**
   * Updates the cache containing all page metadata.
   */
  
  function update_metadata_cache()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( getConfig('cache_thumbs') != '1' )
      return false;
    
    $e = $db->sql_unbuffered_query('SELECT name,urlname,namespace,special,visible,comments_on,protected,delvotes,' . "\n"
                          . '  delvote_ips,wiki_mode,password FROM '.table_prefix.'pages ORDER BY name;');
    if ( !$e )
      $db->_die();
    
    $md_array = array();
    
    while ( $row = $db->fetchrow() )
    {
      $row = $this->calculate_metadata_from_row($row);
      $md_array[$row['urlname']] = $row;
    }
    
    // import cache functions
    global $cache;
    
    // store data (TTL 20 minutes)
    try
    {
      $cache->store('page_meta', $md_array, 20);
    }
    catch ( Exception $e )
    {
    }
    
    return true;
  }
  
  /**
   * Takes a result row from the pages table and calculates correct values for it.
   * @param array
   * @return array
   */
  
  function calculate_metadata_from_row($r)
  {
    $r['urlname_nons'] = $r['urlname'];
    if ( isset($this->nslist[$r['namespace']]) )
    {
      $r['urlname'] = $this->nslist[$r['namespace']] . $r['urlname']; // Applies the User:/File:/etc prefixes to the URL names
    }
    else
    {
      $ns_char = substr($this->nslist['Special'], -1);
      $r['urlname'] = $r['namespace'] . $ns_char . $r['urlname'];
    }
    
    if ( $r['delvotes'] == null)
    {
      $r['delvotes'] = 0;
    }
    if ( $r['protected'] == 0 || $r['protected'] == 1 )
    {
      $r['really_protected'] = (int)$r['protected'];
    }
    else if ( $r['protected'] == 2 && getConfig('wiki_mode') == '1')
    {
      $r['really_protected'] = 1;
    }
    else if ( $r['protected'] == 2 && getConfig('wiki_mode') == '0' )
    {
      $r['really_protected'] = 0;
    }
    return $r;
  }
  
  /**
   * Registers a handler to manually process a namespace instead of the default PageProcessor behavior.
   * The first and only parameter passed to the processing function will be the PageProcessor instance.
   * @param string Namespace to process
   * @param mixed Function address. Either a function name or an array of the form array(0 => mixed (string:class name or object), 1 => string:method)
   */
  
  function register_namespace_processor($namespace, $function)
  {
    if ( isset($this->namespace_processors[$namespace]) )
    {
      $processorname = ( is_string($this->namespace_processors[$namespace]) ) ?
        $this->namespace_processors[$namespace] :
        ( is_object($this->namespace_processors[$namespace][0]) ? get_class($this->namespace_processors[$namespace][0]) : $this->namespace_processors[$namespace][0] ) . '::' .
          $this->namespace_processors[$namespace][1];
          
      trigger_error("Namespace \"$namespace\" is already being processed by $processorname - replacing caller", E_USER_WARNING);
    }
    if ( !is_string($function) )
    {
      if ( !is_array($function) )
        return false;
      if ( count($function) != 2 )
        return false;
      if ( !is_string($function[0]) && !is_object($function[0]) )
        return false;
      if ( !is_string($function[1]) )
        return false;
    }
    
    // security: don't allow Special or Admin namespaces to be overridden
    if ( $namespace == 'Special' || $namespace == 'Admin' )
    {
      trigger_error("Security manager denied attempt to override processor for $namespace", E_USER_ERROR);
    }
    
    $this->namespace_processors[$namespace] = $function;
  }
  
  /**
   * Returns a namespace processor if one exists, otherwise returns false.
   * @param string Namespace
   * @return mixed
   */
  
  function get_namespace_processor($namespace)
  {
    return ( isset($this->namespace_processors[$namespace]) ) ? $this->namespace_processors[$namespace] : false;
  }
  
  /**
   * Fetches the page texts for searching
   */
   
  function fetch_page_search_texts()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $texts = Array();
    $q = $db->sql_query('SELECT t.page_id,t.namespace,t.page_text,t.char_tag FROM '.table_prefix.'page_text AS t
                           LEFT JOIN '.table_prefix.'pages AS p
                             ON t.page_id=p.urlname
                           WHERE p.namespace=t.namespace
                             AND ( p.password=\'\' OR p.password=\'da39a3ee5e6b4b0d3255bfef95601890afd80709\' )
                             AND p.visible=1;'); // Only indexes "visible" pages
    
    if( !$q )
    {
      return false;
    }
    while($row = $db->fetchrow())
    {
      $pid = $this->nslist[$row['namespace']] . $row['page_id'];
      $texts[$pid] = $row['page_text'];
    }
    $db->free_result();
    
    return $texts;
  }
  
  /**
   * Generates an SQL query to grab all of the text
   */
   
  function fetch_page_search_resource()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // sha1('') returns "da39a3ee5e6b4b0d3255bfef95601890afd80709"
    
    $concat_column = ( ENANO_DBLAYER == 'MYSQL' ) ?
      'CONCAT(\'ns=\',t.namespace,\';pid=\',t.page_id)' :
      "'ns=' || t.namespace || ';pid=' || t.page_id";
    
    $texts = 'SELECT t.page_text, ' . $concat_column . ' AS page_idstring, t.page_id, t.namespace FROM '.table_prefix.'page_text AS t
                           LEFT JOIN '.table_prefix.'pages AS p
                             ON ( t.page_id=p.urlname AND t.namespace=p.namespace )
                           WHERE p.namespace=t.namespace
                             AND ( p.password=\'\' OR p.password=\'da39a3ee5e6b4b0d3255bfef95601890afd80709\' )
                             AND p.visible=1;'; // Only indexes "visible" pages
    return $texts;
  }
  
  /**
   * Builds a word list for search indexing.
   * @param string Text to index
   * @param string Page ID of the page being indexed
   * @param string Title of the page being indexed
   * @return array List of words
   */
  
  function calculate_word_list($text, $page_id, $page_name)
  {
    $page_id = dirtify_page_id($page_id);
    $text = preg_replace('/[^a-z0-9\']/i', ' ', $text);
    $page_id = preg_replace('/[^a-z0-9\']/i', ' ', $page_id);
    $page_name = preg_replace('/[^a-z0-9\']/i', ' ', $page_name);
    $text .= " $page_id $page_name";
    $text = explode(' ', $text);
    foreach ( $text as $i => &$word )
    {
      if ( strstr($word, "''") )
        $word = preg_replace("/[']{2,}/", '', $word);
      if ( strlen($word) < 2 )
        unset($text[$i]);
    }
    $text = array_unique(array_values($text));
    // for debugging purposes (usually XSS safe because of character stripping)
    // echo ' ' . implode(' ', $text) . '<br />';
    return $text;
  }
  
  /**
   * Rebuilds the site's entire search index. Considerably more exciting if run from the command line.
   * @param bool If true, verbose output.
   * @param bool If true, verbose + debugging output.
   */
  
  function rebuild_search_index($verbose = false, $debug = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    require_once(ENANO_ROOT . '/includes/search.php');
    
    $progress = false;
    if ( class_exists('ProgressBar') )
    {
      // CLI only.
      $progress = new ProgressBar('Rebuilding search index: [', ']', 'Initializing...', 'green', 'blue', 'white', 'yellow');
      $verbose = false;
      $debug = false;
      $progress->start();
    }
    
    @set_time_limit(0);
    
    $q = $db->sql_query('DELETE FROM ' . table_prefix . 'search_index;');
    if ( !$q )
      $db->_die();
    
    $sha1_blank = sha1('');
    $query_func = ( ENANO_DBLAYER == 'MYSQL' ) ? 'mysql_query' : 'pg_query';
    
    //
    // Index $pages_in_batch pages at a time
    //
    $pages_in_batch = 15;
    
    // First find out how many pages there are
    $q = $db->sql_query('SELECT COUNT(p.urlname) AS num_pages FROM ' . table_prefix . "page_text AS t\n"
                      . "  LEFT JOIN " . table_prefix . "pages AS p\n"
                      . "    ON ( p.urlname = t.page_id AND p.namespace = t.namespace )\n"
                      . "  WHERE ( p.password = '' OR p.password = '$sha1_blank' )\n"
                      . "    AND ( p.visible = 1 );");
    if ( !$q )
      $db->_die();
    
    list($num_pages) = $db->fetchrow_num();
    $num_pages = intval($num_pages);
    $loops = ceil($num_pages / $pages_in_batch);
    $master_word_list = array();
    $stopwords = get_stopwords();
    
    for ( $j = 0; $j < $loops; )
    {
      $offset = $j * $pages_in_batch;
      
      $j++;
      
      if ( $verbose && $debug )
      {
        echo "Running indexing round $j of $loops (offset $offset)\n" . ( isset($_SERVER['REQUEST_URI']) ? '<br />' : '' );
      }
      
      // this is friendly to both MySQL and PostgreSQL.
      $texts = $db->sql_query('SELECT p.name, p.visible, t.page_id, t.namespace, t.page_text FROM ' . table_prefix . "page_text AS t\n"
                            . "  LEFT JOIN " . table_prefix . "pages AS p\n"
                            . "    ON ( p.urlname = t.page_id AND p.namespace = t.namespace )\n"
                            . "  WHERE ( p.password = '' OR p.password = '$sha1_blank' )\n"
                            . "    AND ( p.visible = 1 )\n"
                            . "  LIMIT $pages_in_batch OFFSET $offset;", false);
      if ( !$texts )
        $db->_die();
      
      $k = $offset;
      
      if ( $row = $db->fetchrow($texts) )
      {
        do
        {
          $k++;
          if ( $verbose )
          {
            $mu = memory_get_usage();
            echo "  Indexing page $k of $num_pages: {$row['namespace']}:{$row['page_id']}";
            if ( $debug )
              echo ", mem = $mu...";
            flush();
          }
          else if ( is_object($progress) )
          {
            $progress->update_text_quiet("$k/$num_pages {$row['namespace']}:{$row['page_id']}");
            $progress->set($k, $num_pages);
          }
          
          // skip this page if it's not supposed to be indexed
          if ( $row['visible'] == 0 )
          {
            if ( $verbose )
            {
              echo "skipped";
              if ( isset($_SERVER['REQUEST_URI']) )
                echo '<br />';
              echo "\n";
            }
            continue;
          }
          
          // Indexing identifier for the page in the DB
          $page_uniqid = "ns={$row['namespace']};pid=" . sanitize_page_id($row['page_id']);
          $page_uniqid = $db->escape($page_uniqid);
          
          // List of words on the page
          $wordlist = $this->calculate_word_list($row['page_text'], $row['page_id'], $row['name']);
          
          // Index calculation complete -- run inserts
          $inserts = array();
          foreach ( $wordlist as $word )
          {
            if ( in_array($word, $stopwords) || strval(intval($word)) === $word || strlen($word) < 3 )
              continue;
            $word_db = $db->escape($word);
            $word_db_lc = $db->escape(strtolower($word));
            if ( !in_array($word, $master_word_list) )
            {
              $inserts[] = "( '$word_db', '$word_db_lc', '$page_uniqid' )";
            }
            else
            {
              if ( $verbose && $debug )
                echo '.';
              $pid_col = ( ENANO_DBLAYER == 'MYSQL' ) ?
                          "CONCAT( page_names, ',$page_uniqid' )":
                          "page_names || ',$page_uniqid'";
              $q = $db->sql_query('UPDATE ' . table_prefix . "search_index SET page_names = $pid_col WHERE word = '$word_db';", false);
              if ( !$q )
                $db->_die();
            }
          }
          if ( count($inserts) > 0 )
          {
            if ( $verbose && $debug )
              echo 'i';
            $inserts = implode(",\n  ", $inserts);
            $q = $db->sql_query('INSERT INTO ' . table_prefix . "search_index(word, word_lcase, page_names) VALUES\n  $inserts;", false);
            if ( !$q )
              $db->_die();
          }
          
          $master_word_list = array_unique(array_merge($master_word_list, $wordlist));
          if ( $verbose )
          {
            if ( isset($_SERVER['REQUEST_URI']) )
              echo '<br />';
            echo "\n";
          }
          unset($inserts, $wordlist, $page_uniqid, $word_db, $q, $word, $row);
        }
        while ( $row = $db->fetchrow($texts) );
      }
      $db->free_result($texts);
    }
    if ( $verbose )
    {
      echo "Indexing complete.";
      if ( isset($_SERVER['REQUEST_URI']) )
        echo '<br />';
      echo "\n";
    }
    else if ( is_object($progress) )
    {
      $progress->update_text('Complete.');
      $progress->end();
    }
    return true;
  }
  
  /**
   * Partially rebuilds the search index, removing/inserting entries only for the current page
   * @param string $page_id
   * @param string $namespace
   */
  
  function rebuild_page_index($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    require_once(ENANO_ROOT . '/includes/search.php');
    
    if(!$db->sql_query('SELECT page_text FROM '.table_prefix.'page_text
      WHERE page_id=\''.$db->escape($page_id).'\' AND namespace=\''.$db->escape($namespace).'\';'))
    {
      return $db->get_error();
    }
    if ( $db->numrows() < 1 )
      return 'E: No rows';
    $idstring = $this->nslist[$namespace] . sanitize_page_id($page_id);
    if ( !isset($this->pages[$idstring]) )
    {
      return 'E: Can\'t find page metadata';
    }
    $row = $db->fetchrow();
    $db->free_result();
    $search = new Searcher();
    
    // if the page shouldn't be indexed, send a blank set of strings to the indexing engine
    if ( $this->pages[$idstring]['visible'] == 0 )
    {
      $search->buildIndex(Array("ns={$namespace};pid={$page_id}"=>''));
    }
    else
    {
      $search->buildIndex(Array("ns={$namespace};pid={$page_id}"=>$row['page_text'] . ' ' . $this->pages[$idstring]['name']));
    }
    
    $new_index = $search->index;
    
    if ( ENANO_DBLAYER == 'MYSQL' )
    {
      $keys = array_keys($search->index);
      foreach($keys as $i => $k)
      {
        $c =& $keys[$i];
        $c = hexencode($c, '', '');
      }
      $keys = "word=0x" . implode ( " OR word=0x", $keys ) . "";
    }
    else
    {
      $keys = array_keys($search->index);
      foreach($keys as $i => $k)
      {
        $c =& $keys[$i];
        $c = $db->escape($c);
      }
      $keys = "word='" . implode ( "' OR word='", $keys ) . "'";
    }
    
    $query = $db->sql_query('SELECT word,page_names FROM '.table_prefix.'search_index WHERE '.$keys.';');
    
    while($row = $db->fetchrow())
    {
      $row['word'] = rtrim($row['word'], "\0");
      $new_index[ $row['word'] ] = $row['page_names'] . ',' . $search->index[ $row['word'] ];
    }
    $db->free_result();
    
    $db->sql_query('DELETE FROM '.table_prefix.'search_index WHERE '.$keys.';');
    
    $secs = Array();
    $q = 'INSERT INTO '.table_prefix.'search_index(word,word_lcase,page_names) VALUES';
    foreach($new_index as $word => $pages)
    {
      $secs[] = '(\''.$db->escape($word).'\', \'' . $db->escape(strtolower($word)) . '\', \''.$db->escape($pages).'\')';
    }
    $q .= implode(',', $secs);
    unset($secs);
    $q .= ';';
    if(!$db->check_query($q))
    {
      die('BUG: PathManager::rebuild_page_index: Query rejected by SQL parser:<pre>'.$q.'</pre>');
    }
    $result = $db->sql_query($q);
    if($result)
      return true;
    else
      $db->_die('The search index was trying to rebuild itself when the error occured.');
    
  }
  
  /**
   * Creates an instance of the Searcher class, including index info
   * @return object
   */
   
  function makeSearcher($match_case = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    $q = $db->sql_query('SELECT word,page_names FROM '.table_prefix.'search_index;');
    if(!$q)
    {
      echo $db->get_error();
      return false;
    }
    $idx = Array();
    while($row = $db->fetchrow($q))
    {
      $row['word'] = rtrim($row['word'], "\0");
      $idx[$row['word']] = $row['page_names'];
    }
    $db->free_result();
    $search->index = $idx;
    if($match_case)
      $search->match_case = true;
    return $search;
  }
  
  /**
   * Creates an associative array filled with the values of all the page titles
   * @return array
   */
   
  function get_page_titles()
  {
    $texts = Array();
    for ( $i = 0; $i < sizeof($this->pages) / 2; $i++ )
    {
      $texts[$this->pages[$i]['urlname']] = $this->pages[$i]['name'];
    }
    return $texts;
  }
  
  /**
   * Creates an instance of the Searcher class, including index info for page titles
   * @return object
   */
   
  function makeTitleSearcher($match_case = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    $texts = $this->get_page_titles();
    $search->buildIndex($texts);
    if($match_case)
      $search->match_case = true;
    return $search;
  }
  
  /**
   * Returns a list of groups that a given page is a member of.
   * @param string Page ID
   * @param string Namespace
   * @return array
   */
  
  function get_page_groups($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    static $cache = array();
    
    if ( count($cache) == 0 )
    {
      foreach ( $this->nslist as $key => $_ )
      {
        $cache[$key] = array();
      }
    }
    
    if ( !isset($this->nslist[$namespace]) )
      die('$paths->get_page_groups(): HACKING ATTEMPT: namespace "'. htmlspecialchars($namespace) .'" doesn\'t exist');
    
    $page_id_unescaped = $paths->nslist[$namespace] .
                         dirtify_page_id($page_id);
    $page_id_str       = $paths->nslist[$namespace] .
                         sanitize_page_id($page_id);
    
    $page_id = $db->escape(sanitize_page_id($page_id));
    
    if ( isset($cache[$namespace][$page_id]) )
    {
      return $cache[$namespace][$page_id];
    }
    
    $group_list = array();
    
    // What linked categories have this page?
    $q = $db->sql_unbuffered_query('SELECT g.pg_id, g.pg_type, g.pg_target FROM '.table_prefix.'page_groups AS g
  LEFT JOIN '.table_prefix.'categories AS c
    ON ( ( c.category_id = g.pg_target AND g.pg_type = ' . PAGE_GRP_CATLINK . ' ) OR c.category_id IS NULL )
  LEFT JOIN '.table_prefix.'page_group_members AS m
    ON ( ( g.pg_id = m.pg_id AND g.pg_type = ' . PAGE_GRP_NORMAL . ' ) OR ( m.pg_id IS NULL ) )
  LEFT JOIN '.table_prefix.'tags AS t
    ON ( ( t.tag_name = g.pg_target AND pg_type = ' . PAGE_GRP_TAGGED . ' ) OR t.tag_name IS NULL )
  WHERE
    ( c.page_id=\'' . $page_id . '\' AND c.namespace=\'' . $namespace . '\' ) OR
    ( t.page_id=\'' . $page_id . '\' AND t.namespace=\'' . $namespace . '\' ) OR
    ( m.page_id=\'' . $page_id . '\' AND m.namespace=\'' . $namespace . '\' ) OR
    ( g.pg_type = ' . PAGE_GRP_REGEX . ' );');
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      if ( $row['pg_type'] == PAGE_GRP_REGEX )
      {
        //echo "&lt;debug&gt; matching page " . htmlspecialchars($page_id_unescaped) . " against regex <tt>" . htmlspecialchars($row['pg_target']) . "</tt>.";
        if ( @preg_match($row['pg_target'], $page_id_unescaped) || @preg_match($row['pg_target'], $page_id_str) )
        {
          //echo "..matched";
          $group_list[] = $row['pg_id'];
        }
        //echo "<br />";
      }
      else
      {
        $group_list[] = $row['pg_id'];
      }
    }
    
    $db->free_result();
    
    $cache[$namespace][$page_id] = $group_list;
    
    return $group_list;
    
  }
  
}

?>

<?php

/**
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.2 (Coblynau)
 * Copyright (C) 2006-2007 Dan Fuhry
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
 
class pathManager {
  var $pages, $custom_page, $cpage, $page, $fullpage, $page_exists, $namespace, $nslist, $admin_tree, $wiki_mode, $page_protected, $template_cache;
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $GLOBALS['paths'] =& $this;
    $this->pages = Array();
    
    dc_here('paths: setting up namespaces, admin nodes');
    
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
      'Project' =>sanitize_page_id(getConfig('site_name')).':',
      );
    
    // ACL types
    // These can also be added from within plugins
    
    $session->register_acl_type('read',                   AUTH_ALLOW,    'Read page(s)');
    $session->register_acl_type('post_comments',          AUTH_ALLOW,    'Post comments',                                                                                            Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_comments',          AUTH_ALLOW,    'Edit own comments',                                                                                        Array('post_comments'),                                   'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_page',              AUTH_WIKIMODE, 'Edit page',                                                                                                Array('view_source'),                                     'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('view_source',            AUTH_WIKIMODE, 'View source',                                                                                              Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category'); // Only used if the page is protected
    $session->register_acl_type('mod_comments',           AUTH_DISALLOW, 'Moderate comments',                                                                                        Array('edit_comments'),                                   'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_view',           AUTH_WIKIMODE, 'View history/diffs',                                                                                       Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_rollback',       AUTH_DISALLOW, 'Rollback history',                                                                                         Array('history_view'),                                    'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_rollback_extra', AUTH_DISALLOW, 'Undelete page(s)',                                                                                         Array('history_rollback'),                                'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('protect',                AUTH_DISALLOW, 'Protect page(s)',                                                                                          Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('rename',                 AUTH_WIKIMODE, 'Rename page(s)',                                                                                           Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('clear_logs',             AUTH_DISALLOW, 'Clear page logs (dangerous)',                                                                              Array('read', 'protect', 'even_when_protected'),          'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('vote_delete',            AUTH_ALLOW,    'Vote to delete',                                                                                           Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('vote_reset',             AUTH_DISALLOW, 'Reset delete votes',                                                                                       Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('delete_page',            AUTH_DISALLOW, 'Delete page(s)',                                                                                           Array(),                                                  'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_create',             AUTH_ALLOW,    'Tag page(s)',                                                                                              Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_delete_own',         AUTH_ALLOW,    'Remove own page tags',                                                                                     Array('read', 'tag_create'),                              'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_delete_other',       AUTH_DISALLOW, 'Remove others\' page tags',                                                                                Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('set_wiki_mode',          AUTH_DISALLOW, 'Set per-page wiki mode',                                                                                   Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('password_set',           AUTH_DISALLOW, 'Set password',                                                                                             Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('password_reset',         AUTH_DISALLOW, 'Disable/reset password',                                                                                   Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('mod_misc',               AUTH_DISALLOW, 'Super moderator (generate SQL backtraces, view IP addresses, and send large numbers of private messages)', Array(),                                                  'All');
    $session->register_acl_type('edit_cat',               AUTH_WIKIMODE, 'Edit categorization',                                                                                      Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('even_when_protected',    AUTH_DISALLOW, 'Allow editing, renaming, and categorization even when protected',                                          Array('edit_page', 'rename', 'mod_comments', 'edit_cat'), 'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('upload_files',           AUTH_DISALLOW, 'Upload files',                                                                                             Array('create_page'),                                     'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('upload_new_version',     AUTH_WIKIMODE, 'Upload new versions of files',                                                                             Array('upload_files'),                                    'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('create_page',            AUTH_WIKIMODE, 'Create pages',                                                                                             Array(),                                                  'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('php_in_pages',           AUTH_DISALLOW, 'Embed PHP code in pages',                                                                                  Array('edit_page'),                                       'Article|User|Project|Template|File|Help|System|Category|Admin');
    $session->register_acl_type('edit_acl',               AUTH_DISALLOW, 'Edit access control lists', Array('read', 'post_comments', 'edit_comments', 'edit_page', 'view_source', 'mod_comments', 'history_view', 'history_rollback', 'history_rollback_extra', 'protect', 'rename', 'clear_logs', 'vote_delete', 'vote_reset', 'delete_page', 'set_wiki_mode', 'password_set', 'password_reset', 'mod_misc', 'edit_cat', 'even_when_protected', 'upload_files', 'upload_new_version', 'create_page', 'php_in_pages'));
    
    // DO NOT add new admin pages here! Use a plugin to call $paths->addAdminNode();
    $this->addAdminNode('General', 'General Configuration', 'GeneralConfig');
    $this->addAdminNode('General', 'File uploads', 'UploadConfig');
    $this->addAdminNode('General', 'Allowed file types', 'UploadAllowedMimeTypes');
    $this->addAdminNode('General', 'Manage Plugins', 'PluginManager');
    $this->addAdminNode('General', 'Backup database', 'DBBackup');
    $this->addAdminNode('Content', 'Manage Pages', 'PageManager');
    $this->addAdminNode('Content', 'Edit page content', 'PageEditor');
    $this->addAdminNode('Content', 'Manage page groups', 'PageGroups');
    $this->addAdminNode('Appearance', 'Manage themes', 'ThemeManager');
    $this->addAdminNode('Users', 'Manage users', 'UserManager');
    $this->addAdminNode('Users', 'Edit groups', 'GroupManager');
    $this->addAdminNode('Users', 'COPPA support', 'COPPA');
    $this->addAdminNode('Users', 'Mass e-mail', 'MassEmail');
    $this->addAdminNode('Security', 'Security log', 'SecurityLog');
    $this->addAdminNode('Security', 'Ban control', 'BanControl');
    
    $code = $plugins->setHook('acl_rule_init');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $this->wiki_mode = (int)getConfig('wiki_mode')=='1';
    $this->template_cache = Array();
  }
  function pathManager()
  {
    $this->__construct();
  }
  function init()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    dc_here('paths: selecting master page data');
    
    $code = $plugins->setHook('paths_init_before');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $e = $db->sql_query('SELECT name,urlname,namespace,special,visible,comments_on,protected,delvotes,delvote_ips,wiki_mode,password FROM '.table_prefix.'pages ORDER BY name;');
    if( !$e )
    {
      $db->_die('The error seems to have occured while selecting the page information. File: includes/paths.php; line: '.__LINE__);
    }
    while($r = $db->fetchrow())
    {
      
      $r['urlname_nons'] = $r['urlname'];
      $r['urlname'] = $this->nslist[$r['namespace']] . $r['urlname']; // Applies the User:/File:/etc prefixes to the URL names
      
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
      
      $this->pages[$r['urlname']] = $r;
      $this->pages[] =& $this->pages[$r['urlname']];
      
    }
    $db->free_result();
    dc_here('paths: determining page ID');
    if( isset($_GET['title']) )
    {
      if ( $_GET['title'] == '' && getConfig('main_page') != '' )
      {
        $this->main_page();
      }
      if(strstr($_GET['title'], ' '))
      {
        $loc = urldecode(rawurldecode($_SERVER['REQUEST_URI']));
        $loc = str_replace(' ', '_', $loc);
        $loc = str_replace('+', '_', $loc);
        $loc = str_replace('%20', '_', $loc);
        redirect($loc, 'Redirecting...', 'Space detected in the URL, please wait whilst you are redirected', 0);
        exit;
      }
      $url_namespace_special = substr($_GET['title'], 0, strlen($this->nslist['Special']) );
      $url_namespace_template = substr($_GET['title'], 0, strlen($this->nslist['Template']) );
      if($url_namespace_special == $this->nslist['Special'] || $url_namespace_template == $this->nslist['Template'] )
      {
        $ex = explode('/', $_GET['title']);
        $this->page = $ex[0];
      }
      else
      {
        $this->page = $_GET['title'];
      }
      $this->fullpage = $_GET['title'];
    }
    elseif( isset($_SERVER['PATH_INFO']) )
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      
      if( !isset($pi[1]) || (isset($pi[1]) && $pi[1] == '' && getConfig('main_page') != '') )
      {
        $this->main_page();
      }
      if( strstr($pi[1], ' ') )
      {
        $loc = str_replace(' ', '_', urldecode(rawurldecode($_SERVER['REQUEST_URI'])));
        $loc = str_replace('+', '_', $loc);
        $loc = str_replace('%20', '_', $loc);
        redirect($loc, 'Redirecting...', 'Please wait whilst you are redirected', 3);
        exit;
      }
      unset($pi[0]);
      if( substr($pi[1], 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] || substr($pi[1], 0, strlen($this->nslist['Template'])) == $this->nslist['Template'] )
      {
        $pi2 = $pi[1];
      }
      else
      {
        $pi2 = implode('/', $pi);
      }
      $this->page = $pi2;
      $this->fullpage = implode('/', $pi);
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          $this->page = substr($c, 1, strlen($c));
          
          // Bugfix for apache somehow passing dots as underscores
          global $mime_types;
          
          $exts = array_keys($mime_types);
          $exts = '(' . implode('|', $exts) . ')';
          
          if ( preg_match( '#_'.$exts.'#i', $this->page ) )
          {
            $this->page = preg_replace( '#_'.$exts.'#i', '.\\1', $this->page );
          }
          
          $this->fullpage = $this->page;
          
          if(substr($this->page, 0, strlen($this->nslist['Special']))==$this->nslist['Special'] || substr($this->page, 0, strlen($this->nslist['Template']))==$this->nslist['Template'])
          {
            $ex = explode('/', $this->page);
            $this->page = $ex[0];
          }
          if(strstr($this->page, ' '))
          {
            $loc = str_replace(' ', '_', urldecode(rawurldecode($_SERVER['REQUEST_URI'])));
            $loc = str_replace('+', '_', $loc);
            $loc = str_replace('%20', '_', $loc);
            redirect($loc, 'Redirecting...', 'Space in the URL detected, please wait whilst you are redirected', 0);
            exit;
          }
          break;
        }
      }
      if(!$this->page && !($this->page == '' && getConfig('main_page') == ''))
      {
        $this->main_page();
      }
    }
    
    $this->page = sanitize_page_id($this->page);
    $this->fullpage = sanitize_page_id($this->fullpage);
    
    dc_here('paths: setting $paths->cpage');
    
    if(isset($this->pages[$this->page]))
    {
      dc_here('paths: page existence verified, our page ID is: '.$this->page);
      $this->page_exists = true;
      $this->cpage = $this->pages[$this->page];
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
      dc_here('paths: page doesn\'t exist, creating new page in memory<br />our page ID is: '.$this->page);
      $this->page_exists = false;
      $page_name = dirtify_page_id($this->page);
      $page_name = str_replace('_', ' ', $page_name);
      
      $pid_cleaned = sanitize_page_id($this->page);
      if ( $pid_cleaned != $this->page )
      {
        redirect($pid_cleaned, 'Sanitizer message', 'page id sanitized', 0);
      }
      
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
      
      if($this->namespace=='System') 
      {
        $this->cpage['protected'] = 1;
      }
      if($this->namespace == 'Special')
      {
        // Can't load nonexistent pages
        if( is_string(getConfig('main_page')) )
        {
          $main_page = makeUrl(getConfig('main_page'));
        }
        else
        {
          $main_page = makeUrl($this->pages[0]['urlname']);
        }
        $sp_link = '<a href="' . makeUrlNS('Special', 'SpecialPages') . '">here</a>';
        redirect($main_page, 'Can\'t load special page', 'The special page you requested could not be found. This may be due to a plugin failing to load. A list of all special pages on this website can be viewed '.$sp_link.'. You will be redirected to the main page in 15 seconds.', 14);
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
    
    $session->init_permissions();
  }
  
  function add_page($flags)
  {
    //dc_dump($flags, 'paths: page added by plugin:');
    $flags['urlname_nons'] = $flags['urlname'];
    $flags['urlname'] = $this->nslist[$flags['namespace']] . $flags['urlname']; // Applies the User:/File:/etc prefixes to the URL names
    $pages_len = sizeof($this->pages)/2;
    $this->pages[$pages_len] = $flags;
    $this->pages[$flags['urlname']] =& $this->pages[$pages_len];
  }
  
  function main_page()
  {
    if( is_string(getConfig('main_page')) )
    {
      $main_page = makeUrl(getConfig('main_page'));
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
    dc_here('paths: system message requested: '.$n);
    $q = $db->sql_query('SELECT page_text, char_tag FROM '.table_prefix.'page_text WHERE page_id=\''.$db->escape($n).'\' AND namespace=\'System\'');
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
    
    return $message;
  }
  function get_pageid_from_url()
  {
    if(isset($_GET['title']))
    {
      if( $_GET['title'] == '' && getConfig('main_page') != '' )
      {
        $this->main_page();
      }
      if(strstr($_GET['title'], ' '))
      {
        $loc = urldecode(rawurldecode($_SERVER['REQUEST_URI']));
        $loc = str_replace(' ', '_', $loc);
        $loc = str_replace('+', '_', $loc);
        header('Location: '.$loc);
        exit;
      }
      $ret = $_GET['title'];
    }
    elseif(isset($_SERVER['PATH_INFO']))
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      
      if(!isset($pi[1]) || (isset($pi[1]) && $pi[1] == ''))
      {
        return false;
      }
      
      if(strstr($pi[1], ' '))
      {
        $loc = urldecode(rawurldecode($_SERVER['REQUEST_URI']));
        $loc = str_replace(' ', '_', $loc);
        $loc = str_replace('+', '_', $loc);
        header('Location: '.$loc);
        exit;
      }
      if( !( substr($pi[1], 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] ) )
      {
        unset($pi[0]);
        $pi[1] = implode('/', $pi);
      }
      $ret = $pi[1];
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          $ret = substr($c, 1, strlen($c));
          if(substr($ret, 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] ||
             substr($ret, 0, strlen($this->nslist['Admin'])) == $this->nslist['Admin'])
          {
            $ret = explode('/', $ret);
            $ret = $ret[0];
          }
          break;
        }
      }
    }
    
    return ( isset($ret) ) ? $ret : false;
  }
  // Parses a (very carefully formed) array into Javascript code compatible with the Tigra Tree Menu used in the admin menu
  function parseAdminTree() 
  {
    $k = array_keys($this->admin_tree);
    $i = 0;
    $ret = '';
    $ret .= "var TREE_ITEMS = [\n  ['Administration panel home', 'javascript:ajaxPage(\'".$this->nslist['Admin']."Home\');',\n    ";
    foreach($k as $key)
    {
      $i++;
      $ret .= "['".$key."', 'javascript:trees[0].toggle($i)', \n";
      foreach($this->admin_tree[$key] as $c)
      {
        $i++;
        $ret .= "        ['".$c['name']."', 'javascript:ajaxPage(\\'".$this->nslist['Admin'].$c['pageid']."\\');'],\n";
      }
      $ret .= "      ],\n";
    }
    $ret .= "    ['Log out of admin panel', 'javascript:ajaxPage(\\'".$this->nslist['Admin']."AdminLogout\\');'],\n";
    $ret .= "    ['<span id=\\'keepalivestat\\'>Loading keep-alive control...</span>', 'javascript:ajaxToggleKeepalive();', 
                   ['About keep-alive', 'javascript:aboutKeepAlive();']
                 ],\n";
    // I used this while I painstakingly wrote the Runt code that auto-expands certain nodes based on the value of a bitfield stored in a cookie. *shudders*
    // $ret .= "    ['(debug) Clear menu bitfield', 'javascript:createCookie(\\'admin_menu_state\\', \\'1\\', 365);'],\n";
    $ret .= "]\n];";
    return $ret;
  }
  function addAdminNode($section, $page_title, $url)
  {
    if(!isset($this->admin_tree[$section]))
    {
      $this->admin_tree[$section] = Array();
    }
    $this->admin_tree[$section][] = Array(
        'name'=>$page_title,
        'pageid'=>$url
      );
  }
  function getParam($id = 0)
  {
    // using !empty here is a bugfix for IIS 5.x on Windows 2000 Server
    // It may affect other IIS versions as well
    if(isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']))
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      $id = $id + 2;
      return isset($pi[$id]) ? $pi[$id] : false;
    }
    else if( isset($_GET['title']) )
    {
      $pi = explode('/', $_GET['title']);
      $id = $id + 1;
      return isset($pi[$id]) ? $pi[$id] : false;
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          // Bugfix for apache somehow passing dots as underscores
          global $mime_types;
          $exts = array_keys($mime_types);
          $exts = '(' . implode('|', $exts) . ')';
          if ( preg_match( '#_'.$exts.'#i', $c ) )
            $c = preg_replace( '#_'.$exts.'#i', '.\\1', $c );
          
          $pi = explode('/', $c);
          $id = $id + 2;
          return isset($pi[$id]) ? $pi[$id] : false;
        }
      }
      return false;
    }
  }
  
  function getAllParams()
  {
    // using !empty here is a bugfix for IIS 5.x on Windows 2000 Server
    // It may affect other IIS versions as well
    if(isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']))
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      unset($pi[0], $pi[1]);
      return implode('/', $pi);
    }
    else if( isset($_GET['title']) )
    {
      $pi = explode('/', $_GET['title']);
      unset($pi[0]);
      return implode('/', $pi);
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          // Bugfix for apache somehow passing dots as underscores
          global $mime_types;
          $exts = array_keys($mime_types);
          $exts = '(' . implode('|', $exts) . ')';
          if ( preg_match( '#_'.$exts.'#i', $c ) )
            $c = preg_replace( '#_'.$exts.'#i', '.\\1', $c );
          
          $pi = explode('/', $c);
          unset($pi[0], $pi[1]);
          return implode('/', $pi);
        }
      }
      return false;
    }
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
   * Fetches a MySQL search query to use for Searcher::searchMySQL()
   */
   
  function fetch_page_search_resource()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // sha1('') returns "da39a3ee5e6b4b0d3255bfef95601890afd80709"
    $texts = 'SELECT t.page_text,CONCAT(\'ns=\',t.namespace,\';pid=\',t.page_id) FROM '.table_prefix.'page_text AS t
                           LEFT JOIN '.table_prefix.'pages AS p
                             ON ( t.page_id=p.urlname AND t.namespace=p.namespace )
                           WHERE p.namespace=t.namespace
                             AND ( p.password=\'\' OR p.password=\'da39a3ee5e6b4b0d3255bfef95601890afd80709\' )
                             AND p.visible=1;'; // Only indexes "visible" pages
    return $texts;
  }
  
  /**
   * Rebuilds the search index
   */
   
  function rebuild_search_index()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    $texts = Array();
    $textq = $db->sql_unbuffered_query($this->fetch_page_search_resource());
    if(!$textq) $db->_die('');
    while($row = $db->fetchrow_num())
    {
      $texts[(string)$row[1]] = $row[0];
    }
    $search->buildIndex($texts);
    // echo '<pre>'.print_r($search->index, true).'</pre>';
    // return;
    $q = $db->sql_query('DELETE FROM '.table_prefix.'search_index');
    if(!$q) return false;
    $secs = Array();
    $q = 'INSERT INTO '.table_prefix.'search_index(word,page_names) VALUES';
    foreach($search->index as $word => $pages)
    {
      $secs[] = '(\''.$db->escape($word).'\', \''.$db->escape($pages).'\')';
    }
    $q .= implode(',', $secs);
    unset($secs);
    $q .= ';';
    $result = $db->sql_query($q);
    $db->free_result();
    if($result)
      return true;
    else
      $db->_die('The search index was trying to rebuild itself when the error occured.');
  }
  
  /**
   * Partially rebuilds the search index, removing/inserting entries only for the current page
   * @param string $page_id
   * @param string $namespace
   */
  
  function rebuild_page_index($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$db->sql_query('SELECT page_text FROM '.table_prefix.'page_text
      WHERE page_id=\''.$db->escape($page_id).'\' AND namespace=\''.$db->escape($namespace).'\';'))
    {
      return $db->get_error();
    }
    $row = $db->fetchrow();
    $db->free_result();
    $search = new Searcher();
    $search->buildIndex(Array("ns={$namespace};pid={$page_id}"=>$row['page_text']));
    $new_index = $search->index;
    
    $keys = array_keys($search->index);
    foreach($keys as $i => $k)
    {
      $c =& $keys[$i];
      $c = hexencode($c, '', '');
    }
    $keys = "word=0x" . implode ( " OR word=0x", $keys ) . "";
    
    // Zap the cache
    $cache = array_keys($search->index);
    if ( count($cache) < 1 )
    {
      return false;
    }
    foreach ( $cache as $key => $_unused )
    {
      $cache[$key] = $db->escape( $cache[$key] );
    }
    $cache = "query LIKE '%" . implode ( "%' OR query LIKE '%", $cache ) . "%'";
    $sql = 'DELETE FROM '.table_prefix.'search_cache WHERE '.$cache;
    $db->sql_query($sql);
    
    $query = $db->sql_query('SELECT word,page_names FROM '.table_prefix.'search_index WHERE '.$keys.';');
    
    while($row = $db->fetchrow())
    {
      $row['word'] = rtrim($row['word'], "\0");
      $new_index[ $row['word'] ] = $row['page_names'] . ',' . $search->index[ $row['word'] ];
    }
    $db->free_result();
    
    $db->sql_query('DELETE FROM '.table_prefix.'search_index WHERE '.$keys.';');
    
    $secs = Array();
    $q = 'INSERT INTO '.table_prefix.'search_index(word,page_names) VALUES';
    foreach($new_index as $word => $pages)
    {
      $secs[] = '(\''.$db->escape($word).'\', \''.$db->escape($pages).'\')';
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
    
    $page_id = $db->escape(sanitize_page_id($page_id));
    if ( !isset($this->nslist[$namespace]) )
      die('$paths->get_page_groups(): HACKING ATTEMPT');
    
    $group_list = array();
    
    // What linked categories have this page?
    $q = $db->sql_query('SELECT g.pg_id FROM '.table_prefix.'page_groups AS g
  LEFT JOIN '.table_prefix.'categories AS c
    ON ( ( c.category_id = g.pg_target AND g.pg_type = ' . PAGE_GRP_CATLINK . ' ) OR c.category_id IS NULL )
  LEFT JOIN '.table_prefix.'page_group_members AS m
    ON ( ( g.pg_id = m.pg_id AND g.pg_type = ' . PAGE_GRP_NORMAL . ' ) OR ( m.pg_id IS NULL ) )
  LEFT JOIN '.table_prefix.'tags AS t
    ON ( ( t.tag_name = g.pg_target AND pg_type = ' . PAGE_GRP_TAGGED . ' ) OR t.tag_name IS NULL )
  WHERE
    ( c.page_id=\'' . $page_id . '\' AND c.namespace=\'' . $namespace . '\' ) OR
    ( t.page_id=\'' . $page_id . '\' AND t.namespace=\'' . $namespace . '\' ) OR
    ( m.page_id=\'' . $page_id . '\' AND m.namespace=\'' . $namespace . '\' );');
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      $group_list[] = $row['pg_id'];
    }
    
    $db->free_result();
    
    /*
    // Static-page groups
    $q = $db->sql_query('SELECT g.pg_id FROM '.table_prefix.'page_groups AS g
                           LEFT JOIN '.table_prefix.'page_group_members AS m
                             ON ( g.pg_id = m.pg_id )
                           WHERE m.page_id=\'' . $page_id . '\' AND m.namespace=\'' . $namespace . '\'
                           GROUP BY g.pg_id;');
    
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      $group_list[] = $row['pg_id'];
    }
    
    // Tag groups
    
    $q = $db->sql_query('SELECT g.pg_id FROM '.table_prefix.'page_groups AS g
                           LEFT JOIN '.table_prefix.'tags AS t
                             ON ( t.tag_name = g.pg_target AND pg_type = ' . PAGE_GRP_TAGGED . ' )
                           WHERE t.page_id = \'' . $page_id . '\' AND t.namespace = \'' . $namespace . '\';');
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      $group_list[] = $row['pg_id'];
    }
    */
    
    return $group_list;
    
  }
  
}
  
?>

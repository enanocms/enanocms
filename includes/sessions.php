<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * sessions.php - everything related to security and user management
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
/**
 * Anything and everything related to security and user management. This includes AES encryption, which is illegal in some countries.
 * Documenting the API was not easy - I hope you folks enjoy it.
 * @package Enano
 * @subpackage Session manager
 * @category security, user management, logins, etc.
 */

class sessionManager {
  
  # Variables
  
  /**
   * Whether we're logged in or not
   * @var bool
   */
   
  var $user_logged_in = false;
  
  /**
   * Our current low-privilege session key
   * @var string
   */
  
  var $sid;
  
  /**
   * Username of currently logged-in user, or IP address if not logged in
   * @var string
   */
  
  var $username;
  
  /**
   * User ID of currently logged-in user, or 1 if not logged in
   * @var int
   */
  
  var $user_id = 1;
  
  /**
   * Real name of currently logged-in user, or blank if not logged in
   * @var string
   */
  
  var $real_name;
  
  /**
   * E-mail address of currently logged-in user, or blank if not logged in
   * @var string
   */
  
  var $email;
  
  /**
   * List of "extra" user information fields (IM handles, etc.)
   * @var array (associative)
   */
  
  var $user_extra;
  
  /**
   * User level of current user
   * USER_LEVEL_GUEST: guest
   * USER_LEVEL_MEMBER: regular user
   * USER_LEVEL_CHPREF: default - pseudo-level that allows changing password and e-mail address (requires re-authentication)
   * USER_LEVEL_MOD: moderator
   * USER_LEVEL_ADMIN: administrator
   * @var int
   */
  
  var $user_level;
  
  /**
   * High-privilege session key
   * @var string or false if not running on high-level authentication
   */
  
  var $sid_super;
  
  /**
   * The user's theme preference, defaults to $template->default_theme
   * @var string
   */
  
  var $theme;
  
  /**
   * The user's style preference, or style auto-detected based on theme if not logged in
   * @var string
   */
  
  var $style;
  
  /**
   * Signature of current user - appended to comments, etc.
   * @var string
   */
  
  var $signature;
  
  /**
   * UNIX timestamp of when we were registered, or 0 if not logged in
   * @var int
   */
  
  var $reg_time;
  
  /**
   * The number of unread private messages this user has.
   * @var int
   */
  
  var $unread_pms = 0;
  
  /**
   * AES key used to encrypt passwords and session key info.
   * @var string
   * @access private
   */
   
  protected $private_key;
  
  /**
   * Regex that defines a valid username, minus the ^ and $, these are added later
   * @var string
   */
   
  var $valid_username = '([^<>&\?\'"%\n\r\t\a\/]+)';
  
  /**
   * The current user's user title. Defaults to NULL.
   * @var string
   */
  
  var $user_title = null;
   
  /**
   * What we're allowed to do as far as permissions go. This changes based on the value of the "auth" URI param.
   * @var string
   */
   
  var $auth_level = 1;
  
  /**
   * State variable to track if a session timed out
   * @var bool
   */
  
  var $sw_timed_out = false;
  
  /**
   * Token appended to some important forms to prevent CSRF.
   * @var string
   */
  
  var $csrf_token = false;
  
  /**
   * Switch to track if we're started or not.
   * @access private
   * @var bool
   */
   
  var $started = false;
  
  /**
   * Switch to control compatibility mode (for older Enano websites being upgraded)
   * @access private
   * @var bool
   */
   
  var $compat = false;
  
  /**
   * Our list of permission types.
   * @access private
   * @var array
   */
   
  var $acl_types = Array();
  
  /**
   * The list of descriptions for the permission types
   * @var array
   */
   
  var $acl_descs = Array();
  
  /**
   * A list of dependencies for ACL types.
   * @var array
   */
   
  var $acl_deps = Array();
  
  /**
   * Our tell-all list of permissions. Do not even try to change this.
   * @access private
   * @var array
   */
   
  var $perms = Array();
  
  /**
   * A cache variable - saved after sitewide permissions are checked but before page-specific permissions.
   * @var array
   * @access private
   */
  
  var $acl_base_cache = Array();
  
  /**
   * Stores the scope information for ACL types.
   * @var array
   * @access private
   */
   
  var $acl_scope = Array();
  
  /**
   * Array to track which default permissions are being used
   * @var array
   * @access private
   */
   
  var $acl_defaults_used = Array();
  
  /**
   * Array to track group membership.
   * @var array
   */
   
  var $groups = Array();
  
  /**
   * Associative array to track group modship.
   * @var array
   */
   
  var $group_mod = Array();
  
  /**
   * A constant array of user-level-to-rank default associations.
   * @var array
   */
  
  var $level_rank_table = array(
      USER_LEVEL_ADMIN  => RANK_ID_ADMIN,
      USER_LEVEL_MOD    => RANK_ID_MOD,
      USER_LEVEL_MEMBER => RANK_ID_MEMBER,
      USER_LEVEL_CHPREF => RANK_ID_MEMBER,
      USER_LEVEL_GUEST  => RANK_ID_GUEST
    );
  
  /**
   * A constant array that maps precedence constants to language strings
   * @var array
   */
  
  var $acl_inherit_lang_table = array(
      ACL_INHERIT_ENANO_DEFAULT   => 'acl_inherit_enano_default',
      ACL_INHERIT_GLOBAL_EVERYONE => 'acl_inherit_global_everyone',
      ACL_INHERIT_GLOBAL_GROUP    => 'acl_inherit_global_group',
      ACL_INHERIT_GLOBAL_USER     => 'acl_inherit_global_user',
      ACL_INHERIT_PG_EVERYONE     => 'acl_inherit_pg_everyone',
      ACL_INHERIT_PG_GROUP        => 'acl_inherit_pg_group',
      ACL_INHERIT_PG_USER         => 'acl_inherit_pg_user',
      ACL_INHERIT_LOCAL_EVERYONE  => 'acl_inherit_local_everyone',
      ACL_INHERIT_LOCAL_GROUP     => 'acl_inherit_local_group',
      ACL_INHERIT_LOCAL_USER      => 'acl_inherit_local_user'
    );
  
  # Basic functions
   
  /**
   * Constructor.
   */
   
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( defined('IN_ENANO_INSTALL') && !defined('IN_ENANO_UPGRADE') )
    {
      @include(ENANO_ROOT.'/config.new.php');
    }
    else
    {
      @include(ENANO_ROOT.'/config.php');
    }
    
    unset($dbhost, $dbname, $dbuser, $dbpasswd);
    if(isset($crypto_key))
    {
      $this->private_key = $crypto_key;
      $this->private_key = hexdecode($this->private_key);
    }
    else
    {
      if(is_writable(ENANO_ROOT.'/config.php'))
      {
        // Generate and stash a private key
        // This should only happen during an automated silent gradual migration to the new encryption platform.
        $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
        $this->private_key = $aes->gen_readymade_key();
        
        $config = file_get_contents(ENANO_ROOT.'/config.php');
        if(!$config)
        {
          die('$session->__construct(): can\'t get the contents of config.php');
        }
        
        $config = str_replace("?>", "\$crypto_key = '{$this->private_key}';\n?>", $config);
        // And while we're at it...
        $config = str_replace('MIDGET_INSTALLED', 'ENANO_INSTALLED', $config);
        $fh = @fopen(ENANO_ROOT.'/config.php', 'w');
        if ( !$fh ) 
        {
          die('$session->__construct(): Couldn\'t open config file for writing to store the private key, I tried to avoid something like this...');
        }
        
        fwrite($fh, $config);
        fclose($fh);
      }
      else
      {
        die_semicritical('Crypto error', '<p>No private key was found in the config file, and we can\'t generate one because we don\'t have write access to the config file. Please CHMOD config.php to 666 or 777 and reload this page.</p>');
      }
    }
    // Check for compatibility mode
    if(defined('IN_ENANO_INSTALL'))
    {
      $q = $db->sql_query('SELECT old_encryption FROM '.table_prefix.'users LIMIT 1;');
      if(!$q)
      {
        $error = mysql_error();
        if(strstr($error, "Unknown column 'old_encryption'"))
          $this->compat = true;
        else
          $db->_die('This should never happen and is a bug - the only error that was supposed to happen here didn\'t happen. (sessions.php in constructor, during compat mode check)');
      }
      $db->free_result();
    }
  }
  
  /**
   * PHP 4 compatible constructor. Deprecated in 1.1.x.
   */
   
  /*
  function sessionManager()
  {
    $this->__construct();
  }
  */
  
  /**
   * Wrapper function to sanitize strings for MySQL and HTML
   * @param string $text The text to sanitize
   * @return string
   */
  
  function prepare_text($text)
  {
    global $db;
    return $db->escape(htmlspecialchars($text));
  }
  
  /**
   * Makes a SQL query and handles error checking
   * @param string $query The SQL query to make
   * @return resource
   */
  
  function sql($query)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $result = $db->sql_query($query);
    if(!$result)
    {
      $db->_die('The error seems to have occurred somewhere in the session management code.');
    }
    return $result;
  }
  
  # Session restoration and permissions
  
  /**
   * Initializes the basic state of things, including most user prefs, login data, cookie stuff
   */
  
  function start()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $timezone;
    if($this->started) return;
    $this->started = true;
    $user = false;
    if ( isset($_COOKIE['sid']) )
    {
      if ( $this->compat )
      {
        $userdata = $this->compat_validate_session($_COOKIE['sid']);
      }
      else
      {
        $userdata = $this->validate_session($_COOKIE['sid']);
      }
      if ( is_array($userdata) )
      {
        $data = RenderMan::strToPageID($paths->get_pageid_from_url());
        
        if(!$this->compat && $userdata['account_active'] != 1 && $data[1] != 'Special' && $data[1] != 'Admin')
        {
          $this->show_inactive_error($userdata);
        }
        
        $this->sid = $_COOKIE['sid'];
        $this->user_logged_in = true;
        $this->user_id =       intval($userdata['user_id']);
        $this->username =      $userdata['username'];
        $this->user_level =    intval($userdata['user_level']);
        $this->real_name =     $userdata['real_name'];
        $this->email =         $userdata['email'];
        $this->unread_pms =    $userdata['num_pms'];
        $this->user_title =    ( isset($userdata['user_title']) ) ? $userdata['user_title'] : null;
        if(!$this->compat)
        {
          $this->theme =         $userdata['theme'];
          $this->style =         $userdata['style'];
          $this->signature =     $userdata['signature'];
          $this->reg_time =      $userdata['reg_time'];
        }
        $this->auth_level =    USER_LEVEL_MEMBER;
        // generate an anti-CSRF token
        $this->csrf_token =    sha1($this->username . $this->sid . $this->user_id);
        if(!isset($template->named_theme_list[$this->theme]))
        {
          if($this->compat || !is_object($template))
          {
            $this->theme = 'oxygen';
            $this->style = 'bleu';
          }
          else
          {
            $this->theme = $template->default_theme;
            $this->style = $template->default_style;
          }
        }
        $user = true;
        
        // set timezone params
        $GLOBALS['timezone'] = $userdata['user_timezone'];
        $GLOBALS['dst_params'] = explode(';', $userdata['user_dst']);
        foreach ( $GLOBALS['dst_params'] as &$parm )
        {
          if ( substr($parm, -1) != 'd' )
            $parm = intval($parm);
        }
        
        // Set language
        if ( !defined('ENANO_ALLOW_LOAD_NOLANG') )
        {
          $lang_id = intval($userdata['user_lang']);
          $lang = new Language($lang_id);
          @setlocale(LC_ALL, $lang->lang_code);
        }
        
        if(isset($_REQUEST['auth']) && !$this->sid_super)
        {
          // Now he thinks he's a moderator. Or maybe even an administrator. Let's find out if he's telling the truth.
          if($this->compat)
          {
            $key = $_REQUEST['auth'];
            $super = $this->compat_validate_session($key);
          }
          else
          {
            $key = $_REQUEST['auth'];
            if ( !empty($key) && ( strlen($key) / 2 ) % 4 == 0 )
            {
              $super = $this->validate_session($key);
            }
          }
          if(is_array(@$super))
          {
            $this->auth_level = intval($super['auth_level']);
            $this->sid_super = $_REQUEST['auth'];
          }
        }
      }
    }
    if(!$user)
    {
      //exit;
      $this->register_guest_session();
    }
    if(!$this->compat)
    {
      // init groups
      $q = $this->sql('SELECT g.group_name,g.group_id,m.is_mod FROM '.table_prefix.'groups AS g' . "\n"
        . '  LEFT JOIN '.table_prefix.'group_members AS m' . "\n"
        . '    ON g.group_id=m.group_id' . "\n"
        . '  WHERE ( m.user_id='.$this->user_id.'' . "\n" 
        . '    OR g.group_name=\'Everyone\')' . "\n"
        . '    ' . ( /* quick hack for upgrade compatibility reasons */ enano_version() == '1.0RC1' ? '' : 'AND ( m.pending != 1 OR m.pending IS NULL )' ) . '' . "\n"
        . '  ORDER BY group_id ASC;'); // The ORDER BY is to make sure "Everyone" comes first so the permissions can be overridden
      if($row = $db->fetchrow())
      {
        do {
          $this->groups[$row['group_id']] = $row['group_name'];
          $this->group_mod[$row['group_id']] = ( intval($row['is_mod']) == 1 );
        } while($row = $db->fetchrow());
      }
      else
      {
        die('No group info');
      }
      profiler_log('Fetched group memberships');
    }
    
    // make sure we aren't banned
    $this->check_banlist();
    
    // Printable page view? Probably the wrong place to control
    // it but $template is pretty dumb, it will just about always
    // do what you ask it to do, which isn't always what we want
    if ( isset ( $_GET['printable'] ) )
    {
      $this->theme = 'printable';
      $this->style = 'default';
    }
    
    // setup theme ACLs
    $template->process_theme_acls();
    
    profiler_log('Sessions started. Banlist and theme ACLs initialized');
  }
  
  # Logins
  
  /**
   * Attempts to perform a login using crypto functions
   * @param string $username The username
   * @param string $aes_data The encrypted password, hex-encoded
   * @param string $aes_key The MD5 hash of the encryption key, hex-encoded
   * @param string $challenge The 256-bit MD5 challenge string - first 128 bits should be the hash, the last 128 should be the challenge salt
   * @param int $level The privilege level we're authenticating for, defaults to 0
   * @param string $captcha_hash Optional. If we're locked out and the lockout policy is captcha, this should be the identifier for the code.
   * @param string $captcha_code Optional. If we're locked out and the lockout policy is captcha, this should be the code the user entered.
   * @param bool $remember Optional. If true, remembers the session for X days. Otherwise, assigns a short session. Defaults to false.
   * @param bool $lookup_key Optional. If true (default) this queries the database for the "real" encryption key. Else, uses what is given.
   * @return string 'success' on success, or error string on failure
   */
   
  function login_with_crypto($username, $aes_data, $aes_key_id, $challenge, $level = USER_LEVEL_MEMBER, $captcha_hash = false, $captcha_code = false, $remember = false, $lookup_key = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Instanciate the Rijndael encryption object
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    
    // Fetch our decryption key
    
    if ( $lookup_key )
    {
      $aes_key = $this->fetch_public_key($aes_key_id);
      if ( !$aes_key )
      {
        // It could be that our key cache is full. If it seems larger than 65KB, clear it
        if ( strlen(getConfig('login_key_cache')) > 65000 )
        {
          setConfig('login_key_cache', '');
          return array(
            'success' => false,
            'error' => 'key_not_found_cleared',
            );
        }
        return array(
          'success' => false,
          'error' => 'key_not_found'
          );
      }
    }
    else
    {
      $aes_key =& $aes_key_id;
    }
    
    // Convert the key to a binary string
    $bin_key = hexdecode($aes_key);
    
    if(strlen($bin_key) != AES_BITS / 8)
      return array(
        'success' => false,
        'error' => 'key_wrong_length'
        );
    
    // Decrypt our password
    $password = $aes->decrypt($aes_data, $bin_key, ENC_HEX);
    
    // Let the LoginAPI do the rest.
    return $this->login_without_crypto($username, $password, false, $level, $captcha_hash, $captcha_code, $remember);
  }
  
  /**
   * Attempts to login without using crypto stuff, mainly for use when the other side doesn't like Javascript
   * This method of authentication is inherently insecure, there's really nothing we can do about it except hope and pray that everyone moves to Firefox
   * Technically it still uses crypto, but it only decrypts the password already stored, which is (obviously) required for authentication
   * @param string $username The username
   * @param string $password The password -OR- the MD5 hash of the password if $already_md5ed is true
   * @param bool $already_md5ed This should be set to true if $password is an MD5 hash, and should be false if it's plaintext. Defaults to false.
   * @param int $level The privilege level we're authenticating for, defaults to 0
   * @param string $captcha_hash Optional. If we're locked out and the lockout policy is captcha, this should be the identifier for the code.
   * @param string $captcha_code Optional. If we're locked out and the lockout policy is captcha, this should be the code the user entered.
   * @param bool $remember Optional. If true, remembers the session for X days. Otherwise, assigns a short session. Defaults to false.
   */
  
  function login_without_crypto($username, $password, $already_md5ed = false, $level = USER_LEVEL_MEMBER, $captcha_hash = false, $captcha_code = false, $remember = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( $already_md5ed )
    {
      // No longer supported
      return array(
          'mode' => 'error',
          'error' => '$already_md5ed is no longer supported (set this parameter to false and make sure the password you send to $session->login_without_crypto() is not hashed)'
        );
    }
    
    // Replace underscores with spaces in username
    // (Added in 1.0.2)
    $username = str_replace('_', ' ', $username);
    
    // Perhaps we're upgrading Enano?
    if($this->compat)
    {
      return $this->login_compat($username, md5($password), $level);
    }
    
    if ( !defined('IN_ENANO_INSTALL') )
    {
      $locked_out = $this->get_lockout_info($lockout_data);
      
      $captcha_good = false;
      if ( $lockout_data['lockout_policy'] == 'captcha' && $captcha_hash && $captcha_code )
      {
        // policy is captcha -- check if it's correct, and if so, bypass lockout check
        $real_code = $this->get_captcha($captcha_hash);
        if ( strtolower($real_code) === strtolower($captcha_code) )
        {
          $captcha_good = true;
        }
      }
      if ( $lockout_data['lockout_policy'] != 'disable' && !$captcha_good )
      {
        if ( $lockout_data['lockout_fails'] >= $lockout_data['lockout_threshold'] )
        {
          // ooh boy, somebody's in trouble ;-)
          $row = $db->fetchrow();
          $db->free_result();
          return array(
              'success' => false,
              'error' => 'locked_out',
              'lockout_threshold' => $lockout_data['lockout_threshold'],
              'lockout_duration' => ( $lockout_data['lockout_duration'] ),
              'lockout_fails' => $lockout_data['lockout_fails'],
              'lockout_policy' => $lockout_data['lockout_policy'],
              'time_rem' => $lockout_data['lockout_time_rem'],
              'lockout_last_time' => $lockout_data['lockout_last_time']
            );
        }
      }
      $db->free_result();
    }
    
    // Instanciate the Rijndael encryption object
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    
    // Initialize our success switch
    $success = false;
    
    // Retrieve the real password from the database
    $username_db = $db->escape(strtolower($username));
    if ( !$db->sql_query('SELECT password,password_salt,old_encryption,user_id,user_level,temp_password,temp_password_time FROM '.table_prefix."users\n"
                       . "  WHERE " . ENANO_SQLFUNC_LOWERCASE . "(username) = '$username_db';") )
    {
      $this->sql('SELECT password,\'\' AS password_salt,old_encryption,user_id,user_level,temp_password,temp_password_time FROM '.table_prefix."users\n"
               . "  WHERE " . ENANO_SQLFUNC_LOWERCASE . "(username) = '$username_db';");
    }
    if ( $db->numrows() < 1 )
    {
      // This wasn't logged in <1.0.2, dunno how it slipped through
      if ( $level > USER_LEVEL_MEMBER )
        $this->sql('INSERT INTO ' . table_prefix . "logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES\n"
                   . '  (\'security\', \'admin_auth_bad\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($username).'\', '
                      . '\''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
      else
        $this->sql('INSERT INTO ' . table_prefix . "logs(log_type,action,time_id,date_string,author,edit_summary) VALUES\n"
                   . '  (\'security\', \'auth_bad\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($username).'\', '
                      . '\''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
      
      // Do we also need to increment the lockout countdown?
      if ( @$lockout_data['lockout_policy'] != 'disable' && !defined('IN_ENANO_INSTALL') )
      {
        $ipaddr = $db->escape($_SERVER['REMOTE_ADDR']);
        // increment fail count
        $this->sql('INSERT INTO '.table_prefix.'lockout(ipaddr, timestamp, action) VALUES(\'' . $ipaddr . '\', ' . time() . ', \'credential\');');
        $lockout_data['lockout_fails']++;
        return array(
            'success' => false,
            'error' => ( $lockout_data['lockout_fails'] >= $lockout_data['lockout_threshold'] ) ? 'locked_out' : 'invalid_credentials',
            'lockout_threshold' => $lockout_data['lockout_threshold'],
            'lockout_duration' => ( $lockout_data['lockout_duration'] ),
            'lockout_fails' => $lockout_data['lockout_fails'],
            'lockout_policy' => $lockout_data['lockout_policy']
          );
      }
      
      return array(
        'success' => false,
        'error' => 'invalid_credentials'
      );
    }
    $row = $db->fetchrow();
    
    // Check to see if we're logging in using a temporary password
    
    if((intval($row['temp_password_time']) + 3600*24) > time() )
    {
      $temp_pass = hmac_sha1($password, $row['password_salt']);
      if( $temp_pass === $row['temp_password'] )
      {
        $code = $plugins->setHook('login_password_reset');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        
        return array(
            'success' => false,
            'error' => 'valid_reset',
            'redirect_url' => makeUrlComplete('Special', 'PasswordReset/stage2/' . $row['user_id'] . '/' . $this->pk_encrypt($password))
          );
      }
    }
    
    if ( $row['old_encryption'] == 1 )
    {
      // The user's password is stored using the obsolete and insecure MD5 algorithm - we'll update the field with the new password
      if(md5($password) === $row['password'])
      {
        if ( !defined('IN_ENANO_UPGRADE') )
        {
          $hmac_secret = hexencode(AESCrypt::randkey(20), '', '');
          $password_hmac = hmac_sha1($password, $hmac_secret);
          $this->sql('UPDATE '.table_prefix."users SET password = '$password_hmac', password_salt = '$hmac_secret', old_encryption = 0 WHERE user_id={$row['user_id']};");
        }
        $success = true;
      }
    }
    else if ( $row['old_encryption'] == 2 || ( defined('ENANO_UPGRADE_USE_AES_PASSWORDS') ) && strlen($row['password']) != 40 )
    {
      // Our password field uses the 1.0RC1-1.1.5 encryption format
      $real_pass = $aes->decrypt($row['password'], $this->private_key);
      if($password === $real_pass)
      {
        if ( !defined('IN_ENANO_UPGRADE') )
        {
          $hmac_secret = hexencode(AESCrypt::randkey(20), '', '');
          $password_hmac = hmac_sha1($password, $hmac_secret);
          $this->sql('UPDATE '.table_prefix."users SET password = '$password_hmac', password_salt = '$hmac_secret', old_encryption = 0 WHERE user_id={$row['user_id']};");
        }
        $success = true;
      }
    }
    else
    {
      // Password uses HMAC-SHA1
      $user_challenge = hmac_sha1($password, $row['password_salt']);
      $password_hmac =& $row['password'];
      if ( $user_challenge === $password_hmac )
      {
        $success = true;
      }
    }
    if($success)
    {
      if((int)$level > (int)$row['user_level'])
        return array(
          'success' => false,
          'error' => 'too_big_for_britches'
        );
      $sess = $this->register_session($row['user_id'], $username, ( isset($password_hmac) ? $password_hmac : $password ), $level, $remember);
      if($sess)
      {
        if($level > USER_LEVEL_MEMBER)
          $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'admin_auth_good\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
        else
          $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'auth_good\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
        
        $code = $plugins->setHook('login_success');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        
        return array(
          'success' => true
          );
      }
      else
        return array(
          'success' => false,
          'error' => 'backend_fail'
        );
    }
    else
    {
      if($level > USER_LEVEL_MEMBER)
        $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'admin_auth_bad\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
      else
        $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'auth_bad\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
        
      // Do we also need to increment the lockout countdown?
      if ( !defined('IN_ENANO_INSTALL') && $lockout_data['lockout_policy'] != 'disable' )
      {
        $ipaddr = $db->escape($_SERVER['REMOTE_ADDR']);
        // increment fail count
        $this->sql('INSERT INTO '.table_prefix.'lockout(ipaddr, timestamp, action) VALUES(\'' . $ipaddr . '\', ' . time() . ', \'credential\');');
        $lockout_data['lockout_fails']++;
        return array(
            'success' => false,
            'error' => ( $lockout_data['lockout_fails'] >= $lockout_data['lockout_threshold'] ) ? 'locked_out' : 'invalid_credentials',
            'lockout_threshold' => $lockout_data['lockout_threshold'],
            'lockout_duration' => ( $lockout_data['lockout_duration'] ),
            'lockout_fails' => $lockout_data['lockout_fails'],
            'lockout_policy' => $lockout_data['lockout_policy']
          );
      }
        
      return array(
        'success' => false,
        'error' => 'invalid_credentials'
      );
    }
  }
  
  /**
   * Attempts to log in using the old table structure and algorithm. This is for upgrades from old 1.0.x releases.
   * @param string $username
   * @param string $password This should be an MD5 hash
   * @return string 'success' if successful, or error message on failure
   */
  
  function login_compat($username, $password, $level = 0)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $pass_hashed =& $password;
    $this->sql('SELECT password,user_id,user_level FROM '.table_prefix.'users WHERE username=\''.$this->prepare_text($username).'\';');
    if($db->numrows() < 1)
      return 'The username and/or password is incorrect.';
    $row = $db->fetchrow();
    if($row['password'] == $password)
    {
      if((int)$level > (int)$row['user_level'])
        return 'You are not authorized for this level of access.';
      $sess = $this->register_session_compat(intval($row['user_id']), $username, $password, $level);
      if($sess)
        return 'success';
      else
        return 'Your login credentials were correct, but an internal error occured while registering the session key in the database.';
    }
    else
    {
      return 'The username and/or password is incorrect.';
    }
  }
  
  /**
   * Registers a session key in the database. This function *ASSUMES* that the username and password have already been validated!
   * Basically the session key is a hex-encoded cookie (encrypted with the site's private key) that says "u=[username];p=[sha1 of password];s=[unique key id]"
   * @param int $user_id
   * @param string $username
   * @param string $password_hmac The HMAC of the user's password, right from the database
   * @param int $level The level of access to grant, defaults to USER_LEVEL_MEMBER
   * @param bool $remember Whether the session should be long-term (true) or not (false). Defaults to short-term.
   * @return bool
   */
   
  function register_session($user_id, $username, $password_hmac, $level = USER_LEVEL_MEMBER, $remember = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Random key identifier
    $salt = '';
    for ( $i = 0; $i < 32; $i++ )
    {
      $salt .= chr(mt_rand(32, 126));
    }
    
    // Session key
    if ( defined('ENANO_UPGRADE_USE_AES_PASSWORDS') )
    {
      $session_key = $this->pk_encrypt("u=$username;p=" . sha1($password_hmac) . ";s=$salt");
    }
    else
    {
      $session_key = hmac_sha1($password_hmac, $salt);
    }
    
    // Minimum level
    $level = max(array($level, USER_LEVEL_MEMBER));
    
    // Type of key
    $key_type = ( $level > USER_LEVEL_MEMBER ) ? SK_ELEV : ( $remember ? SK_LONG : SK_SHORT );
    
    // If we're registering an elevated-privilege key, it needs to be on GET
    if($level > USER_LEVEL_MEMBER)
    {
      $this->sid_super = $session_key;
      $_GET['auth'] = $session_key;
    }
    else
    {
      // Stash it in a cookie
      // For now, make the cookie last forever, we can change this in 1.1.x
      setcookie( 'sid', $session_key, time()+15552000, scriptPath.'/', null, $GLOBALS['is_https']);
      $_COOKIE['sid'] = $session_key;
    }
    // $keyhash is stored in the database, this is for compatibility with the older DB structure
    $keyhash = md5($session_key);
    // Record the user's IP
    $ip = $_SERVER['REMOTE_ADDR'];
    if(!is_valid_ip($ip))
      die('$session->register_session: Remote-Addr was spoofed');
    // The time needs to be stashed to enforce the 15-minute limit on elevated session keys
    $time = time();
    
    // Sanity check
    if(!is_int($user_id))
      die('Somehow an SQL injection attempt crawled into our session registrar! (1)');
    if(!is_int($level))
      die('Somehow an SQL injection attempt crawled into our session registrar! (2)');
    
    // Update RAM
    $this->user_id = $user_id;
    $this->user_level = max(array($this->user_level, $level));
    
    // All done!
    $query = $db->sql_query('INSERT INTO '.table_prefix.'session_keys(session_key, salt, user_id, auth_level, source_ip, time, key_type) VALUES(\''.$keyhash.'\', \''.$db->escape($salt).'\', '.$user_id.', '.$level.', \''.$ip.'\', '.$time.', ' . $key_type . ');');
    if ( !$query && defined('IN_ENANO_UPGRADE') )
      // we're trying to upgrade so the key_type column is probably missing - try it again without specifying the key type
      $this->sql('INSERT INTO '.table_prefix.'session_keys(session_key, salt, user_id, auth_level, source_ip, time) VALUES(\''.$keyhash.'\', \''.$db->escape($salt).'\', '.$user_id.', '.$level.', \''.$ip.'\', '.$time.');');
      
    return true;
  }
  
  /**
   * Identical to register_session in nature, but uses the old login/table structure. DO NOT use this except in the upgrade script under very controlled circumstances.
   * @see sessionManager::register_session()
   * @access private
   */
  
  function register_session_compat($user_id, $username, $password, $level = 0)
  {
    $salt = md5(microtime() . mt_rand());
    $thekey = md5($password . $salt);
    if($level > 0)
    {
      $this->sid_super = $thekey;
    }
    else
    {
      setcookie( 'sid', $thekey, time()+315360000, scriptPath.'/' );
      $_COOKIE['sid'] = $thekey;
    }
    $ip = ip2hex($_SERVER['REMOTE_ADDR']);
    if(!$ip)
      die('$session->register_session: Remote-Addr was spoofed');
    $time = time();
    if(!is_int($user_id))
      die('Somehow an SQL injection attempt crawled into our session registrar! (1)');
    if(!is_int($level))
      die('Somehow an SQL injection attempt crawled into our session registrar! (2)');
    $query = $this->sql('INSERT INTO '.table_prefix.'session_keys(session_key, salt, user_id, auth_level, source_ip, time) VALUES(\''.$thekey.'\', \''.$salt.'\', '.$user_id.', '.$level.', \''.$ip.'\', '.$time.');');
    return true;
  }
  
  /**
   * Tells us if we're locked out from logging in or not.
   * @param reference will be filled with information regarding in-progress lockout
   * @return bool True if locked out, false otherwise
   */
  
  function get_lockout_info(&$lockdata)
  {
    global $db;
    
    // this has to be initialized to hide warnings
    $lockdata = null;
    
    // Query database for lockout info
    $locked_out = false;
    $threshold = ( $_ = getConfig('lockout_threshold') ) ? intval($_) : 5;
    $duration  = ( $_ = getConfig('lockout_duration') ) ? intval($_) : 15;
    // convert to minutes
    $duration  = $duration * 60;
    $policy = ( $x = getConfig('lockout_policy') && in_array(getConfig('lockout_policy'), array('lockout', 'disable', 'captcha')) ) ? getConfig('lockout_policy') : 'lockout';
    if ( $policy != 'disable' )
    {
      $ipaddr = $db->escape($_SERVER['REMOTE_ADDR']);
      $timestamp_cutoff = time() - $duration;
      $q = $this->sql('SELECT timestamp FROM ' . table_prefix . 'lockout WHERE timestamp > ' . $timestamp_cutoff . ' AND ipaddr = \'' . $ipaddr . '\' ORDER BY timestamp DESC;');
      $fails = $db->numrows();
      $row = $db->fetchrow();
      $locked_out = ( $fails >= $threshold );
      $lockdata = array(
          'locked_out' => $locked_out,
          'lockout_threshold' => $threshold,
          'lockout_duration' => ( $duration / 60 ),
          'lockout_fails' => $fails,
          'lockout_policy' => $policy,
          'lockout_last_time' => $row['timestamp'],
          'time_rem' => ( $duration / 60 ) - round( ( time() - $row['timestamp'] ) / 60 ),
          'captcha' => ''
        );
      $db->free_result();
    }
    return $locked_out;
  }
  
  /**
   * Creates/restores a guest session
   * @todo implement real session management for guests
   */
   
  function register_guest_session()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    $this->username = $_SERVER['REMOTE_ADDR'];
    $this->user_level = USER_LEVEL_GUEST;
    if($this->compat || defined('IN_ENANO_INSTALL'))
    {
      $this->theme = 'oxygen';
      $this->style = 'bleu';
    }
    else
    {
      $this->theme = ( isset($_GET['theme']) && isset($template->named_theme_list[$_GET['theme']])) ? $_GET['theme'] : $template->default_theme;
      $this->style = ( isset($_GET['style']) && file_exists(ENANO_ROOT.'/themes/'.$this->theme . '/css/'.$_GET['style'].'.css' )) ? $_GET['style'] : preg_replace('/\.css$/', '', $template->named_theme_list[$this->theme]['default_style']);
    }
    $this->user_id = 1;
    // This is a VERY special case we are allowing. It lets the installer create languages using the Enano API.
    if ( !defined('ENANO_ALLOW_LOAD_NOLANG') )
    {
      $language = ( isset($_GET['lang']) && preg_match('/^[a-z0-9-_]+$/', @$_GET['lang']) ) ? $_GET['lang'] : intval(getConfig('default_language'));
      $lang = new Language($language);
      @setlocale(LC_ALL, $lang->lang_code);
    }
    // make a CSRF token
    $this->csrf_token = hmac_sha1($_SERVER['REMOTE_ADDR'], sha1($this->private_key));
  }
  
  /**
   * Validates a session key, and returns the userdata associated with the key or false
   * @param string $key The session key to validate
   * @return array Keys are 'user_id', 'username', 'email', 'real_name', 'user_level', 'theme', 'style', 'signature', 'reg_time', 'account_active', 'activation_key', and 'auth_level' or bool false if validation failed. The key 'auth_level' is the maximum authorization level that this key provides.
   */
   
  function validate_session($key)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    profiler_log("SessionManager: checking session: " . sha1($key));
    
    if ( strlen($key) > 48 )
    {
      return $this->validate_aes_session($key);
    }
    
    profiler_log("SessionManager: checking session: " . $key);
    
    return $this->validate_session_shared($key, '');
  }
  
  /**
   * Validates an old-format AES session key. DO NOT USE THIS. Will return false if called outside of an upgrade.
   * @param string Session key
   * @return array
   */
  
  protected function validate_aes_session($key)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // No valid use except during upgrades
    if ( !preg_match('/^upg-/', enano_version()) && !defined('IN_ENANO_UPGRADE') )
      return false;
    
    $decrypted_key = $this->pk_decrypt($key);
    if ( !$decrypted_key )
    {
      // die_semicritical('AES encryption error', '<p>Something went wrong during the AES decryption process.</p><pre>'.print_r($decrypted_key, true).'</pre>');
      return false;
    }
    
    $n = preg_match('/^u='.$this->valid_username.';p=([A-Fa-f0-9]+?);s=(.{32})$/', $decrypted_key, $keydata);
    if($n < 1)
    {
      echo '(debug) $session->validate_session: Key does not match regex<br />Decrypted key: '.$decrypted_key;
      return false;
    }
    $keyhash = md5($key);
    $salt = $db->escape($keydata[3]);
    
    return $this->validate_session_shared($keyhash, $salt, true);
  }
  
  /**
   * Shared portion of session validation. Do not try to call this.
   * @return array
   * @access private
   */
  
  protected function validate_session_shared($key, $salt, $loose_call = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // using a normal call to $db->sql_query to avoid failing on errors here
    $columns_select  = "u.user_id AS uid, u.username, u.password, u.password_salt, u.email, u.real_name, u.user_level, u.theme,\n"
                      . "  u.style,u.signature, u.reg_time, u.account_active, u.activation_key, u.user_lang, u.user_title, k.salt, k.source_ip,\n"
                      . "  k.time, k.auth_level, k.key_type, COUNT(p.message_id) AS num_pms, u.user_timezone, u.user_dst, x.*";
    
    $columns_groupby = "u.user_id, u.username, u.password, u.password_salt, u.email, u.real_name, u.user_level, u.theme, u.style, u.signature,\n"
                      . "           u.reg_time, u.account_active, u.activation_key, u.user_lang, u.user_timezone, u.user_title, u.user_dst,\n"
                      . "           k.salt, k.source_ip, k.time, k.auth_level, k.key_type, x.user_id, x.user_aim, x.user_yahoo, x.user_msn,\n"
                      . "           x.user_xmpp, x.user_homepage, x.user_location, x.user_job, x.user_hobbies, x.email_public,\n"
                      . "           x.disable_js_fx";
    
    $joins = "  LEFT JOIN " . table_prefix . "users AS u\n"
            . "    ON ( u.user_id=k.user_id )\n"
            . "  LEFT JOIN " . table_prefix . "users_extra AS x\n"
            . "    ON ( u.user_id=x.user_id OR x.user_id IS NULL )\n"
            . "  LEFT JOIN " . table_prefix . "privmsgs AS p\n"
            . "    ON ( p.message_to=u.username AND p.message_read=0 )\n";
    if ( !$loose_call )
    {
      $key_md5 = md5($key);
      $query = $db->sql_query("SELECT $columns_select\n"
                            . "FROM " . table_prefix . "session_keys AS k\n"
                            . $joins
                            . "  WHERE k.session_key='$key_md5'\n"
                            . "  GROUP BY $columns_groupby;");
    }
    else
    {
      $query = $db->sql_query("SELECT $columns_select\n"
                            . "FROM " . table_prefix . "session_keys AS k\n"
                            . $joins
                            . "  WHERE k.session_key='$key'\n"
                            . "    AND k.salt='$salt'\n"
                            . "  GROUP BY $columns_groupby;");
    }
    
    if ( !$query && ( defined('IN_ENANO_INSTALL') or defined('IN_ENANO_UPGRADE') ) )
    {
      $query = $this->sql('SELECT u.user_id AS uid,u.username,u.password,\'\' AS password_salt,u.email,u.real_name,u.user_level,u.theme,u.style,u.signature,u.reg_time,u.account_active,u.activation_key,k.source_ip,k.time,k.auth_level,COUNT(p.message_id) AS num_pms, 1440 AS user_timezone, \'0;0;0;0;60\' AS user_dst, ' . SK_SHORT . ' AS key_type FROM '.table_prefix.'session_keys AS k
                             LEFT JOIN '.table_prefix.'users AS u
                               ON ( u.user_id=k.user_id )
                             LEFT JOIN '.table_prefix.'privmsgs AS p
                               ON ( p.message_to=u.username AND p.message_read=0 )
                             WHERE k.session_key=\''.$key.'\'
                               AND k.salt=\''.$salt.'\'
                             GROUP BY u.user_id,u.username,u.password,u.email,u.real_name,u.user_level,u.theme,u.style,u.signature,u.reg_time,u.account_active,u.activation_key,k.source_ip,k.time,k.auth_level;');
    }
    else if ( !$query )
    {
      $db->_die();
    }
    if($db->numrows() < 1)
    {
      // echo '(debug) $session->validate_session: Key was not found in database<br />';
      return false;
    }
    $row = $db->fetchrow();
    profiler_log("SessionManager: session check: selected and fetched results");
    
    $row['user_id'] =& $row['uid'];
    $ip = $_SERVER['REMOTE_ADDR'];
    if($row['auth_level'] > $row['user_level'])
    {
      // Failed authorization check
      // echo '(debug) $session->validate_session: access to this auth level denied<br />';
      return false;
    }
    if($ip != $row['source_ip'])
    {
      // Special exception for 1.1.x upgrade - the 1.1.3 upgrade changes the size of the column and this is what validate_session
      // expects, but if the column size hasn't changed yet just check the first 10 digits of the IP.
      $fail = true;
      if ( defined('IN_ENANO_UPGRADE') )
      {
        if ( substr($ip, 0, 10) == substr($row['source_ip'], 0, 10) )
          $fail = false;
      }
      // Failed IP address check
      // echo '(debug) $session->validate_session: IP address mismatch<br />';
      if ( $fail )
        return false;
    }
    
    // $loose_call is turned on only from validate_aes_session
    if ( !$loose_call )
    {
      $correct_key = hexdecode(hmac_sha1($row['password'], $row['salt']));
      $user_key = hexdecode($key);
      if ( $correct_key !== $user_key || !is_string($user_key) )
      {
        return false;
      }
    }
    else
    {
      // if this is a "loose call", this only works once (during the final upgrade stage). Destroy the contents of session_keys.
      if ( $row['auth_level'] == USER_LEVEL_ADMIN && preg_match('/^upg-/', enano_version()) )
        $this->sql('DELETE FROM ' . table_prefix . "session_keys;");
    }
    
    // timestamp check
    switch ( $row['key_type'] )
    {
      case SK_SHORT:
        $time_now = time();
        $time_key = $row['time'] + ( 60 * intval(getConfig('session_short', '720')) );
        if ( $time_now > $time_key )
        {
          // Session timed out
          return false;
        }
        break;
      case SK_LONG:
        if ( intval(getConfig('session_remember_time', '0')) === 0 )
        {
          // sessions last infinitely, timestamp validation is therefore successful
          break;
        }
        $time_now = time();
        $time_key = $row['time'] + ( 86400 * intval(getConfig('session_remember_time', '30')) );
        if ( $time_now > $time_key )
        {
          // Session timed out
          return false;
        }
        break;
      case SK_ELEV:
        $time_now = time();
        $time_key = $row['time'] + 900;
        if($time_now > $time_key && $row['auth_level'] > USER_LEVEL_MEMBER)
        {
          // Session timed out
          // echo '(debug) $session->validate_session: super session timed out<br />';
          $this->sw_timed_out = true;
          return false;
        }
        break;
    }
        
    // If this is an elevated-access or short-term session key, update the time
    if( $row['key_type'] == SK_ELEV || $row['key_type'] == SK_SHORT )
    {
      $this->sql('UPDATE '.table_prefix.'session_keys SET time='.time().' WHERE session_key=\''.md5($key).'\';');
    }
    
    $user_extra = array();
    foreach ( array('user_aim', 'user_yahoo', 'user_msn', 'user_xmpp', 'user_homepage', 'user_location', 'user_job', 'user_hobbies', 'email_public', 'disable_js_fx') as $column )
    {
      if ( isset($row[$column]) )
        $user_extra[$column] = $row[$column];
      else
        $user_extra[$column] = '';
    }
    
    $this->user_extra = $user_extra;
    // Leave the rest to PHP's automatic garbage collector ;-)
    
    $row['password'] = '';
    $row['user_timezone'] = intval($row['user_timezone']) - 1440;
    
    profiler_log("SessionManager: finished session check");
    
    return $row;
  }
  
  /**
   * Validates a session key, and returns the userdata associated with the key or false. Optimized for compatibility with the old MD5-based auth system.
   * @param string $key The session key to validate
   * @return array Keys are 'user_id', 'username', 'email', 'real_name', 'user_level', 'theme', 'style', 'signature', 'reg_time', 'account_active', 'activation_key', and 'auth_level' or bool false if validation failed. The key 'auth_level' is the maximum authorization level that this key provides.
   */
   
  function compat_validate_session($key)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $key = $db->escape($key);
    
    $query = $this->sql('SELECT u.user_id,u.username,u.password,u.email,u.real_name,u.user_level,k.source_ip,k.salt,k.time,k.auth_level,1440 AS user_timezone FROM '.table_prefix.'session_keys AS k
                           LEFT JOIN '.table_prefix.'users AS u
                             ON u.user_id=k.user_id
                           WHERE k.session_key=\''.$key.'\';');
    if($db->numrows() < 1)
    {
      // echo '(debug) $session->validate_session: Key '.$key.' was not found in database<br />';
      return false;
    }
    $row = $db->fetchrow();
    $ip = ip2hex($_SERVER['REMOTE_ADDR']);
    if($row['auth_level'] > $row['user_level'])
    {
      // Failed authorization check
      // echo '(debug) $session->validate_session: user not authorized for this access level';
      return false;
    }
    if($ip != $row['source_ip'])
    {
      // Failed IP address check
      // echo '(debug) $session->validate_session: IP address mismatch; IP in table: '.$row['source_ip'].'; reported IP: '.$ip.'';
      return false;
    }
    
    // Do the password validation
    $real_key = md5($row['password'] . $row['salt']);
    
    //die('<pre>'.print_r($keydata, true).'</pre>');
    if($real_key != $key)
    {
      // Failed password check
      // echo '(debug) $session->validate_session: supplied password is wrong<br />Real key: '.$real_key.'<br />User key: '.$key;
      return false;
    }
    
    $time_now = time();
    $time_key = $row['time'] + 900;
    if($time_now > $time_key && $row['auth_level'] >= 1)
    {
      $this->sw_timed_out = true;
      // Session timed out
      // echo '(debug) $session->validate_session: super session timed out<br />';
      return false;
    }
    
    $row['user_timezone'] = intval($row['user_timezone']) - 1440;
    
    return $row;
  }
   
  /**
   * Demotes us to one less than the specified auth level. AKA destroys elevated authentication and/or logs out the user, depending on $level
   * @param int $level How low we should go - USER_LEVEL_MEMBER means demote to USER_LEVEL_GUEST, and anything more powerful than USER_LEVEL_MEMBER means demote to USER_LEVEL_MEMBER
   * @return string 'success' if successful, or error on failure
   */
   
  function logout($level = USER_LEVEL_MEMBER)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $ou = $this->username;
    $oid = $this->user_id;
    if($level > USER_LEVEL_CHPREF)
    {
      $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
      if(!$this->user_logged_in || $this->auth_level < ( USER_LEVEL_MEMBER + 1))
      {
        return 'success';
      }
      // Destroy elevated privileges
      $keyhash = md5($this->sid_super);
      $this->sql('DELETE FROM '.table_prefix.'session_keys WHERE session_key=\''.$keyhash.'\' AND user_id=\'' . $this->user_id . '\';');
      $this->sid_super = false;
      $this->auth_level = USER_LEVEL_MEMBER;
    }
    else
    {
      if($this->user_logged_in)
      {
        $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
        // Completely destroy our session
        if($this->auth_level > USER_LEVEL_CHPREF)
        {
          $this->logout(USER_LEVEL_ADMIN);
        }
        $this->sql('DELETE FROM '.table_prefix.'session_keys WHERE session_key=\''.md5($this->sid).'\';');
        setcookie( 'sid', '', time()-(3600*24), scriptPath.'/' );
      }
    }
    $code = $plugins->setHook('logout_success'); // , Array('level'=>$level,'old_username'=>$ou,'old_user_id'=>$oid));
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    return 'success';
  }
  
  # Miscellaneous stuff
  
  /**
   * Alerts the user that their account is inactive, and tells them appropriate steps to remedy the situation. Halts execution.
   * @param array Return from validate_session()
   */
  
  function show_inactive_error($userdata)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $language = intval(getConfig('default_language'));
    $lang = new Language($language);
    @setlocale(LC_ALL, $lang->lang_code);
    
    $a = getConfig('account_activation');
    switch($a)
    {
      case 'none':
      default:
        $solution = $lang->get('user_login_noact_solution_none');
        break;
      case 'user':
        $solution = $lang->get('user_login_noact_solution_user');
        break;
      case 'admin':
        $solution = $lang->get('user_login_noact_solution_admin');
        break;
    }
    
    // admin activation request opportunity
    $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'logs WHERE log_type=\'admin\' AND action=\'activ_req\' AND edit_summary=\'' . $db->escape($userdata['username']) . '\';');
    if ( !$q )
      $db->_die();
    
    $can_request = ( $db->numrows() < 1 );
    $db->free_result();
    
    if ( isset($_POST['logout']) )
    {
      $this->sid = $_COOKIE['sid'];
      $this->user_logged_in = true;
      $this->user_id =       intval($userdata['user_id']);
      $this->username =      $userdata['username'];
      $this->auth_level =    USER_LEVEL_MEMBER;
      $this->user_level =    USER_LEVEL_MEMBER;
      $this->logout();
      redirect(scriptPath . '/', $lang->get('user_login_noact_msg_logout_success_title'), $lang->get('user_login_noact_msg_logout_success_body'), 5);
    }
    
    if ( $can_request && !isset($_POST['activation_request']) )
    {
      $form = '<p>' . $lang->get('user_login_noact_msg_ask_admins') . '</p>
               <form action="' . makeUrlNS('System', 'ActivateStub') . '" method="post">
                 <p><input type="submit" name="activation_request" value="' . $lang->get('user_login_noact_btn_request_activation') . '" /> <input type="submit" name="logout" value="' . $lang->get('user_login_noact_btn_log_out') . '" /></p>
               </form>';
    }
    else
    {
      if ( $can_request && isset($_POST['activation_request']) )
      {
        $this->admin_activation_request($userdata['username']);
        $form = '<p>' . $lang->get('user_login_noact_msg_admins_just_asked') . '</p>
                 <form action="' . makeUrlNS('System', 'ActivateStub') . '" method="post">
                   <p><input type="submit" name="logout" value="' . $lang->get('user_login_noact_btn_log_out') . '" /></p>
                 </form>';
      }
      else
      {
        $form = '<p>' . $lang->get('user_login_noact_msg_admins_asked') . '</p>
                 <form action="' . makeUrlNS('System', 'ActivateStub') . '" method="post">
                   <p><input type="submit" name="logout" value="' . $lang->get('user_login_noact_btn_log_out') . '" /></p>
                 </form>';
      }
    }
    
    die_semicritical($lang->get('user_login_noact_title'), '<p>' . $lang->get('user_login_noact_msg_intro') . ' '.$solution.'</p>' . $form);
  }
  
  /**
   * Appends the high-privilege session key to the URL if we are authorized to do high-privilege stuff
   * @param string $url The URL to add session data to
   * @return string
   */
  
  function append_sid($url)
  {
    $sep = ( strstr($url, '?') ) ? '&' : '?';
    if ( $this->sid_super )
    {
      $url = $url . $sep . 'auth=' . urlencode($this->sid_super);
      // echo($this->sid_super.'<br/>');
    }
    return $url;
  }
  
  /**
   * Grabs the user's password MD5
   * @return string, or bool false if access denied
   */
   
  function grab_password_hash()
  {
    return false;
  }
  
  /**
   * Destroys the user's password MD5 in memory
   */
  
  function disallow_password_grab()
  {
    $this->password_hash = false;
    return false;
  }
  
  /**
   * Generates an AES key and stashes it in the database
   * @return string Hex-encoded AES key
   */
   
  function rijndael_genkey()
  {
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    $key = $aes->gen_readymade_key();
    $keys = getConfig('login_key_cache');
    if(is_string($keys))
      $keys .= $key;
    else
      $keys = $key;
    setConfig('login_key_cache', $keys);
    return $key;
  }
  
  /**
   * Generate a totally random 128-bit value for MD5 challenges
   * @return string
   */
   
  function dss_rand()
  {
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    $random = $aes->randkey(128);
    unset($aes);
    return md5(microtime() . $random);
  }
  
  /**
   * Fetch a cached login public key using the MD5sum as an identifier. Each key can only be fetched once before it is destroyed.
   * @param string $md5 The MD5 sum of the key
   * @return string, or bool false on failure
   */
   
  function fetch_public_key($md5)
  {
    $keys = getConfig('login_key_cache');
    $keys = enano_str_split($keys, AES_BITS / 4);
    
    foreach($keys as $i => $k)
    {
      if(md5($k) == $md5)
      {
        unset($keys[$i]);
        if(count($keys) > 0)
        {
          if ( strlen(getConfig('login_key_cache') ) > 64000 )
          {
            // This should only need to be done once every month or so for an average-size site
            setConfig('login_key_cache', '');
          }
          else
          {
            $keys = implode('', array_values($keys));
            setConfig('login_key_cache', $keys);
          }
        }
        else
        {
          setConfig('login_key_cache', '');
        }
        return $k;
      }
    }
    // Couldn't find the key...
    return false;
  }
  
  /**
   * Adds a user to a group.
   * @param int User ID
   * @param int Group ID
   * @param bool Group moderator - defaults to false
   * @return bool True on success, false on failure
   */
  
  function add_user_to_group($user_id, $group_id, $is_mod = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Validation
    if ( !is_int($user_id) || !is_int($group_id) || !is_bool($is_mod) )
      return false;
    if ( $user_id < 1 || $group_id < 1 )
      return false;
    
    $mod_switch = ( $is_mod ) ? '1' : '0';
    $q = $this->sql('SELECT member_id,is_mod FROM '.table_prefix.'group_members WHERE user_id=' . $user_id . ' AND group_id=' . $group_id . ';');
    if ( !$q )
      $db->_die();
    if ( $db->numrows() < 1 )
    {
      // User is not in group
      $this->sql('INSERT INTO '.table_prefix.'group_members(user_id,group_id,is_mod) VALUES(' . $user_id . ', ' . $group_id . ', ' . $mod_switch . ');');
      return true;
    }
    else
    {
      $row = $db->fetchrow();
      // Update modship status
      if ( strval($row['is_mod']) == $mod_switch )
      {
        // Modship unchanged
        return true;
      }
      else
      {
        // Modship changed
        $this->sql('UPDATE '.table_prefix.'group_members SET is_mod=' . $mod_switch . ' WHERE member_id=' . $row['member_id'] . ';');
        return true;
      }
    }
    return false;
  }
  
  /**
   * Removes a user from a group.
   * @param int User ID
   * @param int Group ID
   * @return bool True on success, false on failure
   * @todo put a little more error checking in...
   */
  
  function remove_user_from_group($user_id, $group_id)
  {
    if ( !is_int($user_id) || !is_int($group_id) )
      return false;
    $this->sql('DELETE FROM '.table_prefix."group_members WHERE user_id=$user_id AND group_id=$group_id;");
    return true;
  }
  
  /**
   * Checks the banlist to ensure that we're an allowed user. Doesn't return anything because it dies if the user is banned.
   */
   
  function check_banlist()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $col_reason = ( $this->compat ) ? '"No reason entered (session manager is in compatibility mode)" AS reason' : 'reason';
    $banned = false;
    if ( $this->user_logged_in )
    {
      // check by IP, email, and username
      if ( ENANO_DBLAYER == 'MYSQL' )
      {
        $sql = "SELECT $col_reason, ban_value, ban_type, is_regex FROM " . table_prefix . "banlist WHERE \n"
              . "    ( ban_type = " . BAN_IP    . " AND is_regex = 0 ) OR \n"
              . "    ( ban_type = " . BAN_IP    . " AND is_regex = 1 AND '{$_SERVER['REMOTE_ADDR']}' REGEXP ban_value ) OR \n"
              . "    ( ban_type = " . BAN_USER  . " AND is_regex = 0 AND ban_value = '{$this->username}' ) OR \n"
              . "    ( ban_type = " . BAN_USER  . " AND is_regex = 1 AND '{$this->username}' REGEXP ban_value ) OR \n"
              . "    ( ban_type = " . BAN_EMAIL . " AND is_regex = 0 AND ban_value = '{$this->email}' ) OR \n"
              . "    ( ban_type = " . BAN_EMAIL . " AND is_regex = 1 AND '{$this->email}' REGEXP ban_value ) \n"
              . "  ORDER BY ban_type ASC;";
      }
      else if ( ENANO_DBLAYER == 'PGSQL' )
      {
        $sql = "SELECT $col_reason, ban_value, ban_type, is_regex FROM " . table_prefix . "banlist WHERE \n"
              . "    ( ban_type = " . BAN_IP    . " AND is_regex = 0 ) OR \n"
              . "    ( ban_type = " . BAN_IP    . " AND is_regex = 1 AND '{$_SERVER['REMOTE_ADDR']}' ~ ban_value ) OR \n"
              . "    ( ban_type = " . BAN_USER  . " AND is_regex = 0 AND ban_value = '{$this->username}' ) OR \n"
              . "    ( ban_type = " . BAN_USER  . " AND is_regex = 1 AND '{$this->username}' ~ ban_value ) OR \n"
              . "    ( ban_type = " . BAN_EMAIL . " AND is_regex = 0 AND ban_value = '{$this->email}' ) OR \n"
              . "    ( ban_type = " . BAN_EMAIL . " AND is_regex = 1 AND '{$this->email}' ~ ban_value ) \n"
              . "  ORDER BY ban_type ASC;";
      }
      $q = $this->sql($sql);
      if ( $db->numrows() > 0 )
      {
        while ( list($reason_temp, $ban_value, $ban_type, $is_regex) = $db->fetchrow_num() )
        {
          if ( $ban_type == BAN_IP && $is_regex != 1 )
          {
            // check range
            $regexp = parse_ip_range_regex($ban_value);
            if ( !$regexp )
            {
              continue;
            }
            if ( preg_match("/$regexp/", $_SERVER['REMOTE_ADDR']) )
            {
              $reason = $reason_temp;
              $banned = true;
            }
          }
          else
          {
            // User is banned
            $banned = true;
            $reason = $reason_temp;
          }
        }
      }
      $db->free_result();
    }
    else
    {
      // check by IP only
      if ( ENANO_DBLAYER == 'MYSQL' )
      {
        $sql = "SELECT $col_reason, ban_value, ban_type, is_regex FROM " . table_prefix . "banlist WHERE
                  ( ban_type = " . BAN_IP    . " AND is_regex = 0 ) OR
                  ( ban_type = " . BAN_IP    . " AND is_regex = 1 AND '{$_SERVER['REMOTE_ADDR']}' REGEXP ban_value )
                ORDER BY ban_type ASC;";
      }
      else if ( ENANO_DBLAYER == 'PGSQL' )
      {
        $sql = "SELECT $col_reason, ban_value, ban_type, is_regex FROM " . table_prefix . "banlist WHERE
                  ( ban_type = " . BAN_IP    . " AND is_regex = 0 ) OR
                  ( ban_type = " . BAN_IP    . " AND is_regex = 1 AND '{$_SERVER['REMOTE_ADDR']}' ~ ban_value )
                ORDER BY ban_type ASC;";
      }
      $q = $this->sql($sql);
      if ( $db->numrows() > 0 )
      {
        while ( list($reason_temp, $ban_value, $ban_type, $is_regex) = $db->fetchrow_num() )
        {
          if ( $ban_type == BAN_IP && $is_regex != 1 )
          {
            // check range
            $regexp = parse_ip_range_regex($ban_value);
            if ( !$regexp )
              continue;
            if ( preg_match("/$regexp/", $_SERVER['REMOTE_ADDR']) )
            {
              $reason = $reason_temp;
              $banned = true;
            }
          }
          else
          {
            // User is banned
            $reason = $reason_temp;
            $banned = true;
          }
        }
      }
      $db->free_result();
    }
    if ( $banned && $paths->get_pageid_from_url() != $paths->nslist['Special'].'CSS' )
    {
      // This guy is banned - kill the session, kill the database connection, bail out, and be pretty about it
      die_semicritical($lang->get('user_ban_msg_title'), '<p>' . $lang->get('user_ban_msg_body') . '</p><div class="error-box"><b>' . $lang->get('user_ban_lbl_reason') . '</b><br />' . $reason . '</div>');
      exit;
    }
  }
  
  # Registration
  
  /**
   * Registers a user. This does not perform any type of login.
   * @param string New user's username
   * @param string This should be unencrypted.
   * @param string E-mail address.
   * @param string Optional, defaults to ''.
   * @param bool Optional. If true, the account is not activated initially and an admin activation request is sent. The caller is responsible for sending the address info and notice.
   */
   
  function create_user($username, $password, $email, $real_name = '', $coppa = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    // Initialize AES
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    
    // Since we're recording IP addresses, make sure the user's IP is safe.
    $ip =& $_SERVER['REMOTE_ADDR'];
    if ( !is_valid_ip($ip) )
      return 'Invalid IP';
    
    if ( !preg_match('#^'.$this->valid_username.'$#', $username) )
      return $lang->get('user_reg_err_username_banned_chars');
    
    $username = str_replace('_', ' ', $username);
    $user_orig = $username;
    $username = $this->prepare_text($username);
    $email = $this->prepare_text($email);
    $real_name = $this->prepare_text($real_name);
    
    $nameclause = ( $real_name != '' ) ? ' OR real_name=\''.$real_name.'\'' : '';
    $q = $this->sql('SELECT * FROM '.table_prefix.'users WHERE ' . ENANO_SQLFUNC_LOWERCASE . '(username)=\''.strtolower($username).'\' OR email=\''.$email.'\''.$nameclause.';');
    if($db->numrows() > 0)
    {
      $row = $db->fetchrow();
      $str = 'user_reg_err_dupe';
      
      if ( $row['username'] == $username )
      {
        $str .= '_username';
      }
      if ( $row['email'] == $email )
      {
        $str .= '_email';
      }
      if ( $row['real_name'] == $real_name && $real_name != '' )
      {
        $str .= '_realname';
      }
      
      return $lang->get($str);
    }
    
    // Is the password strong enough?
    if ( getConfig('pw_strength_enable') )
    {
      $min_score = intval( getConfig('pw_strength_minimum') );
      $pass_score = password_score($password);
      if ( $pass_score < $min_score )
      {
        return $lang->get('user_reg_err_password_too_weak');
      }
    }
    
    // Require the account to be activated?
    switch(getConfig('account_activation'))
    {
      case 'none':
      default:
        $active = '1';
        break;
      case 'user':
        $active = '0';
        break;
      case 'admin':
        $active = '0';
        break;
    }
    if ( $coppa )
      $active = '0';
    
    $coppa_col = ( $coppa ) ? '1' : '0';
    
    // Generate a totally random activation key
    $actkey = sha1 ( microtime() . mt_rand() );

    // We good, create the user
    $this->sql('INSERT INTO '.table_prefix.'users ( username, email, real_name, theme, style, reg_time, account_active, activation_key, user_level, user_coppa, user_registration_ip ) VALUES ( \''.$username.'\', \''.$email.'\', \''.$real_name.'\', \''.$template->default_theme.'\', \''.$template->default_style.'\', '.time().', '.$active.', \''.$actkey.'\', '.USER_LEVEL_CHPREF.', ' . $coppa_col . ', \'' . $ip . '\' );');
    
    // Get user ID and create users_extra entry
    $q = $this->sql('SELECT user_id FROM '.table_prefix."users WHERE username='$username';");
    if ( $db->numrows() > 0 )
    {
      list($user_id) = $db->fetchrow_num();
      $db->free_result();
      
      $this->sql('INSERT INTO '.table_prefix.'users_extra(user_id) VALUES(' . $user_id . ');');
    }
    
    // Set the password
    $this->set_password($user_id, $password);
    
    // Config option added, 1.1.5
    if ( getConfig('userpage_grant_acl', '1') == '1' )             
    {
      // Grant edit and very limited mod access to the userpage
      $acl_data = array(
          'read' => AUTH_ALLOW,
          'view_source' => AUTH_ALLOW,
          'edit_page' => AUTH_ALLOW,
          'post_comments' => AUTH_ALLOW,
          'edit_comments' => AUTH_ALLOW, // only allows editing own comments
          'history_view' => AUTH_ALLOW,
          'history_rollback' => AUTH_ALLOW,
          'rename' => AUTH_ALLOW,
          'delete_page' => AUTH_ALLOW,
          'tag_create' => AUTH_ALLOW,
          'tag_delete_own' => AUTH_ALLOW,
          'tag_delete_other' => AUTH_ALLOW,
          'edit_cat' => AUTH_ALLOW,
          'create_page' => AUTH_ALLOW
        );
      $acl_data = $db->escape($this->perm_to_string($acl_data));
      $userpage = $db->escape(sanitize_page_id($user_orig));
      $cols = "target_type, target_id, page_id, namespace, rules";
      $vals = ACL_TYPE_USER . ", $user_id, '$userpage', 'User', '$acl_data'";
      $q = "INSERT INTO ".table_prefix."acl($cols) VALUES($vals);";
      $this->sql($q);
    }
    
    // Require the account to be activated?
    if ( $coppa )
    {
      $this->admin_activation_request($user_orig);
      $this->send_coppa_mail($user_orig, $email);
    }
    else
    {
      switch(getConfig('account_activation'))
      {
        case 'none':
        default:
          break;
        case 'user':
          $a = $this->send_activation_mail($user_orig);
          if(!$a)
          {
            $this->admin_activation_request($user_orig);
            return $lang->get('user_reg_err_actmail_failed') . ' ' . $a;
          }
          break;
        case 'admin':
          $this->admin_activation_request($user_orig);
          break;
      }
    }
    
    // Leave some data behind for the hook
    $code = $plugins->setHook('user_registered');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    // Uncomment to automatically log the user in (WARNING: commented out for a reason - doesn't consider activation and other things)
    // $this->register_session($user_orig, $password);
    return 'success';
  }
  
  /**
   * Attempts to send an e-mail to the specified user with activation instructions.
   * @param string $u The usernamd of the user requesting activation
   * @return bool true on success, false on failure
   */
   
  function send_activation_mail($u, $actkey = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    $q = $this->sql('SELECT username,activation_key,account_active,email FROM '.table_prefix.'users WHERE username=\''.$db->escape($u).'\';');
    $r = $db->fetchrow();
    if ( empty($r['email']) )
      $db->_die('BUG: $session->send_activation_mail(): no e-mail address in row');
    
    $aklink = makeUrlComplete('Special', 'ActivateAccount/'.str_replace(' ', '_', $u).'/'. ( ( is_string($actkey) ) ? $actkey : $r['activation_key'] ) );
    $message = $lang->get('user_reg_activation_email', array(
        'activation_link' => $aklink,
        'username' => $u
      ));
      
    if ( getConfig('smtp_enabled') == '1' )
    {
      $result = smtp_send_email($r['email'], $lang->get('user_reg_activation_email_subject'), preg_replace("#(?<!\r)\n#s", "\n", $message), getConfig('contact_email'));
      if ( $result == 'success' )
      {
        $result = true;
      }
      else
      {
        echo $result;
        $result = false;
      }
    }
    else
    {
      $result = mail($r['email'], $lang->get('user_reg_activation_email_subject'), preg_replace("#(?<!\r)\n#s", "\n", $message), 'From: '.getConfig('contact_email'));
    }
    return $result;
  }
  
  /**
   * Attempts to send an e-mail to the specified user's e-mail address on file intended for the parents
   * @param string $u The usernamd of the user requesting activation
   * @return bool true on success, false on failure
   */
   
  function send_coppa_mail($u, $actkey = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $q = $this->sql('SELECT username,email FROM '.table_prefix.'users WHERE user_id=2 OR user_level=' . USER_LEVEL_ADMIN . ' ORDER BY user_id ASC;');
    $un = $db->fetchrow();
    $admin_user = $un['username'];
    
    $q = $this->sql('SELECT username,activation_key,account_active,email FROM '.table_prefix.'users WHERE username=\''.$db->escape($u).'\';');
    $r = $db->fetchrow();
    if ( empty($r['email']) )
      $db->_die('BUG: $session->send_activation_mail(): no e-mail address in row');
      
    if(isset($_SERVER['HTTPS'])) $prot = 'https';
    else $prot = 'http';                                                                           
    if($_SERVER['SERVER_PORT'] == '80') $p = '';
    else $p = ':'.$_SERVER['SERVER_PORT'];
    $sidbak = false;
    if($this->sid_super)
      $sidbak = $this->sid_super;
    $this->sid_super = false;
    if($sidbak)
      $this->sid_super = $sidbak;
    unset($sidbak);
    $link = "$prot://".$_SERVER['HTTP_HOST'].scriptPath;
    
    $message = $lang->get(
        'user_reg_activation_email_coppa',
        array(
          'username' => $u,
          'admin_user' => $admin_user,
          'site_link' => $link
        )
      );
    
    
    if(getConfig('smtp_enabled') == '1')
    {
      $result = smtp_send_email($r['email'], getConfig('site_name').' website account activation', preg_replace("#(?<!\r)\n#s", "\n", $message), getConfig('contact_email'));
      if($result == 'success') 
      {
        $result = true;
      }
      else
      {
        echo $result;
        $result = false;
      }
    } 
    else
    {
      $result = mail($r['email'], getConfig('site_name').' website account activation', preg_replace("#(?<!\r)\n#s", "\n", $message), 'From: '.getConfig('contact_email'));
    }
    return $result;
  }
  
  /**
   * Sends an e-mail to a user so they can reset their password.
   * @param int $user The user ID, or username if it's a string
   * @return bool true on success, false on failure
   */
   
  function mail_password_reset($user)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if(is_int($user))
    {
      $q = $this->sql('SELECT user_id,username,email FROM '.table_prefix.'users WHERE user_id='.$user.';'); // This is SAFE! This is only called if $user is an integer
    }
    elseif(is_string($user))
    {
      $q = $this->sql('SELECT user_id,username,email FROM '.table_prefix.'users WHERE ' . ENANO_SQLFUNC_LOWERCASE . '(username)=' . ENANO_SQLFUNC_LOWERCASE . '(\''.$db->escape($user).'\');');
    }
    else
    {
      return false;
    }
    
    $row = $db->fetchrow();
    $temp_pass = $this->random_pass();
    
    $this->register_temp_password($row['user_id'], $temp_pass);
    
    $site_name = getConfig('site_name');
 
    $message = $lang->get('userfuncs_passreset_email', array(
        'username' => $row['username'],
        'site_name' => $site_name,
        'remote_addr' => $_SERVER['REMOTE_ADDR'],
        'temp_pass' => $temp_pass
      ));
    
    if(getConfig('smtp_enabled') == '1')
    {
      $result = smtp_send_email($row['email'], getConfig('site_name').' password reset', preg_replace("#(?<!\r)\n#s", "\n", $message), getConfig('contact_email'));
      if($result == 'success')
      {
        $result = true;
      }
      else
      {
        echo '<p>'.$result.'</p>';
        $result = false;
      }
    } else {
      $result = mail($row['email'], getConfig('site_name').' password reset', preg_replace("#(?<!\r)\n#s", "\n", $message), 'From: '.getConfig('contact_email'));
    }
    return $result;
  }
  
  /**
   * Sets the temporary password for the specified user to whatever is specified.
   * @param int $user_id
   * @param string $password
   * @return bool
   */
   
  function register_temp_password($user_id, $password)
  {
    global $db;
    if ( !is_int($user_id) )
      return false;
    
    $this->sql('SELECT password_salt FROM ' . table_prefix . "users WHERE user_id = $user_id;");
    if ( $db->numrows() < 1 )
      return false;
    
    list($salt) = $db->fetchrow_num();
    $db->free_result();
    
    $temp_pass = hmac_sha1($password, $salt);
    $this->sql('UPDATE '.table_prefix.'users SET temp_password=\'' . $temp_pass . '\',temp_password_time='.time().' WHERE user_id='.intval($user_id).';');
  }
  
  /**
   * Sends a request to the admin panel to have the username $u activated.
   * @param string $u The username of the user requesting activation
   */
  
  function admin_activation_request($u)
  {
    global $db;
    $this->sql('INSERT INTO '.table_prefix.'logs(log_type, action, time_id, date_string, author, edit_summary) VALUES(\'admin\', \'activ_req\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$this->username.'\', \''.$db->escape($u).'\');');
  }
  
  /**
   * Activates a user account. If the action fails, a report is sent to the admin.
   * @param string $user The username of the user requesting activation
   * @param string $key The activation key
   */
  
  function activate_account($user, $key)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->sql('UPDATE '.table_prefix.'users SET account_active=1 WHERE username=\''.$db->escape($user).'\' AND activation_key=\''.$db->escape($key).'\';');
    $r = mysql_affected_rows();
    if ( $r > 0 )
    {
      $e = $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'activ_good\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($user).'\', \''.$_SERVER['REMOTE_ADDR'].'\')');
    }
    else
    {
      $e = $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'activ_bad\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($user).'\', \''.$_SERVER['REMOTE_ADDR'].'\')');
    }
    return $r;
  }
  
  /**
   * For a given user level identifier (USER_LEVEL_*), returns a string describing that user level.
   * @param int User level
   * @param bool If true, returns a shorter string. Optional.
   * @return string
   */
  
  function userlevel_to_string($user_level, $short = false)
  {
    global $lang;
    
    static $levels = array(
        'short' => array(
            USER_LEVEL_GUEST => 'Guest',
            USER_LEVEL_MEMBER => 'Member',
            USER_LEVEL_CHPREF => 'Sensitive preferences changeable',
            USER_LEVEL_MOD => 'Moderator',
            USER_LEVEL_ADMIN => 'Administrative'
          ),
        'long' => array(
            USER_LEVEL_GUEST => 'Low - guest privileges',
            USER_LEVEL_MEMBER => 'Standard - normal member level',
            USER_LEVEL_CHPREF => 'Medium - user can change his/her own e-mail address and password',
            USER_LEVEL_MOD => 'High - moderator privileges',
            USER_LEVEL_ADMIN => 'Highest - administrative privileges'
          ),
        'l10n' => false
      );
    
    if ( is_object($lang) && !$levels['l10n'] )
    {
      $levels = array(
          'short' => array(
              USER_LEVEL_GUEST => $lang->get('user_level_short_guest'),
              USER_LEVEL_MEMBER => $lang->get('user_level_short_member'),
              USER_LEVEL_CHPREF => $lang->get('user_level_short_chpref'),
              USER_LEVEL_MOD => $lang->get('user_level_short_mod'),
              USER_LEVEL_ADMIN => $lang->get('user_level_short_admin')
            ),
          'long' => array(
              USER_LEVEL_GUEST => $lang->get('user_level_long_guest'),
              USER_LEVEL_MEMBER => $lang->get('user_level_long_member'),
              USER_LEVEL_CHPREF => $lang->get('user_level_long_chpref'),
              USER_LEVEL_MOD => $lang->get('user_level_long_mod'),
              USER_LEVEL_ADMIN => $lang->get('user_level_long_admin')
            ),
          'l10n' => true
        );
    }
    
    $key = ( $short ) ? 'short' : 'long';
    if ( isset($levels[$key][$user_level]) )
    {
      return $levels[$key][$user_level];
    }
    else
    {
      if ( $short )
      {
        return ( is_object($lang) ) ? $lang->get('user_level_short_unknown', array('user_level' => $user_level)) : "Unknown - $user_level";
      }
      else
      {
        return ( is_object($lang) ) ? $lang->get('user_level_long_unknown', array('user_level' => $user_level)) : "Unknown level ($user_level)";
      }
    }
    
    return 'Linux rocks!';
    
  }
  
  /**
   * Updates a user's information in the database. Note that any of the values except $user_id can be false if you want to preserve the old values.
   * Not localized because this really isn't used a whole lot anymore.
   * @param int $user_id The user ID of the user to update - this cannot be changed
   * @param string $username The new username
   * @param string $old_pass The current password - only required if sessionManager::$user_level < USER_LEVEL_ADMIN. This should usually be an UNENCRYPTED string. This can also be an array - if it is, key 0 is treated as data AES-encrypted with key 1
   * @param string $password The new password
   * @param string $email The new e-mail address
   * @param string $realname The new real name
   * @param string $signature The updated forum/comment signature
   * @param int $user_level The updated user level
   * @return string 'success' if successful, or array of error strings on failure
   */
   
  function update_user($user_id, $username = false, $old_pass = false, $password = false, $email = false, $realname = false, $signature = false, $user_level = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Create some arrays
    
    $errors = Array(); // Used to hold error strings
    $strs = Array();   // Sub-query statements
    
    // Scan the user ID for problems
    if(intval($user_id) < 1) $errors[] = 'SQL injection attempt';
    
    // Instanciate the AES encryption class
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    
    // If all of our input vars are false, then we've effectively done our job so get out of here
    if($username === false && $password === false && $email === false && $realname === false && $signature === false && $user_level === false)
    {
   // echo 'debug: $session->update_user(): success (no changes requested)';
      return 'success';
    }
    
    // Initialize our authentication check
    $authed = false;
    
    // Verify the inputted password
    if(is_string($old_pass))
    {
      $q = $this->sql('SELECT password FROM '.table_prefix.'users WHERE user_id='.$user_id.';');
      if($db->numrows() < 1)
      {
        $errors[] = 'The password data could not be selected for verification.';
      }
      else
      {
        $row = $db->fetchrow();
        $real = $aes->decrypt($row['password'], $this->private_key, ENC_HEX);
        if($real == $old_pass)
          $authed = true;
      }
    }
    
    elseif(is_array($old_pass))
    {
      $old_pass = $aes->decrypt($old_pass[0], $old_pass[1]);
      $q = $this->sql('SELECT password FROM '.table_prefix.'users WHERE user_id='.$user_id.';');
      if($db->numrows() < 1)
      {
        $errors[] = 'The password data could not be selected for verification.';
      }
      else
      {
        $row = $db->fetchrow();
        $real = $aes->decrypt($row['password'], $this->private_key, ENC_HEX);
        if($real == $old_pass)
          $authed = true;
      }
    }
    
    // Initialize our query
    $q = 'UPDATE '.table_prefix.'users SET ';
    
    if($this->auth_level >= USER_LEVEL_ADMIN || $authed) // Need the current password in order to update the e-mail address, change the username, or reset the password
    {
      // Username
      if(is_string($username))
      {
        // Check the username for problems
        if(!preg_match('#^'.$this->valid_username.'$#', $username))
          $errors[] = 'The username you entered contains invalid characters.';
        $strs[] = 'username=\''.$db->escape($username).'\'';
      }
      // Password
      if(is_string($password) && strlen($password) >= 6)
      {
        // Password needs to be encrypted before being stashed
        $encpass = $aes->encrypt($password, $this->private_key, ENC_HEX);
        if(!$encpass)
          $errors[] = 'The password could not be encrypted due to an internal error.';
        $strs[] = 'password=\''.$encpass.'\'';
      }
      // E-mail addy
      if(is_string($email))
      {
        if(!check_email_address($email))
          $errors[] = 'The e-mail address you entered is invalid.';
        $strs[] = 'email=\''.$db->escape($email).'\'';
      }
    }
    // Real name
    if(is_string($realname))
    {
      $strs[] = 'real_name=\''.$db->escape($realname).'\'';
    }
    // Forum/comment signature
    if(is_string($signature))
    {
      $strs[] = 'signature=\''.$db->escape($signature).'\'';
    }
    // User level
    if(is_int($user_level))
    {
      $strs[] = 'user_level='.$user_level;
    }
    
    // Add our generated query to the query string
    $q .= implode(',', $strs);
    
    // One last error check
    if(sizeof($strs) < 1) $errors[] = 'An internal error occured building the SQL query, this is a bug';
    if(sizeof($errors) > 0) return $errors;
    
    // Free our temp arrays
    unset($strs, $errors);
    
    // Finalize the query and run it
    $q .= ' WHERE user_id='.$user_id.';';
    $this->sql($q);
    
    // We also need to trigger re-activation.
    if ( is_string($email) )
    {
      switch(getConfig('account_activation'))
      {
        case 'user':
        case 'admin':
          
          if ( $session->user_level >= USER_LEVEL_MOD && getConfig('account_activation') == 'admin' )
            // Don't require re-activation by admins for admins
            break;
          
          // retrieve username
          if ( !$username )
          {
            $q = $this->sql('SELECT username FROM '.table_prefix.'users WHERE user_id='.$user_id.';');
            if($db->numrows() < 1)
            {
              $errors[] = 'The username could not be selected.';
            }
            else
            {
              $row = $db->fetchrow();
              $username = $row['username'];
            }
          }
          if ( !$username )
            return $errors;
          
          // Generate a totally random activation key
          $actkey = sha1 ( microtime() . mt_rand() );
          $a = $this->send_activation_mail($username, $actkey);
          if(!$a)
          {
            $this->admin_activation_request($username);
          }
          // Deactivate the account until e-mail is confirmed
          $q = $db->sql_query('UPDATE '.table_prefix.'users SET account_active=0,activation_key=\'' . $actkey . '\' WHERE user_id=' . $user_id . ';');
          break;
      }
    }
    
    // Yay! We're done
    return 'success';
  }
  
  /**
   * Sets a user's password.
   * @param int|string User ID or username
   * @param string New password
   */
  
  function set_password($user, $password)
  {
    // Generate new password and salt
    $hmac_secret = hexencode(AESCrypt::randkey(20), '', '');
    $password_hmac = hmac_sha1($password, $hmac_secret);
    
    // Figure out how we want to specify the user
    $uidcol = is_int($user) ? "user_id = $user" : ENANO_SQLFUNC_LOWERCASE . "(username) = '" . strtolower($this->prepare_text($user)) . "'";
    
    // Perform update
    $this->sql('UPDATE ' . table_prefix . "users SET old_encryption = 0, password = '$password_hmac', password_salt = '$hmac_secret' WHERE $uidcol;");
    
    return true;
  }
  
  /**
   * Encrypts a string using the site's private key.
   * @param string
   * @param int Return type - one of ENC_BINARY, ENC_HEX, ENC_BASE64
   * @return string
   */
  
  function pk_encrypt($string, $return_type = ENC_HEX)
  {
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    return $aes->encrypt($string, $this->private_key, $return_type);
  }
  
  /**
   * Encrypts a string using the site's private key.
   * @param string
   * @param int Input type - one of ENC_BINARY, ENC_HEX, ENC_BASE64
   * @return string
   */
  
  function pk_decrypt($string, $input_type = ENC_HEX)
  {
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    return $aes->decrypt($string, $this->private_key, $input_type);
  }
  
  #
  # USER RANKS
  #
  
  /**
   * SYNOPSIS OF THE RANK SYSTEM
   * Enano's rank logic calculates a user's rank based on a precedence scale. The way things are checked is:
   *   1. Check to see if the user has a specific rank assigned. Use that if possible.
   *   2. Check the user's primary group to see if it specifies a rank. Use that if possible.
   *   3. Check the other groups a user is in. If one that has a custom rank is encountered, use that rank.
   *   4. See if the user's user level has a specific rank hard-coded to be associated with it. (Always overrideable as can be seen above)
   *   5. Use the "member" rank
   */
  
  /**
   * Generates a textual SQL query for fetching rank data to be sent to calculate_user_rank().
   * @param string Text to append, possibly a WHERE clause or so
   * @return string
   */
  
  function generate_rank_sql($append = '')
  {
    // Generate level-to-rank associations
    $assoc = array();
    foreach ( $this->level_rank_table as $level => $rank )
    {
      $assoc[] = "        ( u.user_level = $level AND rl.rank_id = $rank )";
    }
    $assoc = implode(" OR\n", $assoc) . "\n";
    
    $gid_col = ( ENANO_DBLAYER == 'PGSQL' ) ?
      'array_to_string(' . table_prefix . 'array_accum(m.group_id), \',\') AS group_list' :
      'GROUP_CONCAT(m.group_id) AS group_list';
    
    // The actual query
    $sql = "SELECT u.user_id, u.username, u.user_level, u.user_group, u.user_rank, u.user_title, g.group_rank,\n"
         . "       COALESCE(ru.rank_id,    rg.rank_id,    rl.rank_id,    rd.rank_id   ) AS rank_id,\n"
         . "       COALESCE(ru.rank_title, rg.rank_title, rl.rank_title, rd.rank_title) AS rank_title,\n"
         . "       COALESCE(ru.rank_style, rg.rank_style, rl.rank_style, rd.rank_style) AS rank_style,\n"
         . "       rg.rank_id AS group_rank_id,\n"
         . "       ( ru.rank_id IS NULL AND rg.rank_id IS NULL ) AS using_default,\n"
         . "       ( ru.rank_id IS NULL AND rg.rank_id IS NOT NULL ) AS using_group,\n"
         . "       ( ru.rank_id IS NOT NULL ) AS using_user,\n"
         . "       u.user_rank_userset,\n"
         . "       $gid_col\n"
         . "  FROM " . table_prefix . "users AS u\n"
         . "  LEFT JOIN " . table_prefix . "groups AS g\n"
         . "    ON ( g.group_id = u.user_group )\n"
         . "  LEFT JOIN " . table_prefix . "group_members AS m\n"
         . "    ON ( u.user_id = m.user_id )\n"
         . "  LEFT JOIN " . table_prefix . "ranks AS ru\n"
         . "    ON ( u.user_rank = ru.rank_id )\n"
         . "  LEFT JOIN " . table_prefix . "ranks AS rg\n"
         . "    ON ( g.group_rank = rg.rank_id )\n"
         . "  LEFT JOIN " . table_prefix . "ranks AS rl\n"
         . "    ON (\n"
         . $assoc
         . "      )\n"
         . "  LEFT JOIN " . table_prefix . "ranks AS rd\n"
         . "    ON ( rd.rank_id = 1 )$append\n"
         . "  GROUP BY u.user_id, u.username, u.user_level, u.user_group, u.user_rank, u.user_title, u.user_rank_userset, g.group_rank,\n"
         . "       ru.rank_id, ru.rank_title, ru.rank_style,rg.rank_id, rg.rank_title, rg.rank_style,\n"
         . "       rl.rank_id, rl.rank_title, rl.rank_style,rd.rank_id, rd.rank_title, rd.rank_style;";
    
    return $sql;
  }
  
  /**
   * Returns an associative array with a user's rank information.
   * The array will contain the following values:
   *   username: string  The user's username
   *   user_id:  integer Numerical user ID
   *   rank_id:  integer Numerical rank ID
   *   rank:     string  The user's current rank
   *   title:    string  The user's custom user title if applicable; should be displayed one line below the rank
   *   style:    string  CSS for the username
   * @param int|string Username *or* user ID
   * @return array or false on failure
   */
  
  function get_user_rank($id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $user_ranks;
    // cache info in RAM if possible
    static $_cache = array();
    
    if ( is_int($id) && $id == 0 )
      $id = 1;
    
    if ( is_int($id) )
      $col = "u.user_id = $id";
    else if ( is_string($id) )
      $col = ENANO_SQLFUNC_LOWERCASE . "(username) = " . ENANO_SQLFUNC_LOWERCASE . "('" . $db->escape($id) . "')";
    else
      // invalid parameter
      return false;
      
    // check the RAM cache
    if ( isset($_cache[$id]) )
      return $_cache[$id];
    
    // check the disk cache
    if ( is_int($id) )
    {
      if ( isset($user_ranks[$id]) )
      {
        $_cache[$id] =& $user_ranks[$id];
        return $user_ranks[$id];
      }
    }
    else if ( is_string($id) )
    {
      foreach ( $user_ranks as $key => $valarray )
      {
        if ( is_string($key) && strtolower($key) == strtolower($id) )
        {
          $_cache[$id] = $valarray;
          return $valarray;
        }
      }
    }
    
    $sql = $this->generate_rank_sql("\n  WHERE $col");
    
    $q = $this->sql($sql);
    // any results?
    if ( $db->numrows() < 1 )
    {
      // nuttin'.
      $db->free_result();
      $_cache[$id] = false;
      return false;
    }
    
    // Found something.
    $row = $db->fetchrow();
    $db->free_result();
    
    $row = $this->calculate_user_rank($row);
    
    $_cache[$id] = $row;
    return $row;
  }
  
  /**
   * Performs the actual rank calculation based on the contents of a row.
   * @param array
   * @return array
   */
  
  function calculate_user_rank($row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    static $rank_cache = array();
    static $group_ranks = array();
    
    // try to cache that rank info
    if ( !isset($rank_cache[ intval($row['rank_id']) ]) && $row['rank_id'] )
    {
      $rank_cache[ intval($row['rank_id']) ] = array(
          'rank_id' => intval($row['rank_id']),
          'rank_title' => intval($row['rank_title']),
          'rank_style' => intval($row['rank_style'])
        );
    }
    // cache group info (if appropriate)
    if ( $row['using_group'] && !isset($group_ranks[ intval($row['user_group']) ]) )
    {
      $group_ranks[ intval($row['user_group']) ] = intval($row['group_rank_id']);
    }
    
    // sanitize and process the as-of-yet rank data
    $row['rank_id'] = intval($row["rank_id"]);
    $row['rank_title'] = $row["rank_title"];
    
    // if we're falling back to some default, then see if we can use one of the user's other groups
    if ( $row['using_default'] && !empty($row['group_list']) )
    {
      $group_list = explode(',', $row['group_list']);
      if ( array_walk($group_list, 'intval') )
      {
        // go through the group list and see if any of them has a rank assigned
        foreach ( $group_list as $group_id )
        {
          // cached in RAM? Preferably use that.
          if ( !isset($group_ranks[$group_id]) )
          {
            // Not cached - grab it
            $q = $this->sql('SELECT group_rank FROM ' . table_prefix . "groups WHERE group_id = $group_id;");
            if ( $db->numrows() < 1 )
            {
              $db->free_result();
              continue;
            }
            list($result) = $db->fetchrow_num();
            $db->free_result();
            
            if ( $result === null || $result < 1 )
            {
              $group_ranks[$group_id] = false;
            }
            else
            {
              $group_ranks[$group_id] = intval($result);
            }
          }
          // we've got it now
          if ( $group_ranks[$group_id] )
          {
            // found a group with a rank assigned
            // so get the rank info
            $rank_id =& $group_ranks[$group_id];
            if ( !isset($rank_cache[$rank_id]) )
            {
              $q = $this->sql('SELECT rank_id, rank_title, rank_style FROM ' . table_prefix . "ranks WHERE rank_id = $rank_id;");
              if ( $db->numrows() < 1 )
              {
                $db->free_result();
                continue;
              }
              $rank_cache[$rank_id] = $db->fetchrow();
              $db->free_result();
            }
            // set the final rank parameters
            // die("found member-of-group exception with uid {$row['user_id']} gid $group_id rid $rank_id rt {$rank_cache[$rank_id]['rank_title']}");
            $row['rank_id'] = $rank_id;
            $row['rank_title'] = $rank_cache[$rank_id]['rank_title'];
            $row['rank_style'] = $rank_cache[$rank_id]['rank_style'];
            break;
          }
        }
      }
    }
    
    if ( $row['user_title'] === NULL )
      $row['user_title'] = false;
    
    $row['user_id'] = intval($row['user_id']);
    $row['user_level'] = intval($row['user_level']);
    $row['user_group'] = intval($row['user_group']);
    
    unset($row['user_rank'], $row['group_rank'], $row['group_list'], $row['using_default'], $row['using_group'], $row['user_level'], $row['user_group'], $row['username']);
    return $row;
  }
  
  /**
   * Get the list of ranks that a user is allowed to use. Returns false if they cannot change it.
   * @param string|int User ID or username
   * @return array Associative by rank ID
   */
  
  function get_user_possible_ranks($id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // cache info in RAM if possible
    static $_cache = array();
    
    if ( is_int($id) && $id == 0 )
      $id = 1;
    
    if ( is_int($id) )
      $col = "u.user_id = $id";
    else if ( is_string($id) )
      $col = ENANO_SQLFUNC_LOWERCASE . "(username) = " . ENANO_SQLFUNC_LOWERCASE . "('" . $db->escape($id) . "')";
    else
      // invalid parameter
      return false;
      
    // check the RAM cache
    if ( isset($_cache[$id]) )
      return $_cache[$id];
    
    $sql = $this->generate_rank_sql("\n  WHERE $col");
    
    $q = $this->sql($sql);
    // any results?
    if ( $db->numrows() < 1 )
    {
      // nuttin'.
      $db->free_result();
      $_cache[$id] = false;
      return false;
    }
    
    // Found something.
    $row = $db->fetchrow();
    $db->free_result();
    
    if ( $row['using_user'] && !$row['user_rank_userset'] )
    {
      // The user's rank was set manually by an admin.
      $result = array(
        array(
          'rank_id' => $row['rank_id'],
          'rank_title' => $row['rank_title'],
          'rank_style' => $row['rank_style'],
          'rank_type' => 'user'
          )
        );
      $_cache[$id] = $result;
      return $result;
    }
    
    // copy the result to a more permanent array so we can reference this later
    $current_settings = $row;
    unset($row);
    
    $result = array();
    
    // first rank available to us will be the one set by the user's user level
    if ( isset($this->level_rank_table[$current_settings['user_level']]) )
    {
      $q = $this->sql('SELECT rank_id, rank_title, rank_style FROM ' . table_prefix . "ranks WHERE rank_id = {$this->level_rank_table[$this->user_level]};");
      if ( $db->numrows() > 0 )
      {
        $row = $db->fetchrow();
        $row['rank_type'] = 'ulevel';
        
        $result[] = $row;
      }
      $db->free_result();
    }
    
    // for each group the user is in, figure out if it has a rank associated with it
    $group_list = explode(',', $current_settings['group_list']);
    foreach ( $group_list as $group_id )
    {
      $group_id = intval($group_id);
      $q = $this->sql('SELECT r.rank_id, r.rank_title, r.rank_style FROM ' . table_prefix . "groups AS g\n"
                    . "  LEFT JOIN " . table_prefix . "ranks AS r\n"
                    . "    ON ( g.group_rank = r.rank_id )\n"
                    . "  WHERE g.group_id = $group_id\n"
                    . "    AND r.rank_id IS NOT NULL;");
      if ( $db->numrows() > 0 )
      {
        $row = $db->fetchrow();
        $row['rank_type'] = 'group';
        
        $result[] = $row;
      }
      $db->free_result();
    }
    
    $_cache[$id] = $result;
    return $result;
  }
  
  #
  # Access Control Lists
  #
  
  /**
   * Creates a new permission field in memory. If the permissions are set in the database, they are used. Otherwise, $default_perm is used.
   * @param string $acl_type An identifier for this field
   * @param int $default_perm Whether permission should be granted or not if it's not specified in the ACLs.
   * @param string $desc A human readable name for the permission type
   * @param array $deps The list of dependencies - this should be an array of ACL types
   * @param string $scope Which namespaces this field should apply to. This should be either a pipe-delimited list of namespace IDs or just "All".
   */
   
  function register_acl_type($acl_type, $default_perm = AUTH_DISALLOW, $desc = false, $deps = Array(), $scope = 'All')
  {
    if(isset($this->acl_types[$acl_type]))
      return false;
    else
    {
      if(!$desc)
      {
        $desc = capitalize_first_letter(str_replace('_', ' ', $acl_type));
      }
      $this->acl_types[$acl_type] = $default_perm;
      $this->acl_descs[$acl_type] = $desc;
      $this->acl_deps[$acl_type] = $deps;
      $this->acl_scope[$acl_type] = explode('|', $scope);
    }
    return true;
  }
  
  /**
   * Tells us whether permission $type is allowed or not based on the current rules.
   * @param string $type The permission identifier ($acl_type passed to sessionManager::register_acl_type())
   * @param bool $no_deps If true, disables dependency checking
   * @return bool True if allowed, false if denied or if an error occured
   */
   
  function get_permissions($type, $no_deps = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( isset( $this->perms[$type] ) )
    {
      if ( $this->perms[$type] == AUTH_DENY )
        $ret = false;
      else if ( $this->perms[$type] == AUTH_WIKIMODE && $paths->wiki_mode )
        $ret = true;
      else if ( $this->perms[$type] == AUTH_WIKIMODE && !$paths->wiki_mode )
        $ret = false;
      else if ( $this->perms[$type] == AUTH_ALLOW )
        $ret = true;
      else if ( $this->perms[$type] == AUTH_DISALLOW )
        $ret = false;
    }
    else if(isset($this->acl_types[$type]))
    {
      if ( $this->acl_types[$type] == AUTH_DENY )
        $ret = false;
      else if ( $this->acl_types[$type] == AUTH_WIKIMODE && $paths->wiki_mode )
        $ret = true;
      else if ( $this->acl_types[$type] == AUTH_WIKIMODE && !$paths->wiki_mode )
        $ret = false;
      else if ( $this->acl_types[$type] == AUTH_ALLOW )
        $ret = true;
      else if ( $this->acl_types[$type] == AUTH_DISALLOW )
        $ret = false;
    }
    else
    {
      // ACL type is undefined
      trigger_error('Unknown access type "' . $type . '"', E_USER_WARNING);
      return false; // Be on the safe side and deny access
    }
    if ( !$no_deps )
    {
      if ( !$this->acl_check_deps($type) )
        return false;
    }
    return $ret;
  }
  
  /**
   * Fetch the permissions that apply to the current user for the page specified. The object you get will have the get_permissions method
   * and several other abilities.
   * @param string $page_id
   * @param string $namespace
   * @return object
   */
   
  function fetch_page_acl($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( count ( $this->acl_base_cache ) < 1 )
    {
      // Permissions table not yet initialized
      return false;
    }
    
    // cache of permission objects (to save RAM and SQL queries)
    static $objcache = array();
    
    if ( count($objcache) == 0 )
    {
      foreach ( $paths->nslist as $key => $_ )
      {
        $objcache[$key] = array();
      }
    }
    
    if ( isset($objcache[$namespace][$page_id]) )
    {
      return $objcache[$namespace][$page_id];
    }
    
    $objcache[$namespace][$page_id] = new Session_ACLPageInfo( $page_id, $namespace, $this->acl_types, $this->acl_descs, $this->acl_deps, $this->acl_base_cache );
    $object =& $objcache[$namespace][$page_id];
    
    profiler_log("session: fetched ACLs for page {$namespace}:{$page_id}");
    
    return $object;
  }
  
  /**
   * Fetch the permissions that apply to an arbitrary user for the page specified. The object you get will have the get_permissions method
   * and several other abilities.
   * @param int|string $user_id_or_name; user ID *or* username of the user
   * @param string $page_id; if null, will be default effective permissions. 
   * @param string $namespace; if null, will be default effective permissions.
   * @return object
   */
  
  function fetch_page_acl_user($user_id_or_name, $page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // cache user info
    static $user_info_cache = null;
    
    if ( isset($user_info_cache[$user_id_or_name]) )
    {
      $user_id =& $user_info_cache[$user_id_or_name]['user_id'];
      $groups =& $user_info_cache[$user_id_or_name]['groups'];
    }
    else
    {
      $uid_column = ( is_int($user_id_or_name) ) ? "user_id = $user_id_or_name" : "username = '" . $db->escape($user_id_or_name) . "'";
      $q = $db->sql_query('SELECT u.user_id, m.group_id, g.group_name FROM ' . table_prefix . "users AS u\n"
                        . "  LEFT JOIN " . table_prefix . "group_members AS m\n"
                        . "    ON ( ( u.user_id = m.user_id AND m.pending = 0 ) OR m.member_id IS NULL )\n"
                        . "  LEFT JOIN " . table_prefix . "groups AS g\n"
                        . "    ON ( g.group_id = m.group_id )\n"
                        . "  WHERE $uid_column;");
      if ( !$q )
        $db->_die();
      
      // The l10n engine takes care of this later.
      $groups = array(1 => 'Everyone');
      
      if ( $row = $db->fetchrow() )
      {
        $user_id = intval($row['user_id']);
        if ( $row['group_id'] )
        {
          do
          {
            $groups[ intval($row['group_id'] ) ] = $row['group_name'];
          }
          while ( $row = $db->fetchrow() );
        }
        $db->free_result();
      }
      else
      {
        $db->free_result();
        throw new Exception('Unknown user ID or username');
      }
      
      $user_info_cache[$user_id_or_name] = array(
          'user_id' => $user_id,
          'groups' => $groups
        );
    }
    
    // cache base permissions
    static $base_cache = array();
    if ( !isset($base_cache[$user_id_or_name]) )
    {
      $base_cache[$user_id_or_name] = $this->acl_types;
      $current_perms =& $base_cache[$user_id_or_name];
      $current_perms['__resolve_table'] = array();
      
      $bs = 'SELECT rules, target_type, target_id, rule_id, page_id, namespace, g.group_name FROM '.table_prefix."acl AS a\n"
          . "  LEFT JOIN " . table_prefix . "groups AS g\n"
          . "    ON ( ( a.target_type = " . ACL_TYPE_GROUP . " AND a.target_id = g.group_id ) OR ( a.target_type != " . ACL_TYPE_GROUP . " ) )\n"
          . '  WHERE page_id IS NULL AND namespace IS NULL AND' . "\n"
          . '  ( ';
    
      $q = Array();
      $q[] = '( target_type='.ACL_TYPE_USER.' AND target_id= ' . $user_id . ' )';
      if(count($groups) > 0)
      {
        foreach($groups as $g_id => $g_name)
        {
          $q[] = '( target_type='.ACL_TYPE_GROUP.' AND target_id='.intval($g_id).' )';
        }
      }
      $bs .= implode(" OR \n    ", $q) . " ) \n  ORDER BY target_type ASC, target_id ASC;";
      $q = $this->sql($bs);
      foreach ( $this->acl_types as $perm_type => $_ )
      {
        // init the resolver table with blanks
        $current_perms['__resolve_table'][$perm_type] = array(
            'src' => ACL_INHERIT_ENANO_DEFAULT,
            'rule_id' => -1
          );
      }
      if ( $row = $db->fetchrow() )
      {
        do {
          $rules = $this->string_to_perm($row['rules']);
          $is_everyone = ( $row['target_type'] == ACL_TYPE_GROUP && $row['target_id'] == 1 );
          // track where these rulings are coming from
          $src = ( $is_everyone ) ? ACL_INHERIT_GLOBAL_EVERYONE : ( $row['target_type'] == ACL_TYPE_GROUP ? ACL_INHERIT_GLOBAL_GROUP : ACL_INHERIT_GLOBAL_USER );
          foreach ( $rules as $perm_type => $_ )
          {
            $current_perms['__resolve_table'][$perm_type] = array(
                'src' => $src,
                'rule_id' => $row['rule_id']
              );
            if ( $row['group_name'] )
            {
              $current_perms['__resolve_table'][$perm_type]['group_name'] = $row['group_name'];
            }
          }
          // merge it in
          $current_perms = $this->acl_merge($current_perms, $rules, $is_everyone, $_defaults_used);
        } while ( $row = $db->fetchrow() );
      }
    }
    
    // cache of permission objects (to save RAM and SQL queries)
    static $objcache = array();
    
    if ( count($objcache) == 0 )
    {
      foreach ( $paths->nslist as $key => $_ )
      {
        $objcache[$key] = array();
      }
    }
    
    if ( !isset($objcache[$namespace][$page_id]) )
    {
      $objcache[$namespace][$page_id] = array();
    }
    
    if ( isset($objcache[$namespace][$page_id][$user_id_or_name]) )
    {
      return $objcache[$namespace][$page_id][$user_id_or_name];
    }
    
    //if ( !isset( $paths->pages[$paths->nslist[$namespace] . $page_id] ) )
    //{
    //  // Page does not exist
    //  return false;
    //}
    
    $objcache[$namespace][$page_id][$user_id_or_name] = new Session_ACLPageInfo(
      $page_id,                                        // $page_id, 
      $namespace,                                      // $namespace,
      $this->acl_types,                                // $acl_types,
      $this->acl_descs,                                // $acl_descs,
      $this->acl_deps,                                 // $acl_deps,
      $base_cache[$user_id_or_name],                   // $base,
      $user_info_cache[$user_id_or_name]['user_id'],   // $user_id = null,
      $user_info_cache[$user_id_or_name]['groups'],    // $groups = null,
      $base_cache[$user_id_or_name]['__resolve_table'] // $resolve_table = array()
    );
    $object =& $objcache[$namespace][$page_id][$user_id_or_name];
    
    return $object;
  }
  
  /**
   * Checks if the given ACL rule type applies to a namespace.
   * @param string ACL rule type
   * @param string Namespace
   * @return bool
   */
  
  function check_acl_scope($acl_rule, $namespace)
  {
    if ( !isset($this->acl_scope[$acl_rule]) )
      return false;
    if ( $this->acl_scope[$acl_rule] === array('All') )
      return true;
    return ( in_array($namespace, $this->acl_scope[$acl_rule]) ) ? true : false;
  }
  
  /**
   * Read all of our permissions from the database and process/apply them. This should be called after the page is determined.
   * @access private
   */
  
  function init_permissions()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // Initialize the permissions list with some defaults
    $this->perms = $this->acl_types;
    $this->acl_defaults_used = $this->perms;
    
    // Fetch sitewide defaults from the permissions table
    $bs = 'SELECT rules, target_type, target_id FROM '.table_prefix.'acl' . "\n"
             . '  WHERE page_id IS NULL AND namespace IS NULL AND' . "\n"
             . '  ( ';
    
    $q = Array();
    $q[] = '( target_type='.ACL_TYPE_USER.' AND target_id='.$this->user_id.' )';
    if(count($this->groups) > 0)
    {
      foreach($this->groups as $g_id => $g_name)
      {
        $q[] = '( target_type='.ACL_TYPE_GROUP.' AND target_id='.intval($g_id).' )';
      }
    }
    $bs .= implode(" OR \n    ", $q) . " ) \n  ORDER BY target_type ASC, target_id ASC;";
    $q = $this->sql($bs);
    if ( $row = $db->fetchrow() )
    {
      do {
        $rules = $this->string_to_perm($row['rules']);
        $is_everyone = ( $row['target_type'] == ACL_TYPE_GROUP && $row['target_id'] == 1 );
        $this->acl_merge_with_current($rules, $is_everyone);
      } while ( $row = $db->fetchrow() );
    }
    
    // Cache the sitewide permissions for later use
    $this->acl_base_cache = $this->perms;
    
    profiler_log('session: base ACL set calculated');
    
    // Load and calculate permissions for the current page
    $page_acl = $this->fetch_page_acl($paths->page_id, $paths->namespace);
    $this->perms = $page_acl->perms;
    $this->acl_defaults_used = $page_acl->acl_defaults_used;
  }
  
  /**
   * Extends the scope of a permission type.
   * @param string The name of the permission type
   * @param string The namespace(s) that should be covered. This can be either one namespace ID or a pipe-delimited list.
   * @param object Optional - the current $paths object, in case we're doing this from the acl_rule_init hook
   */
   
  function acl_extend_scope($perm_type, $namespaces, &$p_in)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $p_obj = ( is_object($p_in) ) ? $p_in : $paths;
    $nslist = explode('|', $namespaces);
    foreach ( $nslist as $i => $ns )
    {
      if ( !isset($p_obj->nslist[$ns]) )
      {
        unset($nslist[$i]);
      }
      else
      {
        if ( $this->acl_scope[$perm_type] !== array('All') )
          $this->acl_scope[$perm_type][] = $ns;
        if ( isset($this->acl_types[$perm_type]) && !isset($this->perms[$perm_type]) )
        {
          $this->perms[$perm_type] = $this->acl_types[$perm_type];
        }
      }
    }
  }
  
  /**
   * Converts a permissions field into a string for database insertion. Similar in spirit to serialize().
   * @param array $perms An associative array with only integers as values
   * @return string
   */
   
  function perm_to_string($perms)
  {
    $s = '';
    foreach($perms as $perm => $ac)
    {
      if ( $ac == 'i' )
        continue;
      $s .= "$perm=$ac;";
    }
    return $s;
  }
  
  /**
   * Converts a permissions string back to an array.
   * @param string $perms The result from sessionManager::perm_to_string()
   * @return array
   */
   
  function string_to_perm($perms)
  {
    $ret = Array();
    preg_match_all('#([a-z0-9_-]+)=([0-9]+);#i', $perms, $matches);
    foreach($matches[1] as $i => $t)
    {
      $ret[$t] = intval($matches[2][$i]);
    }
    return $ret;
  }
  
  /**
   * Merges two ACL arrays. Both parameters should be permission list arrays. The second group takes precedence over the first, but AUTH_DENY always prevails.
   * @param array $perm1 The first set of permissions
   * @param array $perm2 The second set of permissions
   * @param bool $is_everyone If true, applies exceptions for "Everyone" group
   * @param array|reference $defaults_used Array that will be filled with default usage data
   * @return array
   */
   
  function acl_merge($perm1, $perm2, $is_everyone = false, &$defaults_used = array())
  {
    $ret = $perm1;
    if ( !is_array(@$defaults_used) )
    {
      $defaults_used = array();
    }
    foreach ( $perm1 as $i => $p )
    {
      if ( isset($perm2[$i]) )
      {
        if ( $is_everyone && isset($defaults_used[$i]) && $defaults_used[$i] === false )
          continue;
        // Decide precedence
        if ( isset($defaults_used[$i]) )
        {
          // echo "$i: default in use, overriding to: {$perm2[$i]}<br />";
          // Defaults are in use, override
          
          // CHANGED - 1.1.4:
          // For some time this has been intentionally relaxed so that the following
          // exception is available to Deny permissions:
          //   If the rule applies to the group "Everyone" on the entire site,
          //   Deny settings could be overriden.
          // This is documented at: http://docs.enanocms.org/Help:4.2
          if ( $perm1[$i] != AUTH_DENY )
          {
            $perm1[$i] = $perm2[$i];
            $defaults_used[$i] = ( $is_everyone );
          }
        }
        else
        {
          // echo "$i: default NOT in use";
          // Defaults are not in use, merge as normal
          if ( $perm1[$i] != AUTH_DENY )
          {
            // echo ", but overriding";
            $perm1[$i] = $perm2[$i];
          }
          // echo "<br />";
        }
      }
    }
    return $perm1;
  }
  
  /**
   * Merges two ACL arrays, but instead of calculating inheritance for missing permission types, just returns 'i' for that type. Useful
   * for explicitly requiring inheritance in ACL editing interfaces
   * @param array $perm1 The first set of permissions
   * @param array $perm2 The second, authoritative set of permissions
   */
  
  function acl_merge_inherit($perm1, $perm2)
  {
    foreach ( $perm1 as $type => $level )
    {
      $perm1[$type][$level] = 'i';
    }
    $ret = $perm1;
    foreach ( $perm2 as $type => $level )
    {
      if ( isset( $ret[$type] ) )
      {
        if ( $ret[$type] != AUTH_DENY )
          $ret[$type] = $level;
      }
    }
    return $ret;
  }
  
  /**
   * Merges the ACL array sent with the current permissions table, deciding precedence based on whether defaults are in effect or not.
   * @param array The array to merge into the master ACL list
   * @param bool If true, $perm is treated as the "new default"
   * @param int 1 if this is a site-wide ACL, 2 if page-specific. Defaults to 2.
   */
  
  function acl_merge_with_current($perm, $is_everyone = false, $scope = 2)
  {
    $this->perms = $this->acl_merge($this->perms, $perm, $is_everyone, $this->acl_defaults_used);
  }
  
  /**
   * Merges two ACL arrays. Both parameters should be permission list arrays. The second group takes precedence
   * over the first, without exceptions. This is used to merge the hardcoded defaults with admin-specified
   * defaults, which take precedence.
   * @param array $perm1 The first set of permissions
   * @param array $perm2 The second set of permissions
   * @return array
   */
   
  function acl_merge_complete($perm1, $perm2)
  {
    $ret = $perm1;
    foreach ( $perm2 as $type => $level )
    {
      $ret[$type] = $level;
    }
    return $ret;
  }
  
  /**
   * Tell us if the dependencies for a given permission are met.
   * @param string The ACL permission ID
   * @return bool
   */
   
  function acl_check_deps($type, $debug = false)
  {
    // This will only happen if the permissions table is hacked or improperly accessed
    if(!isset($this->acl_deps[$type]))
      return true;
    // Permission has no dependencies?
    if(sizeof($this->acl_deps[$type]) < 1)
      return true;
    // go through them all and build a flat list of dependencies
    $deps = $this->acl_deps[$type];
    while(true)
    {
      $j = sizeof($deps);
      for ( $i = 0; $i < $j; $i++ )
      {
        $b = $deps;
        $deps = array_merge($deps, $this->acl_deps[$deps[$i]]);
        if( $b == $deps )
        {
          break 2;
        }
        $j = sizeof($deps);
      }
    }
    $debugdata = array();
    foreach($deps as $d)
    {
      // Our dependencies are fully resolved, so tell get_permissions() to not recursively call this function
      if ( !$this->get_permissions($d, true) )
      {
        if ( $debug )
        {
          $debugdata[] = $d;
        }
        else
        {
          return false;
        }
      }
    }
    return $debug ? $debugdata : true;
  }
  
  /**
   * Makes a CAPTCHA code and caches the code in the database
   * @param int $len The length of the code, in bytes
   * @param string Optional, the hash to reuse
   * @return string A unique identifier assigned to the code. This hash should be passed to sessionManager::getCaptcha() to retrieve the code.
   */
  
  function make_captcha($len = 7, $hash = '')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $code = $this->generate_captcha_code($len);
    if ( !preg_match('/^[a-f0-9]{32}([a-z0-9]{8})?$/', $hash) )
      $hash = md5(microtime() . mt_rand());
    $session_data = $db->escape(serialize(array()));
    
    // sanity check
    if ( !is_valid_ip(@$_SERVER['REMOTE_ADDR']) || !is_int($this->user_id) )
      return false;
    
    $this->sql('DELETE FROM ' . table_prefix . "captcha WHERE session_id = '$hash';");
    $this->sql('INSERT INTO ' . table_prefix . 'captcha(session_id, code, session_data, source_ip, user_id)' . " VALUES('$hash', '$code', '$session_data', '{$_SERVER['REMOTE_ADDR']}', {$this->user_id});");
    return $hash;
  }
  
  /**
   * Generates a "pronouncable" or "human-friendly" word using various phonics rules
   * @param int Optional. The length of the word.
   * @return string
   */
  
  function generate_captcha_code($len = 7)
  {
    // don't use k and x, they get mixed up a lot
    $consonants = 'bcdfghmnpqrsvwyz';
    $vowels = 'aeiou';
    $prev = 'vowel';
    $prev_l = '';
    $word = '';
    $allow_next_vowel = true;
    for ( $i = 0; $i < $len; $i++ )
    {
      if ( $prev == 'vowel' )
      {
        $allow_next_vowel = false;
        if ( $prev_l == 'o' && mt_rand(0, 3) == 3 && $allow_next_vowel )
          $word .= 'i';
        else if ( $prev_l == 'q' && mt_rand(0, 3) != 1 && $allow_next_vowel )
          $word .= 'u';
        else if ( $prev_l == 'o' && mt_rand(0, 3) == 2 && $allow_next_vowel )
          $word .= 'u';
        else if ( $prev_l == 'a' && mt_rand(0, 3) == 3 && $allow_next_vowel )
          $word .= 'i';
        else if ( $prev_l == 'a' && mt_rand(0, 10) == 7 && $allow_next_vowel )
          $word .= 'o';
        else if ( $prev_l == 'a' && mt_rand(0, 7) == 2 && $allow_next_vowel )
          $word .= 'u';
        else
        {
          $allow_next_vowel = true;
          $word .= $consonants{mt_rand(0, (strlen($consonants)-1))};
        }
      }
      else if ( $prev == 'consonant' )
      {
        if ( $prev_l == 'p' && mt_rand(0, 7) == 4 )
          $word .= 't';
        else if ( $prev_l == 'p' && mt_rand(0, 5) == 1 )
          $word .= 'h';
        else
          $word .= $vowels{mt_rand(0, (strlen($vowels)-1))};
      }
      $prev_l = substr($word, -1);
      $l = ( mt_rand(0, 1) == 1 ) ? strtoupper($prev_l) : strtolower($prev_l);
      $word = substr($word, 0, -1) . $l;
      if ( strstr('aeiou', $prev_l) )
        $prev = 'vowel';
      else
        $prev = 'consonant';
    }
    return $word;
  }
  
  /**
   * For the given code ID, returns the correct CAPTCHA code, or false on failure
   * @param string $hash The unique ID assigned to the code
   * @return string The correct confirmation code
   */
  
  function get_captcha($hash)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !preg_match('/^[a-f0-9]{32}([a-z0-9]{8})?$/', $hash) )
    {
      die("session manager: bad captcha_hash $hash");
      return false;
    }
    
    // sanity check
    if ( !is_valid_ip(@$_SERVER['REMOTE_ADDR']) )
    {
      die("session manager insanity: bad REMOTE_ADDR or invalid UID");
      return false;
    }
    
    $q = $this->sql('SELECT code_id, code FROM ' . table_prefix . "captcha WHERE session_id = '$hash' AND source_ip = '{$_SERVER['REMOTE_ADDR']}';");
    if ( $db->numrows() < 1 )
    {
      return false;
    }
    
    list($code_id, $code) = $db->fetchrow_num();
    
    $db->free_result();
    $this->sql('DELETE FROM ' . table_prefix . "captcha WHERE code_id = $code_id;");
    
    return $code;
  }
  
  /**
   * (AS OF 1.0.2: Deprecated. Captcha codes are now killed on first fetch for security.) Deletes all CAPTCHA codes cached in the DB for this user.
   */
  
  function kill_captcha()
  {
    return true;
  }
  
  /**
   * Generates a random password.
   * @param int $length Optional - length of password
   * @return string
   */
   
  function random_pass($length = 10)
  {
    $valid_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_+@#%&<>';
    $valid_chars = enano_str_split($valid_chars);
    $ret = '';
    for ( $i = 0; $i < $length; $i++ )
    {
      $ret .= $valid_chars[mt_rand(0, count($valid_chars)-1)];
    }
    return $ret;
  }
  
  /**
   * Generates some Javascript that calls the AES encryption library. Put this after your </form>.
   * @param string The name of the form
   * @param string The name of the password field
   * @param string The name of the field that switches encryption on or off
   * @param string The name of the field that contains the encryption key
   * @param string The name of the field that will contain the encrypted password
   * @param string The name of the field that handles MD5 challenge data
   * @param string The name of the field that tells if the server supports DiffieHellman
   * @param string The name of the field with the DiffieHellman public key
   * @param string The name of the field that the client should populate with its public key
   * @return string
   */
   
  function aes_javascript($form_name, $pw_field, $use_crypt = 'use_crypt', $crypt_key = 'crypt_key', $crypt_data = 'crypt_data', $challenge = 'challenge_data', $dh_supported = 'dh_supported', $dh_pubkey = 'dh_public_key', $dh_client_pubkey = 'dh_client_public_key')
  {
    $code = '
      <script type="text/javascript">
          
          function runEncryption(nowhiteout)
          {
            var frm = document.forms.'.$form_name.';
            if ( !nowhiteout )
              whiteOutForm(frm);
            
            load_component(\'crypto\');
            var testpassed = ' . ( ( isset($_GET['use_crypt']) && $_GET['use_crypt']=='0') ? 'false; // CRYPTO-AUTH DISABLED ON USER REQUEST // ' : '' ) . '( aes_self_test() && md5_vm_test() );
            var use_diffiehellman = false;' . "\n";
    if ( $dh_supported && $dh_pubkey )
    {
      $code .= <<<EOF
            if ( frm.$dh_supported.value == 'true' && !is_iPhone )
              use_diffiehellman = true;
EOF;
    }
    $code .= '
    
            if ( frm[\'' . $dh_supported . '\'] )
            {
              frm[\'' . $dh_supported . '\'].value = ( use_diffiehellman ) ? "true" : "false";
            }
            
            if ( frm["' . $pw_field . '_confirm"] )
            {
              pass1 = frm.' . $pw_field . '.value;
              pass2 = frm.' . $pw_field . '_confirm.value;
              if ( pass1 != pass2 )
              {
                load_component("l10n");
                alert($lang.get("userfuncs_passreset_err_no_match"));
                return false;
              }
              if ( pass1.length < 6 )
              {
                load_component("l10n");
                alert($lang.get("userfuncs_passreset_err_too_short"));
                return false;
              }
              frm.' . $pw_field . '_confirm.value = "";
            }
            
            if ( testpassed && use_diffiehellman )
            {
              // try to blank out the table to prevent double submits and what have you
              var el = frm.' . $pw_field . ';
              while ( el.tagName != "BODY" && el.tagName != "TABLE" )
              {
                el = el.parentNode;
              }
              /*
              if ( el.tagName == "TABLE" )
              {
                whiteOutElement(el);
              }
              */
              
              frm.'.$use_crypt.'.value = \'yes_dh\';
              
              // Perform Diffie Hellman stuff
              // console.info("DiffieHellman: started keygen process");
              var dh_priv = dh_gen_private();
              var dh_pub = dh_gen_public(dh_priv);
              var secret = dh_gen_shared_secret(dh_priv, frm.' . $dh_pubkey . '.value);
              // console.info("DiffieHellman: finished keygen process");
              
              // secret_hash is used to verify that the server guesses the correct secret
              var secret_hash = hex_sha1(secret);
              
              // give the server our values
              frm.' . $crypt_key . '.value = secret_hash;
              ' . ( $dh_supported ? 'frm.' . $dh_client_pubkey . '.value = dh_pub;' : '' ) . '
              
              // console.info("DiffieHellman: set public values");
              
              // crypt_key is the actual AES key
              var crypt_key = (hex_sha256(secret)).substr(0, (keySizeInBits / 4));
              
              // Perform encryption
              crypt_key = hexToByteArray(crypt_key);
              var pass = frm.'.$pw_field.'.value;
              pass = stringToByteArray(pass);
              var cryptstring = rijndaelEncrypt(pass, crypt_key, \'ECB\');
              if(!cryptstring)
              {
                return false;
              }
              cryptstring = byteArrayToHex(cryptstring);
              // console.info("DiffieHellman: finished AES");
              frm.'.$crypt_data.'.value = cryptstring;
              frm.'.$pw_field.'.value = \'\';
              // console.info("DiffieHellman: ready to submit");
            }
            else if ( testpassed && !use_diffiehellman )
            {
              frm.'.$use_crypt.'.value = \'yes\';
              var cryptkey = frm.'.$crypt_key.'.value;
              frm.'.$crypt_key.'.value = hex_md5(cryptkey);
              cryptkey = hexToByteArray(cryptkey);
              if(!cryptkey || ( ( typeof cryptkey == \'string\' || typeof cryptkey == \'object\' ) ) && cryptkey.length != keySizeInBits / 8 )
              {
                if ( frm._login ) frm._login.disabled = true;
                len = ( typeof cryptkey == \'string\' || typeof cryptkey == \'object\' ) ? \'\\nLen: \'+cryptkey.length : \'\';
                alert(\'The key is messed up\\nType: \'+typeof(cryptkey)+len);
              }
              pass = frm.'.$pw_field.'.value;
              chal = frm.'.$challenge.'.value;
              challenge = hex_md5(pass + chal) + chal;
              frm.'.$challenge.'.value = challenge;
              pass = stringToByteArray(pass);
              cryptstring = rijndaelEncrypt(pass, cryptkey, \'ECB\');
              if(!cryptstring)
              {
                return false;
              }
              cryptstring = byteArrayToHex(cryptstring);
              frm.'.$crypt_data.'.value = cryptstring;
              frm.'.$pw_field.'.value = \'\';
            }
          }
        </script>
        ';
    return $code;
  }
  
  /**
   * Generates the HTML form elements required for an encrypted logon experience.
   * @return string
   */
  
  function generate_aes_form()
  {
    $return = '<input type="hidden" name="use_crypt" value="no" />';
    $return .= '<input type="hidden" name="crypt_key" value="' . $this->rijndael_genkey() . '" />';
    $return .= '<input type="hidden" name="crypt_data" value="" />';
    $return .= '<input type="hidden" name="challenge_data" value="' . $this->dss_rand() . '" />';
    
    require_once(ENANO_ROOT . '/includes/math.php');
    require_once(ENANO_ROOT . '/includes/diffiehellman.php');
    
    global $dh_supported, $_math;
    if ( $dh_supported )
    {
      $dh_key_priv = dh_gen_private();
      $dh_key_pub = dh_gen_public($dh_key_priv);
      $dh_key_priv = $_math->str($dh_key_priv);
      $dh_key_pub = $_math->str($dh_key_pub);
      // store the keys in the DB
      $this->sql('INSERT INTO ' . table_prefix . "diffiehellman( public_key, private_key ) VALUES ( '$dh_key_pub', '$dh_key_priv' );");
      
      $return .=  "<input type=\"hidden\" name=\"dh_supported\" value=\"true\" />
            <input type=\"hidden\" name=\"dh_public_key\" value=\"$dh_key_pub\" />
            <input type=\"hidden\" name=\"dh_client_public_key\" value=\"\" />";
    }
    else
    {
      $return .=  "<input type=\"hidden\" name=\"dh_supported\" value=\"false\" />";
    }
    return $return;
  }
  
  /**
   * If you used all the same form fields as the normal login interface, this will take care of DiffieHellman for you and return the key.
   * @param string Password field name (defaults to "password")
   * @return string
   */
  
  function get_aes_post($fieldname = 'password')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    if ( $_POST['use_crypt'] == 'yes' )
    {
      $crypt_key = $this->fetch_public_key($_POST['crypt_key']);
      if ( !$crypt_key )
      {
        throw new Exception($lang->get('user_err_key_not_found'));
      }
      $crypt_key = hexdecode($crypt_key);
      $data = $aes->decrypt($_POST['crypt_data'], $crypt_key, ENC_HEX);
    }
    else if ( $_POST['use_crypt'] == 'yes_dh' )
    {
      require_once(ENANO_ROOT . '/includes/math.php');
      require_once(ENANO_ROOT . '/includes/diffiehellman.php');
      
      global $dh_supported, $_math;
        
      if ( !$dh_supported )
      {
        throw new Exception('Server does not support DiffieHellman, denying request');
      }
      
      // Fetch private key
      $dh_public = $_POST['dh_public_key'];
      if ( !ctype_digit($dh_public) )
      {
        throw new Exception('ERR_DH_KEY_NOT_INTEGER');
      }
      $q = $db->sql_query('SELECT private_key, key_id FROM ' . table_prefix . "diffiehellman WHERE public_key = '$dh_public';");
      if ( !$q )
        $db->die_json();
      
      if ( $db->numrows() < 1 )
      {
        throw new Exception('ERR_DH_KEY_NOT_FOUND');
      }
      
      list($dh_private, $dh_key_id) = $db->fetchrow_num();
      $db->free_result();
      
      // We have the private key, now delete the key pair, we no longer need it
      $q = $db->sql_query('DELETE FROM ' . table_prefix . "diffiehellman WHERE key_id = $dh_key_id;");
      if ( !$q )
        $db->die_json();
      
      // Generate the shared secret
      $dh_secret = dh_gen_shared_secret($dh_private, $_POST['dh_client_public_key']);
      $dh_secret = $_math->str($dh_secret);
      
      // Did we get all our math right?
      $dh_secret_check = sha1($dh_secret);
      $dh_hash = $_POST['crypt_key'];
      if ( $dh_secret_check !== $dh_hash )
      {
        throw new Exception('ERR_DH_HASH_NO_MATCH');
      }
      
      // All good! Generate the AES key
      $aes_key = substr(sha256($dh_secret), 0, ( AES_BITS / 4 ));
      
      // decrypt user info
      $aes_key = hexdecode($aes_key);
      $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
      $data = $aes->decrypt($_POST['crypt_data'], $aes_key, ENC_HEX);
    }
    else
    {
      $data = $_POST[$fieldname];
    }
    return $data;
  }
  
  /**
   * Backend code for the JSON login interface. Basically a frontend to the session API that takes all parameters in one huge array.
   * @param array LoginAPI request
   * @return array LoginAPI response
   */
  
  function process_login_request($req, $_dbgtmp = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Setup EnanoMath and Diffie-Hellman
    global $dh_supported;
    if ( !function_exists('dh_gen_private') )
    {
      require_once(ENANO_ROOT.'/includes/math.php');
      
      $dh_supported = true;
      try
      {
        require_once(ENANO_ROOT . '/includes/diffiehellman.php');
      }
      catch ( Exception $e )
      {
        $dh_supported = false;
      }
    }
    global $_math;
    
    // Check for the mode
    if ( !isset($req['mode']) )
    {
      return array(
          'mode' => 'error',
          'error' => 'ERR_JSON_NO_MODE'
        );
    }
    
    // Main processing switch
    switch ( $req['mode'] )
    {
      default:
        return array(
            'mode' => 'error',
            'error' => 'ERR_JSON_INVALID_MODE'
          );
        break;
      case 'getkey':
        
        $this->start();
        
        $locked_out = $this->get_lockout_info($lockdata);
        
        $response = array('mode' => 'build_box');
        $response['allow_diffiehellman'] = $dh_supported;
        
        $response['username'] = ( $this->user_logged_in ) ? $this->username : false;
        $response['aes_key'] = $this->rijndael_genkey();
        
        $response['extended_time'] = intval(getConfig('session_remember_time', '30'));
        
        // Lockout info
        $response['locked_out'] = $locked_out;
        
        $response['lockout_info'] = $lockdata;
        if ( $lockdata['lockout_policy'] == 'captcha' && $locked_out )
        {
          $response['lockout_info']['captcha'] = $this->make_captcha();
        }
        
        // Can we do Diffie-Hellman? If so, generate and stash a public/private key pair.
        if ( $dh_supported )
        {
          $dh_key_priv = dh_gen_private();
          $dh_key_pub = dh_gen_public($dh_key_priv);
          $dh_key_priv = $_math->str($dh_key_priv);
          $dh_key_pub = $_math->str($dh_key_pub);
          $response['dh_public_key'] = $dh_key_pub;
          // store the keys in the DB
          $q = $db->sql_query('INSERT INTO ' . table_prefix . "diffiehellman( public_key, private_key ) VALUES ( '$dh_key_pub', '$dh_key_priv' );");
          if ( !$q )
            $db->die_json();
        }
        
        return $response;
        break;
      case 'login_dh':
        // User is requesting a login and has sent Diffie-Hellman data.
        
        //
        // KEY RECONSTRUCTION
        //
        
        $userinfo_crypt = $req['userinfo'];
        $dh_public = $req['dh_public_key'];
        $dh_hash = $req['dh_secret_hash'];
        
        // Check the key
        if ( !ctype_digit($dh_public) || !ctype_digit($req['dh_client_key']) )
        {
          return array(
            'mode' => 'error',
            'error' => 'ERR_DH_KEY_NOT_NUMERIC'
          );
        }
        
        // Fetch private key
        $q = $db->sql_query('SELECT private_key, key_id FROM ' . table_prefix . "diffiehellman WHERE public_key = '$dh_public';");
        if ( !$q )
          $db->die_json();
        
        if ( $db->numrows() < 1 )
        {
          return array(
            'mode' => 'error',
            'error' => 'ERR_DH_KEY_NOT_FOUND'
          );
        }
        
        list($dh_private, $dh_key_id) = $db->fetchrow_num();
        $db->free_result();
        
        // We have the private key, now delete the key pair, we no longer need it
        $q = $db->sql_query('DELETE FROM ' . table_prefix . "diffiehellman WHERE key_id = $dh_key_id;");
        if ( !$q )
          $db->die_json();
        
        // Generate the shared secret
        $dh_secret = dh_gen_shared_secret($dh_private, $req['dh_client_key']);
        $dh_secret = $_math->str($dh_secret);
        
        // Did we get all our math right?
        $dh_secret_check = sha1($dh_secret);
        if ( $dh_secret_check !== $dh_hash )
        {
          return array(
            'mode' => 'error',
            'error' => 'ERR_DH_HASH_NO_MATCH',
          );
        }
        
        // All good! Generate the AES key
        $aes_key = substr(sha256($dh_secret), 0, ( AES_BITS / 4 ));
      case 'login_aes':
        if ( $req['mode'] == 'login_aes' )
        {
          // login_aes-specific code
          $aes_key = $this->fetch_public_key($req['key_aes']);
          if ( !$aes_key )
          {
            return array(
              'mode' => 'error',
              'error' => 'ERR_AES_LOOKUP_FAILED'
            );
          }
          $userinfo_crypt = $req['userinfo'];
        }
        // shared between the two systems from here on out
        
        // decrypt user info
        $aes_key = hexdecode($aes_key);
        $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
        // using "true" here disables caching of the decrypted login info (which includes the password)
        $userinfo_json = $aes->decrypt($userinfo_crypt, $aes_key, ENC_HEX, true);
        if ( !$userinfo_json )
        {
          return array(
            'mode' => 'error',
            'error' => 'ERR_AES_DECRYPT_FAILED'
          );
        }
        // de-JSON user info
        try
        {
          $userinfo = enano_json_decode($userinfo_json);
        }
        catch ( Exception $e )
        {
          return array(
            'mode' => 'error',
            'error' => 'ERR_USERINFO_DECODE_FAILED'
          );
        }
        
        if ( !isset($userinfo['username']) || !isset($userinfo['password']) )
        {
          return array(
            'mode' => 'error',
            'error' => 'ERR_USERINFO_MISSING_VALUES'
          );
        }
        
        $username =& $userinfo['username'];
        $password =& $userinfo['password'];
        
        // At this point if any extra info was injected into the login data packet, we need to let plugins process it
        /**
         * Called upon processing an incoming login request. If you added anything to the userinfo object during the jshook
         * login_build_userinfo, that will be in the $userinfo array here. Expected return values are: true if your plugin has
         * not only succeeded but ALSO issued a session key (bypass the whole Enano builtin login process) and an associative array
         * with "mode" set to "error" and an error string in "error" to send an error back to the client. Any return value other
         * than these will be treated as a pass-through, and the user's password will be validated through Enano's standard process.
         * @hook login_process_userdata_json
         */
        
        $code = $plugins->setHook('login_process_userdata_json', true);
        foreach ( $code as $cmd )
        {
          $result = eval($cmd);
          if ( $result === true )
          {
            return array(
                'mode' => 'login_success',
                'key' => ( $this->sid_super ) ? $this->sid_super : false,
                'user_id' => $this->user_id,
                'user_level' => $this->user_level
              );
          }
          else if ( is_array($result) )
          {
            if ( isset($result['mode']) && $result['mode'] === 'error' && isset($result['error']) )
            {
              // Pass back any additional information from the error response
              $append = $result;
              unset($append['mode'], $append['error']);
              
              $return = array(
                'mode' => 'login_failure',
                'error_code' => $result['error'],
                // Use this to provide a way to respawn the login box
                'respawn_info' => $this->process_login_request(array('mode' => 'getkey'))
              );
              
              $return = array_merge($append, $return);
              return $return;
            }
          }
        }
        
        // If we're logging in with a temp password, attach to the login_password_reset hook to send our JSON response
        // A bit hackish since it just dies with the response :-(
        $plugins->attachHook('login_password_reset', '$this->process_login_request(array(\'mode\' => \'respond_password_reset\', \'user_id\' => $row[\'user_id\'], \'temp_password\' => $this->pk_encrypt($password)));');
        
        // attempt the login
        // function login_without_crypto($username, $password, $already_md5ed = false, $level = USER_LEVEL_MEMBER, $captcha_hash = false, $captcha_code = false)
        $login_result = $this->login_without_crypto($username, $password, false, intval($req['level']), @$req['captcha_hash'], @$req['captcha_code'], @$req['remember']);
        
        if ( $login_result['success'] )
        {
          return array(
              'mode' => 'login_success',
              'key' => ( $this->sid_super ) ? $this->sid_super : false,
                'user_id' => $this->user_id,
                'user_level' => $this->user_level
            );
        }
        else
        {
          return array(
              'mode' => 'login_failure',
              'error_code' => $login_result['error'],
              // Use this to provide a way to respawn the login box
              'respawn_info' => $this->process_login_request(array('mode' => 'getkey'))
            );
        }
        
        break;
      case 'clean_key':
        // Clean out a key, since it won't be used.
        // This is called when the user clicks Cancel in the AJAX login interface.
        if ( !empty($req['key_aes']) )
        {
          $this->fetch_public_key($req['key_aes']);
        }
        if ( !empty($req['key_dh']) )
        {
          $pk = $db->escape($req['key_dh']);
          $q = $db->sql_query('DELETE FROM ' . table_prefix . "diffiehellman WHERE public_key = '$pk';");
          if ( !$q )
            $db->die_json();
        }
        return array(
            'mode' => 'noop'
          );
        break;
      case 'respond_password_reset':
        die(enano_json_encode(array(
            'mode' => 'login_success_reset',
            'user_id' => $req['user_id'],
            'temp_password' => $req['temp_password'],
            'respawn_info' => $this->process_login_request(array('mode' => 'getkey'))
          )));
        break;
      case 'logout':
        if ( !$this->started )
          $this->start();
        if ( !isset($req['csrf_token']) )
          return array(
              'mode' => 'error',
              'error' => 'Invalid CSRF token'
            );
        
        if ( $req['csrf_token'] !== $this->csrf_token )
          return array(
              'mode' => 'error',
              'error' => 'Invalid CSRF token'
            );
        $level = isset($req['level']) && is_int($req['level']) ? $req['level'] : USER_LEVEL_MEMBER;
        if ( ($result = $this->logout($level)) === 'success' )
        {
          return array(
            'mode' => 'logout_success'
          );
        }
        else
        {
          return array(
            'mode' => 'error',
            'error' => $result
          );
        }
        break;
    }
    
  }
  
}

/**
 * Class used to fetch permissions for a specific page. Used internally by SessionManager.
 * @package Enano
 * @subpackage Session manager
 * @license http://www.gnu.org/copyleft/gpl.html
 * @access private
 */
 
class Session_ACLPageInfo {
  
  /**
   * The page ID of this ACL info package
   * @var string
   */
   
  var $page_id;
  
  /**
   * The namespace of the page being checked
   * @var string
   */
   
  var $namespace;
  
  /**
   * Our list of permission types.
   * @access private
   * @var array
   */
   
  var $acl_types = Array();
  
  /**
   * The list of descriptions for the permission types
   * @var array
   */
   
  var $acl_descs = Array();
  
  /**
   * A list of dependencies for ACL types.
   * @var array
   */
   
  var $acl_deps = Array();
  
  /**
   * Our tell-all list of permissions. Do not even try to change this.
   * @access private
   * @var array
   */
   
  var $perms = Array();
  
  /**
   * Array to track which default permissions are being used
   * @var array
   * @access private
   */
   
  var $acl_defaults_used = Array();
  
  /**
   * Tracks whether Wiki Mode is on for the page we're operating on.
   * @var bool
   */
  
  var $wiki_mode = false;
  
  /**
   * Tracks where permissions were calculated using the ACL_INHERIT_* constants. Layout:
   * array(
   *   [permission_name] => array(
   *       [src] => ACL_INHERIT_*
   *       [rule_id] => integer
   *     ),
   *   ...
   * )
   *
   * @var array
   */
  
  var $perm_resolve_table = array();
  
  #
  # USER PARAMETERS
  #
  
  /**
   * User ID
   * @var int
   */
  
  var $user_id = 1;
  
  /**
   * Group membership associative array (group_id => group_name)
   * @var array
   */
  
  var $groups = array();
  
  /**
   * Constructor.
   * @param string $page_id The ID of the page to check
   * @param string $namespace The namespace of the page to check.
   * @param array $acl_types List of ACL types
   * @param array $acl_descs List of human-readable descriptions for permissions (associative)
   * @param array $acl_deps List of dependencies for permissions. For example, viewing history/diffs depends on the ability to read the page.
   * @param array $base What to start with - this is an attempt to reduce the number of SQL queries.
   * @param int|string $user_id_or_name Username or ID to search for, defaults to current user
   * @param array $resolve_table Debugging info for tracking where rules came from, defaults to a blank array.
   */
   
  function __construct($page_id, $namespace, $acl_types, $acl_descs, $acl_deps, $base, $user_id = null, $groups = null, $resolve_table = array())
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // hack
    if ( isset($base['__resolve_table']) )
    {
      unset($base['__resolve_table']);
    }
    
    foreach ( $acl_types as $perm_type => $_ )
    {
      if ( !$session->check_acl_scope($perm_type, $namespace) )
      {
        unset($acl_types[$perm_type]);
        unset($acl_deps[$perm_type]);
        unset($acl_descs[$perm_type]);
        unset($base[$perm_type]);
      }
    }
    
    $this->acl_deps = $acl_deps;
    $this->acl_types = $acl_types;
    $this->acl_descs = $acl_descs;
    
    $this->perms = $acl_types;
    $this->perms = $session->acl_merge_complete($this->perms, $base);
    
    $this->perm_resolve_table = array();
    if ( is_array($resolve_table) )
      $this->perm_resolve_table = $resolve_table;
    
    if ( is_int($user_id) && is_array($groups) )
    {
      $this->user_id = $user_id;
      $this->groups = $groups;
    }
    else
    {
      $this->user_id = $session->user_id;
      $this->groups = $session->groups;
    }
    
    $this->page_id = $page_id;
    $this->namespace = $namespace;
    
    $this->__calculate();
  }
  
  /**
   * Performs the actual permission calculation.
   * @access private
   */
  
  private function __calculate()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $page_id =& $this->page_id;
    $namespace =& $this->namespace;
    
    // PAGE group info
    $pg_list = $paths->get_page_groups($page_id, $namespace);
    $pg_info = '';
    foreach ( $pg_list as $g_id )
    {
      $pg_info .= ' ( page_id=\'' . $g_id . '\' AND namespace=\'__PageGroup\' ) OR';
    }
    
    // Build a query to grab ACL info
    $bs = 'SELECT rules,target_type,target_id,page_id,namespace,rule_id,pg.pg_name,g.group_name FROM '.table_prefix."acl AS a\n"
        . "  LEFT JOIN " . table_prefix . "page_groups AS pg\n"
        . "    ON ( ( a.page_id = CAST(pg.pg_id AS char) AND a.namespace = '__PageGroup' ) OR ( a.namespace != '__PageGroup' ) )\n"
        . "  LEFT JOIN " . table_prefix . "groups AS g\n"
        . "    ON ( ( a.target_type = " . ACL_TYPE_GROUP . " AND a.target_id = g.group_id ) OR ( a.target_type != " . ACL_TYPE_GROUP . " ) )\n";
    
    $bs .= '  WHERE ' . "\n"
          . '  ( ';
    $q = Array();
    $q[] = '( target_type='.ACL_TYPE_USER.' AND target_id='.$this->user_id.' )';
    if(count($this->groups) > 0)
    {
      foreach($this->groups as $g_id => $g_name)
      {
        $q[] = '( target_type='.ACL_TYPE_GROUP.' AND target_id='.intval($g_id).' )';
      }
    }
    // The reason we're using an ORDER BY statement here is because ACL_TYPE_GROUP is less than ACL_TYPE_USER, causing the user's individual
    // permissions to override group permissions.
    $bs .= implode(" OR\n    ", $q) . ' ) AND (' . $pg_info . ' ( page_id=\''.$db->escape($page_id).'\' AND namespace=\''.$db->escape($namespace).'\' ) )     
      ORDER BY target_type ASC, page_id ASC, namespace ASC;';
      
    $q = $session->sql($bs);
    if ( $row = $db->fetchrow() )
    {
      do {
        $rules = $session->string_to_perm($row['rules']);
        $is_everyone = ( $row['target_type'] == ACL_TYPE_GROUP && $row['target_id'] == 1 );
        // log where this comes from
        if ( $row['namespace'] == '__PageGroup' )
        {
          $src = ( $is_everyone ) ? ACL_INHERIT_PG_EVERYONE : ( $row['target_type'] == ACL_TYPE_GROUP ? ACL_INHERIT_PG_GROUP : ACL_INHERIT_PG_USER );
          $pg_name = $row['pg_name'];
        }
        else
        {
          $src = ( $is_everyone ) ? ACL_INHERIT_LOCAL_EVERYONE : ( $row['target_type'] == ACL_TYPE_GROUP ? ACL_INHERIT_LOCAL_GROUP : ACL_INHERIT_LOCAL_USER );
        }
        if ( $row['group_name'] )
        {
          $group_name = $row['group_name'];
        }
        foreach ( $rules as $perm_type => $perm_value )
        {
          if ( !isset($this->perms[$perm_type]) )
            continue;
          
          if ( $this->perms[$perm_type] == AUTH_DENY )
            continue;
          
          if ( !$session->check_acl_scope($perm_type, $this->namespace) )
            continue;
          
          $this->perm_resolve_table[$perm_type] = array(
              'src' => $src,
              'rule_id' => $row['rule_id']
            );
          if ( isset($pg_name) )
          {
            $this->perm_resolve_table[$perm_type]['pg_name'] = $pg_name;
          }
          if ( isset($group_name) )
          {
            $this->perm_resolve_table[$perm_type]['group_name'] = $group_name;
          }
        }
        $this->acl_merge_with_current($rules, $is_everyone);
      } while ( $row = $db->fetchrow() );
    }
    
    $this->page_id = $page_id;
    $this->namespace = $namespace;
    
    $pathskey = $paths->nslist[$this->namespace].sanitize_page_id($this->page_id);
    $ns = namespace_factory($this->page_id, $this->namespace);
    $cdata = $ns->get_cdata();
    $ppwm = $cdata['wiki_mode'];
    unset($ns, $cdata);
    
    if ( $ppwm == 1 && ( $session->user_logged_in || getConfig('wiki_mode_require_login') != '1' ) )
      $this->wiki_mode = true;
    else if ( $ppwm == 1 && !$session->user_logged_in && getConfig('wiki_mode_require_login') == '1' )
      $this->wiki_mode = true;
    else if ( $ppwm == 0 )
      $this->wiki_mode = false;
    else if ( $ppwm == 2 )
    {
      if ( $this->user_id > 1 )
      {
        $this->wiki_mode = ( getConfig('wiki_mode') == '1' );
      }
      else
      {
        $this->wiki_mode = ( getConfig('wiki_mode') == '1' && getConfig('wiki_mode_require_login') != '1' );
      }
    }
    else
    {
      // Ech. Internal logic failure, this should never happen.
      return false;
    }
  }
  
  /**
   * Tells us whether permission $type is allowed or not based on the current rules.
   * @param string $type The permission identifier ($acl_type passed to sessionManager::register_acl_type())
   * @param bool $no_deps If true, disables dependency checking
   * @return bool True if allowed, false if denied or if an error occured
   */
   
  function get_permissions($type, $no_deps = false)
  {
    // echo '<pre>' . print_r($this->perms, true) . '</pre>';
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( isset( $this->perms[$type] ) )
    {
      if ( $this->perms[$type] == AUTH_DENY )
      {
        $ret = false;
      }
      else if ( $this->perms[$type] == AUTH_WIKIMODE && $this->wiki_mode )
      {
        $ret = true;
      }
      else if ( $this->perms[$type] == AUTH_WIKIMODE && !$this->wiki_mode )
      {
        $ret = false;
      }
      else if ( $this->perms[$type] == AUTH_ALLOW )
      {
        $ret = true;
      }
      else if ( $this->perms[$type] == AUTH_DISALLOW )
      {
        $ret = false;
      }
    }
    else if(isset($this->acl_types[$type]))
    {
      if ( $this->acl_types[$type] == AUTH_DENY )
        $ret = false;
      else if ( $this->acl_types[$type] == AUTH_WIKIMODE && $paths->wiki_mode )
        $ret = true;
      else if ( $this->acl_types[$type] == AUTH_WIKIMODE && !$paths->wiki_mode )
        $ret = false;
      else if ( $this->acl_types[$type] == AUTH_ALLOW )
        $ret = true;
      else if ( $this->acl_types[$type] == AUTH_DISALLOW )
        $ret = false;
    }
    else
    {
      // ACL type is undefined
      $caller = 'unknown';
      if ( function_exists('debug_backtrace') )
      {
        if ( $bt = @debug_backtrace() )
        {
          foreach ( $bt as $trace )
          {
            $file = basename($trace['file']);
            if ( $file != 'sessions.php' )
            {
              $caller = $file . ':' . $trace['line'];
              break;
            }
          }
        }
      }
      trigger_error('Unknown access type "' . $type . '", called from ' . $caller . '', E_USER_WARNING);
      return false; // Be on the safe side and deny access
    }
    if ( !$no_deps )
    {
      if ( !$this->acl_check_deps($type) )
        return false;
    }
    return $ret;
  }
  
  /**
   * Tell us if the dependencies for a given permission are met.
   * @param string The ACL permission ID
   * @param bool If true, does not return a boolean value, but instead returns array of dependencies that fail
   * @return bool
   */
   
  function acl_check_deps($type, $debug = false)
  {
    // This will only happen if the permissions table is hacked or improperly accessed
    if(!isset($this->acl_deps[$type]))
      return $debug ? array() : true;
    // Permission has no dependencies?
    if(sizeof($this->acl_deps[$type]) < 1)
      return $debug ? array() : true;
    // go through them all and build a flat list of dependencies
    $deps = $this->acl_deps[$type];
    while(true)
    {
      $j = sizeof($deps);
      for ( $i = 0; $i < $j; $i++ )
      {
        $b = $deps;
        $deps = array_merge($deps, $this->acl_deps[$deps[$i]]);
        if( $b == $deps )
        {
          break 2;
        }
        $j = sizeof($deps);
      }
    }
    $debugdata = array();
    foreach($deps as $d)
    {
      // Our dependencies are fully resolved, so tell get_permissions() to not recursively call this function
      if ( !$this->get_permissions($d, true) )
      {
        if ( $debug )
        {
          $debugdata[] = $d;
        }
        else
        {
          return false;
        }
      }
    }
    return $debug ? $debugdata : true;
  }
  
  /**
   * Merges the ACL array sent with the current permissions table, deciding precedence based on whether defaults are in effect or not.
   * @param array The array to merge into the master ACL list
   * @param bool If true, $perm is treated as the "new default"
   * @param int 1 if this is a site-wide ACL, 2 if page-specific. Defaults to 2.
   */
  
  function acl_merge_with_current($perm, $is_everyone = false, $scope = 2)
  {
    foreach ( $this->perms as $i => $p )
    {
      if ( isset($perm[$i]) )
      {
        if ( $is_everyone && !@$this->acl_defaults_used[$i] )
          continue;
        // Decide precedence
        if ( isset($this->acl_defaults_used[$i]) )
        {
          //echo "$i: default in use, overriding to: {$perm[$i]}<br />";
          // Defaults are in use, override
          $this->perms[$i] = $perm[$i];
          $this->acl_defaults_used[$i] = ( $is_everyone );
        }
        else
        {
          //echo "$i: default NOT in use";
          // Defaults are not in use, merge as normal
          if ( $this->perms[$i] != AUTH_DENY )
          {
            //echo ", but overriding";
            $this->perms[$i] = $perm[$i];
          }
          //echo "<br />";
        }
      }
    }
  }
  
}

/**
 * Cron task - clears out the database of Diffie-Hellman keys
 */

function cron_clean_login_cache()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !$db->sql_query('DELETE FROM ' . table_prefix . 'diffiehellman;') )
    $db->_die();
  
  setConfig('login_key_cache', '');
}

register_cron_task('cron_clean_login_cache', 72);

/**
 * Cron task - clears out outdated high-auth session keys
 */

function cron_clean_old_admin_keys()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $threshold = time() - ( 15 * 60 );
  $ul_member = USER_LEVEL_MEMBER;
  if ( !$db->sql_query('DELETE FROM ' . table_prefix . "session_keys WHERE time < $threshold AND auth_level > $ul_member;") )
    $db->_die();
}

// Once a week
register_cron_task('cron_clean_old_admin_keys', 168);

/**
 * Cron task - regenerate cached user rank information
 */

register_cron_task('generate_cache_userranks', 0.25);

?>

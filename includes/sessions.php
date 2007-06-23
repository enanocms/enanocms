<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 3 (Druid)
 * Copyright (C) 2006-2007 Dan Fuhry
 * sessions.php - everything related to security and user management
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
// Prepare a string for insertion into a MySQL database
function filter($str) { return $db->escape($str); }

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
   * User ID of currently logged-in user, or -1 if not logged in
   * @var int
   */
  
  var $user_id;
  
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
   * MD5 hash of the current user's password, if applicable
   * @var string OR bool false
   */
   
  var $password_hash;
  
  /**
   * The number of unread private messages this user has.
   * @var int
   */
  
  var $unread_pms = 0;
  
  /**
   * AES key used to encrypt passwords and session key info - irreversibly destroyed when disallow_password_grab() is called
   * @var string
   */
   
  var $private_key;
  
  /**
   * Regex that defines a valid username, minus the ^ and $, these are added later
   * @var string
   */
   
   var $valid_username = '([A-Za-z0-9 \!\@\(\)-]+)';
   
  /**
   * What we're allowed to do as far as permissions go. This changes based on the value of the "auth" URI param.
   * @var string
   */
   
  var $auth_level = -1;
  
  /**
   * State variable to track if a session timed out
   * @var bool
   */
  
  var $sw_timed_out = false;
  
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
   * Our tell-all list of permissions.
   * @access private - or, preferably, protected
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
  
  # Basic functions
   
  /**
   * Constructor.
   */
   
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    include(ENANO_ROOT.'/config.php');
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
        $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
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
   * PHP 4 compatible constructor.
   */
   
  function sessionManager()
  {
    $this->__construct();
  }
  
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
    if($this->started) return;
    $this->started = true;
    $user = false;
    if(isset($_COOKIE['sid']))
    {
      if($this->compat)
      {
        $userdata = $this->compat_validate_session($_COOKIE['sid']);
      }
      else
      {
        $userdata = $this->validate_session($_COOKIE['sid']);
      }
      if(is_array($userdata))
      {
        $data = RenderMan::strToPageID($paths->get_pageid_from_url());
        
        if(!$this->compat && $userdata['account_active'] != 1 && $data[1] != 'Special' && $data[1] != 'Admin')
        {
          $this->logout();
          $a = getConfig('account_activation');
          switch($a)
          {
            case 'none':
            default:
              $solution = 'Your account was most likely deactivated by an administrator. Please contact the site administration for further assistance.';
              break;
            case 'user':
              $solution = 'Please check your e-mail; you should have been sent a message with instructions on how to activate your account. If you do not receive an e-mail from this site within 24 hours, please contact the site administration for further assistance.';
              break;
            case 'admin':
              $solution = 'This website has been configured so that all user accounts must be activated by the administrator before they can be used, so your account will most likely be activated the next time the one of the administrators visits the site.';
              break;
          }
          die_semicritical('Account error', '<p>It appears that your user account has not yet been activated. '.$solution.'</p>');
        }
        
        $this->sid = $_COOKIE['sid'];
        $this->user_logged_in = true;
        $this->user_id =       intval($userdata['user_id']);
        $this->username =      $userdata['username'];
        $this->password_hash = $userdata['password'];
        $this->user_level =    intval($userdata['user_level']);
        $this->real_name =     $userdata['real_name'];
        $this->email =         $userdata['email'];
        $this->unread_pms =    $userdata['num_pms'];
        if(!$this->compat)
        {
          $this->theme =         $userdata['theme'];
          $this->style =         $userdata['style'];
          $this->signature =     $userdata['signature'];
          $this->reg_time =      $userdata['reg_time'];
        }
        // Small security risk here - it allows someone who has already authenticated as an administrator to store the "super" key in
        // the cookie. Change this to USER_LEVEL_MEMBER to override that. The same 15-minute restriction applies to this "exploit".
        $this->auth_level =    $userdata['auth_level'];
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
            $key = strrev($_REQUEST['auth']);
            $super = $this->validate_session($key);
          }
          if(is_array($super))
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
      $q = $this->sql('SELECT g.group_name,g.group_id,m.is_mod FROM '.table_prefix.'groups AS g
          LEFT JOIN '.table_prefix.'group_members AS m
            ON g.group_id=m.group_id
          WHERE ( m.user_id='.$this->user_id.' 
            OR g.group_name=\'Everyone\')
            ' . ( enano_version() == '1.0RC1' ? '' : 'AND ( m.pending != 1 OR m.pending IS NULL )' ) . '
          ORDER BY group_id ASC;'); // Make sure "Everyone" comes first so the permissions can be overridden
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
    }
    $this->check_banlist();
    
    if ( isset ( $_GET['printable'] ) )
    {
      $this->theme = 'printable';
      $this->style = 'default';
    }
    
  }
  
  # Logins
  
  /**
   * Attempts to perform a login using crypto functions
   * @param string $username The username
   * @param string $aes_data The encrypted password, hex-encoded
   * @param string $aes_key The MD5 hash of the encryption key, hex-encoded
   * @param string $challenge The 256-bit MD5 challenge string - first 128 bits should be the hash, the last 128 should be the challenge salt
   * @param int $level The privilege level we're authenticating for, defaults to 0
   * @return string 'success' on success, or error string on failure
   */
   
  function login_with_crypto($username, $aes_data, $aes_key, $challenge, $level = USER_LEVEL_MEMBER)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $privcache = $this->private_key;
    
    // Instanciate the Rijndael encryption object
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    
    // Fetch our decryption key
    
    $aes_key = $this->fetch_public_key($aes_key);
    if(!$aes_key)
      return 'Couldn\'t look up public key "'.$aes_key.'" for decryption';
    
    // Convert the key to a binary string
    $bin_key = hexdecode($aes_key);
    
    if(strlen($bin_key) != AES_BITS / 8)
      return 'The decryption key is the wrong length';
    
    // Decrypt our password
    $password = $aes->decrypt($aes_data, $bin_key, ENC_HEX);
    
    // Initialize our success switch
    $success = false;
    
    // Select the user data from the table, and decrypt that so we can verify the password
    $this->sql('SELECT password,old_encryption,user_id,user_level,theme,style,temp_password,temp_password_time FROM '.table_prefix.'users WHERE lcase(username)=\''.$this->prepare_text(strtolower($username)).'\';');
    if($db->numrows() < 1)
      return 'The username and/or password is incorrect.';
    $row = $db->fetchrow();
    
    // Check to see if we're logging in using a temporary password
    
    if((intval($row['temp_password_time']) + 3600*24) > time() )
    {
      $temp_pass = $aes->decrypt( $row['temp_password'], $this->private_key, ENC_HEX );
      if( $temp_pass == $password )
      {
        $url = makeUrlComplete('Special', 'PasswordReset/stage2/' . $row['user_id'] . '/' . $row['temp_password']);
        
        $code = $plugins->setHook('login_password_reset');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        
        redirect($url, 'Login sucessful', 'Please wait while you are transferred to the Password Reset form.');
        exit;
      }
    }
    
    if($row['old_encryption'] == 1)
    {
      // The user's password is stored using the obsolete and insecure MD5 algorithm, so we'll update the field with the new password
      if(md5($password) == $row['password'])
      {
        $pass_stashed = $aes->encrypt($password, $this->private_key, ENC_HEX);
        $this->sql('UPDATE '.table_prefix.'users SET password=\''.$pass_stashed.'\',old_encryption=0 WHERE user_id='.$row['user_id'].';');
        $success = true;
      }
    }
    else
    {
      // Our password field is up-to-date with the >=1.0RC1 encryption standards, so decrypt the password in the table and see if we have a match; if so then do challenge authentication
      $real_pass = $aes->decrypt(hexdecode($row['password']), $this->private_key, ENC_BINARY);
      if($password == $real_pass)
      {
        // Yay! We passed AES authentication, now do an MD5 challenge check to make sure we weren't spoofed
        $chal = substr($challenge, 0, 32);
        $salt = substr($challenge, 32, 32);
        $correct_challenge = md5( $real_pass . $salt );
        if($chal == $correct_challenge)
          $success = true;
      }
    }
    if($success)
    {
      if($level > $row['user_level'])
        return 'You are not authorized for this level of access.';
      
      $sess = $this->register_session(intval($row['user_id']), $username, $password, $level);
      if($sess)
      {
        $this->username = $username;
        $this->user_id = intval($row['user_id']);
        $this->theme = $row['theme'];
        $this->style = $row['style'];
        
        if($level > USER_LEVEL_MEMBER)
          $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'admin_auth_good\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
        else
          $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'auth_good\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
        
        $code = $plugins->setHook('login_success');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        return 'success';
      }
      else
        return 'Your login credentials were correct, but an internal error occurred while registering the session key in the database.';
    }
    else
    {
      if($level > USER_LEVEL_MEMBER)
        $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'admin_auth_bad\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
      else
        $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'auth_bad\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
        
      return 'The username and/or password is incorrect.';
    }
  }
  
  /**
   * Attempts to login without using crypto stuff, mainly for use when the other side doesn't like Javascript
   * This method of authentication is inherently insecure, there's really nothing we can do about it except hope and pray that everyone moves to Firefox
   * Technically it still uses crypto, but it only decrypts the password already stored, which is (obviously) required for authentication
   * @param string $username The username
   * @param string $password The password -OR- the MD5 hash of the password if $already_md5ed is true
   * @param bool $already_md5ed This should be set to true if $password is an MD5 hash, and should be false if it's plaintext. Defaults to false.
   * @param int $level The privilege level we're authenticating for, defaults to 0
   */
  
  function login_without_crypto($username, $password, $already_md5ed = false, $level = USER_LEVEL_MEMBER)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $pass_hashed = ( $already_md5ed ) ? $password : md5($password);
    
    // Perhaps we're upgrading Enano?
    if($this->compat)
    {
      return $this->login_compat($username, $pass_hashed, $level);
    }
    
    // Instanciate the Rijndael encryption object
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    
    // Initialize our success switch
    $success = false;
    
    // Retrieve the real password from the database
    $this->sql('SELECT password,old_encryption,user_id,user_level,temp_password,temp_password_time FROM '.table_prefix.'users WHERE lcase(username)=\''.$this->prepare_text(strtolower($username)).'\';');
    if($db->numrows() < 1)
      return 'The username and/or password is incorrect.';
    $row = $db->fetchrow();
    
    // Check to see if we're logging in using a temporary password
    
    if((intval($row['temp_password_time']) + 3600*24) > time() )
    {
      $temp_pass = $aes->decrypt( $row['temp_password'], $this->private_key, ENC_HEX );
      if( md5($temp_pass) == $pass_hashed )
      {
        $code = $plugins->setHook('login_password_reset');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        
        header('Location: ' . makeUrlComplete('Special', 'PasswordReset/stage2/' . $row['user_id'] . '/' . $row['temp_password']) );
        
        exit;
      }
    }
    
    if($row['old_encryption'] == 1)
    {
      // The user's password is stored using the obsolete and insecure MD5 algorithm - we'll update the field with the new password
      if($pass_hashed == $row['password'] && !$already_md5ed)
      {
        $pass_stashed = $aes->encrypt($password, $this->private_key, ENC_HEX);
        $this->sql('UPDATE '.table_prefix.'users SET password=\''.$pass_stashed.'\',old_encryption=0 WHERE user_id='.$row['user_id'].';');
        $success = true;
      }
      elseif($pass_hashed == $row['password'] && $already_md5ed)
      {
        // We don't have the real password so don't bother with encrypting it, just call it success and get out of here
        $success = true;
      }
    }
    else
    {
      // Our password field is up-to-date with the >=1.0RC1 encryption standards, so decrypt the password in the table and see if we have a match
      $real_pass = $aes->decrypt($row['password'], $this->private_key);
      if($pass_hashed == md5($real_pass))
      {
        $success = true;
      }
    }
    if($success)
    {
      if((int)$level > (int)$row['user_level'])
        return 'You are not authorized for this level of access.';
      $sess = $this->register_session(intval($row['user_id']), $username, $real_pass, $level);
      if($sess)
      {
        if($level > USER_LEVEL_MEMBER)
          $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'admin_auth_good\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
        else
          $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'auth_good\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
        
        $code = $plugins->setHook('login_success');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        return 'success';
      }
      else
        return 'Your login credentials were correct, but an internal error occured while registering the session key in the database.';
    }
    else
    {
      if($level > USER_LEVEL_MEMBER)
        $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'admin_auth_bad\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', ' . intval($level) . ')');
      else
        $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'auth_bad\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\')');
        
      return 'The username and/or password is incorrect.';
    }
  }
  
  /**
   * Attempts to log in using the old table structure and algorithm.
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
   * Basically the session key is a base64-encoded cookie (encrypted with the site's private key) that says "u=[username];p=[sha1 of password]"
   * @param int $user_id
   * @param string $username
   * @param string $password
   * @param int $level The level of access to grant, defaults to USER_LEVEL_MEMBER
   * @return bool
   */
   
  function register_session($user_id, $username, $password, $level = USER_LEVEL_MEMBER)
  {
    $salt = md5(microtime() . mt_rand());
    $passha1 = sha1($password);
    $session_key = "u=$username;p=$passha1;s=$salt";
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    $session_key = $aes->encrypt($session_key, $this->private_key, ENC_HEX);
    if($level > USER_LEVEL_MEMBER)
    {
      $hexkey = strrev($session_key);
      $this->sid_super = $hexkey;
      $_GET['auth'] = $hexkey;
    }
    else
    {
      setcookie( 'sid', $session_key, time()+315360000, scriptPath.'/' );
      $_COOKIE['sid'] = $session_key;
    }
    $keyhash = md5($session_key);
    $ip = ip2hex($_SERVER['REMOTE_ADDR']);
    if(!$ip)
      die('$session->register_session: Remote-Addr was spoofed');
    $time = time();
    if(!is_int($user_id))
      die('Somehow an SQL injection attempt crawled into our session registrar! (1)');
    if(!is_int($level))
      die('Somehow an SQL injection attempt crawled into our session registrar! (2)');
    
    $query = $this->sql('INSERT INTO '.table_prefix.'session_keys(session_key, salt, user_id, auth_level, source_ip, time) VALUES(\''.$keyhash.'\', \''.$salt.'\', '.$user_id.', '.$level.', \''.$ip.'\', '.$time.');');
    return true;
  }
  
  /**
   * Identical to register_session in nature, but uses the old login/table structure. DO NOT use this.
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
   * Creates/restores a guest session
   * @todo implement real session management for guests
   */
   
  function register_guest_session()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
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
      $this->style = ( isset($_GET['style']) && file_exists(ENANO_ROOT.'/themes/'.$this->theme . '/css/'.$_GET['style'].'.css' )) ? $_GET['style'] : substr($template->named_theme_list[$this->theme]['default_style'], 0, strlen($template->named_theme_list[$this->theme]['default_style'])-4);
    }
    $this->user_id = 1;
  }
  
  /**
   * Validates a session key, and returns the userdata associated with the key or false
   * @param string $key The session key to validate
   * @return array Keys are 'user_id', 'username', 'email', 'real_name', 'user_level', 'theme', 'style', 'signature', 'reg_time', 'account_active', 'activation_key', and 'auth_level' or bool false if validation failed. The key 'auth_level' is the maximum authorization level that this key provides.
   */
   
  function validate_session($key)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE, true);
    $decrypted_key = $aes->decrypt($key, $this->private_key, ENC_HEX);
    
    if ( !$decrypted_key )
    {
      die_semicritical('AES encryption error', '<p>Something went wrong during the AES decryption process.</p><pre>'.print_r($decrypted_key, true).'</pre>');
    }
    
    $n = preg_match('/^u='.$this->valid_username.';p=([A-Fa-f0-9]+?);s=([A-Fa-f0-9]+?)$/', $decrypted_key, $keydata);
    if($n < 1)
    {
      // echo '(debug) $session->validate_session: Key does not match regex<br />Decrypted key: '.$decrypted_key;
      return false;
    }
    $keyhash = md5($key);
    $salt = $db->escape($keydata[3]);
    $query = $db->sql_query('SELECT u.user_id AS uid,u.username,u.password,u.email,u.real_name,u.user_level,u.theme,u.style,u.signature,u.reg_time,u.account_active,u.activation_key,k.source_ip,k.time,k.auth_level,COUNT(p.message_id) AS num_pms,x.* FROM '.table_prefix.'session_keys AS k
                               LEFT JOIN '.table_prefix.'users AS u
                                 ON ( u.user_id=k.user_id )
                               LEFT JOIN '.table_prefix.'users_extra AS x
                                 ON ( u.user_id=x.user_id OR x.user_id IS NULL )
                               LEFT JOIN '.table_prefix.'privmsgs AS p
                                 ON ( p.message_to=u.username AND p.message_read=0 )
                               WHERE k.session_key=\''.$keyhash.'\'
                                 AND k.salt=\''.$salt.'\'
                               GROUP BY u.user_id;');
    if ( !$query )
    {
      $query = $this->sql('SELECT u.user_id AS uid,u.username,u.password,u.email,u.real_name,u.user_level,u.theme,u.style,u.signature,u.reg_time,u.account_active,u.activation_key,k.source_ip,k.time,k.auth_level,COUNT(p.message_id) AS num_pms FROM '.table_prefix.'session_keys AS k
                             LEFT JOIN '.table_prefix.'users AS u
                               ON ( u.user_id=k.user_id )
                             LEFT JOIN '.table_prefix.'privmsgs AS p
                               ON ( p.message_to=u.username AND p.message_read=0 )
                             WHERE k.session_key=\''.$keyhash.'\'
                               AND k.salt=\''.$salt.'\'
                             GROUP BY u.user_id;');
    }
    if($db->numrows() < 1)
    {
      // echo '(debug) $session->validate_session: Key was not found in database<br />';
      return false;
    }
    $row = $db->fetchrow();
    $row['user_id'] =& $row['uid'];
    $ip = ip2hex($_SERVER['REMOTE_ADDR']);
    if($row['auth_level'] > $row['user_level'])
    {
      // Failed authorization check
      // echo '(debug) $session->validate_session: access to this auth level denied<br />';
      return false;
    }
    if($ip != $row['source_ip'])
    {
      // Failed IP address check
      // echo '(debug) $session->validate_session: IP address mismatch<br />';
      return false;
    }
    
    // Do the password validation
    $real_pass = $aes->decrypt($row['password'], $this->private_key, ENC_HEX);
    
    //die('<pre>'.print_r($keydata, true).'</pre>');
    if(sha1($real_pass) != $keydata[2])
    {
      // Failed password check
      // echo '(debug) $session->validate_session: encrypted password is wrong<br />Real password: '.$real_pass.'<br />Real hash: '.sha1($real_pass).'<br />User hash: '.$keydata[2];
      return false;
    }
    
    $time_now = time();
    $time_key = $row['time'] + 900;
    if($time_now > $time_key && $row['auth_level'] > USER_LEVEL_MEMBER)
    {
      // Session timed out
      // echo '(debug) $session->validate_session: super session timed out<br />';
      $this->sw_timed_out = true;
      return false;
    }
    
    // If this is an elevated-access session key, update the time
    if( $row['auth_level'] > USER_LEVEL_MEMBER )
    {
      $this->sql('UPDATE '.table_prefix.'session_keys SET time='.time().' WHERE session_key=\''.$keyhash.'\';');
    }
    
    $row['password'] = md5($real_pass);
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
    
    $query = $this->sql('SELECT u.user_id,u.username,u.password,u.email,u.real_name,u.user_level,k.source_ip,k.salt,k.time,k.auth_level FROM '.table_prefix.'session_keys AS k
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
      $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
      if(!$this->user_logged_in || $this->auth_level < USER_LEVEL_MOD) return 'success';
      // Destroy elevated privileges
      $keyhash = md5(strrev($this->sid_super));
      $this->sql('DELETE FROM '.table_prefix.'session_keys WHERE session_key=\''.$keyhash.'\' AND user_id=\'' . $this->user_id . '\';');
      $this->sid_super = false;
      $this->auth_level = USER_LEVEL_MEMBER;
    }
    else
    {
      if($this->user_logged_in)
      {
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
    if(!$this->password_hash) return false;
    return $this->password_hash;
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
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
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
    $aes = new AESCrypt();
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
    if($this->compat)
      $q = $this->sql('SELECT ban_id,ban_type,ban_value,is_regex FROM '.table_prefix.'banlist ORDER BY ban_type;');
    else
      $q = $this->sql('SELECT ban_id,ban_type,ban_value,is_regex,reason FROM '.table_prefix.'banlist ORDER BY ban_type;');
    if(!$q) $db->_die('The banlist data could not be selected.');
    $banned = false;
    while($row = $db->fetchrow())
    {
      if($this->compat)
        $row['reason'] = 'None available - session manager is in compatibility mode';
      switch($row['ban_type'])
      {
      case BAN_IP:
        if(intval($row['is_regex'])==1) {
          if(preg_match('#'.$row['ban_value'].'#i', $_SERVER['REMOTE_ADDR']))
          {
            $banned = true;
            $reason = $row['reason'];
          }
        }
        else {
          if($row['ban_value']==$_SERVER['REMOTE_ADDR']) { $banned = true; $reason = $row['reason']; }
        }
        break;
      case BAN_USER:
        if(intval($row['is_regex'])==1) {
          if(preg_match('#'.$row['ban_value'].'#i', $this->username))
          {
            $banned = true;
            $reason = $row['reason'];
          }
        }
        else {
          if($row['ban_value']==$this->username) { $banned = true; $reason = $row['reason']; }
        }
        break;
      case BAN_EMAIL:
        if(intval($row['is_regex'])==1) {
          if(preg_match('#'.$row['ban_value'].'#i', $this->email))
          {
            $banned = true;
            $reason = $row['reason'];
          }
        }
        else {
          if($row['ban_value']==$this->email) { $banned = true; $reason = $row['reason']; }
        }
        break;
      default:
        die('Ban error: rule "'.$row['ban_value'].'" has an invalid type ('.$row['ban_type'].')');
      }
    }
    if($banned && $paths->get_pageid_from_url() != $paths->nslist['Special'].'CSS')
    {
      // This guy is banned - kill the session, kill the database connection, bail out, and be pretty about it
      die_semicritical('Ban notice', '<div class="error-box">You have been banned from this website. Please contact the site administrator for more information.<br /><br />Reason:<br />'.$reason.'</div>');
      exit;
    }
  }
  
  # Registration
  
  /**
   * Registers a user. This does not perform any type of login.
   * @param string $username
   * @param string $password This should be unencrypted.
   * @param string $email
   * @param string $real_name Optional, defaults to ''.
   */
   
  function create_user($username, $password, $email, $real_name = '')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Initialize AES
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    
    if(!preg_match('#^'.$this->valid_username.'$#', $username)) return 'The username you chose contains invalid characters.';
    $username = $this->prepare_text($username);
    $email = $this->prepare_text($email);
    $real_name = $this->prepare_text($real_name);
    $password = $aes->encrypt($password, $this->private_key, ENC_HEX);
    
    $nameclause = ( $real_name != '' ) ? ' OR real_name=\''.$real_name.'\'' : '';
    $q = $this->sql('SELECT * FROM '.table_prefix.'users WHERE lcase(username)=\''.strtolower($username).'\' OR email=\''.$email.'\''.$nameclause.';');
    if($db->numrows() > 0) {
      $r = 'The ';
      $i=0;
      $row = $db->fetchrow();
      // Wow! An error checker that actually speaks English with the properest grammar! :-P
      if($row['username'] == $username) { $r .= 'username'; $i++; }
      if($row['email'] == $email) { if($i) $r.=', '; $r .= 'e-mail address'; $i++; }
      if($row['real_name'] == $real_name && $real_name != '') { if($i) $r.=', and '; $r .= 'real name'; $i++; }
      $r .= ' that you entered ';
      $r .= ( $i == 1 ) ? 'is' : 'are';
      $r .= ' already in use by another user.';
      return $r;
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
    
    // Generate a totally random activation key
    $actkey = sha1 ( microtime() . mt_rand() );

    // We good, create the user    
    $this->sql('INSERT INTO '.table_prefix.'users ( username, password, email, real_name, theme, style, reg_time, account_active, activation_key, user_level ) VALUES ( \''.$username.'\', \''.$password.'\', \''.$email.'\', \''.$real_name.'\', \''.$template->default_theme.'\', \''.$template->default_style.'\', '.time().', '.$active.', \''.$actkey.'\', '.USER_LEVEL_CHPREF.' )');
    
    // Require the account to be activated?
    switch(getConfig('account_activation'))
    {
      case 'none':
      default:
        break;
      case 'user':
        $a = $this->send_activation_mail($username);
        if(!$a)
        {
          $this->admin_activation_request($username);
          return 'The activation e-mail could not be sent due to an internal error. This could possibly be due to an incorrect SMTP configuration. A request has been sent to the administrator to activate your account for you. ' . $a;
        }
        break;
      case 'admin':
        $this->admin_activation_request($username);
        break;
    }
    
    // Leave some data behind for the hook
    $code = $plugins->setHook('user_registered'); // , Array('username'=>$username));
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    // $this->register_session($username, $password);
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
    $q = $this->sql('SELECT username,email FROM '.table_prefix.'users WHERE user_id=1 OR user_level=' . USER_LEVEL_ADMIN . ' ORDER BY user_id ASC;');
    $un = $db->fetchrow();
    $admin_user = $un['username'];
    $q = $this->sql('SELECT username,activation_key,account_active,email FROM '.table_prefix.'users WHERE username=\''.$db->escape($u).'\';');
    $r = $db->fetchrow();
    if ( empty($r['email']) )
      $db->_die('BUG: $session->send_activation_mail(): no e-mail address in row');
    $message = 'Dear '.$u.',
Thank you for registering on '.getConfig('site_name').'. Your account creation is almost complete. To complete the registration process, please click the following link or paste it into your web browser:
    
';
    if(isset($_SERVER['HTTPS'])) $prot = 'https';
    else $prot = 'http';                                                                           
    if($_SERVER['SERVER_PORT'] == '80') $p = '';
    else $p = ':'.$_SERVER['SERVER_PORT'];
    $sidbak = false;
    if($this->sid_super)
      $sidbak = $this->sid_super;
    $this->sid_super = false;
    $aklink = makeUrlNS('Special', 'ActivateAccount/'.str_replace(' ', '_', $u).'/'. ( ( is_string($actkey) ) ? $actkey : $r['activation_key'] ) );
    if($sidbak)
      $this->sid_super = $sidbak;
    unset($sidbak);
    $message .= "$prot://".$_SERVER['HTTP_HOST'].$p.$aklink;
      $message .= "\n\nSincerely yours, \n$admin_user and the ".$_SERVER['HTTP_HOST']." administration team";
    error_reporting(E_ALL);
    dc_dump($r, 'session: about to send activation e-mail to '.$r['email']);
    if(getConfig('smtp_enabled') == '1')
    {
      $result = smtp_send_email($r['email'], getConfig('site_name').' website account activation', preg_replace("#(?<!\r)\n#s", "\n", $message), getConfig('contact_email'));
      if($result == 'success') $result = true;
      else { echo $result; $result = false; }
    } else {
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
    if(is_int($user))
    {
      $q = $this->sql('SELECT user_id,username,email FROM '.table_prefix.'users WHERE user_id='.$user.';'); // This is SAFE! This is only called if $user is an integer
    }
    elseif(is_string($user))
    {
      $q = $this->sql('SELECT user_id,username,email FROM '.table_prefix.'users WHERE username=\''.$db->escape($user).'\';');
    }
    else
    {
      return false;
    }
    
    $row = $db->fetchrow();
    $temp_pass = $this->random_pass();
    
    $this->register_temp_password($row['user_id'], $temp_pass);
    
    $site_name = getConfig('site_name');
    
    $message = "Dear {$row['username']},
    
Someone (hopefully you) on the {$site_name} website requested that a new password be created.

The request was sent from the IP address {$_SERVER['REMOTE_ADDR']}.

If you did not request the new password, then you do not need to do anything; the password will be invalidated after 24 hours.

If you did request this password, then please log in using the password shown below:

  Password: {$temp_pass}
  
After you log in using this password, you will be able to reset your real password. You can only log in using this temporary password once.

Sincerely yours,
The {$site_name} administration team
";
    
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
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    $temp_pass = $aes->encrypt($password, $this->private_key, ENC_HEX);
    $this->sql('UPDATE '.table_prefix.'users SET temp_password=\'' . $temp_pass . '\',temp_password_time='.time().' WHERE user_id='.intval($user_id).';');
  }
  
  /**
   * Sends a request to the admin panel to have the username $u activated.
   * @param string $u The username of the user requesting activation
   */
  
  function admin_activation_request($u)
  {
    global $db;
    $this->sql('INSERT INTO '.table_prefix.'logs(log_type, action, time_id, date_string, author, edit_summary) VALUES(\'admin\', \'activ_req\', '.time().', \''.date('d M Y h:i a').'\', \''.$this->username.'\', \''.$db->escape($u).'\');');
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
      $e = $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'activ_good\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($user).'\', \''.$_SERVER['REMOTE_ADDR'].'\')');
    }
    else
    {
      $e = $this->sql('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary) VALUES(\'security\', \'activ_bad\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($user).'\', \''.$_SERVER['REMOTE_ADDR'].'\')');
    }
    return $r;
  }
  
  /**
   * For a given user level identifier (USER_LEVEL_*), returns a string describing that user level.
   * @param int User level
   * @return string
   */
  
  function userlevel_to_string($user_level)
  {
    switch ( $user_level )
    {
      case USER_LEVEL_GUEST:
        return 'Low - guest privileges';
      case USER_LEVEL_MEMBER:
        return 'Standard - normal member level';
      case USER_LEVEL_CHPREF:
        return 'Medium - user can change his/her own e-mail address and password';
      case USER_LEVEL_MOD:
        return 'High - moderator privileges';
      case USER_LEVEL_ADMIN:
        return 'Highest - administrative privileges';
      default:
        return "Unknown ($user_level)";
    }
  }
  
  /**
   * Updates a user's information in the database. Note that any of the values except $user_id can be false if you want to preserve the old values.
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
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    
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
        // I didn't write this regex.
        if(!preg_match('/^(?:[\w\d]+\.?)+@(?:(?:[\w\d]\-?)+\.)+\w{2,4}$/', $email))
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
    
    //if ( !isset( $paths->pages[$paths->nslist[$namespace] . $page_id] ) )
    //{
    //  // Page does not exist
    //  return false;
    //}
    
    $object = new Session_ACLPageInfo( $page_id, $namespace, $this->acl_types, $this->acl_descs, $this->acl_deps, $this->acl_base_cache );
    
    return $object;
    
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
    $bs = 'SELECT rules FROM '.table_prefix.'acl WHERE page_id IS NULL AND namespace IS NULL AND ( ';
    
    $q = Array();
    $q[] = '( target_type='.ACL_TYPE_USER.' AND target_id='.$this->user_id.' )';
    if(count($this->groups) > 0)
    {
      foreach($this->groups as $g_id => $g_name)
      {
        $q[] = '( target_type='.ACL_TYPE_GROUP.' AND target_id='.intval($g_id).' )';
      }
    }
    $bs .= implode(' OR ', $q) . ' ) ORDER BY target_type ASC, target_id ASC;';
    $q = $this->sql($bs);
    if ( $row = $db->fetchrow() )
    {
      do {
        $rules = $this->string_to_perm($row['rules']);
        $is_everyone = ( $row['target_type'] == ACL_TYPE_GROUP && $row['target_id'] == 1 );
        $this->acl_merge_with_current($rules, $is_everyone);
      } while ( $row = $db->fetchrow() );
    }
    
    // Eliminate types that don't apply to this namespace
    foreach ( $this->perms AS $i => $perm )
    {
      if ( !in_array ( $paths->namespace, $this->acl_scope[$i] ) && !in_array('All', $this->acl_scope[$i]) )
      {
        unset($this->perms[$i]);
      }
    }
    
    // Cache the sitewide permissions for later use
    $this->acl_base_cache = $this->perms;
    
    // Build a query to grab ACL info
    $bs = 'SELECT rules,target_type,target_id FROM '.table_prefix.'acl WHERE ( ';
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
    $bs .= implode(' OR ', $q) . ' ) AND ( page_id=\''.$db->escape($paths->cpage['urlname_nons']).'\' AND namespace=\''.$db->escape($paths->namespace).'\' )     
      ORDER BY target_type ASC, page_id ASC, namespace ASC;';
    $q = $this->sql($bs);
    if ( $row = $db->fetchrow() )
    {
      do {
        $rules = $this->string_to_perm($row['rules']);
        $is_everyone = ( $row['target_type'] == ACL_TYPE_GROUP && $row['target_id'] == 1 );
        $this->acl_merge_with_current($rules, $is_everyone);
      } while ( $row = $db->fetchrow() );
    }
    
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
   * @return array
   */
   
  function acl_merge($perm1, $perm2)
  {
    $ret = $perm1;
    foreach ( $perm2 as $type => $level )
    {
      if ( isset( $ret[$type] ) )
      {
        if ( $ret[$type] != AUTH_DENY )
          $ret[$type] = $level;
      }
      // else
      // {
      //   $ret[$type] = $level;
      // }
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
    foreach ( $this->perms as $i => $p )
    {
      if ( isset($perm[$i]) )
      {
        if ( $is_everyone && !$this->acl_defaults_used[$i] )
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
   
  function acl_check_deps($type)
  {
    if(!isset($this->acl_deps[$type])) // This will only happen if the permissions table is hacked or improperly accessed
      return true;
    if(sizeof($this->acl_deps[$type]) < 1)
      return true;
    $deps = $this->acl_deps[$type];
    while(true)
    {
      $full_resolved = true;
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
    //die('<pre>'.print_r($deps, true).'</pre>');
    foreach($deps as $d)
    {
      if ( !$this->get_permissions($d) )
      {
        return false;
      }
    }
    return true;
  }
  
  /**
   * Makes a CAPTCHA code and caches the code in the database
   * @param int $len The length of the code, in bytes
   * @return string A unique identifier assigned to the code. This hash should be passed to sessionManager::getCaptcha() to retrieve the code.
   */
  
  function make_captcha($len = 7)
  {
    $chars = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $s = '';
    for($i=0;$i<$len;$i++) $s .= $chars[mt_rand(0, count($chars)-1)];
    $hash = md5(microtime() . mt_rand());
    $this->sql('INSERT INTO '.table_prefix.'session_keys(session_key,salt,auth_level,source_ip,user_id) VALUES(\''.$hash.'\', \''.$s.'\', -1, \''.ip2hex($_SERVER['REMOTE_ADDR']).'\', -2);');
    return $hash;
  }
  
  /**
   * For the given code ID, returns the correct CAPTCHA code, or false on failure
   * @param string $hash The unique ID assigned to the code
   * @return string The correct confirmation code
   */
  
  function get_captcha($hash)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $s = $this->sql('SELECT salt FROM '.table_prefix.'session_keys WHERE session_key=\''.$db->escape($hash).'\' AND source_ip=\''.ip2hex($_SERVER['REMOTE_ADDR']).'\';');
    if($db->numrows() < 1) return false;
    $r = $db->fetchrow();
    return $r['salt'];
  }
  
  /**
   * Deletes all CAPTCHA codes cached in the DB for this user.
   */
  
  function kill_captcha()
  {
    $this->sql('DELETE FROM '.table_prefix.'session_keys WHERE user_id=-2 AND source_ip=\''.ip2hex($_SERVER['REMOTE_ADDR']).'\';');
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
   * Generates some Javascript that calls the AES encryption library.
   * @param string The name of the form
   * @param string The name of the password field
   * @param string The name of the field that switches encryption on or off
   * @param string The name of the field that contains the encryption key
   * @param string The name of the field that will contain the encrypted password
   * @param string The name of the field that handles MD5 challenge data
   * @return string
   */
   
  function aes_javascript($form_name, $pw_field, $use_crypt, $crypt_key, $crypt_data, $challenge)
  {
    $code = '
      <script type="text/javascript">
        disableJSONExts();
          str = \'\';
          for(i=0;i<keySizeInBits/4;i++) str+=\'0\';
          var key = hexToByteArray(str);
          var pt = hexToByteArray(str);
          var ct = rijndaelEncrypt(pt, key, \'ECB\');
          var ct = byteArrayToHex(ct);
          switch(keySizeInBits)
          {
            case 128:
              v = \'66e94bd4ef8a2c3b884cfa59ca342b2e\';
              break;
            case 192:
              v = \'aae06992acbf52a3e8f4a96ec9300bd7aae06992acbf52a3e8f4a96ec9300bd7\';
              break;
            case 256:
              v = \'dc95c078a2408989ad48a21492842087dc95c078a2408989ad48a21492842087\';
              break;
          }
          var testpassed = ' . ( ( isset($_GET['use_crypt']) && $_GET['use_crypt']=='0') ? 'false; // CRYPTO-AUTH DISABLED ON USER REQUEST // ' : '' ) . '( ct == v && md5_vm_test() );
          var frm = document.forms.'.$form_name.';
          if(testpassed)
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
          }
          if(frm.username) frm.username.focus();
          function runEncryption()
          {
            if(testpassed)
            {
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
            return false;
          }
        </script>
        ';
    return $code;
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
   * Our tell-all list of permissions.
   * @access private - or, preferably, protected...too bad this has to be PHP4 compatible
   * @var array
   */
   
  var $perms = Array();
  
  /**
   * Constructor.
   * @param string $page_id The ID of the page to check
   * @param string $namespace The namespace of the page to check.
   * @param array $acl_types List of ACL types
   * @param array $acl_descs List of human-readable descriptions for permissions (associative)
   * @param array $acl_deps List of dependencies for permissions. For example, viewing history/diffs depends on the ability to read the page.
   * @param array $base What to start with - this is an attempt to reduce the number of SQL queries.
   */
   
  function Session_ACLPageInfo($page_id, $namespace, $acl_types, $acl_descs, $acl_deps, $base)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->perms = $session->acl_merge_complete($acl_types, $base);
    $this->acl_deps = $acl_deps;
    $this->acl_types = $acl_types;
    $this->acl_descs = $acl_descs;
    
    // Build a query to grab ACL info
    $bs = 'SELECT rules FROM '.table_prefix.'acl WHERE ( ';
    $q = Array();
    $q[] = '( target_type='.ACL_TYPE_USER.' AND target_id='.$session->user_id.' )';
    if(count($session->groups) > 0)
    {
      foreach($session->groups as $g_id => $g_name)
      {
        $q[] = '( target_type='.ACL_TYPE_GROUP.' AND target_id='.intval($g_id).' )';
      }
    }
    // The reason we're using an ORDER BY statement here is because ACL_TYPE_GROUP is less than ACL_TYPE_USER, causing the user's individual
    // permissions to override group permissions.
    $bs .= implode(' OR ', $q) . ' ) AND ( page_id=\''.$db->escape($page_id).'\' AND namespace=\''.$db->escape($namespace).'\' )     
      ORDER BY target_type ASC, page_id ASC, namespace ASC;';
    $q = $session->sql($bs);
    if ( $row = $db->fetchrow() )
    {
      do {
        $rules = $session->string_to_perm($row['rules']);
        $this->perms = $session->acl_merge($this->perms, $rules);
      } while ( $row = $db->fetchrow() );
    }
    
    $this->page_id = $page_id;
    $this->namespace = $namespace;
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
      else if ( $this->perms[$type] == AUTH_WIKIMODE &&
        ( isset($paths->pages[$paths->nslist[$this->namespace].$this->page_id]) && 
          ( $paths->pages[$paths->nslist[$this->namespace].$this->page_id]['wiki_mode'] == '1' ||
            ( $paths->pages[$paths->nslist[$this->namespace].$this->page_id]['wiki_mode'] == '2'
              && getConfig('wiki_mode') == '1'
          ) ) ) )
        $ret = true;
      else if ( $this->perms[$type] == AUTH_WIKIMODE && (
        !isset($paths->pages[$paths->nslist[$this->namespace].$this->page_id])
        || (
          isset($paths->pages[$paths->nslist[$this->namespace].$this->page_id]) && (
            $paths->pages[$paths->nslist[$this->namespace].$this->page_id]['wiki_mode'] == '0'
            || (
              $paths->pages[$paths->nslist[$this->namespace].$this->page_id]['wiki_mode'] == '2' && getConfig('wiki_mode') != '1'
          ) ) ) ) )
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
   * Tell us if the dependencies for a given permission are met.
   * @param string The ACL permission ID
   * @return bool
   */
   
  function acl_check_deps($type)
  {
    if(!isset($this->acl_deps[$type])) // This will only happen if the permissions table is hacked or improperly accessed
      return true;
    if(sizeof($this->acl_deps[$type]) < 1)
      return true;
    $deps = $this->acl_deps[$type];
    while(true)
    {
      $full_resolved = true;
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
    //die('<pre>'.print_r($deps, true).'</pre>');
    foreach($deps as $d)
    {
      if ( !$this->get_permissions($d) )
      {
        return false;
      }
    }
    return true;
  }
  
}

?>

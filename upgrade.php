<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.2 (Coblynau)
 * upgrade.php - upgrade script
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

define('IN_ENANO_INSTALL', 'true');   

if(!defined('scriptPath')) {
  $sp = dirname($_SERVER['REQUEST_URI']);
  if($sp == '/' || $sp == '\\') $sp = '';
  define('scriptPath', $sp);
}

if(!defined('contentPath')) {
  $sp = dirname($_SERVER['REQUEST_URI']);
  if($sp == '/' || $sp == '\\') $sp = '';
  define('contentPath', $sp);
}

global $_starttime, $this_page, $sideinfo;
$_starttime = microtime(true);

// Determine directory (special case for development servers)
if ( strpos(__FILE__, '/repo/') && file_exists('.enanodev') )
{
  $filename = str_replace('/repo/', '/', __FILE__);
}
else
{
  $filename = __FILE__;
}

define('ENANO_ROOT', dirname($filename));

require(ENANO_ROOT.'/includes/constants.php');

if(defined('ENANO_DEBUG'))
{
  require_once(ENANO_ROOT.'/includes/debugger/debugConsole.php');
}
else
{
  function dc_here($m)     { return false; }
  function dc_dump($a, $g) { return false; }
  function dc_watch($n)    { return false; }
  function dc_start_timer($u) { return false; }
  function dc_stop_timer($m) { return false; }
}

// SCRIPT CONFIGURATION
// Everything related to versions goes here!

// Valid versions to upgrade from
$valid_versions = Array('1.0b1', '1.0b2', '1.0b3', '1.0b4', '1.0RC1', '1.0RC2', '1.0RC3', '1.0', '1.0.1', '1.0.1.1');

// Basically a list of dependencies, which should be resolved automatically
// If, for example, upgrading from 1.0b1 to 1.0RC1 requires one extra query that would not
// normally be required (for whatever reason) then you would add a custom version number to the array under key '1.0b1'.
$deps_list = Array(
    '1.0b1' => Array('1.0b2'),
    '1.0b2' => Array('1.0b3'),
    '1.0b3' => Array('1.0b4'),
    '1.0b4' => Array('1.0RC1'),
    '1.0RC1' => Array('1.0RC2'),
    '1.0RC2' => Array('1.0RC3'),
    '1.0RC3' => Array('1.0'),
    '1.0' => Array('1.0.1'),
    '1.0.1' => Array('1.0.1.1')
  );
$this_version   = '1.0.2';
$func_list = Array(
    '1.0' => Array('u_1_0_1_update_del_votes'),
    '1.0b4' => Array('u_1_0_RC1_update_user_ids', 'u_1_0_RC1_add_admins_to_group', 'u_1_0_RC1_alter_files_table', 'u_1_0_RC1_destroy_session_cookie', 'u_1_0_RC1_set_contact_email', 'u_1_0_RC1_update_page_text'), // ,
    // '1.0RC2' => Array('u_1_0_populate_userpage_comments')
    '1.0RC3' => Array('u_1_0_RC3_make_users_extra')
  );

if(!isset($_GET['mode'])) 
{
  $_GET['mode'] = 'login';
}

function err($t)
{
  global $template;
  echo $t;
  $template->footer(); 
  exit;
}

require(ENANO_ROOT.'/includes/template.php');

// Initialize the session manager
require(ENANO_ROOT.'/includes/functions.php');
require(ENANO_ROOT.'/includes/dbal.php');
require(ENANO_ROOT.'/includes/paths.php');
require(ENANO_ROOT.'/includes/sessions.php');
require(ENANO_ROOT.'/includes/plugins.php');
require(ENANO_ROOT.'/includes/rijndael.php');
require(ENANO_ROOT.'/includes/render.php');
$db = new mysql();
$db->connect();

$plugins = new pluginLoader();

if(!defined('ENANO_CONFIG_FETCHED'))
{
  // Select and fetch the site configuration
  $e = $db->sql_query('SELECT config_name, config_value FROM '.table_prefix.'config;');
  if ( !$e )
  {
    $db->_die('Some critical configuration information could not be selected.');
  }
  else
  {
    define('ENANO_CONFIG_FETCHED', ''); // Used in die_semicritical to figure out whether to call getConfig() or not
  }
  
  $enano_config = Array();
  while($r = $db->fetchrow())
  {
    $enano_config[$r['config_name']] = $r['config_value'];
  }
  $db->free_result();
}

$v = enano_version();
if(in_array($v, Array(false, '', '1.0b3', '1.0b4')))
{
  $ul_admin  = 2;
  $ul_mod    = 1;
  $ul_member = 0;
  $ul_guest  = -1;
}
else
{
  $ul_admin  = USER_LEVEL_ADMIN;
  $ul_mod    = USER_LEVEL_MOD;
  $ul_member = USER_LEVEL_MEMBER;
  $ul_guest  = USER_LEVEL_GUEST;
}

$_GET['title'] = 'unset';

$session = new sessionManager();
$paths = new pathManager();
$session->start();

$template = new template_nodb();
$template->load_theme('oxygen', 'bleu', false);

$modestrings = Array(
              'login'      => 'Administrative login',
              'welcome'    => 'Welcome',
              'setversion' => 'Select Enano version',
              'confirm'    => 'Confirm upgrade',
              'upgrade'    => 'Database installation',
              'finish'     => 'Upgrade complete'
            );

$sideinfo = '';
$vars = $template->extract_vars('elements.tpl');
$p = $template->makeParserText($vars['sidebar_button']);
foreach ( $modestrings as $id => $str )
{
  if ( $_GET['mode'] == $id )
  {
    $flags = 'style="font-weight: bold; text-decoration: underline;"';
    $this_page = $str;
  }
  else
  {
    $flags = '';
  }
  $p->assign_vars(Array(
      'HREF' => '#',
      'FLAGS' => $flags . ' onclick="return false;"',
      'TEXT' => $str
    ));
  $sideinfo .= $p->run();
}

$template->init_vars();

function upg_assign_vars($schema)
{
  $schema = str_replace('{{SITE_NAME}}',   mysql_real_escape_string(getConfig('site_name')), $schema);
  $schema = str_replace('{{SITE_DESC}}',   mysql_real_escape_string(getConfig('site_desc')), $schema);
  $schema = str_replace('{{COPYRIGHT}}',   mysql_real_escape_string(getConfig('copyright_notice')), $schema);
  $schema = str_replace('{{TABLE_PREFIX}}', table_prefix, $schema);
  if(getConfig('wiki_mode')=='1') $schema = str_replace('{{WIKI_MODE}}', '1', $schema);
  else $schema = str_replace('{{WIKI_MODE}}', '0', $schema);
  return $schema;
}

/* Version-specific functions */

function u_1_0_RC1_update_user_ids()
{
  global $db;
  // First, make sure this hasn't already been done
  $q = $db->sql_query('SELECT username FROM '.table_prefix.'users WHERE user_id=1;');
  if ( !$q )
    $db->_die();
  $row = $db->fetchrow();
  if ( $row['username'] == 'Anonymous' )
    return true;
  // Find the first unused user ID
  $used = Array();
  $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users;');
  if ( !$q )
    $db->_die();
  $notfirst = false;
  while ( $row = $db->fetchrow() )
  {
    $i = intval($row['user_id']);
    $used[$i] = true;
    if ( !isset($used[$i - 1]) && $notfirst )
    {
      $id = $i - 1;
      break;
    }
    $notfirst = true;
  }
  if ( !isset($id) )
    $id = $i + 1;
  if ( $id == 0 )
    $id = 2;
  $db->free_result();
  
  $q = $db->sql_query('UPDATE '.table_prefix.'users SET user_id=' . $id . ' WHERE user_id=1;');
  if(!$q)
    $db->_die();
  $q = $db->sql_query('UPDATE '.table_prefix.'users SET user_id=1 WHERE user_id=-1 AND username=\'Anonymous\';');
  if(!$q)
    $db->_die();
  
}

function u_1_0_RC1_add_admins_to_group()
{
  global $db;
  $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE user_level=' . USER_LEVEL_ADMIN . ';');
  if ( !$q )
    $db->_die();
  $base = 'INSERT INTO '.table_prefix.'group_members(group_id,user_id) VALUES';
  $blocks = Array();
  while ( $row = $db->fetchrow($q) )
  {
    $blocks[] = '(2,' . $row['user_id'] . ')';
  }
  $blocks = implode(',', $blocks);
  $sql = $base . $blocks . ';';
  if(!$db->sql_query($sql))
    $db->_die();
}

function u_1_0_RC1_alter_files_table()
{
  global $db;
  if(!is_dir(ENANO_ROOT.'/files'))
    @mkdir(ENANO_ROOT . '/files');
  if(!is_dir(ENANO_ROOT.'/files'))
    die('ERROR: Couldn\'t create files directory');
  $q = $db->sql_unbuffered_query('SELECT * FROM '.table_prefix.'files;', $db->_conn);
  if(!$q) $db->_die();
  while ( $row = $db->fetchrow() )
  {
    $file_data = base64_decode($row['data']);
    $path = ENANO_ROOT . '/files/' . md5( $row['filename'] . '_' . $file_data ) . '_' . $row['time_id'] . $row['file_extension'];
    @unlink($path);
    $handle = @fopen($path, 'w');
    if(!$handle)
      die('fopen failed');
    fwrite($handle, $file_data);
    fclose($handle);
    
  }
  
  $q = $db->sql_query('ALTER TABLE '.table_prefix.'files DROP PRIMARY KEY, ADD COLUMN file_id int(12) NOT NULL auto_increment FIRST, ADD PRIMARY KEY (file_id), ADD COLUMN file_key varchar(32) NOT NULL;');
  if(!$q) $db->_die();
  
  $list = Array();
  $q = $db->sql_unbuffered_query('SELECT * FROM '.table_prefix.'files;', $db->_conn);
  if(!$q) $db->_die();
  while ( $row = $db->fetchrow($q) )
  {
    $file_data = base64_decode($row['data']);
    $key = md5( $row['filename'] . '_' . $file_data );
    $list[] = 'UPDATE '.table_prefix.'files SET file_key=\'' . $key . '\' WHERE file_id=' . $row['file_id'] . ';';
  }
  
  foreach ( $list as $sql )
  {
    if(!$db->sql_query($sql)) $db->_die();
  }
  
  if(!$db->sql_query('ALTER TABLE '.table_prefix.'files DROP data')) $db->_die();
  
}

function u_1_0_RC1_destroy_session_cookie()
{
  unset($_COOKIE['sid']);
  setcookie('sid', '', time()-3600*24, scriptPath);
  setcookie('sid', '', time()-3600*24, scriptPath.'/');
}

function u_1_0_RC1_set_contact_email()
{
  global $db;
  $q = $db->sql_query('SELECT email FROM '.table_prefix.'users WHERE user_level='.USER_LEVEL_ADMIN.' ORDER BY user_level ASC LIMIT 1;');
  if(!$q)
    $db->_die();
  $row = $db->fetchrow();
  setConfig('contact_email', $row['email']);
}

function u_1_0_RC1_update_page_text()
{
  global $db;
  $q = $db->sql_unbuffered_query('SELECT page_id,namespace,page_text,char_tag FROM '.table_prefix.'page_text');
  if (!$q)
    $db->_die();
  
  $qs = array();
  
  while ( $row = $db->fetchrow($q) )
  {
    $row['page_text'] = str_replace(Array(
      "{QUOT:{$row['char_tag']}}",
      "{APOS:{$row['char_tag']}}",
      "{SLASH:{$row['char_tag']}}"
      ), Array(
      '"', "'", '\\'
      ), $row['page_text']);
    $qs[] = 'UPDATE '.table_prefix.'page_text SET page_text=\'' . mysql_real_escape_string($row['page_text']) . '\'
      WHERE page_id=\'' . mysql_real_escape_string($row['page_id']) . '\' AND
            namespace=\'' . mysql_real_escape_string($row['namespace']) . '\';';
  }
  
  foreach($qs as $query)
  {
    if(!$db->sql_query($query))
      $db->_die();
  }
}

function u_1_0_1_update_del_votes()
{
  global $db;
  $q = $db->sql_query('SELECT urlname, namespace, delvote_ips FROM '.table_prefix.'pages;');
  if ( !$q )
    $db->_die();
  
  while ( $row = $db->fetchrow($q) )
  {
    $ips = strval($row['delvote_ips']);
    if ( is_array( @unserialize($ips) ) )
      continue;
    $ips = explode('|', $ips);
    $new = array(
      'ip' => array(),
      'u' => array()
      );
    $i = 0;
    $prev = '';
    $prev_is_ip = false;
    foreach ( $ips as $ip )
    {
      $i++;
      $current_is_ip = is_valid_ip($ip);
      if ( $current_is_ip && $prev_is_ip )
      {
        $i++;
        $new['u'][] = $prev;
      }
      if ( $current_is_ip )
      {
        $new['ip'][] = $ip;
      }
      else
      {
        $new['u'][] = $ip;
      }
      $prev = $ip;
      $prev_is_ip = $current_is_ip;
    }
    if ( $i % 2 == 1 && $prev_is_ip )
    {
      $new['u'][] = $ip;
    }
    $new = serialize($new);
    $e = $db->sql_query('UPDATE '.table_prefix.'pages SET delvote_ips=\'' . $db->escape($new) . '\' WHERE urlname=\'' . $db->escape($row['urlname']) . '\' AND namespace=\'' . $db->escape($row['namespace']) . '\';');
    if ( !$e )
      $db->_die();
  }
  $db->free_result($q);
}

function u_1_0_RC3_make_users_extra()
{
  global $db;
  $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE user_id > 0;');
  if ( !$q )
    $db->_die();
  
  $ids = array();
  while ( $row = $db->fetchrow() )
  {
    $ids[] = intval($row['user_id']);
  }
  
  $ids = '(' . implode('),(', $ids) . ')';
  if ( $ids == '' )
    return false;
  $sql = "INSERT INTO " . table_prefix . "users_extra(user_id) VALUES$ids;";
  
  if ( !$db->sql_query($sql) )
    $db->_die();
}

switch($_GET['mode'])
{
  case "login":
    if ( $session->user_logged_in && $session->user_level < $ul_admin )
    {
      $template->header();
      echo '<p>Your user account does not have permission to perform an upgrade of Enano. Return to the <a href="index.php">index page</a>.</p>';
      $template->footer();
      exit;
    }
    if($session->user_logged_in && $session->user_level >= $ul_admin)
    {
      if(isset($_POST['login']))
      {
        $session->login_without_crypto($_POST['username'], $_POST['password'], false, $ul_admin);
        if($session->sid_super)
        {
          header('Location: upgrade.php?mode=welcome&auth='.$session->sid_super);
          exit;
        }
      }
      $template->header();
      ?>
      <form action="upgrade.php?mode=login" method="post">
      <table border="0" style="margin-left: auto; margin-right: auto; margin-top: 5px;" cellspacing="1" cellpadding="4">
        <tr>
          <th colspan="2">You must re-authenticate to perform this upgrade.</th>
        </tr>
        <?php
        if(isset($_POST['login']))
        {
          echo '<tr><td colspan="2"><p style="color: red;">Login failed. Bad password?</p></td></tr>';
        }
        ?>
        <tr>
          <td>Username:</td><td><input type="text" name="username" size="30" /></td>
        </tr>
        <tr>
          <td>Password:</td><td><input type="password" name="password" size="30" /></td>
        </tr>
        <tr>
          <td colspan="2" style="text-align: center;"><input type="submit" name="login" value="Log in" />
        </tr>
      </table>
      </form>
      <?php
    }
    else
    {
      if(isset($_POST['login']))
      {
        $result = $session->login_without_crypto($_POST['username'], $_POST['password'], false, $ul_member);
        if($result == 'success')
        {
          header('Location: upgrade.php');
          exit;
        }
      }
      $template->header();
      ?>
      <form action="upgrade.php?mode=login" method="post">
      <table border="0" style="margin-left: auto; margin-right: auto; margin-top: 5px;" cellspacing="1" cellpadding="4">
        <tr>
          <th colspan="2">Please log in to continue with this upgrade.</th>
        </tr>
        <?php
        if(isset($_POST['login']))
        {
          echo '<tr><td colspan="2"><p style="color: red;">Login failed. Bad password?</p></td></tr>';
        }
        ?>
        <tr>
          <td>Username:</td><td><input type="text" name="username" size="30" /></td>
        </tr>
        <tr>
          <td>Password:</td><td><input type="password" name="password" size="30" /></td>
        </tr>
        <tr>
          <td colspan="2" style="text-align: center;"><input type="submit" name="login" value="Log in" />
        </tr>
      </table>
      </form>
      <?php
    }
    break;
  case "welcome":
    if(!$session->sid_super) { $template->header(); echo '<p>No admin session found! Please <a href="upgrade.php">restart the upgrade</a>.</p>'; $template->footer(); exit; }
    
    // Just show a simple welcome page to display version information
    $template->header();
    require('config.php');
    
    ?>
    
    <div style="text-align: center; margin-top: 10px;">
      <img alt="[ Enano CMS Project logo ]" src="images/enano-artwork/installer-greeting-blue.png" style="display: block; margin: 0 auto; padding-left: 134px;" />
      <h2>Welcome to the Enano upgrade wizard</h2>
      <?php
      if ( file_exists('./_nightly.php') )
      {
        echo '<div class="warning-box" style="text-align: left; margin: 10px auto; display: table; width: 60%;"><b>You are about to upgrade to a NIGHTLY BUILD of Enano.</b><br />Nightly builds CANNOT be re-upgraded to the final release. They may also contain serious flaws, security problems, or extraneous debugging information. Continuing this process on a production site is NOT recommended.</div>';
      }
      ?>
    </div>
    <div style="display: table; margin: 0 auto;">
      <p>You are about to upgrade Enano to version <b><?php echo $this_version; ?></b>. Before you continue, please ensure that:</p>
      <ul>
        <li>You have completely backed up your database (<b><?php echo "$dbhost:$dbname"; ?></b>)</li>
        <li>You have backed up the entire Enano directory (<b><?php echo ENANO_ROOT; ?></b>)</li>
        <li>You have reviewed the release notes for this version, and you<br />are comfortable with any known bugs or issues</li>
        <li>If you've configured Enano to work using a MySQL user with restricted<br />privileges, you need to enable ALTER, CREATE TABLE, and CREATE INDEX privileges<br />for this upgrade to work.</li>
      </ul>
    </div>
    <div style="text-align: center; margin-top: 10px;">
      <form action="upgrade.php?mode=setversion&amp;auth=<?php echo $session->sid_super; ?>" method="post">
        <input type="submit" value="Continue with upgrade" />
      </form>
    </div>
    
    <?php
    
    break;
  case "setversion":
    if(!$session->sid_super) { $template->header(); echo '<p>No admin session found! Please <a href="upgrade.php">restart the upgrade</a>.</p>'; $template->footer(); exit; }
    $v = ( function_exists('enano_version') ) ? enano_version() : '';
    if(!in_array($v, $valid_versions) && $v != '')
    {
      $template->header();
      ?>
      <p>Your version of Enano (<?php echo $v; ?>) can't be upgraded to this version (<?php echo $this_version; ?>).</p>
      <?php
      break;
    } 
    else if($v == '')
    {
      // OK, we don't know which version he's running. So we'll cheat ;-)
      $template->header();
      echo "<form action='upgrade.php?mode=confirm&amp;auth={$session->sid_super}' method='post'>";
      ?>
      <p>Sorry, we couldn't detect which version of Enano you're running on your server. Please select which version of Enano you have below, and make absolutely sure that you're correct.</p>
      <p><select name="version"><?php
        foreach($valid_versions as $c)
        {
          echo "<option value='{$c}'>{$c}</option>";
        }
      ?></select></p>
      <p>
        <input type="submit" value="Continue" />
      </p>
      <?php
      echo `</form>`;
      break;
    }
    else
    {
      header('Location: upgrade.php?mode=confirm&auth='.$session->sid_super);
    }
    break;
  case "confirm":
    $enano_version = ( isset($_POST['version']) ) ? $_POST['version'] : enano_version();
    
    $template->header();
    if(!$session->sid_super) { echo '<p>No admin session found! Please <a href="upgrade.php">restart the upgrade</a>.</p>'; $template->footer(); exit; }
    ?>
      <form action="upgrade.php?mode=upgrade&amp;auth=<?php echo $session->sid_super; ?>" method="post">
        <table border="0" style="margin-left: auto; margin-right: auto; margin-top: 5px;" cellspacing="1" cellpadding="4">
          <tr>
            <td colspan="2"><p><b>Are you sure you want to perform this upgrade?</b></p><p>You can still cancel the upgrade process now. If<br />the upgrade fails, you will need to roll back<br />any actions made using manual SQL queries.</p><p><b>Please clear your browser cache or<br />shift-reload after the upgrade.</b><br />If you fail to do so, some page elements may<br />be broken.</td>
          </tr>
          <tr>
            <td colspan="2" style="text-align: center;">
              <input type="hidden" name="enano_version" value="<?php echo $enano_version; ?>" />
              <input type="submit" name="doit" value="Upgrade Enano!" />
            </td>
          </tr>
        </table>
      </form>
    <?php
    break;
  case "upgrade":
    $template->header();
    if(!$session->sid_super) { echo '<p>No admin session found! Please <a href="upgrade.php">restart the upgrade</a>.</p>'; $template->footer(); exit; }
    if(!isset($_POST['enano_version'])) { echo '<p>Can\'t find the version information on the POST query, are you trying to do this upgrade directly? Please <a href="upgrade.php">restart the upgrade</a>.</p>'; break; }
    $enano_version = $_POST['enano_version'];
    echo '<p>Preparing for schema execution...';
      // Build an array of queries
      $schema = file_get_contents('upgrade.sql');
      
      // Strip out and process version blocks
      preg_match_all('#---BEGIN ([0-9A-z\.\-]*?)---'."\n".'(.*?)'."\n".'---END \\1---#is', $schema, $matches);
      
      $from_list  =& $matches[1];
      $query_list =& $matches[2];
      
      foreach($matches[0] as $m)
      {
        $schema = str_replace($m, '', $schema);
      }
      $schema = explode("\n", $schema);
      foreach($schema as $k => $q)
      {
        if(substr($q, 0, 2) == '--' || $q == '')
        {
          unset($schema[$k]);
          //die('<pre>'.htmlspecialchars(print_r($schema, true)).'</pre>');
        }
        else
        {
          $schema[$k] = upg_assign_vars($schema[$k]);
        }
      }
      
      foreach($query_list as $k => $q)
      {
        $query_list[$k] = explode("\n", $query_list[$k]);
        foreach($query_list[$k] as $i => $s)
        {
          $tq =& $query_list[$k][$i];
          if(substr($s, 0, 2) == '--' || $s == '')
          {
            unset($query_list[$k][$i]);
            //die('<pre>'.htmlspecialchars(print_r($schema, true)).'</pre>');
          }
          else
          {
            $query_list[$k][$i] = upg_assign_vars($query_list[$k][$i]);
          }
        }
        $query_list[$k] = array_values($query_list[$k]);
      }
      
      $assoc_list = Array();
      
      foreach($from_list as $i => $v)
      {
        $assoc_list[$v] = $query_list[$i];
      }
      
      $schema = array_values($schema);
      
      $deps_resolved = false;
      $installing_versions = Array($enano_version);
      
      while(true)
      {
        $v = array_keys($deps_list);
        foreach($v as $i => $ver)
        {
          if(in_array($ver, $installing_versions))
          {
            // $ver is on the list of versions to be installed. Add its dependencies to the list of versions to install.
            foreach($deps_list[$ver] as $dep)
            {
              if(!in_array($dep, $installing_versions))
              {
                $installing_versions[] = $dep;
              }
            }
          }
          if($i == count($deps_list) - 1)
          {
            break 2;
          }
        }
      }
      
      foreach($installing_versions as $this_ver)
      {
        $schema = array_merge($schema, $assoc_list[$this_ver]);
      }
      
      // Time for some proper SQL syntax!
      // Also check queries for so-called injection attempts to make
      // sure that it doesn't fail during the upgrade process and
      // leave the user with a half-upgraded database
      foreach($schema as $s => $q)
      {
        if(substr($q, strlen($q)-1, 1) != ';') 
        {
          $schema[$s] .= ';';
        }
        if ( !$db->check_query($schema[$s]) )
        {
          // Uh-oh, the check failed, bail out
          // The DBAL runs sanity checks on all queries for safety,
          // so if the check fails in mid-upgrade we are in deep
          // dodo doo-doo.
          echo 'Query failed sanity check, this should never happen and is a bug.</p><p>Query was:</p><pre>'.$schema[$s].'</pre>';
          break 2;
        }
      }
      
      $schema = array_values($schema);
      
      // Used extensively for debugging
      // echo '<pre>'.htmlspecialchars(print_r($schema, true)).'</pre>';
      // break;
      
      echo 'done!<br />Executing upgrade schema...';
      
      // OK, do the loop, baby!!!
      foreach($schema as $q)
      {
        $r = $db->sql_query($q);
        if(!$r)
        {
          echo $db->get_error();
          break 2;
        }
      }
      
      // Call any custom functions
      foreach ( $installing_versions as $ver )
      {
        if ( isset($func_list[$ver]) )
        {
          foreach($func_list[$ver] as $function)
          {
            @call_user_func($function);
          }
        }
      }
      
      // Log the upgrade
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,page_text,edit_summary) VALUES(\'security\', \'upgrade_enano\', ' . time() . ', \'' . date('d M Y h:i a') . '\', \'' . mysql_real_escape_string($session->username) . '\', \'' . mysql_real_escape_string($this_version) . '\', \'' . mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '\');');
      
      echo 'done!</p>';
      echo '<p>You will be redirected shortly. If you aren\'t redirected, <a href="index.php">click here</a>.</p>
            <script type="text/javascript">setTimeout("window.location=\'index.php\'", 2000)</script>';
    break;
}
$template->footer();

?>

<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * database_post.php - Database installation, stage 1
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

// Start up the DBAL
require( ENANO_ROOT . '/includes/dbal.php' );
require( ENANO_ROOT . '/includes/sql_parse.php' );
$dbal = new $driver();
$db_host =& $_POST['db_host'];
$db_user =& $_POST['db_user'];
$db_pass =& $_POST['db_pass'];
$db_name =& $_POST['db_name'];
$db_prefix =& $_POST['table_prefix'];
$db_root_user =& $_POST['db_root_user'];
$db_root_pass =& $_POST['db_root_pass'];

if ( !preg_match('/^[a-z0-9_]*$/', $db_prefix) )
{
  $ui->show_header();
  echo '<p>That table prefix isn\'t going to work.</p>';
  return true;
}

$result = $dbal->connect(true, $db_host, $db_user, $db_pass, $db_name);

// If connection failed, we have the root login, AND we're on MySQL, try to force our way in
if ( !$result && !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) && $driver == 'mysql' )
{
  // Allow a jump / breakout
  switch ( 'foo' ) { case 'foo':
      
    // Try to connect to the DB as root
    $result_root = $dbal->connect(true, $db_host, $db_root_user, $db_root_pass, 'mysql');
    if ( !$result_root )
      break;
    
    $q = $dbal->sql_query('CREATE DATABASE IF NOT EXISTS `' . $dbal->escape($db_name) . '`;');
    if ( !$q )
      break;
    
    if ( $db_host == 'localhost' || $db_host == '127.0.0.1' )
    {
      $q = $dbal->sql_query('GRANT ALL PRIVILEGES ON `' . $dbal->escape($db_name) . '`.* TO \'' . $dbal->escape($db_user) . '\'@\'localhost\'' . "\n" .
                            '  IDENTIFIED BY \'' . $dbal->escape($db_pass) . '\' WITH GRANT OPTION');
    }
    else
    {
      $q = $dbal->sql_query('GRANT ALL PRIVILEGES ON `' . $dbal->escape($db_name) . '`.* TO \'' . $dbal->escape($db_user) . '\'@\'%\'' . "\n" .
                            '  IDENTIFIED BY \'' . $dbal->escape($db_pass) . '\' WITH GRANT OPTION');
    }
    
    if ( !$q )
      break;
    
    $dbal->close();
    $result = $dbal->connect(true, $db_host, $db_user, $db_pass, $db_name);
      
    break;
  }
}

$ui->show_header();

if ( $result )
{
  // We're good, do table drop if requested
  if ( isset($_POST['drop_tables']) )
  {
    global $system_table_list;
    foreach ( $system_table_list as $table )
    {
      $dbal->sql_query("DROP TABLE {$db_prefix}$table");
    }
  }
  // Write out a config file
  $ch = @fopen( ENANO_ROOT . '/config.new.php', 'w' );
  if ( !$ch )
  {
    ?>
    <form action="install.php?stage=database" method="post" name="database_info">
      <h3>Configuration file generation failed.</h3>
      <p>Couldn't open the configuration file to write out database settings. Check your file permissions.</p>
      <p>
        <input type="submit" name="_cont" value="<?php echo $lang->get('database_btn_go_back'); ?>" />
      </p>
    </form>
    <?php
    return true;
  }
  $db_host = str_replace("'", "\\'", $db_host);
  $db_user = str_replace("'", "\\'", $db_user);
  $db_pass = str_replace("'", "\\'", $db_pass);
  $db_name = str_replace("'", "\\'", $db_name);
  $db_prefix = str_replace("'", "\\'", $db_prefix);
  if ( !preg_match('/^[a-z0-9_]*$/', $db_prefix) )
  {
    echo '<p>That table prefix isn\'t going to work.</p>';
    return true;
  }
  fwrite($ch, "<?php
// Enano temporary configuration file, will be OVERWRITTEN after installation.

\$dbdriver = '$driver';
\$dbhost = '$db_host';
\$dbname = '$db_name';
\$dbuser = '$db_user';
\$dbpasswd = '$db_pass';
@define('table_prefix', '$db_prefix');

@define('ENANO_INSTALL_HAVE_CONFIG', 1);
");
  fclose($ch);
  // Create the config table
  try
  {
    $sql_parser = new SQL_Parser( ENANO_ROOT . "/install/schemas/{$driver}_stage1.sql" );
  }
  catch ( Exception $e )
  {
    ?>
    <h3>Can't load schema file</h3>
    <p>The SQL schema file couldn't be loaded.</p>
    <?php echo "<pre>$e</pre>"; ?>
    <?php
    return true;
  }
  // Check to see if the config table already exists
  $q = $dbal->sql_query('SELECT config_name, config_value FROM ' . $db_prefix . 'config LIMIT 1;');
  if ( !$q )
  {
    $sql_parser->assign_vars(array(
        'TABLE_PREFIX' => $db_prefix
      ));
    $sql = $sql_parser->parse();
    foreach ( $sql as $q )
    {
      if ( !$dbal->sql_query($q) )
      {
        ?>
        <form action="install.php?stage=database" method="post" name="database_info">
          <input type="hidden" name="language" value="<?php echo $lang_id; ?>" />
          <input type="hidden" name="driver" value="<?php echo $driver; ?>" />
          <h3><?php echo $lang->get('database_msg_sql_fail_title'); ?></h3>
          <p><?php echo $lang->get('database_msg_sql_fail_body'); ?></p>
          <p><?php echo $lang->get('database_msg_post_fail_desc'); ?>
            <?php
            echo $dbal->sql_error();
            ?>
          </p>
          <p>
            <input type="submit" name="_cont" value="<?php echo $lang->get('database_btn_go_back'); ?>" />
          </p>
        </form>
        <?php
        return true;
      }
    }
  }
  else
  {
    $dbal->free_result();
    if ( !$dbal->sql_query('DELETE FROM ' . $db_prefix . 'config WHERE config_name = \'install_aes_key\';') )
    {
      $dbal->_die('install database_post.php trying to remove old AES installer key');
    }
  }
  $dbal->close();
  ?>
  <form action="install.php?stage=website" method="post" name="install_db_post" onsubmit="return verify();">
  <input type="hidden" name="language" value="<?php echo $lang_id; ?>" />
  <?php
  // FIXME: l10n
  ?>
  <h3><?php echo $lang->get('database_msg_success_title'); ?></h3>
  <p><?php echo $lang->get('database_msg_success_body'); ?></p>
  <p><input type="submit" name="_cont" value="<?php echo $lang->get('meta_btn_continue'); ?>" />  <?php echo $lang->get('database_msg_success_redirect'); ?></p>
  </form>
  <script type="text/javascript">
    setTimeout(function()
      {
        var frm = document.forms.install_db_post;
        frm.submit();
      }, 200);
  </script>
  <?php
}
else
{
  // FIXME: l10n
  ?>
  <form action="install.php?stage=database" method="post" name="database_info">
    <input type="hidden" name="language" value="<?php echo $lang_id; ?>" />
    <input type="hidden" name="driver" value="<?php echo $driver; ?>" />
    <h3><?php echo $lang->get('database_msg_post_fail_title'); ?></h3>
    <p><?php echo $lang->get('database_msg_post_fail_body'); ?></p>
    <p><?php echo $lang->get('database_msg_post_fail_desc'); ?>
      <?php
      echo $dbal->sql_error();
      ?>
    </p>
    <p>
      <input type="submit" name="_cont" value="<?php echo $lang->get('database_btn_go_back'); ?>" />
    </p>
  </form>
  <?php
}


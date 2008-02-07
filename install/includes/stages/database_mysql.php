<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * database_mysql.php - Installer database info page, MySQL
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

if ( isset($_POST['_cont']) )
{
  $allow_go = true;
  // Do we have everything? If so, continue with installation.
  foreach ( array('db_host', 'db_name', 'db_user', 'db_pass') as $field )
  {
    if ( empty($_POST[$field]) )
    {
      $allow_go = false;
    }
  }
  if ( $allow_go )
  {
    require( ENANO_ROOT . '/install/includes/stages/database_post.php' );
    return true;
  }
}

if ( isset($_POST['ajax_test']) )
{
  // Test the database connection
  $return = array(
      'can_install' => false,
      'host_good' => true,
      'creating_user' => false,
      'db_exist' => false,
      'creating_db' => false,
      'creating_db_grant' => false,
      'root_fail' => false,
      'version' => array(
        'version' => 'unknown',
        'good' => 'indeterminate'
      ),
      'last_error' => ''
    );
  
  if ( !isset($_POST['info']) )
    die();
  
  $info = $_POST['info'];
  
  // From here on out will be JSON responses
  header('Content-type: application/json');
  
  try
  {
    $info = @enano_json_decode($info);
  }
  catch ( Zend_Json_Exception $e )
  {
    die(enano_json_encode(array(
        'mode' => 'error',
        'error' => 'Exception in JSON decoder'
      )));
  }
  
  // Try to connect as the normal user
  $test = @mysql_connect($info['db_host'], $info['db_user'], $info['db_pass']);
  if ( !$test )
  {
    $return['creating_user'] = true;
    $return['last_error'] = mysql_error();
    if ( strstr( $return['last_error'], 'Lost connection' ) || strstr( $return['last_error'], 'Unknown MySQL server host' ) )
    {
      $return['host_good'] = false;
    }
    // Doing that failed. If we have root credentials, test those
    if ( !empty($info['db_root_user']) && !empty($info['db_root_pass']) )
    {
      // Log in with root rights and if that works, tell 'em we'll reset the password or create
      // the account if it doesn't exist already. This is done with GRANT ALL PRIVILEGES ON enano_db.*
      // etc etc, a little hackish but known to work with MySQL >= 4.1.
      $test_root = @mysql_connect($info['db_host'], $info['db_root_user'], $info['db_root_pass']);
      if ( $test_root )
      {
        // We logged in with root rights, assume that we have appropriate permissions.
        // If not, well, the installation will fail. Tough on the user, but creating
        // test databases/users is too risky.
        
        // Does the database exist?
        $q = @mysql_query('USE `' . mysql_real_escape_string($info['db_name']) . '`;', $test_root);
        if ( !$q )
        {
          // Nope, we'll have to create it
          $return['creating_db'] = true;
          $return['last_error'] = mysql_error();
        }
        
        $version = mysql_get_server_info($test_root);
        $return['version'] = array(
          'version' => $version,
          'good' => version_compare($version, '4.0.17', '>=')
        );
        
        $return['can_install'] = ( $return['version']['good'] ) ? true : false;
      }
      else
      {
        // Well that helped. Root credentials are bad.
        $return['creating_db'] = true;
        $return['root_fail'] = true;
      }
    }
    else
    {
      // No root credentials, fail out
      $return['root_fail'] = true;
    }
  }
  else
  {
    // We're connected; do we have permission to use the database?
    $have_database = false;
    $q = @mysql_query('USE `' . mysql_real_escape_string($info['db_name']) . '`;', $test);
    if ( $q )
    {
      // Permissions are good and we're all connected. Perform version check...
      $version = mysql_get_server_info($test);
      $return['version'] = array(
        'version' => $version,
        'good' => version_compare($version, '4.0.17', '>=')
      );
      
      $return['can_install'] = ( $return['version']['good'] ) ? true : false;
    }
    else
    {
      $return['last_error'] = mysql_error();
      $return['creating_db'] = true;
      
      // We don't have permission to use the database or it doesn't exist.
      // See if we have a root login to work with, if not then fail
      if ( !empty($info['db_root_user']) && !empty($info['db_root_pass']) )
      {
        // Log in with root rights and if that works, tell 'em we'll create the database.
        $test_root = @mysql_connect($info['db_host'], $info['db_root_user'], $info['db_root_pass']);
        if ( $test_root )
        {
          // We logged in with root rights, assume that we have appropriate permissions.
          // If not, well, the installation will fail. Tough on the user, but creating
          // test databases/users is too risky.
          
          // See if the database already exists
          $dbname = mysql_real_escape_string($info['db_name']);
          $q = @mysql_query("SHOW DATABASES LIKE '$dbname';", $test_root);
          if ( $q )
          {
            if ( mysql_num_rows($q) > 0 )
            {
              $return['creating_db'] = false;
              $return['creating_db_grant'] = true;
            }
            @mysql_free_result($q);
          }
          
          $version = mysql_get_server_info($test);
          $return['version'] = array(
            'version' => $version,
            'good' => version_compare($version, '4.0.17', '>=')
          );
          
          $return['can_install'] = ( $return['version']['good'] ) ? true : false;
        }
        else
        {
          // Well that helped. Root credentials are bad.
          $return['creating_db'] = true;
          $return['root_fail'] = true;
        }
      }
      // No root credentials, fail out
    }
  }
  
  if ( isset($test) && @is_resource($test) )
    @mysql_close($test);
  
  if ( isset($test_root) && @is_resource($test_root) )
    @mysql_close($test_root);
  
  echo enano_json_encode($return);
  
  exit();
}

$ui->add_header('<script type="text/javascript" src="includes/js/formutils.js"></script>');
$ui->show_header();

?>

<div style="float: right; padding: 10px 0 10px 10px;">
  <img alt="MySQL logo" src="../images/about-powered-mysql.png" />
</div>

<p><?php echo $lang->get('dbmysql_blurb_needdb'); ?></p>
<p><?php echo $lang->get('dbmysql_blurb_howtomysql'); ?></p>
<?php
if ( @file_exists('/etc/enano-is-virt-appliance') )
{
  echo '<p>
          ' . $lang->get('database_vm_login_info', array( 'host' => 'localhost', 'user' => 'enano', 'pass' => 'clurichaun', 'name' => 'enano_www1' )) . '
        </p>';
}
?>

<script type="text/javascript">

  var tested = false;

  function verify(field)
  {
    if ( tested && !field )
      return true;
    tested = false;
    if ( document.getElementById('verify_error').className != '' )
    {
      document.getElementById('verify_error').className = '';
      document.getElementById('verify_error').innerHTML = '';
    }
    var frm = document.forms.database_info;
    // List of fields
    var fields = {
      db_host: frm.db_host,
      db_name: frm.db_name,
      db_user: frm.db_user,
      db_pass: frm.db_pass,
      table_prefix: frm.table_prefix,
      db_root_user: frm.db_root_user,
      db_root_pass: frm.db_root_pass
    };
    var passed = true;
    // Main validation
    if ( field == fields.db_host || !field )
    {
      var matches = fields.db_host.value.match(/^([a-z0-9_-]+)((\.([a-z0-9_-]+))*)?$/);
      document.getElementById('s_db_host').src = ( matches ) ? img_neu : img_bad;
      if ( !matches )
        passed = false;
    }
    if ( field == fields.db_name || !field )
    {
      var matches = fields.db_name.value.match(/^[A-z0-9_-]+$/);
      document.getElementById('s_db_name').src = ( matches ) ? img_neu : img_bad;
      if ( !matches )
        passed = false;
    }
    if ( field == fields.db_user || field == fields.db_pass || !field )
    {
      var matches = fields.db_user.value.match(/^[A-z0-9_-]+$/);
      document.getElementById('s_db_auth').src = ( matches ) ? img_neu : img_bad;
      if ( !matches )
        passed = false;
    }
    if ( field == fields.table_prefix || !field )
    {
      var matches = fields.table_prefix.value.match(/^[a-z0-9_]*$/);
      document.getElementById('s_table_prefix').src = ( matches ) ? img_good : img_bad;
      if ( !matches )
        passed = false;
    }
    if ( field == fields.db_root_user || field == fields.db_root_pass || !field )
    {
      var matches = ( ( fields.db_root_user.value.match(/^[A-z0-9_-]+$/) && fields.db_root_pass.value.match(/^.+$/) ) || fields.db_root_user.value == '' );
      document.getElementById('s_db_root').src = ( matches ) ? img_neu : img_bad;
      if ( !matches )
        passed = false;
    }
    return passed;
  }
  
  function ajaxTestConnection()
  {
    if ( !verify() )
    {
      document.body.scrollTop = 0;
      new Spry.Effect.Shake('enano-body', {duration: 750}).start();
      document.getElementById('verify_error').className = 'error-box-mini';
      document.getElementById('verify_error').innerHTML = $lang.get('meta_msg_err_verification');
      return false;
    }
    install_set_ajax_loading();
    
    var frm = document.forms.database_info;
    var connection_info = 'info=' + ajaxEscape(toJSONString({
        db_host: frm.db_host.value,
        db_name: frm.db_name.value,
        db_user: frm.db_user.value,
        db_pass: frm.db_pass.value,
        db_root_user: frm.db_root_user.value,
        db_root_pass: frm.db_root_pass.value
      }));
    
    ajaxPost(scriptPath + '/install/install.php?stage=database', connection_info + '&driver=mysql&ajax_test=on&language=' + enano_lang_code[ENANO_LANG_ID], function()
      {
        if ( ajax.readyState == 4 )
        {
          setTimeout('install_unset_ajax_loading();', 750);
          // Process response
          var response = String(ajax.responseText + '');
          if ( response.substr(0, 1) != '{' )
          {
            alert('Received an invalid JSON response from the server.');
            return false;
          }
          response = parseJSON(response);
          if ( response.mode == 'error' )
          {
            return false;
          }
          document.getElementById('e_db_host').innerHTML = '';
          document.getElementById('e_db_name').innerHTML = '';
          document.getElementById('e_db_auth').innerHTML = '';
          document.getElementById('e_db_root').innerHTML = '';
          if ( response.can_install )
          {
            tested = true;
            var statuses = ['s_db_host', 's_db_name', 's_db_auth', 's_table_prefix', 's_db_root', 's_mysql_version'];
            for ( var i in statuses )
            {
              var img = document.getElementById(statuses[i]);
              if ( img )
                img.src = img_good;
            }
            document.getElementById('e_mysql_version').innerHTML = $lang.get('dbmysql_msg_info_mysql_good');
            document.getElementById('verify_error').className = 'info-box-mini';
            document.getElementById('verify_error').innerHTML = $lang.get('dbmysql_msg_test_success');
            if ( response.creating_db )
            {
              document.getElementById('e_db_name').innerHTML = $lang.get('dbmysql_msg_warn_creating_db');
            }
            if ( response.creating_user )
            {
              document.getElementById('e_db_auth').innerHTML = $lang.get('dbmysql_msg_warn_creating_user');
            }
          }
          else
          {
            // Oh dear, oh dear, oh dear, oh dear, oh dear...
            if ( response.creating_db )
            {
              document.getElementById('e_db_name').innerHTML = $lang.get('dbmysql_msg_err_mysql_dbexist', { mysql_error: response.last_error });
              document.getElementById('s_db_name').src = img_bad;
            }
            if ( response.creating_user )
            {
              document.getElementById('e_db_auth').innerHTML = $lang.get('dbmysql_msg_err_mysql_auth', { mysql_error: response.last_error });
              document.getElementById('s_db_auth').src = img_bad;
            }
            if ( !response.host_good )
            {
              document.getElementById('e_db_host').innerHTML = $lang.get('dbmysql_msg_err_mysql_connect', { db_host: frm.db_host.value, mysql_error: response.last_error });
              document.getElementById('s_db_host').src = img_bad;
            }
          }
        }
      });
  }

</script>

<form action="install.php?stage=database" method="post" name="database_info">
<input type="hidden" name="language" value="<?php echo $lang_id; ?>" />
<input type="hidden" name="driver" value="mysql" />

<table border="0" cellspacing="0" cellpadding="10" width="100%">
  <tr>
    <td colspan="3" style="text-align: center">
      <h3><?php echo $lang->get('dbmysql_table_title'); ?></h3>
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_hostname_title'); ?></b>
      <br /><?php echo $lang->get('dbmysql_field_hostname_body'); ?>
      <br /><span style="color: #993300" id="e_db_host"></span>
    </td>
    <td>
      <input onkeyup="verify(this);" tabindex="1" name="db_host" size="30" type="text" />
    </td>
    <td>
      <img id="s_db_host" alt="Good/bad icon" src="../images/checkbad.png" />
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_dbname_title'); ?></b><br />
      <?php echo $lang->get('dbmysql_field_dbname_body'); ?><br />
      <span style="color: #993300" id="e_db_name"></span>
    </td>
    <td>
      <input onkeyup="verify(this);" tabindex="2" name="db_name" size="30" type="text" />
    </td>
    <td>
      <img id="s_db_name" alt="Good/bad icon" src="../images/checkbad.png" />
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_dbauth_title'); ?></b><br />
      <?php echo $lang->get('dbmysql_field_dbauth_body'); ?><br />
      <span style="color: #993300" id="e_db_auth"></span>
    </td>
    <td>
      <input onkeyup="verify(this);" tabindex="3" name="db_user" size="30" type="text" /><br />
      <br />
      <input name="db_pass" tabindex="4" size="30" type="password" />
    </td>
    <td>
      <img id="s_db_auth" alt="Good/bad icon" src="../images/checkbad.png" />
    </td>
  </tr>
  <tr>
    <td colspan="3" style="text-align: center">
      <h3><?php echo $lang->get('database_heading_optionalinfo'); ?></h3>
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_tableprefix_title'); ?></b><br />
      <?php echo $lang->get('dbmysql_field_tableprefix_body'); ?>
    </td>
    <td>
      <input onkeyup="verify(this);" tabindex="5" name="table_prefix" size="30" type="text" />
    </td>
    <td>
      <img id="s_table_prefix" alt="Good/bad icon" src="../images/check.png" />
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_rootauth_title'); ?></b><br />
      <?php echo $lang->get('dbmysql_field_rootauth_body'); ?><br />
      <span style="color: #993300" id="e_db_root"></span>
    </td>
    <td>
      <input onkeyup="verify(this);" tabindex="6" name="db_root_user" size="30" type="text" /><br />
      <br />
      <input onkeyup="verify(this);" tabindex="7" name="db_root_pass" size="30" type="password" />
    </td>
    <td>
      <img id="s_db_root" alt="Good/bad icon" src="../images/check.png" />
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_mysqlversion_title'); ?></b>
    </td>
    <td id="e_mysql_version">
      <?php echo $lang->get('dbmysql_field_mysqlversion_blurb_willbechecked'); ?>
    </td>
    <td>
      <img id="s_mysql_version" alt="Good/bad icon" src="../images/checkunk.png" />
    </td>
  </tr>
  <tr>
    <td>
      <b><?php echo $lang->get('dbmysql_field_droptables_title'); ?></b><br />
      <?php echo $lang->get('dbmysql_field_droptables_body'); ?>
    </td>
    <td colspan="2">
      <input type="checkbox" tabindex="8" name="drop_tables" id="dtcheck" />  <label for="dtcheck"><?php echo $lang->get('dbmysql_field_droptables_lbl'); ?></label>
    </td>
  </tr>
  <tr>
    <td colspan="3" style="text-align: center">
      <input type="button" tabindex="9" value="<?php echo $lang->get('dbmysql_btn_testconnection'); ?>" onclick="ajaxTestConnection();" />
      <div id="verify_error"></div>
    </td>
  </tr>

</table>

<table border="0">
  <tr>
    <td>
      <input type="submit" tabindex="10" value="<?php echo $lang->get('meta_btn_continue'); ?>" onclick="return verify();" name="_cont" />
    </td>
    <td>
      <p>
        <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
        &bull; <?php echo $lang->get('database_objective_test'); ?><br />
        &bull; <?php echo $lang->get('database_objective_uncrypt'); ?>
      </p>
    </td>
  </tr>
</table>

</form>

<script type="text/javascript">
  verify();
</script>


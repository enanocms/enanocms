<?php
/*
Plugin Name: AjIM Enano Module
Plugin URI: http://enanocms.org/AjIM
Description: AjIM is an AJAX-based chatroom system which was designed to be integrated with other web apps like Enano and phpBB. It's very simple to write bindings for AjIM and it doesn't use that much code which makes it pretty fast.
Author: Dan Fuhry
Version: 1.0
Author URI: http://enanocms.org/
*/

if(!defined('_AJIM_INCLUDED'))
{
  define('_AJIM_INCLUDED', '');
  
  // Change this line to wherever your AjIM installation is
  
  if(defined('scriptPath'))
    define('ajimClientPath', scriptPath.'/ajim');
  
  if(!defined('ENANO_ROOT'))
    define('ENANO_ROOT', dirname(dirname(__FILE__)));
  define('ajimServerPath', ENANO_ROOT.'/ajim');
  global $db, $session, $paths, $template, $plugins; // Common objects
  $__ajim_config = Array(
    'sb_color_background'=>'#FFF',
    'sb_color_foreground'=>'#000',
    );
  if(defined('ENANO_INSTALLED') || defined('MIDGET_INSTALLED'))
  {
    if(!isset($_GET['admin']))
    {
      $plugins->attachHook('compile_template', 'AjIM_SideBar();');
      $plugins->attachHook('acl_rule_init', 'global $session; $session->register_acl_type(\'ajim_post\', AUTH_ALLOW, \'Submit AjIM posts\');');
      include(ajimServerPath . '/ajim.php');
      
      function AjIM_SideBar()
      {
        global $db, $session, $paths, $template, $plugins; // Common objects
        global $__ajim_config;
        $paths->addAdminNode('Plugin configuration', 'AjIM configuration', 'AjIM_Config');
        $dir = getcwd();
        chdir(ENANO_ROOT);
        include('config.php');
        chdir($dir);
        unset($dir);
        if($session->user_level >= USER_LEVEL_ADMIN)
        {
          $r = $db->sql_query('SELECT password FROM '.table_prefix.'users WHERE username=\''.$session->username.'\'');
          $p = $db->fetchrow_num($r);
          $admin = $p[0];
        }
        else 
        {
          $admin = false;
        }
        $__ajim_config['db_connection_handle'] = $db->_conn;
        if(!$session->user_logged_in)
        {
          $__ajim_config['cant_post_notice'] = 'The administrator requires that you <a href="'.makeUrlNS('Special', 'Login/'.$paths->page, null, true).'">log in</a> to post messages.';
        }
        else
        {
          $__ajim_config['cant_post_notice'] = 'The administrator has disallowed message posting for your user account.';
        }
        $canpost = ( $session->get_permissions('ajim_post') ) ? true : false;
        $ajim = new ajim($__ajim_config, table_prefix, scriptPath.'/plugins/ajim.php', $admin, false, $canpost, array('RenderMan', 'render'));
        $template->sidebar_widget('Shoutbox', $ajim->html(ajimClientPath));
        $template->additional_headers .= '<link rel="stylesheet" type="text/css" href="'.ajimClientPath.'/ajim.php?css&amp;id='.$ajim->id.'&amp;pfx='.table_prefix.'&amp;path='.scriptPath.'/plugins/ajim.php" />';
      }
    }
  } elseif(isset($_GET['ajimmode'])) {
    global $db, $session, $paths, $template, $plugins, $dbhost, $dbname, $dbuser, $dbpasswd;
    require_once('../includes/common.php');
    require_once(ajimServerPath . '/ajim.php');
    header('HTTP/1.1 200 OK');
    define('ajimClientPath', scriptPath.'/ajim');
    if($session->user_level >= 2) {
      $admin = $session->grab_password_hash(); 
    } else $admin = false;
    require('../config.php');
    $canpost = (getConfig('ajim_require_login') != '1' || $session->user_logged_in) ? true : false;
    $__ajim_config['db_connection_handle'] = $db->_conn;
    $__ajim_config['cant_post_notice'] = 'The administrator requires that you <a href="'.makeUrlNS('Special', 'Login/'.$paths->page, null, true).'">log in</a> to post messages.';
    $__ajim_config['allow_looping'] = true;
    $ajim = new ajim($__ajim_config, table_prefix, scriptPath.'/plugins/ajim.php', $admin, $_GET['id'], $canpost, array('RenderMan', 'render'));
    $db->close();
    exit;
  }
  
  function page_Admin_AjIM_Config()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(isset($_POST['_save']))
    {
      setConfig('ajim_require_login', ( isset($_POST['ajim_require_login']) ) ? '1' : '0');
    }
    echo '<form name="main" action="'.makeUrl($paths->nslist['Special'].'Administration?module='.$paths->cpage['module']).'" method="post">';
    ?>
    <h3>Configure AjIM, the Asynchronous Javascript Instant Messenger</h3>
     <p>Only one option right now...</p>
     <p><label><input type="checkbox" name="ajim_require_login" <?php if(getConfig('ajim_require_login')=='1') echo 'checked="checked" '; ?>/>Only logged-in users can post</label></p>
     <p><input type="submit" name="_save" value="Save changes" />
    <?php
    echo '</form>';
  }
}
?>

<?php
/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 (Banshee)
 * Copyright (C) 2006-2007 Dan Fuhry
 * install.php - handles everything related to installation and initial configuration
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
@include('config.php');
if( ( defined('ENANO_INSTALLED') || defined('MIDGET_INSTALLED') ) && ((isset($_GET['mode']) && ($_GET['mode']!='finish' && $_GET['mode']!='css')) || !isset($_GET['mode']))) {
  $_GET['title'] = 'Enano:WhoCaresWhatThisIs';
  require('includes/common.php');
  die_friendly('Installation locked', '<p>The Enano installer has found a Enano installation in this directory. You MUST delete config.php if you want to re-install Enano.</p><p>If you wish to upgrade an older Enano installation to this version, please use the <a href="upgrade.php">upgrade script</a>.</p>');
  exit;
}

define('IN_ENANO_INSTALL', 'true');

define('ENANO_VERSION', '1.0');
// In beta versions, define ENANO_BETA_VERSION here

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

function is_page($p)
{
  return true;
}

require('includes/wikiformat.php');
require('includes/constants.php');
require('includes/rijndael.php');
require('includes/functions.php');

strip_magic_quotes_gpc();

//die('Key size: ' . AES_BITS . '<br />Block size: ' . AES_BLOCKSIZE);

if(!function_exists('wikiFormat'))
{
  function wikiFormat($message, $filter_links = true)
  {
    $wiki = & Text_Wiki::singleton('Mediawiki');
    $wiki->setRenderConf('Xhtml', 'code', 'css_filename', 'codefilename');
    $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', contentPath);
    $result = $wiki->transform($message, 'Xhtml');
    
    // HTML fixes
    $result = preg_replace('#<tr>([\s]*?)<\/tr>#is', '', $result);
    $result = preg_replace('#<p>([\s]*?)<\/p>#is', '', $result);
    $result = preg_replace('#<br />([\s]*?)<table#is', '<table', $result);
    
    return $result;
  }
}

global $failed, $warned;

$failed = false;
$warned = false;

function not($var)
{
  if($var)
  {
    return false;
  } 
  else
  {
    return true;
  }
}

function run_test($code, $desc, $extended_desc, $warn = false)
{
  global $failed, $warned;
  static $cv = true;
  $cv = not($cv);
  $val = eval($code);
  if($val)
  {
    if($cv) $color='CCFFCC'; else $color='AAFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px;'>$desc</td><td style='padding-left: 10px;'><img alt='Test passed' src='images/good.gif' /></td></tr>";
  } elseif(!$val && $warn) {
    if($cv) $color='FFFFCC'; else $color='FFFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test passed with warning' src='images/unknown.gif' /></td></tr>";
    $warned = true;
  } else {
    if($cv) $color='FFCCCC'; else $color='FFAAAA';
    echo "<tr><td style='background-color: #$color; width: 500px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test failed' src='images/bad.gif' /></td></tr>";
    $failed = true;
  }
}
function is_apache() { $r = strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') ? true : false; return $r; }

require_once('includes/template.php');

if(!isset($_GET['mode'])) $_GET['mode'] = 'welcome';
switch($_GET['mode'])
{
  case 'mysql_test':
    error_reporting(0);
    $dbhost     = rawurldecode($_POST['host']);
    $dbname     = rawurldecode($_POST['name']);
    $dbuser     = rawurldecode($_POST['user']);
    $dbpass     = rawurldecode($_POST['pass']);
    $dbrootuser = rawurldecode($_POST['root_user']);
    $dbrootpass = rawurldecode($_POST['root_pass']);
    if($dbrootuser != '')
    {
      $conn = mysql_connect($dbhost, $dbrootuser, $dbrootpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('root'.$e);
      }
      $rsp = 'good';
      $q = mysql_query('USE '.$dbname, $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          $rsp .= '_creating_db';
        }
      }
      mysql_close($conn);
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          $rsp .= '_creating_user';
      }
      mysql_close($conn);
      die($rsp);
    }
    else
    {
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('auth'.$e);
      }
      $q = mysql_query('USE '.$dbname, $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          die('name'.$e);
        }
        else
        {
          die('perm'.$e);
        }
      }
    }
    $v = mysql_get_server_info();
    if(version_compare($v, '4.1.17', '<')) die('vers'.$v);
    mysql_close($conn);
    die('good');
    break;
  case 'pophelp':
    $topic = ( isset($_GET['topic']) ) ? $_GET['topic'] : 'invalid';
    switch($topic)
    {
      case 'admin_embed_php':
        $title = 'Allow administrators to embed PHP';
        $content = '<p>This option allows you to control whether anything between the standard &lt;?php and ?&gt; tags will be treated as
                        PHP code by Enano. If this option is enabled, and members of the Administrators group use these tags, Enano will
                        execute that code when the page is loaded. There are obvious potential security implications here, which should
                        be carefully considered before enabling this option.</p>
                    <p>If you are the only administrator of this site, or if you have a high level of trust for those will be administering
                       the site with you, you should enable this to allow extreme customization of pages.</p>
                    <p>Leave this option off if you are at all concerned about security – if your account is compromised and PHP embedding
                       is enabled, an attacker can run arbitrary code on your server! Enabling this will also allow administrators to
                       embed Javascript and arbitrary HTML and CSS.</p>
                    <p>If you don\'t have experience coding in PHP, you can safely disable this option. You may change this at any time
                       using the ACL editor by selecting the Administrators group and This Entire Website under the scope selection, or by
                       using the "embedded PHP kill switch" in the administration panel.</p>';
        break;
      default:
        $title = 'Invalid topic';
        $content = 'Invalid help topic.';
        break;
    }
    echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>Enano installation quick help &bull; {$title}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <style type="text/css">
      body {
        font-family: trebuchet ms, verdana, arial, helvetica, sans-serif;
        font-size: 9pt;
      }
      h2          { border-bottom: 1px solid #90B0D0; margin-bottom: 0; }
      h3          { font-size: 11pt; font-weight: bold; }
      li          { list-style: url(../images/bullet.gif); }
      p           { margin: 1.0em; }
      blockquote  { background-color: #F4F4F4; border: 1px dotted #406080; margin: 1em; padding: 10px; max-height: 250px; overflow: auto; }
      a           { color: #7090B0; }
      a:hover     { color: #90B0D0; }
    </style>
  </head>
  <body>
    <h2>{$title}</h2>
    {$content}
    <p style="text-align: right;">
      <a href="#" onclick="window.close(); return false;">Close window</a>
    </p>
  </body>
</html>
EOF;
    exit;
    break;
  default:
    break;
}

$template = new template_nodb();
$template->load_theme('oxygen', 'bleu', false);

$modestrings = Array(
              'welcome' => 'Welcome',
              'license' => 'License Agreement',
              'sysreqs' => 'Server requirements',
              'database'=> 'Database information',
              'website' => 'Website configuration',
              'login'   => 'Administration login',
              'confirm' => 'Confirm installation',
              'install' => 'Database installation',
              'finish'  => 'Installation complete'
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

if(isset($_GET['mode']) && $_GET['mode'] == 'css')
{
  header('Content-type: text/css');
  echo $template->get_css();
  exit;
}

$template->header();
if(!isset($_GET['mode'])) $_GET['mode'] = 'license';
switch($_GET['mode'])
{ 
  default:
  case 'welcome':
    ?>
    <div style="text-align: center; margin-top: 10px;">
      <img alt="[ Enano CMS Project logo ]" src="images/enano-artwork/installer-greeting-blue.png" style="display: block; margin: 0 auto; padding-left: 100px;" />
      <h2>Welcome to Enano</h2>
      <h3>version 1.0 &ndash; stable<br />
      <span style="font-weight: normal;">also affectionately known as "banshee" <tt>:)</tt></span></h3>
      <?php
      if ( file_exists('./_nightly.php') )
      {
        echo '<div class="warning-box" style="text-align: left; margin: 10px 0;"><b>You are about to install a NIGHTLY BUILD of Enano.</b><br />Nightly builds are NOT upgradeable and may contain serious flaws, security problems, or extraneous debugging information. Installing this version of Enano on a production site is NOT recommended.</div>';
      }
      ?>
      <form action="install.php?mode=license" method="post">
        <input type="submit" value="Start installation" />
      </form>
    </div>
    <?php
    break;
  case "license":
    ?>
    <h3>Welcome to the Enano installer.</h3>
     <p>Thank you for choosing Enano as your CMS. You've selected the finest in design, the strongest in security, and the latest in Web 2.0 toys. Trust us, you'll like it.</p>
     <p>To get started, please read and accept the following license agreement. You've probably seen it before.</p>
     <div style="height: 500px; clip: rect(0px,auto,500px,auto); overflow: auto; padding: 10px; border: 1px dashed #456798; margin: 1em;">
       <h2>GNU General Public License</h2>
       <h3>Declaration of license usage</h3>
       <p>Enano is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.</p>
       <p>This program is distributed in the hope that it will be useful, but <u>without any warranty</u>; without even the implied warranty of <u>merchantability</u> or <u>fitness for a particular purpose</u>. See the GNU General Public License (below) for more details.</p>
       <h3>Human-readable version</h3>
       <p>Enano is distributed under certain licensing terms that we believe make it of the greatest possible use to the public. The license we distribute it under, the GNU General Public License, provides certain terms and conditions that, rather than limit your use of Enano, allow you to get the most out of it. If you would like to read the full text, it can be found below. Here is a human-readable version that we think is a little easier to understand.</p>
       <ul>
       <li>You may to run Enano for any purpose.</li>
       <li>You may study how Enano works and adapt it to your needs.</li>
       <li>You may redistribute copies so you can help your neighbor.</li>
       <li>You may improve Enano and release your improvements to the public, so that the whole community benefits.</li>
       </ul>
       <p>You may exercise the freedoms specified here provided that you comply with the express conditions of this license. The principal conditions are:</p>
       <ul>
       <li>You must conspicuously and appropriately publish on each copy distributed an appropriate copyright notice and disclaimer of warranty and keep intact all the notices that refer to this License and to the absence of any warranty; and give any other recipients of Enano a copy of the GNU General Public License along with Enano. Any translation of the GNU General Public License must be accompanied by the GNU General Public License.</li>
       <li>If you modify your copy or copies of Enano or any portion of it, or develop a program based upon it, you may distribute the resulting work provided you do so under the GNU General Public License. Any translation of the GNU General Public License must be accompanied by the GNU General Public License.</li>
       <li>If you copy or distribute Enano, you must accompany it with the complete corresponding machine-readable source code or with a written offer, valid for at least three years, to furnish the complete corresponding machine-readable source code.</li>
       </ul>
       <p><b>Disclaimer</b>: The above text is not a license. It is simply a handy reference for understanding the Legal Code (the full license) &ndash; it is a human-readable expression of some of its key terms. Think of it as the user-friendly interface to the Legal Code beneath. The above text itself has no legal value, and its contents do not appear in the actual license.<br /><span style="color: #CCC">Text copied from the <a href="http://creativecommons.org/licenses/GPL/2.0/">Creative Commons GPL Deed page</a></span></p>
       <?php
       if ( defined('ENANO_BETA_VERSION') )
       {
         ?>
         <h3>Notice for prerelease versions</h3>
         <p>This version of Enano is designed only for testing and evaluation purposes. <b>It is not yet completely stable, and should not be used on production websites.</b> As with any Enano version, Dan Fuhry and the Enano team cannot be responsible for any damage, physical or otherwise, to any property as a result of the use of Enano. While security is a number one priority, sometimes things slip through.</p>
         <?php
       }
       ?>
       <h3>Lawyer-readable version</h3>
       <?php echo wikiFormat(file_get_contents(ENANO_ROOT . '/GPL')); ?>
     </div>
     <div class="pagenav">
       <form action="install.php?mode=sysreqs" method="post">
         <table border="0">
         <tr>
         <td><input type="submit" value="Continue" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Ensure that you agree with the terms of the license<br />&bull; Have your database host, name, username, and password available</p></td>
         </tr>
         </table>
       </form>
     </div>
    <?php
    break;
  case "sysreqs":
    error_reporting(E_ALL);
    ?>
    <h3>Checking your server</h3>
     <p>Enano has several requirements that must be met before it can be installed. If all is good then note any warnings and click Continue below.</p>
    <table border="0" cellspacing="0" cellpadding="0">
    <?php
    run_test('return version_compare(\'4.3.0\', PHP_VERSION, \'<\');', 'PHP Version >=4.3.0', 'It seems that the version of PHP that your server is running is too old to support Enano properly. If this is your server, please upgrade to the most recent version of PHP, remembering to use the --with-mysql configure option if you compile it yourself. If this is not your server, please contact your webhost and ask them if it would be possible to upgrade PHP. If this is not possible, you will need to switch to a different webhost in order to use Enano.');
    run_test('return function_exists(\'mysql_connect\');', 'MySQL extension for PHP', 'It seems that your PHP installation does not have the MySQL extension enabled. If this is your own server, you may need to just enable the "libmysql.so" extension in php.ini. If you do not have the MySQL extension installed, you will need to either use your distribution\'s package manager to install it, or you will have to compile PHP from source. If you compile PHP from source, please remember to use the "--with-mysql" configure option, and you will have to have the MySQL development files installed (they usually are). If this is not your server, please contact your hosting company and ask them to install the PHP MySQL extension.');
    run_test('return @ini_get(\'file_uploads\');', 'File upload support', 'It seems that your server does not support uploading files. Enano *requires* this functionality in order to work properly. Please ask your server administrator to set the "file_uploads" option in php.ini to "On".');
    run_test('return is_apache();', 'Apache HTTP Server', 'Apparently your server is running a web server other than Apache. Enano will work nontheless, but there are some known bugs with non-Apache servers, and the "fancy" URLs will not work properly. The "Standard URLs" option will be set on the website configuration page, only change it if you are absolutely certain that your server is running Apache.', true);
    //run_test('return function_exists(\'finfo_file\');', 'Fileinfo PECL extension', 'The MIME magic PHP extension is used to determine the type of a file by looking for a certain "magic" string of characters inside it. This functionality is used by Enano to more effectively prevent malicious file uploads. The MIME magic option will be disabled by default.', true);
    run_test('return is_writable(ENANO_ROOT.\'/config.php\');', 'Configuration file writable', 'It looks like the configuration file, config.php, is not writable. Enano needs to be able to write to this file in order to install.<br /><br /><b>If you are installing Enano on a SourceForge web site:</b><br />SourceForge mounts the web partitions read-only now, so you will need to use the project shell service to symlink config.php to a file in the /tmp/persistent directory.');
    run_test('return file_exists(\'/usr/bin/convert\');', 'ImageMagick support', 'Enano uses ImageMagick to scale images into thumbnails. Because ImageMagick was not found on your server, Enano will use the width= and height= attributes on the &lt;img&gt; tag to scale images. This can cause somewhat of a performance increase, but bandwidth usage will be higher, especially if you use high-resolution images on your site.<br /><br />If you are sure that you have ImageMagick, you can set the location of the "convert" program using the administration panel after installation is complete.', true);
    run_test('return is_writable(ENANO_ROOT.\'/cache/\');', 'Cache directory writable', 'Apparently the cache/ directory is not writable. Enano will still work, but you will not be able to cache thumbnails, meaning the server will need to re-render them each time they are requested. In some cases, this can cause a significant slowdown.', true);
    echo '</table>';
    if(!$failed)
    {
      ?>
      
      <div class="pagenav">
      <?php
      if($warned) {
        echo '<table border="0" cellspacing="0" cellpadding="0">';
        run_test('return false;', 'Some scalebacks were made due to your server configuration.', 'Enano has detected that some of the features or configuration settings on your server are not optimal for the best behavior and/or performance for Enano. As a result, certain features or enhancements that are part of Enano have been disabled to prevent further errors. You have seen those "fatal error" notices that spew from PHP, haven\'t you?<br /><br />Fatal error:</b> call to undefined function wannahokaloogie() in file <b>'.__FILE__.'</b> on line <b>'.__LINE__.'', true);
        echo '</table>';
      } else {
        echo '<table border="0" cellspacing="0" cellpadding="0">';
        run_test('return true;', '<b>Your server meets all the requirements for running Enano.</b><br />Click the button below to continue the installation.', 'You should never see this text. Congratulations for being a Enano hacker!');
        echo '</table>';
      }
      ?>
       <form action="install.php?mode=database" method="post">
         <table border="0">
         <tr>
         <td><input type="submit" value="Continue" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Ensure that you are satisfied with any scalebacks that may have been made to accomodate your server configuration<br />&bull; Have your database host, name, username, and password available</p></td>
         </tr>
         </table>
       </form>
     </div>
     <?php
    } else {
      if($failed) {
        echo '<div class="pagenav"><table border="0" cellspacing="0" cellpadding="0">';
        run_test('return false;', 'Your server does not meet the requirements for Enano to run.', 'As a precaution, Enano will not install until the above requirements have been met. Contact your server administrator or hosting company and convince them to upgrade. Good luck.');
        echo '</table></div>';
      }
    }
    ?>
    <?php
    break;
  case "database":
    ?>
    <script type="text/javascript">
      function ajaxGet(uri, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('GET', uri, true);
        ajax.send(null);
      }
      
      function ajaxPost(uri, parms, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('POST', uri, true);
        ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        ajax.setRequestHeader("Content-length", parms.length);
        ajax.setRequestHeader("Connection", "close");
        ajax.send(parms);
      }
      function ajaxTestConnection()
      {
        v = verify();
        if(!v)
        {
          alert('One or more of the form fields is incorrect. Please correct any information in the form that has an "X" next to it.');
          return false;
        }
        var frm = document.forms.dbinfo;
        db_host      = escape(frm.db_host.value.replace('+', '%2B'));
        db_name      = escape(frm.db_name.value.replace('+', '%2B'));
        db_user      = escape(frm.db_user.value.replace('+', '%2B'));
        db_pass      = escape(frm.db_pass.value.replace('+', '%2B'));
        db_root_user = escape(frm.db_root_user.value.replace('+', '%2B'));
        db_root_pass = escape(frm.db_root_pass.value.replace('+', '%2B'));
        
        parms = 'host='+db_host+'&name='+db_name+'&user='+db_user+'&pass='+db_pass+'&root_user='+db_root_user+'&root_pass='+db_root_pass;
        ajaxPost('<?php echo scriptPath; ?>/install.php?mode=mysql_test', parms, function() {
            if(ajax.readyState==4)
            {
              s = ajax.responseText.substr(0, 4);
              t = ajax.responseText.substr(4, ajax.responseText.length);
              if(s.substr(0, 4)=='good')
              {
                document.getElementById('s_db_host').src='images/good.gif';
                document.getElementById('s_db_name').src='images/good.gif';
                document.getElementById('s_db_auth').src='images/good.gif';
                document.getElementById('s_db_root').src='images/good.gif';
                if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = '<b>Warning:<\/b> The database you specified does not exist. It will be created during installation.';
                if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = '<b>Warning:<\/b> The specified regular user does not exist or the password is incorrect. The user will be created during installation. If the user already exists, the password will be reset.';
                document.getElementById('s_mysql_version').src='images/good.gif';
                document.getElementById('e_mysql_version').innerHTML = 'Your version of MySQL meets Enano requirements.';
              }
              else
              {
                switch(s)
                {
                case 'host':
                  document.getElementById('s_db_host').src='images/bad.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_host').innerHTML = '<b>Error:<\/b> The database server "'+document.forms.dbinfo.db_host.value+'" couldn\'t be contacted.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'auth':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/bad.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_auth').innerHTML = '<b>Error:<\/b> Access to MySQL under the specified credentials was denied.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'perm':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = '<b>Error:<\/b> Access to the specified database using those login credentials was denied.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'name':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = '<b>Error:<\/b> The specified database does not exist<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'root':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/bad.gif';
                  document.getElementById('e_db_root').innerHTML = '<b>Error:<\/b> Access to MySQL under the specified credentials was denied.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'vers':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/good.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/good.gif';
                  if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = '<b>Warning:<\/b> The database you specified does not exist. It will be created during installation.';
                  if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = '<b>Warning:<\/b> The specified regular user does not exist or the password is incorrect. The user will be created during installation. If the user already exists, the password will be reset.';
                  
                  document.getElementById('e_mysql_version').innerHTML = '<b>Error:<\/b> Your version of MySQL ('+t+') is older than 4.1.17. Enano will still work, but there is a known bug with the comment system and MySQL 4.1.11 that involves some comments not being displayed, due to an issue with the PHP function mysql_fetch_row().';
                  document.getElementById('s_mysql_version').src='images/bad.gif';
                default:
                  alert(t);
                  break;
                }
              }
            }
          });
      }
      function verify()
      {
        document.getElementById('e_db_host').innerHTML = '';
        document.getElementById('e_db_auth').innerHTML = '';
        document.getElementById('e_db_name').innerHTML = '';
        document.getElementById('e_db_root').innerHTML = '';
        var frm = document.forms.dbinfo;
        ret = true;
        if(frm.db_host.value != '')
        {
          document.getElementById('s_db_host').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_host').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_name.value.match(/^([a-z0-9_]+)$/g))
        {
          document.getElementById('s_db_name').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_user.value != '')
        {
          document.getElementById('s_db_auth').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_auth').src='images/bad.gif';
          ret = false;
        }
        if(frm.table_prefix.value.match(/^([a-z0-9_]*)$/g))
        {
          document.getElementById('s_table_prefix').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_table_prefix').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_root_user.value == '')
        {
          document.getElementById('s_db_root').src='images/good.gif';
        }
        else if(frm.db_root_user.value != '' && frm.db_root_pass.value == '')
        {
          document.getElementById('s_db_root').src='images/bad.gif';
          ret = false;
        }
        else
        {
          document.getElementById('s_db_root').src='images/unknown.gif';
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <p>Now we need some information that will allow Enano to contact your database server. Enano uses MySQL as a data storage backend,
       and we need to have access to a MySQL server in order to continue.</p>
    <p>If you do not have access to a MySQL server, and you are using your own server, you can download MySQL for free from
       <a href="http://www.mysql.com/">MySQL.com</a>. <b>Please note that, like Enano, MySQL is licensed under the GNU GPL.</b>
       If you need to modify MySQL and then distribute your modifications, you must either distribute them under the terms of the GPL
       or purchase a proprietary license.</p>
    <form name="dbinfo" action="install.php?mode=website" method="post">
      <table border="0">
        <tr><td colspan="3" style="text-align: center"><h3>Database information</h3></td></tr>
        <tr><td><b>Database hostname</b><br />This is the hostname (or sometimes the IP address) of your MySQL server. In many cases, this is "localhost".<br /><span style="color: #993300" id="e_db_host"></span></td><td><input onkeyup="verify();" name="db_host" size="30" type="text" /></td><td><img id="s_db_host" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Database name</b><br />The name of the actual database. If you don't already have a database, you can create one here, if you have the username and password of a MySQL user with administrative rights.<br /><span style="color: #993300" id="e_db_name"></span></td><td><input onkeyup="verify();" name="db_name" size="30" type="text" /></td><td><img id="s_db_name" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td rowspan="2"><b>Database login</b><br />These fields should be the username and password of a user with "select", "insert", "update", "delete", "create table", and "replace" privileges for your database.<br /><span style="color: #993300" id="e_db_auth"></span></td><td><input onkeyup="verify();" name="db_user" size="30" type="text" /></td><td rowspan="2"><img id="s_db_auth" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><input name="db_pass" size="30" type="password" /></td></tr>
        <tr><td colspan="3" style="text-align: center"><h3>Optional information</h3></td></tr>
        <tr><td><b>Table prefix</b><br />The value that you enter here will be added to the beginning of the name of each Enano table. You may use lowercase letters (a-z), numbers (0-9), and underscores (_).</td><td><input onkeyup="verify();" name="table_prefix" size="30" type="text" /></td><td><img id="s_table_prefix" alt="Good/bad icon" src="images/good.gif" /></td></tr>
        <tr><td rowspan="2"><b>Database administrative login</b><br />If the MySQL database or username that you entered above does not exist yet, you can create them here, assuming that you have the login information for an administrative user (such as root). Leave these fields blank unless you need to use them.<br /><span style="color: #993300" id="e_db_root"></span></td><td><input onkeyup="verify();" name="db_root_user" size="30" type="text" /></td><td rowspan="2"><img id="s_db_root" alt="Good/bad icon" src="images/good.gif" /></td></tr>
        <tr><td><input onkeyup="verify();" name="db_root_pass" size="30" type="password" /></td></tr>
        <tr><td><b>MySQL version</b></td><td id="e_mysql_version">MySQL version information will be checked when you click "Test Connection".</td><td><img id="s_mysql_version" alt="Good/bad icon" src="images/unknown.gif" /></td></tr>
        <tr><td><b>Delete existing tables?</b><br />If this option is checked, all the tables that will be used by Enano will be dropped (deleted) before the schema is executed. Do NOT use this option unless specifically instructed to.</td><td><input type="checkbox" name="drop_tables" id="dtcheck" />  <label for="dtcheck">Drop existing tables</label></td></tr>
        <tr><td colspan="3" style="text-align: center"><input type="button" value="Test connection" onclick="ajaxTestConnection();" /></td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
       <tr>
       <td><input type="submit" value="Continue" onclick="return verify();" name="_cont" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Check your MySQL connection using the "Test Connection" button.<br />&bull; Be aware that your database information will be transmitted unencrypted several times.</p></td>
       </tr>
       </table>
     </div>
    </form>
    <?php
    break;
  case "website":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    ?>
    <script type="text/javascript">
      function verify()
      {
        var frm = document.forms.siteinfo;
        ret = true;
        if(frm.sitename.value.match(/^(.+)$/g) && frm.sitename.value != 'Enano')
        {
          document.getElementById('s_name').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.sitedesc.value.match(/^(.+)$/g))
        {
          document.getElementById('s_desc').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_desc').src='images/bad.gif';
          ret = false;
        }
        if(frm.copyright.value.match(/^(.+)$/g))
        {
          document.getElementById('s_copyright').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_copyright').src='images/bad.gif';
          ret = false;
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <form name="siteinfo" action="install.php?mode=login" method="post">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <p>The next step is to enter some information about your website. You can always change this information later, using the administration panel.</p>
      <table border="0">
        <tr><td><b>Website name</b><br />The display name of your website. Allowed characters are uppercase and lowercase letters, numerals, and spaces. This must not be blank or "Enano".</td><td><input onkeyup="verify();" name="sitename" type="text" size="30" /></td><td><img id="s_name" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Website description</b><br />This text will be shown below the name of your website.</td><td><input onkeyup="verify();" name="sitedesc" type="text" size="30" /></td><td><img id="s_desc" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Copyright info</b><br />This should be a one-line legal notice that will appear at the bottom of all your pages.</td><td><input onkeyup="verify();" name="copyright" type="text" size="30" /></td><td><img id="s_copyright" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Wiki mode</b><br />This feature allows people to create and edit pages on your site. Enano keeps a history of all page modifications, and you can protect pages to prevent editing.</td><td><input name="wiki_mode" type="checkbox" id="wmcheck" />  <label for="wmcheck">Yes, make my website a wiki.</label></td><td></td></tr>
        <tr><td><b>URL scheme</b><br />Choose how the page URLs will look. Depending on your server configuration, you may need to select the first option. If you don't know, select the first option, and you can always change it later.</td><td colspan="2"><input type="radio" <?php if(!is_apache()) echo 'checked="checked" '; ?>name="urlscheme" value="ugly" id="ugly">  <label for="ugly">Standard URLs - compatible with any web server (www.example.com/index.php?title=Page_name)</label><br /><input type="radio" <?php if(is_apache()) echo 'checked="checked" '; ?>name="urlscheme" value="short" id="short">  <label for="short">Short URLs - requires Apache with a PHP module (www.example.com/index.php/Page_name)</label><br /><input type="radio" name="urlscheme" value="tiny" id="petite">  <label for="petite">Tiny URLs - requires Apache on Linux/Unix/BSD with PHP module and mod_rewrite enabled (www.example.com/Page_name)</label></td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
       <tr>
       <td><input type="submit" value="Continue" onclick="return verify();" name="_cont" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Verify that your site information is correct. Again, all of the above settings can be changed from the administration panel.</p></td>
       </tr>
       </table>
     </div>
    </form>
    <?php
    break;
  case "login":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    require('config.php');
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    if ( isset($crypto_key) )
    {
      $cryptkey = $crypto_key;
    }
    if(!isset($cryptkey) || ( isset($cryptkey) && strlen($cryptkey) != AES_BITS / 4) )
    {
      $cryptkey = $aes->gen_readymade_key();
      $handle = @fopen(ENANO_ROOT.'/config.php', 'w');
      if(!$handle)
      {
        echo '<p>ERROR: Cannot open config.php for writing - exiting!</p>';
        $template->footer();
        exit;
      }
      fwrite($handle, '<?php $cryptkey = \''.$cryptkey.'\'; ?>');
      fclose($handle);
    }
    ?>
    <script type="text/javascript">
      function verify()
      {
        var frm = document.forms.login;
        ret = true;
        if(frm.admin_user.value.match(/^([A-z0-9 \-\.]+)$/g))
        {
          document.getElementById('s_user').src = 'images/good.gif';
        }
        else
        {
          document.getElementById('s_user').src = 'images/bad.gif';
          ret = false;
        }
        if(frm.admin_pass.value.length >= 6 && frm.admin_pass.value == frm.admin_pass_confirm.value)
        {
          document.getElementById('s_password').src = 'images/good.gif';
        }
        else
        {
          document.getElementById('s_password').src = 'images/bad.gif';
          ret = false;
        }
        if(frm.admin_email.value.match(/^(?:[\w\d]+\.?)+@(?:(?:[\w\d]\-?)+\.)+\w{2,4}$/))
        {
          document.getElementById('s_email').src = 'images/good.gif';
        }
        else
        {
          document.getElementById('s_email').src = 'images/bad.gif';
          ret = false;
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
      
      function cryptdata() 
      {
        if(!verify()) return false;
      }
    </script>
    <form name="login" action="install.php?mode=confirm" method="post" onsubmit="runEncryption();">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <p>Next, enter your desired username and password. The account you create here will be used to administer your site.</p>
      <table border="0">
        <tr><td><b>Administration username</b><br />The administration username you will use to log into your site.</td><td><input onkeyup="verify();" name="admin_user" type="text" size="30" /></td><td><img id="s_user" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td>Administration password:</td><td><input onkeyup="verify();" name="admin_pass" type="password" size="30" /></td><td rowspan="2"><img id="s_password" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td>Enter it again to confirm:</td><td><input onkeyup="verify();" name="admin_pass_confirm" type="password" size="30" /></td></tr>
        <tr><td>Your e-mail address:</td><td><input onkeyup="verify();" name="admin_email" type="text" size="30" /></td><td><img id="s_email" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr>
          <td>
            Allow administrative embedding of PHP:<br />
            <small><span style="color: #D84308">Do not under any circumstances enable this option without reading these
                   <a href="install.php?mode=pophelp&amp;topic=admin_embed_php"
                      onclick="window.open(this.href, 'pophelpwin', 'width=550,height=400,status=no,toolbars=no,toolbar=no,address=no,scroll=yes'); return false;"
                      style="color: #D84308; text-decoration: underline;">important security implications</a>.
            </span></small>
          </td>
          <td>
            <label><input type="radio" name="admin_embed_php" value="2" checked="checked" /> Disabled</label>&nbsp;&nbsp;
            <label><input type="radio" name="admin_embed_php" value="4" /> Enabled</label>
          </td>
          <td></td>
        </tr>
        <tr><td colspan="3">If your browser supports Javascript, the password you enter here will be encrypted with AES before it is sent to the server.</td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
       <tr>
       <td><input type="submit" value="Continue" onclick="return cryptdata();" name="_cont" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Remember the username and password you enter here! You will not be able to administer your site without the information you enter on this page.</p></td>
       </tr>
       </table>
      </div>
      <div id="cryptdebug"></div>
     <input type="hidden" name="use_crypt" value="no" />
     <input type="hidden" name="crypt_key" value="<?php echo $cryptkey; ?>" />
     <input type="hidden" name="crypt_data" value="" />
    </form>
    <script type="text/javascript">
    // <![CDATA[
      frm.admin_user.focus();
      function runEncryption()
      {
        str = '';
        for(i=0;i<keySizeInBits/4;i++) str+='0';
        var key = hexToByteArray(str);
        var pt = hexToByteArray(str);
        var ct = rijndaelEncrypt(pt, key, "ECB");
        var ect = byteArrayToHex(ct);
        switch(keySizeInBits)
        {
          case 128:
            v = '66e94bd4ef8a2c3b884cfa59ca342b2e';
            break;
          case 192:
            v = 'aae06992acbf52a3e8f4a96ec9300bd7aae06992acbf52a3e8f4a96ec9300bd7';
            break;
          case 256:
            v = 'dc95c078a2408989ad48a21492842087dc95c078a2408989ad48a21492842087';
            break;
        }
        var testpassed = ( ect == v && md5_vm_test() );
        var frm = document.forms.login;
        if(testpassed)
        {
          // alert('encryption self-test passed');
          frm.use_crypt.value = 'yes';
          var cryptkey = frm.crypt_key.value;
          frm.crypt_key.value = '';
          if(cryptkey != byteArrayToHex(hexToByteArray(cryptkey)))
          {
            alert('Byte array conversion SUCKS');
            testpassed = false;
          }
          cryptkey = hexToByteArray(cryptkey);
          if(!cryptkey || ( ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ) && cryptkey.length != keySizeInBits / 8 )
          {
            frm._cont.disabled = true;
            len = ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ? '\nLen: '+cryptkey.length : '';
            alert('The key is messed up\nType: '+typeof(cryptkey)+len);
          }
        }
        else
        {
          // alert('encryption self-test FAILED');
        }
        if(testpassed)
        {
          pass = frm.admin_pass.value;
          pass = stringToByteArray(pass);
          cryptstring = rijndaelEncrypt(pass, cryptkey, 'ECB');
          //decrypted = rijndaelDecrypt(cryptstring, cryptkey, 'ECB');
          //decrypted = byteArrayToString(decrypted);
          //return false;
          if(!cryptstring)
          {
            return false;
          }
          cryptstring = byteArrayToHex(cryptstring);
          // document.getElementById('cryptdebug').innerHTML = '<pre>Data: '+cryptstring+'<br />Key:  '+byteArrayToHex(cryptkey)+'</pre>';
          frm.crypt_data.value = cryptstring;
          frm.admin_pass.value = '';
          frm.admin_pass_confirm.value = '';
        }
        return false;
      }
      // ]]>
    </script>
    <?php
    break;
  case "confirm":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    ?>
    <form name="confirm" action="install.php?mode=install" method="post">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <h3>Enano is ready to install.</h3>
       <p>The wizard has finished collecting information and is ready to install the database schema. Please review the information below,
          and then click the button below to install the database.</p>
      <ul>
        <li>Database hostname: <?php echo $_POST['db_host']; ?></li>
        <li>Database name: <?php echo $_POST['db_name']; ?></li>
        <li>Database user: <?php echo $_POST['db_user']; ?></li>
        <li>Database password: &lt;hidden&gt;</li>
        <li>Site name: <?php echo $_POST['sitename']; ?></li>
        <li>Site description: <?php echo $_POST['sitedesc']; ?></li>
        <li>Administration username: <?php echo $_POST['admin_user']; ?></li>
        <li>Cipher strength: <?php echo (string)AES_BITS; ?>-bit AES<br /><small>Cipher strength is defined in the file constants.php; if you desire to change the cipher strength, you may do so and then restart installation. Unless your site is mission-critical, changing the cipher strength is not necessary.</small></li>
      </ul>
      <div class="pagenav">
        <table border="0">
          <tr>
            <td><input type="submit" value="Install Enano!" name="_cont" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Pray.</p></td>
          </tr>
        </table>
      </div>
    </form>
    <?php
    break;
  case "install":
    if(!isset($_POST['db_host']) ||
       !isset($_POST['db_name']) ||
       !isset($_POST['db_user']) ||
       !isset($_POST['db_pass']) ||
       !isset($_POST['sitename']) ||
       !isset($_POST['sitedesc']) ||
       !isset($_POST['copyright']) ||
       !isset($_POST['admin_user']) ||
       !isset($_POST['admin_pass']) ||
       !isset($_POST['admin_embed_php']) || ( isset($_POST['admin_embed_php']) && !in_array($_POST['admin_embed_php'], array('2', '4')) ) ||
       !isset($_POST['urlscheme'])
       )
    {
      echo 'The installer has detected that one or more required form values is not set. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    switch($_POST['urlscheme'])
    {
      case "ugly":
      default:
        $cp = scriptPath.'/index.php?title=';
        break;
      case "short":
        $cp = scriptPath.'/index.php/';
        break;
      case "tiny":
        $cp = scriptPath.'/';
        break;
    }
    function err($t) { global $template; echo $t; $template->footer(); exit; }
    
      echo 'Connecting to MySQL...';
      if($_POST['db_root_user'] != '')
      {
        $conn = mysql_connect($_POST['db_host'], $_POST['db_root_user'], $_POST['db_root_pass']);
        if(!$conn) err('Error connecting to MySQL: '.mysql_error());
        $q = mysql_query('USE '.$_POST['db_name']);
        if(!$q)
        {
          $q = mysql_query('CREATE DATABASE '.$_POST['db_name']);
          if(!$q) err('Error initializing database: '.mysql_error());
        }
        $q = mysql_query('GRANT ALL PRIVILEGES ON '.$_POST['db_name'].'.* TO \''.$_POST['db_user'].'\'@\'localhost\' IDENTIFIED BY \''.$_POST['db_pass'].'\' WITH GRANT OPTION;');
        if(!$q) err('Could not create the user account');
        $q = mysql_query('GRANT ALL PRIVILEGES ON '.$_POST['db_name'].'.* TO \''.$_POST['db_user'].'\'@\'%\' IDENTIFIED BY \''.$_POST['db_pass'].'\' WITH GRANT OPTION;');
        if(!$q) err('Could not create the user account');
        mysql_close($conn);
      }
      $conn = mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass']);
      if(!$conn) err('Error connecting to MySQL: '.mysql_error());
      $q = mysql_query('USE '.$_POST['db_name']);
      if(!$q) err('Error selecting database: '.mysql_error());
      echo 'done!<br />';
      
      // Are we supposed to drop any existing tables? If so, do it now
      if(isset($_POST['drop_tables']))
      {
        echo 'Dropping existing Enano tables...';
        // Our list of tables included in Enano
        $tables = Array( 'mdg_categories', 'mdg_comments', 'mdg_config', 'mdg_logs', 'mdg_page_text', 'mdg_session_keys', 'mdg_pages', 'mdg_users', 'mdg_users_extra', 'mdg_themes', 'mdg_buddies', 'mdg_banlist', 'mdg_files', 'mdg_privmsgs', 'mdg_sidebar', 'mdg_hits', 'mdg_search_index', 'mdg_groups', 'mdg_group_members', 'mdg_acl', 'mdg_search_cache' );
        $tables = implode(', ', $tables);
        $tables = str_replace('mdg_', $_POST['table_prefix'], $tables);
        $query_of_death = 'DROP TABLE '.$tables.';';
        mysql_query($query_of_death); // We won't check for errors here because if this operation fails it probably means the tables didn't exist
        echo 'done!<br />';
      }
      
      $cacheonoff = is_writable(ENANO_ROOT.'/cache/') ? '1' : '0';
      
      echo 'Decrypting administration password...';
      
      $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
      
      if ( !empty($_POST['crypt_data']) )
      {
        require('config.php');
        if ( !isset($cryptkey) )
        {
          echo 'failed!<br />Cannot get the key from config.php';
          break;
        }
        $key = hexdecode($cryptkey);
        
        $dec = $aes->decrypt($_POST['crypt_data'], $key, ENC_HEX);
        
      }
      else
      {
        $dec = $_POST['admin_pass'];
      }
      echo 'done!<br />Generating '.AES_BITS.'-bit AES private key...';
      $privkey = $aes->gen_readymade_key();
      $pkba = hexdecode($privkey);
      $encpass = $aes->encrypt($dec, $pkba, ENC_HEX);
      
      echo 'done!<br />Preparing for schema execution...';
      $schema = file_get_contents('schema.sql');
      $schema = str_replace('{{SITE_NAME}}',    mysql_real_escape_string($_POST['sitename']   ), $schema);
      $schema = str_replace('{{SITE_DESC}}',    mysql_real_escape_string($_POST['sitedesc']   ), $schema);
      $schema = str_replace('{{COPYRIGHT}}',    mysql_real_escape_string($_POST['copyright']  ), $schema);
      $schema = str_replace('{{ADMIN_USER}}',   mysql_real_escape_string($_POST['admin_user'] ), $schema);
      $schema = str_replace('{{ADMIN_PASS}}',   mysql_real_escape_string($encpass             ), $schema);
      $schema = str_replace('{{ADMIN_EMAIL}}',  mysql_real_escape_string($_POST['admin_email']), $schema);
      $schema = str_replace('{{ENABLE_CACHE}}', mysql_real_escape_string($cacheonoff          ), $schema);
      $schema = str_replace('{{REAL_NAME}}',    '',                                              $schema);
      $schema = str_replace('{{TABLE_PREFIX}}', $_POST['table_prefix'],                          $schema);
      $schema = str_replace('{{VERSION}}',      ENANO_VERSION,                                   $schema);
      $schema = str_replace('{{ADMIN_EMBED_PHP}}', $_POST['admin_embed_php'],                    $schema);
      // Not anymore!! :-D
      // $schema = str_replace('{{BETA_VERSION}}', ENANO_BETA_VERSION,                              $schema);
      
      if(isset($_POST['wiki_mode']))
      {
        $schema = str_replace('{{WIKI_MODE}}', '1', $schema);
      }
      else
      {
        $schema = str_replace('{{WIKI_MODE}}', '0', $schema);
      }
      
      // Build an array of queries      
      $schema = explode("\n", $schema);
      
      foreach ( $schema as $i => $sql )
      {
        $query =& $schema[$i];
        $t = trim($query);
        if ( empty($t) || preg_match('/^(\#|--)/i', $t) )
        {
          unset($schema[$i]);
          unset($query);
        }
      }
      
      $schema = array_values($schema);
      $schema = implode("\n", $schema);
      $schema = explode(";\n", $schema);
      
      foreach ( $schema as $i => $sql )
      {
        $query =& $schema[$i];
        if ( substr($query, ( strlen($query) - 1 ), 1 ) != ';' )
        {
          $query .= ';';
        }
      }
      
      // echo '<pre>' . htmlspecialchars(print_r($schema, true)) . '</pre>';
      // break;
      
      echo 'done!<br />Executing schema.sql...';
      
      // OK, do the loop, baby!!!
      foreach($schema as $q)
      {
        $r = mysql_query($q, $conn);
        if(!$r) err('Error during mainstream installation: '.mysql_error());
      }
      
      echo 'done!<br />Writing configuration files...';
      if($_POST['urlscheme']=='tiny')
      {
        $ht = fopen(ENANO_ROOT.'/.htaccess', 'a+');
        if(!$ht) err('Error opening file .htaccess for writing');
        fwrite($ht, '
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+) '.scriptPath.'/index.php/$1 [L,QSA]
RewriteRule \.(php|html|gif|jpg|png|css|js)$ - [L]
');
        fclose($ht);
      }
  
      $config_file = '<?php
/* Enano auto-generated configuration file - editing not recommended! */
$dbhost   = \''.addslashes($_POST['db_host']).'\';
$dbname   = \''.addslashes($_POST['db_name']).'\';
$dbuser   = \''.addslashes($_POST['db_user']).'\';
$dbpasswd = \''.addslashes($_POST['db_pass']).'\';
if(!defined(\'ENANO_CONSTANTS\')) {
define(\'ENANO_CONSTANTS\', \'\');
define(\'table_prefix\', \''.$_POST['table_prefix'].'\');
define(\'scriptPath\', \''.scriptPath.'\');
define(\'contentPath\', \''.$cp.'\');
define(\'ENANO_INSTALLED\', \'true\');
}
$crypto_key = \''.$privkey.'\';
?>';

      $cf_handle = fopen(ENANO_ROOT.'/config.php', 'w');
      if(!$cf_handle) err('Couldn\'t open file config.php for writing');
      fwrite($cf_handle, $config_file);
      fclose($cf_handle);
      
      echo 'done!<br />Starting the Enano API...';
      
      $template_bak = $template;
      
      // Get Enano loaded
      $_GET['title'] = 'Main_Page';
      require('includes/common.php');
      
      // We need to be logged in (with admin rights) before logs can be flushed
      $session->login_without_crypto($_POST['admin_user'], $dec, false);
      
      // Now that login cookies are set, initialize the session manager and ACLs
      $session->start();
      $paths->init();
      
      unset($template);
      $template =& $template_bak;
      
      echo 'done!<br />Initializing logs...';
      
      $q = $db->sql_query('INSERT INTO ' . $_POST['table_prefix'] . 'logs(log_type,action,time_id,date_string,author,page_text,edit_summary) VALUES(\'security\', \'install_enano\', ' . time() . ', \'' . date('d M Y h:i a') . '\', \'' . mysql_real_escape_string($_POST['admin_user']) . '\', \'' . mysql_real_escape_string(ENANO_VERSION) . '\', \'' . mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '\');', $conn);
      if ( !$q )
        err('Error setting up logs: '.$db->get_error());
      
      if ( !$session->get_permissions('clear_logs') )
      {
        echo '<br />Error: session manager won\'t permit flushing logs, these is a bug.';
        break;
      }
      
      // unset($session);
      // $session = new sessionManager();
      // $session->start();
      
      PageUtils::flushlogs('Main_Page', 'Article');
      
      echo 'done!<h3>Installation of Enano is complete.</h3><p>Review any warnings above, and then <a href="install.php?mode=finish">click here to finish the installation</a>.';
      
      // echo '<script type="text/javascript">window.location="'.scriptPath.'/install.php?mode=finish";</script>';
      
    break;
  case "finish":
    echo '<h3>Congratulations!</h3>
           <p>You have finished installing Enano on this server.</p>
          <h3>Now what?</h3>
           <p>Click the link below to see the main page for your website. Where to go from here:</p>
           <ul>
             <li>The first thing you should do is log into your site using the Log in link on the sidebar.</li>
             <li>Go into the Administration panel, expand General, and click General Configuration. There you will be able to configure some basic information about your site.</li>
             <li>Visit the <a href="http://enanocms.org/Category:Plugins" onclick="window.open(this.href); return false;">Enano Plugin Gallery</a> to download and use plugins on your site.</li>
             <li>Periodically create a backup of your database and filesystem, in case something goes wrong. This should be done at least once a week &ndash; more for wiki-based sites.</li>
             <li>Hire some moderators, to help you keep rowdy users tame.</li>
             <li>Tell the <a href="http://enanocms.org/Contact_us">Enano team</a> what you think.</li>
             <li><b>Spread the word about Enano by adding a link to the Enano homepage on your sidebar!</b> You can enable this option in the General Configuration section of the administration panel.</li>
           </ul>
           <p><a href="index.php">Go to your website...</a></p>';
    break;
}
$template->footer();
 
?>

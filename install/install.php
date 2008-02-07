<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * install.php - Main installation interface
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 * 
 * Thanks to Stephan for helping out with l10n in the installer (his work is in includes/stages/*.php).
 */

define('IN_ENANO', 1);
// DEFINE THIS BEFORE RELEASE!
//define('ENANO_DANGEROUS', 1);

require_once('includes/common.php');
@ini_set('display_errors', 'on');

$stages = array('language', 'license', 'sysreqs', 'database', 'website', 'login', 'confirm', 'install', 'finish');
$stage_ids = array();

if ( isset($_POST['language']) )
{
  // Include language lib and additional PHP5-only JSON functions
  require_once( ENANO_ROOT . '/includes/json2.php' );
  require_once( ENANO_ROOT . '/includes/lang.php' );
  
  // We have a language ID - init language
  $lang_id = $_POST['language'];
  if ( !isset($languages[$lang_id]) )
  {
    die('Invalid language selection - can\'t load metadata');
  }
  
  $language_dir = $languages[$lang_id]['dir'];
  
  // Initialize language support
  $lang = new Language($lang_id);
  $lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/install.json');
  $lang_uri = 'install.php?do=lang_js&language=%s';
  
  // Init UI
  $ui = new Enano_Installer_UI($lang->get('meta_site_name'), false);
  
  // Add stages
  foreach ( $stages as $stage )
  {
    $stage_ids[$stage] = $ui->add_stage($lang->get("{$stage}_modetitle"), true);
  }
  
  // Determine stage
  if ( isset($_REQUEST['stage']) && isset($stage_ids[$_REQUEST['stage']]) )
  {
    $ui->set_visible_stage($stage_ids[$_REQUEST['stage']]);
    $stage = $_REQUEST['stage'];
  }
  else
  {
    $stage = 'license';
  }
  
  $stage_num = array_search($stage, $stages);
  if ( $stage_num )
  {
    $stage_num++;
    $ui->step = $lang->get('meta_step', array('step' => $stage_num, 'title' => $lang->get("{$stage}_modetitle_long")));
  }
}
else
{
  $ui = new Enano_Installer_UI('Enano installation', false);
  
  if ( version_compare(PHP_VERSION, '5.0.0', '<') )
  {
    $ui->__construct('Enano installation', false);
  }
  
  $ui->step = 'Step 1: Choose language';
  
  $stage = 'language';
  $stage_ids['language'] = $ui->add_stage('Language', true);
  $stage_ids['license'] = $ui->add_stage('License', true);
  $stage_ids['sysreqs'] = $ui->add_stage('Requirements', true);
  $stage_ids['database'] = $ui->add_stage('Database', true);
  $stage_ids['website'] = $ui->add_stage('Site info', true);
  $stage_ids['login'] = $ui->add_stage('Admin login', true);
  $stage_ids['confirm'] = $ui->add_stage('Review', true);
  $stage_ids['install'] = $ui->add_stage('Install', true);
  $stage_ids['finish'] = $ui->add_stage('Finish', true);
}

// If we don't have PHP 5, show a friendly error message and bail out
if ( version_compare(PHP_VERSION, '5.0.0', '<') || isset($_GET['debug_warn_php4']) )
{
  $ui->set_visible_stage(
    $ui->add_stage('PHP compatibility notice', false)
  );
  $ui->step = '';
  $ui->show_header();

  // This isn't localized because all localization code is dependent on
  // PHP 5 (loading lang.php will throw a parser error under PHP4). This
  // one message probably doesn't need to be localized anyway.
  
  ?>
  <h2 class="heading-error">
    Your server doesn't have support for PHP 5.
  </h2>
  <p>
    PHP 5 is the latest version of the language on which Enano was built. Its many new features have been available since early 2004, yet
    many web hosts have not migrated to it because of the work involved. In 2007, Zend Corporation announced that support for the aging
    PHP 4.x would be discontinued at the end of the year. An initiative called <a href="http://gophp5.org/">GoPHP5</a> was started to
    encourage web hosts to migrate to PHP 5.
  </p>
  <p>
    Because of the industry's decision to not support PHP 4 any longer, the Enano team decided that it was time to begin using the powerful
    features of PHP 5 at the expense of PHP 4 compatibility. Therefore, this version of Enano cannot be installed on your server until it
    is upgraded to at least PHP 5.0.0, and preferably the latest available version.
    <!-- No, not even removing the check in this installer script will help. As soon as the PHP4 check is passed, the installer shows the
         language selection page, after which the language code is loaded. The language code and libjson2 will trigger parse errors under
         PHP <5.0.0. -->
  </p>
  <p>
    If you need to use Enano but can't upgrade your PHP because you're on a shared or reseller hosting service, you can use the
    <a href="http://enanocms.org/download?series=1.0">1.0.x series of Enano</a> on your site. While the Enano team attempts to make this
    older series work on PHP 4, official support is not provided for installations of Enano on PHP 4.
  </p>
  <?php
  
  $ui->show_footer();
  exit();
}

if ( isset($_SERVER['PATH_INFO']) && !isset($_GET['str']) && isset($_GET['do']) )
{
  $_GET['str'] = substr($_SERVER['PATH_INFO'], 1);
}

if ( isset($_GET['do']) )
{
  switch ( $_GET['do'] )
  {
    case 'lang_js':
      if ( !isset($_GET['language']) )
        die();
      $lang_id = $_GET['language'];
      header('Content-type: text/javascript');
      if ( !isset($languages[$lang_id]) )
      {
        die('// Bad language ID');
      }
      $language_dir = $languages[$lang_id]['dir'];
      
      // Include language lib and additional PHP5-only JSON functions
      require_once( ENANO_ROOT . '/includes/json2.php' );
      require_once( ENANO_ROOT . '/includes/lang.php' );
  
      // Initialize language support
      $lang = new Language($lang_id);
      $lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/install.json');
      $lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/core.json');
      $lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/user.json');
      
      $time_now = microtime_float();
      $test = "if ( typeof(enano_lang) != 'object' )
{
  var enano_lang = new Object();
  var enano_lang_code = new Object();
}

enano_lang[{$lang->lang_id}] = " . enano_json_encode($lang->strings) . ";
enano_lang_code[{$lang->lang_id}] = '{$lang->lang_code}';";
      $time_total = round(microtime_float() - $time_now, 4);
      echo "// Generated in $time_total seconds\n";
      echo $test;

      exit();
    case 'modrewrite_test':
      // Include language lib and additional PHP5-only JSON functions
      require_once( ENANO_ROOT . '/includes/json2.php' );
      
      if ( isset($_GET['str']) && in_array($_GET['str'], array('standard', 'shortened', 'rewrite')) )
      {
        echo 'good_' . $_GET['str'];
      }
      else
      {
        echo 'bad';
      }
      exit();
  }
}

switch ( $stage )
{
  default:
    $ui->show_header();
    echo '<p>Invalid stage.</p>';
    break;
  case 'language':
    $ui->show_header();
    ?>
    <h2>Welcome to Enano.</h2>
    <h3>Bienvenido a Enano /
       Wilkommen in Enano /
       Bienvenue à Enano /
       Benvenuti a Enano /
       欢迎 Enano /
       Enano へようこそ。
       </h3>
    <p>
       <b>Please select a language:</b> /
       Por favor, seleccione un idioma: /
       Bitte wählen Sie eine Sprache: /
       S’il vous plaît choisir une langue: /
       Selezionare una lingua: /
       请选择一种语言： /
       言語を選択してください：</p>
    <form action="install.php?stage=license" method="post">
      <select name="language" style="width: 200px;" tabindex="1">
        <?php
        foreach ( $languages as $code => $meta )
        {
          $sel = ( $code == 'eng' ) ? ' selected="selected"' : '';
          echo '<option value="' . $code . '"' . $sel . '>' . $meta['name'] . '</option>';
        }
        ?>
      </select>
      <input tabindex="2" type="submit" value="&gt;&gt;" />
    </form>
    <?php
    break;
  case 'license':
    $ui->show_header();
    require( ENANO_ROOT . '/includes/wikiformat.php' );
    require( ENANO_ROOT . '/install/includes/stages/license.php' );
    break;
  case 'sysreqs':
    $ui->show_header();
    require( ENANO_ROOT . '/install/includes/stages/sysreqs.php' );
    break;
  case 'database':
    if ( isset($_POST['driver']) && in_array($_POST['driver'], $supported_drivers) )
    {
      // This is SAFE! It's validated against the array in in_array() above.
      $driver = $_POST['driver'];
      require( ENANO_ROOT . "/install/includes/stages/database_{$driver}.php" );
    }
    else
    {
      $ui->show_header();
      // No driver selected - give the DB drive selection page
      require( ENANO_ROOT . '/install/includes/stages/database.php' );
    }
    break;
  case 'website':
    require( ENANO_ROOT . '/install/includes/stages/website.php' );
    break;
  case 'login':
    require( ENANO_ROOT . '/install/includes/stages/login.php' );
    break;
  case 'confirm':
    require( ENANO_ROOT . '/install/includes/stages/confirm.php' );
    break;
  case 'install':
    require( ENANO_ROOT . '/install/includes/stages/install.php' );
    break;
  case 'finish':
    require( ENANO_ROOT . '/install/includes/stages/finish.php' );
    break;
}

$ui->show_footer();

?>

<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * Installation package
 * sysreqs.php - Installer system-requirements page
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
	die();

require_once(ENANO_ROOT . '/install/includes/libenanoinstall.php');

global $failed, $warned;

$failed = false;
$warned = false;

function run_test($code, $desc, $extended_desc, $warn = false)
{
	global $failed, $warned;
	static $cv = true;
	$cv = !$cv;
	$val = eval($code);
	if($val)
	{
		if($cv) $color='CCFFCC'; else $color='AAFFAA';
		echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc</td><td style='padding-left: 10px;'><img alt='Test passed' src='../images/check.png' /></td></tr>";
	} elseif(!$val && $warn) {
		if($cv) $color='FFFFCC'; else $color='FFFFAA';
		echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test passed with warning' src='../images/checkunk.png' /></td></tr>";
		$warned = true;
	} else {
		if($cv) $color='FFCCCC'; else $color='FFAAAA';
		echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test failed' src='../images/checkbad.png' /></td></tr>";
		$failed = true;
	}
}
function is_apache()
{
	$r = strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') ? true : false;
	return $r;
}

$warnings = array();
$failed = false;
$have_dbms = false;

// Test: Apache
$req_apache = is_apache() ? 'good' : 'bad';

// Test: PHP
if ( version_compare(PHP_VERSION, '5.2.0', '>=') )
{
	$req_php = 'good';
}
else if ( version_compare(PHP_VERSION, '5.0.0', '>=') )
{
	$warnings[] = $lang->get('sysreqs_req_help_php', array('php_version' => PHP_VERSION));
	$req_php = 'warn';
}
else
{
	$failed = true;
	$req_php = 'bad';
}

// Test: Safe Mode
$req_safemode = !intval(@ini_get('safe_mode'));
if ( !$req_safemode )
{
	$warnings[] = $lang->get('sysreqs_req_help_safemode');
	$failed = true;
}

// Test: MySQL
$req_mysql = function_exists('mysql_connect');
if ( $req_mysql )
	$have_dbms = true;

// Test: PostgreSQL
$req_pgsql = function_exists('pg_connect');
if ( $req_pgsql )
	$have_dbms = true;

if ( !$have_dbms )
	$failed = true;

// Test: File uploads
$req_uploads = intval(@ini_get('file_uploads'));

// Test: ctype validation
$req_ctype = function_exists('ctype_digit');
if ( !$req_ctype )
	$failed = true;

// Writability test: config
$req_config_w = write_test('config.new.php');

// Writability test: .htaccess
$req_htaccess_w = write_test('.htaccess.new');

// Writability test: files
$req_files_w = write_test('files');

// Writability test: cache
$req_cache_w = write_test('cache');

if ( !$req_config_w || !$req_htaccess_w || !$req_files_w || !$req_cache_w )
	$warnings[] = $lang->get('sysreqs_req_help_writable');

if ( !$req_config_w )
	$failed = true;

// Extension test: GD
$req_gd = function_exists('imagecreatefrompng') && function_exists('getimagesize') && function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
if ( !$req_gd )
	$warnings[] = $lang->get('sysreqs_req_help_gd2');

// FS test: ImageMagick
$req_imagick = which('convert');
if ( !$req_imagick )
	$warnings[] = $lang->get('sysreqs_req_help_imagemagick', array('path' => get_system_path()));

$crypto_backend = install_get_crypto_backend();

if ( $crypto_backend == 'none' )
	$warnings[] = $lang->get('sysreqs_req_help_crypto_none');
else if ( $crypto_backend == 'bcmath' )
	$warnings[] = $lang->get('sysreqs_req_help_crypto_bcmath');

?>

<div style="float: right; padding-top: 10px;">
	<form action="install.php?stage=sysreqs" method="post">
	<?php
		echo '<input type="hidden" name="language" value="' . $lang_id . '" />';
	?>
	<button style="display: block; padding-bottom: 3px;">
	<img alt=" " src="images/recheck.png" style="position: relative; top: 3px; left: -2px;" />
		<?php echo $lang->get('sysreqs_btn_refresh'); ?>
	</button>
	</form>
</div>

<h3><?php echo $lang->get('sysreqs_heading'); ?></h3>
 <p><?php echo $lang->get('sysreqs_blurb'); ?></p>
 
<span class="menuclear"></span>

<form action="install.php?stage=database" method="post">
<?php
	echo '<input type="hidden" name="language" value="' . $lang_id . '" />';
?>

<?php
if ( !empty($warnings) ):
?>
	<div class="sysreqs_warning">
		<h3><?php echo $lang->get('sysreqs_summary_warn_title'); ?></h3>
		<p><?php echo $lang->get('sysreqs_summary_warn_body'); ?></p>
		<ul>
			<li><?php echo implode("</li>\n      <li>", $warnings); ?></li>
		</ul>
	</div>
<?php
endif;

if ( !$have_dbms ):
?>
	<div class="sysreqs_error">
		<h3><?php echo $lang->get('sysreqs_err_no_dbms_title'); ?></h3>
		<p><?php echo $lang->get('sysreqs_err_no_dbms_body'); ?></p>
	</div>
<?php
endif;
if ( empty($warnings) && !$failed ):
?>
	<div class="sysreqs_success">
		<h3><?php echo $lang->get('sysreqs_summary_pass_title'); ?></h3>
		<p><?php echo $lang->get('sysreqs_summary_pass_body'); ?></p>
	</div>
	<div style="text-align: center;">
		<input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" />
	</div>
<?php
endif;

if ( $failed ):
?>
	<div class="sysreqs_error">
		<h3><?php echo $lang->get('sysreqs_summary_fail_title'); ?></h3>
		<p><?php echo $lang->get('sysreqs_summary_fail_body'); ?></p>
	</div>
<?php
endif;        
?>

<table border="0" cellspacing="0" cellpadding="0" class="sysreqs">

<tr>
	<th colspan="2"><?php echo $lang->get('sysreqs_heading_serverenv'); ?></th>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_apache'); ?></td>
	<?php
	if ( $req_apache ):
		echo '<td class="good">' . $lang->get('sysreqs_req_found') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_notfound') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_php'); ?></td>
	<td class="<?php echo $req_php; ?>">v<?php echo PHP_VERSION; ?></td>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_safemode'); ?></td>
	<?php
	if ( $req_safemode ):
		echo '<td class="good">' . $lang->get('sysreqs_req_disabled') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_enabled') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_uploads'); ?></td>
	<?php
	if ( $req_uploads ):
		echo '<td class="good">' . $lang->get('sysreqs_req_enabled') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_disabled') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_ctype'); ?></td>
	<?php
	if ( $req_ctype ):
		echo '<td class="good">' . $lang->get('sysreqs_req_supported') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_unsupported') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_crypto'); ?>
	</td>
	<?php
	if ( in_array($crypto_backend, array('bcmath', 'bigint', 'gmp')) )
	{
		echo '<td class="good">' . $lang->get("sysreqs_req_{$crypto_backend}") . '</td>';
	}
	else
	{
		echo '<td class="bad">' . $lang->get("sysreqs_req_notfound") . '</td>';
	}
	?>
</tr>

<!-- Database -->

<tr>
	<th colspan="2"><?php echo $lang->get('sysreqs_heading_dbms'); ?></th>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_mysql'); ?></td>
	<?php
	if ( $req_mysql ):
		echo '<td class="good">' . $lang->get('sysreqs_req_supported') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_notfound') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td><?php echo $lang->get('sysreqs_req_postgresql'); ?></td>
	<?php
	if ( $req_pgsql ):
		echo '<td class="good">' . $lang->get('sysreqs_req_supported') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_notfound') . '</td>';
	endif;
	?>
</tr>

<tr>
	<th colspan="2"><?php echo $lang->get('sysreqs_heading_files'); ?></th>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_config_writable'); ?>
	</td>
	<?php
	if ( $req_config_w ):
		echo '<td class="good">' . $lang->get('sysreqs_req_writable') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_unwritable') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_htaccess_writable'); ?><br />
		<small><?php echo $lang->get('sysreqs_req_hint_htaccess_writable'); ?></small>
	</td>
	<?php
	if ( $req_htaccess_w ):
		echo '<td class="good">' . $lang->get('sysreqs_req_writable') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_unwritable') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_files_writable'); ?>
	</td>
	<?php
	if ( $req_files_w ):
		echo '<td class="good">' . $lang->get('sysreqs_req_writable') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_unwritable') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_cache_writable'); ?>
	</td>
	<?php
	if ( $req_cache_w ):
		echo '<td class="good">' . $lang->get('sysreqs_req_writable') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_unwritable') . '</td>';
	endif;
	?>
</tr>

<tr>
	<th colspan="2"><?php echo $lang->get('sysreqs_heading_images'); ?></th>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_gd2'); ?><br />
		<small><?php echo $lang->get('sysreqs_req_hint_gd2'); ?></small>
	</td>
	<?php
	if ( $req_gd ):
		echo '<td class="good">' . $lang->get('sysreqs_req_supported') . '</td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_notfound') . '</td>';
	endif;
	?>
</tr>

<tr>
	<td>
		<?php echo $lang->get('sysreqs_req_imagemagick'); ?><br />
		<small><?php echo $lang->get('sysreqs_req_hint_imagemagick'); ?></small>
	</td>
	<?php
	if ( $req_imagick ):
		echo '<td class="good">' . $lang->get('sysreqs_req_found') . ' <small>(' . htmlspecialchars($req_imagick) . ')</small></td>';
	else:
		echo '<td class="bad">' . $lang->get('sysreqs_req_notfound') . '</td>';
	endif;
	?>
</tr>

</table>

<?php
if ( !$failed ):
?>
		<table border="0">
		<tr>
			<td>
				<input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" />
			</td>
			<td>
				<p>
					<span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
					&bull; <?php echo $lang->get('sysreqs_objective_scalebacks'); ?><br />
					&bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
				</p>
			</td>
		</tr>
		</table>
<?php
endif;
?>
</form>

<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * Installation package
 * common.php - Installer common functions
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// Our version number. This needs to be changed for any custom releases.
$installer_version = array(
	'version' => '1.1.8',
	'type' => 'beta'
	// If type is set to "rc", "beta", or "alpha", optionally another version number can be issued with the key 'sub':
	// 'sub' => '3' will produce Enano 1.1.1a3 / Enano 1.1.1 alpha 3
);

function installer_enano_version($long = false)
{
	global $installer_version;
	static $keywords = array(
		'alpha' => 'a',
		'beta' => 'b',
		'RC' => 'rc'
		);
	$v = $installer_version['version'];
	if ( isset($installer_version['sub']) )
	{
		$v .= ( !$long ) ? $keywords[$installer_version['type']] : " {$installer_version['type']} ";
		$v .= $installer_version['sub'];
	}
	return $v;
}
 
// Determine Enano root directory

if ( !defined('ENANO_ROOT') )
{
	$enano_root = dirname(dirname(dirname(__FILE__)));
	if ( preg_match('#/repo$#', $enano_root) && file_exists("$enano_root/../.enanodev") )
	{
		$enano_root = preg_replace('#/repo$#', '', $enano_root);
	}
	
	define('ENANO_ROOT', $enano_root);
}

chdir(ENANO_ROOT);

// Determine our scriptPath
if ( isset($_SERVER['REQUEST_URI']) && !defined('scriptPath') )
{
	// Use reverse-matching to determine where the REQUEST_URI overlaps the Enano root.
	$requri = $_SERVER['REQUEST_URI'];
	if ( isset($_SERVER['PATH_INFO']) && !preg_match('/index\.php$/', $_SERVER['PATH_INFO']) )
	{
		$requri = preg_replace(';' . preg_quote($_SERVER['PATH_INFO']) . '$;', '', $requri);
	}
	if ( !preg_match('/\.php$/', $requri) )
	{
		// user requested http://foo/enano as opposed to http://foo/enano/index.php
		$requri .= '/index.php';
	}
	$sp = dirname($_SERVER['REQUEST_URI']);
	if ( $sp == '/' || $sp == '\\' )
	{
		$sp = '';
	}
	$sp = preg_replace('#/install$#', '', $sp);
	define('scriptPath', $sp);
}

// is Enano already installed?
@include(ENANO_ROOT . '/config.php');
if ( defined('ENANO_INSTALLED') && defined('ENANO_DANGEROUS') && !isset($_GET['debug_warn_php4']) )
{
	$title = 'Installation locked';
	require('includes/common.php');
	$template->header();
	echo '<p>The installer has detected that an installation of Enano already exists on your server. You MUST delete config.php if you wish to reinstall Enano.</p>';
	$template->footer();
	exit();
}

if ( !function_exists('microtime_float') )
{
	function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
}

define('IN_ENANO_INSTALL', 1);

require_once(ENANO_ROOT . '/install/includes/ui.php');
require_once(ENANO_ROOT . '/includes/functions.php');
require_once(ENANO_ROOT . '/includes/json.php');
require_once(ENANO_ROOT . '/includes/constants.php');
require_once(ENANO_ROOT . '/includes/rijndael.php');
require_once(ENANO_ROOT . '/includes/hmac.php');

// If we have at least PHP 5, load json2
if ( version_compare(PHP_VERSION, '5.0.0', '>=') )
{
	require_once(ENANO_ROOT . '/includes/json2.php');
}

strip_magic_quotes_gpc();

// Build a list of available languages
$dir = @opendir( ENANO_ROOT . '/language' );
if ( !$dir )
	die('CRITICAL: could not open language directory');

$languages = array();
// Use the old PHP4-compatible JSON decoder
$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);

while ( $dh = @readdir($dir) )
{
	if ( $dh == '.' || $dh == '..' )
		continue;
	if ( file_exists( ENANO_ROOT . "/language/$dh/meta.json" ) )
	{
		// Found a language directory, determine metadata
		$meta = @file_get_contents( ENANO_ROOT . "/language/$dh/meta.json" );
		if ( empty($meta) )
			// Could not read metadata file, continue silently
			continue;
		$meta = $json->decode($meta);
		if ( isset($meta['lang_name_english']) && isset($meta['lang_name_native']) && isset($meta['lang_code']) )
		{
			$languages[$meta['lang_code']] = array(
					'name' => $meta['lang_name_native'],
					'name_eng' => $meta['lang_name_english'],
					'dir' => $dh
				);
		}
	}
}

if ( count($languages) < 1 )
{
	die('The Enano installer couldn\'t find any languages in the language/ folder. Enano needs at least one language in this folder to load and install properly.');
}

// List of available DB drivers
$supported_drivers = array('mysql', 'postgresql');

// Divert to CLI loader if running from CLI
if ( isset($argc) && isset($argv) )
{
	if ( is_int($argc) && is_array($argv) && !isset($_SERVER['REQUEST_URI']) )
	{
		define('ENANO_CLI', '');
	}
}

?>

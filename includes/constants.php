<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * constants.php - important defines used Enano-wide
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// Ban types

define('BAN_IP', 1);
define('BAN_USER', 2);
define('BAN_EMAIL', 3);

// ACL permission types
define('AUTH_ALLOW', 4);
define('AUTH_WIKIMODE', 3); // User can do this if wiki mode is enabled
define('AUTH_DISALLOW', 2);
define('AUTH_DENY', 1);     // A Deny setting overrides *everything*

define('ACL_TYPE_GROUP', 1);
define('ACL_TYPE_USER', 2);
define('ACL_TYPE_PRESET', 3);

// System groups
define('GROUP_ID_ADMIN', 2);
define('GROUP_ID_MOD', 3);

// Page group types
define('PAGE_GRP_CATLINK', 1);
define('PAGE_GRP_TAGGED', 2);
define('PAGE_GRP_NORMAL', 3);
define('PAGE_GRP_REGEX', 4);

// Identifier for the default meta-language
define('LANG_DEFAULT', 0);

//
// User types - don't touch these
//

// User can do absolutely everything
define('USER_LEVEL_ADMIN', 9);

// User can edit/[un]approve comments and do some basic administration
define('USER_LEVEL_MOD', 5);

// Default for members. When authed at this level, the user can change his/her password.
define('USER_LEVEL_CHPREF', 3);

// The level that you will be running at most of the time
define('USER_LEVEL_MEMBER', 2);

// Special level for guests
define('USER_LEVEL_GUEST', 1);

// Group status

define('GROUP_CLOSED', 1);
define('GROUP_REQUEST', 2);
define('GROUP_HIDDEN', 3);
define('GROUP_OPEN', 4);

// Other stuff

define('MAX_PMS_PER_BATCH', 7); // The maximum number of users that users can send PMs to in one go; restriction does not apply to users with mod_misc rights
define('SEARCH_RESULTS_PER_PAGE', 10);
define('MYSQL_MAX_PACKET_SIZE', 1048576); // 1MB; this is the default in MySQL 4.x I think

// Sidebar

define('BLOCK_WIKIFORMAT', 0);
define('BLOCK_TEMPLATEFORMAT', 1);
define('BLOCK_HTML', 2);
define('BLOCK_PHP', 3);
define('BLOCK_PLUGIN', 4);
define('SIDEBAR_LEFT', 1);
define('SIDEBAR_RIGHT', 2);

define('GENERAL_ERROR', 'General error');
define('GENERAL_NOTICE', 'Information');
define('CRITICAL_ERROR', 'Critical error');

// You can un-comment the next line to require database backups to be encrypted using the site's unique key.
// This keeps the file safe in transit, but also prevents any type of editing to the file. This is NOT
// recommended except for tiny sites because encrypting an average of 2MB of data will take a while.
// define('SQL_BACKUP_CRYPT', '');

// Security

// AES cipher strength - defaults to 192 and cannot be changed after installation.
// This can be 128, 192, or 256.
define('AES_BITS', 192);

// Define this to enable Mcrypt support which makes encryption work faster. This is only triggered if Mcrypt support is detected.
// THIS IS DISABLED BECAUSE MCRYPT DOES NOT SEEM TO SUPPORT THE AES BLOCK SIZES THAT ENANO USES.
//define('MCRYPT_ACCEL', '');

//if(defined('MCRYPT_RIJNDAEL_' . AES_BITS))
//{
//  eval('$bs = MCRYPT_RIJNDAEL_' . AES_BITS . ';');
//  $bs = mcrypt_module_get_algo_block_size($bs);
//  $bs = $bs * 8;
//  define('AES_BLOCKSIZE', $bs);
//}
// else
// {
//   define('AES_BLOCKSIZE', AES_BITS);
// }

define('AES_BLOCKSIZE', 128);

/*
 * MIMETYPES
 *
 * This array defines the 166 known MIME types used by the Enano file-extension filter. Whether extensions are allowed or not is
 * determined by a bitfield in the config table.
 */

global $mime_types, $mimetype_exps, $mimetype_extlist;

// IMPORTANT: this array can NEVER have items removed from it or key indexes changed
$mime_types = Array (
  'ai'      => 'application/postscript',
  'aif'     => 'audio/x-aiff',
  'aifc'    => 'audio/x-aiff',
  'aiff'    => 'audio/x-aiff',
  'au'      => 'audio/basic',
  'avi'     => 'video/x-msvideo',
  'bcpio'   => 'application/x-bcpio',
  'bin'     => 'application/octet-stream',
  'bmp'     => 'image/bmp',
  'bz2'     => 'application/x-bzip',
  'cdf'     => 'application/x-netcdf',
  'cgm'     => 'image/cgm',
  'class'   => 'application/octet-stream',
  'cpio'    => 'application/x-cpio',
  'cpt'     => 'application/mac-compactpro',
  'csh'     => 'application/x-csh',
  'css'     => 'text/css',
  'dcr'     => 'application/x-director',
  'dir'     => 'application/x-director',
  'djv'     => 'image/vnd.djvu',
  'djvu'    => 'image/vnd.djvu',
  'dll'     => 'application/octet-stream',
  'dms'     => 'application/octet-stream',
  'doc'     => 'application/msword',
  'dtd'     => 'application/xml-dtd',
  'dvi'     => 'application/x-dvi',
  'dxr'     => 'application/x-director',
  'eps'     => 'application/postscript',
  'etx'     => 'text/x-setext',
  'exe'     => 'application/octet-stream',
  'ez'      => 'application/andrew-inset',
  'gif'     => 'image/gif',
  'gram'    => 'application/srgs',
  'grxml'   => 'application/srgs+xml',
  'gtar'    => 'application/x-gtar',
  'gz'      => 'application/x-gzip',
  'hdf'     => 'application/x-hdf',
  'hqx'     => 'application/mac-binhex40',
  'htm'     => 'text/html',
  'html'    => 'text/html',
  'ice'     => 'x-conference/x-cooltalk',
  'ico'     => 'image/x-icon',
  'ics'     => 'text/calendar',
  'ief'     => 'image/ief',
  'ifb'     => 'text/calendar',
  'iges'    => 'model/iges',
  'igs'     => 'model/iges',
  'jar'     => 'application/zip',
  'jpe'     => 'image/jpeg',
  'jpeg'    => 'image/jpeg',
  'jpg'     => 'image/jpeg',
  'js'      => 'application/x-javascript',
  'kar'     => 'audio/midi',
  'latex'   => 'application/x-latex',
  'lha'     => 'application/octet-stream',
  'lzh'     => 'application/octet-stream',
  'm3u'     => 'audio/x-mpegurl',
  'man'     => 'application/x-troff-man',
  'mathml'  => 'application/mathml+xml',
  'me'      => 'application/x-troff-me',
  'mesh'    => 'model/mesh',
  'mid'     => 'audio/midi',
  'midi'    => 'audio/midi',
  'mif'     => 'application/vnd.mif',
  'mov'     => 'video/quicktime',
  'movie'   => 'video/x-sgi-movie',
  'mp2'     => 'audio/mpeg',
  'mp3'     => 'audio/mpeg',
  'mpe'     => 'video/mpeg',
  'mpeg'    => 'video/mpeg',
  'mpg'     => 'video/mpeg',
  'mpga'    => 'audio/mpeg',
  'ms'      => 'application/x-troff-ms',
  'msh'     => 'model/mesh',
  'mxu'     => 'video/vnd.mpegurl',
  'nc'      => 'application/x-netcdf',
  'oda'     => 'application/oda',
  'ogg'     => 'application/ogg',
  'ogm'     => 'application/ogg',
  'pbm'     => 'image/x-portable-bitmap',
  'pdb'     => 'chemical/x-pdb',
  'pdf'     => 'application/pdf',
  'pgm'     => 'image/x-portable-graymap',
  'pgn'     => 'application/x-chess-pgn',
  'png'     => 'image/png',
  'pnm'     => 'image/x-portable-anymap',
  'ppm'     => 'image/x-portable-pixmap',
  'ppt'     => 'application/vnd.ms-powerpoint',
  'ps'      => 'application/postscript',
  'psd'     => 'image/x-photoshop',
  'qt'      => 'video/quicktime',
  'ra'      => 'audio/x-realaudio',
  'ram'     => 'audio/x-pn-realaudio',
  'ras'     => 'image/x-cmu-raster',
  'rdf'     => 'text/xml',
  'rgb'     => 'image/x-rgb',
  'rm'      => 'audio/x-pn-realaudio',
  'roff'    => 'application/x-troff',
  'rpm'     => 'audio/x-pn-realaudio-plugin',
  'rss'     => 'text/xml',
  'rtf'     => 'text/rtf',
  'rtx'     => 'text/richtext',
  'sgm'     => 'text/sgml',
  'sgml'    => 'text/sgml',
  'sh'      => 'application/x-sh',
  'shar'    => 'application/x-shar',
  'silo'    => 'model/mesh',
  'sit'     => 'application/x-stuffit',
  'skd'     => 'application/x-koan',
  'skm'     => 'application/x-koan',
  'skp'     => 'application/x-koan',
  'skt'     => 'application/x-koan',
  'smi'     => 'application/smil',
  'smil'    => 'application/smil',
  'snd'     => 'audio/basic',
  'so'      => 'application/octet-stream',
  'spl'     => 'application/x-futuresplash',
  'src'     => 'application/x-wais-source',
  'stc'     => 'application/zip',
  'std'     => 'application/zip',
  'sti'     => 'application/zip',
  'stm'     => 'application/zip',
  'stw'     => 'application/zip',
  'sv4cpio' => 'application/x-sv4cpio',
  'sv4crc'  => 'application/x-sv4crc',
  'svg'     => 'image/svg+xml',
  'swf'     => 'application/x-shockwave-flash',
  'sxc'     => 'application/zip',
  'sxd'     => 'application/zip',
  'sxi'     => 'application/zip',
  'sxm'     => 'application/zip',
  'sxw'     => 'application/zip',
  't'       => 'application/x-troff',
  'tar'     => 'application/x-tar',
  'tcl'     => 'application/x-tcl',
  'tex'     => 'application/x-tex',
  'texi'    => 'application/x-texinfo',
  'texinfo' => 'application/x-texinfo',
  'tif'     => 'image/tiff',
  'tiff'    => 'image/tiff',
  'tr'      => 'application/x-troff',
  'tsv'     => 'text/tab-separated-values',
  'txt'     => 'text/plain',
  'ustar'   => 'application/x-ustar',
  'vcd'     => 'application/x-cdlink',
  'vrml'    => 'model/vrml',
  'vxml'    => 'application/voicexml+xml',
  'wav'     => 'audio/x-wav',
  'wbmp'    => 'image/vnd.wap.wbmp',
  'wbxml'   => 'application/vnd.wap.wbxml',
  'wml'     => 'text/vnd.wap.wml',
  'wmlc'    => 'application/vnd.wap.wmlc',
  'wmls'    => 'text/vnd.wap.wmlscript',
  'wmlsc'   => 'application/vnd.wap.wmlscriptc',
  'wrl'     => 'model/vrml',
  'xbm'     => 'image/x-xbitmap',
  'xcf'     => 'image/xcf',
  'xht'     => 'application/xhtml+xml',
  'xhtml'   => 'application/xhtml+xml',
  'xls'     => 'application/vnd.ms-excel',
  'xml'     => 'text/xml',
  'xpi'     => 'application/zip',
  'xpm'     => 'image/x-xpixmap',
  'xsl'     => 'text/xml',
  'xslt'    => 'text/xml',
  'xwd'     => 'image/x-xwindowdump',
  'xyz'     => 'chemical/x-xyz',
  'zip'     => 'application/zip',
);

$mimetype_extlist = Array(
  'application/andrew-inset'=>'ez',
  'application/mac-binhex40'=>'hqx',
  'application/mac-compactpro'=>'cpt',
  'application/mathml+xml'=>'mathml',
  'application/msword'=>'doc',
  'application/octet-stream'=>'bin dms lha lzh exe class so dll',
  'application/oda'=>'oda',
  'application/ogg'=>'ogg ogm',
  'application/pdf'=>'pdf',
  'application/postscript'=>'ai eps ps',
  'application/rdf+xml'=>'rdf',
  'application/smil'=>'smi smil',
  'application/srgs'=>'gram',
  'application/srgs+xml'=>'grxml',
  'application/vnd.mif'=>'mif',
  'application/vnd.ms-excel'=>'xls',
  'application/vnd.ms-powerpoint'=>'ppt',
  'application/vnd.wap.wbxml'=>'wbxml',
  'application/vnd.wap.wmlc'=>'wmlc',
  'application/vnd.wap.wmlscriptc'=>'wmlsc',
  'application/voicexml+xml'=>'vxml',
  'application/x-bcpio'=>'bcpio',
  'application/x-bzip'=>'gz bz2',
  'application/x-cdlink'=>'vcd',
  'application/x-chess-pgn'=>'pgn',
  'application/x-cpio'=>'cpio',
  'application/x-csh'=>'csh',
  'application/x-director'=>'dcr dir dxr',
  'application/x-dvi'=>'dvi',
  'application/x-futuresplash'=>'spl',
  'application/x-gtar'=>'gtar tar',
  'application/x-gzip'=>'gz',
  'application/x-hdf'=>'hdf',
  'application/x-jar'=>'jar',
  'application/x-javascript'=>'js',
  'application/x-koan'=>'skp skd skt skm',
  'application/x-latex'=>'latex',
  'application/x-netcdf'=>'nc cdf',
  'application/x-sh'=>'sh',
  'application/x-shar'=>'shar',
  'application/x-shockwave-flash'=>'swf',
  'application/x-stuffit'=>'sit',
  'application/x-sv4cpio'=>'sv4cpio',
  'application/x-sv4crc'=>'sv4crc',
  'application/x-tar'=>'tar',
  'application/x-tcl'=>'tcl',
  'application/x-tex'=>'tex',
  'application/x-texinfo'=>'texinfo texi',
  'application/x-troff'=>'t tr roff',
  'application/x-troff-man'=>'man',
  'application/x-troff-me'=>'me',
  'application/x-troff-ms'=>'ms',
  'application/x-ustar'=>'ustar',
  'application/x-wais-source'=>'src',
  'application/x-xpinstall'=>'xpi',
  'application/xhtml+xml'=>'xhtml xht',
  'application/xslt+xml'=>'xslt',
  'application/xml'=>'xml xsl',
  'application/xml-dtd'=>'dtd',
  'application/zip'=>'zip jar xpi  sxc stc  sxd std   sxi sti   sxm stm   sxw stw  ',
  'audio/basic'=>'au snd',
  'audio/midi'=>'mid midi kar',
  'audio/mpeg'=>'mpga mp2 mp3',
  'audio/ogg'=>'ogg ',
  'audio/x-aiff'=>'aif aiff aifc',
  'audio/x-mpegurl'=>'m3u',
  'audio/x-ogg'=>'ogg ',
  'audio/x-pn-realaudio'=>'ram rm',
  'audio/x-pn-realaudio-plugin'=>'rpm',
  'audio/x-realaudio'=>'ra',
  'audio/x-wav'=>'wav',
  'chemical/x-pdb'=>'pdb',
  'chemical/x-xyz'=>'xyz',
  'image/bmp'=>'bmp',
  'image/cgm'=>'cgm',
  'image/gif'=>'gif',
  'image/ief'=>'ief',
  'image/jpeg'=>'jpeg jpg jpe',
  'image/png'=>'png',
  'image/svg+xml'=>'svg',
  'image/tiff'=>'tiff tif',
  'image/vnd.djvu'=>'djvu djv',
  'image/vnd.wap.wbmp'=>'wbmp',
  'image/x-cmu-raster'=>'ras',
  'image/x-icon'=>'ico',
  'image/x-portable-anymap'=>'pnm',
  'image/x-portable-bitmap'=>'pbm',
  'image/x-portable-graymap'=>'pgm',
  'image/x-portable-pixmap'=>'ppm',
  'image/x-rgb'=>'rgb',
  'image/x-photoshop'=>'psd',
  'image/x-xbitmap'=>'xbm',
  'image/x-xpixmap'=>'xpm',
  'image/x-xwindowdump'=>'xwd',
  'model/iges'=>'igs iges',
  'model/mesh'=>'msh mesh silo',
  'model/vrml'=>'wrl vrml',
  'text/calendar'=>'ics ifb',
  'text/css'=>'css',
  'text/html'=>'html htm',
  'text/plain'=>'txt',
  'text/richtext'=>'rtx',
  'text/rtf'=>'rtf',
  'text/sgml'=>'sgml sgm',
  'text/tab-separated-values'=>'tsv',
  'text/vnd.wap.wml'=>'wml',
  'text/vnd.wap.wmlscript'=>'wmls',
  'text/xml'=>'xml xsl xslt rss rdf',
  'text/x-setext'=>'etx',
  'video/mpeg'=>'mpeg mpg mpe',
  'video/ogg'=>'ogm ogg',
  'video/quicktime'=>'qt mov',
  'video/vnd.mpegurl'=>'mxu',
  'video/x-msvideo'=>'avi',
  'video/x-ogg'=>'ogm ogg',
  'video/x-sgi-movie'=>'movie',
  'x-conference/x-cooltalk'=>'ice',
  // Added for Enano
  'image/xcf' => 'xcf xcfbz2 xcf.bz2',
);

$k = array_keys($mime_types);
$mimetype_exps = Array();
foreach($k as $s => $x)
{
  $mimetype_exps[$x] = pow(2, $s);
}

unset($k, $s, $x);

/*
 * Enano - an open source wiki-like CMS
 * Copyright (C) 2006-2007 Dan Fuhry
 * Javascript client library
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 * For more information about Enano, please visit http://enanocms.org/.
 * Unless otherwise noted, all of the code in these script files may be used freely so long as the above license block
 * is displayed and your modified code is distributed in compliance with the GPL. See the special page "About Enano" on
 * this website for more information.
 */

if(typeof title != 'string')
{
  alert('There was a problem loading the PHP-generated Javascript variables that control parameters for AJAX applets. Most on-page functionality will be very badly broken.\n\nTheme developers, ensure that you are using {JS_DYNAMIC_VARS} *before* you include jsres.php.');
}

// Run-time variables

var detect = navigator.userAgent.toLowerCase();
var IE;
var is_Safari;

// Detect whether the user is running the Evil One or not...

function checkIt(string) {
  place = detect.indexOf(string) + 1;
  thestring = string;
  return place;
}
if (checkIt('msie')) IE = true;
else IE = false;

var is_Opera = ( checkIt('opera') ) ? true : false;
var is_iPhone = ( checkIt('iphone') || checkIt('ipod') ) ? true : false;
var is_firefox2 = ( checkIt('firefox/2.') ) ? true : false;

var KILL_SWITCH = false;

if ( IE )
{
  var version = window.navigator.appVersion;
  version = version.substr( ( version.indexOf('MSIE') + 5 ) );
  var rawversion = '';
  for ( var i = 0; i < version.length; i++ )
  {
    var chr = version.substr(i, 1);
    if ( !chr.match(/[0-9\.]/) )
    {
      break;
    }
    rawversion += chr;
  }
  rawversion = parseInt(rawversion);
  if ( rawversion < 6 )
  {
    KILL_SWITCH = true;
  }
}

// dummy tinyMCE object
var tinyMCE = new Object();

if ( typeof(DISABLE_MCE) == undefined )
{
  var DISABLE_MCE = false;
}

// Obsolete JSON kill switch
function disableJSONExts() { };

is_Safari = checkIt('safari') ? true : false;

var cmt_open;
var list;
var edit_open = false;
var catlist = new Array();
var arrDiff1Buttons = new Array();
var arrDiff2Buttons = new Array();
var arrTimeIdList   = new Array();
var list;
var unObj;
var unSelectMenuOn = false;
var unObjDivCurrentId = false;
var unObjCurrentSelection = false;
var userlist = new Array();
var submitAuthorized = true;
var rDnsObj;
var rDnsBannerObj;
var ns4 = document.layers;
var op5 = (navigator.userAgent.indexOf("Opera 5")!=-1) ||(navigator.userAgent.indexOf("Opera/5")!=-1);
var op6 = (navigator.userAgent.indexOf("Opera 6")!=-1) ||(navigator.userAgent.indexOf("Opera/6")!=-1);
var agt=navigator.userAgent.toLowerCase();
var mac = (agt.indexOf("mac")!=-1);
var ie = (agt.indexOf("msie") != -1);
var mac_ie = mac && ie;
var mouseX = 0;
var mouseY = 0;
var menuheight;
var inertiabase = 1;
var inertiainc = 1;
var slideintervalinc = 20;
var inertiabaseoriginal = inertiabase;
var heightnow;
var targetheight;
var block;
var slideinterval;
var divheights = new Array();
var __menutimeout = false;
var startmouseX = false;
var startmouseY = false;
var startScroll = false;
var is_dragging = false;
var current_ta  = false;
var startwidth  = false;
var startheight = false;
var do_width    = false;
var ajax_load_icon = scriptPath + '/images/loading.gif';
var editor_use_modal_window = false;

// You have an NSIS coder in your midst...
var MB_OK = 1;
var MB_OKCANCEL = 2;
var MB_YESNO = 4;
var MB_YESNOCANCEL = 8;
var MB_ABORTRETRYIGNORE = 16;
var MB_ICONINFORMATION = 32;
var MB_ICONEXCLAMATION = 64;
var MB_ICONSTOP = 128;
var MB_ICONQUESTION = 256;
var MB_ICONLOCK = 512;

// Syntax:
// messagebox(MB_OK|MB_ICONINFORMATION, 'Title', 'Text');
// :-D

var main_css = document.getElementById('mdgCss').href;
if(main_css.indexOf('?') > -1) {
  sep = '&';
} else sep = '?';
var _css = false;
var print_css = main_css + sep + 'printable';

var shift;

function makeUrl(page, query, html_friendly)
{
  url = contentPath+page;
  if(url.indexOf('?') > 0) sep = '&';
  else sep = '?';
  if(query)
  {
    url = url + sep + query;
  }
  if(html_friendly)
  {
    url = url.replace('&', '&amp;');
    url = url.replace('<', '&lt;');
    url = url.replace('>', '&gt;');
  }
  return url;
}

function makeUrlNS(namespace, page, query, html_friendly)
{
  var url = contentPath+namespace_list[namespace]+(page.replace(/ /g, '_'));
  if(url.indexOf('?') > 0) sep = '&';
  else sep = '?';
  if(query)
  {
    url = url + sep + query;
  }
  if(html_friendly)
  {
    url = url.replace('&', '&amp;');
    url = url.replace('<', '&lt;');
    url = url.replace('>', '&gt;');
  }
  return append_sid(url);
}

function strToPageID(string)
{
  // Convert Special:UploadFile to ['UploadFile', 'Special'], but convert 'Image:Enano.png' to ['Enano.png', 'File']
  for(var i in namespace_list)
    if(namespace_list[i] != '')
      if(namespace_list[i] == string.substr(0, namespace_list[i].length))
        return [string.substr(namespace_list[i].length), i];
  return [string, 'Article'];
}

function append_sid(url)
{
  sep = ( url.indexOf('?') > 0 ) ? '&' : '?';
  if(ENANO_SID.length > 10)
  {
    url = url + sep + 'auth=' + ENANO_SID;
    sep = '&';
  }
  if ( pagepass.length > 0 )
  {
    url = url + sep + 'pagepass=' + pagepass;
  }
  return url;
}

var stdAjaxPrefix = append_sid(scriptPath+'/ajax.php?title='+title);

var $_REQUEST = new Object();
if ( window.location.hash )
{
  var hash = String(window.location.hash);
  hash = hash.substr(1);
  var reqobj = hash.split(';');
  var a, b;
  for ( var i = 0; i < reqobj.length; i++ )
  {
    a = reqobj[i].substr(0, reqobj[i].indexOf(':'));
    b = reqobj[i].substr( ( reqobj[i].indexOf(':') + 1 ) );
    $_REQUEST[a] = b;
  }
}

var onload_hooks = new Array();

function addOnloadHook(func)
{
  if ( typeof ( func ) == 'function' )
  {
    if ( typeof(onload_hooks.push) == 'function' )
    {
      onload_hooks.push(func);
    }
    else
    {
      onload_hooks[onload_hooks.length] = func;
    }
  }
}

function runOnloadHooks(e)
{
  var _errorTrapper = 0;
  for ( var _oLc = 0; _oLc < onload_hooks.length; _oLc++ )
  {
    _errorTrapper++;
    if ( _errorTrapper >= 1000 )
      break;
    var _f = onload_hooks[_oLc];
    if ( typeof(_f) == 'function' )
    {
      _f(e);
    }
  }
}

var head = document.getElementsByTagName('head')[0];
if ( !KILL_SWITCH && !DISABLE_MCE )
{
  if ( IE )
  {
    document.write('<script type="text/javascript" src="' + scriptPath + '/includes/clientside/tinymce/tiny_mce.js"></script>');
  }
  else
  {
    var script = document.createElement('script');
    script.type="text/javascript";
    script.src=scriptPath+"/includes/clientside/tinymce/tiny_mce_gzip.js";
    script.onload = function(e)
    {
      tinyMCE_GZ.init(enano_tinymce_gz_options);
    }
    head.appendChild(script);
  }
}

var script = document.createElement('script');
script.type="text/javascript";
script.src=scriptPath+"/includes/clientside/firebug/firebug.js";
head.appendChild(script);

// placeholder for window.console - used if firebug isn't present
// http://getfirebug.com/firebug/firebugx.js
if (!window.console || !console.firebug)
{
    var names = ["log", "debug", "info", "warn", "error", "assert", "dir", "dirxml",
    "group", "groupEnd", "time", "timeEnd", "count", "trace", "profile", "profileEnd"];

    window.console = {};
    for (var i = 0; i < names.length; ++i)
        window.console[names[i]] = function() {}
}

// safari has window.console but not the .debug() method
if ( is_Safari && !window.console.debug )
{
  window.console.debug = function() {};
}

// Do not remove the following comments, they are used by jsres.php.
/*!START_INCLUDER*/

// Start loading files
// The string from the [ to the ] needs to be valid JSON, it's parsed by jsres.php.
var thefiles = [
  'dynano.js',
  'misc.js',
  'login.js',
  'admin-menu.js',
  'ajax.js',
  'autocomplete.js',
  'autofill.js',
  'base64.js',
  'dropdown.js',
  'faders.js',
  'fat.js',
  'grippy.js',
  'json.js',
  'md5.js',
  'libbigint.js',
  'enanomath.js',
  'diffiehellman.js',
  'sha256.js',
  'sliders.js',
  'toolbar.js',
  'rijndael.js',
  'l10n.js',
  'template-compiler.js',
  'acl.js',
  'comments.js',
  'editor.js',
  'flyin.js',
  'paginate.js',
  'pwstrength.js',
  'theme-manager.js',
  'rank-manager.js',
  'SpryEffects.js',
  'loader.js'
];

var problem_scripts = {
  'json.js' : true,
  'template-compiler.js' : true
};

for(var f in thefiles)
{
  if ( typeof(thefiles[f]) != 'string' )
    continue;
  var script = document.createElement('script');
  script.type="text/javascript";
  if ( problem_scripts[thefiles[f]] && KILL_SWITCH )
  {
    // alert('kill switch and problem script');
    continue;
  }
  script.src=scriptPath+"/includes/clientside/static/"+thefiles[f];
  head.appendChild(script);
}

// Do not remove the following comment, it is used by jsres.php.
/*!END_INCLUDER*/

addOnloadHook(function() {
  if ( $_REQUEST['do'] )
  {
    var act = $_REQUEST['do'];
    switch(act)
    {
      case 'comments':
        ajaxComments();
        break;
      case 'edit':
        ajaxEditor();
        break;
      case 'login':
        ajaxStartLogin();
        break;
      case 'history':
        ajaxHistory();
        break;
      case 'catedit':
        ajaxCatEdit();
        break;
    }
  }
});

//*/

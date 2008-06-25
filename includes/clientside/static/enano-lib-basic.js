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

is_Safari = checkIt('safari') ? true : false;

var cmt_open;
var editor_open = false;
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
var timelist = [];
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
var Spry = {};

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

// Can be set to true by slow themes (St. Patty)
if ( typeof(pref_disable_js_fx) != 'boolean' )
{
  var pref_disable_js_fx = false;
}
var aclDisableTransitionFX = ( is_firefox2 || pref_disable_js_fx ) ? true : false;

// Syntax:
// messagebox(MB_OK|MB_ICONINFORMATION, 'Title', 'Text');
// :-D

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

if ( !onload_hooks )
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

var loaded_components = {};
function load_component(file)
{
  if ( !file.match(/\.js$/) )
    file = file + '.js';
  
  console.info('Loading component %s via AJAX', file.replace(/\.js$/, ''));
  
  if ( loaded_components[file] )
  {
    // already loaded
    return true;
  }
  
  load_show_win(file);
  
  // get an XHR instance
  var ajax = ajaxMakeXHR();
  
  var uri = scriptPath + '/includes/clientside/static/' + file;
  ajax.open('GET', uri, false);
  ajax.send(null);
  if ( ajax.readyState == 4 && ajax.status == 200 )
  {
    onload_hooks = new Array();
    eval_global(ajax.responseText);
    load_hide_win();
    runOnloadHooks();
  }
  
  loaded_components[file] = true;
  return true;
}

function load_show_win(file)
{
  var img = '<img style="margin-right: 5px" src="' + scriptPath + '/images/loading.gif" />';
  if ( document.getElementById('_js_load_component') )
  {
    document.getElementById('_js_load_component').innerHTML = img + msg_loading_component.replace('%component%', file);
    return;
  }
  file = file.replace(/\.js$/, '');
  var ld = document.createElement('div');
  ld.style.padding = '10px';
  ld.style.height = '12px';
  ld.style.position = 'fixed';
  ld.style.right = '5px';
  ld.style.bottom = '0px';
  ld.innerHTML = img + msg_loading_component.replace('%component%', file);
  ld.id = '_js_load_component';
  
  ld.style.backgroundImage = 'url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAAlwSFlzAAALEwAACxMBAJqcGAAAAA1JREFUCNdj+P///xkACcgDypG+nnEAAAAASUVORK5CYII=)';
  
  document.body.appendChild(ld);
}

function load_hide_win()
{
  var ld = document.getElementById('_js_load_component');
  ld.parentNode.removeChild(ld);
}

// evaluate a snippet of code in the global context, used for dynamic component loading
// from: http://dean.edwards.name/weblog/2006/11/sandbox/
function eval_global(_jsString)
{
  if (typeof _jsString != "string")
  {
    return false;
  }

  // Check whether window.eval executes code in the global scope.
  window.eval("var __INCLUDE_TEST_1__ = true;");
  if (typeof window.__INCLUDE_TEST_1__ != "undefined")
  {
    delete window.__INCLUDE_TEST_1__;
    window.eval(_jsString);
  }
  else if (typeof window.execScript != "undefined")	// IE only
  {
    window.execScript(_jsString);
  }
  else
  {
    // Test effectiveness of creating a new SCRIPT element and adding it to the document.
    this._insertScriptTag = function (_jsCode) {
      var _script = document.createElement("script");
      _script.type = "text/javascript";
      _script.defer = false;
      _script.text = _jsCode;
      var _headNodeSet = document.getElementsByTagName("head");
      if (_headNodeSet.length)
      {
        _script = _headNodeSet.item(0).appendChild(_script);
      }
      else
      {
        var _head = document.createElement("head");
        _head = document.documentElement.appendChild(_head);
        _script = _head.appendChild(_script);
      }
      return _script;
    }
    var _testScript = this._insertScriptTag("var __INCLUDE_TEST_2__ = true;");
    if (typeof window.__INCLUDE_TEST_2__ == "boolean")
    {
      _testScript.parentNode.removeChild(_testScript);
      this._insertScriptTag(_jsString);
    }
    else
    {
      // Check whether window.setTimeout works in real time.
      window.setTimeout("var __INCLUDE_TEST_3__ = true;", 0);
      if (typeof window.__INCLUDE_TEST_3__ != "undefined")
      {
        delete window.__INCLUDE_TEST_3__;
        window.setTimeout(_jsString, 0);
      }
    }
  }

  return true;
}

var head = document.getElementsByTagName('head')[0];

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
  'functions.js',
  'dropdown.js',
  'json.js',
  'sliders.js',
  'pwstrength.js',
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
      case 'rename':
        ajaxRename();
        break;
    }
  }
});

function Placeholder(funcname, filename)
{
  this.filename = filename;
  this.funcname = funcname;
  this.go = function()
  {
    window[funcname] = null;
    load_component(filename);
    var arglist = [];
    for ( var i = 0; i < arguments.length; i++ )
    {
      arglist[arglist.length] = 'arguments['+i+']';
    }
    arglist = implode(', ', arglist);
    eval(funcname + '(' + arglist + ');');
  }
}

// list of public functions that need placeholders that fetch the component
var placeholder_list = {
  ajaxReset: 'ajax.js',
  ajaxComments: 'comments.js',
  ajaxEditor: 'editor.js',
  ajaxHistory: 'ajax.js',
  ajaxRename: 'ajax.js',
  ajaxDelVote: 'ajax.js',
  ajaxProtect: 'ajax.js',
  ajaxClearLogs: 'ajax.js',
  ajaxResetDelVotes: 'ajax.js',
  ajaxDeletePage: 'ajax.js',
  ajaxSetPassword: 'ajax.js',
  ajaxChangeStyle: 'ajax.js',
  ajaxOpenACLManager: 'acl.js',
  ajaxAdminPage: 'login.js',
  ajaxInitLogout: 'login.js',
  ajaxStartLogin: 'login.js',
  ajaxStartAdminLogin: 'login.js',
  ajaxAdminPage: 'login.js',
  mb_logout: 'login.js',
  selectButtonMajor: 'toolbar.js',
  selectButtonMinor: 'toolbar.js',
  unselectAllButtonsMajor: 'toolbar.js',
  unselectAllButtonsMinor: 'toolbar.js',
  darken: 'fadefilter.js',
  enlighten: 'fadefilter.js',
}

var placeholder_instances = {};

for ( var i in placeholder_list )
{
  var file = placeholder_list[i];
  placeholder_instances[i] = new Placeholder(i, file);
  window[i] = placeholder_instances[i].go;
}

//*/

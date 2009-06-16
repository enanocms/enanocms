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

if ( typeof(title) != 'string')
{
  alert('There was a problem loading the PHP-generated Javascript variables that control parameters for AJAX applets. Most on-page functionality will be very badly broken.\n\nTheme developers, ensure that you are using {JS_DYNAMIC_VARS} *before* you include jsres.php.');
}

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

console.info('Enano::JS runtime: starting system init');

if ( typeof(ENANO_JSRES_COMPRESSED) == undefined )
{
  var ENANO_JSRES_COMPRESSED = false;
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

var tinymce_initted = false;

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
var ajax_load_icon = cdnPath + '/images/loading.gif';
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
  onload_hooks = null;
}

var enano_hooks = {};
function setHook(hook_name)
{
  if ( enano_hooks[hook_name] )
  {
    return enano_hooks[hook_name];
  }
  return 'void(0);';
}

function attachHook(hook_name, code)
{
  if ( !enano_hooks[hook_name] )
    enano_hooks[hook_name] = '';
  
  enano_hooks[hook_name] += code;
}

var loaded_components = loaded_components || {};
var _load_component_running = false;
function load_component(file)
{
  var multiple = false;
  if ( typeof(file) == 'object' )
  {
    if ( ENANO_JSRES_COMPRESSED )
    {
      multiple = true;
      for ( var i = 0; i < file.length; i++ )
      {
        file[i] = (file[i].replace(/\.js$/, '')) + '.js';
        if ( loaded_components[file[i]] )
        {
          file[i] = false;
        }
      }
      var file2 = [];
      for ( var i = 0; i < file.length; i++ )
      {
        if ( file[i] )
          file2.push(file[i]);
      }
      file = file2;
      delete(file2);
      if ( file.length < 1 )
      {
        return true;
      }
      var file_flat = implode(',', file);
    }
    else
    {
      for ( var i = 0; i < file.length; i++ )
      {
        load_component(file[i]);
      }
      return true;
    }
  }
  _load_component_running = true;
  if ( !multiple )
  {
    file = file.replace(/\.js$/, '');
  
    if ( loaded_components[file + '.js'] )
    {
      // already loaded
      return true;
    }
  }
  
  console.info('Loading component %s via AJAX', ( multiple ? file_flat : file ));
  
  load_show_win(( multiple ? file_flat : file ));
  
  // get an XHR instance
  var ajax = ajaxMakeXHR();
  
  if ( !multiple )
    file = file + '.js';
  var uri = ( ENANO_JSRES_COMPRESSED ) ? scriptPath + '/includes/clientside/jsres.php?f=' + (multiple ? file_flat : file ) : scriptPath + '/includes/clientside/static/' + file;
  try
  {
    ajax.open('GET', uri, false);
    ajax.onreadystatechange = function()
    {
      if ( this.readyState == 4 && this.status != 200 )
      {
        alert('There was a problem loading a script from the server. Please check your network connection.');
        load_hide_win();
        throw('load_component(): XHR for component ' + file + ' failed');
      }
    }
    ajax.send(null);
    // async request, so if status != 200 at this point then we're screwed
    if ( ajax.readyState == 4 && ajax.status == 200 )
    {
      if ( onload_complete )
        onload_hooks = new Array();
      eval_global(ajax.responseText);
      if ( window.jQuery && aclDisableTransitionFX )
        if ( window.jQuery.fx )
          window.jQuery.fx.off = true;
      load_hide_win();
      if ( onload_complete )
        runOnloadHooks();
    }
  }
  catch(e)
  {
    alert('There was a problem loading a script from the server. Please check your network connection.');
    load_hide_win();
    console.info("Component loader exception is shown below.");
    console.debug(e);
    throw('load_component(): XHR for component ' + file + ' failed');
  }
  
  if ( !multiple )
  {
    loaded_components[file] = true;
  }
  _load_component_running = false;
  return true;
}

function load_show_win(file)
{
  var img = '<img style="margin-right: 5px" src="' + cdnPath + '/images/loading.gif" />';
  if ( document.getElementById('_js_load_component') )
  {
    document.getElementById('_js_load_component').innerHTML = img + msg_loading_component.replace('%component%', file);
    return;
  }
  file = file.replace(/\.js$/, '').replace(/\.js,/g, ', ');
  var ld = document.createElement('div');
  ld.style.padding = '10px';
  ld.style.height = '12px';
  ld.style.position = 'fixed';
  ld.style.right = '5px';
  ld.style.bottom = '0px';
  ld.innerHTML = img + msg_loading_component.replace('%component%', file);
  ld.id = '_js_load_component';
  
  // FYI: The base64 encoded image is a 70% opacity 1x1px white PNG.
  ld.style.backgroundImage = 'url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAAlwSFlzAAALEwAACxMBAJqcGAAAAA1JREFUCNdj+P///xkACcgDypG+nnEAAAAASUVORK5CYII=)';
  
  document.body.appendChild(ld);
  document.body.style.cursor = 'wait';
}

function load_hide_win()
{
  var ld = document.getElementById('_js_load_component');
  if ( !ld )
    return false;
  ld.parentNode.removeChild(ld);
  document.body.style.cursor = 'default';
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

var autofill_check = function()
{
  var inputs = document.getElementsByTagName('input');
  for ( var i = 0; i < inputs.length; i++ )
  {
    if ( inputs[i].className )
    {
      if ( inputs[i].className.match(/^autofill/) )
      {
        load_component('autofill');
        return;
      }
    }
  }
}

addOnloadHook(autofill_check);

var head = document.getElementsByTagName('head')[0];

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
  'tinymce-init.js',
  'loader.js'
];

for(var f in thefiles)
{
  if ( typeof(thefiles[f]) != 'string' )
    continue;
  var script = document.createElement('script');
  script.type="text/javascript";
  if ( thefiles[f] == 'json.js' && KILL_SWITCH )
  {
    // alert('kill switch and problem script');
    continue;
  }
  script.src=cdnPath+"/includes/clientside/static/"+thefiles[f];
  head.appendChild(script);
}

// Do not remove the following comment, it is used by jsres.php.
/*!END_INCLUDER*/

addOnloadHook(function() {
  if ( $_REQUEST['auth'] )
  {
    var key = $_REQUEST['auth'];
    var loc = String(window.location);
    loc = loc.replace(/#.+$/, '').replace(/&auth=[0-9a-f]+/, '').replace(/\?auth=[0-9a-f]+(&)?/, '$1');
    if ( key != 'false' )
    {
      var sep = loc.indexOf('?') != -1 ? '&' : '?';
      loc = loc + sep + 'auth=' + key;
    }
    console.debug(loc);
    window.location = loc;
  }
  if ( $_REQUEST['do'] )
  {
    var act = $_REQUEST['do'];
    switch(act)
    {
      case 'comments':
        ajaxComments();
        break;
      case 'edit':
        var revid = ( $_REQUEST['rev'] ) ? parseInt($_REQUEST['rev']) : false;
        ajaxEditor(revid);
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
      case 'aclmanager':
        ajaxOpenACLManager();
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
    return eval(funcname + '(' + arglist + ');');
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
  ajaxRollback: 'ajax.js',
  ajaxResetDelVotes: 'ajax.js',
  ajaxDeletePage: 'ajax.js',
  ajaxSetPassword: 'ajax.js',
  ajaxChangeStyle: 'ajax.js',
  ajaxCatToTag: 'ajax.js',
  ajaxCatEdit: 'ajax.js',
  ajaxReverseDNS: 'ajax.js',
  ajaxOpenACLManager: 'acl.js',
  ajaxOpenDirectACLRule: 'acl.js',
  ajaxAdminPage: 'login.js',
  ajaxInitLogout: 'login.js',
  ajaxStartLogin: 'login.js',
  ajaxStartAdminLogin: 'login.js',
  ajaxLoginNavTo: 'login.js',
  ajaxLogonToElev: 'login.js',
  ajaxAdminPage: 'login.js',
  ajaxAdminUser: 'login.js',
  mb_logout: 'login.js',
  selectButtonMajor: 'toolbar.js',
  selectButtonMinor: 'toolbar.js',
  unselectAllButtonsMajor: 'toolbar.js',
  unselectAllButtonsMinor: 'toolbar.js',
  darken: 'fadefilter.js',
  enlighten: 'fadefilter.js',
  autofill_onload: 'autofill.js',
  password_score: 'pwstrength.js',
  password_score_field: 'pwstrength.js',
  ajaxEditTheme: 'theme-manager.js',
  ajaxToggleSystemThemes: 'theme-manager.js',
  ajaxInstallTheme: 'theme-manager.js',
  ajaxInitRankEdit: 'rank-manager.js',
  ajaxInitRankCreate: 'rank-manager.js',
  autofill_init_element: 'autofill.js',
  autofill_onload: 'autofill.js',
  paginator_goto: 'paginate.js'
};

function AutofillUsername(el, p)
{
  p = p || {};
  el.className = 'autofill username';
  el.onkeyup = null;
  autofill_init_element(el, p);
}

function AutofillPage(el, p)
{
  p = p || {};
  el.className = 'autofill page';
  el.onkeyup = null;
  autofill_init_element(el, p);
}

var placeholder_instances = {};

for ( var i in placeholder_list )
{
  var file = placeholder_list[i];
  placeholder_instances[i] = new Placeholder(i, file);
  window[i] = placeholder_instances[i].go;
}

$lang = {
  get: function(a, b)
  {
    load_component('l10n');
    return $lang.get(a, b);
  }
}

//*/

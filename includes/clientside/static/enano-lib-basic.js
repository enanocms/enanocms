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
 * For more information about Enano, please visit http://www.enanocms.org/.
 * All of the code in these script files may be used freely so long as the above license block is displayed and your
 * modified code is distributed under the GPL. See the page Special:About_Enano on this website for more information.
 */

if(typeof title != 'string')
{
  alert('Uh-oh! The required dynamic (PHP-generated) Javascript variables don\'t seem to be available. Javascript is going to be seriously broken.');
}

// Run-time variables

var detect = navigator.userAgent.toLowerCase();
var IE;
var is_Safari;

// dummy tinyMCE object
var tinyMCE = new Object();

// Detect whether the user is running the Evil One or not...

function checkIt(string) {
  place = detect.indexOf(string) + 1;
  thestring = string;
  return place;
}
if (checkIt('msie')) IE = true;
else IE = false;

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

// Code for parsing JSON strings - full source code in json.js
if(!Object.prototype.toJSONString){Array.prototype.toJSONString=function(){var a=['['],b,i,l=this.length,v;function p(s){if(b){a.push(',');}
a.push(s);b=true;}
for(i=0;i<l;i+=1){v=this[i];switch(typeof v){case'undefined':case'function':case'unknown':break;case'object':if(v){if(typeof v.toJSONString==='function'){p(v.toJSONString());}}else{p("null");}
break;default:p(v.toJSONString());}}
a.push(']');return a.join('');};Boolean.prototype.toJSONString=function(){return String(this);};Date.prototype.toJSONString=function(){function f(n){return n<10?'0'+n:n;}
return'"'+this.getFullYear()+'-'+
f(this.getMonth()+1)+'-'+
f(this.getDate())+'T'+
f(this.getHours())+':'+
f(this.getMinutes())+':'+
f(this.getSeconds())+'"';};Number.prototype.toJSONString=function(){return isFinite(this)?String(this):"null";};Object.prototype.toJSONString=function(){var a=['{'],b,i,v;function p(s){if(b){a.push(',');}
a.push(i.toJSONString(),':',s);b=true;}
for(i in this){if(this.hasOwnProperty(i)){v=this[i];switch(typeof v){case'undefined':case'function':case'unknown':break;case'object':if(v){if(typeof v.toJSONString==='function'){p(v.toJSONString());}}else{p("null");}
break;default:p(v.toJSONString());}}}
a.push('}');return a.join('');};(function(s){var m={'\b':'\\b','\t':'\\t','\n':'\\n','\f':'\\f','\r':'\\r','"':'\\"','\\':'\\\\'};s.parseJSON=function(filter){try{if(/^("(\\.|[^"\\\n\r])*?"|[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t])+?$/.test(this)){var j=eval('('+this+')');if(typeof filter==='function'){function walk(k,v){if(v&&typeof v==='object'){for(var i in v){if(v.hasOwnProperty(i)){v[i]=walk(i,v[i]);}}}
return filter(k,v);}
return walk('',j);}
return j;}}catch(e){}
throw new SyntaxError("parseJSON");};s.toJSONString=function(){if(/["\\\x00-\x1f]/.test(this)){return'"'+this.replace(/([\x00-\x1f\\"])/g,function(a,b){var c=m[b];if(c){return c;}
c=b.charCodeAt();return'\\u00'+
Math.floor(c/16).toString(16)+
(c%16).toString(16);})+'"';}
return'"'+this+'"';};})(String.prototype);}

function disableJSONExts()
{
  delete(Object.prototype.toJSONString);
  delete(Array.prototype.toJSONString);
  delete(Boolean.prototype.toJSONString);
  delete(Date.prototype.toJSONString);
  delete(Number.prototype.toJSONString);
  delete(String.prototype.toJSONString);
}

// JSON extensions are deprecated now - use the toJSONString **function**
disableJSONExts();

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

var head = document.getElementsByTagName('head')[0];
var script = document.createElement('script');
script.type="text/javascript";
script.src=scriptPath+"/includes/clientside/tinymce/tiny_mce_src.js";
head.appendChild(script);

// Start loading files
var thefiles = [
  'misc.js',
  'admin-menu.js',
  'ajax.js',
  'autocomplete.js',
  'base64.js',
  'dropdown.js',
  'faders.js',
  'fat.js',
  'grippy.js',
  'json.js',
  'md5.js',
  'sliders.js',
  'toolbar.js',
  'windows.js',
  'rijndael.js',
  'template-compiler.js',
  'acl.js',
  'comments.js',
  'editor.js',
  'dynano.js',
  'flyin.js',
  'loader.js'
];

var problem_scripts = {
  'faders.js' : true,
  'acl.js' : true,
  'admin-menu.js' : true,
  'loader.js' : true
};

for(var f in thefiles)
{
  if ( typeof(thefiles[f]) != 'string' )
    continue;
  var script = document.createElement('script');
  script.type="text/javascript";
  //if ( problem_scripts[thefiles[f]] )
    script.src=scriptPath+"/includes/clientside/static/"+thefiles[f];
  //else
  //  script.src=scriptPath+"/includes/clientside/jsres.php?file="+thefiles[f];
  head.appendChild(script);
}

var onload_hooks = new Array();

function addOnloadHook(func)
{
  if ( typeof ( func ) == 'function' )
  {
    try
    {
      onload_hooks.push(func);
    }
    catch(e)
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

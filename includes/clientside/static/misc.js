// Some additional DHTML functions

function fetch_offset(obj) {
  var left_offset = obj.offsetLeft;
  var top_offset = obj.offsetTop;
  while ((obj = obj.offsetParent) != null) {
    left_offset += obj.offsetLeft;
    top_offset += obj.offsetTop;
  }
  return { 'left' : left_offset, 'top' : top_offset };
}

function fetch_dimensions(o) {
  var w = o.offsetWidth;
  var h = o.offsetHeight;
  return { 'w' : w, 'h' : h };
}

function findParentForm(o)
{
  if ( o.tagName == 'FORM' )
    return o;
  while(true)
  {
    o = o.parentNode;
    if ( !o )
      return false;
    if ( o.tagName == 'FORM' )
      return o;
  }
  return false;
}

function ajaxReverseDNS(o, text)
{
  if(text) var ipaddr = text;
  else var ipaddr = o.innerHTML;
  rDnsObj = o;
  rDnsBannerObj = bannerOn('Retrieving reverse DNS info...');
  ajaxGet(stdAjaxPrefix+'&_mode=rdns&ip='+ipaddr, function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        off = fetch_offset(rDnsObj);
        dim = fetch_dimensions(rDnsObj);
        right = off['left'] + dim['w'];
        top = off['top'] + dim['h'];
        var thediv = document.createElement('div');
        thediv.className = 'info-box';
        thediv.style.margin = '0';
        thediv.style.position = 'absolute';
        thediv.style.top  = top  + 'px';
        thediv.style.display = 'none';
        thediv.style.zIndex = getHighestZ() + 2;
        thediv.id = 'mdgDynamic_rDnsInfoDiv_'+Math.floor(Math.random() * 1000000);
        thediv.innerHTML = '<b>Reverse DNS:</b><br />'+ajax.responseText+' <a href="#" onclick="elem = document.getElementById(\''+thediv.id+'\'); elem.innerHTML = \'\'; elem.style.display = \'none\';return false;">Close</a>';
        var body = document.getElementsByTagName('body');
        body = body[0];
        bannerOff(rDnsBannerObj);
        body.appendChild(thediv);
        thediv.style.display = 'block';
        left = fetch_dimensions(thediv);
        thediv.style.display = 'none';
        left = right - left['w'];
        thediv.style.left = left + 'px';
        thediv.style.display = 'block';
        fadeInfoBoxes();
      }
    });
}

function bannerOn(text)
{
  darken(true);
  var thediv = document.createElement('div');
  thediv.className = 'mdg-comment';
  thediv.style.padding = '0';
  thediv.style.marginLeft = '0';
  thediv.style.position = 'absolute';
  thediv.style.display = 'none';
  thediv.style.padding = '4px';
  thediv.style.fontSize = '14pt';
  thediv.id = 'mdgDynamic_bannerDiv_'+Math.floor(Math.random() * 1000000);
  thediv.innerHTML = text;
  
  var body = document.getElementsByTagName('body');
  body = body[0];
  body.appendChild(thediv);
  body.style.cursor = 'wait';
  
  thediv.style.display = 'block';
  dim = fetch_dimensions(thediv);
  thediv.style.display = 'none';
  bdim = { 'w' : getWidth(), 'h' : getHeight() };
  so = getScrollOffset();
  
  var left = (bdim['w'] / 2) - ( dim['w'] / 2 );
  
  var top  = (bdim['h'] / 2);
  top  = top - ( dim['h'] / 2 );
  
  top = top + so;
  
  thediv.style.top  = top  + 'px';
  thediv.style.left = left + 'px';
  
  thediv.style.display = 'block';
  
  return thediv.id;
}

function bannerOff(id)
{
  e = document.getElementById(id);
  if(!e) return;
  e.innerHTML = '';
  e.style.display = 'none';
  var body = document.getElementsByTagName('body');
  body = body[0];
  body.style.cursor = 'default';
  enlighten(true);
}

function disableUnload(message)
{
  if(typeof message != 'string') message = 'You may want to save your changes first.';
  window._unloadmsg = message;
  window.onbeforeunload = function(e)
  {
    if ( !e )
      e = window.event;
    e.returnValue = window._unloadmsg;
  }
}

function enableUnload()
{
  window._unloadmsg = null;
  window.onbeforeunload = null;
}

/**
 * Gets the highest z-index of all divs in the document
 * @return integer
 */
function getHighestZ()
{
  z = 0;
  var divs = document.getElementsByTagName('div');
  for(var i = 0; i < divs.length; i++)
  {
    if(divs[i].style.zIndex > z) z = divs[i].style.zIndex;
  }
  return z;
}

function isKeyPressed(event)
{
  if (event.shiftKey==1)
  {
    shift = true;
  }
  else
  {
    shift = false;
  }
}

function moveDiv(div, newparent)
{
  var backup = div;
  var oldparent = div.parentNode;
  oldparent.removeChild(div);
  newparent.appendChild(backup);
}

function readCookie(name) {var nameEQ = name + "=";var ca = document.cookie.split(';');for(var i=0;i < ca.length;i++){var c = ca[i];while (c.charAt(0)==' ') c = c.substring(1,c.length);if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);}return null;}
function createCookie(name,value,days){if (days){var date = new Date();date.setTime(date.getTime()+(days*24*60*60*1000));var expires = "; expires="+date.toGMTString();}else var expires = "";document.cookie = name+"="+value+expires+"; path=/";}
function eraseCookie(name) {createCookie(name,"",-1);}

var busyBannerID;
function goBusy(msg)
{
  if(!msg) msg = 'Please wait...';
  body = document.getElementsByTagName('body');
  body = body[0];
  body.style.cursor = 'wait';
  busyBannerID = bannerOn(msg);
}

function unBusy()
{
  body = document.getElementsByTagName('body');
  body = body[0];
  body.style.cursor = 'default';
  bannerOff(busyBannerID);
}

function setAjaxLoading()
{
  if ( document.getElementById('ajaxloadicon') )
  {
    document.getElementById('ajaxloadicon').src=ajax_load_icon;
  }
}

function unsetAjaxLoading()
{
  if ( document.getElementById('ajaxloadicon') )
  {
    document.getElementById('ajaxloadicon').src=scriptPath + '/images/spacer.gif';
  }
}

/*
 * AJAX login box (experimental)
 * Moved / rewritten in login.js
 */

// Included only for API-compatibility
function ajaxPromptAdminAuth(call_on_ok, level)
{
  ajaxLogonInit(call_on_ok, level);
}

// This code is in the public domain. Feel free to link back to http://jan.moesen.nu/
function sprintf()
{
  if (!arguments || arguments.length < 1 || !RegExp)
  {
    return;
  }
  var str = arguments[0];
  var re = /([^%]*)%('.|0|\x20)?(-)?(\d+)?(\.\d+)?(%|b|c|d|u|f|o|s|x|X)(.*)/;
  var a = b = [], numSubstitutions = 0, numMatches = 0;
  while (a = re.exec(str))
  {
    var leftpart = a[1], pPad = a[2], pJustify = a[3], pMinLength = a[4];
    var pPrecision = a[5], pType = a[6], rightPart = a[7];
    
    //alert(a + '\n' + [a[0], leftpart, pPad, pJustify, pMinLength, pPrecision);

    numMatches++;
    if (pType == '%')
    {
      subst = '%';
    }
    else
    {
      numSubstitutions++;
      if (numSubstitutions >= arguments.length)
      {
        alert('Error! Not enough function arguments (' + (arguments.length - 1) + ', excluding the string)\nfor the number of substitution parameters in string (' + numSubstitutions + ' so far).');
      }
      var param = arguments[numSubstitutions];
      var pad = '';
             if (pPad && pPad.substr(0,1) == "'") pad = leftpart.substr(1,1);
        else if (pPad) pad = pPad;
      var justifyRight = true;
             if (pJustify && pJustify === "-") justifyRight = false;
      var minLength = -1;
             if (pMinLength) minLength = parseInt(pMinLength);
      var precision = -1;
             if (pPrecision && pType == 'f') precision = parseInt(pPrecision.substring(1));
      var subst = param;
             if (pType == 'b') subst = parseInt(param).toString(2);
        else if (pType == 'c') subst = String.fromCharCode(parseInt(param));
        else if (pType == 'd') subst = parseInt(param) ? parseInt(param) : 0;
        else if (pType == 'u') subst = Math.abs(param);
        else if (pType == 'f') subst = (precision > -1) ? Math.round(parseFloat(param) * Math.pow(10, precision)) / Math.pow(10, precision): parseFloat(param);
        else if (pType == 'o') subst = parseInt(param).toString(8);
        else if (pType == 's') subst = param;
        else if (pType == 'x') subst = ('' + parseInt(param).toString(16)).toLowerCase();
        else if (pType == 'X') subst = ('' + parseInt(param).toString(16)).toUpperCase();
    }
    str = leftpart + subst + rightPart;
  }
  return str;
}

/**
 * Insert a DOM object _after_ the specified child.
 * @param object Parent node
 * @param object Node to insert
 * @param object Node to insert after
 */

function insertAfter(parent, baby, bigsister)
{
  try
  {
    if ( parent.childNodes[parent.childNodes.length-1] == bigsister )
      parent.appendChild(baby);
    else
      parent.insertBefore(baby, bigsister.nextSibling);
  }
  catch(e)
  {
    alert(e.toString());
    if ( window.console )
    {
      // Firebug support
      window.console.warn(e);
    }
  }
}

/**
 * Validates an e-mail address.
 * @param string E-mail address
 * @return bool
 */

function validateEmail(email)
{
  return ( email.match(/^(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*|(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[^()<>@,;:".\\\[\]\x80-\xff\000-\010\012-\037]*(?:(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[^()<>@,;:".\\\[\]\x80-\xff\000-\010\012-\037]*)*<[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*(?:,[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*)*:[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)?(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*>)$/) ) ? true : false;
}

/**
 * Validates a username.
 * @param string Username to test
 * @return bool
 */

function validateUsername(username)
{
  var regex = new RegExp('^[^<>&\?\'"%\n\r/]+$', '');
  return ( username.match(regex) ) ? true : false;
}

/**
 * Equivalent of PHP's time()
 * @return int
 */

function unix_time()
{
  return parseInt((new Date()).getTime()/1000);
}

/*
 * Utility functions, moved from windows.js
 */
 
// getElementWidth() and getElementHeight()
// Source: http://www.aspandjavascript.co.uk/javascript/javascript_api/get_element_width_height.asp

function getElementHeight(Elem) {
  if (ns4) 
  {
    var elem = getObjNN4(document, Elem);
    return elem.clip.height;
  } 
  else
  {
    if(document.getElementById) 
    {
      var elem = document.getElementById(Elem);
    }
    else if (document.all)
    {
      var elem = document.all[Elem];
    }
    if (op5) 
    { 
      xPos = elem.style.pixelHeight;
    }
    else
    {
      xPos = elem.offsetHeight;
    }
    return xPos;
  } 
}

function getElementWidth(Elem) {
  if (ns4) {
    var elem = getObjNN4(document, Elem);
    return elem.clip.width;
  } else {
    if(document.getElementById) {
      var elem = document.getElementById(Elem);
    } else if (document.all){
      var elem = document.all[Elem];
    }
    if (op5) {
      xPos = elem.style.pixelWidth;
    } else {
      xPos = elem.offsetWidth;
    }
    return xPos;
  }
}

function getHeight() {
  var myHeight = 0;
  if( typeof( window.innerWidth ) == 'number' ) {
    myHeight = window.innerHeight;
  } else if( document.documentElement &&
      ( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) {
    myHeight = document.documentElement.clientHeight;
  } else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) {
    myHeight = document.body.clientHeight;
  }
  return myHeight;
}

function getWidth() {
  var myWidth = 0;
  if( typeof( window.innerWidth ) == 'number' ) {
    myWidth = window.innerWidth;
  } else if( document.documentElement &&
      ( document.documentElement.clientWidth || document.documentElement.clientWidth ) ) {
    myWidth = document.documentElement.clientWidth;
  } else if( document.body && ( document.body.clientWidth || document.body.clientWidth ) ) {
    myWidth = document.body.clientWidth;
  }
  return myWidth;
}

/**
 * Sanitizes a page URL string so that it can safely be stored in the database.
 * @param string Page ID to sanitize
 * @return string Cleaned text
 */

function sanitize_page_id(page_id)
{
  // Remove character escapes
  page_id = dirtify_page_id(page_id);

  var regex = new RegExp('[A-Za-z0-9\\[\\]\./:;\(\)@_-]', 'g');
  pid_clean = page_id.replace(regex, 'X');
  var pid_dirty = [];
  for ( var i = 0; i < pid_clean.length; i++ )
    pid_dirty[i] = pid_clean.substr(i, 1);

  for ( var i = 0; i < pid_dirty.length; i++ )
  {
    var char = pid_dirty[i];
    if ( char == 'X' )
      continue;
    var cid = char.charCodeAt(0);
    cid = cid.toString(16).toUpperCase();
    if ( cid.length < 2 )
    {
      cid = '0' + cid;
    }
    pid_dirty[i] = "." + cid;
  }
  
  var pid_chars = [];
  for ( var i = 0; i < page_id.length; i++ )
    pid_chars[i] = page_id.substr(i, 1);
  
  var page_id_cleaned = '';

  for ( var id in pid_chars )
  {
    var char = pid_chars[id];
    if ( pid_dirty[id] == 'X' )
      page_id_cleaned += char;
    else
      page_id_cleaned += pid_dirty[id];
  }
  
  return page_id_cleaned;
}

/**
 * Removes character escapes in a page ID string
 * @param string Page ID string to dirty up
 * @return string
 */

function dirtify_page_id(page_id)
{
  // First, replace spaces with underscores
  page_id = page_id.replace(/ /g, '_');

  var matches = page_id.match(/\.[A-Fa-f0-9][A-Fa-f0-9]/g);
  
  if ( matches != null )
  {
    for ( var i = 0; i < matches.length; i++ )
    {
      var match = matches[i];
      var byt = (match.substr(1)).toUpperCase();
      var code = eval("0x" + byt);
      var regex = new RegExp('\\.' + byt, 'g');
      page_id = page_id.replace(regex, String.fromCharCode(code));
    }
  }
  
  return page_id;
}

/*
 * Expandable fieldsets
 */

var expander_onload = function()
{
  var sets = document.getElementsByTagName('fieldset');
  if ( sets.length < 1 )
    return false;
  var init_us = [];
  for ( var index = 0; index < sets.length; index++ )
  {
    var mode = sets[index].getAttribute('enano:expand');
    if ( mode == 'closed' || mode == 'open' )
    {
      init_us.push(sets[index]);
    }
  }
  for ( var k = 0; k < init_us.length; k++ )
  {
    expander_init_element(init_us[k]);
  }
}

function expander_init_element(el)
{
  // get the legend tag
  var legend = el.getElementsByTagName('legend')[0];
  if ( !legend )
    return false;
  // existing content
  var existing_inner = legend.innerHTML;
  // blank the innerHTML and replace it with a link
  legend.innerHTML = '';
  var button = document.createElement('a');
  button.className = 'expander expander-open';
  button.innerHTML = existing_inner;
  button.href = '#';
  
  legend.appendChild(button);
  
  button.onclick = function()
  {
    try
    {
      expander_handle_click(this);
    }
    catch(e)
    {
      console.debug('Exception caught: ', e);
    }
    return false;
  }
  
  if ( el.getAttribute('enano:expand') == 'closed' )
  {
    expander_close(el);
  }
}

function expander_handle_click(el)
{
  if ( el.parentNode.parentNode.tagName != 'FIELDSET' )
    return false;
  var parent = el.parentNode.parentNode;
  if ( parent.getAttribute('enano:expand') == 'closed' )
  {
    expander_open(parent);
  }
  else
  {
    expander_close(parent);
  }
}

function expander_close(el)
{
  var children = el.childNodes;
  for ( var i = 0; i < children.length; i++ )
  {
    var child = children[i];
    if ( child.tagName == 'LEGEND' )
    {
      var a = child.getElementsByTagName('a')[0];
      $(a).rmClass('expander-open');
      $(a).addClass('expander-closed');
      continue;
    }
    if ( child.style )
    {
      child.expander_meta_old_state = child.style.display;
      child.style.display = 'none';
    }
  }
  el.expander_meta_padbak = el.style.padding;
  el.setAttribute('enano:expand', 'closed');
}

function expander_open(el)
{
  var children = el.childNodes;
  for ( var i = 0; i < children.length; i++ )
  {
    var child = children[i];
    if ( child.tagName == 'LEGEND' )
    {
      var a = child.getElementsByTagName('a')[0];
      $(a).rmClass('expander-closed');
      $(a).addClass('expander-open');
      continue;
    }
    if ( child.expander_meta_old_state && child.style )
    {
      child.style.display = child.expander_meta_old_state;
      child.expander_meta_old_state = null;
    }
    else
    {
      if ( child.style )
      {
        child.style.display = null;
      }
    }
  }
  if ( el.expander_meta_padbak )
  {
    el.style.padding = el.expander_meta_padbak;
    el.expander_meta_padbak = null;
  }
  else
  {
    el.style.padding = null;
  }
  el.setAttribute('enano:expand', 'open');
}

addOnloadHook(expander_onload);

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
      if(ajax.readyState == 4)
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
  var body = document.getElementsByTagName('body');
  body = body[0];
  body.onbeforeunload='return unescape(\''+escape(message)+'\')';
}

function enableUnload()
{
  var body = document.getElementsByTagName('body');
  body = body[0];
  body.onbeforeunload = null;
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
    document.getElementById('ajaxloadicon').src=scriptPath + '/images/loading.gif';
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
 * Search boxes
 */
 
function buildSearchBoxes()
{
  var divs = document.getElementsByTagName('*');
  var boxes = new Array();
  for ( var i = 0; i < divs.length; i++ )
  {
    if ( divs[i].className)
    {
      if ( divs[i].className.substr(0, 9) == 'searchbox' )
      {
        boxes.push(divs[i]);
      }
    }
  }
  for ( var i = 0; i < boxes.length; i++ )
  {
    if ( boxes[i].className.match(/^searchbox\[([0-9]+)px\]$/) )
    {
      var width = boxes[i].className.match(/^searchbox\[([0-9]+)px\]$/);
      width = parseInt(width[1]);
    }
    else
    {
      var width = 120;
    }
    createSearchBox(boxes[i], width);
  }
}

function createSearchBox(parent, width)
{
  if ( typeof(parent) != 'object')
  {
    alert('BUG: createSearchBox(): parent is not an object');
    return false;
  }
  //parent.style.padding = '0px';
  //parent.style.textAlign = 'center';
  parent.style.width = width + 'px';
  var submit = document.createElement('div');
  submit.onclick = function() { searchFormSubmit(this); };
  submit.className = 'js-search-submit';
  var input = document.createElement('input');
  input.className = 'js-search-box';
  input.value = 'Search';
  input.name = 'q';
  input.style.width = ( width - 28 ) + 'px';
  input.onfocus = function() { if ( this.value == 'Search' ) this.value = ''; };
  input.onblur  = function() { if ( this.value == '' ) this.value = 'Search'; };
  parent.appendChild(input);
  var off = fetch_offset(input);
  var top = off['top'] + 'px';
  var left = ( parseInt(off['left']) + ( width - 24 ) ) + 'px';
  submit.style.top = top;
  submit.style.left = left;
  parent.appendChild(submit);
}

function searchFormSubmit(obj)
{
  var input = obj.previousSibling;
  if ( input.value == 'Search' || input.value == '' )
    return false;
  var p = obj;
  while(true)
  {
    p = p.parentNode;
    if ( !p )
      break;
    if ( typeof(p.tagName) != 'string' )
      break;
    else if ( p.tagName.toLowerCase() == 'form' )
    {
      p.submit();
    }
    else if ( p.tagName.toLowerCase() == 'body' )
    {
      break;
    }
  }
}

/*
 * AJAX login box (experimental)
 */

var ajax_auth_prompt_cache = false;
var ajax_auth_mb_cache = false;
var ajax_auth_level_cache = false;

function ajaxPromptAdminAuth(call_on_ok, level)
{
  if ( typeof(call_on_ok) == 'function' )
  {
    ajax_auth_prompt_cache = call_on_ok;
  }
  if ( !level )
    level = USER_LEVEL_MEMBER;
  ajax_auth_level_cache = level;
  var loading_win = '<div align="center" style="text-align: center;"> \
      <p>Fetching an encryption key...</p> \
      <p><small>Not working? Use the <a href="'+makeUrlNS('Special', 'Login/' + title)+'">alternate login form</a>.</p> \
      <p><img alt="Please wait..." src="'+scriptPath+'/images/loading-big.gif" /></p> \
    </div>';
  var title = ( level > USER_LEVEL_MEMBER ) ? 'You are requesting a sensitive operation.' : 'Please enter your username and password to continue.';
  ajax_auth_mb_cache = new messagebox(MB_OKCANCEL|MB_ICONLOCK, title, loading_win);
  ajax_auth_mb_cache.onbeforeclick['OK'] = ajaxValidateLogin;
  ajaxAuthLoginInnerSetup();
}

function ajaxAuthLoginInnerSetup()
{
  ajaxGet(makeUrlNS('Special', 'Login', 'act=getkey'), function() {
      if ( ajax.readyState == 4 )
      {
        var response = String(ajax.responseText);
        if ( response.substr(0,1) != '{' )
        {
          alert('Invalid JSON response from server: ' + response);
          return false;
        }
        response = parseJSON(response);
        var level = ajax_auth_level_cache;
        var form_html = '';
        if ( level > USER_LEVEL_MEMBER )
        {
          form_html += 'Please re-enter your login details, to verify your identity.<br /><br />';
        }
        form_html += ' \
          <table border="0" align="center"> \
            <tr> \
              <td>Username:</td><td><input tabindex="1" id="ajaxlogin_user" type="text"     size="25" /> \
            </tr> \
            <tr> \
              <td>Password:</td><td><input tabindex="2" id="ajaxlogin_pass" type="password" size="25" /> \
            </tr> \
            <tr> \
              <td colspan="2" style="text-align: center;"> \
                <br /><small>Trouble logging in? Try the <a href="'+makeUrlNS('Special', 'Login/' + title)+'">full login form</a>.<br />';
       if ( level <= USER_LEVEL_MEMBER )
       {
         form_html += ' \
                Did you <a href="'+makeUrlNS('Special', 'PasswordReset')+'">forget your password</a>?<br /> \
                Maybe you need to <a href="'+makeUrlNS('Special', 'Register')+'">create an account</a>.</small>';
       }
       form_html += ' \
              </td> \
            </tr> \
          </table> \
          <input type="hidden" id="ajaxlogin_crypt_key"       value="' + response.key + '" /> \
          <input type="hidden" id="ajaxlogin_crypt_challenge" value="' + response.challenge + '" /> \
        </form>';
        ajax_auth_mb_cache.updateContent(form_html);
        $('messageBox').object.nextSibling.firstChild.tabindex = '3';
        if ( typeof(response.username) == 'string' )
        {
          $('ajaxlogin_user').object.value = response.username;
          $('ajaxlogin_pass').object.focus();
        }
        else
        {
          $('ajaxlogin_user').object.focus();
        }
        $('ajaxlogin_pass').object.onblur = function(e) { if ( !shift ) $('messageBox').object.nextSibling.firstChild.focus(); };
        $('ajaxlogin_pass').object.onkeypress = function(e) { if ( !e && IE ) return true; if ( e.keyCode == 13 ) $('messageBox').object.nextSibling.firstChild.click(); };
      }
    });
}

function ajaxValidateLogin()
{
  var username,password,auth_enabled,crypt_key,crypt_data,challenge_salt,challenge_data;
  username = document.getElementById('ajaxlogin_user');
  if ( !username )
    return false;
  username = document.getElementById('ajaxlogin_user').value;
  password = document.getElementById('ajaxlogin_pass').value;
  auth_enabled = false;
  
  disableJSONExts();
  
  //
  // Encryption test
  //
  
  var str = '';
  for(i=0;i<keySizeInBits/4;i++)
  {
    str+='0';
  }
  str = hexToByteArray(str);
  var ct  = rijndaelEncrypt(str, str, 'ECB');
  ct      = byteArrayToHex(ct);
  var v;
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
  auth_enabled = ( ct == v && md5_vm_test() );
  if ( !auth_enabled )
  {
    alert('Login error: encryption sanity check failed\n');
    return true;
  }
  
  crypt_key = document.getElementById('ajaxlogin_crypt_key').value;
  challenge_salt = document.getElementById('ajaxlogin_crypt_challenge').value;
  
  var crypt_key_md5 = hex_md5(crypt_key);
  
  challenge_data = hex_md5(password + challenge_salt) + challenge_salt;
  
  password = stringToByteArray(password);
  crypt_key = hexToByteArray(crypt_key);
  
  crypt_data = rijndaelEncrypt(password, crypt_key, 'ECB');
  crypt_data = byteArrayToHex(crypt_data);
  
  var json_data = {
    'username' : username,
    'crypt_key' : crypt_key_md5,
    'challenge' : challenge_data,
    'crypt_data' : crypt_data,
    'level' : ajax_auth_level_cache
  };
  
  if ( window.console )
    window.console.debug(json_data);
  json_data = toJSONString(json_data);
  if ( window.console )
    window.console.debug(json_data);
  json_data = encodeURIComponent(json_data);
  if ( window.console )
    window.console.debug(json_data);
  
  var loading_win = '<div align="center" style="text-align: center;"> \
      <p>Logging in...</p> \
      <p><img alt="Please wait..." src="'+scriptPath+'/images/loading-big.gif" /></p> \
    </div>';
    
  ajax_auth_mb_cache.updateContent(loading_win);
  
  ajaxPost(makeUrlNS('Special', 'Login', 'act=ajaxlogin'), 'params=' + json_data, function() {
      if ( ajax.readyState == 4 )
      {
        var response = ajax.responseText;
        if ( response.substr(0,1) != '{' )
        {
          alert('Invalid JSON response from server: ' + response);
          ajaxAuthLoginInnerSetup();
          return false;
        }
        response = parseJSON(response);
        switch(response.result)
        {
          case 'success':
            if ( typeof(ajax_auth_prompt_cache) == 'function' )
            {
              ajax_auth_prompt_cache(response.key);
            }
            break;
          case 'success_reset':
            var conf = confirm('You have logged in using a temporary password. Before you can log in, you must finish resetting your password. Do you want to reset your real password now?');
            if ( conf )
            {
              var url = makeUrlNS('Special', 'PasswordReset/stage2/' + response.user_id + '/' + response.temppass);
              window.location = url;
            }
            else
            {
              ajaxAuthLoginInnerSetup();
            }
            break;
          case 'error':
            alert(response.error);
            ajaxAuthLoginInnerSetup();
            break;
          default:
            alert(ajax.responseText);
            break;
        }
      }
    });
  
  return true;
  
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
  return ( email.match(/^(?:[\w\d]+\.?)+@((?:(?:[\w\d]\-?)+\.)+\w{2,4}|localhost)$/) ) ? true : false;
}


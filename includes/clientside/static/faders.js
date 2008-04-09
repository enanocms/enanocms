// Message box and visual effect system

/**
 * Darkens the browser screen. This will make the entire page un-clickable except for any floating divs created after this is called. Restore with enlighten().
 * @param bool Controls whether the fade should be disabled or not. aclDisableTransitionFX will override this if set to true, and fades are never fired on IE.
 * @param int When specified, represents the numeric opacity value to set the fade layer to. 1-100.
 */

function darken(nofade, opacVal)
{
  if(IE)
    nofade = true;
  if ( !opacVal )
    opacVal = 70;
  if(document.getElementById('specialLayer_darkener'))
  {
    if(nofade)
    {
      changeOpac(opacVal, 'specialLayer_darkener');
      document.getElementById('specialLayer_darkener').style.display = 'block';
      document.getElementById('specialLayer_darkener').myOpacVal = opacVal;
    }
    else
    {
      if ( document.getElementById('specialLayer_darkener').style.display != 'none' )
      {
        var currentOpac = document.getElementById('specialLayer_darkener').myOpacVal;
        opacity('specialLayer_darkener', currentOpac, opacVal, 1000);
        document.getElementById('specialLayer_darkener').myOpacVal = opacVal;
      }
      else
      {
        document.getElementById('specialLayer_darkener').style.display = 'block';
        document.getElementById('specialLayer_darkener').myOpacVal = opacVal;
        opacity('specialLayer_darkener', 0, opacVal, 1000);
      }
    }
  } else {
    w = getWidth();
    h = getHeight();
    var thediv = document.createElement('div');
    if(IE)
      thediv.style.position = 'absolute';
    else
      thediv.style.position = 'fixed';
    if ( IE )
    {
      var top = getScrollOffset();
      thediv.style.top = String(top) + 'px';
    }
    else
    {
      thediv.style.top = '0px';
    }
    thediv.style.left = '0px';
    thediv.style.opacity = '0';
    thediv.style.filter = 'alpha(opacity=0)';
    thediv.style.backgroundColor = '#000000';
    thediv.style.width =  '100%';
    thediv.style.height = '100%';
    thediv.zIndex = getHighestZ() + 5;
    thediv.id = 'specialLayer_darkener';
    thediv.myOpacVal = opacVal;
    if(nofade)
    {
      thediv.style.opacity = ( parseFloat(opacVal) / 100 );
      thediv.style.filter = 'alpha(opacity=' + opacVal + ')';
      body = document.getElementsByTagName('body');
      body = body[0];
      body.appendChild(thediv);
    } else {
      body = document.getElementsByTagName('body');
      body = body[0];
      body.appendChild(thediv);
      opacity('specialLayer_darkener', 0, opacVal, 1000);
    }
  }
}

/**
 * Un-darkens the screen and re-enables clicking of on-screen controls.
 * @param bool If true, disables the fade effect. Fades are always disabled if aclDisableTransitionFX is true and on IE.
 */

function enlighten(nofade)
{
  if(IE)
    nofade = true;
  if(document.getElementById('specialLayer_darkener'))
  {
    if(nofade)
    {
      document.getElementById('specialLayer_darkener').style.display = 'none';
    }
    else
    {
      var from = document.getElementById('specialLayer_darkener').myOpacVal;
      // console.info('Fading from ' + from);
      opacity('specialLayer_darkener', from, 0, 1000);
      setTimeout("document.getElementById('specialLayer_darkener').style.display = 'none';", 1000);
    }
  }
}

/**
 * The ultimate message box framework for Javascript
 * Syntax is (almost) identical to the MessageBox command in NSIS
 * @param int type - a bitfield consisting of the MB_* constants
 * @param string title - the blue text at the top of the window
 * @param string text - HTML for the body of the message box
 * Properties:
 *   onclick - an array of functions to be called on button click events
 *             NOTE: key names are to be strings, and they must be the value of the input, CaSe-SeNsItIvE
 *   onbeforeclick - same as onclick but called before the messagebox div is destroyed
 * Methods:
 *   destroy: kills the running message box
 * Example:
 *   var my_message = new messagebox(MB_OK|MB_ICONSTOP, 'Error logging in', 'The username and/or password is incorrect. Please check the username and retype your password');
 *   my_message.onclick['OK'] = function() {
 *       document.getElementById('password').value = '';
 *     };
 * Deps:
 *   Modern browser that supports DOM
 *   darken() and enlighten() (above)
 *   opacity() - required for darken() and enlighten()
 *   MB_* constants are defined in enano-lib-basic.js
 */

var mb_current_obj;
var mb_previously_had_darkener = false;

function messagebox(type, title, message)
{
  var y = getScrollOffset();
  
  // Prevent multiple instances
  if ( document.getElementById('messageBox') )
    return;
  
  if ( document.getElementById('specialLayer_darkener') )
    if ( document.getElementById('specialLayer_darkener').style.display == 'block' )
      mb_previously_had_darkener = true;
  if ( !mb_previously_had_darkener )
    darken(true);
  if ( aclDisableTransitionFX )
  {
    document.getElementById('specialLayer_darkener').style.zIndex = '5';
  }
  var master_div = document.createElement('div');
  master_div.style.zIndex = '6';
  var mydiv = document.createElement('div');
  mydiv.style.height = '200px';
  w = getWidth();
  h = getHeight();
  if ( aclDisableTransitionFX )
  {
    master_div.style.left = ((w / 2) - 200)+'px';
    master_div.style.top = ((h / 2) + y - 120)+'px';
    master_div.style.position = 'absolute';
  }
  else
  {
    master_div.style.top = '-10000px';
    master_div.style.position = ( IE ) ? 'absolute' : 'fixed';
  }
  z = ( aclDisableTransitionFX ) ? document.getElementById('specialLayer_darkener').style.zIndex : getHighestZ();
  mydiv.style.backgroundColor = '#FFFFFF';
  mydiv.style.padding = '10px';
  mydiv.style.marginBottom = '1px';
  mydiv.id = 'messageBox';
  mydiv.style.overflow = 'auto';
  
  var buttondiv = document.createElement('div');
  
  if ( is_iPhone )
  {
    mydiv.style.width = '120px';
    buttondiv.style.width = '120px';
  }
  else
  {
    mydiv.style.width = '400px';
    buttondiv.style.width = '400px';
  }
  
  w = getWidth();
  h = getHeight();
  if ( aclDisableTransitionFX )
  {
    //buttondiv.style.left = ((w / 2) - 200)+'px';
    //buttondiv.style.top = ((h / 2) + y + 101)+'px';
  }
  //buttondiv.style.position = ( IE ) ? 'absolute' : 'fixed';
  z = ( aclDisableTransitionFX ) ? document.getElementById('specialLayer_darkener').style.zIndex : getHighestZ();
  buttondiv.style.backgroundColor = '#C0C0C0';
  buttondiv.style.padding = '10px';
  buttondiv.style.textAlign = 'right';
  buttondiv.style.verticalAlign = 'middle';
  buttondiv.id = 'messageBoxButtons';
  
  this.clickHandler = function() { messagebox_click(this, mb_current_obj); };
  
  if( ( type & MB_ICONINFORMATION || type & MB_ICONSTOP || type & MB_ICONQUESTION || type & MB_ICONEXCLAMATION ) && !(type & MB_ICONLOCK) )
  {
    mydiv.style.paddingLeft = '50px';
    mydiv.style.width = '360px';
    mydiv.style.backgroundRepeat = 'no-repeat';
    mydiv.style.backgroundPosition = '8px 8px';
  }
  else if ( type & MB_ICONLOCK )
  {
    mydiv.style.paddingLeft = '50px';
    mydiv.style.width = '360px';
    mydiv.style.backgroundRepeat = 'no-repeat';
  }
  
  if(type & MB_ICONINFORMATION)
  {
    mydiv.style.backgroundImage = 'url(\''+scriptPath+'/images/info.png\')';
  }
  
  if(type & MB_ICONQUESTION)
  {
    mydiv.style.backgroundImage = 'url(\''+scriptPath+'/images/question.png\')';
  }
  
  if(type & MB_ICONSTOP)
  {
    mydiv.style.backgroundImage = 'url(\''+scriptPath+'/images/error.png\')';
  }
  
  if(type & MB_ICONEXCLAMATION)
  {
    mydiv.style.backgroundImage = 'url(\''+scriptPath+'/images/warning.png\')';
  }
  
  if(type & MB_ICONLOCK)
  {
    mydiv.style.backgroundImage = 'url(\''+scriptPath+'/images/lock.png\')';
  }
  
  if(type & MB_OK)
  {
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_ok');
    btn._GenericName = 'OK';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
  }
  
  if(type & MB_OKCANCEL)
  {
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_ok');
    btn._GenericName = 'OK';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
    
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_cancel');
    btn._GenericName = 'Cancel';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
  }
  
  if(type & MB_YESNO)
  {
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_yes');
    btn._GenericName = 'Yes';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
    
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_no');
    btn._GenericName = 'No';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
  }
  
  if(type & MB_YESNOCANCEL)
  {
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_yes');
    btn._GenericName = 'Yes';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
    
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_no');
    btn._GenericName = 'No';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
    
    btn = document.createElement('input');
    btn.type = 'button';
    btn.value = $lang.get('etc_cancel');
    btn._GenericName = 'Cancel';
    btn.onclick = this.clickHandler;
    btn.style.margin = '0 3px';
    buttondiv.appendChild(btn);
  }
  
  heading = document.createElement('h2');
  heading.innerHTML = title;
  heading.style.color = '#50A0D0';
  heading.style.fontFamily = 'trebuchet ms, verdana, arial, helvetica, sans-serif';
  heading.style.fontSize = '12pt';
  heading.style.fontWeight = 'lighter';
  heading.style.textTransform = 'lowercase';
  heading.style.marginTop = '0';
  mydiv.appendChild(heading);
  
  var text = document.createElement('div');
  text.innerHTML = String(message);
  this.text_area = text;
  mydiv.appendChild(text);
  
  this.updateContent = function(text)
    {
      this.text_area.innerHTML = text;
    };
    
  this.destroy = function()
    {
      var mbdiv = document.getElementById('messageBox');
      mbdiv.parentNode.removeChild(mbdiv.nextSibling);
      mbdiv.parentNode.removeChild(mbdiv);
      if ( !mb_previously_had_darkener )
        enlighten(true);
    };
  
  //domObjChangeOpac(0, mydiv);
  //domObjChangeOpac(0, master_div);
  
  body = document.getElementsByTagName('body');
  body = body[0];
  master_div.appendChild(mydiv);
  master_div.appendChild(buttondiv);
  
  body.appendChild(master_div);
  
  if ( !aclDisableTransitionFX )
    setTimeout('mb_runFlyIn();', 100);
  
  this.onclick = new Array();
  this.onbeforeclick = new Array();
  mb_current_obj = this;
}

function mb_runFlyIn()
{
  var mydiv = document.getElementById('messageBox');
  var maindiv = mydiv.parentNode;
  fly_in_top(maindiv, true, false);
}

function messagebox_click(obj, mb)
{
  val = ( typeof ( obj._GenericName ) == 'string' ) ? obj._GenericName : obj.value;
  if(typeof mb.onbeforeclick[val] == 'function')
  {
    var o = mb.onbeforeclick[val];
    var resp = o();
    if ( resp )
      return false;
    o = false;
  }
  
  var mydiv = document.getElementById('messageBox');
  var maindiv = mydiv.parentNode;
  
  if ( aclDisableTransitionFX )
  {
    var mbdiv = document.getElementById('messageBox');
    mbdiv.parentNode.removeChild(mbdiv.nextSibling);
    mbdiv.parentNode.removeChild(mbdiv);
    if ( !mb_previously_had_darkener )
      enlighten(true);
  }
  else
  {
    var to = fly_out_top(maindiv, true, false);
    setTimeout("var mbdiv = document.getElementById('messageBox'); mbdiv.parentNode.removeChild(mbdiv.nextSibling); mbdiv.parentNode.removeChild(mbdiv); if ( !mb_previously_had_darkener ) enlighten(true);", to);
  }
  if(typeof mb.onclick[val] == 'function')
  {
    o = mb.onclick[val];
    o();
    o = false;
  }
}

function testMessageBox()
{
  mb = new messagebox(MB_OKCANCEL|MB_ICONINFORMATION, 'Javascripted dynamic message boxes', 'This is soooooo coool, now if only document.createElement() worked in IE!<br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text');
  mb.onclick['OK'] = function()
    {
      alert('You clicked OK!');
    }
  mb.onbeforeclick['Cancel'] = function()
    {
      alert('You clicked Cancel!');
    }
}

/**
 * The miniPrompt function, for creating small prompts and dialogs. The window will be flown in and the window darkened with opac=0.4.
 * @param function Will be passed an HTMLElement that is the body of the prompt window; the function can do with this as it pleases
 */

function miniPrompt(call_on_create)
{
  darken(false, 40);
  
  var wrapper = document.createElement('div');
  wrapper.className = 'miniprompt';
  var top = document.createElement('div');
  top.className = 'mp-top';
  var body = document.createElement('div');
  body.className = 'mp-body';
  var bottom = document.createElement('div');
  bottom.className = 'mp-bottom';
  if ( typeof(call_on_create) == 'function' )
  {
    call_on_create(body);
  }
  wrapper.appendChild(top);
  wrapper.appendChild(body);
  wrapper.appendChild(bottom);
  var left = ( getWidth() / 2 ) - ( 388 / 2 );
  wrapper.style.left = left + 'px';
  var top = getScrollOffset() - 27;
  wrapper.style.top = top + 'px';
  domObjChangeOpac(0, wrapper);
  var realbody = document.getElementsByTagName('body')[0];
  realbody.appendChild(wrapper);
  
  fly_in_top(wrapper, true, true);
  
  setTimeout(function()
    {
      domObjChangeOpac(100, wrapper);
    }, 40);
}

/**
 * For a given element, loops through the element and all of its ancestors looking for a miniPrompt div, and returns it. Returns false on failure.
 * @param object:HTMLElement Child node to scan
 * @return object
 */

function miniPromptGetParent(obj)
{
  while ( true )
  {
    // prevent infinite loops
    if ( !obj || obj.tagName == 'BODY' )
      return false;
    
    if ( $dynano(obj).hasClass('miniprompt') )
    {
      return obj;
    }
    obj = obj.parentNode;
  }
  return false;
}

/**
 * Destroys the first miniPrompt div encountered by recursively checking all parent nodes.
 * Usage: <a href="javascript:miniPromptDestroy(this);">click</a>
 * @param object:HTMLElement a child of the div.miniprompt
 * @param bool If true, does not call enlighten().
 */

function miniPromptDestroy(obj, nofade)
{
  obj = miniPromptGetParent(obj);
  if ( !obj )
    return false;
  
  // found it
  var parent = obj.parentNode;
  if ( !nofade )
    enlighten();
  var timeout = fly_out_top(obj, true, true);
  setTimeout(function()
    {
      parent.removeChild(obj);
    }, timeout);
}

/**
 * Simple test case
 */

function miniPromptTest()
{
  miniPrompt(function(div) { div.innerHTML = 'hello world! <a href="#" onclick="miniPromptDestroy(this); return false;">destroy me</a>'; });
}

// Function to fade classes info-box, warning-box, error-box, etc.

function fadeInfoBoxes()
{
  var divs = new Array();
  d = document.getElementsByTagName('div');
  j = 0;
  for(var i in d)
  {
    if ( !d[i] )
      continue;
    if ( !d[i].tagName )
      continue;
    if(d[i].className=='info-box' || d[i].className=='error-box' || d[i].className=='warning-box' || d[i].className=='question-box')
    {
      divs[j] = d[i];
      j++;
    }
  }
  if(divs.length < 1) return;
  for(i in divs)
  {
    if(!divs[i].id) divs[i].id = 'autofade_'+Math.floor(Math.random() * 100000);
    switch(divs[i].className)
    {
      case 'info-box':
      default:
        from = '#3333FF';
        break;
      case 'error-box':
        from = '#FF3333';
        break;
      case 'warning-box':
        from = '#FFFF33';
        break;
      case 'question-box':
        from = '#33FF33';
        break;
    }
    Fat.fade_element(divs[i].id,30,2000,from,Fat.get_bgcolor(divs[i].id));
  }
}

// Alpha fades

function opacity(id, opacStart, opacEnd, millisec) {
    //speed for each frame
    var speed = Math.round(millisec / 100);
    var timer = 0;

    //determine the direction for the blending, if start and end are the same nothing happens
    if(opacStart > opacEnd) {
        for(i = opacStart; i >= opacEnd; i--) {
            setTimeout("changeOpac(" + i + ",'" + id + "')",(timer * speed));
            timer++;
        }
    } else if(opacStart < opacEnd) {
        for(i = opacStart; i <= opacEnd; i++)
            {
            setTimeout("changeOpac(" + i + ",'" + id + "')",(timer * speed));
            timer++;
        }
    }
}

var opacityDOMCache = new Object();
function domOpacity(obj, opacStart, opacEnd, millisec) {
    //speed for each frame
    var speed = Math.round(millisec / 100);
    var timer = 0;
    
    // unique ID for this animation
    var uniqid = Math.floor(Math.random() * 1000000);
    opacityDOMCache[uniqid] = obj;

    //determine the direction for the blending, if start and end are the same nothing happens
    if(opacStart > opacEnd) {
        for(i = opacStart; i >= opacEnd; i--) {
            setTimeout("var obj = opacityDOMCache["+uniqid+"]; domObjChangeOpac(" + i + ",obj)",(timer * speed));
            timer++;
        }
    } else if(opacStart < opacEnd) {
        for(i = opacStart; i <= opacEnd; i++)
            {
            setTimeout("var obj = opacityDOMCache["+uniqid+"]; domObjChangeOpac(" + i + ",obj)",(timer * speed));
            timer++;
        }
    }
    setTimeout("delete(opacityDOMCache["+uniqid+"]);",(timer * speed));
}

//change the opacity for different browsers
function changeOpac(opacity, id) {
    var object = document.getElementById(id).style;
    object.opacity = (opacity / 100);
    object.MozOpacity = (opacity / 100);
    object.KhtmlOpacity = (opacity / 100);
    object.filter = "alpha(opacity=" + opacity + ")";
}

function mb_logout()
{
  var mb = new messagebox(MB_YESNO|MB_ICONQUESTION, $lang.get('user_logout_confirm_title'), $lang.get('user_logout_confirm_body'));
  mb.onclick['Yes'] = function()
    {
      window.location = makeUrlNS('Special', 'Logout/' + title);
    }
}

function whiteOutElement(el)
{
  var top = $(el).Top();
  var left = $(el).Left();
  var width = $(el).Width();
  var height = $(el).Height();
  
  var blackout = document.createElement('div');
  blackout.style.position = 'absolute';
  blackout.style.top = top + 'px';
  blackout.style.left = left + 'px';
  blackout.style.width = width + 'px';
  blackout.style.height = height + 'px';
  
  blackout.style.backgroundColor = '#FFFFFF';
  domObjChangeOpac(60, blackout);
  blackout.style.backgroundImage = 'url(' + scriptPath + '/includes/clientside/tinymce/themes/advanced/skins/default/img/progress.gif)';
  blackout.style.backgroundPosition = 'center center';
  blackout.style.backgroundRepeat = 'no-repeat';
  blackout.style.zIndex = getHighestZ() + 2;
  
  var body = document.getElementsByTagName('body')[0];
  body.appendChild(blackout);
  
  return blackout;
}


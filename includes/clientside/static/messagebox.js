// Message box and visual effect system

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
 *   var my_message = new MessageBox(MB_OK|MB_ICONSTOP, 'Error logging in', 'The username and/or password is incorrect. Please check the username and retype your password');
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

function MessageBox(type, title, message)
{
  if ( !aclDisableTransitionFX )
  {
    load_component('flyin');
  }
  
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
  master_div.style.zIndex = String(getHighestZ() + 5);
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
  
  mydiv.style.width = '400px';
  buttondiv.style.width = '400px';
  
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

var messagebox = MessageBox;

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
  mb = new MessageBox(MB_OKCANCEL|MB_ICONINFORMATION, 'Javascripted dynamic message boxes', 'This is soooooo coool, now if only document.createElement() worked in IE!<br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text<br /><br /><br /><br /><br />this is some more text');
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
  if ( !aclDisableTransitionFX )
  {
    load_component('flyin');
  }
  if ( document.getElementById('specialLayer_darkener') )
  {
    if ( document.getElementById('specialLayer_darkener').style.display != 'none' )
    {
      var opac = parseFloat(document.getElementById('specialLayer_darkener').style.opacity);
      opac = opac * 100;
      darken(aclDisableTransitionFX, opac);
    }
    else
    {
      darken(aclDisableTransitionFX, 40);
    }
  }
  else
  {
    darken(aclDisableTransitionFX, 40);
  }
  
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
  
  if ( aclDisableTransitionFX )
  {
    domObjChangeOpac(100, wrapper);
  }
  else
  {
    fly_in_top(wrapper, true, true);
    
    setTimeout(function()
      {
        domObjChangeOpac(100, wrapper);
      }, 40);
  }
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
    enlighten(aclDisableTransitionFX);
  if ( aclDisableTransitionFX )
  {
    parent.removeChild(obj);
  }
  else
  {
    var timeout = fly_out_top(obj, true, true);
    setTimeout(function()
      {
        parent.removeChild(obj);
      }, timeout);
  }
}

/**
 * Simple test case
 */

function miniPromptTest()
{
  miniPrompt(function(div) { div.innerHTML = 'hello world! <a href="#" onclick="miniPromptDestroy(this); return false;">destroy me</a>'; });
}

/**
 * Message box system for miniPrompts. Less customization but easier to scale than the regular messageBox framework.
 * @example
 <code>
 miniPromptMessage({
   title: 'Delete page',
   message: 'Do you really want to delete this page? This is reversible unless you clear the page logs.',
   buttons: [
     {
       text: 'Delete',
       color: 'red',
       style: {
         fontWeight: 'bold'
       },
       onclick: function() {
         ajaxDeletePage();
         miniPromptDestroy(this);
       }
     },
     {
       text: 'cancel',
       onclick: function() {
         miniPromptDestroy(this);
       }
     }
   ]
 });
 </code>
 */

function miniPromptMessage(parms)
{
  if ( !parms.title || !parms.message || !parms.buttons )
    return false;
  
  return miniPrompt(function(parent)
    {
      try
      {
        var h3 = document.createElement('h3');
        h3.appendChild(document.createTextNode(parms.title));
        var body = document.createElement('p');
        var message = parms.message.split(unescape('%0A'));
        for ( var i = 0; i < message.length; i++ )
        {
          body.appendChild(document.createTextNode(message[i]));
          if ( i + 1 < message.length )
            body.appendChild(document.createElement('br'));
        }
        
        parent.style.textAlign = 'center';
        
        parent.appendChild(h3);
        parent.appendChild(body);
        parent.appendChild(document.createElement('br'));
        
        // construct buttons
        for ( var i = 0; i < parms.buttons.length; i++ )
        {
          var button = parms.buttons[i];
          button.input = document.createElement('a');
          button.input.href = '#';
          button.input.clickAction = button.onclick;
          button.input.className = 'abutton';
          if ( button.color )
          {
            button.input.className += ' abutton_' + button.color;
          }
          button.input.appendChild(document.createTextNode(button.text));
          if ( button.style )
          {
            for ( var j in button.style )
            {
              button.input.style[j] = button.style[j];
            }
          }
          button.input.onclick = function(e)
          {
            try
            {
              this.clickAction(e);
            }
            catch(e)
            {
              console.error(e);
            }
            return false;
          }
          parent.appendChild(button.input);
        }
        if ( parms.buttons[0] )
        {
          setTimeout(function()
            {
              parms.buttons[0].input.focus();
            }, 300);
        }
      }
      catch ( e )
      {
        console.error(e);
      }
    });
}

function testMPMessageBox()
{
  miniPromptMessage({
    title: 'The Game of LIFE question #73',
    message: 'You just got your girlfriend pregnant. Please select an option:',
    buttons: [
      {
        text: 'Abort',
        color: 'red',
        style: {
          fontWeight: 'bold'
        },
        onclick: function() {
          miniPromptDestroy(this);
        }
      },
      {
        text: 'Retry',
        color: 'blue',
        onclick: function() {
          miniPromptDestroy(this);
        }
      },
      {
        text: 'Ignore',
        color: 'green',
        onclick: function() {
          miniPromptDestroy(this);
        }
      }
    ]
  });
}


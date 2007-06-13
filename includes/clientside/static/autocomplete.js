/*
 * Auto-completing page/username fields
 */

// The ultimate Javascript app: AJAX auto-completion, which responds to up/down arrow keys, the enter key, and the escape key
// The idea was pilfered mercilessly from vBulletin, but uses about 8
// bytes of vB code. All the rest was coded by me, Mr. Javascript Newbie...

// ...in about 8 hours.
// You folks better like this stuff.

function nameCompleteEventHandler(e)
{
  if(!e) e = window.event;
  switch(e.keyCode)
  {
    case 38: // up
      unSelectMove('up');
      break;
    case 40: // down
      unSelectMove('down');
      break;
    case 27: // escape
    case 9:  // tab
      destroyUsernameDropdowns();
      break;
    case 13: // enter
      unSelect();
      break;
    default: return false; break;
  }
  return true;
}

function unSelectMove(dir)
{
  if(submitAuthorized) return false;
  var thediv = document.getElementById(unObjDivCurrentId);
  thetable = thediv.firstChild;
  cel = thetable.firstChild.firstChild;
  d = true;
  index = false;
  changed = false;
  // Object of the game: extract the username, determine its index in the userlist array, and then color the menu items and set unObjCurrentSelection
  while(d) // Set to false if an exception occurs or if we arrive at our destination
  {
    //*
    if(!cel) d=false;
    celbak = cel;
    cel = cel.nextSibling;
    if(!cel) d=false;
    try {
      if(cel.firstChild.nextSibling) html = cel.firstChild.nextSibling.innerHTML;
      else html = cel.firstChild.innerHTML;
      cel.firstChild.className = 'row1';
      if(cel.firstChild.nextSibling) cel.firstChild.nextSibling.className = 'row1';
      thename = html.substr(7, html.length-15);
      // FINALLY! we have extracted the username
      // Now get its position in the userlist array
      if(thename == unObjCurrentSelection)
      {
        index = parseInt(in_array(thename, userlist));
      }
      if(typeof(index) == 'number')
      {
        if(dir=='down')
          n = index+1;
        else if(dir == 'up')
          n = index - 1;
        
        // Try to trap moving the selection up or down beyond the top of bottom
        if(n > userlist.length-1 || n < 0)
        {
          cel.firstChild.className = 'row2';
          if(cel.firstChild.nextSibling) cel.firstChild.nextSibling.className = 'row2';
          return;
        }
        
        if(dir=='down') no=cel.nextSibling;
        else if(dir=='up') no=cel.previousSibling;
        no.firstChild.className = 'row2';
        if(no.firstChild.nextSibling) no.firstChild.nextSibling.className = 'row2';
        if(no.firstChild.id)
        {
          scroll = getScrollOffset() + getHeight();
          elemht = getElementHeight(no.firstChild.id);
          elemoff = fetch_offset(no.firstChild);
          whereto = elemoff['top'] + elemht;
          if(whereto > scroll)
          {
            window.location.hash = '#'+no.firstChild.id;
            unObj.focus();
          }
        }
        cel=cel.nextSibling;
        unObjCurrentSelection = userlist[n];
        index = false;
        changed = true;
        return;
      }
    } catch(e) { }
    //*/ d = false;
  }
}

function unSelect()
{
  if(!unObj || submitAuthorized) return false;
  if ( unObjCurrentSelection )
    unObj.value = unObjCurrentSelection;
  destroyUsernameDropdowns(); 
}

function in_array(needle, haystack)
{
  for(var i in haystack)
  {
    if(haystack[i] == needle) return i;
  }
  return false;
}

function ajaxUserNameComplete(o)
{
  if(!o) {destroyUsernameDropdowns(); return;}
  if(!o.value) {destroyUsernameDropdowns(); return;}
  if(o.value.length < 3) {destroyUsernameDropdowns(); return;}
  //if(IE) return; // This control doesn't work in IE. Yes, it's true! document.createElement doesn't work.
  if(!o.id)
  {
    o.id = 'usernametextboxobj_' + Math.floor(Math.random() * 10000000);
  }
  unObj = o;
  o.setAttribute("autocomplete","off");
  o.onkeyup = function(e, o) { o=unObj; if(!nameCompleteEventHandler(e)) ajaxUserNameComplete(o); }
  val = escape(o.value).replace('+', '%2B');
  ajaxGet(stdAjaxPrefix+'&_mode=fillusername&name='+val, function()
    {
      if(ajax.readyState==4)
      {
        // Determine the appropriate left/top positions, then create a div to use for the drop-down list
        // The trick here is to be able to make the div dynamically destroy itself depending on how far the user's mouse is from it
        destroyUsernameDropdowns();
        off = fetch_offset(unObj);
        dim = fetch_dimensions(unObj);
        left = off['left'];
        i1 = off['top'];
        i2 = dim['h'];
        var top = 0;
        top = i1 + i2;
        var thediv = document.createElement('div');
        thediv.className = 'tblholder';
        thediv.style.marginTop = '0px';
        thediv.style.position = 'absolute';
        thediv.style.top  = top  + 'px';
        thediv.style.left = left + 'px';
        thediv.style.zIndex = getHighestZ() + 2;
        id = 'usernamehoverobj_' + Math.floor(Math.random() * 10000000);
        unObjDivCurrentId = id;
        thediv.id = id;
        unObj.onblur = function() { destroyUsernameDropdowns(); }
        
        eval(ajax.responseText);
        if(errorstring)
        {
          html = '<span style="color: #555; padding: 4px;">'+errorstring+'</span>';
        }
        else
        {
          html = '<table border="0" cellspacing="1" cellpadding="3" style="width: auto;"><tr><th><small>Username matches</small></th></tr>';
          cls = 'row2';
          unObjCurrentSelection = userlist[0];
          for(i=0;i<userlist.length;i++)
          {
            tmpnam = 'listobjnode_'+Math.floor(Math.random() * 10000000);
            html = html + '<tr><td id="'+tmpnam+'" class="'+cls+'" style="cursor: pointer;" onclick="document.getElementById(\''+unObj.id+'\').value=\''+userlist[i]+'\';destroyUsernameDropdowns();"><small>'+userlist[i]+'</small></td></tr>';
            if(cls=='row2') cls='row1';
          }
          html = html + '</table>';
        }
        
        thediv.innerHTML = html;
        var body = document.getElementsByTagName('body');
        body = body[0];
        unSelectMenuOn = true;
        submitAuthorized = false;
        body.appendChild(thediv);
      }
    });
}

function ajaxPageNameComplete(o)
{
  if(!o) {destroyUsernameDropdowns(); return;}
  if(!o.value) {destroyUsernameDropdowns(); return;}
  if(o.value.length < 3) {destroyUsernameDropdowns(); return;}
  if(IE) return; // This control doesn't work in IE. Yes, it's true! document.createElement doesn't work.
  if(!o.id)
  {
    o.id = 'usernametextboxobj_' + Math.floor(Math.random() * 10000000);
  }
  o.setAttribute("autocomplete","off");
  unObj = o;
  o.onkeyup = function(e, o) { o=unObj; if(!nameCompleteEventHandler(e)) ajaxPageNameComplete(o); }
  val = escape(o.value).replace('+', '%2B');
  ajaxGet(stdAjaxPrefix+'&_mode=fillpagename&name='+val, function()
    {
      if(!ajax) return;
      if(ajax.readyState==4)
      {
        // Determine the appropriate left/top positions, then create a div to use for the drop-down list
        // The trick here is to be able to make the div dynamically destroy itself depending on how far the user's mouse is from it
        destroyUsernameDropdowns();
        off = fetch_offset(unObj);
        dim = fetch_dimensions(unObj);
        left = off['left'];
        top = off['top'] + dim['h'];
        var thediv = document.createElement('div');
        thediv.className = 'tblholder';
        thediv.style.marginTop = '0px';
        thediv.style.position = 'absolute';
        thediv.style.top  = top  + 'px';
        thediv.style.left = left + 'px';
        thediv.style.zIndex = getHighestZ() + 2;
        id = 'usernamehoverobj_' + Math.floor(Math.random() * 10000000);
        unObjDivCurrentId = id;
        thediv.id = id;
        
        eval(ajax.responseText);
        if(errorstring)
        {
          html = '<span style="color: #555; padding: 4px;">'+errorstring+'</span>';
        }
        else
        {
          html = '<table border="0" cellspacing="1" cellpadding="3" style="width: auto;"><tr><th colspan="2">Page name matches</th></tr><tr><th><small>Page title</small></th><th><small>Page ID</small></th></tr>';
          cls = 'row2';
          unObjCurrentSelection = userlist[0];
          for(i=0;i<userlist.length;i++)
          {
            tmpnam = 'listobjnode_'+Math.floor(Math.random() * 10000000);
            html = html + '<tr><td id="'+tmpnam+'" class="'+cls+'" style="cursor: pointer;" onclick="document.getElementById(\''+unObj.id+'\').value=\''+userlist[i]+'\';destroyUsernameDropdowns();"><small>'+namelist[i]+'</small></td><td class="'+cls+'" style="cursor: pointer;" onclick="document.getElementById(\''+unObj.id+'\').value=\''+userlist[i]+'\';destroyUsernameDropdowns();"><small>'+userlist[i]+'</small></td></tr>';
            if(cls=='row2') cls='row1';
          }
          html = html + '</table>';
        }
        
        thediv.innerHTML = html;
        var body = document.getElementsByTagName('body');
        body = body[0];
        unSelectMenuOn = true;
        submitAuthorized = false;
        body.appendChild(thediv);
        
        unObj.onblur = function() { CheckDestroyUsernameDropdowns(thediv.id); };
      }
    });
}

function CheckDestroyUsernameDropdowns(id)
{
  elem = document.getElementById(id);
  if(!elem) return;
  if(queryOnObj(elem, 100))
  {
    destroyUsernameDropdowns();
  }
}

function destroyUsernameDropdowns()
{
  var divs = document.getElementsByTagName('div');
  var prefix = 'usernamehoverobj_';
  for(i=0;i<divs.length;i++)                                                                                                                                                                                                                         
  {
    if ( divs[i].id )
    {
      if(divs[i].id.substr(0, prefix.length)==prefix)
      {
        divs[i].innerHTML = '';
        divs[i].style.display = 'none';
      }
    }
  }
  unSelectMenuOn = false;
  unObjDivCurrentId = false;
  unObjCurrentSelection = false;
  submitAuthorized = true;
}

function get_parent_form(o)
{
  if ( !o.parentNode )
    return false;
  if ( o.tagName == 'FORM' )
    return o;
  var p = o.parentNode;
  while(true)
  {
    if ( p.tagName == 'FORM' )
      return p;
    else if ( !p )
      return false;
    else
      p = p.parentNode;
  }
}


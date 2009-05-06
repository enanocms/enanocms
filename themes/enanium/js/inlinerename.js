// Sidebar collapse
function enanium_toggle_sidebar_right()
{
  if ( document.getElementById('enanium_sidebar_right').style.display == 'none' )
  {
    // show
    document.getElementById('enanium_sidebar_right').style.display = 'block';
    document.getElementById('enanium_sidebar_right_hidden').style.display = 'none';
    createCookie('right_sidebar', 'open', 365);
  }
  else
  {
    // hide
    document.getElementById('enanium_sidebar_right').style.display = 'none';
    document.getElementById('enanium_sidebar_right_hidden').style.display = 'block';
    createCookie('right_sidebar', 'collapsed', 365);
  }
}

addOnloadHook(function()
  {
    if ( readCookie('right_sidebar') == 'collapsed' )
    {
      document.getElementById('enanium_sidebar_right').style.display = 'none';
      document.getElementById('enanium_sidebar_right_hidden').style.display = 'block';
    }
  });

// Inline rename

function ajaxRenameInline()
{
  if ( KILL_SWITCH || IE )
    return false;
  // This trick is _so_ vBulletin...
  elem = document.getElementById('h2PageName');
  if(!elem) return;
  elem.style.display = 'none';
  name = elem.firstChild.nodeValue;
  textbox = document.createElement('input');
  textbox.type = 'text';
  textbox.value = name;
  textbox.id = 'pageheading';
  textbox.size = name.length + 7;
  textbox.onkeyup = function(e) { if(!e) return; if(e.keyCode == 13) ajaxRenameInlineSave(); if(e.keyCode == 27) ajaxRenameInlineCancel(); };
  textbox.oldname = name;
  elem.parentNode.insertBefore(textbox, elem);
  document.onclick = ajaxRenameInlineCancel;
  
  load_component(['l10n', 'fadefilter', 'messagebox']);
  textbox.focus();
  textbox.select();
}

function ajaxRenameInlineSave()
{
  elem1 = document.getElementById('h2PageName');
  elem2 = document.getElementById('pageheading');
  if(!elem1 || !elem2) return;
  value = elem2.value;
  elem2.parentNode.removeChild(elem2); // just destroy the thing
  elem1.removeChild(elem1.firstChild);
  elem1.appendChild(document.createTextNode(value));
  elem1.style.display = 'block';
  if(!value || value=='' || value==elem2.oldname) return;
  setAjaxLoading();
  ajaxPost(stdAjaxPrefix+'&_mode=rename', 'newtitle='+ajaxEscape(value), function() {
    if ( ajax.readyState == 4 )
    {
      unsetAjaxLoading();
      var response = String(ajax.responseText);
      if ( !check_json_response(response) )
      {
        handle_invalid_json(response);
        return false;
      }
      response = parseJSON(response);
      if ( response.success )
      {
        new MessageBox( MB_OK|MB_ICONINFORMATION, $lang.get('ajax_rename_success_title'), $lang.get('ajax_rename_success_body', { page_name_new: value }) );
      }
      else
      {
        alert(response.error);
      }
    }
  });
}

function ajaxRenameInlineCancel(e)
{
  if ( typeof(e) != 'object' && IE )
    e = window.event;
  elem1 = document.getElementById('h2PageName');
  elem2 = document.getElementById('pageheading');
  if(!elem1 || !elem2) return;
  if ( typeof(e) == 'object' && e.target )
  {
    if(e.target == elem2)
      return;
  }
  //value = elem2.value;
  elem2.parentNode.removeChild(elem2); // just destroy the thing
  //elem1.innerHTML = value;
  elem1.style.display = 'block';
  document.onclick = null;
}

if ( window.auth_rename )
{
  addOnloadHook(function()
    {
      var h2 = document.getElementById('h2PageName');
      if ( h2 )
      {
        h2.ondblclick = function()
        {
          ajaxRenameInline();
        }
      }
    });
}

/*
 * AJAX applets
 */
 
function ajaxGet(uri, f, call_editor_safe) {
  // Is the editor open?
  if ( editor_open && !call_editor_safe )
  {
    // Make sure the user is willing to close the editor
    var conf = confirm($lang.get('editor_msg_confirm_ajax'));
    if ( !conf )
    {
      // Kill off any "loading" windows, etc. and cancel the request
      unsetAjaxLoading();
      return false;
    }
    // The user allowed the editor to be closed. Reset flags and knock out the on-close confirmation.
    editor_open = false;
    enableUnload();
  }
  if (window.XMLHttpRequest) {
    ajax = new XMLHttpRequest();
  } else {
    if (window.ActiveXObject) {           
      ajax = new ActiveXObject("Microsoft.XMLHTTP");
    } else {
      alert('Enano client-side runtime error: No AJAX support, unable to continue');
      return;
    }
  }
  ajax.onreadystatechange = f;
  ajax.open('GET', uri, true);
  ajax.setRequestHeader( "If-Modified-Since", "Sat, 1 Jan 2000 00:00:00 GMT" );
  ajax.send(null);
}

function ajaxPost(uri, parms, f, call_editor_safe) {
  // Is the editor open?
  if ( editor_open && !call_editor_safe )
  {
    // Make sure the user is willing to close the editor
    var conf = confirm($lang.get('editor_msg_confirm_ajax'));
    if ( !conf )
    {
      // Kill off any "loading" windows, etc. and cancel the request
      unsetAjaxLoading();
      return false;
    }
    // The user allowed the editor to be closed. Reset flags and knock out the on-close confirmation.
    editor_open = false;
    enableUnload();
  }
  if (window.XMLHttpRequest) {
    ajax = new XMLHttpRequest();
  } else {
    if (window.ActiveXObject) {           
      ajax = new ActiveXObject("Microsoft.XMLHTTP");
    } else {
      alert('Enano client-side runtime error: No AJAX support, unable to continue');
      return;
    }
  }
  ajax.onreadystatechange = f;
  ajax.open('POST', uri, true);
  ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  // Setting Content-length in Safari triggers a warning
  if ( !is_Safari )
  {
    ajax.setRequestHeader("Content-length", parms.length);
  }
  ajax.setRequestHeader("Connection", "close");
  ajax.send(parms);
}

/**
 * Show a friendly error message depicting an AJAX response that is not valid JSON
 * @param string Response text
 * @param string Custom error message. If omitted, the default will be shown.
 */

function handle_invalid_json(response, customerror)
{
  var mainwin = $dynano('ajaxEditContainer').object;
  mainwin.innerHTML = '';
  
  // Title
  var h3 = document.createElement('h3');
  h3.appendChild(document.createTextNode('The site encountered an error while processing your request.'));
  mainwin.appendChild(h3);
  
  if ( typeof(customerror) == 'string' )
  {
    var el = document.createElement('p');
    el.appendChild(document.createTextNode(customerror));
    mainwin.appendChild(el);
  }
  else
  {
    customerror  = 'We unexpectedly received the following response from the server. The response should have been in the JSON ';
    customerror += 'serialization format, but the response wasn\'t composed only of the JSON response. There are three possible triggers ';
    customerror += 'for this problem:';
    var el = document.createElement('p');
    el.appendChild(document.createTextNode(customerror));
    mainwin.appendChild(el);
    var ul = document.createElement('ul');
    var li1 = document.createElement('li');
    var li2 = document.createElement('li');
    var li3 = document.createElement('li');
    li1.appendChild(document.createTextNode('The server sent back a bad HTTP response code and thus sent an error page instead of running Enano. This indicates a possible problem with your server, and is not likely to be a bug with Enano.'));
    var osc_exception = ( window.location.hostname == 'demo.opensourcecms.com' ) ? ' This is KNOWN to be the case with the OpenSourceCMS.com demo version of Enano.' : '';
    li2.appendChild(document.createTextNode('The server sent back the expected JSON response, but also injected some code into the response that should not be there. Typically this consists of advertisement code. In this case, the administrator of this site will have to contact their web host to have advertisements disabled.' + osc_exception));
    li3.appendChild(document.createTextNode('It\'s possible that Enano triggered a PHP error or warning. In this case, you may be looking at a bug in Enano.'));
      
    ul.appendChild(li1);
    ul.appendChild(li2);
    ul.appendChild(li3);
    mainwin.appendChild(ul);
  }
  
  var p2 = document.createElement('p');
  p2.appendChild(document.createTextNode('The response received from the server is as follows:'));
  mainwin.appendChild(p2);
  
  var pre = document.createElement('pre');
  pre.appendChild(document.createTextNode(response));
  mainwin.appendChild(pre);
  
  var p3 = document.createElement('p');
  p3.appendChild(document.createTextNode('You may also choose to view the response as HTML. '));
  var a = document.createElement('a');
  a.appendChild(document.createTextNode('View as HTML...'));
  a._resp = response;
  a.id = 'invalidjson_link';
  a.onclick = function()
  {
    var mb = new messagebox(MB_YESNO | MB_ICONEXCLAMATION, 'Do you really want to view this response as HTML?', 'If the response was changed during transmission to include malicious code, you may be allowing that malicious code to run by viewing the response as HTML. Only do this if you have reviewed the response text and have found no suspicious code in it.');
    mb.onclick['Yes'] = function()
    {
      var html = $dynano('invalidjson_link').object._resp;
      var win = window.open('about:blank', 'invalidjson_htmlwin', 'width=550,height=400,status=no,toolbars=no,toolbar=no,address=no,scroll=yes');
      win.document.write(html);
    }
    return false;
  }
  a.href = '#';
  p3.appendChild(a);
  mainwin.appendChild(p3);
}

function ajaxEscape(text)
{
  /*
  text = escape(text);
  text = text.replace(/\+/g, '%2B', text);
  */
  text = window.encodeURIComponent(text);
  return text;
}

function ajaxAltEscape(text)
{
  text = escape(text);
  text = text.replace(/\+/g, '%2B', text);
  return text;
}

function ajaxDiscard()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  c = confirm($lang.get('editor_msg_discard_confirm'));
  if(!c) return;
  ajaxReset();
}

function ajaxReset()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var ns_id = strToPageID(title);
  if ( ns_id[1] == 'Special' || ns_id[1] == 'Admin' )
    return false;
  enableUnload();
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=getpage&noheaders', function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      document.getElementById('ajaxEditContainer').innerHTML = ajax.responseText;
      selectButtonMajor('article');
      unselectAllButtonsMinor();
    }
  });
}

// Miscellaneous AJAX applets

function ajaxProtect(l) {
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if(shift) {
    r = 'NO_REASON';
  } else {
    r = prompt($lang.get('ajax_protect_prompt_reason'));
    if(!r || r=='') return;
  }
  setAjaxLoading();
  document.getElementById('protbtn_0').style.textDecoration = 'none';
  document.getElementById('protbtn_1').style.textDecoration = 'none';
  document.getElementById('protbtn_2').style.textDecoration = 'none';
  document.getElementById('protbtn_'+l).style.textDecoration = 'underline';
  ajaxPost(stdAjaxPrefix+'&_mode=protect', 'reason='+ajaxEscape(r)+'&level='+l, function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      if(ajax.responseText != 'good')
        alert(ajax.responseText);
    }
  }, true);
}

function ajaxRename()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  r = prompt($lang.get('ajax_rename_prompt'));
  if(!r || r=='') return;
  setAjaxLoading();
  ajaxPost(stdAjaxPrefix+'&_mode=rename', 'newtitle='+ajaxEscape(r), function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      alert(ajax.responseText);
    }
  }, true);
}

function ajaxMakePage()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  setAjaxLoading();
  ajaxPost(ENANO_SPECIAL_CREATEPAGE, ENANO_CREATEPAGE_PARAMS, function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      window.location.reload();
    }
  });
}

function ajaxDeletePage()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var reason = prompt($lang.get('ajax_delete_prompt_reason'));
  if ( !reason || reason == '' )
  {
    return false;
  }
  c = confirm($lang.get('ajax_delete_confirm'));
  if(!c)
  {
    return;
  }
  setAjaxLoading();
  ajaxPost(stdAjaxPrefix+'&_mode=deletepage', 'reason=' + ajaxEscape(reason), function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      alert(ajax.responseText);
      window.location.reload();                                                                           
    }
  });
}

function ajaxDelVote()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  c = confirm($lang.get('ajax_delvote_confirm'));
  if(!c) return;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=delvote', function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      alert(ajax.responseText);
    }
  }, true);
}

function ajaxResetDelVotes()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  c = confirm($lang.get('ajax_delvote_reset_confirm'));
  if(!c) return;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=resetdelvotes', function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      alert(ajax.responseText);
      item = document.getElementById('mdgDeleteVoteNoticeBox');
      if(item)
      {
        opacity('mdgDeleteVoteNoticeBox', 100, 0, 1000);
        setTimeout("document.getElementById('mdgDeleteVoteNoticeBox').style.display = 'none';", 1000);
      }
    }
  }, true);
}

function ajaxSetWikiMode(val) {
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  setAjaxLoading();
  document.getElementById('wikibtn_0').style.textDecoration = 'none';
  document.getElementById('wikibtn_1').style.textDecoration = 'none';
  document.getElementById('wikibtn_2').style.textDecoration = 'none';
  document.getElementById('wikibtn_'+val).style.textDecoration = 'underline';
  ajaxGet(stdAjaxPrefix+'&_mode=setwikimode&mode='+val, function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      if(ajax.responseText!='GOOD')
      {
        alert(ajax.responseText);
      }
    }
  });
}

// Editing/saving category information
// This was not easy to write, I hope enjoy it, and dang I swear I'm gonna
// find someone to work on just the Javascript part of Enano...

function ajaxCatEdit()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=catedit', function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      edit_open = false;
      eval(ajax.responseText);
    }
  });
}

function ajaxCatSave()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if(!catlist)
  {
    alert('Var catlist has no properties');
    return;
  }
  query='';
  for(i=0;i<catlist.length;i++)
  {
    l = 'if(document.forms.mdgCatForm.mdgCat_'+catlist[i]+'.checked) s = true; else s = false;';
    eval(l);
    if(s) query = query + '&' + catlist[i] + '=true';
  }
  setAjaxLoading();
  query = query.substring(1, query.length);
  ajaxPost(stdAjaxPrefix+'&_mode=catsave', query, function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      edit_open = false;
      if(ajax.responseText != 'GOOD') alert(ajax.responseText);
      ajaxReset();
    }
  });
}

// History stuff

function ajaxHistory()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=histlist', function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      edit_open = false;
      selectButtonMajor('article');
      selectButtonMinor('history');
      document.getElementById('ajaxEditContainer').innerHTML = ajax.responseText;
      buildDiffList();
    }
  });
}

function ajaxHistView(oldid, tit) {
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if(!tit) tit=title;
  setAjaxLoading();
  ajaxGet(append_sid(scriptPath+'/ajax.php?title='+tit+'&_mode=getpage&oldid='+oldid), function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      edit_open = false;
      document.getElementById('ajaxEditContainer').innerHTML = ajax.responseText;
    }
  });
}

function ajaxRollback(id) {
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=rollback&id='+id, function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      alert(ajax.responseText);
    }
  });
}

function ajaxClearLogs()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  c = confirm($lang.get('ajax_clearlogs_confirm'));
  if(!c) return;
  c = confirm($lang.get('ajax_clearlogs_confirm_nag'));
  if(!c) return;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=flushlogs', function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      alert(ajax.responseText);
      window.location.reload();
    }
  });
}

var timelist;

function buildDiffList()
{
  arrDiff1Buttons = getElementsByClassName(document, 'input', 'clsDiff1Radio');
  arrDiff2Buttons = getElementsByClassName(document, 'input', 'clsDiff2Radio');
  var len = arrDiff1Buttons.length;
  if ( len < 1 )
    return false;
  timelist = new Array();
  for ( var i = 0; i < len; i++ )
  {
    timelist.push( arrDiff2Buttons[i].id.substr(6) );
  }
  timelist.push( arrDiff1Buttons[len-1].id.substr(6) );
  delete(timelist.toJSONString);
  for ( var i = 1; i < timelist.length-1; i++ )
  {
    if ( i >= timelist.length ) break;
    arrDiff2Buttons[i].style.display = 'none';
  }
}

function selectDiff1Button(obj)
{
  var this_time = obj.id.substr(6);
  var index = parseInt(in_array(this_time, timelist));
  for ( var i = 0; i < timelist.length - 1; i++ )
  {
    if ( i < timelist.length - 1 )
    {
      var state = ( i < index ) ? 'inline' : 'none';
      var id = 'diff2_' + timelist[i];
      document.getElementById(id).style.display = state;
      
      // alert("Debug:\nIndex: "+index+"\nState: "+state+"\ni: "+i);
    }
  }
}

function selectDiff2Button(obj)
{
  var this_time = obj.id.substr(6);
  var index = parseInt(in_array(this_time, timelist));
  for ( var i = 1; i < timelist.length; i++ )
  {
    if ( i < timelist.length - 1 )
    {
      var state = ( i > index ) ? 'inline' : 'none';
      var id = 'diff1_' + timelist[i];
      document.getElementById(id).style.display = state;
      
      // alert("Debug:\nIndex: "+index+"\nState: "+state+"\ni: "+i);
    }
  }
}

function ajaxHistDiff()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var id1=false;
  var id2=false;
  for ( i = 0; i < arrDiff1Buttons.length; i++ )
  {
    k = i + '';
    kpp = i + 1;
    kpp = kpp + '';
    if(arrDiff1Buttons[k].checked) id1 = arrDiff1Buttons[k].id.substr(6);
    if(arrDiff2Buttons[k].checked) id2 = arrDiff2Buttons[k].id.substr(6);
  }
  if(!id1 || !id2) { alert('BUG: Couldn\'t get checked radiobutton state'); return; }
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=pagediff&diff1='+id1+'&diff2='+id2, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        unsetAjaxLoading();
        document.getElementById('ajaxEditContainer').innerHTML = ajax.responseText;
      }
    });
}

// Change the user's preferred style/theme

function ajaxChangeStyle()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var inner_html = '';
  inner_html += '<p><label>' + $lang.get('ajax_changestyle_lbl_theme') + ' ';
  inner_html += '  <select id="chtheme_sel_theme" onchange="ajaxGetStyles(this.value);">';
  inner_html += '    <option value="_blank" selected="selected">' + $lang.get('ajax_changestyle_select') + '</option>';
  inner_html +=      ENANO_THEME_LIST;
  inner_html += '  </select>';
  inner_html += '</label></p>';
  var chtheme_mb = new messagebox(MB_OKCANCEL|MB_ICONQUESTION, $lang.get('ajax_changestyle_title'), inner_html);
  chtheme_mb.onbeforeclick['OK'] = ajaxChangeStyleComplete;
}

function ajaxGetStyles(id)
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var thediv = document.getElementById('chtheme_sel_style_parent');
  if ( thediv )
  {
    thediv.parentNode.removeChild(thediv);
  }
  if ( id == '_blank' )
  {
    return null;
  }
  ajaxGet(stdAjaxPrefix + '&_mode=getstyles&id=' + id, function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        // IE doesn't like substr() on ajax.responseText
        var response = String(ajax.responseText + ' ');
        response = response.substr(0, response.length - 1);
        if ( response.substr(0,1) != '[' )
        {
          alert('Invalid or unexpected JSON response from server:\n' + response);
          return null;
        }
        
        // Build a selector and matching label
        var data = parseJSON(response);
        var options = new Array();
        for( var i in data )
        {
          var item = data[i];
          var title = themeid_to_title(item);
          var option = document.createElement('option');
          option.value = item;
          option.appendChild(document.createTextNode(title));
          options.push(option);
        }
        var p_parent = document.createElement('p');
        var label  = document.createElement('label');
        p_parent.id = 'chtheme_sel_style_parent';
        label.appendChild(document.createTextNode($lang.get('ajax_changestyle_lbl_style') + ' '));
        var select = document.createElement('select');
        select.id = 'chtheme_sel_style';
        for ( var i in options )
        {
          select.appendChild(options[i]);
        }
        label.appendChild(select);
        p_parent.appendChild(label);
        
        // Stick it onto the messagebox
        var div = document.getElementById('messageBox');
        var kid = div.firstChild.nextSibling;
        
        kid.appendChild(p_parent);
        
      }
    }, true);
}

function ajaxChangeStyleComplete()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var theme = $dynano('chtheme_sel_theme');
  var style = $dynano('chtheme_sel_style');
  if ( !theme.object || !style.object )
  {
    alert($lang.get('ajax_changestyle_pleaseselect_theme'));
    return true;
  }
  var theme_id = theme.object.value;
  var style_id = style.object.value;
  
  if ( typeof(theme_id) != 'string' || typeof(style_id) != 'string' )
  {
    alert('Couldn\'t get theme or style ID');
    return true;
  }
  
  if ( theme_id.length < 1 || style_id.length < 1 )
  {
    alert('Theme or style ID is zero length');
    return true;
  }
  
  ajaxPost(stdAjaxPrefix + '&_mode=change_theme', 'theme_id=' + ajaxEscape(theme_id) + '&style_id=' + ajaxEscape(style_id), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( ajax.responseText == 'GOOD' )
        {
          var c = confirm($lang.get('ajax_changestyle_success'));
          if ( c )
            window.location.reload();
        }
        else
        {
          alert('Error occurred during attempt to change theme:\n' + ajax.responseText);
        }
      }
    }, true);
  
  return false;
  
}

function themeid_to_title(id)
{
  if ( typeof(id) != 'string' )
    return false;
  id = id.substr(0, 1).toUpperCase() + id.substr(1);
  id = id.replace(/_/g, ' ');
  id = id.replace(/-/g, ' ');
  return id;
}

/*
function ajaxChangeStyle()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  var win = document.getElementById("cn2");
  win.innerHTML = ' \
    <form action="'+ENANO_SPECIAL_CHANGESTYLE+'" onsubmit="jws.closeWin(\'root2\');" method="post" style="text-align: center"> \
    <h3>Select a theme...</h3>\
    <select id="mdgThemeID" name="theme" onchange="ajaxGetStyles(this.value);"> \
    '+ENANO_THEME_LIST+' \
    </select> \
    <div id="styleSelector"></div>\
    <br /><br />\
    <input type="hidden" name="return_to" value="'+title+'" />\
    <input id="styleSubmitter" type="submit" style="display: none; font-weight: bold" value="Change theme" /> \
    <input type="button" value="Cancel" onclick="jws.closeWin(\'root2\');" /> \
    </form> \
  ';
  ajaxGetStyles(ENANO_CURRENT_THEME);
  jws.openWin('root2', 340, 300);
}

function ajaxGetStyles(id) {
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix+'&_mode=getstyles&id='+id, function() {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      eval(ajax.responseText);
      html = '<h3>And a style...</h3><select id="mdgStyleID" name="style">';
      for(i=0;i<list.length;i++) {
        lname = list[i].substr(0, 1).toUpperCase() + list[i].substr(1, list[i].length);
        html = html + '<option value="'+list[i]+'">'+lname+'</option>';
      }
      html = html + '</select>';
      document.getElementById('styleSelector').innerHTML = html;
      document.getElementById('styleSubmitter').style.display = 'inline'; 
    }
  });
}
*/

function ajaxSwapCSS()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  setAjaxLoading();
  if(_css) {
    document.getElementById('mdgCss').href = main_css;
    _css = false;
  } else {
    document.getElementById('mdgCss').href = print_css;
    _css = true;
  }
  unsetAjaxLoading();
  menuOff();
}

function ajaxSetPassword()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  pass = hex_sha1(document.getElementById('mdgPassSetField').value);
  setAjaxLoading();
  ajaxPost(stdAjaxPrefix+'&_mode=setpass', 'password='+pass, function()
    {
      unsetAjaxLoading();
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        alert(ajax.responseText);
      }
    }, true);
}

function ajaxStartLogin()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  ajaxPromptAdminAuth(function(k) {
      window.location.reload();
    }, USER_LEVEL_MEMBER);
}

function ajaxStartAdminLogin()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = USER_LEVEL_ADMIN;
      var loc = makeUrlNS('Special', 'Administration');
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, USER_LEVEL_ADMIN);
    return false;
  }
  var loc = makeUrlNS('Special', 'Administration');
  window.location = loc;
}

function ajaxAdminPage()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = USER_LEVEL_ADMIN;
      var loc = String(window.location + '');
      window.location = append_sid(loc);
      var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'PageManager&source=ajax&page_id=' + ajaxEscape(title));
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, 9);
    return false;
  }
  var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'PageManager&source=ajax&page_id=' + ajaxEscape(title));
  window.location = loc;
}

var navto_ns;
var navto_pg;
var navto_ul;

function ajaxLoginNavTo(namespace, page_id, min_level)
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  navto_pg = page_id;
  navto_ns = namespace;
  navto_ul = min_level;
  if ( auth_level < min_level )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = navto_ul;
      var loc = makeUrlNS(navto_ns, navto_pg);
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, min_level);
    return false;
  }
  var loc = makeUrlNS(navto_ns, navto_pg);
  window.location = loc;
}

function ajaxAdminUser(username)
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = USER_LEVEL_ADMIN;
      var loc = String(window.location + '');
      window.location = append_sid(loc);
      var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'UserManager&src=get&user=' + ajaxEscape(username));
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, 9);
    return false;
  }
  var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'UserManager&src=get&user=' + ajaxEscape(username));
  window.location = loc;
}

function ajaxDisableEmbeddedPHP()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( !confirm($lang.get('ajax_killphp_confirm')) )
    return false;
  var $killdiv = $dynano('php_killer');
  if ( !$killdiv.object )
  {
    alert('Can\'t get kill div object');
    return false;
  }
  $killdiv.object.innerHTML = '<img alt="Loading..." src="' + scriptPath + '/images/loading-big.gif" /><br />Making request...';
  var url = makeUrlNS('Admin', 'Home', 'src=ajax');
  ajaxPost(url, 'act=kill_php', function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( ajax.responseText == '1' )
        {
          var $killdiv = $dynano('php_killer');
          //$killdiv.object.innerHTML = '<img alt="Success" src="' + scriptPath + '/images/error.png" /><br />Embedded PHP in pages has been disabled.';
          $killdiv.object.parentNode.removeChild($killdiv.object);
          var newdiv = document.createElement('div');
          // newdiv.style = $killdiv.object.style;
          newdiv.className = $killdiv.object.className;
          newdiv.innerHTML = '<img alt="Success" src="' + scriptPath + '/images/error.png" /><br />' + $lang.get('ajax_killphp_success');
          $killdiv.object.parentNode.appendChild(newdiv);
          $killdiv.object.parentNode.removeChild($killdiv.object);
        }
        else
        {
          var $killdiv = $dynano('php_killer');
          $killdiv.object.innerHTML = ajax.responseText;
        }
      }
    });
}

var catHTMLBuf = false;

function ajaxCatToTag()
{
  if ( KILL_SWITCH )
    return false;
  setAjaxLoading();
  ajaxGet(stdAjaxPrefix + '&_mode=get_tags', function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        unsetAjaxLoading();
        var resptext = String(ajax.responseText + ' ');
        resptext = resptext.substr(0, resptext.length-1);
        if ( resptext.substr(0, 1) != '{' )
        {
          handle_invalid_json(resptext);
          return false;
        }
        var json = parseJSON(resptext);
        var catbox = document.getElementById('mdgCatBox');
        if ( !catbox )
          return false;
        var linkbox = catbox.parentNode.firstChild.firstChild.nextSibling;
        linkbox.firstChild.nodeValue = $lang.get('catedit_catbox_link_showcategorization');
        linkbox.onclick = function() { ajaxTagToCat(); return false; };
        catHTMLBuf = catbox.innerHTML;
        catbox.innerHTML = '';
        catbox.appendChild(document.createTextNode($lang.get('tags_lbl_page_tags')+' '));
        if ( json.tags.length < 1 )
        {
          catbox.appendChild(document.createTextNode($lang.get('tags_lbl_no_tags')));
        }
        for ( var i = 0; i < json.tags.length; i++ )
        {
          catbox.appendChild(document.createTextNode(json.tags[i].name));
          if ( json.tags[i].can_del )
          {
            catbox.appendChild(document.createTextNode(' '));
            var a = document.createElement('a');
            a.appendChild(document.createTextNode('[X]'));
            a.href = '#';
            a._js_tag_id = json.tags[i].id;
            a.onclick = function() { ajaxDeleteTag(this, this._js_tag_id); return false; }
            catbox.appendChild(a);
          }
          if ( ( i + 1 ) < json.tags.length )
            catbox.appendChild(document.createTextNode(', '));
        }
        if ( json.can_add )
        {
          catbox.appendChild(document.createTextNode(' '));
          var addlink = document.createElement('a');
          addlink.href = '#';
          addlink.onclick = function() { try { ajaxAddTagStage1(); } catch(e) { }; return false; };
          addlink.appendChild(document.createTextNode($lang.get('tags_btn_add_tag')));
          catbox.appendChild(addlink);
        }
      }
    });
}

var addtag_open = false;

function ajaxAddTagStage1()
{
  if ( addtag_open )
    return false;
  var catbox = document.getElementById('mdgCatBox');
  var adddiv = document.createElement('div');
  var text = document.createElement('input');
  var addlink = document.createElement('a');
  addlink.href = '#';
  addlink.onclick = function() { ajaxAddTagStage2(this.parentNode.firstChild.nextSibling.value, this.parentNode); return false; };
  addlink.appendChild(document.createTextNode($lang.get('tags_btn_add')));
  text.type = 'text';
  text.size = '15';
  text.onkeyup = function(e)
  {
    if ( e.keyCode == 13 )
    {
      ajaxAddTagStage2(this.value, this.parentNode);
    }
  }
  
  adddiv.style.margin = '5px 0 0 0';
  adddiv.appendChild(document.createTextNode($lang.get('tags_lbl_add_tag')+' '));
  adddiv.appendChild(text);
  adddiv.appendChild(document.createTextNode(' '));
  adddiv.appendChild(addlink);
  catbox.appendChild(adddiv);
  addtag_open = true;
}

var addtag_nukeme = false;

function ajaxAddTagStage2(tag, nukeme)
{
  if ( !addtag_open )
    return false;
  if ( addtag_nukeme )
    return false;
  addtag_nukeme = nukeme;
  tag = ajaxEscape(tag);
  setAjaxLoading();
  ajaxPost(stdAjaxPrefix + '&_mode=addtag', 'tag=' + tag, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        unsetAjaxLoading();
        var nukeme = addtag_nukeme;
        addtag_nukeme = false;
        var resptext = String(ajax.responseText + ' ');
        resptext = resptext.substr(0, resptext.length-1);
        if ( resptext.substr(0, 1) != '{' )
        {
          handle_invalid_json(resptext);
          return false;
        }
        var json = parseJSON(resptext);
        var parent = nukeme.parentNode;
        parent.removeChild(nukeme);
        addtag_open = false;
        if ( json.success )
        {
          var node = parent.childNodes[1];
          var insertafter = false;
          var nukeafter = false;
          if ( node.nodeValue == $lang.get('tags_lbl_no_tags') )
          {
            nukeafter = true;
          }
          insertafter = parent.childNodes[ parent.childNodes.length - 3 ];
          // these need to be inserted in reverse order
          if ( json.can_del )
          {
            var a = document.createElement('a');
            a.appendChild(document.createTextNode('[X]'));
            a.href = '#';
            a._js_tag_id = json.tag_id;
            a.onclick = function() { ajaxDeleteTag(this, this._js_tag_id); return false; }
            insertAfter(parent, a, insertafter);
            insertAfter(parent, document.createTextNode(' '), insertafter);
          }
          insertAfter(parent, document.createTextNode(json.tag), insertafter);
          if ( !nukeafter )
          {
            insertAfter(parent, document.createTextNode(', '), insertafter);
          }
          if ( nukeafter )
          {
            parent.removeChild(insertafter);
          }
        }
        else
        {
          alert(json.error);
        }
      }
    });
}

function ajaxDeleteTag(parentobj, tag_id)
{
  var arrDelete = [ parentobj, parentobj.previousSibling, parentobj.previousSibling.previousSibling ];
  var parent = parentobj.parentNode;
  var writeNoTags = false;
  if ( parentobj.previousSibling.previousSibling.previousSibling.nodeValue == ', ' )
    arrDelete.push(parentobj.previousSibling.previousSibling.previousSibling);
  else if ( parentobj.previousSibling.previousSibling.previousSibling.nodeValue == $lang.get('tags_lbl_page_tags') + ' ' )
    arrDelete.push(parentobj.nextSibling);
  
  if ( parentobj.previousSibling.previousSibling.previousSibling.nodeValue == $lang.get('tags_lbl_page_tags') + ' ' &&
       parentobj.nextSibling.nextSibling.firstChild )
    if ( parentobj.nextSibling.nextSibling.firstChild.nodeValue == $lang.get('tags_btn_add_tag'))
      writeNoTags = true;
    
  ajaxPost(stdAjaxPrefix + '&_mode=deltag', 'tag_id=' + String(tag_id), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( ajax.responseText == 'success' )
        {
          for ( var i = 0; i < arrDelete.length; i++ )
          {
            try
            {
              parent.removeChild(arrDelete[i]);
            } catch(e) {}
          }
          if ( writeNoTags )
          {
            var node1 = document.createTextNode($lang.get('tags_lbl_no_tags'));
            var node2 = document.createTextNode(' ');
            insertAfter(parent, node1, parent.firstChild);
            insertAfter(parent, node2, node1);
          }
        }
        else
        {
          alert(ajax.responseText);
        }
      }
    });
}

function ajaxTagToCat()
{
  if ( !catHTMLBuf )
    return false;
  var catbox = document.getElementById('mdgCatBox');
  if ( !catbox )
    return false;
  addtag_open = false;
  var linkbox = catbox.parentNode.firstChild.firstChild.nextSibling;
  linkbox.firstChild.nodeValue = $lang.get('tags_catbox_link');
  linkbox.onclick = function() { ajaxCatToTag(); return false; };
  catbox.innerHTML = catHTMLBuf;
  catHTMLBuf = false;
}

var keepalive_interval = false;

function ajaxPingServer()
{
  ajaxGet(stdAjaxPrefix + '&_mode=ping', function()
    {
    });
}

function ajaxToggleKeepalive()
{
  if ( readCookie('admin_keepalive') == '1' )
  {
    createCookie('admin_keepalive', '0', 3650);
    if ( keepalive_interval )
      clearInterval(keepalive_interval);
    var span = document.getElementById('keepalivestat');
    span.firstChild.nodeValue = $lang.get('adm_btn_keepalive_off');
  }
  else
  {
    createCookie('admin_keepalive', '1', 3650);
    if ( !keepalive_interval )
      keepalive_interval = setInterval('ajaxPingServer();', 600000);
    var span = document.getElementById('keepalivestat');
    span.firstChild.nodeValue = $lang.get('adm_btn_keepalive_on');
    ajaxPingServer();
  }
}

var keepalive_onload = function()
{
  if ( readCookie('admin_keepalive') == '1' )
  {
    if ( !keepalive_interval )
      keepalive_interval = setInterval('ajaxPingServer();', 600000);
    var span = document.getElementById('keepalivestat');
    span.firstChild.nodeValue = $lang.get('adm_btn_keepalive_on');
  }
  else
  {
    if ( keepalive_interval )
      clearInterval(keepalive_interval);
    var span = document.getElementById('keepalivestat');
    span.firstChild.nodeValue = $lang.get('adm_btn_keepalive_off');
  }
};

function aboutKeepAlive()
{
  new messagebox(MB_OK|MB_ICONINFORMATION, $lang.get('user_keepalive_info_title'), $lang.get('user_keepalive_info_body'));
}

function ajaxShowCaptcha(code)
{
  var mydiv = document.createElement('div');
  mydiv.style.backgroundColor = '#FFFFFF';
  mydiv.style.padding = '10px';
  mydiv.style.position = 'absolute';
  mydiv.style.top = '0px';
  mydiv.id = 'autoCaptcha';
  mydiv.style.zIndex = String( getHighestZ() + 1 );
  var img = document.createElement('img');
  img.onload = function()
  {
    if ( this.loaded )
      return true;
    var mydiv = document.getElementById('autoCaptcha');
    var width = getWidth();
    var divw = $dynano(mydiv).Width();
    var left = ( width / 2 ) - ( divw / 2 );
    mydiv.style.left = left + 'px';
    fly_in_top(mydiv, false, true);
    this.loaded = true;
  };
  img.src = makeUrlNS('Special', 'Captcha/' + code);
  img.onclick = function() { this.src = this.src + '/a'; };
  img.style.cursor = 'pointer';
  mydiv.appendChild(img);
  domObjChangeOpac(0, mydiv);
  var body = document.getElementsByTagName('body')[0];
  body.appendChild(mydiv);
}

function ajaxUpdateCheck(targetelement)
{
  if ( !document.getElementById(targetelement) )
  {
    return false;
  }
  var target = document.getElementById(targetelement);
  target.innerHTML = '';
  var img = document.createElement('img');
  img.src = scriptPath + '/images/loading.gif';
  img.alt = 'Loading...';
  target.appendChild(img);
  ajaxGet(makeUrlNS('Admin', 'Home/updates.xml'), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var releases = new Array();
        var update_available = false;
        if ( ajax.responseXML == null )
        {
          alert("Error fetching updates list:\n" + ajax.responseText);
          return false;
        }
        if ( ajax.responseXML.firstChild.tagName == 'enano' )
        {
          var enanotag = ajax.responseXML.firstChild;
          for ( var i = 0; i < enanotag.childNodes.length; i++ )
          {
            if ( enanotag.childNodes[i].tagName == 'error' )
            {
              alert(enanotag.childNodes[i].firstChild.nodeValue);
            }
            else if ( enanotag.childNodes[i].tagName == 'latest' )
            {
              // got <latest>
              var latesttag = enanotag.childNodes[i];
              for ( var j = 0; j < latesttag.childNodes.length; j++ )
              {
                var node = latesttag.childNodes[j];
                if ( node.tagName == 'release' )
                {
                  var releasedata = new Object();
                  for ( var k = 0; k < node.attributes.length; k++ )
                  {
                    releasedata[node.attributes[k].nodeName] = node.attributes[k].nodeValue;
                  }
                  releases.push(releasedata);
                }
                else if ( node.tagName == 'haveupdates' )
                {
                  update_available = true;
                }
              }
              break;
            }
          }
        }
        else
        {
          return false;
        }
        var thediv = document.getElementById(targetelement);
        thediv.innerHTML = '';
        if ( !thediv )
        {
          return false;
        }
        if ( releases.length > 0 )
        {
          thediv.className = 'tblholder';
          if ( update_available )
          {
            var infobox = document.createElement('div');
            infobox.className = 'info-box-mini';
            infobox.appendChild(document.createTextNode('An update for Enano is available. The newest release is highlighted below.'));
            infobox.style.borderWidth = '0';
            infobox.style.margin = '0 0 0 0';
            thediv.appendChild(infobox);
          }
          else
          {
            var infobox = document.createElement('div');
            infobox.className = 'info-box-mini';
            infobox.appendChild(document.createTextNode('No new updates are available. The latest available releases are shown below.'));
            infobox.style.borderWidth = '0';
            infobox.style.margin = '0 0 0 0';
            thediv.appendChild(infobox);
          }
          var table = document.createElement('table');
          table.setAttribute('border', '0');
          table.setAttribute('cellspacing', '1');
          table.setAttribute('cellpadding', '4');
          
          var tr = document.createElement('tr');
          
          var td1 = document.createElement('th');
          var td2 = document.createElement('th');
          var td3 = document.createElement('th');
          var td4 = document.createElement('th');
          
          td1.appendChild( document.createTextNode('Release type') );
          td2.appendChild( document.createTextNode('Version') );
          td3.appendChild( document.createTextNode('Code name') );
          td4.appendChild( document.createTextNode('Release notes') );
          
          tr.appendChild(td1);
          tr.appendChild(td2);
          tr.appendChild(td3);
          tr.appendChild(td4);
            
          table.appendChild(tr);
          
          var cls = 'row2';
          
          var j = 0;
          for ( var i in releases )
          {
            j++;
            if ( j > 5 )
              break;
            if ( update_available && j == 1 )
              cls = 'row1_green';
            else
              cls = ( cls == 'row1' ) ? 'row2' : 'row1';
            var release = releases[i];
            var tr = document.createElement('tr');
            
            var td1 = document.createElement('td');
            var td2 = document.createElement('td');
            var td3 = document.createElement('td');
            var td4 = document.createElement('td');
            
            td1.className = cls;
            td2.className = cls;
            td3.className = cls;
            td4.className = cls;
            
            if ( release.tag )
              td1.appendChild( document.createTextNode(release.tag) );
            
            if ( release.version )
              td2.appendChild( document.createTextNode(release.version) );
            
            if ( release.codename )
              td3.appendChild( document.createTextNode(release.codename) );
            
            if ( release.relnotes )
            {
              var a = document.createElement('a');
              a.href = release.relnotes;
              a.appendChild(document.createTextNode('View'));
              td4.appendChild( a );
            }
            
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            tr.appendChild(td4);
            
            table.appendChild(tr);
          }
          thediv.appendChild(table);
        }
        else
        {
          thediv.appendChild(document.createTextNode('No releases available.'));
        }
      }
    });
}


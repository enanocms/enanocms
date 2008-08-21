function ajaxToggleSystemThemes()
{
  var theme_list = document.getElementById('theme_list_edit');
  var mode = ( theme_list.sys_shown ) ? 'hide' : 'show';
  for ( var i = 0; i < theme_list.childNodes.length; i++ )
  {
    var child = theme_list.childNodes[i];
    if ( child.tagName == 'DIV' )
    {
      if ( $dynano(child).hasClass('themebutton_theme_system') )
      {
        if ( $dynano(child).hasClass('themebutton_theme_disabled') )
        {
          $dynano(child).rmClass('themebutton_theme_disabled')
        }
        if ( mode == 'show' )
        {
          domObjChangeOpac(0, child);
          child.style.display = 'block';
          domOpacity(child, 0, 100, 1000);
        }
        else
        {
          domOpacity(child, 100, 0, 1000);
          setTimeout("document.getElementById('" + child.id + "').style.display = 'none';", 1050);
        }
      }
    }
  }
  theme_list.sys_shown = ( mode == 'show' );
  document.getElementById('systheme_toggler').innerHTML = ( mode == 'hide' ) ? $lang.get('acptm_btn_system_themes_show') : $lang.get('acptm_btn_system_themes_hide');
}

function ajaxInstallTheme(theme_id)
{
  var thediv = document.getElementById('themebtn_install_' + theme_id);
  if ( !thediv )
    return false;
  thediv.removeChild(thediv.getElementsByTagName('a')[0]);
  var status = document.createElement('div');
  status.className = 'status';
  thediv.appendChild(status);
  
  var req = toJSONString({
      mode: 'install',
      theme_id: theme_id
    });
  // we've finished nukeing the existing interface, request editor data
  ajaxPost(makeUrlNS('Admin', 'ThemeManager/action.json'), 'r=' + ajaxEscape(req), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( response.substr(0, 1) == '{' )
        {
          response = parseJSON(response);
          if ( response.mode == 'error' )
          {
            alert(response.error);
            return false;
          }
        }
        
        var theme_list = document.getElementById('theme_list_edit');
  
        var btn = document.createElement('div');
        btn.className = 'themebutton';
        btn.style.backgroundImage = thediv.style.backgroundImage;
        btn.id = 'themebtn_edit_' + theme_id;
        
        var a = document.createElement('a');
        a.className = 'tb-inner';
        a.appendChild(document.createTextNode($lang.get('acptm_btn_theme_edit')));
        a.appendChild(document.createTextNode("\n"));
        a.theme_id = theme_id;
        a.onclick = function()
        {
          ajaxEditTheme(this.theme_id);
          return false;
        }
        a.href = '#';
        var span = document.createElement('span');
        span.className = 'themename';
        span.appendChild(document.createTextNode(thediv.getAttribute('enano:themename')));
        a.appendChild(span);
        btn.appendChild(a);
        btn.setAttribute('enano:themename', thediv.getAttribute('enano:themename'));
        theme_list.appendChild(btn);
        
        thediv.parentNode.removeChild(thediv);
      }
    });
}

function ajaxEditTheme(theme_id)
{
  // Fade out and subsequently destroy the entire list, then make an
  // ajax request to the theme manager for the theme info via JSON
  var theme_list = document.getElementById('theme_list_edit').parentNode;
  var backgroundImage = document.getElementById('themebtn_edit_' + theme_id).style.backgroundImage;
  /*
  for ( var i = 0; i < theme_list.childNodes.length; i++ )
  {
    var el = theme_list.childNodes[i];
    if ( el.tagName )
      domOpacity(el, 100, 0, 1000);
  }
  */
  var thediv = document.getElementById('themebtn_edit_' + theme_id);
  if ( !thediv )
    return false;
  thediv.removeChild(thediv.getElementsByTagName('a')[0]);
  var status = document.createElement('div');
  status.className = 'status';
  thediv.appendChild(status);
  
  setTimeout(function()
    {
      var req = toJSONString({
          mode: 'fetch_theme',
          theme_id: theme_id
        });
      // we've finished nukeing the existing interface, request editor data
      ajaxPost(makeUrlNS('Admin', 'ThemeManager/action.json'), 'r=' + ajaxEscape(req), function()
        {
          if ( ajax.readyState == 4 && ajax.status == 200 )
          {
            theme_list.innerHTML = '';
            var response = String(ajax.responseText + '');
            if ( !check_json_response(response) )
            {
              alert(response);
              return false;
            }
            response = parseJSON(response);
            if ( response.mode == 'error' )
            {
              alert(response.error);
              return false;
            }
            response.background_image = backgroundImage;
            ajaxBuildThemeEditor(response, theme_list);
          }
        });
    }, 200);
}

function ajaxBuildThemeEditor(data, target)
{
  // Build the theme editor interface
  // Init opacity
  domObjChangeOpac(0, target);
  
  // Theme preview
  var preview = document.createElement('div');
  preview.style.border = '1px solid #F0F0F0';
  preview.style.padding = '5px';
  preview.style.width = '216px';
  preview.style.height = '150px';
  preview.style.backgroundImage = data.background_image;
  preview.style.backgroundRepeat = 'no-repeat';
  preview.style.backgroundPosition = 'center center';
  preview.style.cssFloat = 'right';
  preview.style.styleFloat = 'right';
  
  target.appendChild(preview);
  
  // Heading
  var h3 = document.createElement('h3');
  h3.appendChild(document.createTextNode($lang.get('acptm_heading_theme_edit', { theme_name: data.theme_name })));
  target.appendChild(h3);
  
  // Field: Theme name
  var l_name = document.createElement('label');
  l_name.appendChild(document.createTextNode($lang.get('acptm_field_theme_name') + ' '));
  var f_name = document.createElement('input');
  f_name.type = 'text';
  f_name.id = 'themeed_field_name';
  f_name.value = data.theme_name;
  f_name.size = '40';
  l_name.appendChild(f_name);
  target.appendChild(l_name);
  
  target.appendChild(document.createElement('br'));
  target.appendChild(document.createElement('br'));
  
  // Field: default style
  var l_style = document.createElement('label');
  l_style.appendChild(document.createTextNode($lang.get('acptm_field_default_style') + ' '));
  var f_style = document.createElement('select');
  f_style.id = 'themeed_field_style';
  var opts = [];
  for ( var i = 0; i < data.css.length; i++ )
  {
    if ( data.css[i] == '_printable' )
      continue;
    
    opts[i] = document.createElement('option');
    opts[i].value = data.css[i];
    opts[i].appendChild(document.createTextNode(data.css[i]));
    if ( data.default_style == data.css[i] )
    {
      opts[i].selected = true;
    }
    f_style.appendChild(opts[i]);
  }
  l_style.appendChild(f_style);
  target.appendChild(l_style);
  
  target.appendChild(document.createElement('br'));
  target.appendChild(document.createElement('br'));
  
  // Default theme
  target.appendChild(document.createTextNode($lang.get('acptm_field_default_theme') + ' '));
  if ( data.is_default )
  {
    var l_default = document.createElement('b');
    l_default.appendChild(document.createTextNode($lang.get('acptm_field_default_msg_current')));
  }
  else
  {
    var l_default = document.createElement('label');
    var f_default = document.createElement('input');
    f_default.type = 'checkbox';
    f_default.id = 'themeed_field_default';
    l_default.appendChild(f_default);
    l_default.appendChild(document.createTextNode($lang.get('acptm_field_default_btn_make_default')));
  }
  target.appendChild(l_default);
  
  target.appendChild(document.createElement('br'));
  target.appendChild(document.createElement('br'));
  
  // Disable theme
  var disable_span = document.createElement('span');
  disable_span.appendChild(document.createTextNode($lang.get('acptm_field_disable_title') + ' '));
  target.appendChild(disable_span);
  var l_disable = document.createElement('label');
  var f_disable = document.createElement('input');
  f_disable.type = 'checkbox';
  f_disable.id = 'themeed_field_disable';
  if ( !data.enabled )
    f_disable.setAttribute('checked', 'checked');
  l_disable.style.fontWeight = 'bold';
  l_disable.appendChild(f_disable);
  l_disable.appendChild(document.createTextNode($lang.get('acptm_field_disable')));
  target.appendChild(l_disable);
  
  // Availability policy
  var h3 = document.createElement('h3');
  h3.appendChild(document.createTextNode($lang.get('acptm_heading_theme_groups')));
  target.appendChild(h3);
  
  // Label for the whole field
  var p_d_policy = document.createElement('p');
  p_d_policy.style.fontWeight = 'bold';
  p_d_policy.appendChild(document.createTextNode($lang.get('acptm_field_policy')));
  target.appendChild(p_d_policy);
  
  // Wrapper for options
  var p_f_policy = document.createElement('p');
  
  // Option: allow all
  var l_policy_allow_all = document.createElement('label');
  var f_policy_allow_all = document.createElement('input');
  f_policy_allow_all.type = 'radio';
  f_policy_allow_all.id = 'themeed_field_policy_allow_all';
  f_policy_allow_all.name = 'themeed_field_policy';
  f_policy_allow_all.value = 'allow_all';
  l_policy_allow_all.appendChild(f_policy_allow_all);
  l_policy_allow_all.appendChild(document.createTextNode(' ' + $lang.get('acptm_field_policy_allow_all')));
  if ( data.group_policy == 'allow_all' )
  {
    f_policy_allow_all.setAttribute('checked', 'checked');
  }
  
  // Option: whitelist
  var l_policy_whitelist = document.createElement('label');
  var f_policy_whitelist = document.createElement('input');
  f_policy_whitelist.type = 'radio';
  f_policy_whitelist.id = 'themeed_field_policy_whitelist';
  f_policy_whitelist.name = 'themeed_field_policy';
  f_policy_whitelist.value = 'whitelist';
  l_policy_whitelist.appendChild(f_policy_whitelist);
  l_policy_whitelist.appendChild(document.createTextNode(' ' + $lang.get('acptm_field_policy_whitelist')));
  if ( data.group_policy == 'whitelist' )
  {
    f_policy_whitelist.setAttribute('checked', 'checked');
  }
  
  // Option: blacklist
  var l_policy_blacklist = document.createElement('label');
  var f_policy_blacklist = document.createElement('input');
  f_policy_blacklist.type = 'radio';
  f_policy_blacklist.id = 'themeed_field_policy_blacklist';
  f_policy_blacklist.name = 'themeed_field_policy';
  f_policy_blacklist.value = 'blacklist';
  l_policy_blacklist.appendChild(f_policy_blacklist);
  l_policy_blacklist.appendChild(document.createTextNode(' ' + $lang.get('acptm_field_policy_blacklist')));
  if ( data.group_policy == 'blacklist' )
  {
    f_policy_blacklist.setAttribute('checked', 'checked');
  }
  f_policy_allow_all.onclick = ajaxThemeManagerHandlePolicyClick;
  f_policy_whitelist.onclick = ajaxThemeManagerHandlePolicyClick;
  f_policy_blacklist.onclick = ajaxThemeManagerHandlePolicyClick;
  
  p_f_policy.appendChild(l_policy_allow_all);
  p_f_policy.appendChild(document.createElement('br'));
  p_f_policy.appendChild(l_policy_whitelist);
  p_f_policy.appendChild(document.createElement('br'));
  p_f_policy.appendChild(l_policy_blacklist);
  
  target.appendChild(p_d_policy);
  target.appendChild(p_f_policy);
  
  var div_acl = document.createElement('div');
  div_acl.id = 'themeed_acl_box';
  div_acl.style.margin = '0 0 10px 30px';
  
  var h3_g = document.createElement('h3');
  h3_g.appendChild(document.createTextNode($lang.get('acptm_field_acl_heading_groups')));
  div_acl.appendChild(h3_g);
  
  var div_groups = document.createElement('div');
  div_groups.style.border = '1px solid #E8E8E8';
  div_groups.id = 'themeed_group_list';
  
  // Group list
  for ( var i in data.group_names )
  {
    var g_name = data.group_names[i];
    var check = document.createElement('input');
    check.type = 'checkbox';
    if ( in_array("g:" + i, data.group_list) )
    {
      check.setAttribute('checked', 'checked');
    }
    check.group_id = parseInt(i);
    var lbl_g_acl = document.createElement('label');
    lbl_g_acl.appendChild(check);
    var str = 'groupcp_grp_' + g_name.toLowerCase();
    var g_name_l10n = ( $lang.get(str) != str ) ? $lang.get(str) : g_name;
    lbl_g_acl.appendChild(document.createTextNode(g_name_l10n));
    div_groups.appendChild(lbl_g_acl);
    div_groups.appendChild(document.createElement('br'));
  }
  div_acl.appendChild(div_groups);
  
  var h3_u = document.createElement('h3');
  h3_u.appendChild(document.createTextNode($lang.get('acptm_field_acl_heading_users')));
  div_acl.appendChild(h3_u);
  
  // User addition field
  var frm = document.createElement('form');
  frm.action = 'javascript:ajaxThemeManagerHandleUserAdd();';
  frm.appendChild(document.createTextNode($lang.get('acptm_field_acl_add_user')));
  var f_useradd = document.createElement('input');
  f_useradd.type = 'text';
  f_useradd.id = 'themeed_field_adduser';
  f_useradd.onkeyup = function(e)
  {
    new AutofillUsername(this, e, false);
  }
  
  frm.appendChild(f_useradd);
  div_acl.appendChild(frm);
  
  div_acl.appendChild(document.createElement('br'));
  
  // User list
  var div_users = document.createElement('div');
  div_users.style.border = '1px solid #E8E8E8';
  div_users.style.padding = '4px';
  div_users.id = 'themeed_user_list';
  for ( var i = 0; i < data.group_list.length; i++ )
  {
    var id = data.group_list[i];
    if ( id.substr(0, 2) != 'u:' )
      continue;
    var uid = id.substr(2);
    var username = data.usernames[uid];
    
    var useritem = document.createElement('span');
    useritem.appendChild(document.createTextNode(username + ' '));
    useritem.userid = parseInt(uid);
    var deleter = document.createElement('a');
    deleter.href = '#';
    deleter.onclick = function()
    {
      ajaxThemeManagerHandleUserRemoval(this);
      return false;
    }
    deleter.appendChild(document.createTextNode('[X]'));
    useritem.appendChild(deleter);
    div_users.appendChild(useritem);
    div_users.appendChild(document.createElement('br'));
  }
  div_acl.appendChild(div_users);
  
  target.appendChild(div_acl);
  
  ajaxThemeManagerHandlePolicyClick();
  
  var clearer = document.createElement('span');
  clearer.className = 'menuclear';
  target.appendChild(clearer);
  
  // Theme ID
  var tid = document.createElement('input');
  tid.type = 'hidden';
  tid.id = 'themeed_theme_id';
  tid.value = data.theme_id;
  target.appendChild(tid);
  
  // Save button
  var raquo = unescape('%BB');
  var savebtn = document.createElement('input');
  savebtn.type = 'button';
  savebtn.style.fontWeight = 'bold';
  savebtn.value = $lang.get('etc_save_changes') + ' ' + raquo;
  savebtn.onclick = function()
  {
    ajaxThemeManagerHandleSaveRequest();
  }
  target.appendChild(savebtn);
  
  target.appendChild(document.createTextNode(' '));
  
  // Cancel button
  var savebtn = document.createElement('input');
  savebtn.type = 'button';
  savebtn.value = $lang.get('etc_cancel');
  savebtn.onclick = function()
  {
    ajaxPage(namespace_list['Admin'] + 'ThemeManager');
  }
  target.appendChild(savebtn);
  
  target.appendChild(document.createTextNode(' '));
  
  // Uninstall button
  var savebtn = document.createElement('input');
  savebtn.type = 'button';
  savebtn.value = $lang.get('acptm_btn_uninstall_theme');
  savebtn.style.color = '#D84308';
  savebtn.onclick = function()
  {
    if ( !confirm($lang.get('acptm_msg_uninstall_confirm')) )
      return false;
    ajaxThemeManagerHandleUninstallClick();
  }
  target.appendChild(savebtn);
  
  // Fade it all in
  domOpacity(target, 0, 100, 500);
  f_name.focus();
}

function ajaxThemeManagerHandlePolicyClick()
{
  if ( document.getElementById('themeed_field_policy_allow_all').checked )
  {
    document.getElementById('themeed_acl_box').style.display = 'none';
  }
  else if ( document.getElementById('themeed_field_policy_whitelist').checked || document.getElementById('themeed_field_policy_blacklist').checked )
  {
    document.getElementById('themeed_acl_box').style.display = 'block';
  }
}

function ajaxThemeManagerHandleUserAdd()
{
  var f_useradd = document.getElementById('themeed_field_adduser');
  f_useradd.setAttribute('disabled', 'disabled');
  var parent = f_useradd.parentNode;
  var img = document.createElement('img');
  img.src = ajax_load_icon;
  img.id = 'themeed_useradd_status';
  img.style.marginLeft = '10px';
  insertAfter(parent, img, f_useradd);
  
  var req = toJSONString({
      mode: 'uid_lookup',
      username: f_useradd.value
    });
  ajaxPost(makeUrlNS('Admin', 'ThemeManager/action.json'), 'r=' + ajaxEscape(req), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var img = document.getElementById('themeed_useradd_status');
        var f_useradd = document.getElementById('themeed_field_adduser');
        
        f_useradd.disabled = null;
        img.parentNode.removeChild(img);
        
        // process response
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          alert(response);
          return false;
        }
        response = parseJSON(response);
        if ( response.mode == 'error' )
        {
          alert(response.error);
          return false;
        }
            
        var uid = parseInt(response.uid);
        var username = response.username;
        
        // Loop through the list of users and remove any existing ones with the same uid
        var div_users = document.getElementById('themeed_user_list');
        var children = div_users.getElementsByTagName('span');
        for ( var i = 0; i < children.length; i++ )
        {
          var child = children[i];
          if ( child.userid == uid )
          {
            // the sister is the br element next to the span with the checkbox/text
            var sister = child.nextSibling;
            div_users.removeChild(child);
            div_users.removeChild(sister);
            break;
          }
        }
        
        var useritem = document.createElement('span');
        useritem.appendChild(document.createTextNode(username + ' '));
        useritem.userid = parseInt(uid);
        var deleter = document.createElement('a');
        deleter.href = '#';
        deleter.onclick = function()
        {
          ajaxThemeManagerHandleUserRemoval(this);
          return false;
        }
        deleter.appendChild(document.createTextNode('[X]'));
        useritem.appendChild(deleter);
        div_users.appendChild(useritem);
        div_users.appendChild(document.createElement('br'));
      }
    });
}

function ajaxThemeManagerHandleUserRemoval(el)
{
  var parent = el.parentNode;
  var uid = parent.userid;
  
  var grandparent = parent.parentNode;
  var sister = parent.nextSibling;
  grandparent.removeChild(parent);
  grandparent.removeChild(sister);
}

function ajaxThemeManagerHandleSaveRequest()
{
  // Build a JSON condensed request
  var md = false;
  if ( document.getElementById('themeed_field_default') )
  {
    if ( document.getElementById('themeed_field_default').checked )
    {
      md = true;
    }
  }
  var policy = 'allow_all';
  if ( document.getElementById('themeed_field_policy_whitelist').checked )
    policy = 'whitelist';
  else if ( document.getElementById('themeed_field_policy_blacklist').checked )
    policy = 'blacklist';
  var json_packet = {
    theme_id: document.getElementById('themeed_theme_id').value,
    theme_name: document.getElementById('themeed_field_name').value,
    default_style: document.getElementById('themeed_field_style').value,
    make_default: md,
    group_policy: policy,
    enabled: ( document.getElementById('themeed_field_disable').checked ? false : true )
  };
  var acl_list = [];
  var checks = document.getElementById('themeed_group_list').getElementsByTagName('input');
  for ( var i = 0; i < checks.length; i++ )
  {
    if ( checks[i].checked )
      acl_list.push('g:' + checks[i].group_id);
  }
  var spans = document.getElementById('themeed_user_list').getElementsByTagName('span');
  for ( var i = 0; i < spans.length; i++ )
  {
    if ( spans[i].userid )
      acl_list.push('u:' + spans[i].userid);
  }
  json_packet.group_list = acl_list;
  
  var json_send = {
    mode: 'save_theme',
    theme_data: json_packet
  };
  
  json_send = ajaxEscape(toJSONString(json_send));
  
  // Request the save
  var parent = document.getElementById('ajaxPageContainer');
  ajaxPost(makeUrlNS('Admin', 'ThemeManager/action.json'), 'r=' + json_send, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        // process response
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          // For this we actually *expect* an HTML response.
          parent.innerHTML = response;
          return false;
        }
        response = parseJSON(response);
        if ( response.mode == 'error' )
        {
          alert(response.error);
          return false;
        }
      }
    });
}

function ajaxThemeManagerHandleUninstallClick()
{
  var theme_id = document.getElementById('themeed_theme_id').value;
  var json_send = {
    mode: 'uninstall',
    theme_id: theme_id
  };
  
  json_send = ajaxEscape(toJSONString(json_send));
  
  // Request the action
  var parent = document.getElementById('ajaxPageContainer');
  ajaxPost(makeUrlNS('Admin', 'ThemeManager/action.json'), 'r=' + json_send, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        // process response
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          // For this we actually *expect* an HTML response.
          parent.innerHTML = response;
          return false;
        }
        response = parseJSON(response);
        if ( response.mode == 'error' )
        {
          alert(response.error);
          return false;
        }
      }
    });
}

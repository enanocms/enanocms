// Javascript routines for the ACL editor

var aclManagerID = 'enano_aclmanager_' + Math.floor(Math.random() * 1000000);
var aclPermList = false;
var aclDataCache = false;

function ajaxOpenACLManager(page_id, namespace)
{
  if(IE)
    return true;
  
  load_component('l10n');
  load_component('messagebox');
  load_component('fadefilter');
  load_component('template-compiler');
  load_component('autofill');
  
  if(!page_id || !namespace)
  {
    var data = strToPageID(title);
    var page_id = data[0];
    var namespace = data[1];
  }
  var params = {
      'mode' : 'listgroups',
      'page_id' : page_id,
      'namespace' : namespace
    };
  params = toJSONString(params);
  params = ajaxEscape(params);
  ajaxPost(stdAjaxPrefix+'&_mode=acljson', 'acl_params='+params, function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        try {
          var groups = parseJSON(ajax.responseText);
        } catch(e) {
          handle_invalid_json(ajax.responseText);
        }
        __aclBuildWizardWindow();
        if ( groups.mode == 'error' )
        {
          alert(groups.error);
          killACLManager();
          return false;
        }
        aclDataCache = groups;
        __aclBuildSelector(groups);
      }
    }, true);
  return false;
}

function ajaxOpenDirectACLRule(rule_id)
{
  load_component('l10n');
  load_component('messagebox');
  load_component('fadefilter');
  load_component('template-compiler');
  load_component('autofill');
  
  var params = {
    target_id: rule_id,
    mode: 'seltarget_id'
  };
  params = ajaxEscape(toJSONString(params));
  ajaxPost(stdAjaxPrefix+'&_mode=acljson', 'acl_params='+params, function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        try
        {
          response = parseJSON(response);
        }
        catch(e)
        {
          handle_invalid_json(response);
        }
        if ( !document.getElementById(aclManagerID) )
        {
          __aclBuildWizardWindow();
          var main = document.getElementById(aclManagerID + '_main');
          main.style.padding = '10px';
        }
        else
        {
          var main = document.getElementById(aclManagerID + '_main');
          main.style.backgroundImage = 'none';
        }
        if ( response.mode == 'error' )
        {
          alert(response.error);
          killACLManager();
          return false;
        }
        aclDataCache = response;
        aclBuildRuleEditor(response, true);
      }
    }, true);
}

function ajaxACLSwitchToSelector()
{
  params = {
      'mode' : 'listgroups'
    };
  if ( aclDataCache.page_id && aclDataCache.namespace )
  {
    params.page_id   = aclDataCache.page_id;
    params.namespace = aclDataCache.namespace;
  }
  params = toJSONString(params);
  params = ajaxEscape(params);
  ajaxPost(stdAjaxPrefix+'&_mode=acljson', 'acl_params='+params, function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        document.getElementById(aclManagerID+'_main').innerHTML = '';
        document.getElementById(aclManagerID + '_back').style.display = 'none';
        document.getElementById(aclManagerID + '_next').value = $lang.get('etc_wizard_next');
        groups = parseJSON(ajax.responseText);
        if ( groups.mode == 'error' )
        {
          alert(groups.error);
          killACLManager();
          return false;
        }
        aclDataCache = groups;
        thispage = strToPageID(title);
        groups.page_id = thispage[0];
        groups.namespace = thispage[1];
        __aclBuildSelector(groups);
      }
    }, true);
}

function __aclBuildSelector(groups)
{
  thispage = strToPageID(title);
  do_scopesel = ( thispage[0] == groups.page_id && thispage[1] == groups.namespace );
  
  document.getElementById(aclManagerID + '_next').style.display = 'inline';
  
  seed = Math.floor(Math.random() * 1000000);
        
  main = document.getElementById(aclManagerID + '_main');
  main.style.padding = '10px';
  main.style.backgroundImage = 'none';
  
  // the "edit existing" button
  var editbtn_wrapper = document.createElement('div');
  editbtn_wrapper.style.styleFloat = 'right';
  editbtn_wrapper.style.cssFloat = 'right';
  editbtn_wrapper.style.fontSize = 'smaller';
  var editbtn = document.createElement('a');
  editbtn.href = '#';
  editbtn.innerHTML = $lang.get('acl_btn_show_existing');
  editbtn_wrapper.appendChild(editbtn);
  main.appendChild(editbtn_wrapper);
  
  editbtn.onclick = function()
  {
    aclSetViewListExisting();
    return false;
  }
  
  selector = document.createElement('div');
  
  grpsel = __aclBuildGroupsHTML(groups);
  grpsel.name = 'group_id';
  
  span = document.createElement('div');
  span.id = "enACL_grpbox_"+seed+"";
  
  // Build the selector
  grpb = document.createElement('input');
  grpb.type = 'radio';
  grpb.name  = 'target_type';
  grpb.value = '1'; // ACL_TYPE_GROUP
  grpb.checked = 'checked';
  grpb.className = seed;
  grpb.onclick = function() { seed = this.className; document.getElementById('enACL_grpbox_'+seed).style.display = 'block'; document.getElementById('enACL_usrbox_'+seed).style.display = 'none'; };
  lbl = document.createElement('label');
  lbl.appendChild(grpb);
  lbl.appendChild(document.createTextNode($lang.get('acl_radio_usergroup')));
  lbl.style.display = 'block';
  span.appendChild(grpsel);
  
  anoninfo = document.createElement('div');
  anoninfo.className = 'info-box-mini';
  anoninfo.appendChild(document.createTextNode($lang.get('acl_msg_guest_howto')));
  span.appendChild(document.createElement('br'));
  span.appendChild(anoninfo);
  
  usrb = document.createElement('input');
  usrb.type = 'radio';
  usrb.name  = 'target_type';
  usrb.value = '2'; // ACL_TYPE_USER
  usrb.className = seed;
  usrb.onclick = function() { seed = this.className; document.getElementById('enACL_grpbox_'+seed).style.display = 'none'; document.getElementById('enACL_usrbox_'+seed).style.display = 'block'; };
  lbl2 = document.createElement('label');
  lbl2.appendChild(usrb);
  lbl2.appendChild(document.createTextNode($lang.get('acl_radio_user')));
  lbl2.style.display = 'block';
  
  usrsel = document.createElement('input');
  usrsel.type = 'text';
  usrsel.name = 'username';
  usrsel.className = 'autofill username';
  usrsel.id = 'userfield_' + aclManagerID;
  try {
    usrsel.setAttribute("autocomplete","off");
  } catch(e) {};
  
  span2 = document.createElement('div');
  span2.id = "enACL_usrbox_"+seed+"";
  span2.style.display = 'none';
  span2.appendChild(usrsel);
  
  // Scope selector
  if(do_scopesel)
  {
    scopediv1 = document.createElement('div');
    scopediv2 = document.createElement('div');
    scopediv3 = document.createElement('div');
    scopeRadioPage = document.createElement('input');
      scopeRadioPage.type = 'radio';
      scopeRadioPage.name = 'scope';
      scopeRadioPage.value = 'page';
      scopeRadioPage.checked = 'checked';
      scopeRadioPage.className = '1048576';
      if ( groups.page_groups.length > 0 ) scopeRadioPage.onclick = function() { var id = 'enACL_pgsel_' + this.className; document.getElementById(id).style.display = 'none'; };
    scopeRadioGlobal = document.createElement('input');
      scopeRadioGlobal.type = 'radio';
      scopeRadioGlobal.name = 'scope';
      scopeRadioGlobal.value = 'global';
      scopeRadioGlobal.className = '1048576';
      if ( groups.page_groups.length > 0 ) scopeRadioGlobal.onclick = function() { var id = 'enACL_pgsel_' + this.className; document.getElementById(id).style.display = 'none'; };
    scopeRadioGroup = document.createElement('input');
      scopeRadioGroup.type = 'radio';
      scopeRadioGroup.name = 'scope';
      scopeRadioGroup.value = 'group';
      scopeRadioGroup.className = '1048576';
      if ( groups.page_groups.length > 0 ) scopeRadioGroup.onclick = function() { var id = 'enACL_pgsel_' + this.className; document.getElementById(id).style.display = 'block'; };
    lblPage = document.createElement('label');
      lblPage.style.display = 'block';
      lblPage.appendChild(scopeRadioPage);
      lblPage.appendChild(document.createTextNode($lang.get('acl_radio_scope_thispage')));
    lblGlobal = document.createElement('label');
      lblGlobal.style.display = 'block';
      lblGlobal.appendChild(scopeRadioGlobal);
      lblGlobal.appendChild(document.createTextNode($lang.get('acl_radio_scope_wholesite')));
    lblGroup = document.createElement('label');
      lblGroup.style.display = 'block';
      lblGroup.appendChild(scopeRadioGroup);
      lblGroup.appendChild(document.createTextNode($lang.get('acl_radio_scope_pagegroup')));
    scopediv1.appendChild(lblPage);
    scopediv2.appendChild(lblGroup);
    scopediv3.appendChild(lblGlobal);
    
    scopedesc = document.createElement('p');
    scopedesc.appendChild(document.createTextNode($lang.get('acl_lbl_scope')));
    
    scopePGrp = document.createElement('select');
    scopePGrp.style.marginLeft = '13px';
    scopePGrp.style.display = 'none';
    scopePGrp.id = "enACL_pgsel_1048576";
    
    var opt;
    for ( var i = 0; i < groups.page_groups.length; i++ )
    {
      opt = document.createElement('option');
      opt.value = groups.page_groups[i].id;
      opt.appendChild(document.createTextNode(groups.page_groups[i].name));
      scopePGrp.appendChild(opt);
    }
    
    scopediv2.appendChild(scopePGrp);
    
  }
  
  // Styles
  span.style.marginLeft = '13px';
  span.style.padding = '5px 0';
  span2.style.marginLeft = '13px';
  span2.style.padding = '5px 0';
  
  selector.appendChild(lbl);
  selector.appendChild(span);
  
  selector.appendChild(lbl2);
  selector.appendChild(span2);
  
  container = document.createElement('div');
  container.style.margin = 'auto';
  container.style.width = '360px';
  container.style.paddingTop = '50px';
  
  head = document.createElement('h2');
  head.appendChild(document.createTextNode($lang.get('acl_lbl_welcome_title')));
  
  desc = document.createElement('p');
  desc.appendChild(document.createTextNode($lang.get('acl_lbl_welcome_body')));
  
  container.appendChild(head);
  container.appendChild(desc);
  container.appendChild(selector);
  
  if(do_scopesel)
  {
    container.appendChild(scopedesc);
    container.appendChild(scopediv1);
    if ( groups.page_groups.length > 0 )
    {
      container.appendChild(scopediv2);
    }
    container.appendChild(scopediv3);
  }
  
  main.appendChild(container);
  
  var mode = document.createElement('input');
  mode.name = 'mode';
  mode.type = 'hidden';
  mode.id = aclManagerID + '_mode';
  mode.value = 'seltarget';
  
  var theform = document.getElementById(aclManagerID + '_formobj_id');
  if ( !theform.mode )
  {
    theform.appendChild(mode);
  }
  else
  {
    theform.removeChild(theform.mode);
    theform.appendChild(mode);
  }
  
  autofill_init_element(usrsel, {
      allow_anon: true
    });
}

var aclDebugWin = false;

function aclDebug(text)
{
  if(!aclDebugWin)
    aclDebugWin = pseudoWindowOpen("data:text/html;plain,<html><head><title>debug win</title></head><body><h1>Debug window</h1></body></html>", "aclDebugWin");
    setTimeout(function() {
  aclDebugWin.pre = aclDebugWin.document.createElement('pre');
  aclDebugWin.pre.appendChild(aclDebugWin.document.createTextNode(text));
  aclDebugWin.b = aclDebugWin.document.getElementsByTagName('body')[0];
    aclDebugWin.b.appendChild(aclDebugWin.pre);}, 1000);
}

var pseudoWindows = new Object();

function pseudoWindowOpen(url, id)
{
  if(pseudoWindows[id])
  {
    document.getElementById('pseudowin_ifr_'+id).src = url;
  }
  else
  {
    win = document.createElement('iframe');
    win.style.position='fixed';
    win.style.width = '640px';
    win.style.height = '480px';
    win.style.top = '0px';
    win.style.left = '0px';
    win.style.zIndex = getHighestZ() + 1;
    win.style.backgroundColor = '#FFFFFF';
    win.name = 'pseudo_ifr_'+id;
    win.id = 'pseudowindow_ifr_'+id;
    win.src = url;
    body = document.getElementsByTagName('body')[0];
    body.appendChild(win);
  }
  win_obj = eval("( pseudo_ifr_"+id+" )");
  return win_obj;
}

function __aclJSONSubmitAjaxHandler(params)
{
  params = toJSONString(params);
  params = ajaxEscape(params);
  ajaxPost(stdAjaxPrefix+'&_mode=acljson', 'acl_params='+params, function() {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        try
        {
          var data = parseJSON(ajax.responseText);
        }
        catch(e)
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        aclDataCache = data;
        switch(data.mode)
        {
          case 'seltarget':
            
            // Build the ACL edit form
            aclBuildRuleEditor(data);
            
            break;
          case 'success':
            var note = document.createElement('div');
            note.className = 'info-box';
            note.style.marginLeft = '0';
            var b = document.createElement('b');
            b.appendChild(document.createTextNode($lang.get('acl_lbl_save_success_title')));
            note.appendChild(b);
            note.appendChild(document.createElement('br'));
            note.appendChild(document.createTextNode($lang.get('acl_lbl_save_success_body', { target_name: data.target_name })));
            note.appendChild(document.createElement('br'));
            
            /*
            var a = document.createElement('a');
            a.href = '#';
            a.id = aclManagerID + '_btn_dismiss';
            a.appendChild(document.createTextNode('[ ' + $lang.get('acl_btn_success_dismiss') + ' :'));
            note.appendChild(a);
            var a2 = document.createElement('a');
            a2.href = '#';
            a.id = aclManagerID + '_btn_close';
            a2.appendChild(document.createTextNode(': ' + $lang.get('acl_btn_success_close') + ' ]'));
            note.appendChild(a2);
            */
            
            var a_dismiss = document.createElement('a');
            a_dismiss.href = '#';
            a_dismiss.appendChild(document.createTextNode('[ ' + $lang.get('acl_btn_success_dismiss') + ' :'));
            note.appendChild(a_dismiss);
            
            var a_close = document.createElement('a');
            a_close.href = '#';
            a_close.appendChild(document.createTextNode(': ' + $lang.get('acl_btn_success_close') + ' ]'));
            note.appendChild(a_close);
            
            document.getElementById(aclManagerID + '_main').insertBefore(note, document.getElementById(aclManagerID + '_main').firstChild);
            
            a_dismiss.setAttribute('onclick', 'var parent = this.parentNode.parentNode; parent.removeChild(this.parentNode); return false;');
            a_close.setAttribute('onclick', 'killACLManager(); return false;');
            
            if ( !document.getElementById(aclManagerID+'_deletelnk') )
              document.getElementById(aclManagerID + '_main').innerHTML += '<p id="'+aclManagerID+'_deletelnk" style="text-align: right;"><a href="#delete_acl_rule" onclick="if(confirm(\'' + $lang.get('acl_msg_deleterule_confirm') + '\')) __aclDeleteRule(); return false;" style="color: red;">' + $lang.get('acl_lbl_deleterule') + '</a></p>';
            
            document.getElementById(aclManagerID+'_main').scrollTop = 0;
            document.getElementById(aclManagerID+'_main').style.backgroundImage = 'none';
                        
            aclDataCache.mode = 'save_edit';
            break;
          case 'delete':
            
            params = {
              'mode' : 'listgroups'
            };
          params = toJSONString(params);
          params = ajaxEscape(params);
          ajaxPost(stdAjaxPrefix+'&_mode=acljson', 'acl_params='+params, function() {
              if ( ajax.readyState == 4 && ajax.status == 200 )
              {
                document.getElementById(aclManagerID+'_main').innerHTML = '';
                document.getElementById(aclManagerID + '_back').style.display = 'none';
                document.getElementById(aclManagerID + '_next').value = $lang.get('etc_wizard_next');
                var thispage = strToPageID(title);
                groups.page_id = thispage[0];
                groups.namespace = thispage[1];
                __aclBuildSelector(groups);
                
                note = document.createElement('div');
                note.className = 'info-box';
                note.style.marginLeft = '0';
                note.style.position = 'absolute';
                note.style.width = '558px';
                note.id = 'aclSuccessNotice_' + Math.floor(Math.random() * 100000);
                b = document.createElement('b');
                b.appendChild(document.createTextNode($lang.get('acl_lbl_delete_success_title')));
                note.appendChild(b);
                note.appendChild(document.createElement('br'));
                note.appendChild(document.createTextNode($lang.get('acl_lbl_delete_success_body', { target_name: aclDataCache.target_name })));
                note.appendChild(document.createElement('br'));
                a = document.createElement('a');
                a.href = '#';
                a.onclick = function() { opacity(this.parentNode.id, 100, 0, 1000); setTimeout('var div = document.getElementById("' + this.parentNode.id + '"); div.parentNode.removeChild(div);', 1100); return false; };
                a.appendChild(document.createTextNode('[ ' + $lang.get('acl_btn_success_dismiss') + ' :'));
                note.appendChild(a);
                a = document.createElement('a');
                a.href = '#';
                a.onclick = function() { killACLManager(); return false; };
                a.appendChild(document.createTextNode(': ' + $lang.get('acl_btn_success_close') + ' ]'));
                note.appendChild(a);
                document.getElementById(aclManagerID + '_main').insertBefore(note, document.getElementById(aclManagerID + '_main').firstChild);
                //fadeInfoBoxes();
                
              }
            }, true);
            
            break;
          case 'error':
            alert("Server side processing error:\n"+data.error);
            break;
          case 'debug':
            aclDebug(data.text);
            break;
          case 'list_existing':
            aclSetViewListExistingRespond(data);
            break;
          default:
            handle_invalid_json(ajax.responseText);
            break;
        }
      }
    }, true);
}

function aclBuildRuleEditor(data, from_direct)
{
  var act_desc = ( data.type == 'new' ) ? $lang.get('acl_lbl_editwin_title_create') : $lang.get('acl_lbl_editwin_title_edit');
  var target_type_t = ( data.target_type == 1 ) ? $lang.get('acl_target_type_group') : $lang.get('acl_target_type_user');
  var target_name_t = data.target_name;
  var scope_type = ( data.page_id == false && data.namespace == false ) ? $lang.get('acl_scope_type_wholesite') : ( data.namespace == '__PageGroup' ) ? $lang.get('acl_scope_type_pagegroup') : $lang.get('acl_scope_type_thispage');
  
  document.getElementById(aclManagerID + '_next').style.display = 'inline';
  
  html = '<h2>'+act_desc+'</h2>';
  html += '<p>' + $lang.get('acl_lbl_editwin_body', { target_type: target_type_t, target: target_name_t, scope_type: scope_type }) + '</p>';
  
  // preset management
  var load_flags = 'href="#" onclick="aclShowPresetLoader(); return false;"';
  var save_flags = 'href="#" onclick="aclShowPresetSave(); return false;"';
  html += '<div style="float: right;">';
  html += $lang.get('acl_btn_edit_presets', { load_flags: load_flags, save_flags: save_flags });
  html += '</div>';
  html += '<div style="clear: both;"></div>';
  
  parser = new templateParser(data.template.acl_field_begin);
  html += parser.run();
  
  cls = 'row2';
  for(var i in data.acl_types)
  {
    if(typeof(data.acl_types[i]) == 'number')
    {
      cls = ( cls == 'row1' ) ? 'row2' : 'row1';
      p = new templateParser(data.template.acl_field_item);
      vars = new Object();
      if ( data.acl_descs[i].match(/^([a-z0-9_]+)$/) )
      {
        vars['FIELD_DESC'] = $lang.get(data.acl_descs[i]);
      }
      else
      {
        vars['FIELD_DESC'] = data.acl_descs[i];
      }
      vars['FIELD_INHERIT_CHECKED'] = '';
      vars['FIELD_DENY_CHECKED'] = '';
      vars['FIELD_DISALLOW_CHECKED'] = '';
      vars['FIELD_WIKIMODE_CHECKED'] = '';
      vars['FIELD_ALLOW_CHECKED'] = '';
      vars['FIELD_NAME'] = i;
      if ( !data.current_perms[i] )
      {
        data.current_perms[i] = 'i';
      }
      switch(data.current_perms[i])
      {
        case 'i':
        default:
          vars['FIELD_INHERIT_CHECKED'] = 'checked="checked"';
          break;
        case 1:
          vars['FIELD_DENY_CHECKED'] = 'checked="checked"';
          break;
        case 2:
          vars['FIELD_DISALLOW_CHECKED'] = 'checked="checked"';
          break;
        case 3:
          vars['FIELD_WIKIMODE_CHECKED'] = 'checked="checked"';
          break;
        case 4:
          vars['FIELD_ALLOW_CHECKED'] = 'checked="checked"';
          break;
      }
      vars['ROW_CLASS'] = cls;
      p.assign_vars(vars);
      html += p.run();
    }
  }
  
  var parser = new templateParser(data.template.acl_field_end);
  html += parser.run();
  
  if(data.type == 'edit')
    html += '<p id="'+aclManagerID+'_deletelnk" style="text-align: right;"><a href="#delete_acl_rule" onclick="if(confirm(\'' + $lang.get('acl_msg_deleterule_confirm') + '\')) __aclDeleteRule(); return false;" style="color: red;">' + $lang.get('acl_lbl_deleterule') + '</a></p>';
  
  var main = document.getElementById(aclManagerID + '_main');
  main.innerHTML = html;
  
  var form = document.getElementById(aclManagerID + '_formobj_id');
  
  if ( from_direct )
  {
    var modeobj = document.getElementById(aclManagerID + '_mode');
    modeobj.value = 'save_edit';
  }
  else
  {
    var modeobj = form_fetch_field(form, 'mode');
    if ( modeobj )
      modeobj.value = 'save_' + data.type;
    else
      alert('modeobj is invalid: '+modeobj);
  }
  
  aclPermList = array_keys(data.acl_types);
  
  document.getElementById(aclManagerID + '_back').style.display = 'inline';
  document.getElementById(aclManagerID + '_next').value = $lang.get('etc_save_changes');
}

function __aclBuildGroupsHTML(groups)
{
  groups = groups.groups;
  select = document.createElement('select');
  for(var i in groups)
  {
    if(typeof(groups[i]['name']) == 'string' && i != 'toJSONString')
    {
      o = document.createElement('option');
      o.value = groups[i]['id'];
      t = document.createTextNode(groups[i]['name']);
      o.appendChild(t);
      select.appendChild(o);
    }
  }
  return select;
}

function __aclBuildWizardWindow()
{
  darken(aclDisableTransitionFX);
  box = document.createElement('div');
  box.style.width = '640px'
  box.style.height = '440px';
  box.style.position = 'fixed';
  width = getWidth();
  height = getHeight();
  box.style.left = ( width / 2 - 320 ) + 'px';
  box.style.top = ( height / 2 - 250 ) + 'px';
  box.style.backgroundColor = 'white';
  box.style.zIndex = getHighestZ() + 1;
  box.id = aclManagerID;
  box.style.opacity = '0';
  box.style.filter = 'alpha(opacity=0)';
  box.style.display = 'none';
  
  mainwin = document.createElement('div');
  mainwin.id = aclManagerID + '_main';
  mainwin.style.clip = 'rect(0px,640px,440px,0px)';
  mainwin.style.overflow = 'auto';
  mainwin.style.width = '620px';
  mainwin.style.height = '420px';
  
  panel = document.createElement('div');
  panel.style.width = '620px';
  panel.style.padding = '10px';
  panel.style.lineHeight = '40px';
  panel.style.textAlign = 'right';
  panel.style.position = 'fixed';
  panel.style.left = ( width / 2 - 320 ) + 'px';
  panel.style.top = ( height / 2 + 190 ) + 'px';
  panel.style.backgroundColor = '#D0D0D0';
  panel.style.opacity = '0';
  panel.style.filter = 'alpha(opacity=0)';
  panel.id = aclManagerID + '_panel';
  
  form = document.createElement('form');
  form.method = 'post';
  form.action = 'javascript:void(0)';
  form.onsubmit = function() { if(this.username && !submitAuthorized) return false; __aclSubmitManager(this); return false; };
  form.name = aclManagerID + '_formobj';
  form.id   = aclManagerID + '_formobj_id';
  
  back = document.createElement('input');
  back.type = 'button';
  back.value = $lang.get('etc_wizard_back');
  back.style.fontWeight = 'normal';
  back.onclick = function() { ajaxACLSwitchToSelector(); return false; };
  back.style.display = 'none';
  back.id = aclManagerID + '_back';
  
  saver = document.createElement('input');
  saver.type = 'submit';
  saver.value = $lang.get('etc_wizard_next');
  saver.style.fontWeight = 'bold';
  saver.id = aclManagerID + '_next';
  
  closer = document.createElement('input');
  closer.type = 'button';
  closer.value = $lang.get('etc_cancel_changes');
  closer.onclick = function()
  {
    miniPromptMessage({
      title: $lang.get('acl_msg_closeacl_confirm_title'),
      message: $lang.get('acl_msg_closeacl_confirm_body'),
      buttons: [
        {
          text: $lang.get('acl_btn_close'),
          color: 'red',
          style: {
            fontWeight: 'bold'
          },
          onclick: function(e)
          {
            killACLManager();
            miniPromptDestroy(this);
          }
        },
        {
          text: $lang.get('etc_cancel'),
          onclick: function(e)
          {
            miniPromptDestroy(this);
          }
        }
      ]
    });
    return false;
  }
  
  spacer1 = document.createTextNode('  ');
  spacer2 = document.createTextNode('  ');
  
  panel.appendChild(back);
  panel.appendChild(spacer1);
  panel.appendChild(saver);
  panel.appendChild(spacer2);
  panel.appendChild(closer);
  form.appendChild(mainwin);
  form.appendChild(panel);
  box.appendChild(form);
  
  body = document.getElementsByTagName('body')[0];
  body.appendChild(box);
  if ( aclDisableTransitionFX )
  {
    document.getElementById(aclManagerID).style.display = 'block';
    changeOpac(100, aclManagerID);
    changeOpac(100, aclManagerID + '_panel');
  }
  else
  {
    setTimeout("document.getElementById('"+aclManagerID+"').style.display = 'block'; opacity('"+aclManagerID+"', 0, 100, 500); opacity('"+aclManagerID + '_panel'+"', 0, 100, 500);", 1000);
  }
}

function killACLManager()
{
  el = document.getElementById(aclManagerID);
  if(el)
  {
    if ( aclDisableTransitionFX )
    {
      enlighten(true);
      el.parentNode.removeChild(el);
    }
    else
    {
      opacity(aclManagerID, 100, 0, 500);
      setTimeout('var el = document.getElementById(aclManagerID); el.parentNode.removeChild(el); enlighten();', 750);
    }
  }
}

function __aclSubmitManager(form)
{
  var thefrm = document.forms[form.name];
  var modeobj = form_fetch_field(thefrm, 'mode');
  if ( typeof(modeobj) == 'object' )
  {
    var mode = (thefrm.mode.value) ? thefrm.mode.value : 'cant_get';
  }
  else
  {
    var mode = '';
  }
  switch(mode)
  {
    case 'cant_get':
      alert('BUG: can\'t get the state value from the form field.');
      break;
    case 'seltarget':
      var target_type = parseInt(getRadioState(thefrm, 'target_type', ['1', '2']));
      if(isNaN(target_type))
      {
        alert($lang.get('acl_err_pleaseselect_targettype'));
        return false;
      }
      target_id = ( target_type == 1 ) ? parseInt(thefrm.group_id.value) : thefrm.username.value;
      
      obj = { 'mode' : mode, 'target_type' : target_type, 'target_id' : target_id };
      
      thispage = strToPageID(title);
      do_scopesel = ( thispage[0] == aclDataCache.page_id && thispage[1] == aclDataCache.namespace );
      
      if(do_scopesel)
      {
        scope = getRadioState(thefrm, 'scope', ['page', 'group', 'global']);
        if(scope == 'page')
        {
          pageid = strToPageID(title);
          obj['page_id'] = pageid[0];
          obj['namespace'] = pageid[1];
        }
        else if(scope == 'global')
        {
          obj['page_id'] = false;
          obj['namespace'] = false;
        }
        else if(scope == 'group')
        {
          obj['page_id'] = document.getElementById('enACL_pgsel_1048576').value;
          obj['namespace'] = '__PageGroup';
        }
        else
        {
          alert('Invalid scope');
          return false;
        }
      }
      else
      {
        obj['page_id'] = aclDataCache.page_id;
        obj['namespace'] = aclDataCache.namespace;
      }
      if(target_id == '')
      {
        alert($lang.get('acl_err_pleaseselect_username'));
        return false;
      }
      __aclJSONSubmitAjaxHandler(obj);
      break;
    case 'save_edit':
    case 'save_new':
      var form = document.forms[aclManagerID + '_formobj'];
      selections = new Object();
      var dbg = '';
      var warned_everyone = false;
      for(var i in aclPermList)
      {
        selections[aclPermList[i]] = getRadioState(form, aclPermList[i], ['i', 1, 2, 3, 4]);
        // If we're editing permissions for everyone on the entire site and the
        // admin selected to deny privileges, give a stern warning about it.
        if ( selections[aclPermList[i]] == 1 && aclDataCache.target_type == 1 /* ACL_TYPE_GROUP */ && aclDataCache.target_id == 1 && !warned_everyone )
        {
          warned_everyone = true;
          if ( !confirm($lang.get('acl_msg_deny_everyone_confirm')) )
          {
            return false;
          }
        }
        dbg += aclPermList[i] + ': ' + selections[aclPermList[i]] + "\n";
        if(!selections[aclPermList[i]])
        {
          alert("Invalid return from getRadioState: "+i+": "+selections[i]+" ("+typeof(selections[i])+")");
          return false;
        }
      }
      obj = new Object();
      obj['perms'] = selections;
      obj['mode'] = mode;
      obj['target_type'] = aclDataCache.target_type;
      obj['target_id'] = aclDataCache.target_id;
      obj['target_name'] = aclDataCache.target_name;
      obj['page_id'] = aclDataCache.page_id;
      obj['namespace'] = aclDataCache.namespace;
      __aclJSONSubmitAjaxHandler(obj);
      break;
    default:
      alert("JSON form submit: invalid mode string "+mode+", stopping execution");
      return false;
      break;
  }
}

function getRadioState(form, name, valArray)
{
  // Konqueror/Safari fix
  if ( form[name] )
  {
    var formitem = form[name];
    if ( String(formitem) == '[object DOMNamedNodesCollection]' || is_Safari )
    {
      var i = 0;
      var radios = new Array();
      var radioids = new Array();
      while(true)
      {
        var elem = formitem[i];
        if ( !elem )
          break;
        radios.push(elem);
        if ( !elem.id )
        {
          elem.id = 'autoRadioBtn_' + Math.floor(Math.random() * 1000000);
        }
        radioids.push(elem.id);
        i++;
      }
      var cr;
      for ( var i = 0; i < radios.length; i++ )
      {
        cr = document.getElementById(radioids[i]);
        if ( cr.value == 'on' || cr.checked == true )
        {
          try {
            return ( typeof ( valArray[i] ) != 'undefined' ) ? valArray[i] : false;
          } catch(e) {
            // alert('Didn\'t get value for index: ' + i);
            return false;
          }
        }
      }
      return false;
    }
  }
  inputs = form.getElementsByTagName('input');
  radios = new Array();
  for(var i in inputs)
  {
    if(inputs[i]) if(inputs[i].type == 'radio')
      radios.push(inputs[i]);
  }
  for(var i in radios)
  {
    if(radios[i].checked && radios[i].name == name)
      return radios[i].value;
  }
  return false;
}

function __aclSetAllRadios(val, valArray)
{
  val = String(val);
  var form = document.forms[aclManagerID + '_formobj'];
  if (!form)
  {
    return false;
  }
  var inputs = form.getElementsByTagName('input');
  var radios = new Array();
  var dbg = '';
  for(var i = 0; i < inputs.length; i++)
  {
    dbg += String(inputs[i]) + "\n";
    if(inputs[i].type == 'radio')
      radios.push(inputs[i]);
  }
  for(var i in radios)
  {
    if(radios[i].value == val)
      radios[i].checked = true;
    else
      radios[i].checked = false;
  }
}

function __aclDeleteRule()
{
  if(!aclDataCache) 
  {
    if ( window.console )
    {
      try{ console.error('ACL editor: can\'t load data cache on delete'); } catch(e) {};
    }
    return false;
  }
  if(aclDataCache.mode != 'seltarget' && aclDataCache.mode != 'save_new' && aclDataCache.mode != 'save_edit')
  {
    if ( window.console )
    {
      try{ console.error('ACL editor: wrong mode on aclDataCache: ' + aclDataCache.mode); } catch(e) {};
    }
    return false;
  }
  parms = {
    'target_type' : aclDataCache.target_type,
    'target_id' : aclDataCache.target_id,
    'target_name' : aclDataCache.target_name,
    'page_id' : aclDataCache.page_id,
    'namespace' : aclDataCache.namespace,
    'mode' : 'delete'
  };
  __aclJSONSubmitAjaxHandler(parms);
}

function aclSetViewListExisting()
{
  if ( !document.getElementById(aclManagerID) )
  {
    return false;
  }
  
  var main = document.getElementById(aclManagerID + '_main');
  main.innerHTML = '';
  main.style.backgroundImage = 'url(' + scriptPath + '/images/loading-big.gif)';
  main.style.backgroundRepeat = 'no-repeat';
  main.style.backgroundPosition = 'center center';
  
  var parms = {
    'mode' : 'list_existing'
  };
  __aclJSONSubmitAjaxHandler(parms);
}

function aclSetViewListExistingRespond(data)
{
  var main = document.getElementById(aclManagerID + '_main');
  main.style.padding = '10px';
  main.innerHTML = '';
  
  var heading = document.createElement('h3');
  heading.appendChild(document.createTextNode($lang.get('acl_msg_scale_intro_title')));
  main.appendChild(heading);
  
  var p = document.createElement('p');
  p.appendChild(document.createTextNode($lang.get('acl_msg_scale_intro_body')));
  main.appendChild(p);
  
  
  main.innerHTML += data.key;
  main.style.backgroundImage = 'none';
  
  document.getElementById(aclManagerID + '_back').style.display = 'inline';
  document.getElementById(aclManagerID + '_next').style.display = 'none';
  
  for ( var i = 0; i < data.rules.length; i++ )
  {
    var rule = data.rules[i];
    // build the rule, this is just more boring DOM crap.
    var div = document.createElement('div');
    div.style.padding = '5px 3px';
    div.style.backgroundColor = '#' + rule.color;
    div.style.cursor = 'pointer';
    div.rule_id = rule.rule_id;
    div.onclick = function()
    {
      var main = document.getElementById(aclManagerID + '_main');
      main.innerHTML = '';
      main.style.backgroundImage = 'url(' + scriptPath + '/images/loading-big.gif)';
      ajaxOpenDirectACLRule(parseInt(this.rule_id));
    }
    div.innerHTML = rule.score_string;
    main.appendChild(div);
  }
}

function aclShowPresetLoader()
{
  var prompt = miniPrompt(function(parent)
    {
      parent.innerHTML = '<img style="display: block; margin: 0 auto;" src="' + cdnPath + '/images/loading-big.gif" />';
    });
  var request = toJSONString({
      mode: 'list_presets'
    });
  ajaxPost(stdAjaxPrefix + '&_mode=acljson', 'acl_params=' + ajaxEscape(request), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( !check_json_response(ajax.responseText) )
        {
          miniPromptDestroy(prompt);
          return handle_invalid_json(ajax.responseText);
        }
        var response = parseJSON(ajax.responseText);
        if ( response.mode == 'error' )
        {
          alert(response.error);
          miniPromptDestroy(prompt);
          return false;
        }
        prompt = prompt.firstChild.nextSibling;
        prompt.style.textAlign = 'center';
        prompt.innerHTML = '<h3>' + $lang.get('acl_lbl_preset_load_title') + '</h3>';
        
        if ( response.presets.length > 0 )
        {
          // selection box
          var para = document.createElement('p');
          var select = document.createElement('select');
          
          var option = document.createElement('option');
          option.value = '0';
          option.appendChild(document.createTextNode($lang.get('acl_lbl_preset_load')));
          select.appendChild(option);
          
          for ( var i = 0; i < response.presets.length; i++ )
          {
            var preset = response.presets[i];
            var option = document.createElement('option');
            option.value = preset.rule_id;
            option.preset_data = preset;
            option.appendChild(document.createTextNode($lang.get(preset.preset_name)));
            select.appendChild(option);
          }
          
          para.appendChild(select);
          prompt.appendChild(para);
          
          // buttons
          var buttons = document.createElement('p');
          
          // load button
          var btn_load = document.createElement('a');
          btn_load.className = 'abutton abutton_green';
          btn_load.style.fontWeight = 'bold';
          btn_load.appendChild(document.createTextNode($lang.get('acl_btn_load_preset')));
          btn_load.selectobj = select;
          btn_load.onclick = function()
          {
            if ( this.selectobj.value == '0' )
            {
              alert($lang.get('acl_err_select_preset'));
              return false;
            }
            // retrieve preset data
            for ( var i = 0; i < this.selectobj.childNodes.length; i++ )
            {
              if ( this.selectobj.childNodes[i].tagName == 'OPTION' )
              {
                var node = this.selectobj.childNodes[i];
                if ( node.value == this.selectobj.value )
                {
                  aclSetRulesAbsolute(node.preset_data.rules);
                  break;
                }
              }
            }
            miniPromptDestroy(this);
            return false;
          }
          btn_load.href = '#';
          buttons.appendChild(btn_load);
          
          buttons.appendChild(document.createTextNode(' '));
          
          // cancel button
          var btn_cancel = document.createElement('a');
          btn_cancel.className = 'abutton';
          btn_cancel.appendChild(document.createTextNode($lang.get('etc_cancel')));
          btn_cancel.onclick = function()
          {
            miniPromptDestroy(this);
            return false;
          }
          btn_cancel.href = '#';
          buttons.appendChild(btn_cancel);
          
          prompt.appendChild(buttons);
        }
        else
        {
          // "no presets"
          prompt.innerHTML += '<p>' + $lang.get('acl_msg_no_presets', { close_flags: 'href="#" onclick="miniPromptDestroy(this); return false;"' }) + '</p>';
        }
      }
    });
}

function aclSetRulesAbsolute(rules)
{
  __aclSetAllRadios('i');
  
  var form = document.forms[aclManagerID + '_formobj'];
  if (!form)
  {
    return false;
  }
  var inputs = form.getElementsByTagName('input');
  var radios = new Array();
  var dbg = '';
  for(var i = 0; i < inputs.length; i++)
  {
    if(inputs[i].type == 'radio')
      radios.push(inputs[i]);
  }
  for(var i in radios)
  {
    if ( typeof(rules[ radios[i]['name'] ]) == 'number' )
    {
      radios[i].checked = ( rules[radios[i]['name']] == radios[i].value );
    }
  }
}

function aclShowPresetSave()
{
  miniPrompt(function(parent)
    {
      parent.style.textAlign = 'center';
      
      parent.innerHTML = '<h3>' + $lang.get('acl_lbl_preset_save_title') + '</h3>';
      var input = document.createElement('input');
      input.id = aclManagerID + '_preset_save';
      input.type = 'text';
      input.size = '30';
      input.onkeypress = function(e)
      {
        // javascript sucks. IE and several others throw myriad errors unless it's done this way.
        if ( e )
        if ( e.keyCode )
        if ( e.keyCode == 13 )
        {
          if ( aclSavePreset() )
          {
            if ( window.opera )
            {
              // damn weird opera bug.
              var input = this;
              setTimeout(function()
                {
                  miniPromptDestroy(input);
                }, 10);
            }
            else
            {
              miniPromptDestroy(this);
            }
          }
        }
        else if ( e.keyCode == 27 )
        {
          miniPromptDestroy(this);
        }
      }
      var para = document.createElement('p');
      para.appendChild(input);
      
      parent.appendChild(para);
      
      // buttons
      var buttons = document.createElement('p');
      
      // save button
      var btn_save = document.createElement('a');
      btn_save.className = 'abutton abutton_green';
      btn_save.style.fontWeight = 'bold';
      btn_save.appendChild(document.createTextNode($lang.get('acl_btn_save_preset')));
      btn_save.selectobj = select;
      btn_save.onclick = function()
      {
        if ( aclSavePreset() )
        {
          miniPromptDestroy(this);
        }
        return false;
      }
      btn_save.href = '#';
      buttons.appendChild(btn_save);
      
      buttons.appendChild(document.createTextNode(' '));
      
      // cancel button
      var btn_cancel = document.createElement('a');
      btn_cancel.className = 'abutton';
      btn_cancel.appendChild(document.createTextNode($lang.get('etc_cancel')));
      btn_cancel.onclick = function()
      {
        miniPromptDestroy(this);
        return false;
      }
      btn_cancel.href = '#';
      buttons.appendChild(btn_cancel);
      
      parent.appendChild(buttons);
      
      var timeout = ( aclDisableTransitionFX ) ? 10 : 1000;
      setTimeout(function()
        {
          input.focus();
        }, timeout);
    });
}

function aclSavePreset()
{
  var input = document.getElementById(aclManagerID + '_preset_save');
  if ( trim(input.value) == '' )
  {
    alert($lang.get('acl_err_preset_name_empty'));
    return false;
  }
  var form = document.forms[aclManagerID + '_formobj'], selections = {};
  var dbg = '';
  var warned_everyone = false;
  for(var i in aclPermList)
  {
    selections[aclPermList[i]] = getRadioState(form, aclPermList[i], ['i', 1, 2, 3, 4]);
    // If we're editing permissions for everyone on the entire site and the
    // admin selected to deny privileges, give a stern warning about it.
    if ( selections[aclPermList[i]] == 1 && aclDataCache.target_type == 1 /* ACL_TYPE_GROUP */ && aclDataCache.target_id == 1 && !warned_everyone )
    {
      warned_everyone = true;
      if ( !confirm($lang.get('acl_msg_deny_everyone_confirm')) )
      {
        return false;
      }
    }
    dbg += aclPermList[i] + ': ' + selections[aclPermList[i]] + "\n";
    if(!selections[aclPermList[i]])
    {
      alert("Invalid return from getRadioState: "+i+": "+selections[i]+" ("+typeof(selections[i])+")");
      return false;
    }
  }
  
  var packet = toJSONString({
      mode: 'save_preset',
      preset_name: input.value,
      perms: selections
    });
  
  var whitey = whiteOutElement(document.getElementById(aclManagerID));
  
  ajaxPost(stdAjaxPrefix + '&_mode=acljson', 'acl_params=' + ajaxEscape(packet), function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        if ( !check_json_response(ajax.responseText) )
        {
          whitey.parentNode.removeChild(whitey);
          return handle_invalid_json(ajax.responseText);
        }
        var response = parseJSON(ajax.responseText);
        if ( response.mode == 'error' )
        {
          whitey.parentNode.removeChild(whitey);
          alert(response.error);
          return false;
        }
        whiteOutReportSuccess(whitey);
      }
    });
  
  return true;
}

function array_keys(obj)
{
  keys = new Array();
  for(var i in obj)
    keys.push(i);
  return keys;
}

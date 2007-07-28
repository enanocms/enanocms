// Javascript routines for the ACL editor

var aclManagerID = 'enano_aclmanager_' + Math.floor(Math.random() * 1000000);
var aclPermList = false;
var aclDataCache = false;

function ajaxOpenACLManager(page_id, namespace)
{
  if(IE)
    return true;
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
      if(ajax.readyState == 4)
      {
        __aclBuildWizardWindow();
        groups = parseJSON(ajax.responseText);
        if ( groups.mode == 'error' )
        {
          alert(groups.error);
          killACLManager();
          return false;
        }
        aclDataCache = groups;
        __aclBuildSelector(groups);
      }
    });
  return false;
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
      if(ajax.readyState == 4)
      {
        document.getElementById(aclManagerID+'_main').innerHTML = '';
        document.getElementById(aclManagerID + '_back').style.display = 'none';
        document.getElementById(aclManagerID + '_next').value = 'Next >';
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
    });
}

function __aclBuildSelector(groups)
{
  thispage = strToPageID(title);
  do_scopesel = ( thispage[0] == groups.page_id && thispage[1] == groups.namespace );
  
  seed = Math.floor(Math.random() * 1000000);
        
  main = document.getElementById(aclManagerID + '_main');
  main.style.padding = '10px';
  
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
  lbl.appendChild(document.createTextNode('A usergroup'));
  lbl.style.display = 'block';
  span.appendChild(grpsel);
  
  anoninfo = document.createElement('div');
  anoninfo.className = 'info-box-mini';
  anoninfo.appendChild(document.createTextNode('To edit permissions for guests, select "a specific user", and enter Anonymous as the username.'));
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
  lbl2.appendChild(document.createTextNode('A specific user'));
  lbl2.style.display = 'block';
  
  usrsel = document.createElement('input');
  usrsel.type = 'text';
  usrsel.name = 'username';
  usrsel.onkeyup = function() { ajaxUserNameComplete(this); };
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
      lblPage.appendChild(document.createTextNode('Only this page'));
    lblGlobal = document.createElement('label');
      lblGlobal.style.display = 'block';
      lblGlobal.appendChild(scopeRadioGlobal);
      lblGlobal.appendChild(document.createTextNode('The entire website'));
    lblGroup = document.createElement('label');
      lblGroup.style.display = 'block';
      lblGroup.appendChild(scopeRadioGroup);
      lblGroup.appendChild(document.createTextNode('A group of pages'));
    scopediv1.appendChild(lblPage);
    scopediv2.appendChild(lblGroup);
    scopediv3.appendChild(lblGlobal);
    
    scopedesc = document.createElement('p');
    scopedesc.appendChild(document.createTextNode('What should this access rule control?'));
    
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
  head.appendChild(document.createTextNode('Manage page access'));
  
  desc = document.createElement('p');
  desc.appendChild(document.createTextNode('Please select who should be affected by this access rule.'));
  
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
      if(ajax.readyState == 4)
      {
        try {
          data = parseJSON(ajax.responseText);
        } catch(e) {
          aclDebug(e+"\n\nResponse:\n"+ajax.responseText);
        }
        aclDataCache = data;
        switch(data.mode)
        {
          case 'seltarget':
            
            // Build the ACL edit form
            // try {
              act_desc = ( data.type == 'new' ) ? 'Create access rule' : 'Editing permissions';
              target_type_t = ( data.target_type == 1 ) ? 'group' : 'user';
              target_name_t = data.target_name;
              var scope_type = ( data.page_id == false && data.namespace == false ) ? 'this entire site' : ( data.namespace == '__PageGroup' ) ? 'this group of pages' : 'this page';
              html = '<h2>'+act_desc+'</h2><p>This panel allows you to edit what the '+target_type_t+' "<b>'+target_name_t+'</b>" can do on <b>' + scope_type + '</b>. Unless you set a permission to "Deny", these permissions may be overridden by other rules.</p>';
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
                  vars['FIELD_DESC'] = data.acl_descs[i];
                  vars['FIELD_DENY_CHECKED'] = '';
                  vars['FIELD_DISALLOW_CHECKED'] = '';
                  vars['FIELD_WIKIMODE_CHECKED'] = '';
                  vars['FIELD_ALLOW_CHECKED'] = '';
                  vars['FIELD_NAME'] = i;
                  switch(data.current_perms[i])
                  {
                    case 1:
                      vars['FIELD_DENY_CHECKED'] = 'checked="checked"';
                      break;
                    case 2:
                    default:
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
                html += '<p id="'+aclManagerID+'_deletelnk" style="text-align: right;"><a href="#delete_acl_rule" onclick="if(confirm(\'Do you really want to delete this rule?\')) __aclDeleteRule(); return false;" style="color: red;">Delete this rule</a></p>';
              
              var main = document.getElementById(aclManagerID + '_main');
              main.innerHTML = html;
              
              var form = document.getElementById(aclManagerID + '_formobj_id');
              
              var modeobj = form_fetch_field(form, 'mode');
              if ( modeobj )
                modeobj.value = 'save_' + data.type;
              else
                alert('modeobj is invalid: '+modeobj);
              
              aclPermList = array_keys(data.acl_types);
              
              document.getElementById(aclManagerID + '_back').style.display = 'inline';
              document.getElementById(aclManagerID + '_next').value = 'Save Changes';
              
            // } catch(e) { alert(e); aclDebug(ajax.responseText); }
            
            break;
          case 'success':
            var note = document.createElement('div');
            note.className = 'info-box';
            note.style.marginLeft = '0';
            var b = document.createElement('b');
            b.appendChild(document.createTextNode('Permissions updated'));
            note.appendChild(b);
            note.appendChild(document.createElement('br'));
            note.appendChild(document.createTextNode('The permissions for '+data.target_name+' on this page have been updated successfully. If you changed permissions that affect your user account, you may not see changes until you reload the page.'));
            note.appendChild(document.createElement('br'));
            var a = document.createElement('a');
            a.href = 'javascript:void(0);';
            a.onclick = function() { this.parentNode.parentNode.removeChild(this.parentNode); return false; };
            a.appendChild(document.createTextNode('[ dismiss :'));
            note.appendChild(a);
            var a2 = document.createElement('a');
            a2.href = 'javascript:void(0);';
            a2.onclick = function() { killACLManager(); return false; };
            a2.appendChild(document.createTextNode(': close manager ]'));
            note.appendChild(a2);
            document.getElementById(aclManagerID + '_main').insertBefore(note, document.getElementById(aclManagerID + '_main').firstChild);
            if(!document.getElementById(aclManagerID+'_deletelnk'))
              document.getElementById(aclManagerID + '_main').innerHTML += '<p id="'+aclManagerID+'_deletelnk" style="text-align: right;"><a href="#delete_acl_rule" onclick="if(confirm(\'Do you really want to delete this rule?\')) __aclDeleteRule(); return false;" style="color: red;">Delete this rule</a></p>';
            //fadeInfoBoxes();
            document.getElementById(aclManagerID+'_main').scrollTop = 0;
            
            aclDataCache.mode = 'save_edit';
            break;
          case 'delete':
            
            params = {
              'mode' : 'listgroups'
            };
          params = toJSONString(params);
          params = ajaxEscape(params);
          ajaxPost(stdAjaxPrefix+'&_mode=acljson', 'acl_params='+params, function() {
              if(ajax.readyState == 4)
              {
                document.getElementById(aclManagerID+'_main').innerHTML = '';
                document.getElementById(aclManagerID + '_back').style.display = 'none';
                document.getElementById(aclManagerID + '_next').value = 'Next >';
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
                b.appendChild(document.createTextNode('Entry deleted'));
                note.appendChild(b);
                note.appendChild(document.createElement('br'));
                note.appendChild(document.createTextNode('The access rules for '+aclDataCache.target_name+' on this page have been deleted.'));
                note.appendChild(document.createElement('br'));
                a = document.createElement('a');
                a.href = '#';
                a.onclick = function() { opacity(this.parentNode.id, 100, 0, 1000); setTimeout('var div = document.getElementById("' + this.parentNode.id + '"); div.parentNode.removeChild(div);', 1100); return false; };
                a.appendChild(document.createTextNode('[ dismiss :'));
                note.appendChild(a);
                a = document.createElement('a');
                a.href = '#';
                a.onclick = function() { killACLManager(); return false; };
                a.appendChild(document.createTextNode(': close manager ]'));
                note.appendChild(a);
                document.getElementById(aclManagerID + '_main').insertBefore(note, document.getElementById(aclManagerID + '_main').firstChild);
                //fadeInfoBoxes();
                
              }
            });
            
            break;
          case 'error':
            alert("Server side processing error:\n"+data.error);
            break;
          case 'debug':
            aclDebug(data.text);
            break;
          default:
            alert("Invalid JSON response from server\nMode: "+data.mode+"\nJSON string: "+ajax.responseText);
            break;
        }
      }
    });
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
  darken();
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
  back.value = '< Back';
  back.style.fontWeight = 'normal';
  back.onclick = function() { ajaxACLSwitchToSelector(); return false; };
  back.style.display = 'none';
  back.id = aclManagerID + '_back';
  
  saver = document.createElement('input');
  saver.type = 'submit';
  saver.value = 'Next >';
  saver.style.fontWeight = 'bold';
  saver.id = aclManagerID + '_next';
  
  closer = document.createElement('input');
  closer.type = 'button';
  closer.value = 'Cancel Changes';
  closer.onclick = function() { if(!confirm('Do you really want to close the ACL manager?')) return false; killACLManager(); return false; }
  
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
  setTimeout("document.getElementById('"+aclManagerID+"').style.display = 'block'; opacity('"+aclManagerID+"', 0, 100, 500); opacity('"+aclManagerID + '_panel'+"', 0, 100, 500);", 1000);
}

function killACLManager()
{
  el = document.getElementById(aclManagerID);
  if(el)
  {
    opacity(aclManagerID, 100, 0, 500);
    setTimeout('var el = document.getElementById(aclManagerID); el.parentNode.removeChild(el); enlighten();', 750);
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
        alert('Please select a target type.');
        return false;
      }
      target_id = ( target_type == 1 ) ? parseInt(thefrm.group_id.value) : thefrm.username.value;
      
      obj = { 'mode' : mode, 'target_type' : target_type, 'target_id' : target_id };
      
      thispage = strToPageID(title);
      do_scopesel = ( thispage[0] == aclDataCache.page_id && thispage[1] == aclDataCache.namespace );
      
      if(do_scopesel)
      {
        scope = getRadioState(thefrm, 'scope', ['page', 'global']);
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
        alert('Please enter a username.');
        return false;
      }
      __aclJSONSubmitAjaxHandler(obj);
      break;
    case 'save_edit':
    case 'save_new':
      var form = document.forms[aclManagerID + '_formobj'];
      selections = new Object();
      var dbg = '';
      for(var i in aclPermList)
      {
        selections[aclPermList[i]] = getRadioState(form, aclPermList[i], [1, 2, 3, 4]);
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

function array_keys(obj)
{
  keys = new Array();
  for(var i in obj)
    keys.push(i);
  return keys;
}

function form_fetch_field(form, name)
{
  var fields = form.getElementsByTagName('input');
  if ( fields.length < 1 )
    return false;
  for ( var i = 0; i < fields.length; i++ )
  {
    var field = fields[i];
    if ( field.name == name )
      return field;
  }
  return false;
}


function ajaxToggleSystemThemes()
{
  var theme_list = document.getElementById('theme_list_edit');
  var mode = ( theme_list.sys_shown ) ? 'hide' : 'show';
  for ( var i = 0; i < theme_list.childNodes.length; i++ )
  {
    var child = theme_list.childNodes[i];
    if ( child.tagName == 'DIV' )
    {
      if ( $(child).hasClass('themebutton_theme_system') )
      {
        if ( $(child).hasClass('themebutton_theme_disabled') )
        {
          $(child).rmClass('themebutton_theme_disabled')
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
  setTimeout(function()
    {
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
    }, 3000);
}

function ajaxEditTheme(theme_id)
{
  // Fade out and subsequently destroy the entire list, then make an
  // ajax request to the theme manager for the theme info via JSON
  var theme_list = document.getElementById('theme_list_edit').parentNode;
  var backgroundImage = document.getElementById('themebtn_edit_' + theme_id).style.backgroundImage;
  for ( var i = 0; i < theme_list.childNodes.length; i++ )
  {
    var el = theme_list.childNodes[i];
    if ( el.tagName )
      domOpacity(el, 100, 0, 1000);
  }
  setTimeout(function()
    {
      theme_list.innerHTML = '';
      var req = toJSONString({
          mode: 'fetch_theme',
          theme_id: theme_id
        });
      // we've finished nukeing the existing interface, request editor data
      ajaxPost(makeUrlNS('Admin', 'ThemeManager/action.json'), 'r=' + ajaxEscape(req), function()
        {
          if ( ajax.readyState == 4 && ajax.status == 200 )
          {
            var response = String(ajax.responseText + '');
            if ( response.substr(0, 1) != '{' )
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
    }, 1050);
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
  
  // Availability policy
  var h3 = document.createElement('h3');
  h3.appendChild(document.createTextNode($lang.get('acptm_heading_theme_groups')));
  target.appendChild(h3);
  
  for ( var i = 0; i < data.group_list.length; i++ )
  {
    
  }
  
  var clearer = document.createElement('span');
  clearer.className = 'menuclear';
  target.appendChild(clearer);
  
  // Fade it all in
  domOpacity(target, 0, 100, 500);
  f_name.focus();
}

/*
var menuClicked = false;
var menuID = false;
var menuParent = false;
function adminOpenMenu(menu, parent)
{
  menuParent = parent;
  if ( typeof(menu) == 'string' )
  {
    menu = document.getElementById(menu);
  }
  if(!menu)
  {
    alert('Menu object is invalid');
    return false;
  }
  var off = fetch_offset(parent);
  var dim = fetch_dimensions(parent);
  var w = 200;
  var top = off['top'] + dim['h'];
  var left = off['left'] + dim['w'] - w;
  menu.style.top = top + 'px';
  menu.style.left = left + 'px';
  menu.style.display = 'block';
  menuID = menu.id;
  setTimeout('setMenuoffEvents();', 500);
  //if(!IE)
  //  parent.onclick = eval('(function() { this.onclick = function() { adminOpenMenu(\'' + menu.id + '\', this); return false; }; return false; } )');
}

function adminMenuOff()
{
  if ( menuID )
  {
    menu = document.getElementById(menuID);
    menu.style.display = 'none';
    menu.onmousedown = false;
    menu.onmouseup = false;
    menuID = false;
    document.onclick = false;
    //menuParent.onclick();
    //menuParent = false;
  }
}

function setMenuoffEvents()
{
  menu = document.getElementById(menuID);
  menu.onmousedown = function() { menuClicked = true; }
  menu.onmouseup   = function() { setTimeout('menuClicked = false;', 100); }
  document.onclick = function() { if ( menuClicked ) return false; adminMenuOff(); }
}
*/

function admin_expand()
{
  var expander = document.getElementById('sidebar-hide');
  var content  = document.getElementById('sidebar-show');
  var holder  = document.getElementById('td-sidebar');
  if ( content.style.display == 'table' )
  {
    createCookie('theme_admin_sidebar', 'collapsed', 3650);
    admin_collapse_real(expander, content, holder);
  }
  else
  {
    createCookie('theme_admin_sidebar', 'expanded', 3650);
    admin_expand_real(expander, content, holder);
  }
}

function admin_collapse_real(expander, content, holder)
{
  expander.className = 'collapsed';
  content.style.display = 'none';
  holder.style.width = '0px';
  holder.style.paddingRight = '12px';
  holder.style.paddingLeft = '0px';
}

function admin_expand_real(expander, content, holder)
{
  expander.className = 'expanded';
  content.style.display = 'table';
  holder.style.width = '230px';
  holder.style.paddingLeft = '12px';
  holder.style.paddingRight = '0px';
}

function expander_set_height()
{
  var expander = document.getElementById('sidebar-hide');
  var magic = $('header').Height() + $('pagebar_main').Height();
  var height = getHeight();
  var exheight = height - magic;
  expander.style.height = exheight + 'px';
  expander.style.top = magic + 'px';
}

function expander_onload()
{
  var expander = document.getElementById('sidebar-hide');
  var content  = document.getElementById('sidebar-show');
  var holder  = document.getElementById('td-sidebar');
  if ( readCookie('theme_admin_sidebar') == 'collapsed' )
  {
    admin_collapse_real(expander, content, holder);
  }
  else if ( readCookie('theme_admin_sidebar') == 'expanded' )
  {
    admin_expand_real(expander, content, holder);
  }
}

addOnloadHook(expander_set_height);
addOnloadHook(expander_onload);
window.onresize = expander_set_height;


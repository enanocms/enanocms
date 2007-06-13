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


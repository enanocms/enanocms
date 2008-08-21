/*
 * IE doesn't like display: table.
 */

var TBL_SHOW = ( IE ) ? 'block' : 'table';

function admin_expand()
{
  var expander = document.getElementById('sidebar-hide');
  var content  = document.getElementById('sidebar-show');
  var holder  = document.getElementById('td-sidebar');
  if ( content.style.display == TBL_SHOW )
  {
    admin_collapse_real(expander, content, holder);
    createCookie('theme_admin_sidebar', 'collapsed', 3650);
  }
  else
  {
    admin_expand_real(expander, content, holder);
    createCookie('theme_admin_sidebar', 'expanded', 3650);
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
  content.style.display = TBL_SHOW;
  holder.style.width = '230px';
  holder.style.paddingLeft = '12px';
  holder.style.paddingRight = '0px';
}

function expander_set_height()
{
  var expander = document.getElementById('sidebar-hide');
  var magic = $dynano('header').Height() + $dynano('pagebar_main').Height();
  var height = getHeight();
  var exheight = height - magic;
  expander.style.height = exheight + 'px';
  expander.style.top = magic + 'px';
  expander_set_pos();
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

function expander_set_pos()
{
  var winheight = getHeight();
  var magic = $dynano('header').Height() + $dynano('pagebar_main').Height();
  var top = getScrollOffset();
  if ( typeof(top) != 'number' )
  {
    return null;
  }
  magic = magic - top;
  if ( magic < 0 )
    magic = 0;
  var bartop = magic + top;
  var barheight = winheight - magic;
  var expander = document.getElementById('sidebar-hide');
  expander.style.top = bartop + 'px';
  expander.style.height = barheight + 'px';
}

addOnloadHook(expander_set_height);
addOnloadHook(expander_onload);
window.onresize = expander_set_height;
window.onscroll = expander_set_pos;

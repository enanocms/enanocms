// Some final stuff - loader routines, etc.

function mdgInnerLoader(e)
{
  if(window.location.hash == '#comments') ajaxComments();
  window.onkeydown=isKeyPressed;
  window.onkeyup=function(e) { isKeyPressed(e); };
  Fat.fade_all();
  fadeInfoBoxes();
  //initTextareas();
  //buildSearchBoxes();
  jBoxInit();
  if(typeof (dbx_set_key) == 'function')
  {
    dbx_set_key();
  }
  initSliders();
  runOnloadHooks(e);
}
var ld;
if(window.onload) ld = window.onload;
else ld = function() {return;};
function enano_init(e) {
  if ( typeof(ld) == 'function' )
  {
    ld(e);
  }
  mdgInnerLoader(e);
}

if ( typeof(KILL_SWITCH) == 'boolean' && !KILL_SWITCH )
{
  window.onload = enano_init;
}


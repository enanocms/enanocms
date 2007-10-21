// Some final stuff - loader routines, etc.

function mdgInnerLoader(e)
{
  jws.startup();
  if(window.location.hash == '#comments') ajaxComments();
  window.onkeydown=isKeyPressed;
  window.onkeyup=function(e) { isKeyPressed(e); };
  Fat.fade_all();
  fadeInfoBoxes();
  //initTextareas();
  buildSearchBoxes();
  jBoxInit();
  if(typeof (dbx_set_key) == 'function')
  {
    dbx_set_key();
  }
  initSliders();
  runOnloadHooks(e);
}
if(window.onload) var ld = window.onload;
else var ld = function() {return;};
function enano_init(e) {
  ld(e);
  mdgInnerLoader(e);
}

if ( typeof(KILL_SWITCH) == 'boolean' && !KILL_SWITCH )
{
  window.onload = enano_init;
}


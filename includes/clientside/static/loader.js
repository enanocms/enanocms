// Some final stuff - loader routines, etc.

var __tmpEnanoStartup9843275;
  
function enanoStartup(e) {
  if ( !e )
  {
    // Delay initting sliders until images are loaded
    if ( typeof(window.onload) == 'function' )
      __tmpEnanoStartup9843275 = window.onload;
    else
      __tmpEnanoStartup9843275 = function(){};
    window.onload = function(e){__tmpEnanoStartup9843275(e);initSliders();};
  }
  else
  {
    initSliders();
  }
}

function mdgInnerLoader(e)
{
  jws.startup();
  enanoStartup(e);
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
  runOnloadHooks(e);
}
if(window.onload) var ld = window.onload;
else var ld = function() {return;};
function enano_init(e) {
  ld(e);
  mdgInnerLoader(e);
}
window.onload = enano_init;


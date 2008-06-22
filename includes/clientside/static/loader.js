// Some final stuff - loader routines, etc.

var onload_complete = false;

function mdgInnerLoader(e)
{
  window.onkeydown = isKeyPressed;
  window.onkeyup = function(e) { isKeyPressed(e); };
  Fat.fade_all();
  fadeInfoBoxes();
  jBoxInit();
  if(typeof (dbx_set_key) == 'function')
  {
    dbx_set_key();
  }
  initSliders();
  runOnloadHooks(e);
}

// if some dumb plugin set an onload function, preserve it 
var ld;
if ( window.onload)
{
  ld = window.onload;
}
else
{
  ld = function() {return;};
}

// Enano's main init function.
function enano_init(e)
{
  if ( typeof(ld) == 'function' )
  {
    ld(e);
  }
  mdgInnerLoader(e);
  
  // we're loaded; set flags to true
  onload_complete = true;
}

// don't init the page if less than IE6
if ( typeof(KILL_SWITCH) == 'boolean' && !KILL_SWITCH )
{
  window.onload = enano_init;
}


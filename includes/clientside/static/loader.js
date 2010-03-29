// Some final stuff - loader routines, etc.

var onload_complete = false;

function mdgInnerLoader(e)
{
	window.onkeydown = isKeyPressed;
	window.onkeyup = function(e) { isKeyPressed(e); };
	
	if ( typeof(dbx_set_key) == 'function')
	{
		dbx_set_key();
	}
	
	runOnloadHooks(e);
}

// Enano's main init function.
function enano_init(e)
{
	mdgInnerLoader(e);
	
	// we're loaded; set flags to true
	console.info('Enano::JS runtime: system init complete');
	onload_complete = true;
}

// don't init the page if less than IE6
if ( typeof(KILL_SWITCH) == 'boolean' && !KILL_SWITCH )
{
	window.onload = enano_init;
}


// Make an HTML element fly in from the top or bottom.
// Includes inertia!

// vB, don't even try. It's GPL like the rest of Enano. I know you're jealous. >:)

var fly_in_cache = new Object();
var FI_TOP = 1;
var FI_BOTTOM = 2;
var FI_IN = 1;
var FI_OUT = 2;
var FI_UP = 1;
var FI_DOWN = 2;
// increase to slow down transitions (for debug)
var FI_MULTIPLIER = 1;

/**
 * You can thank Robert Penner for the math used here. Ported from an ActionScript class.
 * License: Modified BSD license <http://www.robertpenner.com/easing_terms_of_use.html>
 */

// Effects code - don't bother changing these formulas
var Back = {
  easeOut: function(t, b, c, d, s)
  {
    if (s == undefined) s = 1.70158;
    return c * ( ( t=t/d-1 ) * t * ( ( s + 1 ) * t + s) + 1) + b;
  },
  easeIn: function (t, b, c, d, s)
  {
    if (s == undefined) s = 1.70158;
    return c * ( t/=d ) * t * ( ( s + 1 ) * t - s) + b;
  },
  easeInOut: function (t, b, c, d, s)
  {
    if (s == undefined) s = 1.70158; 
    if ((t /= d/2) < 1) 
    {
      return c/2*(t*t*(((s*=(1.525))+1)*t - s)) + b;
    }
    return c/2*((t-=2)*t*(((s*=(1.525))+1)*t + s) + 2) + b;
  }
}

// This should be set to the class name of the effect you want.
var GlideEffect = Back;

// Placeholder functions, to make organization a little easier :-)

function fly_in_top(element, nofade, height_taken_care_of)
{
  return fly_core(element, nofade, FI_TOP, FI_IN, height_taken_care_of);
}

function fly_in_bottom(element, nofade, height_taken_care_of)
{
  return fly_core(element, nofade, FI_BOTTOM, FI_IN, height_taken_care_of);
}

function fly_out_top(element, nofade, height_taken_care_of)
{
  return fly_core(element, nofade, FI_TOP, FI_OUT, height_taken_care_of);
}

function fly_out_bottom(element, nofade, height_taken_care_of)
{
  return fly_core(element, nofade, FI_BOTTOM, FI_OUT, height_taken_care_of);
}

function fly_core(element, nofade, origin, direction, height_taken_care_of)
{
  if ( !element || typeof(element) != 'object' )
    return false;
  
  // force to array
  if ( !element.length )
    element = [ element ];
  
  // target dimensions
  var top, left;
  // initial dimensions
  var topi, lefti;
  // current dimensions
  var topc, leftc;
  // screen dimensions
  var w = getWidth();
  var h = getHeight();
  var y = parseInt ( getScrollOffset() );
  // temp vars
  var dim, off, diff, dist, ratio, opac_factor;
  // setup element
  for ( var i = 0; i < element.length; i++ )
    element[i].style.position = 'absolute';
  
  dim = [ $dynano(element[0]).Height(), $dynano(element[0]).Width() ];
  off = [ $dynano(element[0]).Top(), $dynano(element[0]).Left() ];
  
  if ( height_taken_care_of )
  {
    top = off[0];
    left = off[1];
  }
  else
  {
    top  = Math.round(( h / 2 ) - ( dim[0] / 2 )) + y; // - ( h / 4 ));
    left = Math.round(( w / 2 ) - ( dim[1] / 2 ));
  }
  
  // you can change this around to get it to fly in from corners or be on the left/right side
  lefti = left;
  
  // calculate first frame Y position
  if ( origin == FI_TOP && direction == FI_IN )
  {
    topi = 0 - dim[0] + y;
  }
  else if ( origin == FI_TOP && direction == FI_OUT )
  {
    topi = top;
    top = 0 - dim[0] + y;
  }
  else if ( origin == FI_BOTTOM && direction == FI_IN )
  {
    topi = h + y;
  }
  else if ( origin == FI_BOTTOM && direction == FI_OUT )
  {
    topi = top;
    top = h + y;
  }
  
  var abs_dir = ( ( origin == FI_TOP && direction == FI_IN ) || ( origin == FI_BOTTOM && direction == FI_OUT ) ) ? FI_DOWN : FI_UP;
  
  var diff_top = top - topi;
  var diff_left = left - lefti;
  
  var frames = 100;
  var timeout = 0;
  var timerstep = 8 * FI_MULTIPLIER;
  
  // cache element so it can be changed from within setTimeout()
  var rand_seed = Math.floor(Math.random() * 1000000);
  fly_in_cache[rand_seed] = element;
  
  for ( var i = 0; i < frames; i++ )
  {
    topc = GlideEffect.easeInOut(i, topi, diff_top, frames);
    leftc = GlideEffect.easeInOut(i, lefti, diff_left, frames);
    
    var code = 'var element = fly_in_cache[' + rand_seed + '];' + "\n";
    code +=    'for ( var i = 0; i < element.length; i++ )' + "\n";
    code +=    '{' + "\n";
    code +=    '  element[i].style.top = "' + topc + 'px";' + "\n";
    if ( !height_taken_care_of )
      code +=  '  element[i].style.left = "' + leftc + 'px";' + "\n";
    code +=    '}';
    
    setTimeout(code, timeout);
    
    timeout += timerstep;
    
    var ratio = i / frames;
    
    if ( !nofade )
    {
      // handle fade
      var opac_factor = ratio * 100;
      if ( direction == FI_OUT )
        opac_factor = 100 - opac_factor;
      
      var code = 'var element = fly_in_cache[' + rand_seed + '];' + "\n";
      code +=    'for ( var i = 0; i < element.length; i++ )' + "\n";
      code +=    '{' + "\n";
      code +=    '  domObjChangeOpac(' + opac_factor + ', element[i]);' + "\n";
      code +=    '}';
      
      setTimeout(code, timeout);
    }
    
  }
  
  // old framestepper code removed from here in Loch Ness
  
  timeout += timerstep;
  return timeout;
}

function abs(i)
{
  if ( isNaN(i) )
    return i;
  return ( i < 0 ) ? ( 0 - i ) : i;
}


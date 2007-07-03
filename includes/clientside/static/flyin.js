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
  element.style.position = 'absolute';
  
  dim = [ $(element).Height(), $(element).Width() ];
  off = [ $(element).Top(), $(element).Left() ];
  
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
  var timerstep = 8;
  
  // cache element so it can be changed from within setTimeout()
  var rand_seed = Math.floor(Math.random() * 1000000);
  fly_in_cache[rand_seed] = element;
  
  for ( var i = 0; i < frames; i++ )
  {
    topc = GlideEffect.easeInOut(i, topi, diff_top, frames);
    leftc = GlideEffect.easeInOut(i, lefti, diff_left, frames);
    setTimeout('var o = fly_in_cache['+rand_seed+']; o.style.top=\''+topc+'px\'; o.style.left=\''+leftc+'px\';', timeout);
    timeout += timerstep;
    
    var ratio = i / frames;
    
    if ( !nofade )
    {
      // handle fade
      var opac_factor = ratio * 100;
      if ( direction == FI_OUT )
        opac_factor = 100 - opac_factor;
      setTimeout('var o = fly_in_cache['+rand_seed+']; domObjChangeOpac('+opac_factor+', o);', timeout);
    }
    
  }
  
  /*
   * Framestepper parameters
   * /
  
  // starting value for inertia
  var inertiabase = 1;
  // increment for inertia, or 0 to disable inertia effects
  var inertiainc  = 1;
  // when the progress reaches this %, deceleration is activated
  var divider = 0.666667;
  // multiplier for deceleration, setting this above 2 can cause some weird slowdown effects
  var decelerate  = 2; // 1 / divider; // reciprocal of the divider
  
  /*
   * Timer parameters
   * /
  
  // how long animation start is delayed, you want this at 0
  var timer = 0;
  // frame ttl
  var timestep = 12;
  // sanity check
  var frames = 0;
  
  // cache element so it can be changed from within setTimeout()
  var rand_seed = Math.floor(Math.random() * 1000000);
  fly_in_cache[rand_seed] = element;
  
  // set element left pos, you can comment this out to preserve left position
  element.style.left = left + 'px';
  
  // total distance to be traveled
  dist = abs(top - topi);
  
  // animation loop
  
  while ( true )
  {
    // used for a sanity check
    frames++;
    
    // time until this frame should be executed
    timer += timestep;
    
    // math stuff
    // how far we are along in animation...
    diff = abs(top - topi);
    // ...in %
    ratio = abs( 1 - ( diff / dist ) );
    // decelerate if we're more than 2/3 of the way there
    if ( ratio < divider )
      inertiabase += inertiainc;
    else
      inertiabase -= ( inertiainc * decelerate );
    
    // if the deceleration factor is anywhere above 1 then technically that can cause an infinite loop
    // so leave this in there unless decelerate is set to 1
    if ( inertiabase < 1 )
      inertiabase = 1;
    
    // uncomment to disable inertia
    // inertiabase = 3;
    
    // figure out frame Y position
    topi = ( abs_dir == FI_UP ) ? topi - inertiabase : topi + inertiabase;
    if ( ( abs_dir == FI_DOWN && topi > top ) || ( abs_dir == FI_UP && top > topi ) )
      topi = top;
    
    // tell the browser to do it
    setTimeout('var o = fly_in_cache['+rand_seed+']; o.style.top=\''+topi+'px\';', timer);
    if ( !nofade )
    {
      // handle fade
      opac_factor = ratio * 100;
      if ( direction == FI_OUT )
        opac_factor = 100 - opac_factor;
      setTimeout('var o = fly_in_cache['+rand_seed+']; domObjChangeOpac('+opac_factor+', o);', timer);
    }
    
    // if we're done or if our sanity check failed then break out of the loop
    if ( ( abs_dir == FI_DOWN && topi >= top ) || ( abs_dir == FI_UP && top >= topi ) || frames > 1000 )
      break;
  }
  
  timer += timestep;
  setTimeout('delete(fly_in_cache['+rand_seed+']);', timer);
  return timer;
  */
  timeout += timerstep;
  return timeout;
}

function abs(i)
{
  if ( isNaN(i) )
    return i;
  return ( i < 0 ) ? ( 0 - i ) : i;
}


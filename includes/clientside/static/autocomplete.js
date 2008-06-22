/*
 * Auto-completing page/username fields
 * NOTE: A more efficient version of the username field is used for Mozilla browsers. The updated code is in autofill.js.
 */

//
// **** 1.1.4: DEPRECATED ****
// Replaced with Spry-based mechanism.
//

function get_parent_form(o)
{
  if ( !o.parentNode )
    return false;
  if ( o.tagName == 'FORM' )
    return o;
  var p = o.parentNode;
  while(true)
  {
    if ( p.tagName == 'FORM' )
      return p;
    else if ( !p )
      return false;
    else
      p = p.parentNode;
  }
}


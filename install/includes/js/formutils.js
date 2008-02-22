/**
 * Images used for form field validation
 * @var string img_bad: Shown on field validation failure
 * @var string img_good: Shown on field validation success
 * @var string img_neu: Shown when a field's value matches known good regexp but still needs testing (e.g. DB info)
 */

var img_bad = '../images/checkbad.png';
var img_good = '../images/check.png';
var img_neu = '../images/checkunk.png';

/**
 * Highlights the background of the next-up <tr> tag.
 * @param object Form field
 */

function set_focus(item)
{
  var hint_id = ( item.type == 'radio' ) ? 'hint_' + item.name + '_' + item.value : 'hint_' + item.name;
  if ( document.getElementById(hint_id) )
  {
    var el = document.getElementById(hint_id);
    el.style.zIndex = String(getHighestZ() + 2);
    domObjChangeOpac(0, el);
    el.style.display = 'block';
    domOpacity(el, 0, 100, 400);
  }
  item = getParentTR(item);
  if ( item.tagName == 'TR' )
  {
    item.style.backgroundColor = '#FFFFE0';
  }
}

/**
 * Clears the background of the next-up <tr> tag.
 * @param object Form field
 */

function clear_focus(item)
{
  var hint_id = ( item.type == 'radio' ) ? 'hint_' + item.name + '_' + item.value : 'hint_' + item.name;
  if ( document.getElementById(hint_id) )
  {
    var el = document.getElementById(hint_id);
    // el.style.display = 'none';
    domOpacity(el, 100, 0, 200);
    setTimeout(function()
      {
        el.style.display = 'none';
      }, 250);
  }
  item = getParentTR(item);
  if ( item.tagName == 'TR' )
  {
    if ( IE )
    {
      item.style.backgroundColor = 'transparent';
    }
    else
    {
      item.style.backgroundColor = null;
    }
  }
}

function getParentTR(item)
{
  var tagName = item.tagName;
  while ( tagName != 'TR' && tagName != null )
  {
    item = item.parentNode;
    tagName = item.tagName;
  }
  if ( tagName == 'TR' && item.className != 'nohighlight' )
  {
    return item;
  }
  return null;
}

function init_hint(input, hint)
{
  hint.className = 'fieldtip_js';
  setTimeout(function()
    {
      if ( input.type == 'radio' )
      {
        var tr = getParentTR(input).parentNode.parentNode.parentNode;
        var span_width = $(tr).Width() - 24;
      }
      else
      {
        var span_width = $(input).Width() - 24;
      }
      var span_top = $(input).Top() + $(input).Height();
      var span_left = $(input).Left();
      hint.style.top = span_top + 'px';
      hint.style.left = span_left + 'px';
      hint.style.width = span_width + 'px';
      hint.style.display = 'none';
    }, 100);
}

var set_inputs_to_highlight = function()
{
  var inputs = document.getElementsByTagName('input');
  for ( var i = 0; i < inputs.length; i++ )
  {
    // Highlighting
    var tr = getParentTR(inputs[i]);
    if ( tr )
    {
      inputs[i].onfocus = function()
      {
        set_focus(this);
      }
      inputs[i].onblur = function()
      {
        clear_focus(this);
      }
    }
    // Hints
    var hint_id = ( inputs[i].type == 'radio' ) ? 'hint_' + inputs[i].name + '_' + inputs[i].value : 'hint_' + inputs[i].name;
    if ( document.getElementById(hint_id) )
    {
      var el = document.getElementById(hint_id);
      if ( el.tagName == 'SPAN' )
      {
        init_hint(inputs[i], el);
      }
    }
  }
}

addOnloadHook(set_inputs_to_highlight);

function install_set_ajax_loading()
{
  var base = document.getElementById('enano-body');
  var hider = document.createElement('div');
  hider.style.position = 'absolute';
  hider.style.backgroundColor = '#FFFFFF';
  hider.style.top = $(base).Top() + 'px';
  hider.style.left = $(base).Left() + 'px';
  hider.style.width = $(base).Width() + 'px';
  hider.style.height = $(base).Height() + 'px';
  hider.style.backgroundPosition = 'center center';
  hider.style.backgroundImage = 'url(../images/loading-big.gif)';
  hider.style.backgroundRepeat = 'no-repeat';
  hider.id = 'ajax_loader';
  domObjChangeOpac(0, hider);
  var body = document.getElementsByTagName('body')[0];
  body.appendChild(hider);
  opacity('ajax_loader', 0, 70, 750);
}

function install_unset_ajax_loading()
{
  if ( document.getElementById('ajax_loader') )
  {
    opacity('ajax_loader', 70, 0, 750);
    setTimeout(function()
      {
        var body = document.getElementsByTagName('body')[0];
        body.removeChild(document.getElementById('ajax_loader'));
      }, 1000);
  }
}

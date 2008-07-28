// Tabs on userpage

var userpage_blocks = [];

var userpage_onload = function()
{
  var wrapper = document.getElementById('userpage_wrap');
  var links = document.getElementById('userpage_links');
  
  wrapper.className = 'userpage_wrap';
  links.className = 'userpage_links';
  
  var blocks = wrapper.getElementsByTagName('div');
  var first_block = false;
  for ( var i = 0; i < blocks.length; i++ )
  {
    var block = blocks[i];
    if ( /^tab:/.test(block.id) )
    {
      $(block).addClass('userpage_block');
      var block_id = block.id.substr(4);
      userpage_blocks.push(block_id);
      if ( !first_block )
      {
        // this is the first block on the page, memorize it
        first_block = block_id;
      }
    }
  }
  // init links
  var as = links.getElementsByTagName('a');
  for ( var i = 0; i < as.length; i++ )
  {
    var a = as[i];
    if ( a.href.indexOf('#') > -1 )
    {
      var hash = a.href.substr(a.href.indexOf('#'));
      var blockid = hash.substr(5);
      a.blockid = blockid;
      a.onclick = function()
      {
        userpage_select_block(this.blockid);
        return false;
      }
      a.id = 'userpage_blocklink_' + blockid;
    }
  }
  if ( $_REQUEST['tab'] )
  {
    userpage_select_block($_REQUEST['tab'], true);
  }
  else
  {
    userpage_select_block(first_block, true);
  }
}

addOnloadHook(userpage_onload);

/**
 * Select (show) the specified block on the userpage.
 * @param string block name
 * @param bool If true, omits transition effects.
 */

function userpage_select_block(block, nofade)
{
  // memorize existing scroll position, reset the hash, then scroll back to where we were
  // a little hackish and might cause a flash, but it's better than hiding the tabs on each click
  var currentScroll = getScrollOffset();
  
  var current_block = false;
  nofade = true;
  for ( var i = 0; i < userpage_blocks.length; i++ )
  {
    var div = document.getElementById('tab:' + userpage_blocks[i]);
    if ( div )
    {
      if ( div.style.display != 'none' )
      {
        current_block = userpage_blocks[i];
        if ( nofade || aclDisableTransitionFX )
        {
          div.style.display = 'none';
        }
      }
    }
    var a = document.getElementById('userpage_blocklink_' + userpage_blocks[i]);
    if ( a )
    {
      if ( $(a.parentNode).hasClass('userpage_tab_active') )
      {
        $(a.parentNode).rmClass('userpage_tab_active');
      }
    }
  }
  if ( nofade || !current_block || aclDisableTransitionFX )
  {
    var div = document.getElementById('tab:' + block);
    div.style.display = 'block';
  }
  /*
  else
  {
    // do this in a slightly fancier fashion
    load_component('SpryEffects');
    (new Spry.Effect.Blind('tab:' + current_block, { from: '100%', to: '0%', finish: function()
        {
          (new Spry.Effect.Blind('tab:' + block, { from: '0%', to: '100%' })).start();
        }
      })).start();
  }
  */
  var a = document.getElementById('userpage_blocklink_' + block);
  $(a.parentNode).addClass('userpage_tab_active');
  
  window.location.hash = 'tab:' + block;
  setScrollOffset(currentScroll);
}

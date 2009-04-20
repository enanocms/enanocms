addOnloadHook(function()
  {
    load_component(['jquery', 'jquery-ui']);
    $('.sbedit-column').sortable({
      handle: '.sbedit-handle',
      connectWith: '.sbedit-column',
      stop: function()
      {
        ajaxUpdateSidebarOrder();
      }
    });
  });

function serialize_sidebar()
{
  var columns = {};
  var i = 0;
  $('.sbedit-column').each(function(i, e)
    {
      var arr = $(e).sortable('toArray');
      for ( var j = 0; j < arr.length; j++ )
        arr[j] = parseInt(arr[j].replace(/^block:/, ''));
      
      i++;
      columns[i] = arr;
    });
  return toJSONString(columns);
}

function sbedit_open_editor(a)
{
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    load_component('login');
    ajaxDynamicReauth(function(sid)
      {
        sbedit_open_editor(a);
      }, USER_LEVEL_ADMIN);
    return false;
  }
  load_component(['fadefilter', 'l10n']);
  var shade = darken(true, 50, 'sbedit-shade');
  $(shade).css('z-index', 0);
  var parent = sbedit_get_parent(a);
  var offset = $(parent).offset();
  var top = (( getHeight() ) / 2) - 200 + getScrollOffset();
  var box = $(parent)
    .clone()
    .empty()
    .attr('id', 'sb_blockedit')
    .addClass('sbedit-float')
    .css('height', $(parent).height())
    .css('top', offset.top)
    .css('left', offset.left)
    .appendTo('body');
  var item_id = parseInt($(parent).attr('id').replace(/^block:/, ''));
  
  $(box)
    .animate({ width: 500, height: 400, top: top, left: (getWidth() / 2) - 250 }, 400, function()
      {
        var whitey = whiteOutElement(this);
        $(this).append('<textarea style="width: 98%; height: 360px; margin: 0 auto; display: block;" rows="20" cols="80"></textarea>');
        $(this).append('<p style="text-align: center;"><a href="#" onclick="sbedit_edit_save(this); return false;">' + $lang.get('etc_save_changes') + '</a> | <a href="#" onclick="sbedit_edit_cancel(this); return false;">' + $lang.get('etc_cancel') + '</a></p>');
        $.get(makeUrlNS('Special', 'EditSidebar', 'action=getsource&noheaders&id=' + item_id), {}, function(response, statustext)
          {
            $('textarea', box).val(response);
            $(whitey).remove();
            
            $(box).attr('enano:item_id', item_id);
          }, 'html');
      })
    .get(0);
}

function sbedit_edit_save(a)
{
  var box = a.parentNode.parentNode;
  var parent = document.getElementById('block:' + $(box).attr('enano:item_id'));
  var whitey = whiteOutElement(box);
  $.post(makeUrlNS('Special', 'EditSidebar', 'noheaders&action=save&id=' + $(box).attr('enano:item_id')), { content: $('textarea', box).attr('value') }, function(response, statustext)
    {
      whiteOutReportSuccess(whitey);
      setTimeout(function()
        {
          sbedit_close_editor(parent, box);
        }, 1250);
    }, 'html');
}

function sbedit_edit_cancel(a)
{
  var box = a.parentNode.parentNode;
  var parent = document.getElementById('block:' + $(box).attr('enano:item_id'));
  
  sbedit_close_editor(parent, box);
}

function sbedit_close_editor(parent, box)
{
  if ( !parent )
  {
    console.warn('Failed to get DOM object for parent, skipping transition effect');
  }
  
  if ( jQuery.fx.off || !parent )
  {
    enlighten(true, 'sbedit-shade');
    $('body').get(0).removeChild(box);
    return true;
  }
  
  var offset = $(parent).offset();
  $(box).empty().animate(
    {
      width:  $(parent).width(),
      height: $(parent).height(),
      top:    offset.top,
      left:   offset.left,
    }, 400, function()
    {
      $(this).css('background-color', '#f70').fadeOut(1000, function() { $(this).remove(); });
      enlighten(true, 'sbedit-shade');
    });
}

function sbedit_delete_block(a)
{
  var parent = sbedit_get_parent(a);
  load_component(['messagebox', 'fadefilter', 'flyin', 'l10n']);
  var mp = miniPromptMessage({
      title: $lang.get('sbedit_msg_delete_confirm_title'),
      message: $lang.get('sbedit_msg_delete_confirm_body'),
      buttons: [
        {
          text: $lang.get('sbedit_btn_delete_confirm'),
          color: 'red',
          onclick: function()
          {
            var mp = miniPromptGetParent(this);
            sbedit_delete_block_s2(mp.target_block);
            miniPromptDestroy(this);
            return false;
          },
          style: {
            fontWeight: 'bold'
          }
        },
        {
          text: $lang.get('etc_cancel'),
          onclick: function()
          {
            miniPromptDestroy(this);
            return false;
          }
        }
      ]
    });
  mp.target_block = parent;
}

function sbedit_delete_block_s2(box)
{
  var parent = box;
  var id = parseInt($(parent).attr('id').replace(/^block:/, ''));
  var whitey = whiteOutElement(parent);
  
  $.get(makeUrlNS('Special', 'EditSidebar', 'action=delete&ajax=true&noheaders&id=' + id), function(response, statustext)
    {
      if ( response == 'GOOD' )
      {
        whiteOutReportSuccess(whitey);
        setTimeout(function()
          {
            $(parent)
            .hide('blind', { duration: 500 }, function()
              {
                $(this).remove();
                ajaxUpdateSidebarOrder();
              });
          }, 1250);
      }
      else
      {
        whiteOutReportFailure(whitey);
        alert(response);
      }
    }, 'html');
}

function sbedit_rename_block(a)
{
  var parent = sbedit_get_parent(a);
  $('div.sbedit-handle > span', parent).hide();
  var input = $('div.sbedit-handle > input', parent).show().focus().select().keyup(function(e)
    {
      switch(e.keyCode)
      {
        case 13:
          // enter
          var whitey = whiteOutElement(this.parentNode);
          var me = this;
          var id = parseInt($(parent).attr('id').replace(/^block:/, ''));
          $.post(makeUrlNS('Special', 'EditSidebar', 'ajax&noheaders&action=rename&id='+id), { newname: $(this).attr('value') }, function(response, statustext)
            {
              if ( response == 'GOOD' )
              {
                whiteOutReportSuccess(whitey);
                setTimeout(function()
                  {
                    $(me).hide();
                    $('span', me.parentNode).show().text(me.value);
                  }, 1250);
              }
              else
              {
                alert(response);
                whiteOutReportFailure(whitey);
              }
            }, 'html');
          break;
        case 27:
          // escape
          this.value = this.origvalue;
          $(this).hide();
          $('span', this.parentNode).show();
          break;
      }
    }).get(0);
  input.origvalue = input.value;
}

function sbedit_disenable_block(a)
{
  var parent = sbedit_get_parent(a);
  var whitey = whiteOutElement(parent);
  $.get(makeUrlNS('Special', 'EditSidebar', 'action=disenable&ajax=true&noheaders&id=' + parseInt($(parent).attr('id').replace(/^block:/, ''))), {}, function(response, statustext)
    {
      if ( response == 'GOOD' )
      {
        whiteOutReportSuccess(whitey);
        $(parent).toggleClass('disabled');
      }
      else
      {
        whiteOutReportFailure(whitey);
        alert(response);
      }
    }, 'html');
}

function sbedit_get_parent(a)
{
  var o = a.parentNode;
  while ( !$(o).hasClass('sbedit-block') )
    o = o.parentNode;
  
  return o;
}

function ajaxUpdateSidebarOrder()
{
  setAjaxLoading();
  var ser = serialize_sidebar();
  $.post(makeUrlNS('Special', 'EditSidebar', 'update_order'), { order: ser }, function(response, statustext)
    {
      var msg = document.createElement('div');
      $(msg)
        .addClass('info-box-mini')
        .text('Sidebar order saved.')
        .css('position', 'fixed')
        .css('bottom', 1)
        .appendTo('body')
        .css('left', ( getWidth() / 2 ) - ( $(msg).width() / 2 ));
      setTimeout(function()
        {
          $(msg).fadeOut(500, function() { $(this).remove(); });
        }, 1000);
      unsetAjaxLoading();
    }, 'json');
}


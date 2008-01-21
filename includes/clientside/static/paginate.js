// Javascript pagination control

/**
 * Paginates an array of data.
 * @param array List of objects to render
 * @param function Function called on each member of the array to render, should take an array member and set this.html to a string
 * @param int Optional. Object to start at, defaults to 0.
 * @param int Optional. Objects per page, defaults to 10
 * @param object Variable that will be passed to the renderer callback
 */
 
var pagin_objects = new Object();

function paginator(data, callback, offset, perpage, passer)
{
  if ( !perpage || typeof(perpage) != 'number' || ( typeof(perpage) == 'number' && perpage < 1 ) )
  {
    this.perpage = 10;
  }
  else
  {
    this.perpage = perpage;
  }
  if ( typeof(offset) != 'number' )
    this.offset = 0;
  else
    this.offset = offset;
  if ( typeof(passer) != 'undefined' )
    this.passer = passer;
  else
    this.passer = false;
  this.num_pages = Math.ceil(data.length / perpage );
  this.random_id = 'autopagin_' + Math.floor(Math.random() * 1000000);
  this._build_control = _build_paginator;
  if ( this.num_pages > 1 )
  {
    var pg_control = '<div class="'+this.random_id+'_control">'+this._build_control(0)+'</div>';
  }
  else
  {
    var pg_control = '';
  }
  this.html = pg_control;
  var i = 0;
  while ( i < data.length )
  {
    if ( i % this.perpage == 0 )
    {
      if ( i > 0 )
        this.html += '</div>';
      var display = ( ( i * this.perpage ) == this.offset ) ? '' : 'display: none;';
      var thispage = Math.floor(i / this.perpage);
      this.html += '<div id="' + this.random_id + '_' + thispage + '" style="' + display + '">';
    }
    this.html += callback(data[i], this.passer);
    i++;
  }
  this.html += '</div>';
  this.html += pg_control;
  pagin_objects[this.random_id] = this;
}

/**
 * Yet another demonstration of the fact that with the right tools, any amount of Javascript can be ported from PHP.
 * @access private
 */

function _build_paginator(this_page)
{
  var div_styling = ( IE ) ? 'width: 1px; margin: 10px auto 10px 0;' : 'display: table; margin: 10px 0 0 auto;';
  var begin = '<div class="tblholder" style="'+div_styling+'"><table border="0" cellspacing="1" cellpadding="4"><tr><th>' + $lang.get('paginate_lbl_page') + '</th>';
  var block = '<td class="row1" style="text-align: center; white-space: nowrap;">{LINK}</td>';
  var end = '</tr></table></div>';
  var blk = new templateParser(block);
  var inner = '';
  var cls = 'row2';
  
  if ( this_page > 0 )
  {
    var url = '#page_'+(this_page);
    var link = "<a href=\""+url+"\" onclick=\"jspaginator_goto('"+this.random_id+"', "+(this_page-1)+"); return false;\" style='text-decoration: none;'>&laquo; " + $lang.get('paginate_btn_prev') + "</a>";
    cls = ( cls == 'row1' ) ? 'row2' : 'row1';
    blk.assign_vars({
        CLASS: cls,
        LINK: link
      });
    inner += blk.run();
  }
  if ( this.num_pages < 5 )
  {
    for ( var i = 0; i < this.num_pages; i++ )
    {
      cls = ( cls == 'row1' ) ? 'row2' : 'row1';
      var j = i + 1;
      var url = '#page_'+j;
      var link = ( i == this_page ) ? "<b>"+j+"</b>" : "<a href=\""+url+"\" onclick=\"jspaginator_goto('"+this.random_id+"', "+i+"); return false;\" style='text-decoration: none;'>"+j+"</a>";
      blk.assign_vars({
          CLASS: cls,
          LINK: link
        });
      inner += blk.run();
    }
  }
  else
  {
    if ( this_page + 5 > this.num_pages )
    {
      var list = new Array();
      var tp = this_page;                    // The vectors below used to be 3, 2, and 1
      if ( this_page + 0 == this.num_pages ) tp = tp - 2;
      if ( this_page + 1 == this.num_pages ) tp = tp - 1;
      if ( this_page + 2 == this.num_pages ) tp = tp - 0;
      for ( var i = tp - 1; i <= tp + 1; i++ )
      {
        list.push(i);
      }
    }
    else
    {
      var list = new Array();
      var current = this_page;
      var lower = ( current < 3 ) ? 1 : current - 1;
      for ( var i = 0; i < 3; i++ )
      {
        list.push(lower + i);
      }
    }
    var url = '#page_1';
    var link = ( 0 == this_page ) ? "<b>" + $lang.get('paginate_btn_first') + "</b>" : "<a href=\""+url+"\" onclick=\"jspaginator_goto('"+this.random_id+"', 0); return false;\" style='text-decoration: none;'>&laquo; " + $lang.get('paginate_btn_first') + "</a>";
    blk.assign_vars({
        CLASS: cls,
        LINK: link
      });
    inner += blk.run();

    // if ( !in_array(1, $list) )
    // {
    //   $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    //   $blk->assign_vars(array('CLASS'=>$cls,'LINK'=>'...'));
    //   $inner .= $blk->run();
    // }

    for ( var k in list )
    {
      var i = list[k];
      if ( i == this.num_pages )
        break;
      cls = ( cls == 'row1' ) ? 'row2' : 'row1';
      var j = i + 1;
      var url = '#page_'+j;
      var link = ( i == this_page ) ? "<b>"+j+"</b>" : "<a href=\""+url+"\" onclick=\"jspaginator_goto('"+this.random_id+"', "+i+"); return false;\" style='text-decoration: none;'>"+j+"</a>";
      blk.assign_vars({
          CLASS: cls,
          LINK: link
        });
      inner += blk.run();
    }

    if ( this_page < this.num_pages )
    {
      // $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      // $blk->assign_vars(array('CLASS'=>$cls,'LINK'=>'...'));
      // $inner .= $blk->run();

      cls = ( cls == 'row1' ) ? 'row2' : 'row1';
      var url = '#page_' + String( this.num_pages-1 );
      var link = ( ( this.num_pages - 1 ) == this_page ) ? "<b>" + $lang.get('paginate_btn_last') + "</b>" : "<a href=\""+url+"\" onclick=\"jspaginator_goto('"+this.random_id+"', "+(this.num_pages-1)+"); return false;\" style='text-decoration: none;'>" + $lang.get('paginate_btn_last') + " &raquo;</a>";
      blk.assign_vars({
          CLASS: cls,
          LINK: link
        });
      inner += blk.run();
    }

  }

  if ( this_page < ( this.num_pages - 1 ) )
  {
    var url = '#page_' + String(this_page + 2);
    var link = "<a href=\""+url+"\" onclick=\"jspaginator_goto('"+this.random_id+"', "+(this_page+1)+"); return false;\" style='text-decoration: none;'>" + $lang.get('paginate_btn_next') + " &raquo;</a>";
    cls = ( cls == 'row1' ) ? 'row2' : 'row1';
    blk.assign_vars({
          CLASS: cls,
          LINK: link
        });
      inner += blk.run();
  }

  inner += '<td class="row2" style="cursor: pointer;" onclick="paginator_goto(this, '+this_page+', '+this.num_pages+', '+this.perpage+', {js: true, random_id: \''+this.random_id+'\'});">&darr;</td>';

  var paginator = "\n"+begin+inner+end+"\n";
  return paginator;
  
}

var __paginateLock = false;

function jspaginator_goto(pagin_id, jump_to)
{
  if ( __paginateLock )
    return false;
  var theobj = pagin_objects[pagin_id];
  var current_div = false;
  var new_div = false;
  for ( var i = 0; i < theobj.num_pages; i++ )
  {
    var thediv = document.getElementById(pagin_id + '_' + i);
    if ( !thediv )
    {
      // if ( window.console )
        // window.console.error('jspaginator_goto(): got a bad DOM object in loop');
      return false;
    }
    // window.console.debug("Div "+i+' of '+(theobj.num_pages-1)+': ', thediv);
    if ( thediv.style.display != 'none' )
      current_div = thediv;
    else if ( i == jump_to )
      new_div = thediv;
  }
  
  if ( !new_div )
  {
    // if ( window.console )
      // window.console.error('jspaginator_goto(): didn\'t get new div');
    return false;
  }
  if ( !current_div )
  {
    // if ( window.console )
      // window.console.error('jspaginator_goto(): didn\'t get current div');
    return false;
  }
  
  // window.console.debug(current_div);
  // window.console.debug(new_div);
  
  // White-out the old div and fade in the new one
  
  if ( IE || is_Safari )
  {
    current_div.style.display = 'none';
    new_div.style.display = 'block';
  }
  else
  {
    __paginateLock = true;
    var fade_time = 375;
    var code = 'var old = \'' + current_div.id + '\';';
    code    += 'var newer = \'' + new_div.id + '\';';
    code    += 'document.getElementById(old).style.display = "none";';
    code    += 'changeOpac(0, newer);';
    code    += 'document.getElementById(newer).style.display = "block";';
    code    += 'opacity(newer, 0, 100, '+fade_time+');';
    code    += '__paginateLock = false;';
    // if ( window.console )
      // window.console.debug('metacode for fader: ', code);
    opacity(current_div.id, 100, 0, fade_time);
    setTimeout(code, (fade_time + 50));
  }
  
  
  var pg_control = theobj._build_control(jump_to);
  var divs = getElementsByClassName(document, 'div', pagin_id + '_control');
  for ( var i = 0; i < divs.length; i++ )
  {
    divs[i].innerHTML = pg_control;
  }
}

function paginator_goto(parentobj, this_page, num_pages, perpage, url_string)
{
  var height = $(parentobj).Height();
  var width  = $(parentobj).Width();
  var left   = $(parentobj).Left();
  var top    = $(parentobj).Top();
  var left_pos = left + width ;
  var top_pos = height + top;
  var div = document.createElement('div');
  div.style.position = 'absolute';
  div.style.top = top_pos + 'px';
  div.className = 'question-box';
  div.style.margin = '1px 0 0 2px';
  var vtmp = 'input_' + Math.floor(Math.random() * 1000000);
  var regex = new RegExp('\"', 'g');
  var submit_target = ( typeof(url_string) == 'object' ) ? ( toJSONString(url_string) ).replace(regex, '\'') : 'unescape(\'' + escape(url_string) + '\')';
  var onclick = 'paginator_submit(this, '+num_pages+', '+perpage+', '+submit_target+'); return false;';
  div.innerHTML = $lang.get('paginate_lbl_goto_page') + '<br /><input type="text" size="2" style="padding: 1px; font-size: 8pt;" value="'+(parseInt(this_page)+1)+'" id="'+vtmp+'" />&emsp;<a href="#" onclick="'+onclick+'" style="font-size: 14pt; text-decoration: none;">&raquo;</a>&emsp;<a href="#" onclick="fly_out_top(this.parentNode, false, true); return false;" style="font-size: 14pt; text-decoration: none;">&times;</a>';
  
  var body = document.getElementsByTagName('body')[0];
  domObjChangeOpac(0, div);
  
  body.appendChild(div);
  
  document.getElementById(vtmp).onkeypress = function(e)
    {
      if ( e.keyCode == 13 )
        this.nextSibling.nextSibling.onclick();
    };
  document.getElementById(vtmp).focus();
  
  // fade the div
  /*
  if(!div.id) div.id = 'autofade_'+Math.floor(Math.random() * 100000);
  var from = '#33FF33';
  Fat.fade_element(div.id,30,2000,from,Fat.get_bgcolor(div.id));
  */
  
  fly_in_bottom(div, false, true);
  
  var divh = $(div).Width();
  left_pos = left_pos - divh;
  div.style.left = left_pos + 'px';
}

function paginator_submit(obj, max, perpage, formatstring)
{
  var userinput = obj.previousSibling.previousSibling.value;
  userinput = parseInt(userinput);
  var offset = ( userinput - 1 ) * perpage;
  if ( userinput > max || isNaN(userinput) || userinput < 1 )
  {
    new messagebox(MB_OK|MB_ICONSTOP, $lang.get('paginate_err_bad_page_title'), $lang.get('paginate_err_bad_page_body', { max: max }));
    return false;
  }
  if ( typeof(formatstring) == 'object' )
  {
    fly_out_top(obj.parentNode, false, true);
    jspaginator_goto(formatstring.random_id, ( offset / perpage ));
  }
  else
  {
    var url = sprintf(formatstring, String(offset));
    fly_out_top(obj.parentNode, false, true);
    window.location = url;
  }
}


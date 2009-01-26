// Comments

var comment_template = false;
var comment_render_track = 0;

window.ajaxComments = function(parms)
{
  load_component(['l10n', 'paginate', 'template-compiler', 'toolbar', 'flyin']);
  setAjaxLoading();
  var pid = strToPageID(title);
  if(!parms)
  {
    var parms = {
      'mode' : 'fetch'
    };
  }
  parms.page_id = pid[0];
  parms.namespace = pid[1];
  if(comment_template)
    parms.have_template = true;
  parms = ajaxEscape(toJSONString(parms));
  ajaxPost(stdAjaxPrefix+'&_mode=comments', 'data=' + parms, function(ajax) {
    if ( ajax.readyState == 4 && ajax.status == 200 ) {
      unsetAjaxLoading();
      selectButtonMajor('discussion');
      unselectAllButtonsMinor();
      // IE compatibility - doing ajax.responseText.substr() doesn't work
      var rsptxt = ajax.responseText + '';
      if ( rsptxt.substr(0, 1) != '{' )
      {
        document.getElementById('ajaxEditContainer').innerHTML = '<p>Comment system Javascript runtime: invalid JSON response from server, response text:</p><pre>' + ajax.responseText + '</pre>';
        return false;
      }
      var response = parseJSON(ajax.responseText);
      switch(response.mode)
      {
        case 'fetch':
          document.getElementById('ajaxEditContainer').innerHTML = '<div class="wait-box">Rendering '+response.count_total+' comments...</div>';
          if(response.template)
            comment_template = response.template;
          setAjaxLoading();
          renderComments(response);
          unsetAjaxLoading();
          break;
        case 'redraw':
          redrawComment(response);
          break;
        case 'annihilate':
          annihiliateComment(response.id);
          break;
        case 'materialize':
          alert($lang.get('comment_msg_comment_posted'));
          hideCommentForm();
          materializeComment(response);
          break;
        case 'error':
          new MessageBox(MB_OK|MB_ICONSTOP, ( response.title ? response.title : 'Error fetching comment data' ), response.error);
          break;
        default:
          alert(ajax.responseText);
          break;
      }
    }
  });
}

window.renderComments = function(data)
{
  
  var html = '';
  
  // Header
  
    html += '<h3>' + $lang.get('comment_heading') + '</h3>';
    
    var ns = ENANO_PAGE_TYPE;
  
  // Counters
    if ( data.auth_mod_comments )
    {
      var cnt = ( data.auth_mod_comments ) ? data.count_total : data.count_appr;
      
      var subst = {
        num_comments: cnt,
        page_type: ns
      }
      var count_msg = ( cnt == 0 ) ? $lang.get('comment_msg_count_zero', subst) : ( ( cnt == 1 ) ? $lang.get('comment_msg_count_one', subst) : $lang.get('comment_msg_count_plural', subst) );
      
      html += "<p id=\"comment_status\"><span>" + count_msg + '</span>';
      if ( data.count_unappr > 0 )
      {
        html += ' <span style="color: #D84308" id="comment_status_unapp">' + $lang.get('comment_msg_count_unapp_mod', { num_unapp: data.count_unappr }) + '</span>';
      }
      html += '</p>';
    }
    else
    {
      var cnt = data.count_appr;
      
      var subst = {
        num_comments: cnt,
        page_type: ns
      }
      var count_msg = ( cnt == 0 ) ? $lang.get('comment_msg_count_zero', subst) : ( ( cnt == 1 ) ? $lang.get('comment_msg_count_one', subst) : $lang.get('comment_msg_count_plural', subst) );
      
      html += "<p id=\"comment_status\">" + count_msg;
      if ( data.count_unappr > 0 )
      {
        var unappr_msg  = ( data.count_unappr == 1 ) ? $lang.get('comment_msg_count_unapp_one') : $lang.get('comment_msg_count_unapp_plural', { num_unapp: data.count_unappr });
        html += ' ' + unappr_msg;
      }
      html += '</p>';
    }
    
  // Comment display
  
  if ( data.count_total > 0 )
  {
    comment_render_track = 0;
    var commentpages = new paginator(data.comments, _render_comment, 0, 10, data);
    html += commentpages.html;
  }
  
  if ( data.auth_post_comments )
  {
    // Posting form
  
    html += '<h3>' + $lang.get('comment_postform_title') + '</h3>';
    html += '<p>' + $lang.get('comment_postform_blurb');
    if ( data.approval_needed )
      html+=' ' + $lang.get('comment_postform_blurb_unapp');
    html += ' <a id="leave_comment_button" href="#" onclick="displayCommentForm(); return false;">' + $lang.get('comment_postform_blurb_link') + '</a></p>';
    html += '<div id="comment_form" style="display: none;">';
    html += '  <table border="0" style="width: 100%;">';
    html += '    <tr><td>' + $lang.get('comment_postform_field_name') + '</td><td>';
    if ( data.user_id > 1 ) html += data.username + '<input id="commentform_name" type="hidden" value="'+data.username+'" size="40" />';
    else html += '<input id="commentform_name" type="text" size="40" style="width: 100%;" />';
    html += '    </td></tr>';
    html += '    <tr><td>' + $lang.get('comment_postform_field_subject') + '</td><td><input id="commentform_subject" type="text" size="40" style="width: 100%;" /></td></tr>';
    html += '    <tr><td>' + $lang.get('comment_postform_field_comment') + '</td><td><textarea id="commentform_message" rows="15" cols="50" style="width: 100%;"></textarea></td></tr>';
    if ( !data.logged_in && data.guest_posting == '1' )
    {
      html += '  <tr><td>' + $lang.get('comment_postform_field_captcha_title') + '<br /><small>' + $lang.get('comment_postform_field_captcha_blurb') + '</small></td><td>';
      html += '  <img alt="CAPTCHA image" src="'+makeUrlNS('Special', 'Captcha/' + data.captcha)+'" onclick="this.src=\''+makeUrlNS('Special', 'Captcha/' + data.captcha)+'/\'+Math.floor(Math.random()*10000000);" style="cursor: pointer;" /><br />';
      html += '  ' + $lang.get('comment_postform_field_captcha_label') + ' <input type="text" size="8" id="commentform_captcha" />';
      html += '  <!-- This input is used to track the ID of the CAPTCHA image --> <input type="hidden" id="commentform_captcha_id" value="'+data.captcha+'" />';
      html += '  </td></tr>';
    }
    html += '    <tr><td colspan="2" style="text-align: center;"><input type="button" onclick="submitComment();" value="' + $lang.get('comment_postform_btn_submit') + '" /></td></tr>';
    html += '  </table>';
    html += '</div>';
  }
    
  document.getElementById('ajaxEditContainer').innerHTML = html;
  if ( document.getElementById('commentform_message') )
  {
    document.getElementById('commentform_message').allow_wysiwyg = data.auth_edit_wysiwyg
  }
  
  for ( i = 0; i < data.comments.length; i++ )
  {
    document.getElementById('comment_source_'+i).value = data.comments[i].comment_source;
  }
  
}

var _render_comment = function(this_comment, data)
{
  var i = comment_render_track;
  comment_render_track++;
  var parser = new templateParser(comment_template);
  var tplvars = new Object();
  
  if ( this_comment.approved != '1' && !data.auth_mod_comments )
    return '';
  
  tplvars.ID = i;
  tplvars.DATETIME = this_comment.time;
  tplvars.SUBJECT = this_comment.subject;
  tplvars.DATA = this_comment.comment_data;
  tplvars.SIGNATURE = this_comment.signature;
  
  if ( this_comment.approved != '1' )
    tplvars.SUBJECT += ' <span style="color: #D84308">' + $lang.get('comment_msg_note_unapp') + '</span>';
  
  // Name
  tplvars.NAME = this_comment.name;
  if ( this_comment.user_id > 1 )
    tplvars.NAME = '<a href="' + makeUrlNS('User', this_comment.name) + '" style="' + this_comment.rank_style + '">' + this_comment.name + '</a>';
  
  // Avatar
  if ( this_comment.user_has_avatar == '1' )
  {
    tplvars.AVATAR_URL = this_comment.avatar_path;
    tplvars.USERPAGE_LINK = makeUrlNS('User', this_comment.name);
    tplvars.AVATAR_ALT = $lang.get('usercp_avatar_image_alt', { username: this_comment.name });
  }
  
  // User level
  tplvars.USER_LEVEL = '';
  if ( this_comment.user_title )
    tplvars.USER_LEVEL += this_comment.user_title;
  if ( this_comment.rank_title && this_comment.user_title )
    tplvars.USER_LEVEL += '<br />';
  if ( this_comment.rank_title )
    tplvars.USER_LEVEL += $lang.get(this_comment.rank_title);
  
  // Send PM link
  tplvars.SEND_PM_LINK=(this_comment.user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/Compose/To/' + ( this_comment.name.replace(/ /g, '_') )) +'">' + $lang.get('comment_btn_send_privmsg') + '</a><br />':'';
  
  // Add buddy link
  tplvars.ADD_BUDDY_LINK=(this_comment.user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/FriendList/Add/' + ( this_comment.name.replace(/ /g, '_') )) +'">' + $lang.get('comment_btn_add_buddy') + '</a><br />':'';
  
  // Edit link
  tplvars.EDIT_LINK='<a href="#edit_'+i+'" onclick="editComment(\''+i+'\', this); return false;" id="cmteditlink_'+i+'">' + $lang.get('comment_btn_edit') + '</a>';
  
  // Delete link
  tplvars.DELETE_LINK='<a href="#delete_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">' + $lang.get('comment_btn_delete') + '</a>';
  
  // Moderation: (Un)approve link
  var appr = ( this_comment.approved == 1 ) ? $lang.get('comment_btn_mod_unapprove') : $lang.get('comment_btn_mod_approve');
  tplvars.MOD_APPROVE_LINK='<a href="#approve_'+i+'" id="comment_approve_'+i+'" onclick="approveComment(\''+i+'\'); return false;">'+appr+'</a>';
  
  // Moderation: Delete post link
  tplvars.MOD_DELETE_LINK='<a href="#mod_del_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">' + $lang.get('comment_btn_mod_delete') + '</a>';
  
  // Moderation: IP address link
  if ( this_comment.have_ip )
  {
    tplvars.MOD_IP_LINK = '<span id="comment_ip_' + i + '"><a href="#mod_ip_' + i + '" onclick="viewCommentIP(' + this_comment.comment_id + ', ' + i + '); return false;">' + $lang.get('comment_btn_mod_ip_logged') + '</a></span>';
  }
  else
  {
    tplvars.MOD_IP_LINK = $lang.get('comment_btn_mod_ip_missing');
  }
  
  var tplbool = new Object();
  
  tplbool.signature = ( this_comment.signature == '' ) ? false : true;
  tplbool.can_edit = ( data.auth_edit_comments && ( ( this_comment.user_id == data.user_id && data.logged_in ) || data.auth_mod_comments ) );
  tplbool.auth_mod = data.auth_mod_comments;
  tplbool.is_friend = ( this_comment.is_buddy == 1 && this_comment.is_friend == 1 );
  tplbool.is_foe = ( this_comment.is_buddy == 1 && this_comment.is_friend == 0 );
  tplbool.user_has_avatar = ( this_comment.user_has_avatar == '1' );
  
  if ( tplbool.is_friend )
    tplvars.USER_LEVEL += '<br /><b>' + $lang.get('comment_on_friend_list') + '</b>';
  else if ( tplbool.is_foe )
    tplvars.USER_LEVEL += '<br /><b>' + $lang.get('comment_on_foe_list') + '</b>';
  
  parser.assign_vars(tplvars);
  parser.assign_bool(tplbool);
  
  var ret = '<div id="comment_holder_' + i + '">';
  ret += '<input type="hidden" value="'+this_comment.comment_id+'" />';
  ret += '<input type="hidden" id="comment_source_'+i+'" />';
  ret += parser.run();
  ret += '</div>';
  return ret;
}

window.displayCommentForm = function()
{
  document.getElementById('leave_comment_button').style.display = 'none';
  document.getElementById('comment_form').style.display = 'block';
  if ( $dynano('commentform_message').object.allow_wysiwyg )
    $dynano('commentform_message').makeSwitchable();
}

window.hideCommentForm = function()
{
  document.getElementById('leave_comment_button').style.display = 'inline';
  document.getElementById('comment_form').style.display = 'none';
}

window.editComment = function(id, link)
{
  var ctr = document.getElementById('subject_'+id);
  var subj = ( ctr.firstChild ) ? trim(ctr.firstChild.nodeValue) : ''; // If there's a span in there that says 'unapproved', this eliminates it
  ctr.innerHTML = '';
  var ipt = document.createElement('input');
  ipt.id = 'subject_edit_'+id;
  ipt.value = subj;
  ctr.appendChild(ipt);
  
  var src = document.getElementById('comment_source_'+id).value;
  var cmt = document.getElementById('comment_'+id);
  cmt.innerHTML = '';
  var ta = document.createElement('textarea');
  ta.rows = '10';
  ta.cols = '40';
  ta.style.width = '98%';
  ta.value = src;
  ta.id = 'comment_edit_'+id;
  cmt.appendChild(ta);
  $dynano(ta).makeSwitchable();
  
  link.style.fontWeight = 'bold';
  link.innerHTML = $lang.get('comment_btn_save');
  link.onclick = function() { var id = this.id.substr(this.id.indexOf('_')+1); saveComment(id, this); return false; };
}

window.saveComment = function(id, link)
{
  var data = document.getElementById('comment_edit_'+id).value;
  var subj = document.getElementById('subject_edit_'+id).value;
  var div = document.getElementById('comment_holder_'+id);
  var real_id = div.getElementsByTagName('input')[0]['value'];
  var req = {
    'mode' : 'edit',
    'id'   : real_id,
    'local_id' : id,
    'data' : data,
    'subj' : subj
  };
  link.style.fontWeight = 'normal';
  link.innerHTML = $lang.get('comment_btn_edit');
  link.onclick = function() { var id = this.id.substr(this.id.indexOf('_')+1); editComment(id, this); return false; };
  ajaxComments(req);
}

window.deleteComment = function(id)
{
  if ( !shift )
  {
    var c = confirm($lang.get('comment_msg_delete_confirm'));
    if(!c)
      return false;
  }
  var div = document.getElementById('comment_holder_'+id);
  var real_id = div.getElementsByTagName('input')[0]['value'];
  var req = {
    'mode' : 'delete',
    'id'   : real_id,
    'local_id' : id
  };
  ajaxComments(req);
}

window.submitComment = function()
{
  var name = document.getElementById('commentform_name').value;
  var subj = document.getElementById('commentform_subject').value;
  var text = $dynano('commentform_message').getContent();
  if ( document.getElementById('commentform_captcha') )
  {
    var captcha_code = document.getElementById('commentform_captcha').value;
    var captcha_id   = document.getElementById('commentform_captcha_id').value;
  }
  else
  {
    var captcha_code = '';
    var captcha_id   = '';
  }
  if ( subj == '' )
  {
    load_component(['messagebox', 'fadefilter']);
    new MessageBox(MB_OK|MB_ICONSTOP, 'Input validation failed', 'Please enter a subject for your comment.');
    return false;
  }
  if ( text == '' )
  {
    load_component(['messagebox', 'fadefilter']);
    new MessageBox(MB_OK|MB_ICONSTOP, 'Input validation failed', 'Please enter some text for the body of your comment .');
    return false;
  }
  var req = {
    'mode' : 'submit',
    'name' : name,
    'subj' : subj,
    'text' : text,
    'captcha_code' : captcha_code,
    'captcha_id'   : captcha_id
  };
  ajaxComments(req);
}

window.redrawComment = function(data)
{
  if ( data.subj )
  {
    document.getElementById('subject_' + data.id).innerHTML = data.subj;
  }
  if ( data.approved && data.approved != '1' )
  {
    document.getElementById('subject_' + data.id).innerHTML += ' <span style="color: #D84308">' + $lang.get('comment_msg_note_unapp') + '</span>';
  }
  if ( data.approved && ( typeof(data.approve_updated) == 'string' && data.approve_updated == 'yes' ) )
  {
    var appr = ( data.approved == '1' ) ? $lang.get('comment_btn_mod_unapprove') : $lang.get('comment_btn_mod_approve');
    document.getElementById('comment_approve_'+data.id).innerHTML = appr;
    
    if ( data.approved == '1' )
      comment_decrement_unapproval();
    else
      comment_increment_unapproval();
  }
  if ( data.text )
  {
    document.getElementById('comment_' + data.id).innerHTML = data.text;
  }
  if ( data.src )
  {
    document.getElementById('comment_source_' + data.id).value = data.src;
  }
  if ( data.ip_addr )
  {
    var span = $dynano('comment_ip_' + data.local_id).object;
    if ( !span )
      return false;
    span.innerHTML = $lang.get('comment_msg_ip_address') + ' <a href="#rdns" onclick="ajaxReverseDNS(this); return false;">' + data.ip_addr + '</a>';
  }
}

window.approveComment = function(id)
{
  var div = document.getElementById('comment_holder_'+id);
  var real_id = div.getElementsByTagName('input')[0]['value'];
  var req = {
    'mode' : 'approve',
    'id'   : real_id,
    'local_id' : id
  };
  ajaxComments(req);
}

// Does the actual DOM object removal
window.annihiliateComment = function(id) // Did I spell that right?
{
  var approved = true;
  if(document.getElementById('comment_approve_'+id))
  {
    var appr = document.getElementById('comment_approve_'+id).firstChild.nodeValue;
    if ( appr == $lang.get('comment_btn_mod_approve') )
    {
      approved = false;
    }
  }
  
  var div = document.getElementById('comment_holder_'+id);
  div.parentNode.removeChild(div);
  
  // update approval status
  if ( document.getElementById('comment_count_unapp_inner') && !approved )
  {
    comment_decrement_unapproval();
  }
}

window.materializeComment = function(data)
{
  // Intelligently get an ID

  var i = 0;
  var brother;
  while ( true )
  {
    var x = document.getElementById('comment_holder_'+i);
    if(!x)
      break;
    brother = x;
    i++;
  }
  
  var parser = new templateParser(comment_template);
  var tplvars = new Object();
  
  if ( data.approved != '1' && !data.auth_mod_comments )
    return false;
  
  tplvars.ID = i;
  tplvars.DATETIME = data.time;
  tplvars.SUBJECT = data.subject;
  tplvars.DATA = data.comment_data;
  tplvars.SIGNATURE = data.signature;
  
  tplvars.NAME = data.name;
  if ( data.user_id > 1 )
    tplvars.NAME = '<a href="' + makeUrlNS('User', data.name) + '">' + data.name + '</a>';
  
  if ( data.approved != '1' )
    tplvars.SUBJECT += ' <span style="color: #D84308">' + $lang.get('comment_msg_note_unapp') + '</span>';
  
  // User level
  tplvars.USER_LEVEL = $lang.get('user_type_guest');
  if ( data.user_level >= data.user_level_list.member ) tplvars.USER_LEVEL = $lang.get('user_type_member');
  if ( data.user_level >= data.user_level_list.mod ) tplvars.USER_LEVEL = $lang.get('user_type_mod');
  if ( data.user_level >= data.user_level_list.admin ) tplvars.USER_LEVEL = $lang.get('user_type_admin');
  
  // Avatar
  if ( data.user_has_avatar == '1' )
  {
    tplvars.AVATAR_URL = scriptPath + '/' + data.avatar_directory + '/' + data.user_id + '.' + data.avatar_type;
    tplvars.USERPAGE_LINK = makeUrlNS('User', data.name);
    tplvars.AVATAR_ALT = $lang.get('usercp_avatar_image_alt', { username: data.name });
  }
  
  // Send PM link
  tplvars.SEND_PM_LINK=(data.user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/Compose/To/' + ( data.name.replace(/ /g, '_') )) +'">' + $lang.get('comment_btn_send_privmsg') + '</a><br />':'';
  
  // Add buddy link
  tplvars.ADD_BUDDY_LINK=(data.user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/FriendList/Add/' + ( data.name.replace(/ /g, '_') )) +'">' + $lang.get('comment_btn_add_buddy') + '</a><br />':'';
  
  // Edit link
  tplvars.EDIT_LINK='<a href="#edit_'+i+'" onclick="editComment(\''+i+'\', this); return false;" id="cmteditlink_'+i+'">' + $lang.get('comment_btn_edit') + '</a>';
  
  // Delete link
  tplvars.DELETE_LINK='<a href="#delete_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">' + $lang.get('comment_btn_delete') + '</a>';
  
  // Moderation: (Un)approve link
  var appr = ( data.approved == 1 ) ? $lang.get('comment_btn_mod_unapprove') : $lang.get('comment_btn_mod_approve');
  tplvars.MOD_APPROVE_LINK='<a href="#approve_'+i+'" id="comment_approve_'+i+'" onclick="approveComment(\''+i+'\'); return false;">'+appr+'</a>';
  
  // Moderation: Delete post link
  tplvars.MOD_DELETE_LINK='<a href="#mod_del_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">' + $lang.get('comment_btn_mod_delete') + '</a>';
  
  // Moderation: IP address link
  tplvars.MOD_IP_LINK = '<span id="comment_ip_' + i + '"><a href="#mod_ip_' + i + '" onclick="viewCommentIP(' + data.comment_id + ', ' + i + '); return false;">' + $lang.get('comment_btn_mod_ip_logged') + '</a></span>';
  
  var tplbool = new Object();
  
  tplbool.signature = ( data.signature == '' ) ? false : true;
  tplbool.can_edit = ( data.auth_edit_comments && ( ( data.user_id == data.user_id && data.logged_in ) || data.auth_mod_comments ) );
  tplbool.auth_mod = data.auth_mod_comments;
  tplbool.user_has_avatar = ( data.user_has_avatar == '1' );
  
  parser.assign_vars(tplvars);
  parser.assign_bool(tplbool);
  
  var div = document.createElement('div');
  div.id = 'comment_holder_'+i;
  
  div.innerHTML = '<input type="hidden" value="'+data.comment_id+'" /><input type="hidden" id="comment_source_'+i+'" />' + parser.run();
  
  if ( brother )
  {
    brother.parentNode.insertBefore(div, brother.nextSibling);
  }
  else
  {
    // No comments in ajaxEditContainer, insert it after the header
    var aec = document.getElementById("ajaxEditContainer");
    aec.insertBefore(div, aec.firstChild.nextSibling.nextSibling);
  }
  
  document.getElementById('comment_source_'+i).value = data.comment_source;
  
  var cnt = document.getElementById('comment_count_inner').innerHTML;
  cnt = parseInt(cnt);
  if ( isNaN(cnt) )
    cnt = 0;
  
  var subst = {
    num_comments: cnt,
    page_type: ENANO_PAGE_TYPE
  }
  
  var count_msg = ( cnt == 0 ) ? $lang.get('comment_msg_count_zero', subst) : ( ( cnt == 1 ) ? $lang.get('comment_msg_count_one', subst) : $lang.get('comment_msg_count_plural', subst) );
  
  document.getElementById('comment_status').firstChild.innerHTML = count_msg;
  
  if(document.getElementById('comment_approve_'+i))
  {
    var is_unappr = document.getElementById('comment_approve_'+i).firstChild.nodeValue;
    is_unappr = ( is_unappr == $lang.get('comment_btn_mod_approve') );
    if ( is_unappr )
    {
      comment_increment_unapproval();
    }
  }
  
}

window.comment_decrement_unapproval = function()
{
  if ( document.getElementById('comment_count_unapp_inner') )
  {
    var num_unapp = parseInt(document.getElementById('comment_count_unapp_inner').innerHTML);
    if ( !isNaN(num_unapp) )
    {
      num_unapp = num_unapp - 1;
      if ( num_unapp == 0 )
      {
        var p = document.getElementById('comment_status');
        p.removeChild(p.childNodes[2]);
        p.removeChild(p.childNodes[1]);
      }
      else
      {
        var count_msg = $lang.get('comment_msg_count_unapp_mod', { num_unapp: num_unapp });
        document.getElementById('comment_count_unapp_inner').parentNode.innerHTML = count_msg;
      }
    }
  }
}

window.comment_increment_unapproval = function()
{
  if ( document.getElementById('comment_count_unapp_inner') )
  {
    var num_unapp = parseInt(document.getElementById('comment_count_unapp_inner').innerHTML);
    if ( isNaN(num_unapp) )
      num_unapp = 0;
    num_unapp = num_unapp + 1;
    var count_msg = $lang.get('comment_msg_count_unapp_mod', { num_unapp: num_unapp });
    document.getElementById('comment_count_unapp_inner').parentNode.innerHTML = count_msg;
  }
  else
  {
    var count_msg = $lang.get('comment_msg_count_unapp_mod', { num_unapp: 1 });
    var status = document.getElementById('comment_status');
    if ( !status.childNodes[1] )
      status.appendChild(document.createTextNode(' '));
    var span = document.createElement('span');
    span.id = 'comment_status_unapp';
    span.style.color = '#D84308';
    span.innerHTML = count_msg;
    status.appendChild(span);
  }
}

window.viewCommentIP = function(id, local_id)
{
  // set "loading" indicator on IP button
  var span = $dynano('comment_ip_' + local_id).object;
  if ( !span )
    return false;
  span.innerHTML = '<img alt="..." src="' + ajax_load_icon + '" />';
  
  var parms = {
    mode: 'view_ip',
    id: id,
    local_id: local_id
  }
  ajaxComments(parms);
}


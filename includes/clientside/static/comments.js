// Comments

var comment_template = false;

function ajaxComments(parms) {
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
  ajaxPost(stdAjaxPrefix+'&_mode=comments', 'data=' + parms, function() {
    if(ajax.readyState == 4) {
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
          document.getElementById('ajaxEditContainer').innerHTML = 'Rendering response...';
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
          alert('Your comment has been posted. If it does not appear right away, it is probably awaiting approval.');
          hideCommentForm();
          materializeComment(response);
          break;
        case 'error':
          alert(response.error);
          break;
        default:
          alert(ajax.responseText);
          break;
      }
    }
  });
}

function renderComments(data)
{
  
  var html = '';
  
  // Header
  
    html += '<h3>Article Comments</h3>';
    
    var ns = ( strToPageID(title)[1]=='Article' ) ? 'article' : ( strToPageID(title)[1].toLowerCase() ) + ' page';
  
    // Counters
    if ( data.auth_mod_comments )
    {
      var cnt = ( data.auth_mod_comments ) ? data.count_total : data.count_appr;
      if ( cnt == 0 ) cnt = 'no';
      var s  = ( cnt == 1 ) ? '' : 's';
      var is = ( cnt == 1 ) ? 'is' : 'are';
      html += "<p id=\"comment_status\">There "+is+" " + cnt + " comment"+s+" on this "+ns+".";
      if ( data.count_unappr > 0 )
      {
        html += ' <span style="color: #D84308">' + data.count_unappr + ' of those are unapproved.</span>';
      }
      html += '</p>';
    }
    else
    {
      var cnt = data.count_appr;
      if ( cnt == 0 ) cnt = 'no';
      var s  = ( cnt == 1 ) ? '' : 's';
      var is = ( cnt == 1 ) ? 'is' : 'are';
      html += "<p id=\"comment_status\">There "+is+" " + cnt + " comment"+s+" on this "+ns+".";
      if ( data.count_unappr > 0 )
      {
        var s  = ( data.count_unappr == 1 ) ? '' : 's';
        var is = ( data.count_unappr == 1 ) ? 'is' : 'are';
        html += ' However, there '+is+' '+data.count_unappr+' additional comment'+s+' awaiting approval.';
      }
      html += '</p>';
    }
    
  // Comment display
  
  if ( data.count_total > 0 )
  {
    var parser = new templateParser(comment_template);
    for ( var i = 0; i < data.comments.length; i++ )
    {
      var tplvars = new Object();
      
      if ( data.comments[i].approved != '1' && !data.auth_mod_comments )
        continue;
      
      tplvars.ID = i;
      tplvars.DATETIME = data.comments[i].time;
      tplvars.SUBJECT = data.comments[i].subject;
      tplvars.DATA = data.comments[i].comment_data;
      tplvars.SIGNATURE = data.comments[i].signature;
      
      if ( data.comments[i].approved != '1' )
        tplvars.SUBJECT += ' <span style="color: #D84308">(Unapproved)</span>';
      
      // Name
      tplvars.NAME = data.comments[i].name;
      if ( data.comments[i].user_id > 1 )
        tplvars.NAME = '<a href="' + makeUrlNS('User', data.comments[i].name) + '">' + data.comments[i].name + '</a>';
      
      // User level
      tplvars.USER_LEVEL = 'Guest';
      if ( data.comments[i].user_level >= data.user_level.member ) tplvars.USER_LEVEL = 'Member';
      if ( data.comments[i].user_level >= data.user_level.mod ) tplvars.USER_LEVEL = 'Moderator';
      if ( data.comments[i].user_level >= data.user_level.admin ) tplvars.USER_LEVEL = 'Administrator';
      
      // Send PM link
      tplvars.SEND_PM_LINK=(data.comments[i].user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/Compose/To/' + ( data.comments[i].name.replace(/ /g, '_') )) +'">Send private message</a><br />':'';
      
      // Add buddy link
      tplvars.ADD_BUDDY_LINK=(data.comments[i].user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/FriendList/Add/' + ( data.comments[i].name.replace(/ /g, '_') )) +'">Add to buddy list</a><br />':'';
      
      // Edit link
      tplvars.EDIT_LINK='<a href="#edit_'+i+'" onclick="editComment(\''+i+'\', this); return false;" id="cmteditlink_'+i+'">edit</a>';
      
      // Delete link
      tplvars.DELETE_LINK='<a href="#delete_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">delete</a>';
      
      // Moderation: (Un)approve link
      var appr = ( data.comments[i].approved == 1 ) ? 'Unapprove' : 'Approve';
      tplvars.MOD_APPROVE_LINK='<a href="#approve_'+i+'" id="comment_approve_'+i+'" onclick="approveComment(\''+i+'\'); return false;">'+appr+'</a>';
      
      // Moderation: Delete post link
      tplvars.MOD_DELETE_LINK='<a href="#mod_del_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">Delete</a>';
      
      var tplbool = new Object();
      
      tplbool.signature = ( data.comments[i].signature == '' ) ? false : true;
      tplbool.can_edit = ( data.auth_edit_comments && ( ( data.comments[i].user_id == data.user_id && data.logged_in ) || data.auth_mod_comments ) );
      tplbool.auth_mod = data.auth_mod_comments;
      
      parser.assign_vars(tplvars);
      parser.assign_bool(tplbool);
      
      html += '<div id="comment_holder_' + i + '"><input type="hidden" value="'+data.comments[i].comment_id+'" /><input type="hidden" id="comment_source_'+i+'" />' + parser.run() + '</div>';
    }
  }
  
  if ( data.auth_post_comments )
  {
    
    // Posting form
  
    html += '<h3>Got something to say?</h3>';
    html += '<p>If you have comments or suggestions on this article, you can shout it out here.';
    if ( data.approval_needed )
      html+=' Before your post will be visible to the public, a moderator will have to approve it.';
    html += ' <a id="leave_comment_button" href="#" onclick="displayCommentForm(); return false;">Leave a comment...</a></p>';
    html += '<div id="comment_form" style="display: none;">';
    html += '  <table border="0">';
    html += '    <tr><td>Your name/screen name:</td><td>';
    if ( data.user_id > 1 ) html += data.username + '<input id="commentform_name" type="hidden" value="'+data.username+'" size="40" />';
    else html += '<input id="commentform_name" type="text" size="40" />';
    html += '    </td></tr>';
    html += '    <tr><td>Comment subject:</td><td><input id="commentform_subject" type="text" size="40" /></td></tr>';
    html += '    <tr><td>Comment:</td><td><textarea id="commentform_message" rows="15" cols="50"></textarea></td></tr>';
    if ( !data.logged_in && data.guest_posting == '1' )
    {
      html += '  <tr><td>Visual confirmation:<br /><small>Please enter the confirmation code seen in the image on the right into the box. If you cannot read the code, please click on the image to generate a new one. This helps to prevent automated bot posting.</small></td><td>';
      html += '  <img alt="CAPTCHA image" src="'+makeUrlNS('Special', 'Captcha/' + data.captcha)+'" onclick="this.src=\''+makeUrlNS('Special', 'Captcha/' + data.captcha)+'/\'+Math.floor(Math.random()*10000000);" style="cursor: pointer;" /><br />';
      html += '  Confirmation code: <input type="text" size="8" id="commentform_captcha" />';
      html += '  <!-- This input is used to track the ID of the CAPTCHA image --> <input type="hidden" id="commentform_captcha_id" value="'+data.captcha+'" />';
      html += '  </td></tr>';
    }
    html += '    <tr><td colspan="2" style="text-align: center;"><input type="button" onclick="submitComment();" value="Submit comment" /></td></tr>';
    html += '  </table>';
    html += '</div>';
    
  }
    
  document.getElementById('ajaxEditContainer').innerHTML = html;
  
  for ( i = 0; i < data.comments.length; i++ )
  {
    document.getElementById('comment_source_'+i).value = data.comments[i].comment_source;
  }
  
}

function displayCommentForm()
{
  document.getElementById('leave_comment_button').style.display = 'none';
  document.getElementById('comment_form').style.display = 'block';
}

function hideCommentForm()
{
  document.getElementById('leave_comment_button').style.display = 'inline';
  document.getElementById('comment_form').style.display = 'none';
}

function editComment(id, link)
{
  var ctr = document.getElementById('subject_'+id);
  var subj = trim(ctr.firstChild.nodeValue); // If there's a span in there that says 'unapproved', this eliminates it
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
  ta.value = src;
  ta.id = 'comment_edit_'+id;
  cmt.appendChild(ta);
  
  link.style.fontWeight = 'bold';
  link.innerHTML = 'save';
  link.onclick = function() { var id = this.id.substr(this.id.indexOf('_')+1); saveComment(id, this); return false; };
}

function saveComment(id, link)
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
  link.innerHTML = 'edit';
  link.onclick = function() { var id = this.id.substr(this.id.indexOf('_')+1); editComment(id, this); return false; };
  ajaxComments(req);
}

function deleteComment(id)
{
  //var c = confirm('Do you really want to delete this comment?');
  //if(!c);
  //  return false;
  var div = document.getElementById('comment_holder_'+id);
  var real_id = div.getElementsByTagName('input')[0]['value'];
  var req = {
    'mode' : 'delete',
    'id'   : real_id,
    'local_id' : id
  };
  ajaxComments(req);
}

function submitComment()
{
  var name = document.getElementById('commentform_name').value;
  var subj = document.getElementById('commentform_subject').value;
  var text = document.getElementById('commentform_message').value;
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

function redrawComment(data)
{
  if ( data.subj )
  {
    document.getElementById('subject_' + data.id).innerHTML = data.subj;
  }
  if ( data.approved && data.approved != '1' )
  {
    document.getElementById('subject_' + data.id).innerHTML += ' <span style="color: #D84308">(Unapproved)</span>';
  }
  if ( data.approved && ( typeof(data.approve_updated) == 'string' && data.approve_updated == 'yes' ) )
  {
    var appr = ( data.approved == '1' ) ? 'Unapprove' : 'Approve';
    document.getElementById('comment_approve_'+data.id).innerHTML = appr;
    
    // Update approval status
    var p = document.getElementById('comment_status');
    var count = p.firstChild.nodeValue.split(' ')[2];
    
    if ( p.firstChild.nextSibling )
    {
      var span = p.firstChild.nextSibling;
      var is = ( data.approved == '1' ) ? -1 : 1;
      var n_unapp = parseInt(span.firstChild.nodeValue.split(' ')[0]) + is;
      n_unapp = n_unapp + '';
    }
    else
    {
      var span = document.createElement('span');
      p.innerHTML += ' ';
      span.innerHTML = ' ';
      span.style.color = '#D84308';
      var n_unapp = '1';
      p.appendChild(span);
    }
    span.innerHTML = n_unapp + ' of those are unapproved.';
    if ( n_unapp == '0' )
      p.removeChild(span);
  }
  if ( data.text )
  {
    document.getElementById('comment_' + data.id).innerHTML = data.text;
  }
  if ( data.src )
  {
    document.getElementById('comment_source_' + data.id).value = data.src;
  }
}

function approveComment(id)
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
function annihiliateComment(id) // Did I spell that right?
{
  // Approved?
  var p = document.getElementById('comment_status');
  
  if(document.getElementById('comment_approve_'+id))
  {
    var appr = document.getElementById('comment_approve_'+id).firstChild.nodeValue;
    if ( p.firstChild.nextSibling && appr == 'Approve' )
    {
      var span = p.firstChild.nextSibling;
      var t = span.firstChild.nodeValue;
      var n_unapp = ( parseInt(t.split(' ')[0]) ) - 1;
      if ( n_unapp == 0 )
        p.removeChild(span);
      else
        span.firstChild.nodeValue = n_unapp + t.substr(t.indexOf(' '));
    }
  }
  
  var div = document.getElementById('comment_holder_'+id);
  div.parentNode.removeChild(div);
  var t = p.firstChild.nodeValue.split(' ');
  t[2] = ( parseInt(t[2]) - 1 ) + '';
  delete(t.toJSONString);
  if ( t[2] == '1' )
  {
    t[1] = 'is';
    t[3] = 'comment';
  }
  else
  {
    t[1] = 'are';
    t[3] = 'comments';
  }
  t = implode(' ', t);
  p.firstChild.nodeValue = t;
}

function materializeComment(data)
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
    tplvars.SUBJECT += ' <span style="color: #D84308">(Unapproved)</span>';
  
  // User level
  tplvars.USER_LEVEL = 'Guest';
  if ( data.user_level >= data.user_level_list.member ) tplvars.USER_LEVEL = 'Member';
  if ( data.user_level >= data.user_level_list.mod ) tplvars.USER_LEVEL = 'Moderator';
  if ( data.user_level >= data.user_level_list.admin ) tplvars.USER_LEVEL = 'Administrator';
  
  // Send PM link
  tplvars.SEND_PM_LINK=(data.user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/Compose/To/' + ( data.name.replace(/ /g, '_') )) +'">Send private message</a><br />':'';
  
  // Add buddy link
  tplvars.ADD_BUDDY_LINK=(data.user_id>1)?'<a onclick="window.open(this.href); return false;" href="'+ makeUrlNS('Special', 'PrivateMessages/FriendList/Add/' + ( data.name.replace(/ /g, '_') )) +'">Add to buddy list</a><br />':'';
  
  // Edit link
  tplvars.EDIT_LINK='<a href="#edit_'+i+'" onclick="editComment(\''+i+'\', this); return false;" id="cmteditlink_'+i+'">edit</a>';
  
  // Delete link
  tplvars.DELETE_LINK='<a href="#delete_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">delete</a>';
  
  // Moderation: (Un)approve link
  var appr = ( data.approved == 1 ) ? 'Unapprove' : 'Approve';
  tplvars.MOD_APPROVE_LINK='<a href="#approve_'+i+'" id="comment_approve_'+i+'" onclick="approveComment(\''+i+'\'); return false;">'+appr+'</a>';
  
  // Moderation: Delete post link
  tplvars.MOD_DELETE_LINK='<a href="#mod_del_'+i+'" onclick="deleteComment(\''+i+'\'); return false;">Delete</a>';
  
  var tplbool = new Object();
  
  tplbool.signature = ( data.signature == '' ) ? false : true;
  tplbool.can_edit = ( data.auth_edit_comments && ( ( data.user_id == data.user_id && data.logged_in ) || data.auth_mod_comments ) );
  tplbool.auth_mod = data.auth_mod_comments;
  
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
  
  var p = document.getElementById('comment_status');
  var t = p.firstChild.nodeValue.split(' ');
  var n = ( isNaN(parseInt(t[2])) ) ? 0 : parseInt(t[2]);
  t[2] = ( n + 1 ) + '';
  delete(t.toJSONString);
  if ( t[2] == '1' )
  {
    t[1] = 'is';
    t[3] = 'comment';
  }
  else
  {
    t[1] = 'are';
    t[3] = 'comments';
  }
  t = implode(' ', t);
  p.firstChild.nodeValue = t;
  
  if(document.getElementById('comment_approve_'+i))
  {
    var appr = document.getElementById('comment_approve_'+i).firstChild.nodeValue;
    if ( p.firstChild.nextSibling && appr == 'Approve' )
    {
      var span = p.firstChild.nextSibling;
      var t = span.firstChild.nodeValue;
      var n_unapp = ( parseInt(t.split(' ')[0]) ) - 1;
      if ( n_unapp == 0 )
        p.removeChild(span);
      else
        span.firstChild.nodeValue = n_unapp + t.substr(t.indexOf(' '));
    }
    else if ( appr == 'Approve' && !p.firstChild.nextSibling )
    {
      var span = document.createElement('span');
      p.innerHTML += ' ';
      span.innerHTML = '1 of those are unapproved.';
      span.style.color = '#D84308';
      var n_unapp = '1';
      p.appendChild(span);
    }
  }
  
}

function htmlspecialchars(text)
{
  text = text.replace(/</g, '&lt;');
  text = text.replace(/>/g, '&gt;');
  return text;
}

// Equivalent to PHP trim() function
function trim(text)
{
  text = text.replace(/^([\s]+)/, '');
  text = text.replace(/([\s]+)$/, '');
  return text;
}

// Equivalent to PHP implode() function
function implode(chr, arr)
{
  if ( typeof ( arr.toJSONString ) == 'function' )
    delete(arr.toJSONString);
  
  var ret = '';
  var c = 0;
  for ( var i in arr )
  {
    if(i=='toJSONString')continue;
    if ( c > 0 )
      ret += chr;
    ret += arr[i];
    c++;
  }
  return ret;
}

function nl2br(text)
{
  var regex = new RegExp(unescape('%0A'), 'g');
  return text.replace(regex, '<br />' + unescape('%0A'));
}


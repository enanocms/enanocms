/*
 * AJAX-based intelligent login interface
 */

/*
 * FRONTEND
 */

/**
 * Performs a logon as a regular member.
 */

function ajaxLogonToMember()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level >= USER_LEVEL_MEMBER )
    return true;
  ajaxLoginInit(function(k)
    {
      window.location.reload();
    }, USER_LEVEL_MEMBER);
}

/**
 * Authenticates to the highest level the current user is allowed to go to.
 */

function ajaxLogonToElev()
{
  if ( auth_level == user_level )
    return true;
  
  ajaxLoginInit(function(k)
    {
      ENANO_SID = k;
      var url = String(' ' + window.location).substr(1);
      url = append_sid(url);
      window.location = url;
    }, user_level);
}

/*
 * BACKEND
 */

/**
 * Holding object for various AJAX authentication information.
 * @var object
 */

var logindata = {};

/**
 * Path to the image used to indicate loading progress
 * @var string
 */

if ( !ajax_login_loadimg_path )
  var ajax_login_loadimg_path = false;

if ( !ajax_login_successimg_path )
  var ajax_login_successimg_path = false;

/**
 * Status variables
 * @var int
 */

var AJAX_STATUS_LOADING_KEY = 1;
var AJAX_STATUS_GENERATING_KEY = 2;
var AJAX_STATUS_LOGGING_IN = 3;
var AJAX_STATUS_SUCCESS = 4;
var AJAX_STATUS_DESTROY = 65535;

/**
 * State constants
 * @var int
 */

var AJAX_STATE_EARLY_INIT = 1;
var AJAX_STATE_LOADING_KEY = 2;

/**
 * Performs the AJAX request to get an encryption key and from there spawns the login form.
 * @param function The function that will be called once authentication completes successfully.
 * @param int The security level to authenticate at - see http://docs.enanocms.org/Help:Appendix_B
 */

function ajaxLoginInit(call_on_finish, user_level)
{
  logindata = {};
  
  var title = ( user_level > USER_LEVEL_MEMBER ) ? $lang.get('user_login_ajax_prompt_title_elev') : $lang.get('user_login_ajax_prompt_title');
  logindata.mb_object = new messagebox(MB_OKCANCEL | MB_ICONLOCK, title, '');
  
  logindata.mb_object.onclick['Cancel'] = function()
  {
    // Hide the error message and captcha
    if ( document.getElementById('ajax_login_error_box') )
    {
      document.getElementById('ajax_login_error_box').parentNode.removeChild(document.getElementById('ajax_login_error_box'));
    }
    if ( document.getElementById('autoCaptcha') )
    {
      var to = fly_out_top(document.getElementById('autoCaptcha'), false, true);
      setTimeout(function() {
          var d = document.getElementById('autoCaptcha');
          d.parentNode.removeChild(d);
        }, to);
    }
  };
  
  logindata.mb_object.onbeforeclick['OK'] = function()
  {
    ajaxLoginSubmitForm();
    return true;
  }
  
  // Fetch the inner content area
  logindata.mb_inner = document.getElementById('messageBox').getElementsByTagName('div')[0];
  
  // Initialize state
  logindata.showing_status = false;
  logindata.user_level = user_level;
  logindata.successfunc = call_on_finish;
  
  // Build the "loading" window
  ajaxLoginSetStatus(AJAX_STATUS_LOADING_KEY);
  
  // Request the key
  ajaxLoginPerformRequest({ mode: 'getkey' });
}

/**
 * Sets the contents of the AJAX login window to the appropriate status message.
 * @param int One of AJAX_STATUS_*
 */

function ajaxLoginSetStatus(status)
{
  if ( !logindata.mb_inner )
    return false;
  if ( logindata.showing_status )
  {
    var div = document.getElementById('ajax_login_status');
    if ( div )
      logindata.mb_inner.removeChild(div);
  }
  switch(status)
  {
    case AJAX_STATUS_LOADING_KEY:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_fetching_key');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_loadimg_path ) ? ajax_login_loadimg_path : scriptPath + '/images/loading-big.gif';
      div.appendChild(img);
      
      // Another coupla brs
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      // The link to the full login form
      var small = document.createElement('small');
      small.innerHTML = $lang.get('user_login_ajax_link_fullform', { link_full_form: makeUrlNS('Special', 'Login/' + title) });
      div.appendChild(small);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
    case AJAX_STATUS_GENERATING_KEY:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_generating_key');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_loadimg_path ) ? ajax_login_loadimg_path : scriptPath + '/images/loading-big.gif';
      div.appendChild(img);
      
      // Another coupla brs
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      // The link to the full login form
      var small = document.createElement('small');
      small.innerHTML = $lang.get('user_login_ajax_link_fullform_dh', { link_full_form: makeUrlNS('Special', 'Login/' + title) });
      div.appendChild(small);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
    case AJAX_STATUS_LOGGING_IN:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_loggingin');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_loadimg_path ) ? ajax_login_loadimg_path : scriptPath + '/images/loading-big.gif';
      div.appendChild(img);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
    case AJAX_STATUS_SUCCESS:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_success_short');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_successimg_path ) ? ajax_login_successimg_path : scriptPath + '/images/check.png';
      div.appendChild(img);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
    case AJAX_STATUS_DESTROY:
    case null:
    case undefined:
      logindata.showing_status = false;
      return null;
      break;
  }
  logindata.showing_status = true;
}

/**
 * Performs an AJAX logon request to the server and calls ajaxLoginProcessResponse() on the result.
 * @param object JSON packet to send
 */

function ajaxLoginPerformRequest(json)
{
  json = toJSONString(json);
  json = ajaxEscape(json);
  ajaxPost(makeUrlNS('Special', 'Login/action.json'), 'r=' + json, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        // parse response
        var response = String(ajax.responseText + '');
        if ( response.substr(0, 1) != '{' )
        {
          handle_invalid_json(response);
          return false;
        }
        response = parseJSON(response);
        ajaxLoginProcessResponse(response);
      }
    }, true);
}

/**
 * Processes a response from the login server
 * @param object JSON response
 */

function ajaxLoginProcessResponse(response)
{
  // Did the server send a plaintext error?
  if ( response.mode == 'error' )
  {
    logindata.mb_object.destroy();
    new messagebox(MB_ICONSTOP | MB_OK, 'FIXME L10N: There was an error in the login process', 'The following error code came from the server:<br />' + response.error);
    return false;
  }
  // Rid ourselves of any loading windows
  ajaxLoginSetStatus(AJAX_STATUS_DESTROY);
  // Main mode switch
  switch ( response.mode )
  {
    case 'build_box':
      // The server wants us to build the login form, all the information is there
      ajaxLoginBuildForm(response);
      break;
    case 'login_success':
      ajaxLoginSetStatus(AJAX_STATUS_SUCCESS);
      logindata.successfunc(response.key);
      break;
    case 'login_failure':
      document.getElementById('messageBox').style.backgroundColor = '#C0C0C0';
      var mb_parent = document.getElementById('messageBox').parentNode;
      new Spry.Effect.Shake(mb_parent, {duration: 1500}).start();
      setTimeout(function()
        {
          document.getElementById('messageBox').style.backgroundColor = '#FFF';
          ajaxLoginBuildForm(response.respawn_info);
          ajaxLoginShowFriendlyError(response);
        }, 2500);
      break;
  }
}

/*
 * RESPONSE HANDLERS
 */

/**
 * Builds the login form.
 * @param object Metadata to build off of
 */

function ajaxLoginBuildForm(data)
{
  // let's hope this effectively preloads the image...
  var _ = document.createElement('img');
  _.src = ( ajax_login_successimg_path ) ? ajax_login_successimg_path : scriptPath + '/images/check.png';
  
  var div = document.createElement('div');
  div.id = 'ajax_login_form';
  
  var show_captcha = ( data.locked_out && data.lockout_info.lockout_policy == 'captcha' ) ? data.lockout_info.captcha : false;
  
  // text displayed on re-auth
  if ( logindata.user_level > USER_LEVEL_MEMBER )
  {
    div.innerHTML += $lang.get('user_login_ajax_prompt_body_elev') + '<br /><br />';
  }
  
  // Create the form
  var form = document.createElement('form');
  form.action = 'javascript:void(ajaxLoginSubmitForm());';
  form.onsubmit = function()
  {
    ajaxLoginSubmitForm();
    return false;
  }
  
  // Using tables to wrap form elements because it results in a
  // more visually appealing form. Yes, tables suck. I don't really
  // care - they make forms look good.
  
  var table = document.createElement('table');
  table.style.margin = '0 auto';
  
  // Field - username
  var tr1 = document.createElement('tr');
  var td1_1 = document.createElement('td');
  td1_1.appendChild(document.createTextNode($lang.get('user_login_field_username') + ':'));
  tr1.appendChild(td1_1);
  var td1_2 = document.createElement('td');
  var f_username = document.createElement('input');
  f_username.id = 'ajax_login_field_username';
  f_username.name = 'ajax_login_field_username';
  f_username.type = 'text';
  f_username.size = '25';
  if ( data.username )
    f_username.value = data.username;
  td1_2.appendChild(f_username);
  tr1.appendChild(td1_2);
  table.appendChild(tr1);
  
  // Field - password
  var tr2 = document.createElement('tr');
  var td2_1 = document.createElement('td');
  td2_1.appendChild(document.createTextNode($lang.get('user_login_field_password') + ':'));
  tr2.appendChild(td2_1);
  var td2_2 = document.createElement('td');
  var f_password = document.createElement('input');
  f_password.id = 'ajax_login_field_password';
  f_password.name = 'ajax_login_field_username';
  f_password.type = 'password';
  f_password.size = '25';
  if ( !show_captcha )
  {
    f_password.onkeyup = function(e)
    {
      if ( !e.keyCode )
        e = window.event;
      if ( !e.keyCode )
        return true;
      if ( e.keyCode == 13 )
      {
        ajaxLoginSubmitForm();
      }
    }
  }
  td2_2.appendChild(f_password);
  tr2.appendChild(td2_2);
  table.appendChild(tr2);
  
  // Field - captcha
  if ( show_captcha )
  {
    var tr3 = document.createElement('tr');
    var td3_1 = document.createElement('td');
    td3_1.appendChild(document.createTextNode($lang.get('user_login_field_captcha') + ':'));
    tr3.appendChild(td3_1);
    var td3_2 = document.createElement('td');
    var f_captcha = document.createElement('input');
    f_captcha.id = 'ajax_login_field_captcha';
    f_captcha.name = 'ajax_login_field_username';
    f_captcha.type = 'text';
    f_captcha.size = '25';
    f_captcha.onkeyup = function(e)
    {
      if ( !e )
        e = window.event;
      if ( !e.keyCode )
        return true;
      if ( e.keyCode == 13 )
      {
        ajaxLoginSubmitForm();
      }
    }
    td3_2.appendChild(f_captcha);
    tr3.appendChild(td3_2);
    table.appendChild(tr3);
  }
  
  // Done building the main part of the form
  form.appendChild(table);
  
  // Field: enable Diffie Hellman
  var lbl_dh = document.createElement('label');
  lbl_dh.style.fontSize = 'smaller';
  lbl_dh.style.display = 'block';
  lbl_dh.style.textAlign = 'center';
  var check_dh = document.createElement('input');
  check_dh.type = 'checkbox';
  // this onclick attribute changes the cookie whenever the checkbox or label is clicked
  check_dh.setAttribute('onclick', 'var ck = ( this.checked ) ? "enable" : "disable"; createCookie("diffiehellman_login", ck, 3650);');
  if ( readCookie('diffiehellman_login') != 'disable' )
    check_dh.setAttribute('checked', 'checked');
  check_dh.id = 'ajax_login_field_dh';
  lbl_dh.appendChild(check_dh);
  lbl_dh.innerHTML += $lang.get('user_login_ajax_check_dh');
  form.appendChild(lbl_dh);
  
  div.appendChild(form);
  
  // Diagnostic / help links
  // (only displayed in login, not in re-auth)
  if ( logindata.user_level == USER_LEVEL_MEMBER )
  {
    form.style.marginBottom = '10px';
    var links = document.createElement('small');
    links.style.display = 'block';
    links.style.textAlign = 'center';
    links.innerHTML = '';
    if ( !show_captcha )
      links.innerHTML += $lang.get('user_login_ajax_link_fullform', { link_full_form: makeUrlNS('Special', 'Login/' + title) }) + '<br />';
    // Always shown
    links.innerHTML += $lang.get('user_login_ajax_link_forgotpass', { forgotpass_link: makeUrlNS('Special', 'PasswordReset') }) + '<br />';
    if ( !show_captcha )
      links.innerHTML += $lang.get('user_login_createaccount_blurb', { reg_link: makeUrlNS('Special', 'Register') });
    div.appendChild(links);
  }
  
  // Insert the entire form into the login window
  logindata.mb_inner.innerHTML = '';
  logindata.mb_inner.appendChild(div);
  
  // Post operations: field focus
  if ( data.username )
    f_password.focus();
  else
    f_username.focus();
  
  // Post operations: show captcha window
  if ( show_captcha )
    ajaxShowCaptcha(show_captcha);
  
  // Post operations: stash encryption keys and All That Jazz(TM)
  logindata.key_aes = data.aes_key;
  logindata.key_dh = data.dh_public_key;
  logindata.captcha_hash = show_captcha;
  
  // Are we locked out? If so simulate an error and disable the controls
  if ( data.lockout_info.lockout_policy == 'lockout' && data.locked_out )
  {
    f_username.setAttribute('disabled', 'disabled');
    f_password.setAttribute('disabled', 'disabled');
    var fake_packet = {
      error_code: 'locked_out',
      respawn_info: data
    };
    ajaxLoginShowFriendlyError(fake_packet);
  }
}

function ajaxLoginSubmitForm(real, username, password, captcha)
{
  // Perform AES test to make sure it's all working
  if ( !aes_self_test() )
  {
    alert('BUG: AES self-test failed');
    login_cache.mb_object.destroy();
    return false;
  }
  // Hide the error message and captcha
  if ( document.getElementById('ajax_login_error_box') )
  {
    document.getElementById('ajax_login_error_box').parentNode.removeChild(document.getElementById('ajax_login_error_box'));
  }
  if ( document.getElementById('autoCaptcha') )
  {
    var to = fly_out_top(document.getElementById('autoCaptcha'), false, true);
    setTimeout(function() {
        var d = document.getElementById('autoCaptcha');
        d.parentNode.removeChild(d);
      }, to);
  }
  // Encryption: preprocessor
  if ( real )
  {
    var do_dh = true;
  }
  else if ( document.getElementById('ajax_login_field_dh') )
  {
    var do_dh = document.getElementById('ajax_login_field_dh').checked;
  }
  else
  {
    // The user probably clicked ok when the form wasn't in there.
    return false;
  }
  if ( !username )
  {
    var username = document.getElementById('ajax_login_field_username').value;
  }
  if ( !password )
  {
    var password = document.getElementById('ajax_login_field_password').value;
  }
  if ( !captcha && document.getElementById('ajax_login_field_captcha') )
  {
    var captcha = document.getElementById('ajax_login_field_captcha').value;
  }
  
  if ( do_dh )
  {
    ajaxLoginSetStatus(AJAX_STATUS_GENERATING_KEY);
    if ( !real )
    {
      // Wait while the browser updates the login window
      setTimeout(function()
        {
          ajaxLoginSubmitForm(true, username, password, captcha);
        }, 200);
      return true;
    }
    // Perform Diffie Hellman stuff
    var dh_priv = dh_gen_private();
    var dh_pub = dh_gen_public(dh_priv);
    var secret = dh_gen_shared_secret(dh_priv, logindata.key_dh);
    // secret_hash is used to verify that the server guesses the correct secret
    var secret_hash = hex_sha1(secret);
    // crypt_key is the actual AES key
    var crypt_key = (hex_sha256(secret)).substr(0, (keySizeInBits / 4));
  }
  else
  {
    var crypt_key = logindata.key_aes;
  }
  
  ajaxLoginSetStatus(AJAX_STATUS_LOGGING_IN);
  
  // Encrypt the password and username
  var userinfo = toJSONString({
      username: username,
      password: password
    });
  var crypt_key_ba = hexToByteArray(crypt_key);
  userinfo = stringToByteArray(userinfo);
  
  userinfo = rijndaelEncrypt(userinfo, crypt_key_ba, 'ECB');
  userinfo = byteArrayToHex(userinfo);
  // Encrypted username and password (serialized with JSON) are now in the userinfo string
  
  // Collect other needed information
  if ( logindata.captcha_hash )
  {
    var captcha_hash = logindata.captcha_hash;
    var captcha_code = captcha;
  }
  else
  {
    var captcha_hash = false;
    var captcha_code = false;
  }
  
  // Ship it across the 'net
  if ( do_dh )
  {
    var json_packet = {
      mode: 'login_dh',
      userinfo: userinfo,
      captcha_code: captcha_code,
      captcha_hash: captcha_hash,
      dh_public_key: logindata.key_dh,
      dh_client_key: dh_pub,
      dh_secret_hash: secret_hash,
      level: logindata.user_level
    }
  }
  else
  {
    var json_packet = {
      mode: 'login_aes',
      userinfo: userinfo,
      captcha_code: captcha_code,
      captcha_hash: captcha_hash,
      key_aes: hex_md5(crypt_key),
      level: logindata.user_level
    }
  }
  ajaxLoginPerformRequest(json_packet);
}

function ajaxLoginShowFriendlyError(response)
{
  if ( !response.respawn_info )
    return false;
  if ( !response.error_code )
    return false;
  var text = ajaxLoginGetErrorText(response);
  if ( document.getElementById('ajax_login_error_box') )
  {
    // console.info('Reusing existing error-box');
    document.getElementById('ajax_login_error_box').innerHTML = text;
    return true;
  }
  
  // console.info('Drawing new error-box');
  
  // calculate position for the top of the box
  var mb_bottom = $('messageBoxButtons').Top() + $('messageBoxButtons').Height();
  // if the box isn't done flying in yet, just estimate
  if ( mb_bottom < ( getHeight() / 2 ) )
  {
    mb_bottom = ( getHeight() / 2 ) + 120;
  }
  var win_bottom = getHeight() + getScrollOffset();
  var top = mb_bottom + ( ( win_bottom - mb_bottom ) / 2 ) - 32;
  // left position = 0.2 * window_width, seeing as the box is 60% width this works hackishly but nice and quick
  var left = getWidth() * 0.2;
  
  // create the div
  var errbox = document.createElement('div');
  errbox.className = 'error-box-mini';
  errbox.style.position = 'absolute';
  errbox.style.width = '60%';
  errbox.style.top = top + 'px';
  errbox.style.left = left + 'px';
  errbox.innerHTML = text;
  errbox.id = 'ajax_login_error_box';
  
  var body = document.getElementsByTagName('body')[0];
  body.appendChild(errbox);
}

function ajaxLoginGetErrorText(response)
{
  switch ( response.error_code )
  {
    default:
      return $lang.get('user_err_' + response.error_code);
      break;
    case 'locked_out':
      if ( response.respawn_info.lockout_info.lockout_policy == 'lockout' )
      {
        return $lang.get('user_err_locked_out', { 
                  lockout_threshold: response.respawn_info.lockout_info.lockout_threshold,
                  lockout_duration: response.respawn_info.lockout_info.lockout_duration,
                  time_rem: response.respawn_info.lockout_info.time_rem,
                  plural: ( response.respawn_info.lockout_info.time_rem == 1 ) ? '' : $lang.get('meta_plural'),
                  captcha_blurb: ''
                });
        break;
      }
    case 'invalid_credentials':
      var base = $lang.get('user_err_invalid_credentials');
      if ( response.respawn_info.locked_out )
      {
        base += ' ';
        var captcha_blurb = '';
        switch(response.respawn_info.lockout_info.lockout_policy)
        {
          case 'captcha':
            captcha_blurb = $lang.get('user_err_locked_out_captcha_blurb');
            break;
          case 'lockout':
            break;
          default:
            base += 'WTF? Shouldn\'t be locked out with lockout policy set to disable.';
            break;
        }
        base += $lang.get('user_err_locked_out', { 
                  captcha_blurb: captcha_blurb,
                  lockout_threshold: response.respawn_info.lockout_info.lockout_threshold,
                  lockout_duration: response.respawn_info.lockout_info.lockout_duration,
                  time_rem: response.respawn_info.lockout_info.time_rem,
                  plural: ( response.respawn_info.lockout_info.time_rem == 1 ) ? '' : $lang.get('meta_plural')
                });
      }
      else if ( response.respawn_info.lockout_info.lockout_policy == 'lockout' || response.respawn_info.lockout_info.lockout_policy == 'captcha' )
      {
        // if we have a lockout policy of captcha or lockout, then warn the user
        switch ( response.respawn_info.lockout_info.lockout_policy )
        {
          case 'captcha':
            base += $lang.get('user_err_invalid_credentials_lockout', { 
                fails: response.respawn_info.lockout_info.lockout_fails,
                lockout_threshold: response.respawn_info.lockout_info.lockout_threshold,
                lockout_duration: response.respawn_info.lockout_info.lockout_duration
              });
            break;
          case 'lockout':
            break;
        }
      }
      return base;
      break;
  }
}


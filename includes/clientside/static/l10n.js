/*
 * Enano client-side localization library
 */

var Language = function(lang_id)
{
  if ( typeof(enano_lang) != 'object' )
    return false;
  if ( typeof(enano_lang[lang_id]) != 'object' )
    return false;
  this.strings = enano_lang[lang_id];
  
  this.get = function(string_id, subst)
  {
    var catname = string_id.substr(0, string_id.indexOf('_'));
    var string_name = string_id.substr(string_id.indexOf('_') + 1);
    if ( typeof(this.strings[catname]) != 'object' )
      return string_id;
    if ( typeof(this.strings[catname][string_name]) != 'string' )
      return string_id;
    return this.perform_subst(this.strings[catname][string_name], subst) + '**';
  }
  
  this.perform_subst = function(str, subst)
  {
    var this_regex = /%this\.([a-z0-9_]+)%/;
    var match;
    while ( str.match(this_regex) )
    {
      match = str.match(this_regex);
      str = str.replace(match[0], this.get(match[1]));
    }
    // hackish workaround for %config.*%
    str = str.replace(/%config\.([a-z0-9_]+)%/g, '%$1%');
    if ( typeof(subst) == 'object' )
    {
      for ( var i in subst )
      {
        if ( !i.match(/^([a-z0-9_]+)$/) )
          continue;
        var regex = new RegExp('%' + i + '%', 'g');
        str = str.replace(regex, subst[i]);
      }
    }
    return str;
  }
  
}

var $lang;

var language_onload = function()
{
  $lang = new Language(ENANO_LANG_ID);
  // for debugging :-)
  // alert( $lang.get('user_err_invalid_credentials_lockout_captcha', { lockout_fails: '3', lockout_threshold: '5', lockout_duration: '15' }) );
}

addOnloadHook(language_onload);


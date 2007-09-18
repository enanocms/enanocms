/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2007 Dan Fuhry
 * pwstrength - Password evaluation and strength testing algorithm
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function password_score_len(password)
{
  if ( typeof(password) != "string" )
  {
    return -10;
  }
  var len = password.length;
  var score = len - 7;
  return score;
}

function password_score(password)
{
  if ( typeof(password) != "string" )
  {
    return -10;
  }
  var score = 0;
  var debug = [];
  // length check
  var lenscore = password_score_len(password);
  
  debug.push(''+lenscore+' points for length');
  
  score += lenscore;
    
  var has_upper_lower = false;
  var has_symbols     = false;
  var has_numbers     = false;
  
  // contains uppercase and lowercase
  if ( password.match(/[A-z]+/) && password.toLowerCase() != password )
  {
    score += 1;
    has_upper_lower = true;
    debug.push('1 point for having uppercase and lowercase');
  }
  
  // contains symbols
  if ( password.match(/[^A-z0-9]+/) )
  {
    score += 1;
    has_symbols = true;
    debug.push('1 point for having nonalphanumeric characters (matching /[^A-z0-9]+/)');
  }
  
  // contains numbers
  if ( password.match(/[0-9]+/) )
  {
    score += 1;
    has_numbers = true;
    debug.push('1 point for having numbers');
  }
  
  if ( has_upper_lower && has_symbols && has_numbers && password.length >= 9 )
  {
    // if it has uppercase and lowercase letters, symbols, and numbers, and is of considerable length, add some serious points
    score += 4;
    debug.push('4 points for having uppercase and lowercase, numbers, and nonalphanumeric and being more than 8 characters');
  }
  else if ( has_upper_lower && has_symbols && has_numbers && password.length >= 6 )
  {
    // still give some points for passing complexity check
    score += 2;
    debug.push('2 points for having uppercase and lowercase, numbers, and nonalphanumeric');
  }
  else if(( ( has_upper_lower && has_symbols ) ||
            ( has_upper_lower && has_numbers ) ||
            ( has_symbols && has_numbers ) ) && password.length >= 6 )
  {
    // if 2 of the three main complexity checks passed, add a point
    score += 1;
    debug.push('1 point for having 2 of 3 complexity checks');
  }
  else if ( ( !has_upper_lower && !has_numbers && has_symbols ) ||
            ( !has_upper_lower && !has_symbols && has_numbers ) ||
            ( !has_numbers && !has_symbols && has_upper_lower ) )
  {
    score += -2;
    debug.push('-2 points for only meeting 1 complexity check');
  }
  else if ( password.match(/^[0-9]*?([a-z]+)[0-9]?$/) )
  {
    // password is something like magnum1 which will be cracked in seconds
    score += -4;
    debug.push('-4 points for being of the form [number][word][number], which is easily cracked');
  }
  else if ( !has_upper_lower && !has_numbers && !has_symbols )
  {
    // this is if somehow the user inputs a password that doesn't match the rule above, but still doesn't contain upper and lowercase, numbers, or symbols
    debug.push('-3 points for not meeting any complexity checks');
    score += -3;
  }
  
  //
  // Repetition
  // Example: foobar12345 should be deducted points, where f1o2o3b4a5r should be given points
  // None of the positive ones kick in unless the length is at least 8
  //
  
  if ( password.match(/([A-Z][A-Z][A-Z][A-Z]|[a-z][a-z][a-z][a-z])/) )
  {
    debug.push('-2 points for having more than 4 letters of the same case in a row');
    score += -2;
  }
  else if ( password.match(/([A-Z][A-Z][A-Z]|[a-z][a-z][a-z])/) )
  {
    debug.push('-1 points for having more than 3 letters of the same case in a row');
    score += -1;
  }
  else if ( password.match(/[A-z]/) && !password.match(/([A-Z][A-Z][A-Z]|[a-z][a-z][a-z])/) && password.length >= 8 )
  {
    debug.push('1 point for never having more than 2 letters of the same case in a row');
    score += 1;
  }
  
  if ( password.match(/[0-9][0-9][0-9][0-9]/) )
  {
    debug.push('-2 points for having 4 or more numbers in a row');
    score += -2;
  }
  else if ( password.match(/[0-9][0-9][0-9]/) )
  {
    debug.push('-1 points for having 3 or more numbers in a row');
    score += -1;
  }
  else if ( has_numbers && !password.match(/[0-9][0-9][0-9]/) && password.length >= 8 )
  {
    debug.push('1 point for never more than 2 numbers in a row');
    score += -1;
  }
  
  // make passwords like fooooooooooooooooooooooooooooooooooooo totally die by subtracting a point for each character repeated at least 3 times in a row
  var prev_char = '';
  var warn = false;
  var loss = 0;
  for ( var i = 0; i < password.length; i++ )
  {
    var chr = password.substr(i, 1);
    if ( chr == prev_char && warn )
    {
      loss += -1;
    }
    else if ( chr == prev_char && !warn )
    {
      warn = true;
    }
    else if ( chr != prev_char && warn )
    {
      warn = false;
    }
    prev_char = chr;
  }
  if ( loss < 0 )
  {
    debug.push(''+loss+' points for immediate character repetition');
    score += loss;
    // this can bring the score below -10 sometimes
    if ( score < -10 )
    {
      debug.push('Score set to -10 because it went below that floor');
      score = -10;
    }
  }
  
  var debug_txt = "<b>How this score was calculated</b>\nYour score was tallied up based on an extensive algorithm which outputted\nthe following scores based on traits of your password. Above you can see the\ncomposite score; your individual scores based on certain tests are below.\n\nThe scale is open-ended, with a minimum score of -10. 10 is very strong, 4\nis strong, 1 is good and -3 is fair. Below -3 scores \"Weak.\"\n\n";
  for ( var i = 0; i < debug.length; i++ )
  {
    debug_txt += debug[i] + "\n";
  }
  
  if ( window.console )
    window.console.info(debug_txt);
  else if ( document.getElementById('passdebug') )
    document.getElementById('passdebug').innerHTML = debug_txt;
  
  return score;
}

function password_score_draw(score)
{
  // some colors are from the Gmail sign-up form
  if ( score >= 10 )
  {
    var color = '#000000';
    var fgcolor = '#666666';
    var str = 'Very strong (score: '+score+')';
  }
  else if ( score > 3 )
  {
    var color = '#008000';
    var fgcolor = '#004000';
    var str = 'Strong (score: '+score+')';
  }
  else if ( score >= 1 )
  {
    var color = '#6699cc';
    var fgcolor = '#4477aa';
    var str = 'Good (score: '+score+')';
  }
  else if ( score >= -3 )
  {
    var color = '#f5ac00';
    var fgcolor = '#ffcc33';
    var str = 'Fair (score: '+score+')';
  }
  else
  {
    var color = '#aa0033';
    var fgcolor = '#FF6060';
    var str = 'Weak (score: '+score+')';
  }
  return {
    color: color,
    fgcolor: fgcolor,
    str: str
  };
}

function password_score_field(field)
{
  var indicator = false;
  if ( field.nextSibling )
  {
    if ( field.nextSibling.className == 'password-checker' )
    {
      indicator = field.nextSibling;
    }
  }
  if ( !indicator )
  {
    var indicator = document.createElement('span');
    indicator.className = 'password-checker';
    if ( field.nextSibling )
    {
      field.parentNode.insertBefore(indicator, field.nextSibling);
    }
    else
    {
      field.parentNode.appendChild(indicator);
    }
  }
  var score = password_score(field.value);
  var data = password_score_draw(score);
  indicator.style.color = data.color;
  indicator.style.fontWeight = 'bold';
  indicator.innerHTML = ' ' + data.str;
  
  if ( document.getElementById('pwmeter') )
  {
    var div = document.getElementById('pwmeter');
    div.style.width = '250px';
    score += 10;
    if ( score > 25 )
      score = 25;
    div.style.backgroundColor = data.color;
    var width = Math.round( score * (250 / 25) );
    div.innerHTML = '<div style="width: '+width+'px; background-color: '+data.fgcolor+'; height: 8px;"></div>';
  }
}


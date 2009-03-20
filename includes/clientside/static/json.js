/*
    json.js
    2007-03-20

    All of the code contained within this file is released into
    the public domain. Optionally, you may distribute this code
    under the terms of the GNU General Public License as well
    (public domain licensing allows this).
    
*/

function toJSONString(input)
{
  if ( window.JSON )
  {
    return window.JSON.stringify(input);
  }
  var m = {
          '\b': '\\b',
          '\t': '\\t',
          '\n': '\\n',
          '\f': '\\f',
          '\r': '\\r',
          '"' : '\\"',
          '\\': '\\\\'
          };
  var t = typeof(input);
  switch(t)
  {
    case 'string':
      if (/["\\\x00-\x1f]/.test(input))
      {
          return '"' + input.replace(/([\x00-\x1f\\"])/g, function(a, b)
            {
              var c = m[b];
              if (c) {
                  return c;
              }
              c = b.charCodeAt();
              return '\\u00' +
                  Math.floor(c / 16).toString(16) +
                  (c % 16).toString(16);
          }) + '"';
      }
      return '"' + input + '"';
      break;
    case 'array':
      var a = ['['],  
            b,          
            i,          
            l = input.length,
            v;          

        var p = function (s) {

            if (b) {
                a.push(',');
            }
            a.push(s);
            b = true;
        }

        for (i = 0; i < l; i += 1) {
            v = input[i];
            switch (typeof v) {
            case 'object':
              if (v) {
                p(toJSONString(v));
              } else {
                p("null");
              }
              break;
            case 'array':
            case 'string':
            case 'number':
            case 'boolean':
                p(toJSONString(v));
            }
        }

        a.push(']');
        return a.join('');
      break;
    case 'date':
      var f = function (n)
      {
        return n < 10 ? '0' + n : n;
      }
      return '"' + input.getFullYear() + '-' +
                 f(input.getMonth() + 1) + '-' +
                 f(input.getDate()) + 'T' +
                 f(input.getHours()) + ':' +
                 f(input.getMinutes()) + ':' +
                 f(input.getSeconds()) + '"';
                 
    case 'boolean':
      return String(input);
      break;
    case 'number':
      return isFinite(input) ? String(input) : "null";
      break;
    case 'object':
      var a = ['{'],  
          b,          
          k,          
          v;          

      var p = function (s)
      {
        if (b)
        {
          a.push(',');
        }
        a.push(toJSONString(k), ':', s);
        b = true;
      }

      for (k in input) 
      {
        if (input.hasOwnProperty(k))
        {
          v = input[k];
          switch (typeof v) {

            case 'object':
              if (v) {
                p(toJSONString(v));
              } else {
                p("null");
              }
              break;
            case 'string':
            case 'number':
            case 'boolean':
              p(toJSONString(v));
              break;
          }
        }
      }

      a.push('}');
      return a.join('');
      break;
  }
}

function parseJSON(string, filter)
{
  if ( window.JSON )
  {
    return window.JSON.parse(string);
  }
  
  try {
    if (/^("(\\.|[^"\\\n\r])*?"|[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t])+?$/.
            test(string))
    {
  
        var j = eval('(' + string + ')');
        if (typeof filter === 'function') {
  
            function walk(k, v)
            {
                if (v && typeof v === 'object') {
                    for (var i in v) {
                        if (v.hasOwnProperty(i)) {
                            v[i] = walk(i, v[i]);
                        }
                    }
                }
                return filter(k, v);
            }
  
            j = walk('', j);
        }
        return j;
    }
  } catch (e) {
  
  }
  throw new SyntaxError("parseJSON");
}

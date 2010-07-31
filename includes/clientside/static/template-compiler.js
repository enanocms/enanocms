// An implementation of Enano's template compiler in Javascript. Same exact API
// as the PHP version - constructor accepts text, then the assign_vars, assign_bool, and run methods.

window.templateParser = function(text)
{
	this.tpl_code    = text;
	this.tpl_strings = new Object();
	this.tpl_bool    = new Object();
	this.assign_vars = __tpAssignVars;
	this.assign_bool = __tpAssignBool;
	this.fetch_hook  = __tpFetchHook;
	this.run         = __tpRun;
}

window.__tpAssignVars = function(vars)
{
	for(var i in vars)
	{
		this.tpl_strings[i] = vars[i];
	}
}

window.__tpAssignBool = function(vars)
{
	for(var i in vars)
	{
		this.tpl_bool[i] = ( vars[i] ) ? true : false; 
	}
}

window.__tpRun = function()
{
	if(typeof(this.tpl_code) == 'string')
	{
		tpl_code = __tpCompileTemplate(this.tpl_code);
		try {
			compiled = eval(tpl_code);
		}
		catch(e)
		{
			alert(e);
			load_component('acl');
			aclDebug(tpl_code);
		}
		return compiled;
	}
	return false;
}

window.__tpCompileTemplate = function(code)
{
	// Compile plaintext/template code to javascript code
	code = code.replace(/\\/g, "\\\\");
	code = code.replace(/\'/g,  "\\'");
	code = code.replace(/\"/g,  '\\"');
	code = code.replace(new RegExp(unescape('%0A'), 'g'), '\\n');
	code = "'" + code + "'";
	code = code.replace(/\{([A-z0-9_-]+)\}/ig, "' + this.tpl_strings['$1'] + '");
	code = code.replace(/\{lang:([a-z0-9_]+)\}/g, "' + $lang.get('$1') + '");
	code = code.replace(/\<!-- IFSET ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- BEGINELSE \1 --\>([\s\S]*?)\<!-- END \1 --\>/ig, "' + ( ( typeof(this.tpl_strings['$1']) == 'string' ) ? '$2' : '$3' ) + '");
	code = code.replace(/\<!-- IFSET ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- END \1 --\>/ig, "' + ( ( typeof(this.tpl_strings['$1']) == 'string' ) ? '$2' : '' ) + '");
	code = code.replace(/\<!-- BEGIN ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- BEGINELSE \1 --\>([\s\S]*?)\<!-- END \1 --\>/ig, "' + ( ( this.tpl_bool['$1'] == true ) ? '$2' : '$3' ) + '");
	code = code.replace(/\<!-- BEGIN ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- END \1 --\>/ig, "' + ( ( this.tpl_bool['$1'] == true ) ? '$2' : '' ) + '");
	code = code.replace(/\<!-- BEGINNOT ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- END \1 --\>/ig, "' + ( ( this.tpl_bool['$1'] == false ) ? '$2' : '' ) + '");
	code = code.replace(/\<!-- HOOK ([A-z0-9_-]+) --\>/ig, "' + this.fetch_hook('$1') + '");
	return code;
}

window.__tpExtractVars = function(code)
{
	code = code.replace('\\', "\\\\");
	code = code.replace("'",  "\\'");
	code = code.replace('"',  '\\"');
	code = code.replace(new RegExp(unescape('%0A'), 'g'), "\\n");
	code = code.match(/\<!-- VAR ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- ENDVAR \1 -->/g);
	code2 = '';
	for(var i in code)
		if(typeof(code[i]) == 'string')
			code2 = code2 + code[i];
	code = code2.replace(/\<!-- VAR ([A-z0-9_-]+) --\>([\s\S]*?)\<!-- ENDVAR \1 -->/g, "'$1' : \"$2\",");
	code = '( { ' + code + ' "________null________" : false } )';
	vars = eval(code);
	return vars;
}

window.__tpFetchHook = function(hookid)
{
	var _ob = '';
	window.Template = this;
	window.Echo = function(h)
	{
		_ob += h;
	}
	eval(setHook('thook_' + hookid));
	window.Echo = window.Template = false;
	return _ob;
}


//
// Add the wrappers for preformatted tags within content.
//

addOnloadHook(function()
	{
		preformat_process_all();
	});

function preformat_process_all()
{
	var aec = document.getElementById('ajaxEditContainer');
	if ( !aec )
		return false;
	var pres = aec.getElementsByTagName('pre');
	for ( var i = 0; i < pres.length; i++ )
	{
		if ( pres[i].hasButtonPanel )
			continue;
		pres[i].hasButtonPanel = true;
		
		var btnp = document.createElement('div');
		btnp.mypre = pres[i];
		btnp.className = 'preformat-panel';
		btnp.appendChild(document.createTextNode($lang.get('onpage_pre_lbl_code')));
		btnp.appendChild(document.createTextNode(' <'));
		var a_sel = document.createElement('a');
		a_sel.href = '#';
		a_sel.onclick = function()
			{
				preformat_handle_select_click(this.parentNode);
				return false;
			};
		a_sel.appendChild(document.createTextNode($lang.get('onpage_pre_btn_select')));
		btnp.appendChild(a_sel);
		btnp.appendChild(document.createTextNode('> <'));
		var a_pop = document.createElement('a');
		a_pop.href = '#';
		a_pop.onclick = function()
			{
				preformat_handle_popup_click(this.parentNode);
				return false;
			};
		a_pop.appendChild(document.createTextNode($lang.get('onpage_pre_btn_popup')));
		btnp.appendChild(a_pop);
		btnp.appendChild(document.createTextNode('>'));
		pres[i].parentNode.insertBefore(btnp, pres[i]);
	}
}

function preformat_handle_select_click(btnp)
{
	var pre = btnp.mypre;
	select_element(pre);
}

function preformat_handle_popup_click(btnp)
{
	var pre = btnp.mypre;
	var text = pre.innerHTML;
	var newwin = window.open('about:blank', 'codepopwin', 'width=800,height=600,status=no,toolbars=no,toolbar=no,address=no,scroll=yes');
	newwin.document.open();
	newwin.document.write('<html><head><title>' + $lang.get('onpage_pre_popup_title') + '</title></head><body>');
	newwin.document.write('<pre>' + text + '</pre>');
	newwin.document.write('</body></html>');
	newwin.document.close();
}

function select_element(element)
{
	if (IE)
	{
		// IE
		var range = document.body.createTextRange();
		range.moveToElementText(element);
		range.select();
	}
	else if (is_Gecko || is_Opera)
	{
		// Mozilla/Opera
		var selection = window.getSelection();
		var range = document.createRange();
		range.selectNodeContents(element);
		selection.removeAllRanges();
		selection.addRange(range);
	}
	else if (is_Webkit)
	{
		// Safari (Chrome?)
		var selection = window.getSelection();
		selection.setBaseAndExtent(element, 0, element, 1);
	}
}


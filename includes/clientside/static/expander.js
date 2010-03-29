/*
 * Expandable fieldsets
 */

var expander_onload = function()
{
	var sets = document.getElementsByTagName('fieldset');
	if ( sets.length < 1 )
		return false;
	var init_us = [];
	for ( var index = 0; index < sets.length; index++ )
	{
		var mode = sets[index].getAttribute('enano:expand');
		if ( mode == 'closed' || mode == 'open' )
		{
			init_us.push(sets[index]);
		}
	}
	for ( var k = 0; k < init_us.length; k++ )
	{
		expander_init_element(init_us[k]);
	}
}

function expander_init_element(el)
{
	// get the legend tag
	var legend = el.getElementsByTagName('legend')[0];
	if ( !legend )
		return false;
	// existing content
	var existing_inner = legend.innerHTML;
	// blank the innerHTML and replace it with a link
	legend.innerHTML = '';
	var button = document.createElement('a');
	button.className = 'expander expander-open';
	button.innerHTML = existing_inner;
	button.href = '#';
	
	legend.appendChild(button);
	
	button.onclick = function()
	{
		try
		{
			expander_handle_click(this);
		}
		catch(e)
		{
			console.debug('Exception caught: ', e);
		}
		return false;
	}
	
	if ( el.getAttribute('enano:expand') == 'closed' )
	{
		expander_close(el);
	}
}

function expander_handle_click(el)
{
	if ( el.parentNode.parentNode.tagName != 'FIELDSET' )
		return false;
	var parent = el.parentNode.parentNode;
	if ( parent.getAttribute('enano:expand') == 'closed' )
	{
		expander_open(parent);
	}
	else
	{
		expander_close(parent);
	}
}

function expander_close(el)
{
	var children = el.childNodes;
	for ( var i = 0; i < children.length; i++ )
	{
		var child = children[i];
		if ( child.tagName == 'LEGEND' )
		{
			var a = child.getElementsByTagName('a')[0];
			$dynano(a).rmClass('expander-open');
			$dynano(a).addClass('expander-closed');
			continue;
		}
		if ( child.style )
		{
			child.expander_meta_old_state = child.style.display;
			child.style.display = 'none';
		}
	}
	el.expander_meta_padbak = el.style.padding;
	el.setAttribute('enano:expand', 'closed');
}

function expander_open(el)
{
	var children = el.childNodes;
	for ( var i = 0; i < children.length; i++ )
	{
		var child = children[i];
		if ( child.tagName == 'LEGEND' )
		{
			var a = child.getElementsByTagName('a')[0];
			$dynano(a).rmClass('expander-closed');
			$dynano(a).addClass('expander-open');
			continue;
		}
		if ( child.expander_meta_old_state && child.style )
		{
			child.style.display = child.expander_meta_old_state;
			child.expander_meta_old_state = null;
		}
		else
		{
			if ( child.style )
			{
				child.style.display = null;
			}
		}
	}
	if ( el.expander_meta_padbak )
	{
		el.style.padding = el.expander_meta_padbak;
		el.expander_meta_padbak = null;
	}
	else
	{
		el.style.padding = null;
	}
	el.setAttribute('enano:expand', 'open');
}

addOnloadHook(expander_onload);

/*
 * Enano - an open source wiki-like CMS
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 * Some code found in this script is not licensed under the GNU General Public License; however, it is believed that the license terms shown
 * below are GPL-compatible. If you believe that this is not the case, please drop a note to support@enano.homelinux.org.
 */
 
/*
 * Title: Tigra Tree
 * Description: See the demo at url
 * URL: http://www.softcomplex.com/products/tigra_tree_menu/
 * Version: 1.1
 * Date: 11-12-2002 (mm-dd-yyyy)
 * Notes: This script is free. Visit official site for further details.
 * 
 * There is no license fee or royalty fee to be paid at any time for using the Tigra Tree Menu v1.x
 * You may include the source code or modified source code within your own projects for either personal
 * or commercial use but excluding the restrictions outlined below. The following restrictions apply to
 * all parts of the component, including all source code, samples and documentation.
 *
 * - Header block of script file (tree.js) CAN NOT be modified or removed.
 * - The above items CAN NOT be sold as are, either individually or together.
 * - The above items CAN NOT be modified and then sold as a library component, either individually or together.
 *
 * Due to the unclear licensing conditions on this script, I contacted the author, who said that because Enano
 * is not a "competing product" I was allowed to treat the code as GPL. The conversation can be seen in the
 * /licenses/tigra-menu.html document in the Enano distribution.
 */
 
var ck = readCookie('admin_menu_state');
if(ck)
{
  var ck = parseInt(ck);
}
else
{
  ck = 0;
}
ck = ( isNaN(ck) ) ? 0 : ck;

function tree (a_items, a_template) {

	this.a_tpl      = a_template;
	this.a_config   = a_items;
	this.o_root     = this;
	this.a_index    = [];
	this.o_selected = null;
	this.n_depth    = -1;
	
	var o_icone = new Image(),
		o_iconl = new Image();
	o_icone.src = a_template['icon_e'];
	o_iconl.src = a_template['icon_l'];
	a_template['im_e'] = o_icone;
	a_template['im_l'] = o_iconl;
	for (var i = 0; i < 64; i++)
		if (a_template['icon_' + i]) {
			var o_icon = new Image();
			a_template['im_' + i] = o_icon;
			o_icon.src = a_template['icon_' + i];
		}
	
	this.toggle = function (n_id,co) { var o_item = this.a_index[n_id]; o_item.open(o_item.b_opened,co); };
  this.open   = function (n_id,co) { var o_item = this.a_index[n_id]; o_item.open(false,co); };
	this.select = function (n_id)    { return this.a_index[n_id].select(); };
	this.mout   = function (n_id)    { this.a_index[n_id].upstatus(true) };
	this.mover  = function (n_id)    { this.a_index[n_id].upstatus() };

	this.a_children = [];
	for (var i = 0; i < a_items.length; i++)
  {
		new tree_item(this, i);
  }

	this.n_id = trees.length;
	trees[this.n_id] = this;
	
	for (var i = 0; i < this.a_children.length; i++) {
		document.write(this.a_children[i].init());
		this.a_children[i].open(false, true);
	}
}
function tree_item (o_parent, n_order) {

	this.n_depth  = o_parent.n_depth + 1;
	this.a_config = o_parent.a_config[n_order + (this.n_depth ? 2 : 0)];
	if (!this.a_config) return;

	this.o_root    = o_parent.o_root;
	this.o_parent  = o_parent;
	this.n_order   = n_order;
	this.b_opened  = !this.n_depth;

	this.n_id = this.o_root.a_index.length;
	this.o_root.a_index[this.n_id] = this;
	o_parent.a_children[n_order] = this;

	this.a_children = [];
	for (var i = 0; i < this.a_config.length - 2; i++)
  {
		new tree_item(this, i);
  }
  
	this.get_icon = item_get_icon;
	this.open     = item_open;
	this.select   = item_select;
	this.init     = item_init;
	this.upstatus = item_upstatus;
	this.is_last  = function () { return this.n_order == this.o_parent.a_children.length - 1 };
  
  // CODE MODIFICATION
  // added:
    // Do we need to open the branch?
    n = Math.pow(2, this.n_id);
    var disp = ( ck & n ) ? true : false;
    s = ( disp ) ? 'open' : 'closed';
    //if(s=='open') alert(this.n_id + ': ' + s);
    if(disp) setTimeout('trees['+trees.length+'].open('+this.n_id+', true);', 10);
  // END MODIFICATIONS
}

function item_open (b_close, nocookie) {
  //alert('item_open('+this.n_id+');');
	var o_idiv = get_element('i_div' + this.o_root.n_id + '_' + this.n_id);
	if (!o_idiv) return;
	
	if (!o_idiv.innerHTML) {
		var a_children = [];
		for (var i = 0; i < this.a_children.length; i++)
    {
			a_children[i]= this.a_children[i].init();
    }
		o_idiv.innerHTML = a_children.join('');
	}
	o_idiv.style.display = (b_close ? 'none' : 'block');
  
  // CODE MODIFICATION
  // added:
    if(!nocookie)
    {
      // The idea here is to use a bitwise field. Nice 'n simple, right? Object of the game is to assemble
      // a binary number that depicts the open/closed state of the entire menu in one cookie.
      n = Math.pow(2, this.n_id);
      ck = ( b_close ) ? ck-n : ck+n;
      //alert('open(): doing the cookie routine for id '+this.n_id+"\nResult for bitwise op: "+ck);
      createCookie('admin_menu_state', ck, 365);
    } else {
      //alert('open(): NOT doing the cookie routine for id '+this.n_id);
    }
  // END MODIFICATIONS
	
	this.b_opened = !b_close;
	var o_jicon = document.images['j_img' + this.o_root.n_id + '_' + this.n_id],
		o_iicon = document.images['i_img' + this.o_root.n_id + '_' + this.n_id];
	if (o_jicon) o_jicon.src = this.get_icon(true);
	if (o_iicon) o_iicon.src = this.get_icon();
	this.upstatus();
}

function item_select (b_deselect) {
	if (!b_deselect) {
		var o_olditem = this.o_root.o_selected;
		this.o_root.o_selected = this;
		if (o_olditem) o_olditem.select(true);
	}
	var o_iicon = document.images['i_img' + this.o_root.n_id + '_' + this.n_id];
	if (o_iicon) o_iicon.src = this.get_icon();
	get_element('i_txt' + this.o_root.n_id + '_' + this.n_id).style.fontWeight = b_deselect ? 'normal' : 'bold';
	
	this.upstatus();
	return Boolean(this.a_config[1]);
}

function item_upstatus (b_clear) {
	window.setTimeout('window.status="' + (b_clear ? '' : this.a_config[0] + (this.a_config[1] ? ' ('+ this.a_config[1] + ')' : '')) + '"', 10);
}

function item_init () {
	var a_offset = [],
		o_current_item = this.o_parent;
	for (var i = this.n_depth; i > 1; i--) {
		a_offset[i] = '<img src="' + this.o_root.a_tpl[o_current_item.is_last() ? 'icon_e' : 'icon_l'] + '" border="0" align="absbottom">';
		o_current_item = o_current_item.o_parent;
	}
	return '<table cellpadding="0" cellspacing="0" border="0"><tr><td nowrap="nowrap">' + (this.n_depth ? a_offset.join('') + (this.a_children.length
		? '<a href="javascript: trees[' + this.o_root.n_id + '].toggle(' + this.n_id + ')" onmouseover="trees[' + this.o_root.n_id + '].mover(' + this.n_id + ')" onmouseout="trees[' + this.o_root.n_id + '].mout(' + this.n_id + ')"><img src="' + this.get_icon(true) + '" border="0" align="absbottom" name="j_img' + this.o_root.n_id + '_' + this.n_id + '"></a>'
		: '<img src="' + this.get_icon(true) + '" border="0" align="absbottom">') : '')
  // CODE MODIFICATION
  // removed: 
	//	+ '<a href="' + this.a_config[1] + '" target="' + this.o_root.a_tpl['target'] + '" onclick="return trees[' + this.o_root.n_id + '].select(' + this.n_id + ')" ondblclick="trees[' + this.o_root.n_id + '].toggle(' + this.n_id + ')" onmouseover="trees[' + this.o_root.n_id + '].mover(' + this.n_id + ')" onmouseout="trees[' + this.o_root.n_id + '].mout(' + this.n_id + ')" class="t' + this.o_root.n_id + 'i" id="i_txt' + this.o_root.n_id + '_' + this.n_id + '"><img src="' + this.get_icon() + '" border="0" align="absbottom" name="i_img' + this.o_root.n_id + '_' + this.n_id + '" class="t' + this.o_root.n_id + 'im">' + this.a_config[0] + '</a></td></tr></table>' + (this.a_children.length ? '<div id="i_div' + this.o_root.n_id + '_' + this.n_id + '" style="display:none"></div>' : '');
  // added:
  + '<a href="' + this.a_config[1] + '" target="' + this.o_root.a_tpl['target'] + '" onclick="return trees[' + this.o_root.n_id + '].select(' + this.n_id + ')" ondblclick="trees[' + this.o_root.n_id + '].toggle(' + this.n_id + ')" onmouseover="trees[' + this.o_root.n_id + '].mover(' + this.n_id + ')" onmouseout="trees[' + this.o_root.n_id + '].mout(' + this.n_id + ')" class="t' + this.o_root.n_id + 'i" id="i_txt' + this.o_root.n_id + '_' + this.n_id + '">' + this.a_config[0] + '</a></td></tr></table>' + (this.a_children.length ? '<div id="i_div' + this.o_root.n_id + '_' + this.n_id + '" style="display:none"></div>' : '');
  // END MODIFICATIONS
  alert('i_div' + this.o_root.n_id + '_' + this.n_id);
}

function item_get_icon (b_junction) {
	return this.o_root.a_tpl['icon_' + ((this.n_depth ? 0 : 32) + (this.a_children.length ? 16 : 0) + (this.a_children.length && this.b_opened ? 8 : 0) + (!b_junction && this.o_root.o_selected == this ? 4 : 0) + (b_junction ? 2 : 0) + (b_junction && this.is_last() ? 1 : 0))];
}

var trees = [];
get_element = document.all ?
	function (s_id) { return document.all[s_id] } :
	function (s_id) { return document.getElementById(s_id) };



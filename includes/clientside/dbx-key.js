function dbx_set_key()
{
  //initialise the docking boxes manager
  var manager = new dbxManager('main');   //session ID [/-_a-zA-Z0-9/]
  
  //onstatechange fires when any group state changes
	manager.onstatechange = function()
	{
		//copy the state string to a local var
		var state = this.state;

		//remove group name and open/close state tokens
		state = state.replace(/sbedit_(left|right)=/ig, '').replace(/[\-\+]/g, '');

		//split into an array
    state = state.split('&');
    
		//output field
		var field = document.getElementById('divOrder_Left');
    field.value = state[0];
    var field = document.getElementById('divOrder_Right');
    field.value = state[1];

		//return value determines whether cookie is set
		return false;
	};
  
  //create new docking boxes group
  var sbedit_left = new dbxGroup(
    'sbedit_left',   // container ID [/-_a-zA-Z0-9/]
    'vertical', // orientation ['vertical'|'horizontal']
    '7',        // drag threshold ['n' pixels]
    'no',       // restrict drag movement to container axis ['yes'|'no']
    '10',       // animate re-ordering [frames per transition, or '0' for no effect]
    'no',       // include open/close toggle buttons ['yes'|'no']
    'open',     // default state ['open'|'closed']
    'open',     // word for "open", as in "open this box"
    'close',    // word for "close", as in "close this box"
    'click-down and drag to move this box',     // sentence for "move this box" by mouse
    'click to %toggle% this box',               // pattern-match sentence for "(open|close) this box" by mouse
    'use the arrow keys to move this box',      // sentence for "move this box" by keyboard
    ', or press the enter key to %toggle% it',  // pattern-match sentence-fragment for "(open|close) this box" by keyboard
    '%mytitle%  [%dbxtitle%]'                   // pattern-match syntax for title-attribute conflicts
  );
  
  //create new docking boxes group
  var sbedit_right = new dbxGroup(
    'sbedit_right',   // container ID [/-_a-zA-Z0-9/]
    'vertical', // orientation ['vertical'|'horizontal']
    '7',        // drag threshold ['n' pixels]
    'no',       // restrict drag movement to container axis ['yes'|'no']
    '10',       // animate re-ordering [frames per transition, or '0' for no effect]
    'no',       // include open/close toggle buttons ['yes'|'no']
    'open',     // default state ['open'|'closed']
    'open',     // word for "open", as in "open this box"
    'close',    // word for "close", as in "close this box"
    'click-down and drag to move this box',     // sentence for "move this box" by mouse
    'click to %toggle% this box',               // pattern-match sentence for "(open|close) this box" by mouse
    'use the arrow keys to move this box',      // sentence for "move this box" by keyboard
    ', or press the enter key to %toggle% it',  // pattern-match sentence-fragment for "(open|close) this box" by keyboard
    '%mytitle%  [%dbxtitle%]'                   // pattern-match syntax for title-attribute conflicts
  );
}

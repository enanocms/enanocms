window.AjaxUpload = function(formid)
{
	load_component(['jquery', 'jquery-ui', 'l10n']);
	
	var theform = document.getElementById(formid);
	theform.AjaxUpload = this;
	this.form = theform;
	
	$(this.form).submit(function()
		{
			return this.AjaxUpload.presubmit();
		});
};

window.zeropad = function(i, ndig)
{
	var s = String(i);
	while ( s.length < ndig )
		s = '0' + s;
	return s;
}

window.humanize_time = function(secs)
{
	var seconds = secs % 60;
	var minutes = (secs - seconds) / 60;
	if ( minutes >= 60 )
	{
		var hours = (minutes - (minutes % 60)) / 60;
		minutes = minutes % 60;
		return zeropad(hours, 2) + ':' + zeropad(minutes, 2) + ':' + zeropad(seconds, 2);
	}
	return zeropad(minutes, 2) + ':' + zeropad(seconds, 2);
}

AjaxUpload.prototype.cancelbit = false;
	
AjaxUpload.prototype.status = function(state)
	{
		if ( state.done )
		{
			$('div.wait-box', this.statusbox).text($lang.get('upload_msg_processing'));
			$('div.progress', this.statusbox).progressbar('value', 100);
		}
		else if ( state.cancel_upload )
		{
			if ( window.stop )
				window.stop();
			else if ( document.execCommand )
				document.execCommand('Stop');
			$('div.wait-box', this.statusbox).addClass('error-box').removeClass('wait-box').text($lang.get('upload_msg_cancelled'));
			
			$('div.progress', this.statusbox).progressbar('value', 100).addClass('ui-progressbar-failure');
		}
		else
		{
			var rawpct = state.bytes_processed / state.content_length;
			var pct = (Math.round((rawpct) * 1000)) / 10;
			var elapsed = state.current_time - state.start_time;
			var rawbps = state.bytes_processed / elapsed;
			var kbps = Math.round((rawbps) / 1024);
			var remain_bytes = state.content_length - state.bytes_processed;
			var remain_time = Math.round(remain_bytes / rawbps);
			if ( pct > 0 )
			$('div.wait-box', this.statusbox).text($lang.get('upload_msg_uploading', {
								percent: pct,
								elapsed: humanize_time(elapsed),
								speed: kbps,
								remain: humanize_time(remain_time)
							}))
					.append('<br /><a href="#" class="cancel"></a>');
			else
				$('div.wait-box', this.statusbox).text($lang.get('upload_msg_starting'))
					.append('<br /><a href="#" class="cancel"></a>');
			
			var au = this;
			$('a.cancel', this.statusbox).text($lang.get('upload_btn_cancel')).click(function()
				{
					au.cancel();
					return false;
				});
				
			$('div.progress', this.statusbox).progressbar('value', pct);
		}
	};
	
AjaxUpload.prototype.cancel = function()
	{
		this.cancelbit = true;
	};

AjaxUpload.prototype.refresh_status = function(au)
	{
		try
		{
			var cancelbit = au.cancelbit ? '&cancel=true' : '';
			au.cancelbit = false;
			ajaxGet(makeUrlNS('Special', 'AjaxUpload', 'form=' + au.form.id + '&uploadstatus=' + au.key + cancelbit), au._incoming_status);
		}
		catch (e)
		{
			alert(e);
		}
	};
	
AjaxUpload.prototype._incoming_status = function(ajax)
	{
		if ( ajax.readyState == 4 )
		{
			var state = parseJSON(ajax.responseText);
			if ( state )
			{
				var au = document.getElementById(state.form).AjaxUpload;
				au.status(state);
				
				if ( !state.done && !state.cancel_upload )
					setTimeout(function()
						{
							au.refresh_status(au)
						}, 250);
			}
		}
	};
	
AjaxUpload.prototype.presubmit = function()
	{
		try
		{
			// create status container and target iframe
			this.statusbox = document.createElement('div');
			this.iframe = document.createElement('iframe');
			this.iframe.AjaxUpload = this;
			$(this.iframe)
				.attr('src', 'about:blank')
				.attr('width', '1')
				.attr('height', '1')
				.attr('frameborder', '0')
				.css('visibility', 'hidden')
				.attr('name', this.form.id + '_frame')
				.load(this._frame_onload);
				
			this.form.parentNode.insertBefore(this.statusbox, this.form);
			this.form.parentNode.insertBefore(this.iframe, this.form);
			this.form.target = this.form.id + '_frame';
			
			this.upload_start();
			
			var have_progress_support = this.form.progress_support.value == 'true';
			if ( have_progress_support )
			{
				this.key = this.form[this.form.upload_progress_name.value].value;
				this.refresh_status(this);
			}
		}
		catch ( e )
		{
			console.debug(e);
			return false;
		}
		
		return true;
	};
	
AjaxUpload.prototype._frame_onload = function()
	{
		var childbody = window[this.AjaxUpload.form.id + '_frame'].document.getElementsByTagName('body')[0];
		window[this.AjaxUpload.form.id + '_frame'].document.close();
		this.AjaxUpload.upload_success(childbody);
	};
	
AjaxUpload.prototype.upload_start = function()
	{
		$(this.statusbox).html('<div class="wait-box">' + $lang.get('upload_msg_starting') + '</div><div class="progress" style="margin-top: 10px;"></div>');
		$('div.progress', this.statusbox).progressbar({ value: 0 });
		$(this.form).hide();
	};
	
AjaxUpload.prototype.upload_success = function(childbody)
	{
		$(this.statusbox).html('<div class="info-box">Upload complete! Result from server:' + childbody.innerHTML + '</div>');
		$('div.info-box', this.statusbox).append('<a href="#">Reset!</a>');
		var form_id = this.form.id;
		$('div.info-box a', this.statusbox).click(function()
			{
				try
				{
					var au = document.getElementById(form_id).AjaxUpload;
					au.reset();
				}
				catch(e) {};
				return false;
			});
	};
	
AjaxUpload.prototype.reset = function()
	{
		this.iframe.parentNode.removeChild(this.iframe);
		this.statusbox.parentNode.removeChild(this.statusbox);
		delete(window[this.form.id + '_frame']);
		$('form#' + this.form.id).show();
		return false;
	};

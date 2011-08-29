function statusBoxController (el, btn) {
	this.el = $(el);
	this.btn = $(btn);
	this.isOpen = false;
	this.clear = function() {
		this.el.text('');
	};
	this.update = function(blah) {
		// console.log('update: ' + blah);
		this.el.prepend(blah + '<br/>');
	};
	this.open = function() {
		this.btn.text('Close Status');
		this.el.slideDown();
		this.isOpen = true;
	};
	this.close = function() {
		this.btn.text('Open Status');
		this.el.slideUp();
		this.isOpen = false;
	};
	this.toggle = function() {
		if (!this.isOpen) this.open();
		else this.close();
	};
	this.btn.click(function() {
		clickHandler($(this).attr('id'));
	});
};
function statusBarController (el, btn) {
	this.el = $(el);
	this.btn = $(btn);
	this.isOpen = false;
	this.open = function() {
		this.el.slideDown();
		this.isOpen = true;
	};
	this.close = function() {
		this.el.slideUp();
		this.isOpen = false;
	};
	this.toggle = function() {
		if (this.isOpen) this.close();
		else this.open();
	};
	this.btn.click(function() {
		clickHandler($(this).attr('id'));
	});
};
function progressBarController(el) {
	this.el = $(el);
	this.pos = 0;
	this.update = function(pos) {
		this.pos = pos;
		this.set();
	};
	this.reset = function() {
		this.pos = 0;
		this.set();
	};
	this.set = function() {
		this.el.css('width', this.pos + '%');
	}
	this.reset();
}
function clickHandler(el) {
	switch (el) {
		case 'statusButton':
			statusBox.toggle();
		break;
		case 'updateMissing':
		case 'updatePlexData':
		case 'updateTheTvDb':
			url = "req.php?r=" + el;
			$.stream(url, {
				enableXDR: false,
				type: "http",
				dataType: "json",
				context: $("#status")[0],
				reconnect: false,
				open: function(event, stream) {
					console.log('Url: ' + url);
					statusBox.clear();
					statusBar.open();
					$('div#buttons button').attr('disabled', true);
				},
				message: function(event) {
					// console.log(event.data);
					switch (event.data.code) {
						case 'sta':
							progressBar.update(event.data.val);
						break;
						case 'info':
							statusBox.update(event.data.msg);
						break;
					}
				},
				close: function() {
					statusBar.close();
					progressBar.reset();
					$('button').attr('disabled', false);
				},
				error: function() {
					console.log('error error error!');
				}
			});
		break;
	}
}

$(function(){
	progressBar = new progressBarController('div#progressBar');
	statusBar = new statusBarController('div#statusBar');
	statusBox = new statusBoxController('div#status', 'button#statusButton');
	
	$('#buttons button').click(function() {
		clickHandler($(this).attr('id'));
	});
	
	$('input[type=checkbox]').click(function(){
		switch ($(this).attr('class')) {
			case 'series': case 'season':
				$(this).parent().next('div').slideToggle();
			break;
			case 'episode':
				$(this).parent().slideUp();
			break;
		}
	});
});
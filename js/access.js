var USER_ACCESS_ALL = 0;
var USER_ACCESS_READ_ONLY = 1;
var USER_ACCESS_TWO_FACTOR = 2;
var USER_ACCESS_TWO_FACTOR_FORCED = 3;
var USER_ACCESS_NONE = 4;

var isChecking = false;

function getCookie(name) {
  var dc = document.cookie;
  var prefix = name + "=";
  var begin = dc.indexOf("; " + prefix);
  if (begin == -1) {
    begin = dc.indexOf(prefix);
    if (begin != 0) return null;
  }
  else
  {
    begin += 2;
    var end = document.cookie.indexOf(";", begin);
    if (end == -1) {
      end = dc.length;
    }
  }
  return unescape(dc.substring(begin + prefix.length, end));
}

function deleteCookie(name, path, domain) {
	path = (path ? ";path=" + path : "");
	domain = (domain ? ";domain=" + domain : "");
	var expiration = "Thu, 01-Jan-1970 00:00:01 GMT";
  document.cookie = name + "=" + path + domain + ";expires=" + expiration;
}

// Check access (r/o if on a backup server, or if on new main server and migrating)
function checkUserServerAccess(user_id, server_id){
	if(window.location.pathname.indexOf('/shared/')===0 ||
			window.location.pathname.indexOf('/public.php', window.location.pathname.length-'/public.php'.length)!==-1){
		return false;
	}
	$(document).off('mousedown', checkUserServerAccess);
	if(isChecking || !$('.viewcontainer:not(.hidden) .crumb.last').length){
		return false;
	}
	isChecking = true;
	
	// First check cookie
	var accessOk = getCookie('oc_access_ok');
	if(accessOk){
		isChecking = false;
		return true;
	}
	
	$.ajax({
		url: OC.filePath('files_sharding', 'ajax', 'get_user_server_access.php'),
		async: true,
		data: {
			user_id: user_id, // can be empty
			server_id: server_id // can be empty
		},
		type: "GET",
		success: function(result) {
			isChecking = false;
			if(result['access'] || result['access']===USER_ACCESS_ALL){
				var res = parseInt(result['access'], 10);
				switch(res){
					case USER_ACCESS_NONE:
						logout('Permission denied');
						break;
					case USER_ACCESS_READ_ONLY:
						disableWrite();
						break;
					case USER_ACCESS_TWO_FACTOR:
						promptSecondFactor();
						break;
					case USER_ACCESS_TWO_FACTOR_FORCED:
						promptSecondFactor();
						break;
				}
				// If we got here, all is well and a cookie has been set.
			}
			else{
				logout('Bad access rights, '+result['access']);
			}
		},
		error: function(result) {
			isChecking = false;
		}
	});
	return false;
}

var notify = false;

function disableWrite(){
	if(!notify){
		if(!$('.viewcontainer:not(.hidden) .access-message').length){
			$('<div class="msg access-message"></div>').insertAfter('.viewcontainer:not(.hidden) .crumb.last');
		}
		//OC.msg.startAction('.viewcontainer:not(.hidden) .access-message',  t("files_sharding", "You only have read access on this server"));
		OC.msg.finishedSaving('.viewcontainer:not(.hidden) .access-message', {status: 'error', data: {message: t("files_sharding", "You only have read access on this server")}});
		notify = true;
	}
	else{
		return false;
	}
    //I'll check prop() vs. attr() here - Ashokaditya
	$('.file-actions .action').not('.action-download').prop( "disabled", true );
	$('.ui-draggable').removeClass('ui-draggable');
	$('.ui-draggable').remove();
	/*$('td.filename').draggable('destroy'); */
    // changed to dragstop which is the correct event for end of drag
    // or dropping the draggable element
    // We need to catch also files dropped from the desktop - Frederik.
	$('body').on('drop', function (e) {
		e.preventDefault();
		return false;
	});
	$('body').on('dragstop', function (e) {
		e.preventDefault();
		return false;
	});
	$('#fileList').on('drag', function (e) {
		e.preventDefault();
		return false;
	});
	var style = $('<style>#controls #upload,  #controls #new, .select-all, .fileselect, .action.delete, .fileactions-wrap, .app-gallery .right, li[data-id="meta_data"], li[data-id="importer_index"] , li[data-id="uploader"] { display: none; }</style>');
	$('html > head').append(style);
  //$("a").draggable('disable');
  //$(".user-menu").draggable('disable');
}

function logout(message){
	$cookieDomain = window.location.hostname.substring(window.location.hostname.indexOf('.'));
	deleteCookie('oc_ok', '/', $cookieDomain);
	deleteCookie('oc_access_ok', '/', $cookieDomain);
	window.location = "/index.php?logout=true&blocked=true"+(message?'&message='+message:'')+'&requesttoken='+oc_requesttoken;
}

function sendSecondFactor(forceNew){
	$.ajax({
		url: OC.filePath('files_sharding', 'ajax', 'send_second_factor.php'),
		async: false,
		data: {
			forceNew: forceNew
		},
		type: "GET",
		success: function(result) {
			if(result.status!="success"){
				logout("Could not send token.");
			}
		},
		error: function(result) {
			logout("Could not send token.");
		}
	});
}

function checkSecondFactor(token){
	$.ajax({
		url: OC.filePath('files_sharding', 'ajax', 'check_second_factor.php'),
		async: false,
		data: {
			token: token
		},
		type: "GET",
		success: function(result) {
			if(result.status!="success"){
				$('input.form-control').attr('placeholder', 'Wrong token. Try again.');
			}
		},
		error: function(result) {
			logout("Could not check token. Please try again.");
		}
	});
}

function promptSecondFactor(){
	sendSecondFactor();
	OC.dialogs.prompt("You've been emailed a one-time security token. Please enter it and click 'Continue'.",
		"Two-factor authentication",
		function(arg){
			if(arg){
				var token = $('input.form-control').val();
				checkSecondFactor(token);
			}
			else{
				logout("Canceling..."+arg);
			}
	}, true, "Token", false, "Continue", "Cancel");
}

$(document).ready(function(){
	//checkUserServerAccess();
	//var checkAccessInterval = 10*1000;
	//var checkAccessID = setInterval(checkUserServerAccess, checkAccessInterval);
	if (-1==$.inArray(checkUserServerAccess, $(document).data('events').mousedown)) {
		$(document).mousedown(function(){checkUserServerAccess()});
	}
});


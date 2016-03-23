var USER_ACCESS_ALL = 0;
var USER_ACCESS_READ_ONLY = 1;
var USER_ACCESS_NONE = 2;

var isChecking = false;

// Check access (r/o if on a backup server, or if on new main server and migrating)
function checkUserServerAccess(){
	$(document).off('mousedown', checkUserServerAccess);
	if(isChecking){
		return false;
	}
	isChecking = true;
	var user_id;
	var server_id;
	$.ajax({
		url: OC.filePath('files_sharding', 'ajax', 'get_user_server_access.php'),
		async: true,
		data: {
			user_id: user_id,
			server_id: server_id
		},
		type: "GET",
		success: function(result) {
			isChecking = false;
			if(result['access']){
				var res = parseInt(result['access'], 10);
				switch(res){
					case USER_ACCESS_NONE:
						logout();
						break;
					case USER_ACCESS_READ_ONLY:
						disableWrite();
						break;
				}
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
		if(!$('.access-message').length){
			$('<div class="msg access-message"></div>').insertAfter('.crumb.last');
		}
		OC.msg.finishedSaving('.access-message', {status: 'success', data: {message: "You only have read access on this server"}});
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

function logout(){
	$cookieDomain = window.location.hostname.substring(window.location.hostname.indexOf('.'));
	deleteCookie('oc_ok', '/', $cookieDomain);
	window.location = "/index.php?logout=true&blocked=true";
}

$(document).ready(function(){
	checkUserServerAccess();
	if (-1 !== $.inArray(checkUserServerAccess, $(document).data('events').mousedown)) {
		$(document).on('mousedown', checkUserServerAccess);
	}
});


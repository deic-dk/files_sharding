var USER_ACCESS_ALL = 0;
var USER_ACCESS_READ_ONLY = 1;
var USER_ACCESS_NONE = 2;

// Check access (r/o if on a backup server, or if on new main server and migrating)
function checkUserServerAccess(){
	var user_id;
	var server_id;
	$.ajax({
		url: OC.filePath('files_sharding', 'ajax', 'get_user_server_access.php'),
		async: false,
		data: {
			user_id: user_id,
			server_id: server_id
		},
		type: "GET",
		success: function(result) {
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
		}
	});
}

var notify = false;

function disableWrite(){
	if(!notify){
		OC.msg.finishedSaving('.access-message', {status: 'success', data: {message: "You only have read access on this server"}});
		notify = true;
	}
	$('.file-actions .action').not('.action-download').prop( "disabled", true );
	$('.ui-draggable').removeClass('ui-draggable');
	$('.ui-draggable').remove();
	/*$('td.filename').draggable('destroy'); */
	$('body').on('drop', function (e) {
		return false;
	});
	/*$('body').on('drag', function (e) {
		return false;
	});*/
	var style = $('<style>#controls #upload,  #controls #new, .select-all, .fileselect, .action.delete, .fileactions-wrap, .app-gallery .right, li[data-id="meta_data"], li[data-id="importer_index"] , li[data-id="uploader"] { display: none; }</style>');
	$('html > head').append(style);
}

function logout(){
	deleteCookie('oc_ok', '/', '.data.deic.dk');
	window.location = "/index.php?logout=true&blocked=true";
}

$(window).load(function(){
	$('<div class="msg access-message"></div>').insertAfter('.crumb.last');
	checkUserServerAccess();
});

$(document).one('mousedown', function(){
	checkUserServerAccess();
});


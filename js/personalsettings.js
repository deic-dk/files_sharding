function get_home_server(previous_site, site){
	backup_server_id = $('#filesShardingPersonalSettings .backup_server').attr('id');
	$.ajax(OC.linkTo('files_sharding','ajax/get_server.php'), {
		 type:'POST',
		  data:{
			  site: site,
			  priority: 0,
			  exclude_server_id: backup_server_id
		 },
		 dataType:'json',
		 success: function(s){
			 if(s.error){
				$('#filesShardingPersonalSettings div select.home_site').val('');
				 alert(s.error);
			 }
			  $('#filesShardingPersonalSettings .home_server').text( s.server_url);
			  $('#filesShardingPersonalSettings .home_server').attr('id',  s.server_id);
				// When changing - remove current backup site from backup sites list and replace it with current main site (selected)
			  $('#filesShardingPersonalSettings div select.backup_site option[value='+site+']').hide();
			  $('#filesShardingPersonalSettings div select.backup_site option[value='+previous_site+']').show().attr('selected', 'selected');
		 },
		error:function(s){
			alert("Unexpected error!");
		}
	});
}

function get_backup_server(site){
	home_server_id = $('#filesShardingPersonalSettings .home_server').attr('id');
	$.ajax(OC.linkTo('files_sharding','ajax/get_server.php'), {
		 type:'POST',
		  data:{
			  site: site,
			  priority: 1,
			  exclude_server_id: home_server_id
		 },
		 dataType:'json',
		 success: function(s){
			 if(s.error){
				$('#filesShardingPersonalSettings div select.backup_site').val('');
				 alert(s.error);
			 }
			  $('#filesShardingPersonalSettings .backup_server').text( s.server_url);
			  $('#filesShardingPersonalSettings .backup_server').attr('id',  s.server_id);
		 },
		error:function(s){
			$('#filesShardingPersonalSettings div select.backup_site').val('');
			alert("Unexpected error!");
		}
	});
}

function set_home_server(home_server_id, backup_server_id){
	
	$.ajax(OC.linkTo('files_sharding','ajax/set_home_server.php'), {
		 type:'POST',
		  data:{
			  home_server_id: home_server_id,
			  backup_server_id: backup_server_id
		 },
		 dataType:'json',
		 success: function(s){
			 OC.msg.finishedSaving('#setHomeServerMsg', {status: 'success', data: {message: "Site selection saved"}});
			 $('#lastSync').text(s.last_sync);
			 $('#nextSync').text(s.next_sync);
		 },
		error:function(s){
			 OC.msg.finishedSaving('#setHomeServerMsg', {status: 'error', data: {message: "Unexpected error"}});
		}
	});
}

var remove_dialogs = [];

function create_remove_dialog(path){
	if(remove_dialogs[path] != undefined){
		return;
	}
	$("#filesShardingDataFolders #filesShardingDataFoldersList div.dataFolder[path='"+path+"'] div.dialog").text("Are you sure you want to sync the folder "+path+" again?");
	remove_dialogs[path] =  $("#filesShardingDataFolders  #filesShardingDataFoldersList div.dataFolder[path='"+path+"'] div.dialog").dialog({
		title: "Confirm sync",
		autoOpen: false,
		resizable: true,
		height:180,
		width:320,
		modal: true,
		buttons: {
			"Sync": function() {
				removeDataFolder(path);
				$(this).dialog("close");
			},
			"Cancel": function() {
				$(this).dialog("close");
			}
		}
	});
}

function appendDataDiv(folder){
	$('#filesShardingDataFolders #filesShardingDataFoldersList').append('<div class="dataFolder nowrap" path="'+folder+'">\
   		<span style="float:left;width:92%;">\
   		<label>'+folder+'</label>\
   		</span>\
   		<label class="remove_data_folder btn btn-flat">-</label>\
   		<div class="dialog" display="none"></div>\
   		</div>');
}

function addDataFolder(folder){
	
	if($("#filesShardingDataFolders #filesShardingDataFoldersList div.dataFolder[path='"+folder+"']").length>0){
		return false;
	}
	
	$.ajax(OC.linkTo('files_sharding','ajax/add_data_folder.php'), {
		 type:'POST',
		  data:{
			  folder: folder,
		 },
		 dataType:'json',
		 success: function(s){
			 appendDataDiv(folder, s.folder);
		 },
		error:function(s){
			alert("Unexpected error!");
		}
	});
}

function removeDataFolder(folder){
	$.ajax(OC.linkTo('files_sharding','ajax/remove_data_folder.php'), {
		 type:'POST',
		  data:{
			  folder: folder,
		 },
		 dataType:'json',
		 success: function(s){
				if(s.error){
					alert(s.error);
				}
				else{
					$("#filesShardingDataFolders div#filesShardingDataFoldersList div.dataFolder[path='"+folder+"']").remove();
				}
		 },
		error:function(s){
			alert("Unexpected error!");
		}
	});
}

function stripTrailingSlash(str) {
	if(str.substr(-1)=='/') {
		str = str.substr(0, str.length - 1);
	}
	if(str.substr(1)!='/') {
		str = '/'+str;
	}
	return str;
}

var previous_home_site;

$(document).ready(function(){
	
	$("li").click(function(){
		$(this).css("font-weight", "bold");
	});

	choose_data_folder_dialog = $("#filesShardingDataFolders div.addDataFolder div.dialog").dialog({//create dialog, but keep it closed
	  title: "Choose new data folder to exclude from syncing",
	  autoOpen: false,
	  height: 440,
	  width: 620,
	  modal: true,
	  buttons: {
	   	"Choose": function() {
	   		folder = stripTrailingSlash($('#chosen_folder').text());
	   		addDataFolder(folder);
			choose_data_folder_dialog.dialog("close");
	   	},
	   	"Cancel": function() {
	   		choose_data_folder_dialog.dialog("close");
			}
	  }
	});

	$('#filesShardingPersonalSettings div select.home_site').on('change', function() {
		get_home_server($(this).val());
	 });
	
	$('#filesShardingPersonalSettings div select.home_site').focus(function () {
		previous_home_site = $(this).val();
	}).change(function() {
		get_home_server(previous_home_site, $(this).val());
	});
	
	$('#filesShardingPersonalSettings div select.backup_site').on('change', function() {
		 $('#lastSync').text('');
		 $('#nextSync').text('');
		get_backup_server($(this).val());
	 });
	
	$('#filesShardingPersonalSettings .save_home_server .save').click(function(){
		home_server_id = $('#filesShardingPersonalSettings .home_server').attr('id');
		backup_server_id = $('#filesShardingPersonalSettings .backup_server').attr('id');
		set_home_server(home_server_id, backup_server_id);
	});


	$('#filesShardingDataFolders div#filesShardingDataFoldersList div.dataFolder .remove_data_folder').live('click', function(e){
		path = $(this).parent().attr('path');
		create_remove_dialog(path);
		remove_dialogs[path].dialog('open');
	});
	
	$('#filesShardingDataFolders div.addDataFolder .add_data_folder').live('click', function(){
	  choose_data_folder_dialog.dialog('open');
	  //choose_data_folder_dialog.load("/apps/chooser/");
	  choose_data_folder_dialog.show();
		$('#loadFolderTree').fileTree({
			//root: '/',
			script: '../../apps/chooser/jqueryFileTree.php',
			//script: '../../apps/files_sharding/jqueryFileTree.php',
			multiFolder: false,
			selectFile: false,
			selectFolder: true,
			folder: '/',
			file: ''
		},
		// single-click
		function(file) {
			$('#chosen_folder').text(file);
		},
		// double-click
		function(file) {
			if(file.indexOf("/", file.length-1)!=-1){// folder double-clicked
				addDataFolder(file);
				choose_data_folder_dialog.dialog("close");
			}
		});
	});


});
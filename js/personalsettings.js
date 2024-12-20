OC.dialogs = _.extend({}, OC.dialogs, {
	notify:function(text, title, callback, modal, name, password, buttons) {
		return $.when(this._getMessageTemplate()).then(function ($tmpl) {
			var mywidth = $(window).width()*0.7;
			var inputwidth = 0.9*mywidth;
			var dialogName = 'oc-dialog-' + OCdialogs.dialogsCounter + '-content';
			var dialogId = '#' + dialogName;
			var $dlg = $tmpl.octemplate({
				dialog_name: dialogName,
				title      : title,
				message    : text,
				type       : 'notice'
			});
			if(name){
				var input = $('<input/>');
				input.attr('type', password ? 'password' : 'text').attr('id', dialogName + '-input');
				var label = $('<label/>').attr('for', dialogName + '-input').text(name + ': ');
				$dlg.append(label);
				$dlg.append(input);
				input.css('width', inputwidth);
			}
			if (modal === undefined) {
				modal = false;
			}
			$('body').append($dlg);
			var buttonlist = [{
					text         : t('core', buttons && buttons==OCdialogs.YES_NO_BUTTONS?'Yes':'OK'),
					click        : function () {
						if (callback !== 'undefined' && callback !==null) {
							callback(true, typeof input!=='undefined'?input.val():null);
						}
						$(dialogId).ocdialog('close');
					},
					defaultButton: true
				}
			];
			if(buttons && buttons==OCdialogs.YES_NO_BUTTONS){
				buttonlist.unshift({
					text : t('core', 'No'),
					click: function () {
						if (callback !== undefined) {
							callback(false, typeof input!=='undefined'?input.val():null);
						}
						$(dialogId).ocdialog('close');
					}
				});
				};

			$(dialogId).ocdialog({
				closeOnEscape: true,
				modal        : modal,
				buttons      : buttonlist,
				width: mywidth
			});
			OCdialogs.dialogsCounter++;
		});
	}
});


var changing = false;
var previous_home_site;
var saving = false;

function get_home_server(site){
	changing = true;
	var backup_server_id = $('#filesShardingPersonalSettings .backup_server').attr('id');
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
				 alert('Could not get home server. '+s.error);
			 }
			  $('#filesShardingPersonalSettings .home_server').text( s.server_url);
			  $('#filesShardingPersonalSettings .home_server').attr('id',  s.server_id);
				// When changing - remove current backup site from backup sites list and replace it with current main site (selected)
			  $('#filesShardingPersonalSettings div select.backup_site option[value="'+site+'"]').hide();
			  $('#filesShardingPersonalSettings div select.backup_site option[value="'+previous_home_site+'"]').show();
			  if($('#filesShardingPersonalSettings div select.backup_site').val()!=''){
				  $('#filesShardingPersonalSettings div select.backup_site option[value="'+previous_home_site+'"]').attr('selected', 'selected');
				  get_backup_server(previous_home_site)
			  }
			  else{
				  $('#filesShardingPersonalSettings div select.backup_site option').removeAttr('selected');
			  }
			  previous_home_site = site;
			  changing = false;
		 },
		error:function(s){
			changing = false;
			alert("Unexpected error!");
		}
	});
}

function get_backup_server(site){
	var home_server_id = $('#filesShardingPersonalSettings .home_server').attr('id');
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
				 alert('Could not get backup server. '+s.error);
			 }
			 $('#filesShardingPersonalSettings .backup_server').parent().show();
			  $('#filesShardingPersonalSettings .backup_server').text( s.server_url);
			  $('#filesShardingPersonalSettings .backup_server').attr('id',  s.server_id);
		 },
		error:function(s){
			alert("Unexpected error!");
		}
	});
}

function set_home_server(home_server_id, backup_server_id, home_server_access){
	
	$.ajax(OC.linkTo('files_sharding','ajax/set_home_server.php'), {
		type:'POST',
		data:{
			home_server_id: home_server_id,
			backup_server_id: backup_server_id,
			home_server_access: home_server_access
		},
		 dataType:'json',
		 success: function(s){
			 OC.msg.finishedSaving('#setHomeServerMsg', {status: 'success', data: {message: "Site selection saved"}});
			 $('#lastSync').text(s.last_sync+' ' + (typeof s.timezone!=='undefined'?s.timezone:''));
			 $('#nextSync').text(s.next_sync+' ' + (typeof s.timezone!=='undefined'?s.timezone:''));
			 $('#filesShardingPersonalSettings #current_home_server').attr('site', home_server_id);
			 $('#filesShardingPersonalSettings #current_home_server').text($('#filesShardingPersonalSettings .home_server').text());
		 },
		error:function(s){
			 OC.msg.finishedSaving('#setHomeServerMsg', {status: 'error', data: {message: "Unexpected error"}});
		}
	});
}

var remove_dialogs = [];

function create_r_dialog(path){
	if(remove_dialogs[path] != undefined){
		return;
	}
	msg = t("chooser", "Are you sure you want to sync the folder %s again?");
	msg = msg.replace(/%s/, path)
	$("#filesShardingDataFolders #filesShardingDataFoldersList div.dataFolder[path='"+path+"'] div.dialog").text(msg);
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

function appendDataDiv(folder, group){
	$('#filesShardingDataFolders #filesShardingDataFoldersList').append('<div class="dataFolder nowrap remove_element" path="'+folder+'">\
   		<span class="data_folder">\
			<label class="data_folder_path">'+folder+'</label>\
   		<label class="data_folder_group" title="Group">'+group+'</label>\
   		</span>\
   		<label class="remove_data_folder btn btn-flat" title="Sync this folder again">-</label>\
   		<div class="dialog" display="none"></div>\
   		</div>');
}

function addDataFolder(folder, group){
	
	if($("#filesShardingDataFolders #filesShardingDataFoldersList div.dataFolder[path='"+folder+"']").length>0){
		return false;
	}
	
	$.ajax(OC.linkTo('files_sharding','ajax/add_data_folder.php'), {
		 type:'POST',
		  data:{
			  folder: folder,
			  group: group,
		 },
		 dataType:'json',
		 success: function(s){
			 appendDataDiv(folder, group);
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
					alert('Could not remove data folder. '+s.error);
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

$(document).ready(function(){
	
	previous_home_site = $('#filesShardingPersonalSettings div select.home_site').val();
	
	$("li").click(function(){
		$(this).css("font-weight", "bold");
	});

	var choose_data_folder_dialog;
	var buttons = {};
	buttons[t("chooser", "Choose")] = function() {
		folder = stripTrailingSlash($('#chosen_folder').text());
		group = $('#group_folder').val();
		addDataFolder(folder, group);
		choose_data_folder_dialog.dialog("close");
 	};
 	buttons[t("chooser", "Cancel")] = function() {
		choose_data_folder_dialog.dialog("close");
	};
	choose_data_folder_dialog = $("#filesShardingDataFolders div.addDataFolder div.dialog").dialog({//create dialog, but keep it closed
		title: t("chooser", "Choose data folder"),
		autoOpen: false,
		height: 440,
		width: 620,
		modal: true,
		dialogClass: "sharding_dialog",
		buttons: buttons
	});

	$('#filesShardingPersonalSettings div select.home_site').on('change', function() {
		if(changing){
			return false;
		}
		get_home_server($(this).val());
	 });
	
	$('#filesShardingPersonalSettings div select.backup_site').on('change', function() {
		 $('#lastSync').text('');
		 $('#nextSync').text('');
		get_backup_server($(this).val());
	 });
	
	$('#filesShardingPersonalSettings .save_home_server .save').click(function(){
		if(saving){
			return false;
		}
		saving = true;
		var current_home_server = $('#filesShardingPersonalSettings #current_home_server').text();
		var current_home_site = $('#filesShardingPersonalSettings #current_home_server').attr('site');
		var new_home_server = $('#filesShardingPersonalSettings .home_server').text();
		var home_server_id = $('#filesShardingPersonalSettings .home_server').attr('id');
		var home_site = $('#filesShardingPersonalSettings div select.home_site').val() ;
		var backup_server_id = $('#filesShardingPersonalSettings .backup_server').attr('id');
		var home_server_access = $('#filesShardingPersonalSettings div select.home_server_access').val() ;
		if(new_home_server!=current_home_server){
  		OC.dialogs.confirm('Are you sure you want to change site from '+current_home_site+' to '+home_site+' ?', 'Change site?',
          function(res){
	  				if(res){
	  		  		OC.dialogs.notify('Your files will now be migrated. Please change your sync clients from\
	  		  				'+current_home_server+' \
	  		  				to\
	  		  				'+new_home_server+'.\
	  		  				Your files will be set read-only on both your old and the new home server until the migration is over. \
	  		  				Please log out and log back in in a few hours.',
	  		  				'Change server', function(e){set_home_server(home_server_id, backup_server_id);}, false);
	  				}
	  				saving = false;
          }
       );
		}
		else{
			set_home_server(home_server_id, backup_server_id, home_server_access);
			saving = false;
		}
	});

	$('#filesShardingDataFolders div#filesShardingDataFoldersList div.dataFolder .remove_data_folder').live('click', function(e){
		path = $(this).parent().attr('path');
		create_r_dialog(path);
		remove_dialogs[path].dialog('open');
	});
	
	$('#filesShardingDataFolders div.addDataFolder .add_data_folder').live('click', function(){
	  choose_data_folder_dialog.dialog('open');
	  //choose_data_folder_dialog.load("/apps/chooser/");
	  choose_data_folder_dialog.show();
		group = $('#group_folder').val();
		$('#loadDataFolderTree').fileTree({
			//root: '/',
			script: '../../apps/chooser/jqueryFileTree.php',
			//script: '../../apps/files_sharding/jqueryFileTree.php',
			multiFolder: false,
			selectFile: false,
			selectFolder: true,
			folder: '/',
			file: '',
			group: group
		},
		// single-click
		function(file) {
			$('#chosen_folder').text(file);
		},
		// double-click
		function(file) {
			if(file.indexOf("/", file.length-1)!=-1){// folder double-clicked
				addDataFolder(file, group);
				choose_data_folder_dialog.dialog("close");
			}
		});
	});

	$("#filesShardingPersonalSettings #sharding-info").on("click", function () {
		if($('.sharding-help').length){
			return false;
		};
		dialog_html = "<div><h3>"+t("files_sharding", "Choosing home and backup site")+"</h3>\
				<a class='oc-dialog-close close svg'></a>\
				<div class='sharding-help'></div></div>";
				
		 $(dialog_html).dialog({
			 	autoOpen: true,
			  dialogClass: "files_sharding-oc-dialog",
			  resizeable: true,
			  draggable: true,
			  modal: false,
			  height: 600,
			  width: 720,
				buttons: [{
					"id": "sharding_info",
					"text": "OK",
					"click": function() {
						$( this ).dialog( "close" );
					}
				}]
			});

		$('body').append('<div class="modalOverlay"></div>');

		$('.oc-dialog-close').live('click', function() {
			$(".files_sharding-oc-dialog").remove();
			$('.modalOverlay').remove();
		});

		$('.ui-helper-clearfix').css("display", "none");

		$.ajax(OC.linkTo('files_sharding', 'ajax/get_help.php'), {
			type: 'GET',
			success: function(jsondata){
				if(jsondata) {
					$('.sharding-help').html(jsondata.data.page);
				}
			},
			error: function(data) {
				alert("Unexpected error!");
			}
		});
		
		$(document).click(function(e){
			if ($(e.target).attr('id')!='sharding-info' && $('.sharding_dialog').length &&
					!$(e.target).parents().filter('.oc-dialog').length ) {
				//$(".oc-dialog").remove();
				//$('.modalOverlay').remove();
			}
		});
		
	}); 
	
	if(!$('.backup_server').text().trim().length){
		$('.backup_server').parent().hide();
	}
	
});

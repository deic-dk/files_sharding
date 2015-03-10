function get_home_server(site){
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
			 // TODO: notify 
		 },
		error:function(s){
			alert("Unexpected error!");
		}
	});
}

$(document).ready(function(){
	$('#filesShardingPersonalSettings div select.home_site').on('change', function() {
		get_home_server($(this).val());
	 });
	$('#filesShardingPersonalSettings div select.backup_site').on('change', function() {
		get_backup_server($(this).val());
	 });
	$('#filesShardingPersonalSettings .save_home_server .save').click(function(){
		home_server_id = $('#filesShardingPersonalSettings .home_server').attr('id');
		backup_server_id = $('#filesShardingPersonalSettings .backup_server').attr('id');
		 set_home_server(home_server_id, backup_server_id);
	});
});
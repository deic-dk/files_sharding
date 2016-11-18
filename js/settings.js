function allow_local_login(id, allow_local_login){
	$.ajax(OC.linkTo('files_sharding','ajax/set_allow_local_login.php'), {
		 type:'POST',
		  data:{
			  id: id,
			  allow_local_login: allow_local_login
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

var delete_dialogs = [];

function create_delete_dialog(id){
	if(delete_dialogs[id] != undefined){
		return;
	}
	$( "#filesShardingSettings div#"+id+" div.dialog" ).text("Are you sure you want to delete the server "+id);
	delete_dialogs[id] =  $( "#filesShardingSettings div#"+id+" div.dialog" ).dialog({
		title: "Confirm delete",
		autoOpen: false,
		resizable: true,
		height:180,
		width:320,
		modal: true,
		buttons: {
			"Delete": function() {
				delete_server(id);
				location.reload();
				$( this ).dialog( "close" );
			},
			"Cancel": function() {
				$( this ).dialog( "close" );
			}
		}
	});
}

function delete_server(id){
	$.ajax(OC.linkTo('files_sharding','ajax/delete_server.php'), {
		 type:'POST',
		  data:{
			  id: id,
		 },
		 dataType:'json',
		 success: function(s){
			 // TODO: notify 
		 }
	});
}

function add_server(url, internal_url, x509_dn, site, charge, allow_local_login, id){
	if(!url){
		alert("You need to provide a URL");
		return;
	}
	if(!site){
		alert("You need to provide a site name");
		return;
	}
	if(!charge){
		alert("You need to provide a charge amount");
		return;
	}
	$.ajax(OC.linkTo('files_sharding','ajax/add_server.php'), {
		 type:'POST',
		  data:{
		  	id: id,
			  url: url,
			  internal_url: internal_url,
			  x509_dn: x509_dn,
			  site: site,
			  charge: charge,
			  allow_local_login: allow_local_login
		 },
		 dataType:'json',
		 success: function(s){
			location.reload();
		 },
		error:function(s){
			alert("Unexpected error!");
		}
	});
}

function addEditLink($id){
	$('a.edit_description'+($id?'[id='+$id+']':'')).click(function(event){
		var id = $(event.target).attr('id');
		$('textarea.description[id="'+id+'"]').toggleClass('hidden');
	});
}

function addScrollbar(){
	$('#filesShardingSettings').width($(window).innerWidth()*0.85);
	$('#filesShardingSettings .server').width($(window).innerWidth()*0.85);
}

$(document).ready(function(){
	$('#filesShardingSettings div.server input.allow_local_login').each(function(){
	  $(this).change(function(){
		  allow_local_login($(this).parent().attr('id'), $(this).is(':checked')?'yes':'no');
	  });
	});
	$('#filesShardingSettings div.server .delete_server').live('click', function(e){
		 id = $(this).parent().attr('id');
		create_delete_dialog(id);
		delete_dialogs[id].dialog( "open" );
	});
	$('#filesShardingSettings div .add_server').live('click', function(){
			id = $(this).parent().find('input.id').first().val();
			url = $(this).parent().find('input.url').first().val();
			internal_url = $(this).parent().find('input.internal_url').first().val();
			site = $(this).parent().find('input.site').first().val();
			charge = $(this).parent().find('input.charge').first().val();
			allow_local_login =  $(this).parent().find('input.allow_local_login').first().is(':checked')?'yes':'no';
			x509_dn =  $(this).parent().find('input.x509_dn').first().val();
			add_server(url, internal_url, x509_dn, site, charge, allow_local_login, id);
	});
	addEditLink();
	addScrollbar()
});

$(window).resize(function(){
	addScrollbar();
});

<fieldset id="filesShardingSettings" class="section">

  <h2>
  <?php
  	p($l->t('Data location'));
  	OCP\Util::addStyle('files_sharding', 'settings');
  ?>
  </h2>

  <?php function print_server($id, $url, $internal_url, $site, $charge, $allow_local_login, $description){
		if(\OCP\App::isEnabled('files_accounting')){
			$currency = OCA\Files_Accounting\Storage_Lib::getBillingCurrency();
		}
  	print('<input class="id" type="text" value="'.$id.'"> /
  		<input class="url" type="text" value="'.$url.'"> /
  		<input class="internal_url" type="text" value="'.$internal_url.'"> /
  		<input class="site" type="text" value="'.$site.'"> /'.
  		'<input class="charge" type="text" value="'.$charge.'">'.
  		(\OCP\App::isEnabled('files_accounting')?'<label>'.$currency.'</label>':'').' / '.
  		'<input class="allow_local_login" type="checkbox"'.($allow_local_login==='yes'?' checked="checked"' : '').'>
  		/ <a class="edit_description" id="'.$id.'" href="#">edit</a>
  			<textarea class="description hidden" rows="3" cols="92" id="'.$id.'">'.$description.'</textarea>');
  } ?>
  
  <?php
  	print('<div><label>Servers:</label></div>');
  	print('<label><i>ID</i></label> /
  		<label>URL</label> /
  		<label>Internal URL</label> /
  		<label>Site</label> /
  		<label>Charge per GB</label> /
  		<label>Allow local login</label> /
			<label>Description</label>');
  	foreach ($_['servers_list'] as $server){
  		print('<div class="server" id="'.$server['id'].'">');
  		print_server($server['id'], $server['url'], $server['internal_url'], $server['site'],
  			$server['charge_per_gb'], $server['allow_local_login'], $server['description']);
  		print('<label class="add_server btn btn-flat">Save</label>');
  		print('<label class="delete_server btn btn-flat">Delete</label><div class="dialog" display="none"></div>');
  		print('</div>');
  	}
  	print('<div><label>Add server:</label></div><div>');
  	print_server("", "", "", "", "", "", "");
  	print('<label class="add_server btn btn-flat">Add</label>');
  	print('</div>');
  	?>

</fieldset>

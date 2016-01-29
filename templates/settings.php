<fieldset id="filesShardingSettings" class="section">

  <h2>
  <?php
  	p($l->t('Data location'));
  	OCP\Util::addStyle('files_sharding', 'settings');
  ?>
  </h2>

  <?php function print_server($id, $url, $site, $charge, $allow_local_login){
  	print('<label><i>ID:</i></label>
  		<input class="id" type="text" value="'.$id.'">
  		<label>URL:</label>
  		<input class="url" type="text" value="'.$url.'">
  		<label>site:</label>
  		<input class="site" type="text" value="'.$site.'">
		<label>Charge/GB (DKK):</label>
		<input class="charge" type="text" value="'.$charge.'"> 
  		<label>Allow local login:</label>
  		<input class="allow_local_login" type="checkbox"'.($allow_local_login==='yes' ? ' checked="checked"' : '').'>
  		');
  } ?>
  
  <?php
		print('<div><label>Servers:</label></div>');
  	foreach ($_['servers_list'] as $server){
			print('<div class="server" id="'.$server['id'].'">');
  		print_server($server['id'], $server['url'], $server['site'], $server['charge_per_gb'], $server['allow_local_login']);
  		print('<label class="delete_server btn btn-flat">-</label><div class="dialog" display="none"></div>');
  		print('</div>');
  	}
  	print('<div><label>Add server:</label></div><div>');
  	print_server("", "", "", "", "");
  	print('<label class="add_server btn btn-flat">+</label>');
  	print('</div>');
  	?>
	
</fieldset>

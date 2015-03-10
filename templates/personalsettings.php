<fieldset id="filesShardingPersonalSettings" class="section">
	<h2>Data location</h2>
	<div>
		<label class="nowrap">Home site: </label>
		<select class="home_site">
			<?php
			foreach ($_['sites_list'] as $site){
				print '<option value="'.$site['site'].'"'.($_['user_home_site']===$site['site']?'" selected':'"').'>'.$site['site'].'</option>';
			}
			?>
		</select>
	</div>
	<div>
		<label class="nowrap">Backup site: </label>
		<select class="backup_site">
			<?php
			print '<option value=""></option>';
			foreach ($_['sites_list'] as $site){
				print '<option value="'.$site['site'].'"'.(isset($_['user_backup_site'])&&$_['user_backup_site']===$site['site']?'" selected':'"').'>'.$site['site'].'</option>';
			}
			?>
		</select>
	</div>
	<div>
		<label class="nowrap">Server for sync clients: </label>
		<label class="nowrap home_server" id="<?php print($_['user_server_id']);?>"><?php print($_['user_server_url']);?></label>
	</div>
	<div>
		<label class="nowrap">Backup server: </label>
		<label class="nowrap backup_server" id="<?php print(isset($_['user_backup_server_id'])?$_['user_backup_server_id']:'')?>">
					<?php print(isset($_['user_backup_server_url'])?$_['user_backup_server_url']:'');?></label>
	</div>
	<div class="save_home_server">
		<a class="save btn btn-primary btn-flat" href="#">Save</a>
	</div>	
</fieldset>
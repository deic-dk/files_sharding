<fieldset id="filesShardingPersonalSettings" class="section">
	<h2><?php p($l->t('Data location'));?></h2>
	<a id="sharding-info" class="custom-popup"><?php p($l->t("What's this?"));?></a>
	<div class="home_site">
		<label class="nowrap"><?php p($l->t('Home site'));?>: </label>
		<select class="home_site">
			<?php
			print '<option value="'.$_['user_home_site'].'">'.$_['user_home_site'].'</option>';
			if(isset($_['synced_user_backup_site'])){
				print '<option value="'.$_['synced_user_backup_site'].'"'.($_['user_home_site']===$_['synced_user_backup_site']?'" selected':'"').'>'.$_['synced_user_backup_site'].'</option>';
			}
			?>
		</select>
		<div class="hidden" id="current_home_server" site=$_['user_home_site']><?php print $_['user_server_url']; ?></div>
	</div>
	<div>
		<label class="nowrap"><?php p($l->t('Backup site'));?>: </label>
		<select class="backup_site">
			<?php
			print '<option value=""></option>';
			foreach ($_['sites_list'] as $site){
				/*if($site['site']==$_['user_home_site']){
					continue;
				}*/
				print '<option value="'.$site['site'].'"'.
				($site['site']==$_['user_home_site']?' style="display:none;"':'').
				(isset($_['user_backup_site'])&&$_['user_backup_site']===$site['site']?' selected="selected"':'').
				'>'.$site['site'].'</option>';
			}
			?>
		</select>
	</div>
	<div>
		<label class="nowrap"><?php p($l->t('URL for sync clients'));?>: </label>
		<label class="nowrap home_server" id="<?php print($_['user_server_id']);?>"><?php print($_['user_server_url']);?></label>
	</div>
	<div>
		<label class="nowrap"><?php p($l->t('URL for file-transfer (WebDAV) clients'));?>: </label>
		<label class="nowrap home_server" id="<?php print($_['user_server_id']);?>"><?php print($_['user_server_url']);?>/files/</label>
	</div>
	<div>
		<label class="nowrap"><?php p($l->t('Backup server'));?>: </label>
		<label class="nowrap backup_server"
			id="<?php print(isset($_['user_backup_server_id'])?$_['user_backup_server_id']:'')?>">
			<?php print(isset($_['user_backup_server_url'])?$_['user_backup_server_url']:'');?></label>
			<label class="nowrap"><?php p($l->t('Last backup'));?>:</label><span id="lastSync">
				<?php print(!empty($_['user_backup_server_lastsync'])?
						OCP\Util::formatDate($_['user_backup_server_lastsync']).' '.date_default_timezone_get():'')?>
			</span>
			<label class="nowrap"><?php p($l->t('Next backup'));?>:</label><span id="nextSync">
				<?php print(!empty($_['user_backup_server_nextsync'])?
						OCP\Util::formatDate($_['user_backup_server_nextsync']).' '.date_default_timezone_get():'')?>
			</span>
			</div>
	<div class="save_home_server">
		<a class="save btn btn-primary btn-flat" href="#"><?php p($l->t('Save'));?></a><span id="setHomeServerMsg" class="msg">
	</div>
</fieldset>

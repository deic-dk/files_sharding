<fieldset id="filesShardingDataFolders" class="section">
	<h2><?php p($l->t('Data folders')); ?></h2>
	<?php p($l->t("These folders are intended to hold data and live only on the server.")); ?>
	<?php print_unescaped($l->t("They are <i>not</i> synchronized to your desktop or laptop.")); ?>
	<br />
	<br />
	<div id="filesShardingDataFoldersList">
	<?php foreach($_['data_folders'] as $p){
		$path = $p['folder'];
		$group = $p['gid'];
		?>
		<div class="dataFolder nowrap remove_element" path="<?php echo($path);?>" group="<?php echo($group);?>">
			<span class="data_folder"><label class="data_folder_path"><?php echo($path);?></label><label class="data_folder_group" title="Group"><?php print($group);?></label></span>
			<label title="Sync this folder again" class="remove_data_folder btn btn-flat">-</label>
			<div class="dialog" display="none"></div></div>
	<?php } ?>
	</div>
	<br />
	<div class="nowrap addDataFolder">
		<select id="group_folder">
		<option value="" selected="selected" style="margin-top:0px;"><?php p($l->t("Home")); ?></option>
		<?php
		foreach($_['member_groups'] as $group){
			echo '<option value="'.$group['gid'].'">'.$group['gid'].'</option>';
		}
		?>
		</select>
		<label class="add_data_folder btn btn-flat" title="<?php p($l->t("Add data folder")); ?>">+</label>
		<div id="chosen_folder" style="visibility:hidden;display:none;"></div>
		<div class="dialog" display="none">
			<div id="loadDataFolderTree"></div>
			<div id="file" style="visibility: hidden; display:inline;"></div>
		</div>
	</div>
</fieldset>


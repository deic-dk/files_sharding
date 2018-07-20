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
		<div class="dataFolder nowrap" path="<?php print($path);?>">
			<span style="float:left;width:70%;"><label><?php print($path);?></label></span>
			<span style="float:left;width:22%;"><label><?php print($group);?></label></span>
			<label class="remove_data_folder btn btn-flat">-</label>
			<div class="dialog" display="none"></div>
		</div>
	<?php } ?>
	</div>
	<br />
	<div class="nowrap addDataFolder">
		<span style="float:left;width:70%;"><label></label></span>
		<span style="float:left;width:22%;"><select id="group_folder">
		<option value="" selected="selected"><?php p($l->t("Home")); ?></option>
		<?php
		foreach($_['member_groups'] as $group){
			echo "<option value='".$group."'>".$group."</option>";
		}
		?>
		</select></span>
		<label class="add_data_folder btn btn-flat">+</label>
		<div id="chosen_folder" style="visibility:hidden;display:none;"></div>
		<div class="dialog" display="none">
			<div id="loadDataFolderTree"></div>
			<div id="file" style="visibility: hidden; display:inline;"></div>
		</div>
	</div>
</fieldset>
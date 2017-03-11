<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$exclude_as_backup = $_POST['exclude_as_backup'];
$id = $_POST['id'];

OC_Log::write('files_sharding',"Setting exclude_as_backup of server ".$id." to ".$exclude_as_backup, OC_Log::WARN);

if(!OCA\FilesSharding\Lib::dbExcludeAsBackup($id, $exclude_as_backup)){
	$ret['error'] = "Failed setting exclude_as_backup of server ".$id." to ".$exclude_as_backup;
}
else{
	$ret['msg'] = "Set exclude_as_backup of server ".$id." to ".$exclude_as_backup;
}

OCP\JSON::encodedPrint($ret);

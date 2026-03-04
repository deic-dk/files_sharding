<?php

OCP\JSON::checkAppEnabled('files_sharding');

$ret = array();

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}


if(!isset($_POST['user_id']) || !isset($_POST['folder']) || !isset($_POST['only_from'])){
	http_response_code(401);
	exit;
}

$folder = $_POST['folder'];
$user_id = $_POST['user_id'];
$only_from = $_POST['only_from'];
$group = empty($_POST['group'])?'':$_POST['group'];

if(!OCA\FilesSharding\Lib::updateDataFolder($folder, $group, $user_id, $only_from)){
	\OCP\Util::writeLog('files_sharding', 'ERROR updating data folder '.$folder, \OC_Log::ERROR);
	$ret['error'] = "Failed updating data folder";
	OCP\JSON::error($ret);
}
else{
	\OCP\Util::writeLog('files_sharding', 'Updated data folder '.$folder, \OC_Log::WARN);
	$ret = OCA\FilesSharding\Lib::dbGetDataFoldersList($user_id);
	OCP\JSON::encodedPrint($ret);
}


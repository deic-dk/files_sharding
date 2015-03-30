<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$folder = $_POST['folder'];
$user_id = isset($_POST['user_id'])?$_POST['user_id']:\OCP\USER::getUser();

OC_Log::write('files_sharding',"Resyncing folder: ".$folder, OC_Log::WARN);

if(empty($folder) ||
		!OCA\FilesSharding\Lib::removeDataFolder($folder, $user_id)){
	$ret['error'] = "Failed resyncing folder ".$folder;
}
else{
	$ret['msg'] = "Resynced folder ".$folder;
}

OCP\JSON::encodedPrint($ret);

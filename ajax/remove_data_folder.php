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
	$data_folders = array();
	$session = \OC::$server->getSession();
	foreach ($session['oc_data_folders'] as $row){
		if($row['folder']!=$folder){
			$data_folders[] = $row;
		}
	}
	$_SESSION['oc_data_folders'] = $data_folders;
	$ret['msg'] = "Resynced folder ".$folder;
}

OCP\JSON::encodedPrint($ret);

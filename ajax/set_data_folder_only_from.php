<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$folder = $_POST['folder'];
$only_from = $_POST['only_from'];
$group = empty($_POST['group'])?'':$_POST['group'];

if(isset($_POST['user_id'])){
	$user_id = $_POST['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}

OC_Log::write('files_sharding',"Restricting access to folder: ".$folder." for ".$user_id." to ".$only_from, OC_Log::WARN);

if(empty($folder) || empty($user_id) ||
		!OCA\FilesSharding\Lib::updateDataFolder($folder, $group, $user_id, $only_from)){
			$ret['error'] = "Failed updating folder ".$folder;
}
else{
	$session = \OC::$server->getSession();
	$session['oc_data_folders'] = OCA\FilesSharding\Lib::getDataFoldersList($user_id);
	$ret['folder'] = $folder;
	$ret['msg'] = "Added folder ".$folder;
}

OCP\JSON::encodedPrint($ret);
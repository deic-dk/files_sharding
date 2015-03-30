<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$folder = $_POST['folder'];

if(isset($_POST['user_id'])){
	$user_id = $_POST['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}

OC_Log::write('files_sharding',"Adding folder: ".$folder." for ".$user_id, OC_Log::WARN);


if(empty($folder) || empty($user_id) ||
		!OCA\FilesSharding\Lib::addDataFolder($folder, $user_id)){
	$ret['error'] = "Failed adding folder ".$folder;
}
else{
	$ret['folder'] = $folder;
	$ret['msg'] = "Added folder ".$folder;
}

OCP\JSON::encodedPrint($ret);

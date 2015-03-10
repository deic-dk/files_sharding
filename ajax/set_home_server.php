<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$home_server_id = $_POST['home_server_id'];
if(isset($_POST['user_id'])){
	$user_id = $_POST['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}

OC_Log::write('files_sharding',"Setting home server: ".$user_id.":".$home_server_id, OC_Log::WARN);

$ret['msg'] = "";

if(!OCA\FilesSharding\Lib::dbSetServerForUser($user_id, $home_server_id, 0)){
	$ret['error'] = "Failed setting home server ".$home_server_id;
}
else{
	$ret['msg'] .= "Set home server ".$home_server_id;
}

$backup_server_id = isset($_POST['backup_server_id'])?$_POST['backup_server_id']:null;
if(!OCA\FilesSharding\Lib::dbSetServerForUser($user_id, $backup_server_id, 1)){
	$ret['error'] = "Failed setting backup server ".$backup_server_id;
}
else{
	$ret['msg'] .= ". Set backup server ".$home_server_id;
}

OCP\JSON::encodedPrint($ret);

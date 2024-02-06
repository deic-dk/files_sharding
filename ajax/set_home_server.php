<?php

OCP\JSON::checkAppEnabled('files_sharding');

if(empty($_POST['user_id'])){
	OCP\JSON::checkLoggedIn();
	$user_id = OCP\USER::getUser();
}
else{
	if(!OCA\FilesSharding\Lib::checkIP()){
		http_response_code(401);
		exit;
	}
	$user_id = $_POST['user_id'];
}
$ret['msg'] = "";
$ret['last_sync'] = "";
$ret['next_sync'] = "";

if(!empty($_POST['home_server_id'])){
	$home_server_id = $_POST['home_server_id'];
	OC_Log::write('files_sharding',"Setting home server: ".$user_id.":".$home_server_id, OC_Log::WARN);
	if(!OCA\FilesSharding\Lib::dbSetServerForUser($user_id, $home_server_id,
			OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_PRIMARY,
			(!empty($_POST['home_server_access'])||$_POST['home_server_access']==="0"?
					$_POST['home_server_access']:
					OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY))){
		$ret['error'] = "Failed setting home server ".$home_server_id;
	}
	else{
		$ret['msg'] .= "Set home server ".$home_server_id;
	}
}

if(!empty($_POST['backup_server_id'])){
	$backup_server_id = isset($_POST['backup_server_id'])?$_POST['backup_server_id']:null;
	if(!OCA\FilesSharding\Lib::dbSetServerForUser($user_id, $backup_server_id,
			OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_BACKUP_1,
			OCA\FilesSharding\Lib::$USER_ACCESS_READ_ONLY)){
		$ret['error'] = "Failed setting backup server ".$backup_server_id;
	}
	else{
		$lastSync = empty($lastSync)?'':OCA\FilesSharding\Lib::dbLookupLastSync($server_id, $user_id);
		$nextSync = (empty($lastSync)?time():$lastSync) + OCA\FilesSharding\Lib::$USER_SYNC_INTERVAL_SECONDS;
		$ret['last_sync'] = empty($lastSync)?'':OCP\Util::formatDate($lastSync);
		$ret['next_sync'] = OCP\Util::formatDate($nextSync);
		$ret['timeszone'] = date_default_timezone_get();
		$ret['msg'] .= ". Set backup server ".$home_server_id;
	}
}

OCP\JSON::encodedPrint($ret);

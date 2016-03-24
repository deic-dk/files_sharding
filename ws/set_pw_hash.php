<?php

	OCP\App::checkAppEnabled('files_sharding');

include_once("files_sharding/lib/session.php");
include_once("files_sharding/lib/lib_files_sharding.php");


$ret = array();

if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['status'] = 'error';
	$ret['error'] = "Network not secure";
}
else{
	$user_id = $_POST['user_id'];
	$serverURL = \OCA\FilesSharding\Lib::getServerForUser($user_id, true);
	$pwHash = \OCA\FilesSharding\Lib::getPasswordHash($user_id, $serverURL);
	if(!empty($pwHash)){
		$pwOk = \OCA\FilesSharding\Lib::setPasswordHash($user_id, $pwHash);
	}
	if($pwOk) {
		$ret['status'] = 'success';
	}
	else{
		$ret['status'] = 'error';
	}
	OC_Log::write('files_sharding', 'Setting password hash', OC_Log::WARN);
}

OCP\JSON::encodedPrint($ret);

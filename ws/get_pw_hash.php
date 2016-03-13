<?php

	OCP\App::checkAppEnabled('files_sharding');

include_once("files_sharding/lib/session.php");
include_once("files_sharding/lib/lib_files_sharding.php");


$ret = array();

if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['error'] = "Network not secure";
}
else{
	$user_id = $_POST['user_id'];
	$pwHash = \OCA\FilesSharding\Lib::dbGetPwHash($user_id);
	if(empty($pwHash)) {
		$ret['error'] = "User not found";
	}
	else{
		$ret['pw_hash'] = $pwHash;
	}
	OC_Log::write('files_sharding', 'Giving out password hash', OC_Log::WARN);
}

//OCP\JSON::encodedPrint(Session::unserialize($data));
OCP\JSON::encodedPrint($ret);

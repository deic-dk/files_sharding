<?php

	OCP\App::checkAppEnabled('files_sharding');

include_once("files_sharding/lib/lib_files_sharding.php");

$ret = array();
$pwOk = false;

if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['status'] = 'error';
	$ret['error'] = "Network not secure";
}
else{
	$user_id = $_POST['user_id'];
	// Delete local pw hash
	$pwOk = \OCA\FilesSharding\Lib::setPasswordHash($user_id, '');
	if($pwOk) {
		$ret['status'] = 'success';
	}
	else{
		$ret['status'] = 'error';
	}
	OC_Log::write('files_sharding', 'Deleted password hash for '.$user_id, OC_Log::WARN);
}

OCP\JSON::encodedPrint($ret);

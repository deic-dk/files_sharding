<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$user_id = empty($_GET['user_id'])?\OCP\USER::getUser():$_GET['user_id'];
$twofactor = $_GET['twofactor'];

OC_Log::write('files_sharding',"Setting twofactor access to ".$twofactor." for ".$user_id, OC_Log::WARN);

if(!OCA\FilesSharding\Lib::setServerForUser($user_id, null, null,
		$twofactor==='yes'?OCA\FilesSharding\Lib::$USER_ACCESS_TWO_FACTOR:OCA\FilesSharding\Lib::$USER_ACCESS_ALL)){
	$ret['error'] = "Failed setting twofactor authentication of ".$user_id." to ".$twofactor;
}
else{
	$ret['msg'] = "Set twofactor authentication of ".$user_id." to ".$twofactor;
}

OCP\JSON::encodedPrint($ret);

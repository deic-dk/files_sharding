<?php

	OCP\App::checkAppEnabled('files_sharding');

include_once("files_sharding/lib/session.php");
include_once("files_sharding/lib/lib_files_sharding.php");


$ret = array();

if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['error'] = "Network not secure";
}
else{
	$uid = $_POST['user_id'];
	
	$query = OC_DB::prepare( "SELECT `password` FROM `*PREFIX*users` WHERE `uid` = ?" );
	$result = $query->execute( array( $uid ))->fetchRow();
	if(!$result) {
		$ret['error'] = "User not found";
	}
	else{
		$ret['pw_hash'] = $result['password'];
	}
	OC_Log::write('files_sharding', 'Giving out password hash', OC_Log::WARN);
}

//OCP\JSON::encodedPrint(Session::unserialize($data));
OCP\JSON::encodedPrint($ret);

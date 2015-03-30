<?php

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$query=(isset($_POST['query']))?$_POST['query']:'';
$user_id=(isset($_POST['user_id']))?$_POST['user_id']:'';

if(!isset($_POST['user_id'])){
	http_response_code(401);
	exit;
}

if($query) {
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	$result = \OC::$server->getSearch()->search($query);
	//\OCP\Util::writeLog('search', 'Search results: '.json_encode($result), \OC_Log::WARN);
	OC_JSON::encodedPrint($result);
}
else {
	echo 'false';
}

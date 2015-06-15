<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

if(isset($_GET['user_id'])&&$_GET['user_id']){
	$user_id = $_GET['user_id'];
}
else{
	http_response_code(401);
	exit;
}

$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
$dir = $_GET['dir'];

if(!empty($user_id)){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
}

\OCP\Util::writeLog('files_sharding', 'ID: '.$id, \OC_Log::WARN);

$result = OCA\FilesSharding\Lib::rename($owner, $id, $dir, $_GET["file"], $_GET["newname"]);

OCP\JSON::encodedPrint($result);


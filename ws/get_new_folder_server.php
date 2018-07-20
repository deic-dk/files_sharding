<?php

OCP\JSON::checkAppEnabled('files_sharding');
//OCP\JSON::checkLoggedIn();
if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['error'] = "Network not secure";
	http_response_code(401);
	exit;
}

include_once("files_sharding/lib/lib_files_sharding.php");

$folder = $_POST['folder'];
$user_id = $_POST['user_id'];
$currentServerId = OCA\FilesSharding\Lib::dbLookupServerId($_SERVER['REMOTE_ADDR']);
$url = OCA\FilesSharding\Lib::dbGetNewServerForFolder($folder, $user_id, $currentServerId);
$status = empty($url)?'error: server '.$url.' not found':'success';
$ret = Array('url' => $url, 'status' => $status);

OCP\JSON::encodedPrint($ret);

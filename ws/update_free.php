<?php

OCP\JSON::checkAppEnabled('files_sharding');

$ret = array();

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$total = $_POST['total'];
$free = $_POST['free'];
$server_id = empty($_POST['server_id'])?null:$_POST['server_id'];
if(empty($server_id)){
	$hostname = $_SERVER['SERVER_NAME'];
	$server_id = OCA\FilesSharding\Lib::lookupServerId($hostname);
}

$ret = OCA\FilesSharding\Lib::dbUpdateFree($total, $free, $server_id);

OCP\JSON::encodedPrint($ret);


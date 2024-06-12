<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$owner = isset($_GET['owner'])?$_GET['owner'] : '';
$id = isset($_GET['id'])?$_GET['id'] : '';
$path = $_GET['path'];
$group = $_GET['group'];

\OCP\Util::writeLog('files_sharding', 'Share file ID: '.$id, \OC_Log::WARN);

session_write_close();

$result = OCA\FilesSharding\Lib::deleteFileShare($owner, $id);

// This script will only be called by a non-master client/slave,
// thus we also need to delete the fake directory/file created.

if(\OCA\FilesSharding\Lib::isMaster()){
	OCA\FilesSharding\Lib::deleteFileShareTarget($owner, $path, $group);
}

OCP\JSON::encodedPrint($result);


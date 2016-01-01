<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$id = isset($_GET['id']);
$path = $_GET['path'];

\OCP\Util::writeLog('files_sharding', 'ID: '.$id, \OC_Log::WARN);

$result = OCA\FilesSharding\Lib::deleteShareFileTarget($owner, $id, $path);

OCP\JSON::encodedPrint($result);


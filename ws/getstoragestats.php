<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$dir = !empty($_GET['dir'])?$_GET['dir']:'/';
$owner = !empty($_GET['owner'])?$_GET['owner']:'';
$id = !empty($_GET['id'])?$_GET['id']:'';
$group = !empty($_GET['group'])?$_GET['group']:'';


\OC_Util::setupFS();
$ret = OCA\FilesSharding\Lib::buildFileStorageStatistics($dir, $owner, $id, $group);

OCP\JSON::success($ret);
<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$files = $_GET["files"];
$dir = $_GET["dir"];
$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
$dirId = isset($_GET['dir_id']) ? $_GET['dir_id'] : '';
$group = isset($_GET['group']) ? $_GET['group'] : '';
$storage = isset($_GET['storage']) ? $_GET['storage']!="false" :false;

// TODO: Check of user_id is allowed to read files - perhaps already done by get().
//       --- Well, in general user_id will not exist on the same node as owner.


\OCA\FilesSharding\Lib::serveFiles($files, $dir, $owner, $id, $group, $dirId, $storage);

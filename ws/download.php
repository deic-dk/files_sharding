<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$files = $_GET["files"];
$dir = $_GET["dir"];
$user_id = isset($_GET['user_id'])&&$_GET['user_id'] ? $_GET['user_id'] : OCP\USER::getUser();
$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
if(!empty($owner)){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
}
if(!empty($id)){
	$path = \OC\Files\Filesystem::getPath($id);
	$dir = substr($path, 0, strrpos($path, '/'));
}
if(!empty($owner)){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
}
// TODO: Check of user_id is allowed to read files - perhaps already done by get().

$files_list = json_decode($files);
// in case we get only a single file
if (!is_array($files_list)) {
	$files_list = array($files);
}

\OCP\Util::writeLog('files_sharding', 'files: '.$files.', dir: '.$dir.', owner: '.$owner, \OC_Log::WARN);

OC_Files::get($dir, $files_list, $_SERVER['REQUEST_METHOD'] == 'HEAD');

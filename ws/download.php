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
$dirId = isset($_GET['dir_id']) ? $_GET['dir_id'] : '';
$group = isset($_GET['group']) ? $_GET['group'] : '';
$group_dir_owner = \OCP\USER::getUser();

if(!empty($owner)){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
	$group_dir_owner = $owner;
}

if(!empty($group) && !empty($group_dir_owner)){
	\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
}

if(!empty($id)){
	$path = \OC\Files\Filesystem::getPath($id);
	$files = basename($path);
	$dir = dirname($path);
}

if(!empty($dirId)){
	$dir = \OC\Files\Filesystem::getPath($dirId);
}
/*if(!empty($user_id)){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
}*/
// TODO: Check of user_id is allowed to read files - perhaps already done by get().
//       --- Well, in general user_id will not exist on the same node as owner.

$files_list = json_decode($files);
// in case we get only a single file
if (!is_array($files_list)) {
	$files_list = array($files);
}

\OCP\Util::writeLog('files_sharding', 'files: '.$files.', dir: '.$dir.', owner: '.$owner, \OC_Log::WARN);

if(!empty($dir)){
	OC_Files::get($dir, $files_list, $_SERVER['REQUEST_METHOD'] == 'HEAD');
}
else{
	\OCP\Util::writeLog('files_sharding', 'ERROR: file(s)  not found '.$id.'-->'.$_GET["files"], \OC_Log::ERROR);
}


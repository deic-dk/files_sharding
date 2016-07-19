<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$user_id = isset($_POST['user_id'])&&$_POST['user_id'] ? $_POST['user_id'] : OCP\USER::getUser();
$owner = isset($_POST['owner']) ? $_POST['owner'] : '';
$id = isset($_POST["id"])?stripslashes($_POST["id"]):'';
$dir = isset($_POST["dir"])?stripslashes($_POST["dir"]):'';
$file = isset($_POST["file"])?stripslashes($_POST["file"]):'';
$target = isset($_POST["target"])?stripslashes(rawurldecode($_POST["target"])):'';
$group = isset($_REQUEST['group']) ? $_REQUEST['group'] : '';
$group_dir_owner = \OCP\USER::getUser();

if(empty($dir) || empty($target) || empty($file)){
	http_response_code(400);
	exit();
}

if(!empty($owner)){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
	$group_dir_owner = $owner;
}
elseif(!empty($user_id)){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	$group_dir_owner = $user_id;
}

if(!empty($group) && !empty($group_dir_owner)){
	\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
}

if(!empty($id)){
	$path = \OC\Files\Filesystem::getPath($id);
	$path = stripslashes($path);
	$base = preg_replace('|'.$dir.'$|', '', $path);
	$dir = $path;
	$target = $base.$target;
}

// TODO: Check permissions

if(!\OC\Files\Filesystem::file_exists($target . '/' . $file) &&($target != '' || strtolower($file) != 'shared')) {
	$targetFile = \OC\Files\Filesystem::normalizePath($target . '/' . $file);
	$sourceFile = \OC\Files\Filesystem::normalizePath($dir . '/' . $file);
	\OCP\Util::writeLog('files_sharding', 'Renaming '.$base.':'.$sourceFile.'-->'.$targetFile, \OC_Log::WARN);
	$result = \OC\Files\Filesystem::rename($sourceFile, $targetFile);
}

OCP\JSON::encodedPrint($result);


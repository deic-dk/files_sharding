<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$dir = stripslashes($_POST["dir"]);
$allFiles = isset($_POST["allfiles"]) ? $_POST["allfiles"] : false;

if(isset($_POST['user_id'])&&$_POST['user_id']){
	$user_id = $_POST['user_id'];
}
else{
	http_response_code(401);
	exit;
}

$owner = isset($_POST['owner']) ? $_POST['owner'] : '';
$id = isset($_POST['id']) ? $_POST['id'] : '';

if(!empty($owner)){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
}

elseif(!empty($user_id)){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
}

if(!empty($id)){
	$path = \OC\Files\Filesystem::getPath($id);
	$dir = substr($path, 0, strrpos($path, '/'));
}

// TODO: Check permissions.
// delete all files in dir ?
if ($allFiles === 'true') {
	$files = array();
	$fileList = \OC\Files\Filesystem::getDirectoryContent($dir);
	foreach ($fileList as $fileInfo) {
		$files[] = $fileInfo['name'];
	}
} else {
	$files = isset($_POST["file"]) ? $_POST["file"] : $_POST["files"];
	$files = json_decode($files);
}
$filesWithError = '';

$success = true;

//Now delete
foreach ($files as $file) {
	if(\OC\Files\Filesystem::file_exists($dir . '/' . $file) &&
			!\OC\Files\Filesystem::unlink($dir . '/' . $file)) {
		\OCP\Util::writeLog('files_sharding', 'Could not delete file '.$dir . '/' . $file.' --> '.
				\OC\Files\Filesystem::file_exists($dir . '/' . $file), \OC_Log::WARN);
		$filesWithError .= $file . "\n";
		$success = false;
	}
}

// TODO
$storageStats = \OCA\Files\Helper::buildFileStorageStatistics($dir);

$ret = array();
$ret['files'] = $files;
$ret['filesWithError'] = $filesWithError;
$ret['success'] = $success;

\OCP\Util::writeLog('files_sharding', 'deleted files: '.json_encode($files).', dir: '.$dir.', owner: '.$owner, \OC_Log::WARN);

OCP\JSON::encodedPrint($ret);



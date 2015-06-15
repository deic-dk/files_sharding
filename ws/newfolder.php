<?php

// Init owncloud

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	if(!OC_User::isLoggedIn()) {
		http_response_code(401);
		exit;
	}
}

//OCP\JSON::callCheck();
//\OC::$session->close();

// Get the params
$dir = isset( $_POST['dir'] ) ? stripslashes($_POST['dir']) : '';
$foldername = isset( $_POST['foldername'] ) ? stripslashes($_POST['foldername']) : '';
$user_id = isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : '';
$owner = isset( $_REQUEST['owner'] ) ? $_REQUEST['owner'] : '';
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';

if($owner){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
}
elseif($user_id && !\OCP\USER::getUser()){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
}

if($id){
	$dir = \OC\Files\Filesystem::getPath($id);
	\OCP\Util::writeLog('files_sharding', 'DIR: '.$dir.', PATH: '.$path.', ID: '.$id, \OC_Log::WARN);
}

$l10n = \OC_L10n::get('files');

$result = array(
	'success' 	=> false,
	'data'		=> NULL
	);

if(trim($foldername) === '') {
	$result['data'] = array('message' => $l10n->t('Folder name cannot be empty.'));
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}

if(!OCP\Util::isValidFileName($foldername)) {
	$result['data'] = array('message' => (string)$l10n->t("Invalid name, '\\', '/', '<', '>', ':', '\"', '|', '?' and '*' are not allowed."));
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}

if (!\OC\Files\Filesystem::file_exists($dir . '/')) {
	$result['data'] = array('message' => (string)$l10n->t(
			'The target folder has been moved or deleted.'),
			'code' => 'targetnotfound'
		);
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}

//TODO why is stripslashes used on foldername here but not in newfile.php?
$target = $dir . '/' . stripslashes($foldername);
		
if (\OC\Files\Filesystem::file_exists($target)) {
	$result['data'] = array('message' => $l10n->t(
			'The name %s is already used in the folder %s. Please choose a different name.',
			array($foldername, $dir))
		);
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}

if(\OC\Files\Filesystem::mkdir($target)) {
	if ( $dir !== '/') {
		$path = $dir.'/'.$foldername;
	} else {
		$path = '/'.$foldername;
	}
	$meta = \OC\Files\Filesystem::getFileInfo($path);
	$meta['type'] = 'dir'; // missing ?!
	OCP\JSON::success(array('data' => \OCA\Files\Helper::formatFileInfo($meta)));
	myexit($user_id, $owner);
}

function myexit($user_id, $owner){
	if($user_id && $owner && $user_id != $owner){
		// If not done, the user shared with will now be logged in as $owner
		\OC_Util::teardownFS();
		\OC_User::setUserId($user_id);
		\OC_Util::setupFS($user_id);
	}
	exit();
}

OCP\JSON::error(array('data' => array( 'message' => $l10n->t('Error when creating the folder') )));

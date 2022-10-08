<?php

require_once __DIR__ . '/../../../lib/base.php';

//\OC::$session->close();

// Get the params
$dir = isset( $_REQUEST['dir'] ) ? '/'.trim($_REQUEST['dir'], '/\\') : '';
$filename = isset( $_REQUEST['filename'] ) ? trim($_REQUEST['filename'], '/\\') : '';
$content = isset( $_REQUEST['content'] ) ? $_REQUEST['content'] : '';
$source = isset( $_REQUEST['source'] ) ? trim($_REQUEST['source'], '/\\') : '';
$user_id = isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : \OCP\USER::getUser();
$owner = isset( $_REQUEST['owner'] ) ? $_REQUEST['owner'] : '';
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$group = isset($_REQUEST['group']) ? $_REQUEST['group'] : '';
$group_dir_owner = \OCP\USER::getUser();
// Can be 'overwrite', 'append' or 'backoff'
$overwrite = isset($_REQUEST['overwrite']) ? $_REQUEST['overwrite'] : 'overwrite';
$storage = isset($_REQUEST["storage"]) ? $_REQUEST["storage"]!="false" :false;

if(!OCA\FilesSharding\Lib::checkIP()){
	if(!OC_User::isLoggedIn()) {
		http_response_code(401);
		exit;
	}
}

if($owner){
	\OC_Util::teardownFS();
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
	$group_dir_owner = $owner;
}
elseif($user_id && !\OCP\USER::getUser()){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	$group_dir_owner = $user_id;
}

if(!empty($group) && !empty($group_dir_owner)){
	\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
}

if($storage){
	\OC_Util::teardownFS();
	\OC\Files\Filesystem::init(\OCP\User::getUser(), '/'.\OCP\User::getUser().'/files_external/storage/');
}

if($id){
	$dir = \OC\Files\Filesystem::getPath($id);
	\OCP\Util::writeLog('files_sharding', 'DIR: '.$dir.', NAME: '.$filename.', ID: '.$id, \OC_Log::WARN);
}

global $eventSource;

if($source) {
	$eventSource=new OC_EventSource();
} else {
	//OC_JSON::callCheck();
}

\OCP\Util::writeLog('files_sharding','owner: '.$owner, \OCP\Util::WARN);

function progress($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max) {
	static $filesize = 0;
	static $lastsize = 0;
	global $eventSource;

	switch($notification_code) {
		case STREAM_NOTIFY_FILE_SIZE_IS:
			$filesize = $bytes_max;
			break;

		case STREAM_NOTIFY_PROGRESS:
			if ($bytes_transferred > 0) {
				if (!isset($filesize)) {
				} else {
					$progress = (int)(($bytes_transferred/$filesize)*100);
					if($progress>$lastsize) { //limit the number or messages send
						$eventSource->send('progress', $progress);
					}
					$lastsize=$progress;
				}
			}
			break;
	}
}

function read_file_header($target, $header){
	$content = \OC\Files\Filesystem::file_get_contents($target);
	$contentArray = explode("\n", $content);
	$pattern = '/^'.$header.':(.*)$/';
	foreach($contentArray as $line){
		if(preg_match($pattern, $line, $matches) && isset($matches[1])){
			return trim($matches[1]);
		}
	}
	return null;
}

$l10n = \OC_L10n::get('files');

$result = array(
	'success' 	=> false,
	'data'		=> NULL
);
$trimmedFileName = trim($filename);

if($trimmedFileName === '') {
	$result['data'] = array('message' => (string)$l10n->t('File name cannot be empty.'));
	restoreUser($user_id, $owner);
	OCP\JSON::error($result);
	exit();
}
if($trimmedFileName === '.' || $trimmedFileName === '..') {
	$result['data'] = array('message' => (string)$l10n->t('"%s" is an invalid file name.', $trimmedFileName));
	restoreUser($user_id, $owner);
	OCP\JSON::error($result);
	exit();
}

if(!OCP\Util::isValidFileName($filename)) {
	$result['data'] = array('message' => (string)$l10n->t("Invalid name, '\\', '/', '<', '>', ':', '\"', '|', '?' and '*' are not allowed."));
	restoreUser($user_id, $owner);
	OCP\JSON::error($result);
	exit();
}

if (!\OC\Files\Filesystem::file_exists($dir . '/')) {
	$result['data'] = array('message' => (string)$l10n->t(
			'The target folder '.\OCP\USER::getUser().': '.$dir.'/ has been moved or deleted.'),
			'code' => 'targetnotfound'
		);
	restoreUser($user_id, $owner);
	OCP\JSON::error($result);
	exit();
}

//TODO why is stripslashes used on foldername in newfolder.php but not here?
$target = $dir.'/'.$filename;

$append = false;
if (\OC\Files\Filesystem::file_exists($target)) {
	if(empty($overwrite) || $overwrite==='backoff'){
		$result['data'] = array('message' => (string)$l10n->t(
				'The name %s is already used in the folder %s. Please choose a different name.',
				array($filename, $dir))
		);
		restoreUser($user_id, $owner);
		OCP\JSON::error($result);
		exit();
	}
	elseif($overwrite==='append'){
		// Check if the file has header 'Comments: on'.
		if(empty($user_id) && read_file_header($target, 'Comments')!=='on'){
			$result['data'] = array('message' => (string)$l10n->t("Forbidden, '\\', '/', '<', '>', ':', '\"', '|', '?' and '*' are not allowed."));
			restoreUser($user_id, $owner);
			OCP\JSON::error($result);
			exit();
		}
		$append = true;
	}
}
elseif(empty($user_id)){
	// Empty user_id is only allowed for appending comments to blog md file, which has this
	// allowed in the header with 'Comments: on'.
	$result['data'] = array('message' => (string)$l10n->t("Forbidden, '\\', '/', '<', '>', ':', '\"', '|', '?' and '*' are not allowed."));
	restoreUser($user_id, $owner);
	OCP\JSON::error($result);
	exit();
}

if($source) {
	if(substr($source, 0, 8)!='https://' and substr($source, 0, 7)!='http://') {
		restoreUser($user_id, $owner);
		OCP\JSON::error(array('data' => array('message' => $l10n->t('Not a valid source'))));
		exit();
	}

	if (!ini_get('allow_url_fopen')) {
		$eventSource->send('error', array('message' => $l10n->t('Server is not allowed to open URLs, please check the server configuration')));
		$eventSource->close();
		restoreUser($user_id, $owner);
		exit();
	}

	$ctx = stream_context_create(null, array('notification' =>'progress'));
	$sourceStream=@fopen($source, 'rb', false, $ctx);
	$result = 0;
	if (is_resource($sourceStream)) {
		$meta = stream_get_meta_data($sourceStream);
		if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
			//check stream size
			//$storageStats = \OCA\Files\Helper::buildFileStorageStatistics($dir);
			$storageStats = \OCA\FilesSharding\Lib::buildFileStorageStatistics($dir, $owner, $id, $group);
			$freeSpace = $storageStats['freeSpace'];

			foreach($meta['wrapper_data'] as $header) {
				list($name, $value) = explode(':', $header);
				if ('content-length' === strtolower(trim($name))) {
					$length = (int) trim($value);

					if ($length > $freeSpace) {
						$delta = $length - $freeSpace;
						$humanDelta = OCP\Util::humanFileSize($delta);

						$eventSource->send('error', array('message' => (string)$l10n->t('The file exceeds your available space by %s', array($humanDelta))));
						$eventSource->close();
						fclose($sourceStream);
						restoreUser($user_id, $owner);
						exit();
					}
				}
			}
		}
		$result=\OC\Files\Filesystem::file_put_contents($target, $sourceStream);
	}
	if($result) {
		$meta = \OC\Files\Filesystem::getFileInfo($target);
		$data = \OCA\Files\Helper::formatFileInfo($meta);
		$eventSource->send('success', $data);
	} else {
		$eventSource->send('error', array('message' => $l10n->t('Error while downloading %s to %s', array($source, $target))));
	}
	if (is_resource($sourceStream)) {
		fclose($sourceStream);\OC_Util::teardownFS();
	}
	$eventSource->close();
	restoreUser($user_id, $owner);
	exit();
} else {
	$success = false;
	if (!$content) {
		$templateManager = OC_Helper::getFileTemplateManager();
		$mimeType = OC_Helper::getMimetypeDetector()->detectPath($target);
		$content = $templateManager->getTemplate($mimeType);
	}

	if($content) {
		if($append){
			$origContent = \OC\Files\Filesystem::file_get_contents($target);
			$content = $origContent . $content;
		}
		$success = \OC\Files\Filesystem::file_put_contents($target, $content);
	} else {
		$success = \OC\Files\Filesystem::touch($target);
	}

	if($success) {
		$meta = \OC\Files\Filesystem::getFileInfo($target);
		restoreUser($user_id, $owner);
		OCP\JSON::success(array('data' => \OCA\Files\Helper::formatFileInfo($meta)));
		exit();
	}
}

function restoreUser($user_id, $owner){
	if($user_id && $owner && $user_id != $owner){
		// If not done, the user shared with will now be logged in as $owner
		\OC_Util::teardownFS();
		\OC_User::setUserId($user_id);
		\OC_Util::setupFS($user_id);
	}
}

OCP\JSON::error(array('data' => array( 'message' => $l10n->t('Error when creating the file') )));

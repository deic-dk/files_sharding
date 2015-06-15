<?php

require_once __DIR__ . '/../../../lib/base.php';

//\OC::$session->close();

// Get the params
$dir = isset( $_REQUEST['dir'] ) ? '/'.trim($_REQUEST['dir'], '/\\') : '';
$filename = isset( $_REQUEST['filename'] ) ? trim($_REQUEST['filename'], '/\\') : '';
$content = isset( $_REQUEST['content'] ) ? $_REQUEST['content'] : '';
$source = isset( $_REQUEST['source'] ) ? trim($_REQUEST['source'], '/\\') : '';
$user_id = isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : '';
$owner = isset( $_REQUEST['owner'] ) ? $_REQUEST['owner'] : '';
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';

if(!OCA\FilesSharding\Lib::checkIP()){
	if(!OC_User::isLoggedIn()) {
		http_response_code(401);
		exit;
	}
}

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

// Init owncloud
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

$l10n = \OC_L10n::get('files');

$result = array(
	'success' 	=> false,
	'data'		=> NULL
);
$trimmedFileName = trim($filename);

if($trimmedFileName === '') {
	$result['data'] = array('message' => (string)$l10n->t('File name cannot be empty.'));
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}
if($trimmedFileName === '.' || $trimmedFileName === '..') {
	$result['data'] = array('message' => (string)$l10n->t('"%s" is an invalid file name.', $trimmedFileName));
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}

if(!OCP\Util::isValidFileName($filename)) {
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

//TODO why is stripslashes used on foldername in newfolder.php but not here?
$target = $dir.'/'.$filename;

if (\OC\Files\Filesystem::file_exists($target)) {
	$result['data'] = array('message' => (string)$l10n->t(
			'The name %s is already used in the folder %s. Please choose a different name.',
			array($filename, $dir))
		);
	OCP\JSON::error($result);
	myexit($user_id, $owner);
}

if($source) {
	if(substr($source, 0, 8)!='https://' and substr($source, 0, 7)!='http://') {
		OCP\JSON::error(array('data' => array('message' => $l10n->t('Not a valid source'))));
		myexit($user_id, $owner);
	}

	if (!ini_get('allow_url_fopen')) {
		$eventSource->send('error', array('message' => $l10n->t('Server is not allowed to open URLs, please check the server configuration')));
		$eventSource->close();
		myexit($user_id, $owner);
	}

	$ctx = stream_context_create(null, array('notification' =>'progress'));
	$sourceStream=@fopen($source, 'rb', false, $ctx);
	$result = 0;
	if (is_resource($sourceStream)) {
		$meta = stream_get_meta_data($sourceStream);
		if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
			//check stream size
			$storageStats = \OCA\Files\Helper::buildFileStorageStatistics($dir);
			$freeSpace = $storageStats['freeSpace'];

			foreach($meta['wrapper_data'] as $header) {
				list($name, $value) = explode(':', $header);
				if ('content-length' === strtolower(trim($name))) {
					$length = (int) trim($value);

					if ($length > $freeSpace) {
						$delta = $length - $freeSpace;
						$humanDelta = OCP\Util::humanFileSize($delta);

						$eventSource->send('error', array('message' => (string)$l10n->t('The file exceeds your quota by %s', array($humanDelta))));
						$eventSource->close();
						fclose($sourceStream);
						myexit($user_id, $owner);
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
		fclose($sourceStream);
	}
	$eventSource->close();
	myexit($user_id, $owner);
} else {
	$success = false;
	if (!$content) {
		$templateManager = OC_Helper::getFileTemplateManager();
		$mimeType = OC_Helper::getMimetypeDetector()->detectPath($target);
		$content = $templateManager->getTemplate($mimeType);
	}

	if($content) {
		$success = \OC\Files\Filesystem::file_put_contents($target, $content);
	} else {
		$success = \OC\Files\Filesystem::touch($target);
	}

	if($success) {
		$meta = \OC\Files\Filesystem::getFileInfo($target);
		OCP\JSON::success(array('data' => \OCA\Files\Helper::formatFileInfo($meta)));
		myexit($user_id, $owner);
	}
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

OCP\JSON::error(array('data' => array( 'message' => $l10n->t('Error when creating the file') )));

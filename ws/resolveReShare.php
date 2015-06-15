<?php

OCP\JSON::checkAppEnabled('files_sharding');

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$linkItem = json_decode($_POST['linkItem'], true);

$rootLinkItem = \OCP\Share::resolveReShare($linkItem);

// Now get the path (for upload.php)
OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
// Setup FS with owner
OC_Util::tearDownFS();
OC_Util::setupFS($rootLinkItem['uid_owner']);
// The token defines the target directory (security reasons)
$path = \OC\Files\Filesystem::getPath($linkItem['file_source']);
$rootLinkItem['path'] = $path;
//

\OCP\Util::writeLog('files_sharding', 'Returning rootLinkItem '.serialize($linkItem).' --> '.serialize($rootLinkItem), \OC_Log::WARN);

OCP\JSON::encodedPrint($rootLinkItem);


<?php

OCP\JSON::checkAppEnabled('files_sharding');

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$linkItem = json_decode($_POST['linkItem'], true);
$group = empty($_POST['group'])?'':$_POST['group'];

$rootLinkItem = \OCA\FilesSharding\Lib::resolveReShare($linkItem);

// Now get the path (for upload.php)
OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
// Setup FS with owner
OC_Util::tearDownFS();
if(!empty($group)){
	//\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$rootLinkItem['uid_owner'].'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($rootLinkItem['uid_owner'], $groupDir);
}
else{
	OC_Util::setupFS($rootLinkItem['uid_owner']);
}
// The token defines the target directory (security reasons)
$path = \OC\Files\Filesystem::getPath($linkItem['file_source']);
$rootLinkItem['path'] = $path;
//

\OCP\Util::writeLog('files_sharding', 'Returning rootLinkItem '.serialize($linkItem).' --> '.serialize($rootLinkItem), \OC_Log::WARN);

OCP\JSON::encodedPrint($rootLinkItem);


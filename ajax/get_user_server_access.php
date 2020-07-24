<?php

OCP\JSON::checkAppEnabled('files_sharding');
//OCP\JSON::checkLoggedIn();

if(isset($_GET['user_id'])){
	$user_id = $_GET['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}
$server_id = isset($_GET['server_id'])?$_GET['server_id']:'';

$access = OCA\FilesSharding\Lib::getUserServerAccess($server_id, $user_id);

// If access is USER_ACCESS_ALL, set a cookie, remembering it for 10 minutes
\OCP\Util::writeLog('files_sharding', 'Access: '.$access, \OC_Log::WARN);
if(((int)$access)===OCA\FilesSharding\Lib::$USER_ACCESS_ALL){
	$secure_cookie = OC_Config::getValue("forcessl", false);
	$expires = time() + OCA\FilesSharding\Lib::$ACCESS_OK_COOKIE_SECONDS;
	$domain = OCA\FilesSharding\Lib::getCookieDomain();
	\OCP\Util::writeLog('files_sharding', 'Setting cookie oc_access_ok, '.$domain, \OC_Log::WARN);
	setcookie("oc_access_ok", "1", $expires, \OC::$WEBROOT . '/', $domain, $secure_cookie);
	/*$date = new DateTime();
	$date->setTimestamp($expires);
	header('Set-Cookie: '.OCA\FilesSharding\Lib::$ACCESS_OK_COOKIE.'=1; expires='.$date->format(DateTime::COOKIE).
			'; path='.\OC::$WEBROOT . '/'.'; domain='.$domain.
			'; sameSite=None; secure');*/
}

$ret = Array('access' => $access);

OCP\JSON::encodedPrint($ret);

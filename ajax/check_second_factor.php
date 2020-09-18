<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

if(isset($_GET['user_id'])){
	$user_id = $_GET['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}

$token = $_GET['token'];

if(OCA\FilesSharding\Lib::checkOneTimeToken($user_id, $token)){
	
	$secure_cookie = OC_Config::getValue("forcessl", false);
	$expires = time() + OCA\FilesSharding\Lib::$ACCESS_OK_COOKIE_SECONDS;
	$domain = OCA\FilesSharding\Lib::getCookieDomain();
	\OCP\Util::writeLog('files_sharding', 'Setting cookie oc_access_ok, '.$domain, \OC_Log::WARN);
	setcookie(OCA\FilesSharding\Lib::$ACCESS_OK_COOKIE, "1", $expires, \OC::$WEBROOT . '/', $domain, $secure_cookie);
	/*$date = new DateTime();
	$date->setTimestamp($expires);
	header('Set-Cookie: '.OCA\FilesSharding\Lib::$ACCESS_OK_COOKIE.'=1; expires='.$date->format(DateTime::COOKIE).
			'; path='.\OC::$WEBROOT . '/'.'; domain='.$domain.
			'; sameSite=None; secure');*/
	$ret = Array('token' => $token);
	OCP\JSON::success($ret);
}
else{
	OCP\JSON::error();
}


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
	setcookie("oc_access_ok", "1", $expires, '/', $domain, $secure_cookie);
	
	$ret = Array('token' => $token);
	OCP\JSON::success($ret);
}
else{
	OCP\JSON::error();
}


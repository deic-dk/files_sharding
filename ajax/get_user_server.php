<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

if(isset($_GET['user_id'])){
	$user_id = $_GET['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}
$internal = isset($_GET['internal'])?$_GET['internal'] && $_GET['internal']!=="false" && $_GET['internal']!=="no":false;

$url = OCA\FilesSharding\Lib::getServerForUser($user_id, $internal);

if(!empty($url)){
	$status ='success';
}
else{
	// If no server has been set for the user, he can logically only be on the master
	$status = 'success: server '.$url.' not found, using master';
	$url = $internal?OCA\FilesSharding\Lib::getMasterInternalURL():OCA\FilesSharding\Lib::getMasterURL();
}

$parse = parse_url($url);
$user_host = $parse['host'];

$same = OCA\FilesSharding\Lib::onServerForUser($user_id);

$ret = Array('url' => $url, 'same' => $same, 'status' => $status);

OCP\JSON::encodedPrint($ret);

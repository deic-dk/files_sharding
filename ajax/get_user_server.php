<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

if(isset($_GET['user_id'])){
	$user_id = $_GET['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}
$internal = isset($_GET['internal'])?$_GET['internal']:false;

$url = OCA\FilesSharding\Lib::getServerForUser($user_id, $internal && $internal!=="false" && $internal!=="no");

if(!empty($url)){
	$parse = parse_url($url);
	$user_host = $parse['host'];
}
else{
	// If no server has been set for the user, he can logically only be on the master
	$user_host = OCA\FilesSharding\Lib::getMasterURL();
}

$same = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===$user_host ||
				isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===$user_host;

if(empty($url)){
	$status = 'success: server '.$url.' not found, using master';
	$url = OCA\FilesSharding\Lib::getMasterInternalURL();
}
else{
	$status ='success';
}

$ret = Array('url' => $url, 'same' => $same, 'status' => $status);

OCP\JSON::encodedPrint($ret);

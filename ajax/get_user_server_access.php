<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

if(isset($_GET['user_id'])){
	$user_id = $_GET['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}
$server_id = isset($_GET['server_id'])?$_GET['server_id']:'';

$access = OCA\FilesSharding\Lib::getUserServerAccess($server_id, $user_id);

$ret = Array('access' => $access);

OCP\JSON::encodedPrint($ret);

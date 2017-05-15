<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

if(isset($_GET['user_id'])){
	$user_id = $_GET['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}

$forceNew = isset($_GET['force_new'])&&$_GET['force_new']==='yes';

$token = OCA\FilesSharding\Lib::getOneTimeToken($user_id, $forceNew);

if(OCA\FilesSharding\Lib::emailOneTimeToken($user_id, $token)){
	OCP\JSON::success();
}
else{
	OCP\JSON::error();
}


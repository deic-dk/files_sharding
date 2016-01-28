<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$url = $_POST['url'];
$site = $_POST['site'];
$charge = $_POST['charge'];
$allow_local_login = $_POST['allow_local_login'];

OC_Log::write('files_sharding',"Adding server: ".$url.", ".$allow_local_login, OC_Log::WARN);

if(!OCA\FilesSharding\Lib::dbAddServer($url, $site, $charge, $allow_local_login)){
	$ret['error'] = "Failed adding server ".$url;
}
else{
	$ret['msg'] = "Added server ".$url;
}

OCP\JSON::encodedPrint($ret);

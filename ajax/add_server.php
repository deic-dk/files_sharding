<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$id = $_POST['id'];
$url = $_POST['url'];
$internal_url = $_POST['internal_url'];
$site = $_POST['site'];
$charge = $_POST['charge'];
$allow_local_login = $_POST['allow_local_login'];
$x509_dn = $_POST['x509_dn'];
$description = $_POST['description'];

OC_Log::write('files_sharding',"Adding server: ".$url.", ".$allow_local_login, OC_Log::WARN);

if(!OCA\FilesSharding\Lib::dbAddServer($url, $internal_url, $site, $charge, $allow_local_login,
		$id, $x509_dn, $description)){
	$ret['error'] = "Failed adding server ".$url;
}
else{
	$ret['msg'] = "Added server ".$url;
}

OCP\JSON::encodedPrint($ret);

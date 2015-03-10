<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$allow_local_login = $_POST['allow_local_login'];
$id = $_POST['id'];

OC_Log::write('files_sharding',"Setting allow_local_login of server ".$id." to ".$allow_local_login, OC_Log::WARN);

if(!OCA\FilesSharding\Lib::dbSetAllowLocalLogin($id, $allow_local_login)){
	$ret['error'] = "Failed setting allow_local_login of server ".$id." to ".$allow_local_login;
}
else{
	$ret['msg'] = "Set allow_local_login of server ".$id." to ".$allow_local_login;
}

OCP\JSON::encodedPrint($ret);

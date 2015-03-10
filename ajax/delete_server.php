<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$id = $_POST['id'];

OC_Log::write('files_sharding',"Deleting server: ".$id, OC_Log::WARN);

if(!OCA\FilesSharding\Lib::dbDeleteServer($id)){
	$ret['error'] = "Failed deleting server ".$id;
}
else{
	$ret['msg'] = "Deleted server ".$id;
}

OCP\JSON::encodedPrint($ret);

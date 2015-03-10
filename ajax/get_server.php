<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

if(isset($_POST['user_id'])){
	$user_id = $_POST['user_id'];
}
else{
	$user_id = OCP\USER::getUser();
}
if(isset($_POST['priority'])){
	$priority = $_POST['priority'];
}
else{
	$priority = 0;
}
if(isset($_POST['site']) && !empty($_POST['site'])){
	$site = $_POST['site'];
	$server_id = OCA\FilesSharding\Lib::dbChooseServerForUser($user_id, $site, $priority, isset($_POST['exclude_server_id'])?$_POST['exclude_server_id']:null);
}
else{
	// Allow empty $server_id only for backup server
	$server_id = $priority===0?OCA\FilesSharding\Lib::dbLookupServerIdForUser($user_id, $priority):null;
}

if(empty($server_id) && $priority===0){
	\OCP\Util::writeLog('files_sharding', 'get_server: No server found via db for user '.$user_id. '. Using default', \OC_Log::WARN);
	$ret['error'] = "Failed getting server for ".$site;
}
else{
	\OCP\Util::writeLog('files_sharding', 'Getting server for '.$server_id.' via db for user '.$user_id, \OC_Log::WARN);
	$server_url = OCA\FilesSharding\Lib::dbLookupServerURL($server_id);
	$ret['server_url'] = $server_url;
	$ret['server_id'] = $server_id;
	$ret['msg'] = "Got server ".$server_id;
}

OCP\JSON::encodedPrint($ret);

<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\JSON::checkLoggedIn();

$user_id = empty($_GET['user_id'])?\OCP\USER::getUser():$_GET['user_id'];
$nopassword = $_GET['nopassword'];

OC_Log::write('files_sharding',"Setting nopassword access to ".nopassword." for ".$user_id, OC_Log::WARN);

$res = true;
if($nopassword=='yes'){
	$res = OCA\FilesSharding\Lib::setPasswordHash($user_id, '');
	// Set on home server as well
	if(\OCP\App::isEnabled('files_sharding')){
		if(\OCA\FilesSharding\Lib::isMaster() && !\OCA\FilesSharding\Lib::onServerForUser($user_id)){
			$serverURL = \OCA\FilesSharding\Lib::getServerForUser($user_id, true);
			$pwOk = \OCA\FilesSharding\Lib::ws('set_no_password_auth', array('user_id'=>$user_id), true, true, $serverURL);
			if($pwOk['status']!='success'){
				$res = false;
				OC_Log::write('ChangePassword','ERROR: Could not remove password for: '.
						$user_id." on ".$serverURL, \OC_Log::ERROR);
			}
		}
	}
}

if(!$res){
	$ret['error'] = "Failed setting nopassword authentication of ".$user_id." to ".$nopassword;
}
else{
	$ret['msg'] = "Set nopassword authentication of ".$user_id." to ".$nopassword;
}

OCP\JSON::encodedPrint($ret);

<?php

OCP\App::checkAppEnabled('files_sharding');

include_once("files_sharding/lib/lib_files_sharding.php");

$ret = array();
if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['error'] = "Network not secure";
	OC_Log::write('files_sharding', $ret['error'], OC_Log::ERROR);
}
else{
	$id = $_POST['id'];
	$session_save_path = trim(session_save_path());
	if(empty($session_save_path)){
		$session_save_path = "/tmp";
	}
	$data = file_get_contents($session_save_path."/sess_".$id);
	if(!$data){
		$ret['error'] = "File not found. ".$session_save_path."/sess_".$id;
		OC_Log::write('files_sharding', $ret['error'], OC_Log::ERROR);
	}
	else{
		session_id($id);
		session_reset();
		$user = $_SESSION['user_id'];
		
		if(empty($_SESSION["oc_mail"])){
			$display_name = \OCP\User::getDisplayName($user);
			$email = OC_Preferences::getValue($user, 'settings', 'email', '');
			$quota = OC_Preferences::getValue($user, 'files', 'quota');
			$freequota = OC_Preferences::getValue($user, 'files_accounting', 'freequota');
			
			$_SESSION["oc_display_name"] = $display_name;
			$_SESSION["oc_mail"] = $email;
			$_SESSION["oc_quota"] = $quota;
			$_SESSION["oc_freequota"] = $freequota;
			
			$storageId = 'home::'.$user;
			$numericStorageId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
			$_SESSION["oc_storage_id"] = $storageId;
			$_SESSION["oc_numeric_storage_id"] = $numericStorageId;
			
			session_write_close();
		}
		
		$data = file_get_contents($session_save_path."/sess_".$id);
		$ret['session'] = $data;
		OC_Log::write('files_sharding', 'Passing on session: '.serialize($_SESSION).'-->'.$session_save_path."/sess_".$id." --> ".$data, OC_Log::WARN);
	}
}

//OCP\JSON::encodedPrint(Session::unserialize($data));
OCP\JSON::encodedPrint($ret);

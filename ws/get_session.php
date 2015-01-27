<?php

OCP\App::checkAppEnabled('files_sharding');

include("lib/session.php");
include("lib/lib_files_sharding.php");

$ret = array();
if(!OC_Files_Sharding::checkIP()){
	$ret['error'] = "Network not secure";
}
else{
	$id = $_POST['id'];
	$session_save_path = trim(session_save_path());
	if(empty($session_save_path)){
		$session_save_path = "/tmp";
	}
	$data = file_get_contents($session_save_path."/sess_".$id);
	if(!$data){
		$ret['error'] = "File not found";
	}
	else{
		$ret['session'] = $data;
	}
}

OC_Log::write('files_sharding', 'Passing on session: '.$session_save_path."/sess_".$id." --> ".$data, OC_Log::WARN);

	//OCP\JSON::encodedPrint(Session::unserialize($data));
	OCP\JSON::encodedPrint($ret);

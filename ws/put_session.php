<?php

OCP\App::checkAppEnabled('files_sharding');

include("lib/session.php");
include("lib/lib_files_sharding.php");

$ret = array();
if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['error'] = "Network not secure";
}
else{
	$id = $_POST['id'];
	$data = json_decode($_POST['session']);
	$session_save_path = trim(session_save_path());
	\OC_Log::write('files_sharding',"Saving session ".(!empty($id)?$id:"NONE").":".$data.":".(file_exists("$session_save_path/sess_$id")?"$session_save_path/sess_$id":"not found"), \OC_Log::WARN);
	if(empty($session_save_path)){
		$session_save_path = "/tmp";
	}
	if(empty($data) && !empty($id) && file_exists("$session_save_path/sess_$id")) {
		\OC_Log::write('files_sharding',"Deleting session file "."$session_save_path/sess_$id", \OC_Log::WARN);
		$res = unlink("$session_save_path/sess_$id");
	}
	else{
		$res = file_put_contents("$session_save_path/sess_$id", $data);
	}
	if(!$res){
		$ret['error'] = "Could not save $session_save_path/sess_$id";
	}
}
		
OCP\JSON::encodedPrint($ret);

<?php

//require_once('apps/files_sharding/lib/lib_sharder.php');

OCP\App::registerPersonal('files_sharding', 'settings');

OC::$CLASSPATH['OCA\FilesSharding\PracticalSession'] = 'files_sharding/lib/practicalsession.php';
OC::$CLASSPATH['OCA\FilesSharding\Hooks'] = 'files_sharding/lib/hooks.php';
OC::$CLASSPATH['OCA\FilesSharding\FileSessionHandler'] = 'files_sharding/lib/filesessionhandler.php';

OCP\Util::connectHook('OC', 'initSession', 'OCA\FilesSharding\Hooks', 'initSession');
OCP\Util::connectHook('OC_User', 'logout', 'OCA\FilesSharding\Hooks', 'logout');

/*$user_id = OC_Chooser::checkIP();
$user_id = "fror@dtu.dk";

OC_Log::write('sharder','user_id '.$user_id,OC_Log::INFO);

if($user_id != '' && OC_User::userExists($user_id)){
   $_SESSION['user_id'] = $user_id;
   \OC_Util::setupFS();
}

if($_SERVER['HTTP_REFERER']===$_SERVER['SERVER_NAME']){
	setcookie('saml_auth_fail', 'notallowed', 0, '/', 'data.deic.dk', false, false);
}*/


<?php

//require_once('apps/sharder/lib/lib_sharder.php');

OCP\App::registerPersonal('sharder', 'settings');

#$user_id = OC_Chooser::checkIP();
#$user_id = "fror@dtu.dk";

#OC_Log::write('sharder','user_id '.$user_id,OC_Log::INFO);

#if($user_id != '' && OC_User::userExists($user_id)){
#   $_SESSION['user_id'] = $user_id;
#   \OC_Util::setupFS();
#}

#if($_SERVER['HTTP_REFERER']===$_SERVER['SERVER_NAME']){
#	setcookie('saml_auth_fail', 'notallowed', 0, '/', 'data.deic.dk', false, false);
#}


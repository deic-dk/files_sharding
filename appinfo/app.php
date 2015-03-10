<?php

require_once('apps/files_sharding/lib/lib_files_sharding.php');

if(OCA\FilesSharding\Lib::isMaster()){
	OCP\App::registerAdmin('files_sharding', 'settings');
	OCP\App::registerPersonal('files_sharding', 'personalsettings');
	return;
}

OC::$CLASSPATH['OCA\FilesSharding\PracticalSession'] = 'files_sharding/lib/practicalsession.php';
OC::$CLASSPATH['OCA\FilesSharding\Hooks'] = 'files_sharding/lib/hooks.php';
OC::$CLASSPATH['OCA\FilesSharding\FileSessionHandler'] = 'files_sharding/lib/filesessionhandler.php';

OCP\Util::connectHook('OC', 'initSession', 'OCA\FilesSharding\Hooks', 'initSession');
OCP\Util::connectHook('OC_User', 'logout', 'OCA\FilesSharding\Hooks', 'logout');


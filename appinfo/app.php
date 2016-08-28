<?php

require_once('apps/files_sharding/lib/lib_files_sharding.php');

require_once('apps/files_sharding/lib/myshare.php');


// This does not work: The sharing backend registers 'file' first and the present
// registration is ignored.
OC::$CLASSPATH['OC_Shard_Backend_File'] = 'files_sharding/lib/file.php';
OC\Share\MyShare::myRegisterBackend('file', 'OC_Shard_Backend_File', null, null);

// The idea is to get apps/files/list.php to display shared folders.
// list.php first calls getFileInfo() from filesystem.php which call the same from view.php.
// getFileInfo() needs an entry to exist in the local oc_filecache table.

OC::$CLASSPATH['OCA\Files\Share_files_sharding\Api'] = 'files_sharding/lib/files_sharing_api.php';
// This is to have items shared with me populated
OC_API::register('get', '/apps/files_sharding/api/v1/shares', array('\OCA\Files\Share_files_sharding\Api', 'getAllShares'), 'files_sharing');
//OC_API::register('post', '/apps/files_sharing/api/v1/shares', array('\OCA\Files\Share_files_sharding\Api', 'createShare'), 'files_sharing');
OC_API::register('get', '/apps/files_sharing/api/v1/shares/{id}', array('\OCA\Files\Share_files_sharding\Api', 'getShare'), 'files_sharing');
//OC_API::register('put', '/apps/files_sharing/api/v1/shares/{id}', array('\OCA\Files\Share_files_sharding\Api', 'updateShare'), 'files_sharing');
//OC_API::register('delete', '/apps/files_sharing/api/v1/shares/{id}', array('\OCA\Files\Share_files_sharding\Api', 'deleteShare'), 'files_sharing');

// Fix stuff in Lucene. TODO: remove when fixed upstream
// This is not working - presumably because the apps in question are loaded after this one.
// We do it in the theme instead (in js.js and search.php).
//OC_Search::removeProvider('OC\Search\Provider\File');
//OC_Search::removeProvider('OCA\Search_Lucene\Lucene');
OC::$CLASSPATH['OCA\Search_Lucene\MyLucene'] = 'files_sharding/lib/my_lucene.php';
OC_Search::registerProvider('OCA\Search_Lucene\MyLucene');

OC::$CLASSPATH['OCA\FilesSharding\SearchShared'] = 'apps/files_sharding/lib/search_shared.php';
OC_Search::registerProvider('OCA\FilesSharding\SearchShared');

// When search_lucene indexes files, it creates a View object with root /files/user_name,
// but without 'mounting' the user's directory, i.e. without calling sertupFS or initMountPoints().
// When then view->getFileInfo() is called, this results in creating oc_filecache entries
// under storage 2 with path /files/user_name/dir/file, duplicating the already existing entry
// under the user's storage with path /dir/file.
// We fix this by adding initMountPoints() to the hook.
OC_Hook::clear(OC\Files\Filesystem::CLASSNAME, OC\Files\Filesystem::signal_post_write);
OC_Hook::clear(OC\Files\Filesystem::CLASSNAME, OC\Files\Filesystem::signal_post_rename);
OCP\Util::connectHook(OC\Files\Filesystem::CLASSNAME, OC\Files\Filesystem::signal_post_write,
		'OCA\FilesSharding\Hooks', 'indexFile');
OCP\Util::connectHook(OC\Files\Filesystem::CLASSNAME, OC\Files\Filesystem::signal_post_rename,
'OCA\FilesSharding\Hooks', 'renameFile');

OC::$CLASSPATH['OCA\FilesSharding\Hooks'] = 'files_sharding/lib/hooks.php';
OCP\Util::connectHook('OC_Filesystem', 'post_rename', 'OCA\FilesSharding\Hooks', 'renameHook');
OCP\Util::connectHook('OC_Filesystem', 'post_delete', 'OCA\FilesSharding\Hooks', 'deleteHook');

OCP\App::registerPersonal('files_sharding', 'personalsettings');

// This is to avoid checking paths of shared files against own files - which triggers infinite (2) renaming when parent is -1
\OC_Hook::clear('OC_Filesystem', 'setup');
\OCP\Util::connectHook('OC_Filesystem', 'setup', 'OCA\FilesSharding\Hooks', 'noSharedSetup');

// Cron job for syncing users 
OC::$CLASSPATH['OCA\FilesSharding\BackgroundJob\SyncUser'] = 'apps/files_sharding/lib/backgroundjob/sync_user.php';
OC::$CLASSPATH['OCA\FilesSharding\BackgroundJob\DeleteUser'] = 'apps/files_sharding/lib/backgroundjob/sync_user.php';
require_once('apps/files_sharding/lib/backgroundjob/sync_user.php');
OCP\Backgroundjob::registerJob('OCA\FilesSharding\BackgroundJob\SyncUser');
OCP\Backgroundjob::registerJob('OCA\FilesSharding\BackgroundJob\DeleteUser');
// 

OCP\Util::addScript('files_sharding', 'access');

if(OCA\FilesSharding\Lib::isMaster()){
	OCP\App::registerAdmin('files_sharding', 'settings');
	OC::$CLASSPATH['ServerSync_Activity'] ='apps/files_sharding/lib/activity.php';
	\OC::$server->getActivityManager()->registerExtension(function() {
		return new ServerSync_Activity(
				\OC::$server->query('L10NFactory'),
				\OC::$server->getURLGenerator(),
				\OC::$server->getActivityManager(),
				\OC::$server->getConfig()
		);
	});
	// Bump up quota if smaller than freequota.
	// TODO: move to somewhere user is set. Or drop. Drop from filesessionhandler too...
		$user = \OCP\USER::getUser();
		if(!empty($user) && \OCP\App::isEnabled('files_accounting')){
			$quotas = \OCA\Files_Accounting\Storage_Lib::getQuotas($user);
			\OCP\Util::writeLog('files_sharding', 'Quotas: '.$user.':'.$quotas['quota'].'<' .$quotas['freequota'], \OC_Log::INFO);
			if(!empty($quotas['quota']) && !empty($quotas['freequota']) &&
					\OCP\Util::computerFileSize($quotas['quota']) < \OCP\Util::computerFileSize($quotas['freequota']) ||
					!empty($quotas['default_quota']) && !empty($quotas['freequota']) &&
					\OCP\Util::computerFileSize($quotas['default_quota']) < \OCP\Util::computerFileSize($quotas['freequota'])){
				\OCP\Config::setUserValue($uid, 'files', 'quota', $quotas['freequota']);
			}
		}
	return;
}

//\OCP\Util::connectHook('OC_Filesystem', 'setup', 'OCA\FilesSharding\Hooks', 'setup');
\OCP\Util::connectHook('OC_Filesystem', 'post_initMountPoints', 'OCA\FilesSharding\Hooks', 'setup');

OCP\Util::connectHook('OC', 'initSession', 'OCA\FilesSharding\Hooks', 'initSession');
OCP\Util::connectHook('OC_User', 'logout', 'OCA\FilesSharding\Hooks', 'logout');

OC::$CLASSPATH['OCA\FilesSharding\PracticalSession'] = 'files_sharding/lib/practicalsession.php';
OC::$CLASSPATH['OCA\FilesSharding\FileSessionHandler'] = 'files_sharding/lib/filesessionhandler.php';


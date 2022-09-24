<?php

namespace OCA\FilesSharding;

require_once __DIR__ . '/../../../lib/base.php';

class Hooks {

	private static $handler;
	
	public static function initSession($params){

		self::initHandler();
		
		if(empty($_COOKIE[$params['sessionName']])){
			return;
		}
		
		\OC_Log::write('files_sharding',"Checking session ".$_COOKIE[$params['sessionName']].": ".
				serialize($params['session']), \OC_Log::INFO);

		session_set_save_handler(
			array(self::$handler, 'open'),
			array(self::$handler, 'close'),
			array(self::$handler, 'read'),
			array(self::$handler, 'write'),
			array(self::$handler, 'destroy'),
			array(self::$handler, 'gc')
		);

		// the following prevents unexpected effects when using objects as save handlers
		register_shutdown_function('session_write_close');

		$params['session'] = new PracticalSession($params['sessionName']);
		$params['useCustomSession'] = true;

		//$newSession = self::getSession($sessionName);
		//$session = json_decode($newSession);
		//session_decode($newSession);
	
		return true;
	}
	
	public static function post_login($parameters) {
		$user = \OCP\USER::getUser();
		
		\OCA\FilesSharding\Lib::checkAdminIP($user);

		// Bump up quota if smaller than freequota.
		// Notice: Done in filesessionhandler too.
		if(\OCA\FilesSharding\Lib::isMaster() && !empty($user) && \OCP\App::isEnabled('files_accounting')){
			$quotas = \OCA\Files_Accounting\Storage_Lib::getQuotas($user);
			if(!empty($quotas['quota']) && !empty($quotas['freequota']) &&
					\OCP\Util::computerFileSize($quotas['quota']) <
						\OCP\Util::computerFileSize($quotas['freequota']) ||
					!empty($quotas['default_quota']) && !empty($quotas['freequota']) &&
					$quotas['default_quota'] != INF &&
					\OCP\Util::computerFileSize($quotas['default_quota']) <
						\OCP\Util::computerFileSize($quotas['freequota'])){
				\OCP\Util::writeLog('files_sharding', 'Updating quota to freequota for user: '.
						$user.':'.$quotas['quota'].'/'.$quotas['default_quota'].':'.
						\OCP\Util::computerFileSize($quotas['default_quota']).'-->' .$quotas['freequota'], \OC_Log::WARN);
				\OCP\Config::setUserValue($user, 'files', 'quota', $quotas['freequota']);
			}
		}
	}
	
	public static function logout($params){
		self::initHandler();
		$session = \OC::$server->getUserSession();
		$user = $session->getUser()->getUid();
		\OC_Log::write('files_sharding',"Logging out ".$user.":".serialize($params), \OC_Log::WARN);
		//\OC_User::logout();
		$session->unsetMagicInCookie();
		$session->setUser(null);
		$session->setLoginName(null);
		$instanceId = \OC_Config::getValue('instanceid', null);
		if(!empty($_COOKIE[$instanceId])){
			// This actually prevents a proper saml logout
			//self::$handler->putSession($_COOKIE[$instanceId], "");
			//self::$handler->destroy($_COOKIE[$instanceId]);
			// Do a saml logout
			if(\OCP\App::isEnabled('user_saml') && Lib::isMaster()){
				\OC_USER_SAML_Hooks::logout();
			}
			header('Location: ' . Lib::getMasterURL()."index.php?logout=true&requesttoken=".
			//$_SESSION["requesttoken"]);
			\OC_Util::callRegister());
			exit();
		}
	}
	
	private static function initHandler(){
		if(!isset(self::$handler)){
			self::$handler = new FileSessionHandler('/tmp');
		}
	}
	
	// NOT USED
	public static function setup($options) {
		// Don't load shares when called via webdav or cron
		if(empty($_SERVER['REQUEST_URI']) ||
			strpos($_SERVER['REQUEST_URI'], \OC::$WEBROOT."/remote.php/mydav")===0 ||
			strpos($_SERVER['REQUEST_URI'], \OC::$WEBROOT."/remote.php/webdav")===0 ||
			strpos($_SERVER['REQUEST_URI'], \OC::$WEBROOT."/files/")===0){
			return;
		}
		
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			$shares = \OCP\Share::getItemsSharedWith('file');
		}
		else{
			$shares = \OCA\FilesSharding\Lib::ws('getItemsSharedWith', array('user_id' => \OC_User::getUser(),
				'itemType' => 'file'));
		}
		$manager = \OC\Files\Filesystem::getMountManager();
		$loader = \OC\Files\Filesystem::getLoader();
		if (!\OCP\User::isLoggedIn() || \OCP\User::getUser() != $options['user']
				|| $shares
		) {
			foreach ($shares as $share) {
				// don't mount shares where we have no permissions
				if ($share['permissions'] > 0) {
					$mount = new \OCA\Files_Sharing\SharedMount(
							'\OC\Files\Storage\Shared',
							$options['user_dir'] . '/' . $share['file_target'],
							array(
									'share' => $share,
							),
							$loader
					);
					//\OCP\Util::writeLog('files_sharing','Adding mount '.serialize($share), \OCP\Util::WARN);
					$manager->addMount($mount);
				}
			}
		}
	}
	
	// When called properly by \OC_Hook::emit, $group is not set.
	// We need to call manually from rename.php when in a group folder, because
	// $params['oldpath] is relative to /user/files/
	public static function renameHook($params, $group=null){
		\OCP\Util::writeLog('files_sharing','RENAME '.serialize($params), \OCP\Util::WARN);
		if(!\OCP\App::isEnabled('files_sharding')){
			return true;
		}
		$user_id = \OCP\User::getUser();
		$id = \OCA\FilesSharding\Lib::getFileId($params['newpath'], $user_id, $group);
		$matchlen = \OCA\FilesSharding\Lib::stripleft($params['oldpath'], $params['newpath']);
		$oldname = substr($params['oldpath'], $matchlen);
		$newname = substr($params['newpath'], $matchlen);
		
		if(\OCA\FilesSharding\Lib::isMaster()){
			$res = \OCA\FilesSharding\Lib::renameShareFileTarget($user_id, $id, $oldname, $newname);
		}
		else{
			$oldname = implode('/', array_map('rawurlencode', explode('/', $oldname)));
			$newname = implode('/', array_map('rawurlencode', explode('/', $newname)));
			$res = \OCA\FilesSharding\Lib::ws('rename_share_file_target',
				array('owner' => $user_id, 'id' => $id, 'oldname' => $oldname, 'newname' => $newname));
		}
		return $res;
	}
	
	public static function deleteHook($params, $group=null){
		if(!\OCP\App::isEnabled('files_sharding')){
			return true;
		}
		$user_id = \OCP\User::getUser();
		$path = $params['path'];
		// This is the local ID, i.e. item_source
		$id = \OCA\FilesSharding\Lib::getFileId($path, $user_id, $group);
		\OCP\Util::writeLog('files_sharing','DELETE '.$user_id.'-->'.$id.'-->'.serialize($params), \OCP\Util::WARN);
		
		if(\OCA\FilesSharding\Lib::isMaster()){
			if($id){
				$res = \OCA\FilesSharding\Lib::deleteFileShare($user_id, $id);
			}
			else{
				$res = false;
				\OCP\Util::writeLog('files_sharing','ERROR: Could not delete share for file. '.serialize($params), \OCP\Util::WARN);
			}
			\OCA\FilesSharding\Lib::deleteFileShareTarget($user_id, $path, $group);
		}
		else{
			$path = implode('/', array_map('rawurlencode', explode('/', $path)));
			if(!empty($group)){
				$group = rawurlencode($group);
			}
			if($id){
				$res = \OCA\FilesSharding\Lib::ws('delete_file_share',
					array('owner' => $user_id, 'id' => $id, 'group'=>$group));
			}
			else{
				$res = false;
				\OCP\Util::writeLog('files_sharing','ERROR: Could not delete share for file. '.serialize($params), \OCP\Util::WARN);
			}
		}
		return $res;
	}
	
	public static function noSharedSetup(){
		\OCP\Util::writeLog('files_sharing','Clearing hook', \OCP\Util::DEBUG);
		\OC_Hook::clear('OC_Filesystem', 'setup');
	}
	
	///// All this is here to avoid the double entries in oc_filecache, caused by 
	///// index jobs running without 'mounted' /username/files.
	public static function indexFile(array $param) {
		if (isset($param['path'])) {
			$param['user'] = \OCP\User::getUser();
			//Add Background Job:
			\OCP\BackgroundJob::addQueuedTask(
			'search_lucene',
			'OCA\FilesSharding\Hooks',
			'doIndexFile',
			json_encode($param) );
		} else {
			\OCP\Util::writeLog('search_lucene',
			'missing path parameter',
			\OCP\Util::WARN);
		}
	}
	static public function doIndexFile($param) {
		$data = json_decode($param);
		if ( ! isset($data->path) ) {
			\OCP\Util::writeLog('search_lucene',
			'missing path parameter',
			\OCP\Util::WARN);
			return false;
		}
		if ( ! isset($data->user) ) {
			\OCP\Util::writeLog('search_lucene',
			'missing user parameter',
			\OCP\Util::WARN);
			return false;
		}
		\OCP\Util::writeLog('files_sharding', 'USER: '.$data->user, \OC_Log::WARN);
		\OC\Files\Filesystem::initMountPoints($data->user);
		\OCA\Search_Lucene\Indexer::indexFile($data->path, $data->user);
	}
	
	public static function renameFile(array $param) {
		if (isset($param['newpath'])) {
			self::indexFile(array('path'=>$param['newpath']));
		}
		if (isset($param['oldpath'])) {
			\OCA\Search_Lucene\Hooks::deleteFile(array('path'=>$param['oldpath']));
		}
	}
	/////
}
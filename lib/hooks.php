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
		
		\OC_Log::write('files_sharding',"Checking session ".$_COOKIE[$params['sessionName']].": ".serialize($params['session']), \OC_Log::WARN);

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
	
	public static function setup($options) {
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
	
	public static function renameHook($params){
		\OCP\Util::writeLog('files_sharing','RENAME '.serialize($params), \OCP\Util::WARN);
		if(!\OCP\App::isEnabled('files_sharding')){
			return true;
		}
		$user_id = \OCP\User::getUser();
		$id = \OCA\FilesSharding\Lib::getFileId($params['newpath']);
		$matchlen = \OCA\FilesSharding\Lib::stripleft($params['oldpath'], $params['newpath']);
		$oldname = substr($params['oldpath'], $matchlen);
		$newname = substr($params['newpath'], $matchlen);
		
		if(\OCA\FilesSharding\Lib::isMaster()){
			$res = \OCA\FilesSharding\Lib::renameShareFileTarget($user_id, $id, $oldname, $newname);
		}
		else{
			$res = \OCA\FilesSharding\Lib::ws('rename_share_file_target',
					array('owner' => $user_id, 'id' => $id, 'oldname' => $oldname, 'newname' => $newname));
		}
		return $res;
	}
	
	public static function deleteHook($params){
		\OCP\Util::writeLog('files_sharing','DELETE '.serialize($params), \OCP\Util::WARN);
		if(!\OCP\App::isEnabled('files_sharding')){
			return true;
		}
		$user_id = \OCP\User::getUser();
		$id = \OCA\FilesSharding\Lib::getFileId($params['uid']);
		$path = substr($params['path'], $matchlen);
		
		if(\OCA\FilesSharding\Lib::isMaster()){
			$res = \OCA\FilesSharding\Lib::deleteShareFileTarget($user_id, $id, $path);
		}
		else{
			$res = \OCA\FilesSharding\Lib::ws('delete_share_file_target',
					array('owner' => $user_id, 'id' => $id, 'path' => $path));
		}
		return $res;
	}
	
}
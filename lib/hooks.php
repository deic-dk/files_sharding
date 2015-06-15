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
		$user_id = \OCP\User::getUser();
		if(!isset($user_id) || !$user_id){
			\OCP\Util::writeLog('files_sharing','ERROR: not logged in.', \OCP\Util::WARN);
			return false;
		}
		
		$id = \OCA\FilesSharding\Lib::getFileId($params['newpath']);
		
		$matchlen = \OCA\FilesSharding\Lib::stripleft($params['oldpath'], $params['newpath']);
		$oldname = substr($params['oldpath'], $matchlen);
		$newname = substr($params['newpath'], $matchlen);
		
		$old_file_target = \OCA\FilesSharding\Lib::getShareFileTarget($id);
		
		$new_file_target = preg_replace('|(.*)'.$oldname.'$|', '$1'.$newname, $old_file_target);
				
		\OC_Log::write('OCP\Share', 'QUERY: '.$oldname.':'.$newname.':'.$new_file_target, \OC_Log::WARN);
		
		$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `file_target` = ? WHERE `uid_owner` = ? AND `item_source` = ? AND `file_target` = ?');
		$result = $query->execute(array($new_file_target, $user_id, $id, $old_file_target));
		
		if($result === false) {
			\OC_Log::write('OCP\Share', 'Couldn\'t update share table for '.$user_id.' --> '.serialize($params), \OC_Log::ERROR);
		}
	}
	
}
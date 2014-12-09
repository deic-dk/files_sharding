<?php

namespace OCA\FilesSharding;

require_once __DIR__ . '/../../../lib/base.php';

class Hooks {

	private static $handler;
	private static $LOGOUT_URL = "https://MASTER_FQ/index.php?logout=true";
	
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
			// This actually precents a proper saml log out
			//self::$handler->putSession($_COOKIE[$instanceId], "");
			//self::$handler->destroy($_COOKIE[$instanceId]);
			// Redirect back to master to saml log out
			header('Location: ' . self::$LOGOUT_URL);
			exit();
		}
	}
	
	private static function initHandler(){
		if(!isset(self::$handler)){
			self::$handler = new FileSessionHandler('/tmp');
		}
	}
	
}
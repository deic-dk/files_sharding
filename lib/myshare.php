<?php

namespace OC\Share;

class MyShare extends \OC\Share\Share {
	
	/*public static function __callstatic($method, $args) {
		\OCP\Util::writeLog('Share', 'CALLING '.$method.'-->'.implode($args).'-->'.implode(parent::$backendTypes), \OC_Log::WARN);
		return call_user_func_array(array('\OC\Share\Share', $method), $args);
	}
	
	public static function __callstatic($method, $args) {
		\OCP\Util::writeLog('Share', 'CALLING '.$method.'-->'.implode($args).'-->'.implode(parent::$backendTypes), \OC_Log::WARN);
		return call_user_func_array(array('\OC\Share\Share', $method), $args);
	}*/
	
	public static function myRegisterBackend($itemType, $class, $collectionOf = null, $supportedFileExtensions = null){
		unset(self::$backendTypes[$itemType]);
		self::registerBackend($itemType, $class, $collectionOf, $supportedFileExtensions);
		/*self::$backendTypes[$itemType] = array(
			'class' => $class,
			'collectionOf' => $collectionOf,
			'supportedFileExtensions' => $supportedFileExtensions
		);*/
		/*if(count(self::$backendTypes) === 1) {
			\OC_Util::addScript('core', 'share');
			\OC_Util::addStyle('core', 'share');
		}*/
		return true;
	}
	
	/*public static function registerBackend($itemType, $class, $collectionOf = null, $supportedFileExtensions = null){
		\OCP\Util::writeLog('search', 'Backends now: '.serialize(self::$backendTypes), \OC_Log::WARN);
		unset(parent::$backendTypes[$itemType]);
		return parent::registerBackend($itemType, $class, $collectionOf, $supportedFileExtensions);
	}*/
	
}

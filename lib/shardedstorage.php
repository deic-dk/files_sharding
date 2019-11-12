<?php 

namespace OC\Files\Storage;

use OC\Files\Storage\Local;

class Sharded extends \OC\Files\Storage\Local {

	private $userid;

	public function __construct($arguments) {
		$this->userid = $arguments['userid'];
		$configDataDirectory = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT."/data");
		$datadir = rtrim($configDataDirectory, '/').'/'.$this->userid;
	}

	public function getUserId() {
		return $this->userid;
	}
	
	public function getId() {
		return 'home::' . $this->getUserId();
	}
	
}
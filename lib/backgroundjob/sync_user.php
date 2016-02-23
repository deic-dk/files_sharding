<?php

namespace OCA\FilesSharding\BackgroundJob;

class SyncUser extends \OC\BackgroundJob\TimedJob {
	
	public function __construct() {
		// Run all 15 Minutes
		$this->setInterval(15 * 60);
	}
	
	protected function run($argument) {
		if(!\OC::$CLI){
			// With web cron, don't sync.
			\OCP\Util::writeLog('files_sharding', 'ERROR: Will not sync with web cron.', \OC_Log::ERROR);
		}
		$userArr = \OCA\FilesSharding\Lib::getNextSyncUser();
		$user = $userArr['user_id'];
		$priority = $userArr['priority'];
		\OCP\Util::writeLog('files_sharding', 'Syncing user '.$user, \OC_Log::WARN);
		if(!empty($user)){
			\OCA\FilesSharding\Lib::syncUser($user, $priority);
		}
	}
}

class DeleteUser extends \OC\BackgroundJob\TimedJob {

	public function __construct() {
		// Run all 30 Minutes
		$this->setInterval(60 * 60);
	}

	protected function run($argument) {
		if(!\OC::$CLI){
			// With web cron, don't sync.
			\OCP\Util::writeLog('files_sharding', 'ERROR: Will not delete with web cron.', \OC_Log::ERROR);
		}
		$user = \OCA\FilesSharding\Lib::getNextDeleteUser();
		if(!empty($user)){
			\OCA\FilesSharding\Lib::deleteUser($user);
		}
	}
	
}
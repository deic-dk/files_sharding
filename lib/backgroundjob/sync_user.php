<?php

namespace OCA\FilesSharding\BackgroundJob;

class SyncUser extends \OC\BackgroundJob\TimedJob {
	
	private static $MAX_SYNC_ATTEMPTS = 3;
	
	public function __construct() {
		// Run all 15 Minutes
		$this->setInterval(15 * 60);
	}
	
	protected function run($argument) {
		if(!\OC::$CLI){
			// With web cron, don't sync.
			\OCP\Util::writeLog('files_sharding', 'ERROR: Will not sync with web cron.', \OC_Log::ERROR);
		}
		$user = \OCA\FilesSharding\Lib::getNextSyncUser();
		if(!empty($user)){
			syncUser($user);
		}
	}
	
	protected function syncUser($user) {
		$i = 0;
		do {
			if($i>self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Syncing not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			// TODO: 
			++$i;
		}
		while ($syncedFiles>0);
	}
}
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
			self::syncUser($user);
		}
	}
	
	protected function syncUser($user) {
		$i = 0;
		do{
			if($i>self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Syncing not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			$syncedFiles = shell_exec(__DIR__."/../sync_user.sh -u \"".$user."\" -s ".$server." | grep 'Synced files:' | awk -F ':' '{print $NF}'");
			\OCP\Util::writeLog('files_sharding', 'Synced '.$syncedFiles.' files for '.$user.' from '.$server, \OC_Log::ERROR);
			++$i;
			// Get list of shared file mappings: ID -> path and update item_source on oc_share table on master with new IDs
			\OCA\FilesSharding\Lib::updateUserSharedFiles($user);
			// Get exported metadata (by path) via remote metadata web API and insert metadata on synced files by using local metadata web API
			\OCA\meta_data\Tags::updateFileTags($user, $url);
		}
		while(!is_numeric($syncedFiles) || is_numeric($syncedFiles) && $syncedFiles!=0);
	}
}

class DeleteUser extends \OC\BackgroundJob\TimedJob {

	private static $MAX_SYNC_ATTEMPTS = 3;

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
			self::deleteUser($user);
		}
	}

	protected function deleteUser($user) {
		$i = 0;
		do{
			if($i>self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Deletion not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			$remainingFiles = shell_exec(__DIR__."/../delete_user.sh -u \"".$user." | grep 'Remaining files:' | awk -F ':' '{print $NF}'");
			++$i;
		}
		while(!is_numeric($remainingFiles) || is_numeric($remainingFiles) && $remainingFiles!=0);
		\OCA\FilesSharding\Lib::setServerForUser($user, null, \OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_DISABLED);
	}
}
<?php

namespace OCA\FilesSharding\BackgroundJob;

require_once('user_notification/lib/data.php');
require_once('activity/lib/data.php');

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
			$server = \OCA\FilesSharding\Lib::syncUser($user, $priority);
			$thisServerId = \OCA\FilesSharding\Lib::lookupServerId();
			// Notify user
			if(\OCP\App::isEnabled('user_notification')){
				$primary_server_url = \OCA\FilesSharding\Lib::getServerForUser($user);
				if($priority==self::$USER_SERVER_PRIORITY_PRIMARY){
					\OCA\UserNotification\Data::send('files_sharding', 'Your files have been backed up.', array(),
							'sync_finished',
							array($server, $thisServerId), '', '', $user, \OCA\FilesSharding\Lib::TYPE_SERVER_SYNC,
							\OCA\UserNotification\Data::PRIORITY_HIGH, $user);
				}
				else{
					\OCA\UserNotification\Data::send('files_sharding', 'Your files have been migrated.', array(),
							'migration_finished',
							array($server, $thisServerId), '', '', $user, \OCA\FilesSharding\Lib::TYPE_SERVER_SYNC,
							\OCA\UserNotification\Data::PRIORITY_HIGH, $user);
				}
			}
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
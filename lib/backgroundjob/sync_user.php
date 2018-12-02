<?php

namespace OCA\FilesSharding\BackgroundJob;


if(\OCP\App::isEnabled('user_notification')){
	require_once('user_notification/lib/data.php');
}
if(\OCP\App::isEnabled('activity')){
	require_once('activity/lib/data.php');
}

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
		\OCP\Util::writeLog('files_sharding', 'Got sync user '.serialize($userArr), \OC_Log::WARN);
		if(!empty($userArr['user_id'])){
			$user = $userArr['user_id'];
			$priority = $userArr['priority'];
			$numericStorageId = $userArr['numeric_storage_id'];
			if(!\OC_User::userExists($user)){
				\OCP\Util::writeLog('files_sharding', 'Creating user '.$user, \OC_Log::WARN);
				$password = \OC_Util::generateRandomBytes(20);
				\OC_User::createUser($user, $password);
			}
			// Update the password and storage ids of the user (in case they have changed)
			$serverURL = \OCA\FilesSharding\Lib::getServerForUser($user, true);
			$pwHash = \OCA\FilesSharding\Lib::getPasswordHash($user, $serverURL);
			$pwOk = \OCA\FilesSharding\Lib::setPasswordHash($user, $pwHash);
			$storageOk = \OCA\FilesSharding\Lib::setNumericStorageId($user, $numericStorageId);
			\OCP\Util::writeLog('files_sharding', 'Syncing user '.$user.':'.$priority, \OC_Log::WARN);
			$server = \OCA\FilesSharding\Lib::syncUser($user, $priority);
			// Notify user
			if(!empty($server) && \OCP\App::isEnabled('user_notification')){
				$thisServerId = \OCA\FilesSharding\Lib::lookupServerId();
				$l = \OC_L10N::get('files_sharding');
				if($priority==\OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_PRIMARY){
					\OCA\UserNotification\Data::send('files_sharding', $l->t('Your files have been migrated.'), array(),
							'migration_finished',
							array($server, $thisServerId), '', '', $user, \OCA\FilesSharding\Lib::TYPE_SERVER_SYNC,
							\OCA\UserNotification\Data::PRIORITY_HIGH, $user);
				}
				else{
					\OCA\UserNotification\Data::send('files_sharding', $l->t('Your files have been backed up'), array(),
							'sync_finished',
							array($server, $thisServerId), '', '', $user, \OCA\FilesSharding\Lib::TYPE_SERVER_SYNC,
							\OCA\UserNotification\Data::PRIORITY_MEDIUM, $user);
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
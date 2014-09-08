<?php

class OC_Sharder {

	public static function getShareOwner($token) {
		$query = \OC_DB::prepare('SELECT `uid_owner` FROM `*PREFIX*share` WHERE `token` = ?');
		$result = $query->execute(Array($token));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('sharing', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('sharing', 'ERROR: Duplicate entries found for token:item_source '.$token.' : '.$itemSource, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['uid_owner']);
		}
		\OCP\Util::writeLog('sharing', 'ERROR: share not found: '.$token, \OC_Log::ERROR);
		return null;
	}
	
	public static function getServerForFolder(){
		// Call head-node
		
	}
	
	/**
	 * Lookup home server for user in database.
	 * @param unknown $user
	 */
	public static function dbLookupServerForUser($user){
		$query = \OC_DB::prepare('SELECT `server` FROM `*PREFIX*files_sharding_folders` WHERE `uid` = ?');
		$result = $query->execute(Array($token));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('sharing', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('sharing', 'ERROR: Duplicate entries found for token:item_source '.$token.' : '.$itemSource, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['uid_owner']);
		}
		\OCP\Util::writeLog('sharing', 'ERROR: share not found: '.$token, \OC_Log::ERROR);
		return null;
	}

	/**
	 * Lookup home server for user via web service.
	 * @param unknown $user
	 */
	public static function wsLookupServerForUser($user){
		
	}

}

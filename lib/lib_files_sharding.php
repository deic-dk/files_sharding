<?php

class OC_Sharder {
	
	/* Trusted network. It is presumed that firewall rules have been set up such that
	   these addresses are blocked on non-secure interfaces. */
	// TODO: make these configurable settings
	const TRUSTED_NET = 'TRUSTED_NET';
	/* The master server in the trusted network */
	const MASTER_INTERNAL_URL = "https://MASTER_INTERNAL_IP/";
	
	public static function getShareOwner($token) {
		$query = \OC_DB::prepare('SELECT `uid_owner` FROM `*PREFIX*share` WHERE `token` = ?');
		$result = $query->execute(Array($token));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for token: '.$token, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['uid_owner']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: share not found: '.$token, \OC_Log::ERROR);
		return null;
	}
	
	/**
	 * Get the name of a server.
	 * @param $id
	 */
	private static function dbLookupServerName($id){
		$query = \OC_DB::prepare('SELECT `name` FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$id, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['name']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: ID not found: '.$id, \OC_Log::ERROR);
		return null;
	}

	/**
	 * Get the id of a server.
	 * @param $name
	 */
	private static function dbLookupServerId($name){
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `name` = ?');
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$name, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['id']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: server not found: '.$name, \OC_Log::ERROR);
		return null;
	}

	/**
	 * Get the priority of a server (small number = high priority).
	 * @param $name
	 */
	private static function dbLookupUserServerPriority($user, $serverId){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_folder_servers` WHERE `user` = ? AND `server_id` = ?');
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$user.' : '.$serverId, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['priority']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: server not found: '.$name, \OC_Log::ERROR);
		return null;
	}
	
	/**
	 * Get the priority of a server (small number = high priority).
	 * @param $name
	 */
	private static function dbLookupFolderServerPriority($folderId, $serverId){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder_id` = ? AND `server_id` = ?');
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$folderId.' : '.$serverId, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['priority']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: server not found: '.$name, \OC_Log::ERROR);
		return null;
	}
	
	/**
	 * Lookup home server for user in database.
	 * @param $user
	 */
	public static function dbLookupNextServerForUser($user){
		// Who's asking?
		$currentServerId = -1;
		if(array_key_exists('REMOTE_ADDR', $_SERVER)){
			$currentServerId = dbLookupServerId($_SERVER['REMOTE_ADDR']);
			$currentServerPriority = dbLookupUserServerPriority($user, $currentServerId);
		}
		$query = \OC_DB::prepare('SELECT `priority, server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user` = ? ORDER BY `priority`');
		$result = $query->execute(Array($user));
		$results = $result->fetchAll();
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		foreach($results as $row){
			if($row['priority']>$currentServerPriority){
				return dbLookupServerName($row['priority']);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found for '.$user, \OC_Log::ERROR);
		return empty($results)?null:$results[0];
	}

	/**
	 * Lookup home server for user via web service.
	 * @param unknown $user
	 */
	private static function wsLookupNextServerForUser($user){
		$url = OC_Sharder::MASTER_INTERNAL_URL."apps/files_sharding/ws/get_user_server.php";
		$data = array(
			'json' => '{"user":"'.$user.'"}',
		);
		
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found for '.$user, \OC_Log::ERROR);
		if($status>=300){
			return null;
		}
		
		$response = json_decode($json_response, false);
		return $response->host;
	}

	/**
	 * Lookup home server for user.
	 * @param unknown $user
	 */
	public static function getNextServerForUser($user){
		// If I'm the head-node, look up in DB
		if($_SERVER['REMOTE_ADDR']===$_SERVER['SERVER_ADDR']){
			$server = self::dbLookupNextServerForUser($user);
		}
		// Otherwise, ask head-node
		else{
			$server = self::wsLookupNextServerForUser($user);
		}
	}
	
	/**
	 * Lookup home server for folder in database.
	 * @param $folderId
	 */
	public static function dbLookupNextServerForFolder($folderId){
		// Who's asking?
		$currentServerId = -1;
		if(array_key_exists('REMOTE_ADDR', $_SERVER)){
			$currentServerId = dbLookupServerId($_SERVER['REMOTE_ADDR']);
			$currentServerPriority = dbLookupFolderServerPriority($folderId, $currentServerId);
		}
		$query = \OC_DB::prepare('SELECT `priority, server_id` FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder_id` = ? ORDER BY `id`');
		$result = $query->execute(Array($folder));
		$results = $result->fetchAll();
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		foreach($results as $row){
			if($row['priority']>$currentServerPriority){
				return dbLookupServerName($row['priority']);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found for '.$user, \OC_Log::ERROR);
		return empty($results)?null:$results[0];
	}

	/**
	 * Lookup home server for user via web service.
	 * @param int $folderId
	 */
	private static function wsLookupNextServerForFolder($folderId){
		$url = OC_Sharder::MASTER_INTERNAL_URL."apps/files_sharding/ws/get_folder_server.php";
		$data = array(
			'json' => '{"folder_id":"'.$folderId.'"}',
		);
		
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found for folder '.$folderId, \OC_Log::ERROR);
		if($status>=300){
			return null;
		}
		
		$response = json_decode($json_response, false);
		return $response->host;
	}

	/**
	 * Lookup home server for folder.
	 * @param string $folder
	 */
	public static function getNextServerForFolder($folder){
		// TODO: use ownCloud method for this
		$folderId = getId($folder);
			// If I'm the head-node, look up in DB
		if($_SERVER['REMOTE_ADDR']===$_SERVER['SERVER_ADDR']){
			$server = self::dbLookupNextServerForFolder($folderId);
		}
		// Otherwise, ask head-node
		else{
			$server = self::wsLookupNextServerForFolder($folderId);
		}
	}
	
	/**
	 * Check that the requesting IP address is allowed to get confidential
	 * information.
	 */
	public static function checkIP(){
		OC_Log::write('files_sharding', 'Client IP '.$_SERVER['REMOTE_ADDR'], OC_Log::INFO);
		if(strpos($_SERVER['REMOTE_ADDR'], OC_Sharder::TRUSTED_NET)===0){
			return true;
		}
		return false;
	}
	
}

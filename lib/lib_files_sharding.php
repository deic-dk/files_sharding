<?php

namespace OCA\FilesSharding;

class Lib {

	private static $masterinternalurl = '';
	private static $masterfq = '';
	private static $masterurl = '';
	private static $cookiedomain = '';
	private static $trustednet = '';
	
	public static function getCookieDomain(){
		if(self::$cookiedomain===''){
			self::$cookiedomain = \OCP\Config::getSystemValue('cookiedomain', '');
			self::$cookiedomain = (substr(self::$cookiedomain, -7)==='_DOMAIN'?null:self::$cookiedomain);
		}
		return self::$cookiedomain;
	}
	
	public static function isMaster(){
		if(self::$masterfq===''){
			self::$masterfq = \OCP\Config::getSystemValue('masterfq', '');
			self::$masterfq = (substr(self::$masterfq, 0, 7)==='MASTER_'?null:self::$masterfq);
		}
		return (empty(self::$masterfq) ||
				isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===self::$masterfq) ||
				isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===self::$masterfq;
	}
	
	public static function getMasterURL(){
		if(self::$masterurl===''){
			self::$masterurl = \OCP\Config::getSystemValue('masterurl', '');
			self::$masterurl = (substr(self::$masterurl, 0, 7)==='MASTER_'?'/':self::$masterurl);
		}
		return self::$masterurl;
	}
	
	public static function getMasterHostName(){
		if(self::$masterfq===''){
			self::$masterfq = \OCP\Config::getSystemValue('masterfq', '');
			self::$masterfq = (substr(self::$masterfq, 0, 7)==='MASTER_'?'/':self::$masterfq);
		}
		return self::$masterfq;
	}
	
	public static function getMasterInternalURL(){
		if(self::$masterinternalurl===''){
			self::$masterinternalurl = \OCP\Config::getSystemValue('masterinternalurl', '');
			self::$masterinternalurl = (substr(self::$masterinternalurl, 0, 7)==='MASTER_'?'/':self::$masterinternalurl);
		}
		return self::$masterinternalurl;
	}

	public static function getAllowLocalLogin($node){
		if(self::isMaster()){
			return self::dbGetAllowLocalLogin($node)==='yes';
		}
		else{
			return self::wsGetAllowLocalLogin($node)==='yes';
		}
	}
	
	public static function dbGetAllowLocalLogin($node){
		$query = \OC_DB::prepare('SELECT `allow_local_login` FROM `*PREFIX*files_sharding_servers` WHERE `url` LIKE ?');
		$result = $query->execute(Array("http%://$node%"));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$id, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return $row['allow_local_login'];
		}
	}

	private static function dbSetAllowLocalLogin($id, $value){
		$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_servers` set `allow_local_login` = ? WHERE `id` = ?');
		$result = $query->execute( array($value, $id));
		return $result ? true : false;
	}
	
	private static function wsGetAllowLocalLogin($node){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/get_allow_local_login.php";
		$content = "";
		$data = array(
			'node' => $node
		);
		
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		//\OCP\Util::writeLog('files_sharding', 'response from '.$url.': '.$status.':'.$json_response, \OC_Log::WARN);
		curl_close($curl);
		
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no answer via ws for '.$node.' : '.$json_response, \OC_Log::ERROR);
			return null;
		}
		
		$response = json_decode($json_response, false);
		
		if(strpos($response->status, 'error')!==false){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no answer via ws for '.$node.' : '.$response->status, \OC_Log::ERROR);
			return null;
		}
		
		return $response->allow_local_login;
	}

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
	
	public static function dbGetServersList(){
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_servers`');
		$result = $query->execute(Array());
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}
	
	public static function dbGetSitesList(){
		$query = \OC_DB::prepare('SELECT DISTINCT `site` FROM `*PREFIX*files_sharding_servers`');
		$result = $query->execute(Array());
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}	
	/**
	 * Get the URL of a server.
	 * @param $id
	 */
	public static function dbLookupServerURL($id){
		$query = \OC_DB::prepare('SELECT `url` FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$id, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['url']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: ID not found: '.$id, \OC_Log::ERROR);
		return null;
	}

	/**
	 * Get the ID of a server.
	 * @param $hostname hostname of the server
	 */
	public static function dbLookupServerId($hostname){
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `url` LIKE ?');
		$result = $query->execute(Array("http%://$hostname%"));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$hostname, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['id']);
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING, dbLookupServerId: server not found: '.$hostname, \OC_Log::ERROR);
		return null;
	}
	
	public static function dbAddServer($url, $site, $allow_local_login){
		
		$id = md5(uniqid(rand(), true));
		
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>0){
			$error = 'ERROR: Duplicate entries found for server '.$id.' : '.$server_id;
			\OCP\Util::writeLog('files_sharding', $error, \OCP\Util::ERROR);
			throw new Exception($error);
		}
		
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*files_sharding_servers` (`id`, `url`, `site`, `allow_local_login`) VALUES (?, ?, ?, ?)');
		$result = $query->execute( array($id, $url, $site, $allow_local_login));
		return $result ? true : false;
	}
	
	public static function dbDeleteServer($id){
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		return $result ? true : false;
	}
	
	/**
	 * Get the priority of a server (small number = high priority).
	 * @param $name
	 */
	private static function dbLookupUserServerPriority($user, $serverId){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_folder_servers` WHERE `user_id` = ? AND `server_id` = ?');
		$result = $query->execute(Array($user, $serverId));
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
		\OCP\Util::writeLog('files_sharding', 'ERROR, dbLookupUserServerPriority: server not found: '.$serverId, \OC_Log::ERROR);
		return -1;
	}
	
	/**
	 * Get the priority of a server (small number = high priority).
	 * @param $name
	 */
	private static function dbLookupFolderServerPriority($folderId, $serverId){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder_id` = ? AND `server_id` = ?');
		$result = $query->execute(Array($folderId, $serverId));
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
		\OCP\Util::writeLog('files_sharding', 'ERROR, dbLookupFolderServerPriority: server not found for folder: '.$folderId.' : '.$serverId, \OC_Log::ERROR);
		return -1;
	}
	
	/**
	 * Choose server for user, given a chosen site.
	 * @param $user_id
	 * @param $site
	 * @param $priority
	 * @param $exclude_server_id can be null
	 * @return Server ID
	 */
	public static function dbChooseServerForUser($user_id, $site, $priority, $exclude_server_id){
		// TODO: placing algorithm that takes, quota, available space and even distribution of users into consideration
		// For now, just take the first server found of the given site.
		$current_server_id = self::dbLookupServerIdForUser($user_id, $priority);
		$old_server_id = self::dbLookupOldServerIdForUser($user_id, $site);
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `site` = ?');
		$result = $query->execute(Array($site));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		\OCP\Util::writeLog('files_sharding', 'Sites: '.count($results), \OC_Log::WARN);
		
		if(isset($exclude_server_id)){
			$i = 0;
			$del = false;
			foreach($results as $row){
				if(!empty($exclude_server_id) && $row['id']===$exclude_server_id){
					$del = true;
					break;
				}
				++$i;
			}
			if($del){
				array_splice($results, $i, 1);
				array_values($results);
			}
		}
		
		// First see if the user is just playing around and returning to the same site
		foreach($results as $row){
			if(!empty($current_server_id) && $row['id']===$current_server_id){
				return($row['id']);
			}
		}
		// Give priority to a server used before
		foreach($results as $row){
			if(!empty($current_server_id) && $row['id']===$old_server_id){
				return($row['id']);
			}
		}

		$num_rows = count($results);
		\OCP\Util::writeLog('files_sharding', 'Sites: '.$num_rows, \OC_Log::WARN);
		if($num_rows>0){
			$random_int = rand(0, $num_rows-1);
			\OCP\Util::writeLog('files_sharding', 'Choosing random site '.$random_int.' out of '.$num_rows, \OC_Log::WARN);
			return $results[$random_int]['id'];
		}
		
		// Always return something as home server
		/*$default_server_id = Lib::dbLookupServerId(self::$masterfq);
		if($priority==0 && (!isset($exclude_server_id) || $default_server_id!==$exclude_server_id)){
			\OCP\Util::writeLog('files_sharding', 'WARNING, dbChooseServerForUser: site not found: '.$site.'. Using default', \OC_Log::INFO);
			return $default_server_id;
		}*/
		
		\OCP\Util::writeLog('files_sharding', 'WARNING, dbChooseServerForUser: site not found: '.$site.'. Using null', \OC_Log::INFO);
		return null;
	}
	
	/**
	 * Set primary or backup server for a user.
	 * @param $user_id user ID
	 * @param $server_id server ID
	 * @param $priority server priority: -1: disabled, 0: primary/home (r/w), 1: backup (r/o), >1: unused
	 * @throws Exception
	 * @return boolean true on success, false on failure
	 */
	public static function dbSetServerForUser($user_id, $server_id, $priority){
		// If we're setting a home server, set current home server as backup server
		if($priority===0){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = 1 WHERE `user_id` = ? AND `priority` = 0');
			$result = $query->execute( array($user_id));
		}
		// If we're setting a backup server, disable current backup server
		if($priority===1){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = -1 WHERE `user_id` = ? AND `priority` = 1');
			$result = $query->execute( array($user_id));
			// Backup server cleared, nothing more to do
			if(empty($server_id)){
				return $result ? true : false;
			}
		}
		
		$query = \OC_DB::prepare('SELECT `user_id`, `server_id`, `priority` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `server_id` = ?');
		$result = $query->execute(Array($user_id, $server_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		
		\OCP\Util::writeLog('files_sharding', 'Number of servers for '.$user_id.":".$server_id.":".count($results), \OCP\Util::ERROR);
		if(count($results)===0){
			$query = \OC_DB::prepare('INSERT INTO `*PREFIX*files_sharding_user_servers` (`user_id`, `server_id`, `priority`) VALUES (?, ?, ?)');
			$result = $query->execute( array($user_id, $server_id, $priority));
			return $result ? true : false;
		}
		foreach($results as $row){
			if($row['priority']===$priority){
				return true;
			}
		}
		$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ? WHERE `user_id` = ? AND `server_id` = ?');
		$result = $query->execute( array($priority, $user_id, $server_id));
		return $result ? true : false;
	}

	
	/**
	 * Lookup home server for user in database.
	 * @param $user
	 * @return URL of the server - null if none has been set. Important as user_saml relies on this.
	 */
	public static function dbLookupServerUrlForUser($user){
		$id = self::dbLookupServerIdForUser($user, 0);
		if(!empty($id)){
			return self::dbLookupServerURL($id);
		}
		\OCP\Util::writeLog('files_sharding', 'No server found via db for user '.$user. '. Using default', \OC_Log::DEBUG);
		return null;
	}
	
	/**
	 * @param $user
	 * @return ID of server
	 */
	public static function dbLookupServerIdForUser($user, $priority){
		// Priorities: -1: disabled, 0: home (r/w), >0: secondary (r/o)
		$query = \OC_DB::prepare('SELECT `server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `priority` = ? ORDER BY `priority`');
		$result = $query->execute(Array($user, $priority));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for user:priority '.$user.":".$priority, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return $row['server_id'];
		}
		\OCP\Util::writeLog('files_sharding', 'No server found via db for user:priority '.$user.":".$priority, \OC_Log::DEBUG);
		return null;
	}
	
	public static function dbLookupOldServerIdForUser($user, $site){
		$servers = self::dbGetServersList();
		$query = \OC_DB::prepare('SELECT `server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? ORDER BY `priority`');
		$result = $query->execute(Array($user));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for user:site '.$user.":".$site, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			foreach($servers as $server){
				if(isset($servers['site']) && $servers['site']===$site && $row['server_id']===$servers['id']){
					return $row['server_id'];
				}
			}
		}
		\OCP\Util::writeLog('files_sharding', 'No server found via db for user:site '.$user.":".$site, \OC_Log::DEBUG);
		return null;
	}
	
/**
 * 
 * @param $server_id
 * @return site name. If $server_id is null, returns the master site name. Important as it is used by user_saml
 */
	public static function dbGetSite($server_id){
		$master_server_id = Lib::dbLookupServerId(self::$masterfq);
		$servers = self::dbGetServersList();
		foreach($servers as $server){
			if($server['id']===$server_id){
				return $server['site'];
			}
			if($server['id']===$master_server_id){
				$master_site = $server['site'];
			}
		}
		\OCP\Util::writeLog('files_sharding', 'dbLookupHomeSiteForUser: site not found for server: '.$server_id.' Using master '.$master_site, \OC_Log::ERROR);
		return $master_site;
	}

	/**
	 * Lookup home server for user via web service.
	 * @param unknown $user
	 * @return URL of the server
	 */
	private static function wsLookupServerUrlForUser($user){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/get_user_server.php";
		$content = "";
		$data = array(
			'user' => $user
		);
		
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		//\OCP\Util::writeLog('files_sharding', 'response from '.$url.': '.$status.':'.$json_response, \OC_Log::WARN);
		curl_close($curl);
		
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found via ws for user '.$user.' : '.$json_response, \OC_Log::ERROR);
			return null;
		}
		
		$response = json_decode($json_response, false);
		
		if(strpos($response->status, 'error')!==false){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found via ws for user '.$user.' : '.$response->status, \OC_Log::ERROR);
			return null;
		}
		
		return $response->url;
	}

	/**
	 * Lookup server for user.
	 * @param unknown $user
	 * @return the base URL (https://...) of the server that will serve the files
	 */
	public static function getServerForUser($user){
		// If I'm the head-node, look up in DB
		if(self::isMaster()){
			$server = self::dbLookupServerUrlForUser($user);
		}
		// Otherwise, ask head-node
		else{
			$server = self::wsLookupServerUrlForUser($user);
		}
		return $server;
	}
	
	/**
	 * Lookup server for folder in database.
	 * @param $folderId
	 * @return URL (https://...)
	 */
	public static function dbLookupNextServerForFolder($folderId){
		// Who's asking?
		$currentServerId = -1;
		if(array_key_exists('REMOTE_ADDR', $_SERVER)){
			$currentServerId = self::dbLookupServerId($_SERVER['REMOTE_ADDR']);
			$currentServerPriority = self::dbLookupFolderServerPriority($folderId, $currentServerId);
		}
		$query = \OC_DB::prepare('SELECT `priority`, `server_id` FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder_id` = ? ORDER BY `priority`');
		$result = $query->execute(Array($folderId));
		$results = $result->fetchAll();
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		foreach($results as $row){
			if($row['priority']>$currentServerPriority){
				return dbLookupServerURL($row['server_id']);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found for folder '.$folderId, \OC_Log::ERROR);
		return empty($results)?null:$results[0];
	}

	/**
	 * Lookup server for user via web service.
	 * @param int $folderId
	 * @return URL (https://...)
	 */
	private static function wsLookupNextServerForFolder($folderId){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/get_folder_server.php";
		$data = array(
			"folder_id" => $folderId,
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
		if($status===0 || $status>=300 || empty($json_response)){
			return null;
		}
		
		$response = json_decode($json_response, false);
		
		if(strpos($response->status, 'error')!==false){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found via ws for folder '.$folderId.' : '.$response->status, \OC_Log::ERROR);
			return null;
		}
		
		return $response->url;
	}

	/**
	 * Lookup home server for folder.
	 * @param string $folder
	 * @return URL (https://...)
	 */
	public static function getNextServerForFolder($folder){
		// TODO: use ownCloud method for this
		\OCP\Util::writeLog('files_sharding', 'Folder: '.$folder, \OC_Log::WARN);
		$fileInfo = \OC\Files\Filesystem::getFileInfo('/fror@dtu.dk/files'.$folder);
		$folderId = $fileInfo->getId();
		\OCP\Util::writeLog('files_sharding', 'Folder ID: '.$folderId, \OC_Log::WARN);
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
		if(self::$trustednet===''){
			self::$trustednet = \OCP\Config::getSystemValue('trustednet', '');
			self::$trustednet = (substr(self::$trustednet, 0, 8)==='TRUSTED_'?null:self::$trustednet);
		}
		
		\OC_Log::write('files_sharding', 'Client IP '.$_SERVER['REMOTE_ADDR'], \OC_Log::INFO);
		if(strpos($_SERVER['REMOTE_ADDR'], Lib::$trustednet)===0){
			return true;
		}
		return false;
	}
	
}

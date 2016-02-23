<?php

namespace OCA\FilesSharding;

class Lib {

	private static $masterinternalurl = '';
	private static $masterfq = '';
	private static $masterurl = '';
	private static $cookiedomain = '';
	private static $trustednet = '';
	
	public static $USER_ACCESS_ALL = 0;
	public static $USER_ACCESS_READ_ONLY = 1;
	public static $USER_ACCESS_NONE = 2;
	
	public static $USER_SERVER_PRIORITY_DISABLE = -2;
	public static $USER_SERVER_PRIORITY_DISABLED = -1;
	public static $USER_SERVER_PRIORITY_PRIMARY = 0;
	public static $USER_SERVER_PRIORITY_BACKUP_1 = 1;
	public static $USER_SERVER_PRIORITY_BACKUP_2 = 2;
	
	public static $USER_SYNC_INTERVAL_SECONDS = 86400; // 24 hours
	private static $MAX_SYNC_ATTEMPTS = 3;
	
	public static function getCookieDomain(){
		if(self::$cookiedomain===''){
			self::$cookiedomain = \OCP\Config::getSystemValue('cookiedomain', '');
			self::$cookiedomain = (substr(self::$cookiedomain, -7)==='_DOMAIN'?null:self::$cookiedomain);
		}
		return self::$cookiedomain;
	}
	
	public static function onServerForUser($user_id=null){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$user_server = self::getServerForUser($user_id);
		if(!empty($user_server)){
			$parse = parse_url($user_server);
			$user_host = $parse['host'];
		}
		else{
			// If no server has been set for the user, he can logically only be on the master
			return self::isMaster();
		}
		return 
				isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===$user_host ||
				isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===$user_host;
	}
	
	public static function isMaster(){
		self::getMasterHostName();
		self::getMasterInternalURL();
		if(!empty(self::$masterinternalurl)){
			$parse = parse_url(self::$masterinternalurl);
			$masterinternalip =  $parse['host'];
		}
		return empty(self::$masterfq) && empty($masterinternalip) ||
				isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST']===self::$masterfq || $_SERVER['HTTP_HOST']===$masterinternalip) ||
				isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME']===self::$masterfq || $_SERVER['SERVER_NAME']===$masterinternalip);
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
			return self::dbGetAllowLocalLogin($node)!=='no';
		}
		else{
			$ret = self::ws('get_allow_local_login', Array('node' => $node), true, false);
			return $ret->allow_local_login!=='no';
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
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$node, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return $row['allow_local_login'];
		}
	}

	public static function dbSetAllowLocalLogin($id, $value){
		$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_servers` set `allow_local_login` = ? WHERE `id` = ?');
		$result = $query->execute( array($value, $id));
		return $result ? true : false;
	}
	
	/*
	 * Map of calls to be cached => seconds to live.
	 */
	private static $WS_CACHE_CALLS = array('getItemsSharedWith'=>10, 'get_data_folders'=>10,
			'get_user_server'=>10, 'getFileTags'=>10, 'share_fetch'=>10, 'getShareByToken'=>10,
			'share_fetch'=>10, 'searchTagsByIDs'=>10, 'searchTags'=>10, 'getItemsSharedWithUser'=>10,
			'get_server_id'=>10, 'get_servers'=>10, 'getTaggedFiles'=>10, 'get_user_server_access'=>20);
	
	public static function ws($script, $data, $post=false, $array=true, $baseUrl=null, $appName=null){
		$content = "";
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
		if($baseUrl==null){
			$baseUrl = self::getMasterInternalURL();
		}
		$url = $baseUrl . "/apps/".(empty($appName)?"files_sharding":$appName)."/ws/".$script.".php";		
		if(!$post){
			$url .= "?".$content;
			$cache_key = $url;
		}
		else{
			$cache_key = $url.$content;
		}
		
		if(isset(self::$WS_CACHE_CALLS[$script]) && apc_exists($cache_key)){
			$json_response = apc_fetch($cache_key);
			$response = json_decode($json_response, $array);
			\OCP\Util::writeLog('files_sharding', 'Returning cached response for '.$script.'-->'.$cache_key, \OC_Log::DEBUG);
			return $response;	
		}
		
		\OCP\Util::writeLog('files_sharding', 'URL: '.$url.', '.($post?'POST':'GET').': '.$content, \OC_Log::WARN);
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		if($post){
			curl_setopt($curl, CURLOPT_POST, $post);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		}
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_UNRESTRICTED_AUTH, TRUE);
			
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: bad ws response. '.$json_response, \OC_Log::ERROR);
			return null;
		}
		
		if(isset(self::$WS_CACHE_CALLS[$script])){
			\OCP\Util::writeLog('files_sharding', 'Caching response for '.$script.'-->'.$cache_key, \OC_Log::WARN);
			apc_store($cache_key, $json_response, self::$WS_CACHE_CALLS[$script]);
		}
		else{
			\OCP\Util::writeLog('files_sharding', 'NOT caching response for '.$script.'-->'.$cache_key, \OC_Log::WARN);
		}
		
		$response = json_decode($json_response, $array);
		return $response;
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
	
	public static function getServersList(){
		if(self::isMaster()){
			return self::dbGetServersList();
		}
		else{
			return self::ws('get_servers', Array(), true, true);
		}
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
	
	public static function dbGetUserFiles($user_id=null){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$storage = \OC\Files\Filesystem::getStorage($user_id.'/files/');
		$storageId = $storage->getId();
		$numericStorageId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
		\OCP\Util::writeLog('files_sharding', 'Storage ID for '.$user_id.': '.$storageId, \OC_Log::WARN);
		if(empty($numericStorageId) || $numericStorageId==-1){
			return null;
		}
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE storage = ?');
		$result = $query->execute(Array($numericStorageId));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}
	
	public static function dbGetUserFile($path, $user_id=null){
		$loggedin_user = \OCP\USER::getUser();
		if(isset($user_id)){
			if(isset($loggedin_user)){
				$old_user = self::switchUser($user_id);
			}
			else{
				\OC_User::setUserId($user_id);
				\OC_Util::setupFS($user_id);
			}
		}
		else{
			$user_id = getUser();
		}
		if(empty($user_id)){
			\OCP\Util::writeLog('files_sharding', 'No user', \OC_Log::ERROR);
			return null;
		}
		$storage = \OC\Files\Filesystem::getStorage($user_id.'/files/');
		$storageId = $storage->getId();
		$numericStorageId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
		\OCP\Util::writeLog('files_sharding', 'Storage ID for '.$user_id.': '.$storageId, \OC_Log::WARN);
		if(empty($numericStorageId) || $numericStorageId==-1){
			return null;
		}
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE storage = ? AND path = ?');
		$result = $query->execute(Array($numericStorageId, $path));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		\OCP\Util::writeLog('files_sharding','Found file: '.$numericStorageId. ' --> '.$path.' : '.serialize($results),
				\OC_Log::DEBUG);
		if(isset($old_user) && $old_user){
			self::restoreUser($old_user);
		}
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for user/path '.$user_id.'/'.$path, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row);
		}
		return array();
	}
	
	public static function updateShareItemSources($user_id, $map){
		foreach($map as $oldItemSource => $newItemSource){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*share` set `item_source` = ? WHERE item_source` = ? AND `uid_owner` = $user_id');
			$result = $result && $query->execute(Array($oldItemSource, $newItemSource, $user_id));
			if(\OCP\DB::isError($result)){
				\OCP\Util::writeLog('files_sharding', 'ERROR: failed to update share.item_source from '.
						$oldItemSource.'to'.$newItemSource.' : '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			}
		}
		return $result;
	}
	
	public static function addDataFolder($folder, $user_id){
		if(self::isMaster()){
			$user_server_id = self::dbLookupServerIdForUser($user_id, 0);
			if($user_server_id==null){
				$user_server_id = self::dbChooseServerForUser($user_id, $site, 0, null);
				self::dbSetServerForUser($user_id, $user_server_id, 0);
			}
			return self::dbAddDataFolder($folder, $user_server_id, $user_id);
		}
		else{
			return self::ws('add_data_folder', Array('user_id' => $user_id, 'folder' => $folder), true, true);
		}
	}
	
	public static function dbAddDataFolder($folder, $server_id, $user_id){
		$query = \OC_DB::prepare(
				'INSERT INTO `*PREFIX*files_sharding_folder_servers` (`folder`, `server_id`, `user_id`,  `priority`) VALUES (?, ?, ?, ?)');
		$result = $query->execute(Array($folder, $server_id, $user_id, 0));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			return false;
		}
		$_SESSION['oc_data_folders'] = self::dbGetDataFoldersList($user_id);
		return true;
	}

	public static function inDataFolder($path, $user_id=null){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$dataFolders = self::getDataFoldersList($user_id);
		$checkPath = trim($path, '/ ');
		$checkPath = '/'.$checkPath;
		$checkLen = strlen($checkPath);
		foreach($dataFolders as $p){
			$dataFolderPath = $p['folder'];
			$dataFolderLen = strlen($dataFolderPath);
			\OCP\Util::writeLog('files_sharding', 'Checking path: '.$user_id.'-->'.$checkPath.'-->'.$dataFolderPath, \OC_Log::DEBUG);
			if($checkPath===$dataFolderPath || substr($checkPath, 0, $dataFolderLen+1)===$dataFolderPath.'/'){
				\OCP\Util::writeLog('files_sharding', 'Excluding '.$dataFolderPath, \OC_Log::INFO);
				return true;
			}
		}
		return false;
	}

	public static function getDataFoldersList($user_id){
		if(self::isMaster()){
			// On the master, get the list from the database
			return self::dbGetDataFoldersList($user_id);
		}
		else{
			// On a slave, in the web interface, use session variable set by the master
			if(isset($_SESSION['oc_data_folders'])){
				return $_SESSION['oc_data_folders'];
			}
			else{
				// On a slave via webdav, ask the master
				return self::ws('get_data_folders', Array('user_id' => $user_id), true, true);
			}
		}
	}
	
	public static function dbGetDataFoldersList($user_id){
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_folder_servers` WHERE user_id = ?');
		$result = $query->execute(Array($user_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}

	public static function removeDataFolder($folder, $user_id){
		if(self::isMaster()){
			return self::dbRemoveDataFolder($folder, $user_id);
		}
		else{
			return self::ws('remove_data_folder', Array('user_id' => $user_id, 'folder' => $folder), true, true);
		}
	}
	
	private static function dbRemoveDataFolder($folder, $user_id){
		// If folder spans several servers, deny syncing
		$results = self::getServersForFolder($folder, $user_id);
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', "Error: cannot sync sharded folder ".$folder, \OC_Log::ERROR);
			return false;
		}
		
		$query = \OC_DB::prepare(
				'DELETE FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder` = ? AND `user_id` = ?');
		$result = $query->execute(Array($folder, $user_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			return false;
		}
		$_SESSION['oc_data_folders'] = self::dbGetDataFoldersList($user_id);
		return true;
	}
	
	/**
	 * From files_sharing.
	 * get file ID from a given path
	 * @param string $path
	 * @param string $user_id
	 * @return string fileID or null
	 */
	public static function getFileId($path, $user_id=null) {
		if(!isset($user_id)){
			$user_id = \OCP\User::getUser();
		}
		$view = new \OC\Files\View('/'.$user_id.'/files');
		$fileId = null;
		$fileInfo = $view->getFileInfo($path);
		if ($fileInfo) {
			$fileId = $fileInfo['fileid'];
		}
		return $fileId;
	}

	public static function getFilePath($id, $owner=null) {
		if(isset($owner) && $owner!=\OCP\USER::getUser()){
			$user_id = self::switchUser($owner);
		}
		$ret = \OC\Files\Filesystem::getpath($id);
		if(isset($user_id) && $user_id){
			self::restoreUser($user_id);
		}
		return $ret;
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
	 * Get the internal URL of a server.
	 * @param $id
	 */
	public static function dbLookupInternalServerURL($id){
		$query = \OC_DB::prepare('SELECT `internal_url` FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$id, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['internal_url']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: ID not found: '.$id, \OC_Log::ERROR);
		return null;
	}
	
	/**
	 * Get accessrights to a server (r/o for backup server, none when migrating).
	 * @param $id
	 */
	public static function getUserServerAccess($serverId=null, $userId=null){
		if(empty($serverId)){
			$serverId = self::lookupServerId($_SERVER['HTTP_HOST']);
		}
		if(empty($userId)){
			$userId = \OCP\USER::getUser();
		}
		if(self::isMaster()){
			return self::dbGetUserServerAccess($serverId, $userId);
		}
		else{
			$res = self::ws('get_user_server_access', Array('server_id' => $serverId, 'user_id' => $userId), false, true);
			return $res['access'];
		}
	}

	public static function dbGetUserServerAccess($serverId, $userId){
		$query = \OC_DB::prepare('SELECT `access` FROM `*PREFIX*files_sharding_user_servers` WHERE `server_id` = ? AND  `user_id` = ?');
		$result = $query->execute(Array($serverId, $userId));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Too many entries found for server '.$serverId.', user '.$userId, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['access']);
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING: server '.$serverId.', user '.$userId.' not found.', \OC_Log::DEBUG);
		return self::$USER_ACCESS_ALL;
	}
	
	private static function dbGetServerUsers($serverId){
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_user_servers` WHERE `server_id` = ?');
		$result = $query->execute(Array($serverId));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}

/**
	 * Get the ID of a server.
	 * @param $hostname hostname of the server
	 */
	public static function lookupServerId($hostname){
		if(self::isMaster()){
			return self::dbLookupServerId($hostname);
		}
		else{
			$res = self::ws('get_server_id', Array('hostname' => $hostname), false, true);
			return $res['id'];
		}
	}
	
	public static function dbLookupServerId($hostname){
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `url` LIKE ? OR `internal_url` LIKE ?');
		$result = $query->execute(Array("http%://$hostname%", "http%://$hostname%"));
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
	
	public static function dbAddServer($url, $site, $charge, $allow_local_login){
		
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
		
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*files_sharding_servers` (`id`, `url`, `site`, `allow_local_login`, `charge_per_gb`) VALUES (?, ?, ?, ?, ?)');
		$result = $query->execute( array($id, $url, $site, $allow_local_login, $charge));
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
	private static function dbLookupFolderServerPriority($folder, $user_id, $server_id){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder` = ? AND `user_id` = ? AND `server_id` = ?');
		$result = $query->execute(Array($folder, $user_id, $server_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server '.$folder.' : '.$server_id, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['priority']);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR, dbLookupFolderServerPriority: server not found for folder: '.$folder.' : '.$server_id, \OC_Log::ERROR);
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
		/*$default_server_id = self::dbLookupServerId(self::$masterfq);
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
	public static function dbSetServerForUser($user_id, $server_id, $priority, $access=null){
		if(empty($server_id)){
			$server = $_SERVER['REMOTE_ADDR'];
			$server_id = self::dbLookupServerId($server);
			if(empty($server_id)){
				$server = self::getMasterHostName();
				$server_id = self::dbLookupServerId($server);
			}
		}
		// If we're not changing anything, just return true
		if(self::dbLookupServerIdForUser($user_id, $priority)===$server_id){
			return true;
		}
		// If we're setting a home server, set current home server as backup server
		// NO - handled in javascript
		/*if($priority===0){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = 1 WHERE `user_id` = ? AND `priority` = 0');
			$result = $query->execute( array($user_id));
		}*/
		// If we're setting a new backup server, disable current backup server
		if($priority==1){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ?, `access` = ? WHERE `user_id` = ? AND `priority` >= ?');
			$result = $query->execute( array(self::$USER_SERVER_PRIORITY_DISABLE, self::$USER_ACCESS_NONE, $user_id, self::$USER_SERVER_PRIORITY_BACKUP_1));
			// Backup server cleared, nothing more to do
			if(empty($server_id)){
				return $result ? true : false;
			}
		}
		
		$query = \OC_DB::prepare('SELECT `user_id`, `server_id`, `priority`, `access` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `server_id` = ?');
		$result = $query->execute(Array($user_id, $server_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		
		\OCP\Util::writeLog('files_sharding', 'Number of servers for '.$user_id.":".$server_id.":".count($results), \OCP\Util::ERROR);
		if(count($results)===0){
			$newAccess = empty($access)?$access=self::$USER_ACCESS_READ_ONLY:$access;
			$query = \OC_DB::prepare('INSERT INTO `*PREFIX*files_sharding_user_servers` (`user_id`, `server_id`, `priority`, `access`) VALUES (?, ?, ?, ?)');
			$result = $query->execute( array($user_id, $server_id, $priority, $newAccess));
			return $result ? true : false;
		}
		else{
			foreach($results as $row){
				if($row['priority']==$priority && (empty($access) || $row['access']==$access)){
					return true;
				}
			}
			
			if(empty($access)){
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ? WHERE `user_id` = ? AND `server_id` = ?');
				$result = $query->execute(array($priority, $user_id, $server_id));
			}
			else{
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ?, `access` = ? WHERE `user_id` = ? AND `server_id` = ?');
				$result = $query->execute(array($priority, $access, $user_id, $server_id));
			}
			return $result ? true : false;
		}
	}
	
	public static function setServerForUser($user_id, $server_id, $priority, $access=null){
		if(self::isMaster()){
			$ret = self::dbSetServerForUser($user_id, $server_id, $priority, $access);
		}
		else{
			$ret = self::ws('set_server_for_user',
				array('user_id'=>$user_id, 'server_id'=>$server_id, 'priority'=>$priority, 'access'=>$access));
		}
		return $ret;
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
	 * Lookup internal URL of home server for user in database.
	 * @param $user
	 * @return URL of the server - null if none has been set. Important as user_saml relies on this.
	 */
	public static function dbLookupInternalServerUrlForUser($user){
		$id = self::dbLookupServerIdForUser($user, 0);
		if(!empty($id)){
			return self::dbLookupInternalServerURL($id);
		}
		\OCP\Util::writeLog('files_sharding', 'No server found via db for user '.$user. '. Using default', \OC_Log::DEBUG);
		return null;
	}
	
	/**
	 * @param $user
	 * @return ID of primary server
	 */
	public static function lookupServerIdForUser($user){
		if(self::isMaster()){
			$serverId = self::dbLookupServerIdForUser($user, 0);
		}
		// Otherwise, ask master
		else{
			$res = self::ws('get_user_server', Array('user_id' => $user), false, true);
			$serverId = $res['id'];
		}
		return $serverId;
	}
	
	/**
	 * @param $user
	 * @param $priority
	 * @param $lastSync If given, only return server if it has been synced since $lastSync
	 * @return ID of server
	 */
	public static function dbLookupServerIdForUser($user, $priority, $lastSync=-1){
		// Priorities: -1: disabled, 0: primary/home (r/w), 1: backup (r/o), >1: unused
		$sql = 'SELECT `server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ?';
		if($lastSync>0){
			$sql .= ' AND `last_sync` >= ?';
		}
		$sql .= ' AND `priority` = ? ORDER BY `priority`';
		$query = \OC_DB::prepare($sql);
		$result = $lastSync>0?$query->execute(Array($user, $priority, $lastSync)):
			$query->execute(Array($user, $priority));
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
	
	public static function dbLookupLastSync($server_id, $user_id){
		$sql = 'SELECT `last_sync` FROM `*PREFIX*files_sharding_user_servers` WHERE `server_id` = ? AND `user_id` = ?';
		$query = \OC_DB::prepare($sql);
		$result = $query->execute(Array($server_id, $user_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for server:user '.$server_id.":".$user_id, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return $row['last_sync'];
		}
	}
	
/**
 * 
 * @param $server_id
 * @return site name. If $server_id is null, returns the master site name. Important as it is used by user_saml
 */
	public static function dbGetSite($server_id){
		$master_server_id = self::dbLookupServerId(self::$masterfq);
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
	 * Lookup URL of server for user.
	 * @param unknown $user_id
	 * @param internal $internal whether to return the internal URL
	 * @return the base URL (https://...) of the server that will serve the files
	 */
	public static function getServerForUser($user_id, $internal = false){
		// If I'm the master, look up in DB
		if(self::isMaster()){
			if($internal){
				$server = self::dbLookupInternalServerUrlForUser($user_id);
			}
			else{
				$server = self::dbLookupServerUrlForUser($user_id);
			}
		}
		// Otherwise, ask master
		else{
			$response = self::ws('get_user_server', Array('user_id' => $user_id, 'internal' => $internal), false, false);
			$server = $response->url;
		}
		return $server;
	}
	
	private static function getServersForFolder($folder, $user_id=null){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$query = \OC_DB::prepare('SELECT `priority`, `server_id` FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder` = ? and `user_id` = ? ORDER BY `priority`');
		$result = $query->execute(Array($folder, $user_id));
		$results = $result->fetchAll();
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		return $results;
	}
	
	/**
	 * Lookup next server for folder in database.
	 * The premise is that the current server,
	 * i.e. the server issuing the request, is full.
	 * @param $folder
	 * @param $user_id
	 * @param $currentServerId slave issuing the request - can be null
	 * @return URL (https://...)
	 */
	public static function dbLookupNextServerForFolder($folder, $user_id, $currentServerId=null){
		$currentServerId = -1;
		$currentServerPriority = -1;
		if(!empty($currentServerId)){
			$currentServerPriority = self::dbLookupFolderServerPriority($folder, $user_id, $currentServerId);
		}
		$results = getServersForFolder($folder, $user_id);
		foreach($results as $row){
			if($row['priority']>$currentServerPriority){
				return dbLookupServerURL($row['server_id']);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING: no server registered for folder '.$folder, \OC_Log::WARN);
		return empty($results)?null:$results[0];
	}

	/**
	 * Lookup home server for folder.
	 * @param string $folder path
	 * @param string $user_id
	 * @return URL (https://...)
	 */
	public static function getNextServerForFolder($folder, $user_id=null){
		if(substr($folder, 0, 1)!=='/'){
			\OCP\Util::writeLog('files_sharding', 'Relative paths not allowed: '.$folder, \OC_Log::ERROR);
		}
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$folders = explode('/', trim($folder, '/'));
		$baseFolder = $folders[0];
		\OCP\Util::writeLog('files_sharding', 'Base folder: '.$baseFolder, \OC_Log::WARN);
		//$fileInfo = \OC\Files\Filesystem::getFileInfo('/'.$user_id.'/files/'.$baseFolder);
		//$folderId = $fileInfo->getId();
		// If I'm the master, look up in DB
		if(self::isMaster()){
			$server = self::dbLookupNextServerForFolder($folder, $user_id);
		}
		// Otherwise, ask master
		else{
			$server = self::ws('get_folder_server', Array('user_id' => $user_id, 'folder' => $folder), true, false);
		}
		return $server;
	}
	
	public static function getNextSyncUser(){
		if(self::isMaster()){
			$userArr = self::dbGetNextSyncUser();
		}
		else{
			$userArr =  self::ws('get_next_sync_user', array());
		}
		return $userArr;
	}
	
	public static function dbGetNextSyncUser($server=null){
		if(empty($server)){
			$server = getMasterHostName();
		};
		// Get first row in oc_files_sharding_user_servers with server_id matching mine.
		// Notice: We cannot use methods relying on $_SERVER IP/host variables, as we are run from cron.
		$serverId = self::dbLookupServerId($server);
		$rows = self::dbGetServerUsers($serverId);
		foreach($rows as $row){
		// no matching process/running shell script, last_sync more than 20 hours ago and
		// ( priority 0 and access 1: execute shell script with the user+(new) primary server OR
		//   priority>0 and access 1: execute shell script with the user+backup server ).
			if($row['last_sync'] < time() - self::$USER_SYNC_INTERVAL_SECONDS &&
					($row['priority']===self::$USER_SERVER_PRIORITY_PRIMARY &&
							$row['access']===self::$USER_ACCESS_READ_ONLY ||
						$row['priority']>self::$USER_SERVER_PRIORITY_PRIMARY)){
				return($row);
			}
		}
		return null;
	}
	
	public static function getNextDeleteUser(){
		if(self::isMaster()){
			$user = self::dbGetNextDeleteUser();
		}
		else{
			$userArr =  self::ws('get_next_delete_user', array());
			$user = $userArr['user_id'];
		}
		return $user;
	}
	
	public static function dbGetNextDeleteUser($server=null){
		if(empty($server)){
			$server = getMasterHostName();
		};
		// Get first row in oc_files_sharding_user_servers with server_id matching mine.
		// Notice: We cannot use methods relying on $_SERVER IP/host variables, as we are run from cron.
		$serverId = self::dbLookupServerId($server);
		$rows = self::dbGetServerUsers($serverId);
		foreach($rows as $row){
			if($row['priority']===self::$USER_SERVER_PRIORITY_DISABLE){
				return($row['user_id']);
			}
		}
		return null;
	}
	
	public static function syncUser($user, $priority) {
		$serverURL = \OCA\FilesSharding\Lib::getServerForUser($user, true);
		$parse = parse_url($serverURL);
		$server = $parse['host'];
		if(empty($server)){
			\OCP\Util::writeLog('files_sharding', 'No server for user '.$user, \OC_Log::ERROR);
			return;
		}
		$i = 0;
		do{
			if($i>self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Syncing not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			$syncedFiles = shell_exec(__DIR__."/../sync_user.sh -u \"".$user."\" -s ".$server." | grep 'Synced files:' | awk -F ':' '{printf \$NF}'");
			\OCP\Util::writeLog('files_sharding', 'Synced '.$syncedFiles.' files for '.$user.' from '.$server, \OC_Log::ERROR);
			++$i;
		}
		while(!is_numeric($syncedFiles) || is_numeric($syncedFiles) && $syncedFiles!=0);
		// Get list of shared file mappings: ID -> path and update item_source on oc_share table on master with new IDs
		self::updateUserSharedFiles($user);
		// Get exported metadata (by path) via remote metadata web API and insert metadata on synced files by using local metadata web API
		\OCA\meta_data\Tags::updateUserFileTags($user, $serverURL);
		// Get and insert the password of the user
		$pwHash = self::getPasswordHash($user, $serverURL);
		$pwOk = self::setPasswordHash($user, $pwHash);
		if($i<=self::$MAX_SYNC_ATTEMPTS && $pwOk){
			// Update last_sync, set r/w if this is a new primary server
			$access = null;
			if($priority==self::$USER_SERVER_PRIORITY_PRIMARY){
				$access = self::$USER_ACCESS_ALL;
			}
			self::setServerForUser($user, null, $priority, $access);
			// Notify user
			send($app, $subject, $subjectparams = array(), $message = '', $messageparams = array(),
			$file = '', $link = '', $affecteduser = '', $type = '', $prio = IExtension::PRIORITY_MEDIUM);
		}
	}
	
	public static function deleteUser($user) {
		$i = 0;
		do{
			\OCP\Util::writeLog('files_sharding', 'Deleting user '.$user, \OC_Log::WARN);
			if($i>self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Deletion not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			$remainingFiles = shell_exec(__DIR__."/../delete_user.sh -u \"".$user." | grep 'Remaining files:' | awk -F ':' '{printf $NF}'");
			++$i;
		}
		while(!is_numeric($remainingFiles) || is_numeric($remainingFiles) && $remainingFiles!=0);
		self::setServerForUser($user, null, self::$USER_SERVER_PRIORITY_DISABLED, self::$USER_ACCESS_NONE);
	}
	
	public static function updateUserSharedFiles($user_id){
		// Get all files/folders shared by user
		$sharedItems = self::getItemSharedByUser($user_id);
		// Correction array to send to master
		$newIdMap = array('user_id'=>$user_id);
		foreach($sharedItems as $share){
			$path = \OC\Files\Filesystem::getPath($share['file_source']);
			// Get files/folders owned by user (locally) with the path of $share
			$file = OCA\FilesSharding\Lib::dbGetUserFile('files'.$path, $user_id);
			\OCP\Util::writeLog('files_sharding', 'Share: '.$file['path'].'==='.'files'.$path, \OC_Log::WARN);
			if($share['item_source']!=$file['fileid']){
				$newIdMap[$share['item_source']] = $file['fileid'];
			}
		}
		// Send the correction array to master
		$ret = self::ws('update_share_item_sources', $newIdMap);
		if($ret==null){
			\OCP\Util::writeLog('files_sharding', 'updateUserSharedFiles error', \OC_Log::ERROR);
		}
	}
	
	public static function setPasswordHash($user_id, $pwHash) {
		$query = \OC_DB::prepare('UPDATE `*PREFIX*users` SET `password` = ? WHERE `uid` = ?');
		$result = $query->execute(array($pwHash, $user_id));
		return $result ? true : false;
	}
	
	static function getPasswordHash($user_id, $serverURL=null){
		if($serverURL==null){
			$serverURL = self::getMasterInternalURL();
		}
		$res = self::ws('get_pw_hash', array('user_id'=>$user_id), true, true, $serverURL);
		if(empty($res->{'pw_hash'})){
			\OC_Log::write('files_sharding',"No password returned. ".$res->{'error'}, \OC_Log::WARN);
			return null;
		}
		if(!empty($res->{'error'})){
			\OC_Log::write('files_sharding',"Password error. ".$res->{'error'}, \OC_Log::WARN);
		}
		return $res->{'pw_hash'};
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
		
		if(strpos($_SERVER['REMOTE_ADDR'], self::$trustednet)===0){
			\OC_Log::write('files_sharding', 'Remote IP '.$_SERVER['REMOTE_ADDR'].' OK', \OC_Log::DEBUG);
			return true;
		}
		\OC_Log::write('files_sharding', 'Remote IP '.$_SERVER['REMOTE_ADDR'].' not trusted', \OC_Log::WARN);
		return false;
	}
	
	public static function getFileSource($itemSource, $itemType='file', $sharedWithMe=false) {
		if($sharedWithMe){
			$master_to_slave_id_map = \OCP\Share::getItemsSharedWith($itemType);
		}
		else{
			$master_to_slave_id_map = \OCP\Share::getItemsShared($itemType);
		}
		foreach($master_to_slave_id_map as $item1=>$data1){
			if($master_to_slave_id_map[$item1]['item_source'] == $itemSource){
				$ret = $master_to_slave_id_map[$item1]['file_source'];
				return $ret;
			}
		}
		return $itemSource;
	}
	
	public static function getItemsSharedWithUser($user_id){
		if(self::isMaster()){
			$sharedFiles = \OCP\Share::getItemsSharedWithUser('file', $user_id, \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS);
			$sharedFolders = \OCP\Share::getItemsSharedWithUser('folder', $user_id, \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS);
		}
		else{
			$sharedFiles =  self::ws('getItemsSharedWithUser',
					array('itemType' => 'file', 'user_id' => $user_id, 'shareWith' => $user_id, 'format' => \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS));
			$sharedFolders =  self::ws('getItemsSharedWithUser',
					array('itemType' => 'folder', 'user_id' => $user_id, 'shareWith' => $user_id, 'format' => \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS));
		}
		$result = array();
		if(!empty($sharedFiles)){
			$result = array_merge($result, $sharedFiles);
		}
		if(!empty($sharedFolders)){
			$result = array_merge($result, $sharedFolders);
		}
		return $result;
	}
	
	/**
	 * Get all items (yes, bad naming) shared by user.
	 * @param unknown $user_id
	 * @return array
	 */
	public static function getItemSharedByUser($user_id){
		if(!\OCP\App::isEnabled('files_sharding') || self::isMaster()){
			
			$loggedin_user = \OCP\USER::getUser();
			if(isset($user_id)){
				if(isset($loggedin_user)){
					$old_user = self::switchUser($user_id);
				}
				else{
					\OC_User::setUserId($user_id);
					\OC_Util::setupFS($user_id);
				}
			}
			else{
				$user_id = getUser();
			}
			if(empty($user_id)){
				\OCP\Util::writeLog('files_sharding', 'No user', \OC_Log::ERROR);
				return null;
			}
			$ret = \OCP\Share::getItemShared('file', null);
			if(isset($old_user) && $old_user){
				self::restoreUser($old_user);
			}
			return $ret;
		}
		else{
			\OCP\Util::writeLog('files_sharding', 'OCA\Files\Share_files_sharding::getItemShared '.$user_id.":".'file'.":".null, \OC_Log::WARN);
			return self::ws('getItemShared', array('user_id' => $user_id, 'itemType' => 'file',
					'itemSource' =>null));
		}
	}
	
	public static function getServerUsers($sharedItems){
		$owners = array();
		$serverUsers = array();
		//$hostname = $_SERVER['HTTP_HOST'];
		//$thisServerId = self::lookupServerId($hostname);
		foreach($sharedItems as $item){
			if(!in_array($item['uid_owner'], $owners)){
				$owners[] = $item['uid_owner'];
				$serverID = self::lookupServerIdForUser($item['uid_owner']);
				if(empty($serverID)){
					$masterHostName =  self::getMasterHostName();
					$serverID = self::lookupServerId($masterHostName);
				}
				/*if($serverID==$thisServerId){
				 continue;
				}*/
				if(array_key_exists($serverID, $serverUsers)){
					if(in_array($item['uid_owner'], $serverUsers[$serverID])){
						continue;
					}
				}
				else{
					$serverUsers[$serverID] = array();
				}
				$serverUsers[$serverID][] = $item['uid_owner'];
			}
		}
		return $serverUsers;
	}
	
	public static function rename($owner, $id, $dir, $name, $newname){
		$user_id = \OCP\USER::getUser();
		if($owner && $owner!==$user_id){
			\OC_Util::teardownFS();
			//\OC\Files\Filesystem::initMountPoints($owner);
			\OC_User::setUserId($owner);
			\OC_Util::setupFS($owner);
			\OCP\Util::writeLog('files_sharding', 'Owner: '.$owner.', user: '.\OCP\USER::getUser(), \OC_Log::WARN);
		}
		else{
			unset($user_id);
		}
		$view = \OC\Files\Filesystem::getView();
		if($id){
			$path = $view->getPath($id);
			$pathinfo = pathinfo($path);
			$dir = $pathinfo['dirname'];
			$name = $pathinfo['basename'];
			\OCP\Util::writeLog('files_sharding', 'DIR: '.$dir.', PATH: '.$path.', ID: '.$id, \OC_Log::WARN);
		}
		$files = new \OCA\Files\App(
				$view,
				\OC_L10n::get('files')
		);
		$result = $files->rename(
				$dir,
				$name,
				$newname
		);
		if(isset($user_id) && $user_id){
			// If not done, the user shared with will now be logged in as $owner
			\OC_Util::teardownFS();
			\OC_User::setUserId($user_id);
			\OC_Util::setupFS($user_id);
		}
		return $result;
	}
	
	public static function renameShareFileTarget($owner, $id, $oldname, $newname){
		
		if(!isset($owner) || !$owner){
			\OCP\Util::writeLog('files_sharing','ERROR: no owner given.', \OCP\Util::WARN);
			return false;
		}
		
		$old_file_target = self::getShareFileTarget($id);
		$new_file_target = preg_replace('|(.*)'.$oldname.'$|', '$1'.$newname, $old_file_target);
		
		\OC_Log::write('OCP\Share', 'QUERY: '.$oldname.':'.$newname.':'.$new_file_target, \OC_Log::WARN);
		
		$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `file_target` = ? WHERE `uid_owner` = ? AND `item_source` = ? AND `file_target` = ?');
		$result = $query->execute(array($new_file_target, $owner, $id, $old_file_target));
		
		if($result === false) {
			\OC_Log::write('OCP\Share', 'Couldn\'t update share table for '.$owner.' --> '.serialize($params), \OC_Log::ERROR);
		}
		
		return $result;
	}
	
	public static function deleteShareFileTarget($owner, $id, $path){
		
		if(!isset($owner) || !$owner){
			\OCP\Util::writeLog('files_sharing','ERROR: no owner given.', \OCP\Util::WARN);
			return false;
		}
		
		$old_file_target = self::getShareFileTarget($id);
		
		\OC_Log::write('OCP\Share', 'QUERY: '.$path, \OC_Log::WARN);
		
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*share` WHERE `uid_owner` = ? AND `item_source` = ?');
		$result = $query->execute(array($owner, $id));
		
		if($result === false) {
			\OC_Log::write('OCP\Share', 'Couldn\'t update share table for '.$user_id.' --> '.serialize($params), \OC_Log::ERROR);
		}
		
		return $result;
	}
	
	public static function getShareFileTarget($item_source){
		$query = \OC_DB::prepare('SELECT `file_target` FROM `*PREFIX*share` WHERE `item_source` = ?');
		$result = $query->execute(array($item_source));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$row = $result->fetchRow();
		return($row['file_target']);
	}
	
	public static function switchUser($owner){
		$user_id = \OCP\USER::getUser();
		if($owner && $owner!==$user_id){
			\OC_Util::teardownFS();
			//\OC\Files\Filesystem::initMountPoints($owner);
			\OC_User::setUserId($owner);
			\OC_Util::setupFS($owner);
			\OCP\Util::writeLog('files_sharding', 'Owner: '.$owner.', user: '.\OCP\USER::getUser(), \OC_Log::WARN);
			return $user_id;
		}
		else{
			return null;
		}
	}
	
	public static function restoreUser($user_id){
		// If not done, the user shared with will now be logged in as $owner
		\OC_Util::teardownFS();
		\OC_User::setUserId($user_id);
		\OC_Util::setupFS($user_id);
	}
	
	public static function getFileInfo($path, $owner, $id, $parentId){
		$info = null;
		if(($id || $parentId) && $owner){
			// For a shared directory get info from server holding the data
			if(!self::onServerForUser($owner)){
				$dataServer = self::getServerForUser($owner, true);
				if(!$dataServer){
					$dataServer = self::getMasterInternalURL();
				}
				if($id){
					$data = self::ws('getFileInfoData',
							array('user_id' => \OC_User::getUser(), 'path'=>urlencode($path), 'id'=>$id, 'owner'=>$owner),
							false, true, $dataServer);
				}
				elseif($parentId){
					$parentData = self::ws('getFileInfoData',
							array('user_id' => \OC_User::getUser(), 'id'=>$parentId, 'owner'=>$owner),
							false, true, $dataServer);
					$dirPath = preg_replace('|^files/|','/', $parentData['internalPath']);
					$pathinfo = pathinfo($path);
					$data = self::ws('getFileInfoData',
							array('user_id' => \OC_User::getUser(), 'path'=>urlencode($dirPath.'/'.$pathinfo['basename']), 'owner'=>$owner),
							false, true, $dataServer);
				}
				if($data){
					$storage = \OC\Files\Filesystem::getStorage($data['path']);
					$info = new \OC\Files\FileInfo($data['path'], $storage, $data['internalPath'], $data);
					\OCP\Util::writeLog('files_sharding', 'Returning file info for '.$data['path'].'-->'.serialize($data), \OC_Log::WARN);
				}
			}
			else{
				if($owner!=\OCP\USER::getUser()){
					$user_id = self::switchUser($owner);
				}
				if($id){
					$path = \OC\Files\Filesystem::getPath($id);
				}
				elseif($parentId){
					$parentPath = \OC\Files\Filesystem::getPath($parentId);
					$path = $parentPath . '/' . basename($path);
				}
				\OCP\Util::writeLog('files_sharding', 'Getting info for '.$path, \OC_Log::WARN);
				$info = \OC\Files\Filesystem::getFileInfo($path);
			}
		}
		else{
			// For non-shared directories, file information is kept on the slave
			if($id){
				$path = \OC\Files\Filesystem::getPath($id);
			}
			$info = \OC\Files\Filesystem::getFileInfo($path);
		}
		
		if(isset($user_id) && $user_id){
			self::restoreUser($user_id);
		}
		
		return $info;
	}
	
	public static function moveTmpFile($tmpFile, $path, $dirOwner, $dirId){
		if($dirId){
			$dirMeta = self::getFileInfo(null, $dirOwner, $dirId, null);
			$dirPath = preg_replace('|^files/|','/', $dirMeta->getInternalPath());
			$pathinfo = pathinfo($path);
			$path = $dirPath.'/'.$pathinfo['basename'];
		}
		
		if($dirOwner){
			// For a shared directory send data to server holding the directory
			if(!self::onServerForUser($dirOwner)){
				$dataServer = self::getServerForUser($dirOwner, true);
				if(!$dataServer){
					$dataServer = self::getMasterInternalURL();
				}
				return self::putFile($tmpFile, $dataServer, $dirOwner, $path);
			}
			else{
				if($dirOwner!=\OCP\USER::getUser()){
					$user_id = self::switchUser($dirOwner);
				}
			}
		}
		$ret = \OC\Files\Filesystem::fromTmpFile($tmpFile, $path);
		
		if(isset($user_id) && $user_id){
			self::restoreUser($user_id);
		}
		
		return $ret;
		
	}

	public static function putFile($tmpFile, $dataServer, $dirOwner, $path){
		
		$url = $dataServer . 'remote.php/mydav' . $path;
		
		\OCP\Util::writeLog('files_sharding', 'PUTTING '.$tmpFile.'-->'.$url, \OC_Log::ERROR);
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_UNRESTRICTED_AUTH, TRUE);
		curl_setopt($curl, CURLOPT_USERPWD, $dirOwner.':');
		curl_setopt($curl, CURLOPT_UPLOAD, TRUE);
		curl_setopt($curl, CURLOPT_PUT, TRUE);
		curl_setopt($curl, CURLOPT_INFILESIZE, filesize($tmpFile));
		$fh = fopen($tmpFile, 'r');
		curl_setopt($curl, CURLOPT_INFILE, $fh);
		$res = curl_exec ($curl);
		fclose($fh);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not put. '.$res, \OC_Log::ERROR);
			return null;
		}
		return true;
	}
	
	public static function buildFileStorageStatistics($dir, $owner, $id){
		// TODO: Implement and integrate with future placing algorithm
		return Array('uploadMaxFilesize' => -1);
	}

	// Inspired by http://stackoverflow.com/questions/16955549/first-segment-of-a-intersection-between-two-string
	public static function stripleft($a, $b) {
		$len = strlen($a) > strlen($b) ? strlen($b) : strlen($a);
		for($i=0; $i<$len; $i++){
			if(substr($a, $i, 1) != substr($b, $i, 1)){
				break;
			}
		}
		return $i;
	}
	
	public static function stripright($a, $b) {
		$len = strlen($a) > strlen($b) ? strlen($b) : strlen($a);
		for($i=$len-1; $i<=0; $i--){
			if(substr($a, $i, 1) != substr($b, $i, 1)){
				break;
			}
		}
		return $i;
	}
	
}

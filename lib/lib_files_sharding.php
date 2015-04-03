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
	
	public static function onServerForUser($user_id=null){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$user_server = self::dbLookupServerUrlForUser($user_id);
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
	
	// TODO: have all ws* functions use this
	public static function ws($script, $data, $post=false, $array=true){
		$content = "";
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
		$url = self::getMasterInternalURL();
		$url .= "apps/files_sharding/ws/".$script.".php";
		if(!$post){
			$url .= "?".$content;
		}
		\OCP\Util::writeLog('files_sharding', 'URL: '.$url, \OC_Log::WARN);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		if($post){
			curl_setopt($curl, CURLOPT_POST, $post);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		}
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
	
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: '.$json_response, \OC_Log::ERROR);
			return null;
		}
	
		$response = json_decode($json_response, $array);
	
		return $response;
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
	
	public static function getServersList(){
		if(self::isMaster()){
			return self::dbGetServersList();
		}
		else{
			return self::wsGetServersList();
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
	
	private static function wsGetServersList(){
		$url = self::getMasterInternalURL();
		$url = $url."/apps/files_sharding/ws/get_servers.php";
		$content = "";
		$data = array();
	
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
		curl_close($curl);
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no data servers found --> '.serialize($json_response), \OC_Log::ERROR);
			return null;
		}
			
		$response = json_decode($json_response, true);
		\OCP\Util::writeLog('files_sharding', 'Received data servers '.$json_response, \OC_Log::WARN);
		$_SESSION['oc_data_folders'] = $response;
		return $response;
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
			return self::wsAddDataFolder($folder, $user_id);
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
	
	private static function wsAddDataFolder($folder, $user_id){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/add_data_folder.php";
		$content = "";
		$data = array(
				'folder' => $folder,
				'user_id' => $user_id,
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
		curl_close($curl);
		
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not add data folder via ws for user '.$user_id.' : '.$json_response, \OC_Log::ERROR);
			return null;
		}
		
		$response = json_decode($json_response, true);
		
		if(isset($response['error'])){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not add data folder via ws for user '.$user_id.' : '.$response['error'], \OC_Log::ERROR);
			return false;
		}
		$_SESSION['oc_data_folders'] = $response;
		return true;
	}

	public static function inDataFolder($path, $user_id=null){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$dataFolders = self::getDataFoldersList($path, $user_id);
		$checkPath = trim($path, '/ ');
		$checkPath = '/'.$checkPath;
		$checkLen = strlen($checkPath);
		foreach($dataFolders as $p){
			$dataFolderPath = $p['folder'];
			$dataFolderLen = strlen($dataFolderPath);
			if($checkPath===$dataFolderPath || substr($checkPath, 0, $dataFolderLen+1)===$dataFolderPath.'/'){
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
				return self::wsGetDataFoldersList($user_id);
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
	
	private static function wsGetDataFoldersList($user_id=null){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/get_data_folders.php";
		$content = "";
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		$data = array(
				"user_id" => $user_id,
		);
	
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
	
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		curl_setopt($curl, CURLOPT_COOKIEJAR, 'oc_data_folders_'.$user_id);
		curl_setopt($curl, CURLOPT_COOKIEFILE, '/var/tmp/'.$user_id);
		
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no data folders for '.$user_id.' from '.$url.' --> '.serialize($json_response), \OC_Log::ERROR);
			return null;
		}
			
		$response = json_decode($json_response, true);
		\OCP\Util::writeLog('files_sharding', 'Received data folders '.$json_response, \OC_Log::WARN);
		$_SESSION['oc_data_folders'] = $response;
		return $response;
	}

	public static function removeDataFolder($folder, $user_id){
		if(self::isMaster()){
			return self::dbRemoveDataFolder($folder, $user_id);
		}
		else{
			return self::wsRemoveDataFolder($folder, $user_id);
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
	
	private static function wsRemoveDataFolder($folder, $user_id){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/remove_data_folder.php";
		$content = "";
		$data = array(
				'folder' => $folder,
				'user_id' => $user_id,
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
		curl_close($curl);
	
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not resync data folder '.$folder.' : '.$json_response, \OC_Log::ERROR);
			return null;
		}
	
		$response = json_decode($json_response, true);
	
		if(isset($response['error'])){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not remove data folder via ws for user '.$user_id.' : '.$response['error'], \OC_Log::ERROR);
			return false;
		}
		$_SESSION['oc_data_folders'] = $response;
		return true;
	}
	
	/**
	 * From files_sharing.
	 * get file ID from a given path
	 * @param string $path
	 * @return string fileID or null
	 */
	public static function getFileId($path) {
		$view = new \OC\Files\View('/'.\OCP\User::getUser().'/files');
		$fileId = null;
		$fileInfo = $view->getFileInfo($path);
		if ($fileInfo) {
			$fileId = $fileInfo['fileid'];
		}
		return $fileId;
	}

	public static function getFilePath($id) {
		return \OC\Files\Filesystem::getpath($id);
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
		// Priorities: -1: disabled, 0: primary/home (r/w), 1: backup (r/o), >1: unused
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
		// If I'm the master, look up in DB
		if(self::isMaster()){
			$server = self::dbLookupServerUrlForUser($user);
		}
		// Otherwise, ask master
		else{
			$server = self::wsLookupServerUrlForUser($user);
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
	 * Lookup server for user via web service.
	 * @param $folder
	 * @param $user_id
	 * @return URL (https://...)
	 */
	private static function wsLookupNextServerForFolder($folder, $user_id){
		$url = self::getMasterInternalURL();
		$url = $url."apps/files_sharding/ws/get_folder_server.php";
		$content = "";
		$data = array(
			"folder" => $folder,
			"user_id" => $user_id,
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
		curl_close($curl);
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no server found for folder '.$folder, \OC_Log::ERROR);
			return null;
		}
		
		$response = json_decode($json_response, false);
		
		if(strpos($response->status, 'error')!==false){
			\OCP\Util::writeLog('files_sharding', 'ERROR: no free server found via ws for folder '.$folder.' : '.$response->status, \OC_Log::ERROR);
			return null;
		}
		
		return $response->url;
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
			$server = self::wsLookupNextServerForFolder($folder, $user_id);
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
		
		if(strpos($_SERVER['REMOTE_ADDR'], Lib::$trustednet)===0){
			\OC_Log::write('files_sharding', 'Remote IP '.$_SERVER['REMOTE_ADDR'].' OK', \OC_Log::WARN);
			return true;
		}
		\OC_Log::write('files_sharding', 'Remote IP '.$_SERVER['REMOTE_ADDR'].' not trusted', \OC_Log::WARN);
		return false;
	}
	
	public static function searchAllServers($query){
		$user_id = \OCP\USER::getUser();
		$servers = self::getServersList();
		\OCP\Util::writeLog('search', 'Searching servers '.serialize($servers), \OC_Log::WARN);
		$results = array();
		foreach($servers as $server){
			if(isset($server['internal_url']) && !empty($server['internal_url'])){
				$results[$server['url']] = self::wsSearchServer($query, $server['internal_url'], $user_id);
			}
		}
		return $results;
	}
	
	private static function wsSearchServer($query, $url, $user_id){
		$full_url = $url."/apps/files_sharding/ws/search.php";
		$content = "";
		$data = array('query'=>$query, 'user_id'=>$user_id);
	
		foreach($data as $key=>$value) { $content .= $key.'='.$value.'&'; }
	
		$curl = curl_init($full_url);
		curl_setopt($curl, CURLOPT_HEADER, false);
	
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		\OCP\Util::writeLog('search', 'Searching: '.$full_url, \OC_Log::WARN);
		
		$json_response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300 || empty($json_response)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: search failed on '.$full_url.' --> '.serialize($json_response), \OC_Log::ERROR);
			return null;
		}
			
		$response = json_decode($json_response, true);
		\OCP\Util::writeLog('files_sharding', 'Received search results '.$json_response, \OC_Log::WARN);
		return $response;
	}
	
}

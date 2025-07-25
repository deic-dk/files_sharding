<?php

namespace OCA\FilesSharding;

class Lib {

	private static $masterinternalurl = '';
	private static $masterfq = '';
	private static $masterurl = '';
	private static $cookiedomain = '';
	private static $trustednets = null;
	// No limit
	private static $minfree = -1;
	
	private static $adminOkIPs = [];
	
	public static $USER_ACCESS_ALL = 0;
	public static $USER_ACCESS_READ_ONLY = 1;
	public static $USER_ACCESS_TWO_FACTOR = 2;
	public static $USER_ACCESS_TWO_FACTOR_FORCED = 3;
	public static $USER_ACCESS_NONE = 4;
	
	// Setting the password hash in the DB to this disables password login and hides password login on the login page
	public static $FORCE_NO_PASSWORD_HASH = "!";
	
	public static $USER_SERVER_PRIORITY_DISABLE = -2;
	public static $USER_SERVER_PRIORITY_DISABLED = -1;
	public static $USER_SERVER_PRIORITY_PRIMARY = 0;
	public static $USER_SERVER_PRIORITY_BACKUP_1 = 1;
	public static $USER_SERVER_PRIORITY_BACKUP_2 = 2;
	
	// Short-lived cookie to guarantee recent login and redirect
	public static $LOGIN_OK_COOKIE = 'oc_ok';
	// Relatively short-lived cookie to cache positive answer to access query to master
	public static $ACCESS_OK_COOKIE = 'oc_access_ok';
	public static $ACCESS_OK_COOKIE_SECONDS = 600;
	// Session cookie to inform that login has been performed on master, i.e. that
	// we're not in stand-alone mode
	public static $MASTER_LOGIN_COOKIE = 'oc_master_login';
	
	public static $NOT_IN_DATA_FOLDER = 0;
	public static $IN_DATA_FOLDER = 1;
	public static $IS_DATA_FOLDER = 2;
	
	const TYPE_SERVER_SYNC = 'server_sync';
	
	public static $USER_SYNC_INTERVAL_SECONDS = 86400; // 24 hours
	private static $MAX_SYNC_ATTEMPTS = 1;
	
	// To use X.509 authentification for trusted WS calls, set the following paths
	// in the config file: wscertificate, wsprivatekey, wscacertificate
	// NOTICE that Apache must also use the file wscacertificate.
	private static $WS_CERT_CACHE_KEY = 'oc_ws_cert';
	private static $WS_CERT_SUBJECT_CACHE_KEY = 'oc_ws_cert_subject';
	private static $WS_KEY_CACHE_KEY = 'oc_ws_private_key';
	private static $WS_CACERT_CACHE_KEY = 'oc_ws_cacert';
	// Full path of the certificate/key files used for trusted WS requests if the
	// above attributes are set in the config file.
	public static $wsCert = '';
	public static $wsKey = '';
	public static $wsCACert = '';
	public static $wsCertSubject = '';
	
	private static $SECOND_FACTOR_CACHE_KEY_PREFIX = 'oc_second_factor';
	
	public static function getServerAccessText($access){
		$l = new \OC_L10N('files_sharding');
		$ret = $l->t("Read/write");
		switch($access){
			case self::$USER_ACCESS_ALL:
				$ret = $l->t("Read/write");
				break;
			case self::$USER_ACCESS_READ_ONLY:
				$ret = $l->t("Read only");
				break;
			case self::$USER_ACCESS_TWO_FACTOR:
				$ret = $l->t("Read/write with two-factor authentication");
				break;
			case self::$USER_ACCESS_TWO_FACTOR_FORCED:
				$ret = $l->t("Read/write with two-factor authentication forced");
				break;
			case self::$USER_ACCESS_NONE:
				$ret = $l->t("None");
				break;
		}
		return $ret;
	}
	
	private static function getAdminUser(){
		$sql = "SELECT uid FROM *PREFIX*group_user WHERE gid = ?";
		$args = array('admin');
		$query = \OCP\DB::prepare($sql);
		$output = $query->execute($args);
		while($row=$output->fetchRow()){
			if(!empty($row['uid'])){
				return $row['uid'];
			}
		}
		return null;
	}
	
	private static function isAdminIP($ip){
		if(empty($ip) || !empty(self::$adminOkIPs[$ip])){
			return false;
		}
		$adminIpsString = \OC_Config::getValue('adminips', '');
		$adminIps = explode(',', $adminIpsString);
		foreach($adminIps as $adminIp) {
			if(strpos(trim($ip), trim($adminIp))===0){
				self::$adminOkIPs[] = $ip;
				return true;
			}
		}
		return false;
	}
	
	public static function checkAdminIP($user=null){
		if(empty($user)){
			$user = \OCP\USER::getUser();
		}
		$adminUser = self::getAdminUser();
		\OCP\Util::writeLog('files_sharding', 'checkAdminIP: '.$user.'<-->'.
				$adminUser.'<-->'.$_SERVER['REMOTE_ADDR'].'<-->'.self::isAdminIP($_SERVER['REMOTE_ADDR']), \OC_Log::DEBUG);
		// Not admin, all ok
		if($user != $adminUser){
			return;
		}
		// Kick out if logged in as admin from a non-white-listed IP.
		if(!empty($_SERVER['REMOTE_ADDR']) && !self::isAdminIP($_SERVER['REMOTE_ADDR'])){
			\OC_Util::tearDownFS();
			\OC_User::setUserId("");
			session_destroy();
			$session_id = session_id();
			unset($_COOKIE[$session_id]);
			\OC_Response::redirect(\OC::$WEBROOT);
			exit;
		}
		// We're an admin user and our IP is ok
	}
	
	public static function getCookieDomain(){
		if(self::$cookiedomain===''){
			self::$cookiedomain = \OCP\Config::getSystemValue('cookiedomain', '');
			self::$cookiedomain = (substr(self::$cookiedomain, -7)==='_DOMAIN'?null:self::$cookiedomain);
		}
		return self::$cookiedomain;
	}
	
	public static function getMinFree(){
		if(self::$minfree===-1){
			$minfreegb = \OCP\Config::getSystemValue('minfreegb', -1);
			self::$minfree = $minfreegb>=0?$minfreegb*pow(1024,3):-1;
		}
		return self::$minfree;
	}
	
	public static function isHostMe($hostname){
		if(empty($hostname)){
			return false;
		}
		if(empty($_SERVER['HTTP_HOST']) && empty($_SERVER['SERVER_NAME'])){
			// Running off cron
			$myShortName = php_uname("n");
			$homeNameArr = explode(".", $hostname);
			$homeName = isset($homeNameArr[0])?$homeNameArr[0]:null;
			return !empty($homeName) && $myShortName == $homeName;
		}
		return
			isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===$hostname ||
			isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===$hostname;
	}
	
	public static function isServerMe($server){
		if(empty($server)){
			return false;
		}
		$parse = parse_url($server);
		return self::isHostMe($parse['host']);
	}
	
	public static function onServerForUser($user_id=null){
		$user_id = empty($user_id)?\OCP\USER::getUser():$user_id;
		$user_server = self::getServerForUser($user_id);
		$user_server_internal = self::getServerForUser($user_id, true);
		$user_server_private = self::internalToPrivate($user_server_internal);
		if(!empty($user_server)){
			$parse = parse_url($user_server);
			$user_host = $parse['host'];
			$parse = parse_url($user_server_internal);
			$user_host_internal = $parse['host'];
			$parse = parse_url($user_server_private);
			$user_host_private = $parse['host'];
			\OCP\Util::writeLog('files_sharding', 'onServerForUser: '.$user_id.':'.$user_host.
					':'.$user_host_internal.':'.(empty($_SERVER['HTTP_HOST'])?'':$_SERVER['HTTP_HOST']).
					':'.(empty($_SERVER['SERVER_NAME'])?'':$_SERVER['SERVER_NAME']), \OC_Log::WARN);
		}
		if(empty($user_server)){
			// If no server has been set for the user, he can logically only be on the master
			\OCP\Util::writeLog('files_sharding', 'onServerForUser: No host for user '.$user_id, \OC_Log::WARN);
			return self::isMaster();
		}
		if(empty($_SERVER['HTTP_HOST']) && empty($_SERVER['SERVER_NAME'])){
			// Running off cron
			$myShortName = php_uname("n");
			$homeNameArr = explode(".", $user_host);
			$homeName = isset($homeNameArr[0])?$homeNameArr[0]:null;
			$homeNameInternalArr = explode(".", $user_host_internal);
			$homeNameInternal = isset($homeNameInternalArr[0])?$homeNameInternalArr[0]:null;
			return !empty($homeName) && $myShortName == $homeName ||
				!empty($homeNameInternal) && $myShortName == $homeNameInternal;
		}
		return 
				isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===$user_host ||
				isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===$user_host_internal ||
				isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']===$user_host_private ||
				isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===$user_host ||
				isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===$user_host_internal ||
				isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']===$user_host_private;
	}
	
	public static function isMaster(){
		self::getMasterHostName();
		self::getMasterInternalURL();
		if(!empty(self::$masterinternalurl)){
			$masterprivateurl = self::internalToPrivate(self::$masterinternalurl);
			$parsedinternal = parse_url(self::$masterinternalurl);
			$masterinternalip = $parsedinternal['host'];
			$parsedprivate = parse_url($masterprivateurl);
			$masterprivateip = $parsedprivate['host'];
		}
		$masterNameArr = explode(".", self::$masterfq);
		$masterShortName = empty($masterNameArr[0])?self::$masterfq:$masterNameArr[0];
		if(empty($_SERVER['HTTP_HOST']) && empty($_SERVER['SERVER_NAME'])){
			// Running off cron
			$myShortName = php_uname("n");
			return isset($masterNameArr[0]) && $myShortName == $masterNameArr[0];
		}
		return empty(self::$masterfq) && empty($masterinternalip) ||
		
				isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST']===self::$masterfq ||
						isset($masterinternalip) && $_SERVER['HTTP_HOST']===$masterinternalip ||
						isset($masterinternalip) && $_SERVER['HTTP_HOST']===$masterprivateip ||
						isset($masterinternalip) && $_SERVER['HTTP_HOST']===$masterShortName) ||
						
				isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME']===self::$masterfq ||
						isset($masterinternalip) && $_SERVER['SERVER_NAME']===$masterinternalip ||
						isset($masterinternalip) && $_SERVER['SERVER_NAME']===$masterprivateip ||
						isset($masterinternalip) && $_SERVER['SERVER_NAME']===$masterShortName);
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
	
	public static function getMasterSite(){
		if(!empty(self::$mastersite)){
			return self::$mastersite;
		}
		$servers = self::getServersList();
		$masterUrl = self::getMasterURL();
		foreach($servers as $server){
			if($server['url']===$masterUrl ||
					$server['url'].'/'===$masterUrl ||
					$server['url']===$masterUrl.'/'){
				return($server['site']);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: Could not find master site '.$masterUrl.
				', '.serialize($servers), \OC_Log::ERROR);
		return null;
	}
	
	public static function getMasterID(){
		$servers = self::getServersList();
		$masterUrl = self::getMasterURL();
		foreach($servers as $server){
			if($server['url']===$masterUrl ||
					$server['url'].'/'===$masterUrl ||
					$server['url']===$masterUrl.'/'){
						return($server['id']);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: Could not find master ID '.$masterUrl.
				', '.serialize($servers), \OC_Log::ERROR);
		return null;
	}
	
	public static function internalToPrivate($internalUrl){
		// User VLANs allowing private data transfers to/from Kube containers
		$vnet = \OCP\Config::getSystemValue('uservlannet', '');
		$vnet = trim($vnet);
		$vnets = explode(' ', $vnet);
		$uservlannets = array_map('trim', $vnets);
		if(count($uservlannets)==1 && substr($uservlannets[0], 0, 10)==='USER_VLAN_'){
			$uservlannets = [];
		}
		// Trusted internal networks
		$tnet = \OCP\Config::getSystemValue('trustednet', '');
		$tnet = trim($tnet);
		$tnets = explode(' ', $tnet);
		$trustednets = array_map('trim', $tnets);
		if(count($trustednets)==1 && substr($trustednets[0], 0, 8)==='TRUSTED_'){
			$trustednets = [];
		}
		$privateUrl = $internalUrl;
		// We're assuming the number private and internal networks are the same and that silos
		// are on the same number in the list and have the same trailing number on each.
		// E.g. silo2: 10.0.0.15 -> 10.2.0.15
		$netpos = 0;
		foreach($trustednets as $net){
			if(preg_match('|^https*://'.$net.'.*|', $internalUrl)){
				$privateUrl = preg_replace('|^(https*://)'.$net.'|', '${1}'.$uservlannets[$netpos], $internalUrl);
				break;
			}
			++$netpos;
		}
		return $privateUrl;
	}
	
	public static function getAllowLocalLogin($node){
		if(self::isMaster()){
			return self::dbGetAllowLocalLogin($node)!=='no';
		}
		else{
			$ret = self::ws('get_allow_local_login', Array('node' => $node), true, true);
			return empty($ret['allow_local_login'])||$ret['allow_local_login']!=='no';
		}
	}
	
	public static function dbGetAllowLocalLogin($node){
		$query = \OC_DB::prepare('SELECT `allow_local_login` FROM `*PREFIX*files_sharding_servers` WHERE `url` LIKE ?');
		$result = $query->execute(Array("http%://$node%"));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not determine local login permission, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
	
	public static function dbExcludeAsBackup($id, $value){
		$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_servers` set `exclude_as_backup` = ? WHERE `id` = ?');
		$result = $query->execute( array($value, $id));
		return $result ? true : false;
	}
	
	/**
	 * @param unknown $user
	 * @param unknown $url
	 * @param unknown $destBaseDir
	 * @param unknown $target
	 * @param unknown $username optional remote login username
	 * @param unknown $password optional remote login password
	 * @return NULL|unknown
	 */
	public static function getFile($user, $url, $destBaseDir, $target,
			$username='', $password=''){

		\OC_Util::teardownFS();
		\OC\Files\Filesystem::init($user, $destBaseDir);

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_UNRESTRICTED_AUTH, TRUE);
		if(!empty($username)){
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
		}
		
		if(!empty(self::getWSCert())){
			\OCP\Util::writeLog('files_sharding', 'Authenticating '.$url.':'.$username.':'.$password.' with cert '.self::$wsCert.
					' and key '.self::$wsKey, \OC_Log::WARN);
			curl_setopt($curl, CURLOPT_CAINFO, self::$wsCACert);
			curl_setopt($curl, CURLOPT_SSLCERT, self::$wsCert);
			curl_setopt($curl, CURLOPT_SSLKEY, self::$wsKey);
			//curl_setopt($curl, CURLOPT_SSLCERTPASSWD, '');
			//curl_setopt($curl, CURLOPT_SSLKEYPASSWD, '');
		}
		
		$data = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300 || $data===null || $data===false){
			\OCP\Util::writeLog('files_sharding', 'ERROR: bad ws response from '.$url.' : '.$status.' : '.$data, \OC_Log::ERROR);
			return null;
		}
		
		\OCP\Util::writeLog('files_sharding', 'Writing data to '.$destBaseDir.':'.$target, \OC_Log::WARN);
		$success = \OC\Files\Filesystem::file_put_contents($target, $data);
		
		return $success;
	}
	
	public static function propfind($url, $prop='', $username='', $password='', $tryCertAuth=false){
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PROPFIND");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($curl, CURLOPT_UNRESTRICTED_AUTH, TRUE);
		if(!empty($username)){
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
			//curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_HEADER, false);
			\OCP\Util::writeLog('files_sharding', 'Authenticating '.$url.':'.$username.':'.$password, \OC_Log::WARN);
		}
		
		if($tryCertAuth && !empty(self::getWSCert())){
			\OCP\Util::writeLog('files_sharding', 'Authenticating '.$url.':'.$username.':'.$password.' with cert '.self::$wsCert.
					' and key '.self::$wsKey, \OC_Log::WARN);
			curl_setopt($curl, CURLOPT_CAINFO, self::$wsCACert);
			curl_setopt($curl, CURLOPT_SSLCERT, self::$wsCert);
			curl_setopt($curl, CURLOPT_SSLKEY, self::$wsKey);
			//curl_setopt($curl, CURLOPT_SSLCERTPASSWD, '');
			//curl_setopt($curl, CURLOPT_SSLKEYPASSWD, '');
		}
		
		// Make sure there's always a basic auth header, so a username can be found even
		// when using the server cert as client cert (backup sync)
		$header = array(
				'HTTP/1.1',
				'Content-type: text/plain',
				'Authorization: Basic ' . base64_encode("$username:$password")
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		
		$data = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300 || $data===null || $data===false){
			\OCP\Util::writeLog('files_sharding', 'ERROR: bad response from '.$url.' : '.$status.' : '.$data, \OC_Log::ERROR);
			return null;
		}
		if(!empty($prop)){
			\OCP\Util::writeLog('files_sharding', 'Parsing data '.$data, \OC_Log::DEBUG);
			$dom = new \DOMDocument();
			$dom->loadXML($data);
			$xpath = new \DOMXpath($dom);
			$xpath->registerNamespace('ns', 'http://sabredav.org/ns');
			$xpath->registerNamespace('ns', 'http://nextcloud.org/ns');
			$xpath->registerNamespace('ns', 'http://owncloud.org/ns');
			$ret = $xpath->evaluate('string(//d:response/d:propstat/d:prop/'.$prop.')');
			$ret = trim($ret, '"');
			return $ret;
		}
		else{
			return $data;
		}
	}
	
	/*
	 * Map of calls to be cached => seconds to live.
	 */
	private static $WS_CACHE_CALLS = array('getItemsSharedWith'=>10, 'get_data_folders'=>10,
			'get_user_server'=>30, 'getFileTags'=>10, 'share_fetch'=>10, 'getShareByToken'=>10,
			'searchTagsByIDs'=>10, 'searchTags'=>10, 'getItemsSharedWithUser'=>20,
			'get_server_id'=>60, 'get_servers'=>20, 'getTaggedFiles'=>30, 'get_user_server_access'=>30,
			'read'=>30, 'get_allow_local_login'=>60, 'userExists'=>60, 'personalStorage'=>20, 'getCharge'=>30,
			'accountedYears'=>60, 'getUserGroups'=>10, 'lookupServerId'=>60, 'getServePublicUrl'=>60,
			'searchKeyByID'=>30, 'get_public_shares'=>30, 'lookupSiteInfo'=>30
	);
	
	public static function getWSCert(){
		if(empty(self::$wsCert)){
			if(!apc_exists(self::$WS_CERT_CACHE_KEY)){
				self::$wsCert = \OCP\Config::getSystemValue('wscertificate', '');
				apc_store(self::$WS_CERT_CACHE_KEY, self::$wsCert);
				if(!empty(self::$wsCert)){
					$parsedCert = openssl_x509_parse(file_get_contents(self::$wsCert));
					self::$wsCertSubject = $parsedCert['name'];
					apc_store(self::$WS_CERT_SUBJECT_CACHE_KEY, self::$wsCertSubject);
					
					self::$wsKey = \OCP\Config::getSystemValue('wsprivatekey', '');
					apc_store(self::$WS_KEY_CACHE_KEY, self::$wsKey);
					
					self::$wsCACert = \OCP\Config::getSystemValue('wscacertificate', '');
					apc_store(self::$WS_CACERT_CACHE_KEY, self::$wsCACert);
				}
			}
			else{
				self::$wsCert = apc_fetch(self::$WS_CERT_CACHE_KEY);
				if(!empty(self::$wsCert)){
					self::$wsCertSubject = apc_fetch(self::$WS_CERT_SUBJECT_CACHE_KEY);
					self::$wsKey = apc_fetch(self::$WS_KEY_CACHE_KEY);
					self::$wsCACert = apc_fetch(self::$WS_CACERT_CACHE_KEY);
				}
			}
		}
		return empty(self::$wsCert)?null:array('certificate_file'=>self::$wsCert, 'key_file'=>self::$wsKey,
				'subject'=>self::$wsCertSubject, 'ca_file'=>self::$wsCACert);
	}
	
	public static function ws($script, $data, $post=false, $array=true, $baseUrl=null,
			$appName=null, $urlencode=false, $timeoutMs=0, $async=false){
		$content = "";
		foreach($data as $key=>$value) { $content .= $key.'='.($urlencode?urlencode($value):$value).'&'; }
		if($baseUrl==null){
			$baseUrl = self::getMasterInternalURL();
		}
		if(empty($url)){
			$url = $baseUrl . "/apps/".(empty($appName)?"files_sharding":$appName)."/ws/".$script.".php";
		}
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
			\OCP\Util::writeLog('files_sharding', 'Returning cached response for '.$script.'-->'.$cache_key, \OC_Log::INFO);
			return $response;	
		}
		
		\OCP\Util::writeLog('files_sharding', 'URL: '.$url.', '.($post?'POST':'GET').': '.$content, \OC_Log::WARN);
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'curl');
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
		
		if(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			$langHeader = array("Accept-Language: ".$_SERVER['HTTP_ACCEPT_LANGUAGE']);
			\OCP\Util::writeLog('files_sharding', 'Using language header '.$langHeader[0], \OC_Log::DEBUG);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $langHeader);
		}
		
		if(!empty(self::getWSCert())){
			\OCP\Util::writeLog('files_sharding', 'Authenticating '.$url.' with cert '.self::$wsCert.
					' and key '.self::$wsKey, \OC_Log::INFO);
			curl_setopt($curl, CURLOPT_CAINFO, self::$wsCACert);
			curl_setopt($curl, CURLOPT_SSLCERT, self::$wsCert);
			curl_setopt($curl, CURLOPT_SSLKEY, self::$wsKey);
			//curl_setopt($curl, CURLOPT_SSLCERTPASSWD, '');
			//curl_setopt($curl, CURLOPT_SSLKEYPASSWD, '');
		}
		
		if(!empty($timeoutMs)){
			curl_setopt($curl, CURLOPT_TIMEOUT_MS, $timeoutMs);
			curl_setopt($curl, CURLOPT_NOSIGNAL, 1);
		}
		
		// If run asynchroneously, we don't provide any response
		if($async){
			$mh = curl_multi_init();
			curl_multi_add_handle($mh, $curl);
			do{
				$status = curl_multi_exec($mh, $active);
				if($active){
					// Wait a short time for more activity
					curl_multi_select($mh);
				}
			}
			while($active && $status == CURLM_OK);
			curl_multi_remove_handle($mh, $curl);
			curl_multi_close($mh);
			return null;
		}
		else{
			$json_response = curl_exec($curl);
			$status = curl_getinfo($curl);
			curl_close($curl);
			if(empty($status['http_code']) || $status['http_code']===0 || $status['http_code']>=300 || $json_response===null || $json_response===false){
				\OCP\Util::writeLog('files_sharding', 'ERROR: bad ws response from '.$url.' : '. serialize($status).' : '.$json_response, \OC_Log::ERROR);
				return true;
			}
			
			if(isset(self::$WS_CACHE_CALLS[$script])){
				if(apc_add($cache_key, $json_response, (int)self::$WS_CACHE_CALLS[$script])){
					\OCP\Util::writeLog('files_sharding', 'Caching response for '.apc_exists($cache_key).': '.$script.'-->'.$cache_key, \OC_Log::WARN);
				}
			}
			else{
				\OCP\Util::writeLog('files_sharding', 'NOT caching response for '.$script.'-->'.$cache_key.'-->'.$status['total_time'], \OC_Log::WARN);
			}
			
			$response = json_decode($json_response, $array);
			return $response;
		}
	}
	
	public static function getPublicShare($token) {
		if(self::isMaster()){
			return self::dbGetPublicShare($token);
		}
		else{
			$ret = self::ws('get_public_share', Array('token'=>$token),false, true, null, null, true);
			return $ret;
		}
	}
	
	public static function dbGetPublicShare($token) {
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `token` = ?');
		$result = $query->execute(Array($token));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Could not determine share owner, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for token: '.$token, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row);
		}
		\OCP\Util::writeLog('files_sharding', 'ERROR: share not found: '.$token, \OC_Log::ERROR);
		return [];
	}
	
	public static function getPublicShares($userid) {
		if(self::isMaster()){
			return self::dbGetPublicShares($userid);
		}
		else{
			$ret = self::ws('get_public_shares', Array('userid'=>$userid),false, true, null, null, true);
			return $ret;
		}
	}
	
	public static function dbGetPublicShares($userid) {
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `uid_owner` = ? AND share_type = ?');
		$result = $query->execute(Array($userid, \OCP\Share::SHARE_TYPE_LINK));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Could not find public shares, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}

	/**
	 * Check if a path is publicly shared or a subdirectory of a publicly shared directory.
	 * The path is relative to eithers /<user_id>/files/ or /<user_id>/user_group_admin/<group_name>/
	 */
	public static function checkPubliclyShared($path, $owner, $group=""){
		$publicShares = self::getPublicShares($owner);
		$user_id = self::switchUser($owner);
		if(!empty($group)){
			\OC\Files\Filesystem::tearDown();
			$groupDir = '/'.$owner.'/user_group_admin/'.$group;
			\OC\Files\Filesystem::init($owner, $groupDir);
		}
		else{
			\OC\Files\Filesystem::init($owner);
			$path = rtrim($path, "/");
		}
		try{
			foreach($publicShares as $publicShare){
				$sharedFileId = $publicShare['item_source'];
				$sharedPath = \OC\Files\Filesystem::getpath($sharedFileId);
				\OCP\Util::writeLog('files_sharding', 'Public share: '.$sharedPath.':'.
						$publicShare['permissions'].'<-->'.$path, \OC_Log::DEBUG);
				if($publicShare['item_type']=="folder"){
					if(strpos($path, $sharedPath."/")===0){
						self::restoreUser($user_id, true);
						return $publicShare['permissions'];
					}
				}
				if(rtrim($path, "/")==rtrim($sharedPath, "/")){
					self::restoreUser($user_id, true);
					return $publicShare['permissions'];
				}
			}
		}
		catch(\Exception $e){
			self::restoreUser($user_id, true);
		}
		self::restoreUser($user_id, true);
		return false;
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
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get list of servers, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}

	public static function dbGetServer($id){
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get server '.$id.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)===0){
			return null;
		}
		return $results[0];
	}
	
	public static function dbGetSitesList($onlyBackupSites=false){
		$sql = "SELECT DISTINCT `site` FROM `*PREFIX*files_sharding_servers`";
		if($onlyBackupSites){
			$sql .= " WHERE `exclude_as_backup` != 'yes' AND `site` != 'none' AND `site` IS NOT NULL AND `site` <> ''";
		}
		$query = \OC_DB::prepare($sql);
		$result = $query->execute(Array());
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get list of sites, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}
	
	public static function dbGetFile($fileid){
		$queryString = 'SELECT * FROM `*PREFIX*filecache` WHERE fileid = ?';
		$queryParams = Array($fileid);
		$query = \OC_DB::prepare($queryString);
		$result = $query->execute($queryParams);
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Could not get file with id, '.$fileid.'. '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(empty($results)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Could not get file with id, '.$fileid.'. '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			return null;
		}
		return $results[0];
	}
	
	/**
	 * 
	 * @param unknown $user_id
	 * @param string $group
	 * @param string $prefix
	 * @param string $type 'dir' or 'file'
	 * @param boolean $storage whether or not the file/dir is in /storage
	 * @return NULL|unknown
	 */
	public static function dbGetUserFiles($user_id=null, $group="", $prefix="", $type='', $storage=false){
		$loggedin_user = \OCP\USER::getUser();
		if(isset($user_id)){
			if(isset($loggedin_user) && $user_id!=$loggedin_user){
				$old_user = self::switchUser($user_id);
			}
			else{
				\OC_User::setUserId($user_id);
				\OC_Util::setupFS($user_id);
			}
		}
		else{
			$user_id = $loggedin_user;
		}
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		if(empty($storage)){
			$storage = \OC\Files\Filesystem::getStorage('/'.$user_id.'/');
		}
		else{
			$storage = \OC\Files\Filesystem::getStorage('/'.$user_id.'/files_external/storage/');
		}
		$storageId = $storage->getId();
		//$mount = \OC\Files\Filesystem::getMountByNumericId('8');
		$numericStorageId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
		\OCP\Util::writeLog('files_sharding', 'Storage ID for '.$user_id.': '.$numericStorageId.': '/*.
				$mount[0]->getMountPoint()*/, \OC_Log::WARN);
		if(empty($numericStorageId) || $numericStorageId==-1){
			return null;
		}
		$queryString = 'SELECT * FROM `*PREFIX*filecache` WHERE storage = ?';
		$queryParams = Array($numericStorageId);
		if(!empty($prefix)){
			if(!empty($group)){
				$queryString = $queryString . ' AND path LIKE ?';
				$queryParams[] = 'user_group_admin/'.$group.'/'.ltrim($prefix, '/').'%';
			}
			elseif($storage){
				$queryString = $queryString . ' AND path LIKE ?';
				$queryParams[] = ltrim($prefix, '/').'%';
			}
			else{
				$queryString = $queryString . ' AND path LIKE ?';
				$queryParams[] = 'files/'.ltrim($prefix, '/').'%';
			}
		}
		if(!empty($type)){
			$storage = \OC\Files\Filesystem::getStorage('/'.$user_id.'/');
			$cache = $storage->getCache();
			$dirMimeId = $cache->getMimetypeId('httpd/unix-directory');
			if($type=='file'){
				$queryString = $queryString . ' AND mimetype != ?';
				$queryParams[] = $dirMimeId;
			}
			elseif($type=='dir'){
				$queryString = $queryString . ' AND mimetype = ?';
				$queryParams[] = $dirMimeId;
			}
			else{
				throw new Exception('Unsopported type '.$type);
			}
		}
		\OCP\Util::writeLog('files_sharding', 'Getting user files with '.$queryString, \OC_Log::WARN);
		$query = \OC_DB::prepare($queryString);
		$result = $query->execute($queryParams);
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Could not get user files, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		\OCP\Util::writeLog('files_sharding', 'Got user files '.serialize($results), \OC_Log::WARN);
		if(isset($old_user) && $old_user){
			self::restoreUser($old_user);
		}
		return $results;
	}
	
	private static function dbGetUserFile($path, $user_id){
		if(empty($user_id)){
			\OCP\Util::writeLog('files_sharding', 'No user', \OC_Log::ERROR);
			return null;
		}
		$storage = \OC\Files\Filesystem::getStorage('/'.$user_id.'/');
		$storageId = $storage->getId();
		$numericStorageId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
		\OCP\Util::writeLog('files_sharding', 'Storage ID for user '.$user_id.': '.$storageId, \OC_Log::WARN);
		if(empty($numericStorageId) || $numericStorageId==-1){
			return null;
		}
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*filecache` WHERE storage = ? AND path = ?');
		$result = $query->execute(Array($numericStorageId, $path));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get user file '.$path.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		\OCP\Util::writeLog('files_sharding','Found file: '.$numericStorageId. ' --> '.$path.' : '.serialize($results),
				\OC_Log::DEBUG);
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for user/path '.$user_id.'/'.$path, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row);
		}
		return array();
	}
	
	public static function updateShareItemSources($user_id, $map){
		$result = true;
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
	
	public static function addDataFolder($folder, $group, $user_id, $serverID=null){
		if(self::isMaster()){
			$user_server_id = empty($serverID)?self::dbLookupServerIdForUser($user_id, self::$USER_SERVER_PRIORITY_PRIMARY):$serverID;
			if(empty($user_server_id)){
				$user_server_id = self::getMasterID();
			}
			self::dbAddDataFolder($folder, $group, $user_server_id, $user_id, self::$USER_SERVER_PRIORITY_PRIMARY);
		}
		else{
			$_SESSION['oc_data_folders'][] = array('folder'=>$folder, 'gid'=>$group,
				'server_id'=>empty($serverID)?'':$serverID, 'user_id'=>$user_id,
				'priority'=>self::$USER_SERVER_PRIORITY_PRIMARY);
			return self::ws('add_data_folder',
					Array('user_id' => $user_id, 'folder' => $folder, 'group' => $group,
							'server_id' => (empty($serverID)?'':$serverID)), true, true);
		}
	}
	
	private static function dbAddDataFolder($folder, $group, $server_id, $user_id, $priority=0){
		$query = \OC_DB::prepare(
				'INSERT INTO `*PREFIX*files_sharding_folder_servers` (`folder`, `gid`, `server_id`, `user_id`,  `priority`) VALUES (?, ?, ?, ?, ?)');
		$result = $query->execute(Array($folder, $group, $server_id, $user_id, $priority));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not add data folder '.$folder.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			return false;
		}
		$_SESSION['oc_data_folders'] = self::dbGetDataFoldersList($user_id);
		return true;
	}

	public static function inDataFolder($path, $user_id=null, $group=null){
		$user_id = (empty($user_id)?\OCP\USER::getUser():$user_id);
		$dataFolders = self::getDataFoldersList($user_id);
		$checkPath = trim($path, '/ ');
		$checkPath = '/'.$checkPath;
		foreach($dataFolders as $p){
			if(!empty($group) && !empty($p['gid']) && $group!=$p['gid']){
				continue;
			}
			$dataFolderPath = $p['folder'];
			$dataFolderLen = strlen($dataFolderPath);
			\OCP\Util::writeLog('files_sharding', 'Checking path: '.$user_id.'-->'.$checkPath.'-->'.$dataFolderPath, \OC_Log::DEBUG);
			if(substr($checkPath, 0, $dataFolderLen+1)===$dataFolderPath.'/'){
				\OCP\Util::writeLog('files_sharding', 'Excluding '.$dataFolderPath, \OC_Log::INFO);
				return self::$IN_DATA_FOLDER;
			}
			elseif($checkPath===$dataFolderPath){
				\OCP\Util::writeLog('files_sharding', 'Excluding '.$dataFolderPath, \OC_Log::INFO);
				return self::$IS_DATA_FOLDER;
			}
		}
		return self::$NOT_IN_DATA_FOLDER;
	}
	
	public static function getDataFoldersList($user_id){
		if(self::isMaster()){
			// On the master, get the list from the database
			return self::dbGetDataFoldersList($user_id);
		}
		else{
			// On a slave, in the web interface, use session variable set by the master
			if(isset($_SESSION['oc_data_folders'])){
				$res = $_SESSION['oc_data_folders'];
			}
			else{
				// On a slave via webdav, ask the master
				$res = self::ws('get_data_folders', Array('user_id' => $user_id), true, true);
			}
			return empty($res)?[]:$res;
		}
	}
	
	public static function dbGetDataFoldersList($user_id){
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_folder_servers` WHERE user_id = ?');
		$result = $query->execute(Array($user_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get list of data folders, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			return [];
		}
		$results = $result->fetchAll();
		return $results;
	}

	public static function removeDataFolder($folder, $user_id, $group=''){
		if(self::isMaster()){
			return self::dbRemoveDataFolder($folder, $user_id, $group);
		}
		else{
			return self::ws('remove_data_folder', Array('user_id' => $user_id, 'folder' => $folder, 'group' => $group), true, true);
		}
	}
	
	private static function dbRemoveDataFolder($folder, $user_id, $group=''){
		if(empty($group)){
			$group = '';
		}
		// If folder spans several servers, deny syncing
		$results = self::dbGetServersForFolder($folder, $user_id, $group);
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', "Error: cannot sync sharded folder ".$folder, \OC_Log::ERROR);
			return false;
		}
		$query = \OC_DB::prepare(
				'DELETE FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder` = ? AND `user_id` = ? AND `gid` = ?');
		$result = $query->execute(Array($folder, $user_id, $group));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not remove data folder '.$folder.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
	 * @param string $group
	 * @return string fileID or null
	 */
	public static function getFileId($path, $user_id=null, $group=null) {
		if(!isset($user_id)){
			$user_id = \OCP\User::getUser();
		}
		if(!empty($group)){
			$view = new \OC\Files\View('/'.$user_id.'/user_group_admin/'.$group);
		}
		else{
			$view = new \OC\Files\View('/'.$user_id.'/files');
		}
		$fileId = null;
		$fileInfo = $view->getFileInfo($path);
		if($fileInfo){
			$fileId = $fileInfo['fileid'];
		}
		else{
			\OCP\Util::writeLog('files_sharding', 'Could not find ID for for '.$user_id.' : '.$path.' --> '.serialize($fileInfo), \OC_Log::ERROR);
		}
		\OCP\Util::writeLog('files_sharding', 'Got ID '.$fileId.' for path '.$path, \OC_Log::INFO);
		return $fileId;
	}

	public static function getFilePath($id, $owner=null, $group=null) {
		if(isset($owner) && $owner!=\OCP\USER::getUser()){
			$user_id = self::switchUser($owner);
		}
		if(!empty($group)){
			if(empty($owner)){
				$owner = \OC_User::getUser();
			}
			\OC\Files\Filesystem::tearDown();
			$groupDir = '/'.$owner.'/user_group_admin/'.$group;
			\OC\Files\Filesystem::init($owner, $groupDir);
		}
		$ret = \OC\Files\Filesystem::getpath($id);
		if(isset($user_id) && $user_id || !empty($group)){
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
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not look up URL for '.$id.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not look up internal URL for '.$id.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
			$userId = \OC_User::getUser();
			if(empty($userId) && isset($_SERVER['PHP_AUTH_USER'])){
				$userId = $_SERVER['PHP_AUTH_USER'];
			}
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
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not check access for for '.
					$userId.'/'.$serverId.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
	
	public static function dbGetUserServerPriority($serverId, $userId){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_user_servers` WHERE `server_id` = ? AND  `user_id` = ?');
		$result = $query->execute(Array($serverId, $userId));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get priority for for '.$userId.'/'.$serverId.', '.
					\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Too many entries found for server '.$serverId.', user '.$userId, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return($row['priority']);
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING: server '.$serverId.', user '.$userId.' not found.', \OC_Log::DEBUG);
		return self::$USER_SERVER_PRIORITY_PRIMARY;
	}
	
	private static function dbGetServerUsers($serverId){
		$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_user_servers` WHERE `server_id` = ?');
		$result = $query->execute(Array($serverId));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get users on '.$serverId.', '.
					\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		return $results;
	}

/**
	 * Get the ID of a server. Default to ID of myself.
	 * @param $hostname hostname of the server
	 */
	public static function lookupServerId($hostname=null){
		if(self::isMaster()){
			if(empty($hostname)){
				$hostname = self::$masterfq;
			}
			return self::dbLookupServerId($hostname);
		}
		else{
			$res = self::ws('get_server_id', empty($hostname)?Array():Array('hostname' => $hostname), false, true);
			return $res['id'];
		}
	}
	
	private static function is_ip($str) {
		$ret = filter_var($str, FILTER_VALIDATE_IP);
	
		return $ret;
	}
	
	private static function is_ipv4($str) {
		$ret = filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
	
		return $ret;
	}
	
	private static function is_ipv6($str) {
		$ret = filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
	
		return $ret;
	}
	
	public static function dbLookupServerId($hostname){
		if(!empty($hostname) && !empty($_SERVER['MY_HOST'])){
			// If the request is coming to one of my host aliases, go on with my canonical hostname
			$trusted_domains = \OCP\Config::getSystemValue('trusted_domains', []);
			foreach($trusted_domains as $trusted_domain){
				if($hostname==$trusted_domain){
					//return null;
					$hostname = $_SERVER['MY_HOST'];
					break;
				}
			}
		}
		$servers = self::dbGetServersList();
		foreach($servers as $server){
			if($server['url']==$hostname || $server['internal_url']==$hostname){
				return $server['id'];
			}
			$urlParts = parse_url($server['url']);
			if($urlParts['host']==$hostname){
				return $server['id'];
			}
			$urlParts = parse_url($server['internal_url']);
			if($urlParts['host']==$hostname){
				return $server['id'];
			}
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING, dbLookupServerId: '.$hostname.
				': Trying DNS lookup. This takes time, so consider changing your DB entry.', \OC_Log::WARN);
		if(self::is_ip($hostname)){
			$checkHostName = gethostbyaddr($hostname);
		}
		else{
			$checkHostIPs = gethostbynamel($hostname);
		}
		foreach($servers as $server){
			$urlParts = parse_url($server['url']);
			$dbHost = $urlParts['host'];
			// Two IPs - one (the one in the DB) may not be the primary
			if(self::is_ip($hostname)){
				if(self::is_ip($dbHost)){
					$dbHostName = gethostbyaddr($dbHost);
					if($checkHostName==$dbHostName){
						return $server['id'];
					}
				}
				// DB entry is a hostname, checked host is and IP
				else{
					// First check DNS lookup of checked host IP
					if($checkHostName==$dbHost){
						return $server['id'];
					}
					// Then check IPs the DB entry resolves to
					$dbHostIPs = gethostbynamel($dbHost);
					foreach($dbHostIPs as $dbHostIP){
						if($hostname==$dbHostIP){
							return $server['id'];
						}
					}
				}
			}
			else{
				// Checked host is  a hostname, DB entry an IP
				if(self::is_ip($dbHost)){
					// First check DNS lookup of DB entry
					$dbHostName = gethostbyaddr($dbHost);
					if($hostname==$dbHostName){
						return $server['id'];
					}
					// Then check IPs the checked hostname resolves to
					foreach($checkHostIPs as $checkHostIP){
						if($checkHostIP==$dbHost){
							return $server['id'];
						}
					}
				}
				// Two hostnames
				else{
					$dbHostIPs = gethostbynamel($dbHost);
					foreach($checkHostIPs as $checkHostIP){
						foreach($dbHostIPs as $dbHostIP){
							if($checkHostIP==$dbHostIP){
								return $server['id'];
							}
						}
					}
				}
			}
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING, dbLookupServerId: server not found: '.$hostname,
				\OC_Log::ERROR);
		return null;
	}
	
	public static function dbAddServer($url, $internal_url, $site, $charge,
			$allow_local_login, $id=null, $x509_dn='', $description=''){
		
		if(empty($id)){
			$id = md5(uniqid(rand(), true));
		}
		
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get ID, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)===0){
			$query = \OC_DB::prepare('INSERT INTO `*PREFIX*files_sharding_servers` (`id`, `url`, `internal_url`, `site`, `allow_local_login`, `charge_per_gb`, `x509_dn`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
			$result = $query->execute( array($id, $url, $internal_url, $site, $allow_local_login, $charge, $x509_dn, $description) );
		}
		elseif(count($results)===1){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_servers` SET `url` = ?, `internal_url` = ?, `site` = ?, `allow_local_login` = ?, `charge_per_gb` = ? , `x509_dn` = ?, `description` = ? WHERE `ID` = ?');
			$result = $query->execute( array($url, $internal_url, $site, $allow_local_login,
					$charge, $x509_dn, $description, $id));
		}
		if(count($results)>1){
			$error = 'ERROR: Duplicate entries found for server '.$id.' : '.$server_id;
			\OCP\Util::writeLog('files_sharding', $error, \OCP\Util::ERROR);
			throw new Exception($error);
		}
		return $result ? true : false;
	}
	
	public static function dbDeleteServer($id){
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?');
		$result = $query->execute(Array($id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not delete server '.$id.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		return $result ? true : false;
	}
	
	/**
	 * Get the priority of a server (small number = high priority).
	 * @param $name
	 */
	private static function dbLookupUserServerPriority($user, $serverId){
		$query = \OC_DB::prepare('SELECT `priority` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `server_id` = ?');
		$result = $query->execute(Array($user, $serverId));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not look up server priority for '.
					$user.'/'.$serverId.', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
	 * Choose site for user on very first login.
	 * @param $mail
	 * @param $schacHomeOrganization - e.g. "dtu.dk"
	 * @param $organizationName - e.g. "Danmarks Tekniske Universitet"
	 * @param $entitlement
	 */
	// TODO: refine this - taking into account available servers and their space
	public static function dbChooseSiteForUser($mail, $schacHomeOrganization, $organizationName,
			$entitlement){
		// Keep non-nationals on master (they will be harder to support on sharding issues)
		// Keep cancer.dk users on master - they use a common, large shared folder - try to give them
		// decent performance.
		// TODO: make placement of users/domains doable from the admin web interface
		$masterfq = self::getMasterHostName();
		if(substr($mail,-3)!==substr($masterfq,-3) || substr($mail,-10)==='@cancer.dk'){
			return self::getMasterSite();
		}
		$servers = self::dbGetServersList();
		$shortest = INF;
		$closestSite = null;
		foreach($servers as $server){
			if(empty($server['site']) || strtolower($server['site'])=='none'){
				continue;
			}
			$l = levenshtein(strtolower($server['site']), strtolower($schacHomeOrganization));
			\OC_Log::write('files_sharding','Levenshtein for '.$server['site'].' for user '.$mail.":".$schacHomeOrganization.
					":".$entitlement.":".$l, \OC_Log::WARN);
			if($l>=0 && $l<$shortest){
				$shortest = $l;
				$closestSite = $server['site'];
			}
		}
		if(!empty($closestSite)){
			return $closestSite;
		}
		// Default to master
		return self::getMasterSite();
	}
	
	/**
	 * Choose server for user, given a chosen site.
	 * @param $user_id
	 * @param $email
	 * @param $site
	 * @param $priority
	 * @param $exclude_server_id can be null
	 * @return Server ID
	 */
	public static function dbChooseServerForUser($user_id, $user_email, $site, $priority, $exclude_server_id){
		
		// Keep non-nationals on master (they will be harder to support on sharding issues)
		// Keep cancer.dk users on master - they use a common, large shared folder - try to give them
		// decent performance.
		// TODO: make placement of users/domains doable from the admin web interface
		if(!empty($user_email)){
			$masterfq = self::getMasterHostName();
			if(substr($user_email,-3)!==substr($masterfq,-3) || substr($user_email,-10)==='@cancer.dk'){
				$masterHostName = self::getMasterHostName();
				$masterID = self::dbLookupServerId($masterHostName);
				// We could also just have returned null, as the default is master
				return $masterID;
			}
		}
		
		// TODO: placing algorithm that takes, quota, available space and even distribution of users into consideration
		// For now, just take the first server found of the given site.
		$current_server_id = self::dbLookupServerIdForUser($user_id, $priority);
		// Watch out for migrated users
		if(empty($current_server_id) && !empty ($user_email)){
			$current_server_id = self::dbLookupServerIdForUser($user_email, $priority);
		}
		$old_server_ids = self::dbLookupOldServerIdsForUser($user_id);
		\OCP\Util::writeLog('files_sharding', 'Current server: '.$current_server_id, \OC_Log::WARN);
		$query = \OC_DB::prepare('SELECT `id` FROM `*PREFIX*files_sharding_servers` WHERE `site` = ?');
		$result = $query->execute(Array($site));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get id, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		\OCP\Util::writeLog('files_sharding', 'Number of servers for site '.$site.': '.count($results), \OC_Log::WARN);
		
		$del = array();
		
		foreach($results as $i=>$row){
			if(!empty($exclude_server_id) && $row['id']===$exclude_server_id ||
					$priority>=self::$USER_SERVER_PRIORITY_BACKUP_1 && !empty($row['exclude_as_backup']) &&
					$row['exclude_as_backup']==='yes'){
				$del[] = $i;
				break;
			}
		}
		foreach($del as $i){
			\OCP\Util::writeLog('files_sharding', 'Excluding '.implode(':', $results[$i]), \OC_Log::WARN);
			$results = array_splice($results, $i, 1);
			$results = array_values($results);
			\OCP\Util::writeLog('files_sharding', 'Servers now: '.serialize($results), \OC_Log::WARN);
		}
				
		// First see if the user is just playing around and returning to the same site
		foreach($results as $row){
			if(!empty($current_server_id) && $row['id']===$current_server_id){
				return($row['id']);
			}
		}
		// Give priority to a server used before
		foreach($results as $row){
			if(!empty($current_server_id) &&
					in_array($row['id'], array_keys($old_server_ids)) && $old_server_ids[$row['id']]===$site){
				return($row['id']);
			}
		}

		$num_rows = count($results);
		\OCP\Util::writeLog('files_sharding', 'Number of servers now: '.$num_rows, \OC_Log::WARN);
		if($num_rows>0){
			$random_int = rand(0, $num_rows-1);
			\OCP\Util::writeLog('files_sharding', 'Choosing random server '.$random_int.' out of '.$num_rows, \OC_Log::WARN);
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
	 * @param $access
	 * @param $last_sync
	 * @throws Exception
	 * @return boolean true on success, false on failure
	 */
	public static function dbSetServerForUser($user_id, $server_id, $priority=null, $access=null, $last_sync=0){
		if(empty($priority) && $priority!==0 && $priority!=='0'){
			$priority = self::dbGetUserServerPriority($server_id, $user_id);
		}
		if(empty($server_id)){
			// If disabling a user we want to disable all servers
			if((int)$priority==self::$USER_SERVER_PRIORITY_DISABLED && (int)$access==self::$USER_ACCESS_NONE){
				$server = "ALL";
				$server_id = "%";
			}
			else{
				// This is in case it is a slave making a ws request.
				$server = $_SERVER['REMOTE_ADDR'];
				$server_id = self::dbLookupServerId($server);
				// This is in case we're just altering an entry
				if(empty($server_id)){
					$server_info = self::dbGetUserServerInfo($user_id, $priority);
					$server_id = empty($server_info)||empty($server_info['id'])?null:$server_info['id'];
				}
				// Fall back to master
				if(empty($server_id)){
					$server = self::getMasterHostName();
					$server_id = self::dbLookupServerId($server);
				}
			}
		}
		// If we're not changing anything, just return true
		if(!isset($access) && empty($last_sync) && self::dbLookupServerIdForUser($user_id, $priority)===$server_id){
			return true;
		}
		// If we're setting a home server, set current home server as backup server
		// NO - handled in javascript
		/*if($priority===0){
			$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = 1 WHERE `user_id` = ? AND `priority` = 0');
			$result = $query->execute( array($user_id));
		}*/
		// If we're setting a new backup server, disable current backup server
		if($priority==self::$USER_SERVER_PRIORITY_BACKUP_1){
			if(!empty($server_id) && $server_id!="%"){
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ?, `access` = ? WHERE `user_id` = ? AND `priority` >= ? AND `server_id` != ?');
				$result = $query->execute(array(self::$USER_SERVER_PRIORITY_DISABLE,
						self::$USER_ACCESS_NONE, $user_id, self::$USER_SERVER_PRIORITY_BACKUP_1, $server_id));
			}
			else{
			// Backup server cleared, nothing more to do
				return $result ? true : false;
			}
		}
		
		if(!empty($server) && $server == "ALL"){
			$query = \OC_DB::prepare('SELECT `user_id`, `server_id`, `priority`, `access`, `last_sync` '.
					'FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ?');
			$result = $query->execute(Array($user_id));
		}
		else{
			$query = \OC_DB::prepare('SELECT `user_id`, `server_id`, `priority`, `access`, `last_sync` '.
					'FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `server_id` = ?');
			$result = $query->execute(Array($user_id, $server_id));
		}
		
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get user server, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		
		\OCP\Util::writeLog('files_sharding', 'Number of servers for '.$user_id.":".$server_id.":".count($results).":".$priority.":".$access,
				\OCP\Util::ERROR);
		if(count($results)===0 && (int)$priority!=self::$USER_SERVER_PRIORITY_DISABLED && (int)$access!==self::$USER_ACCESS_NONE &&
				$server_id!="%"){
			$newAccess = isset($access)?$access:self::$USER_ACCESS_READ_ONLY;
			$lastSync = empty($last_sync)?0:$last_sync;
			$query = \OC_DB::prepare('INSERT INTO `*PREFIX*files_sharding_user_servers` (`user_id`, `server_id`, `priority`, `access`, `last_sync`) VALUES (?, ?, ?, ?, ?)');
			$result = $query->execute( array($user_id, $server_id, $priority, $newAccess, $lastSync));
			return $result ? true : false;
		}
		else{
			foreach($results as $row){
				if((empty($server) || $server != "ALL") && $row['priority']==$priority &&
						(!isset($access) || $row['access']==$access) &&
						(empty($last_sync) || $row['last_sync']==$last_sync)){
					return true;
				}
			}
			
			if(!isset($access) && empty($last_sync)){
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ? WHERE `user_id` = ? AND `server_id` LIKE ?');
				$result = $query->execute(array($priority, $user_id, $server_id));
			}
			elseif(empty($last_sync)){
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ?, `access` = ? WHERE `user_id` = ? AND `server_id` LIKE ?');
				$result = $query->execute(array($priority, $access, $user_id, $server_id));
			}
			elseif(!isset($access)){
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ?, `last_sync` = ? WHERE `user_id` = ? AND `server_id` LIKE ?');
				$result = $query->execute(array($priority, $last_sync, $user_id, $server_id));
			}
			else{
				$query = \OC_DB::prepare('UPDATE `*PREFIX*files_sharding_user_servers` set `priority` = ?, `access` = ?, `last_sync` = ? WHERE `user_id` = ? AND `server_id` LIKE ?');
				$result = $query->execute(array($priority, $access, $last_sync, $user_id, $server_id));
			}
			return $result ? true : false;
		}
	}
	
	public static function setServerForUser($user_id, $server_id, $priority, $access=null, $last_sync=0){
		if(self::isMaster()){
			$ret = self::dbSetServerForUser($user_id, $server_id, $priority, $access, $last_sync);
		}
		else{
			$args = array('user_id'=>$user_id, 'server_id'=>$server_id, 'priority'=>$priority);
			if($access===0 || $access==='0' || !empty($access)){
				$args['access'] = $access;
			}
			if($last_sync!==null){
				$args['last_sync'] = $last_sync;
			}
			$res = self::ws('set_server_for_user', $args);
			$ret = empty($res['error']);
		}
		return $ret;
	}

	
	/**
	 * Lookup home server for user in database.
	 * @param $user
	 * @return URL of the server - null if none has been set. Important as user_saml relies on this.
	 */
	public static function dbLookupServerUrlForUser($user, $priority=null){
		if(empty($priority)){
			$priority = self::$USER_SERVER_PRIORITY_PRIMARY;
		}
		$id = self::dbLookupServerIdForUser($user, $priority);
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
	public static function dbLookupInternalServerUrlForUser($user, $priority=0){
		$id = self::dbLookupServerIdForUser($user, $priority);
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
	public static function lookupServerIdForUser($user, $priority=null){
		if($priority===null){
			$priority = self::$USER_SERVER_PRIORITY_PRIMARY;
		}
		if(self::isMaster()){
			$serverId = self::dbLookupServerIdForUser($user, $priority);
		}
		// Otherwise, ask master
		else{
			$res = self::ws('get_user_server', Array('user_id' => $user, 'priority'=>$priority), false, true);
			$serverId = $res['id'];
		}
		return $serverId;
	}
	
	public static function dbGetUserServerInfo($user, $priority=null){
		if($priority===null){
			$priority = self::$USER_SERVER_PRIORITY_PRIMARY;
		}
		$serverId = self::dbLookupServerIdForUser($user, $priority);
		$server = self::dbGetServer($serverId);
		return $server;
	}
	
	public static function getUserServerInfo($user, $priority=null){
		if($priority===null){
			$priority = self::$USER_SERVER_PRIORITY_PRIMARY;
		}
		if(self::isMaster()){
			return self::dbGetUserServerInfo($user, $priority);
		}
		else{
			return self::ws('get_user_server_info',
					Array('user'=>urlencode($user), 'priority'=>$priority), true, false);
		}
	}
	
	/**
	 * @param $user
	 * @param $priority
	 * @param $lastSync If given, only return server if it has been synced since $lastSync
	 * @return ID of server
	 */
	public static function dbLookupServerIdForUser($user, $priority, $lastSync=-1){
		\OCP\Util::writeLog('files_sharding', "Looking up server ".$user .":". $priority .":". $lastSync, \OC_Log::DEBUG);
		// Priorities: -1: disabled, 0: primary/home (r/w), 1: backup (r/o), >1: unused
		$sql = 'SELECT `server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ?';
		if($lastSync>0){
			$sql .= ' AND `last_sync` >= ?';
		}
		$sql .= ' AND `priority` = ? ORDER BY `priority`';
		$query = \OC_DB::prepare($sql);
		$result = $lastSync>0?$query->execute(Array($user, $lastSync, $priority)):
			$query->execute(Array($user, $priority));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get server_id, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		if(count($results)>1){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Duplicate entries found for user:priority '.$user.":".$priority, \OCP\Util::ERROR);
		}
		foreach($results as $row){
			return $row['server_id'];
		}
		\OCP\Util::writeLog('files_sharding', 'No server found for query '.$sql."-->".$user .":". $priority .":". $lastSync, \OC_Log::DEBUG);
		return null;
	}
	
	private static function dbLookupOldServerIdsForUser($user){
		$servers = self::dbGetServersList();
		$ret = array();
		// Active servers
		$query = \OC_DB::prepare(
				'SELECT `server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `priority` >= 0 ORDER BY `priority` ASC');
		$result = $query->execute(Array($user));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get old server IDs, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		foreach($results as $row){
			foreach($servers as $server){
				if($row['server_id']===$server['id']){
					$ret[$row['server_id']] = $server['site'];
				}
			}
		}
		// Inactive servers
		$query = \OC_DB::prepare(
				'SELECT `server_id` FROM `*PREFIX*files_sharding_user_servers` WHERE `user_id` = ? AND `priority` < 0 ORDER BY `priority` DESC');
		$result = $query->execute(Array($user));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get old server IDs, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$results = $result->fetchAll();
		foreach($results as $row){
			foreach($servers as $server){
				if($row['server_id']===$server['id']){
					$ret[$row['server_id']] = $server['site'];
				}
			}
		}
		\OCP\Util::writeLog('files_sharding', 'Old servers: '.serialize($ret), \OC_Log::WARN);
		return $ret;
	}
	
	public static function dbLookupLastSync($server_id, $user_id){
		$sql = 'SELECT `last_sync` FROM `*PREFIX*files_sharding_user_servers` WHERE `server_id` = ? AND `user_id` = ?';
		$query = \OC_DB::prepare($sql);
		$result = $query->execute(Array($server_id, $user_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get last sync, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
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
		\OCP\Util::writeLog('files_sharding', 'dbGetSite: site not found for server: '.$server_id.' Using master '.$master_site, \OC_Log::ERROR);
		return $master_site;
	}

	/**
	 * Lookup URL of server for user.
	 * @param unknown $user_id
	 * @param internal $internal whether to return the internal URL
	 * @return the base URL (https://...) of the server that will serve the files
	 */
	public static function getServerForUser($user_id, $internal=false, $priority=0, $default_to_master=false){
		// If I'm the master, look up in DB
		if(self::isMaster()){
			if($internal){
				$server = self::dbLookupInternalServerUrlForUser($user_id, $priority);
			}
			else{
				$server = self::dbLookupServerUrlForUser($user_id, $priority);
			}
		}
		// Otherwise, ask master
		else{
			$response = self::ws('get_user_server',
					Array('user_id' => $user_id, 'internal' => ($internal?'yes':'no'), 'priority' => $priority), false, false);
			$server = $response->url;
		}
		if(empty($server) && $default_to_master){
			$server = $internal?self::getMasterInternalURL():self::getMasterURL();
		}
		return $server;
	}
	
	private static function dbGetServersForFolder($folder, $user_id=null, $group=''){
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		if(empty($group)){
			$group = '';
		}
		
		$folders = explode('/', trim($folder, '/'));
		$ret = array();
		while(!empty($folders)){
			$fol = '/'.implode($folders, '/');
			$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*files_sharding_folder_servers` WHERE `folder` = ? and `user_id` = ? and `gid` = ? ORDER BY `priority`');
			$result = $query->execute(Array($fol, $user_id, $group));
			$results = $result->fetchAll();
			if(\OCP\DB::isError($result)){
				\OCP\Util::writeLog('files_sharding', 'ERROR: could not get servers for folder, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
			}
			$ret = array_merge($ret, $results);
			array_pop($folders);
		}
		
		return $ret;
	}
	
	/**
	 * Look up server for folder in database.
	 * @param $folder
	 * @param $user_id
	 * @param $group
	 * @return URL (https://...)
	 */
	public static function dbGetServerForFolder($folder, $user_id, $internal=false, $group=''){
		$results = self::dbGetServersForFolder($folder, $user_id, $group);
		foreach($results as $row){
			if($row['priority']=self::$USER_SERVER_PRIORITY_PRIMARY){
				if($internal){
					return dbLookupInternalServerURL($row['server_id']);
				}
				else{
					return dbLookupServerURL($row['server_id']);
				}
			}
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING: no server registered for folder '.$folder, \OC_Log::WARN);
		return null;
	}
	
	public static function dbGetNewServerForFolder($folder, $user_id, $currentServerId=null, $group=''){
		if(empty($currentServerId)){
			$currentServer = self::dbGetServerForFolder($folder, $user_id, false, $group);
			if(!empty($currentServer)){
				$urlParts = parse_url($currentServer);
				$currentServerId = self::dbLookupServerId($urlParts['host']);
			}
		}
		if(empty($currentServerId)){
			$currentServerId = self::dbLookupServerIdForUser($user_id, self::$USER_SERVER_PRIORITY_PRIMARY);
		}
		if(!empty($currentServerId)){
			$folder_site = self::dbGetSite($currentServerId);
		}
		if(empty($folder_site)){
			$folder_site = self::getMasterSite();
		}
		$folder_server_rows = self::dbGetServersForFolder($folder, $user_id, $group);
		$folder_servers = array_column($folder_server_rows, 'id');
		$all_servers = self::dbGetServersList();
		foreach($all_servers as $server){
			if($server['site']==$folder_site && !in_array($server['id'], $folder_servers) &&
					self::hasFreeSpace($server['id'])){
				return $server['id'];
			}
		}
		\OCP\Util::writeLog('files_sharding', 'WARNING: no server available for folder '.$folder, \OC_Log::WARN);
		return null;
	}
	
	public static function updateFree(){
		$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
		$free = @disk_free_space($dataDir);
		$total = @disk_total_space($dataDir);
		$minFree = self::getMinFree();
		// We expose the free space above the reserved minimum
		$free = $free - $minFree;
		if(self::isMaster()){
			$server_id = self::lookupServerId();
			return self::dbUpdateFree($total, $free, $server_id);
		}
		else{
			return self::ws('update_free', Array('total'=>$total, 'free'=>$free), true, true);
		}
	}
	
	public static function dbUpdateFree($total, $free, $server_id){
		$query = \OC_DB::prepare(
				'UPDATE `*PREFIX*files_sharding_servers` SET `total` = ?, `free` = ? WHERE `id` = ?');
		$result = $query->execute(Array($total, $free, $server_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not update free, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		return $result;
	}
	
	public function hasFreeSpace($server_id){
		$query = \OC_DB::prepare( "SELECT `free` FROM `*PREFIX*files_sharding_servers` WHERE `id` = ?" );
		$result = $query->execute(array($server_id));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get free, '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$row = $result->fetchRow();
		return(empty($row)?0:($row['free']>0));
	}

	/**
	 * Lookup home server for folder.
	 * @param string $folder path
	 * @param string $user_id
	 * @param boolean internal
	 * @param string $group
	 * @return URL (https://...)
	 */
	public static function getServerForFolder($folder, $user_id=null, $internal=false, $group=''){
		if(substr($folder, 0, 1)!=='/'){
			\OCP\Util::writeLog('files_sharding', 'Relative paths not allowed: '.$folder, \OC_Log::ERROR);
		}
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		// If I'm the master, look up in DB
		if(self::isMaster()){
			$server = self::dbGetServerForFolder($folder, $user_id, $internal, $group);
		}
		// Otherwise, ask master
		else{
			$server = self::ws('get_folder_server', Array('user_id' => $user_id, 'folder' => $folder,
					'internal'=>($internal?'yes':'no'), 'group' => $group), true, false);
		}
		return (empty($server)?null:$server);
	}
	
	public static function getNewServerForFolder($folder, $user_id=null, $currentServerId=null, $group=''){
		if(substr($folder, 0, 1)!=='/'){
			\OCP\Util::writeLog('files_sharding', 'Relative paths not allowed: '.$folder, \OC_Log::ERROR);
		}
		$user_id = $user_id==null?\OCP\USER::getUser():$user_id;
		// If I'm the master, look up in DB
		if(self::isMaster()){
			$server = self::dbGetNewServerForFolder($folder, $user_id, $currentServerId, $group);
		}
		// Otherwise, ask master
		else{
			$server = self::ws('get_new_folder_server', Array('user_id' => $user_id, 'folder' => $folder, 'group' => $group),
					true, false);
		}
		return $server;
	}
	
	
	/**
	 * Called when newfolder/mkcoll is attempted and checks if minfreegb is exceeded.
	 * If so, creates the folder locally and
	 * assigns the next server of this site as returned by getNewServerForFolder()
	 * to the folder in question.
	 * Otherwise just returns.
	 * @param unknown $folder
	 * @param unknown $user_id
	 */
	public static function setServerForFolder($folder, $user_id=null, $group=null){
		$maxUploadFileSize = -1;
		try{
			$storageStats = self::buildFileStorageStatistics($folder, $user_id, null, $group);
			$maxUploadFileSize = empty($storageStats['uploadMaxFilesize'])?-1:$storageStats['uploadMaxFilesize'];
		}
		catch(\Exception $e){
			\OCP\Util::writeLog('files_sharding',
					'A problem occurred while building file storage statistics '.$e->getMessage(),
					\OCP\Util::ERROR);
			$maxUploadFileSize = -1;
		}
		$serverUrl = self::getNewServerForFolder($folder, $user_id, $group);
		if(!empty($serverUrl)){
			$urlParts = parse_url($serverUrl);
			$serverID = self::lookupServerId($urlParts['host']);
			$minfree = self::getMinFree();
			if($minfree>=0 && $maxUploadFileSize>=0 && $maxUploadFileSize>$minfree){
				self::addDataFolder($folder, $group, $user_id, $serverID);
			}
		}
		return self::getServerForFolder($folder, $user_id, true, $group);
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
			$server = self::getMasterHostName();
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
					(((int)$row['priority'])===self::$USER_SERVER_PRIORITY_PRIMARY &&
							((int)$row['access'])===self::$USER_ACCESS_READ_ONLY ||
						((int)$row['priority'])>self::$USER_SERVER_PRIORITY_PRIMARY)){
				// Need to pass the storage ID, so the user gets the same on the backup server
				$loggedin_user = \OCP\USER::getUser();
				if(isset($loggedin_user) && $row['user_id']!=$loggedin_user){
					$old_user = self::switchUser($row['user_id']);
				}
				else{
					\OC_User::setUserId($row['user_id']);
					\OC_Util::setupFS($row['user_id']);
				}
				$storage = \OC\Files\Filesystem::getStorage('/'.$row['user_id'].'/');
				$storageId = $storage->getId();
				$numericStorageId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
				$row['numeric_storage_id'] = $numericStorageId;
				if(isset($old_user) && $old_user){
					self::restoreUser($old_user);
				}
				return($row);
			}
		}
		if(isset($old_user) && $old_user){
			self::restoreUser($old_user);
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
			$server = self::getMasterHostName();
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
	
	/**
	 * 
	 * @param unknown $user
	 * @param unknown $url
	 * @param unknown $dir path of synced directory, absolute or relative to owncloud data root
	 * @return boolean
	 */
	private static function syncDir($user, $url, $dir){
		$i = 0;
		$output=[];
		$ret = 0;
		// groups and/or usage dir may not exist (yet)
		if(!\OC\Files\Filesystem::file_exists($dir)){
			return 0;
		}
		do{
			if($i>=self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Syncing not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			
			$syncedFiles = exec("LANG=en_US.UTF-8 ".__DIR__."/sync_user.sh -u '".$user."' '".$dir."' ".$url." | grep 'Synced files:' | awk -F ':' '{printf \$NF}'",
					$output, $ret);
			
			if($ret==0){
				\OCP\Util::writeLog('files_sharding', 'Synced '.$syncedFiles.' files for '.$user.' from '.$url, \OC_Log::WARN);
			}
			elseif($ret==124){
				\OCP\Util::writeLog('files_sharding', 'Timeout while syncing files for '.$user.
						' from '.$url.' : '.implode("\n", $output), \OC_Log::ERROR);
				break;
			}
			else{
				\OCP\Util::writeLog('files_sharding', 'Problem syncing '.$syncedFiles.' files for '.$user.' from '.$url, \OC_Log::WARN);
			}
			++$i;
		}
		// Some may have files that change while sync is running - let's just trust rclone...
		while(!is_numeric($syncedFiles) /*|| is_numeric($syncedFiles) && $syncedFiles!=0*/);
		return /*$syncedFiles===0 && */$i<=self::$MAX_SYNC_ATTEMPTS;
	}
	
	/**
	 * syncUser - sync user files to the current server.
	 * That is either from his primary server to his secondary (backup server)
	 * or vice versa.
	 * The last case is indicated by $priority = $USER_SERVER_PRIORITY_PRIMARY.
	 * @param unknown $user
	 * @param unknown $priority
	 * @return URL of primary server of the user
	 */
	public static function syncUser($user, $priority) {
		$myServerId = self::lookupServerId();
		$servers = self::getServersList();
		$key = array_search($myServerId, array_column($servers, 'id'));
		if($servers[$key]['exclude_as_backup']==='yes'){
			return null;
		}
		$publicServerURL = self::getServerForUser($user, false,
				((int)$priority)===self::$USER_SERVER_PRIORITY_PRIMARY?self::$USER_SERVER_PRIORITY_BACKUP_1:
				self::$USER_SERVER_PRIORITY_PRIMARY);
		$serverURL = self::getServerForUser($user, true,
				((int)$priority)===self::$USER_SERVER_PRIORITY_PRIMARY?self::$USER_SERVER_PRIORITY_BACKUP_1:
				self::$USER_SERVER_PRIORITY_PRIMARY);
		if(empty($serverURL)){
			$serverURL = self::getMasterInternalURL();
		}
		$parse = parse_url($serverURL);
		$server = $parse['host'];
		if(empty($server)){
			\OCP\Util::writeLog('files_sharding', 'No server for user '.$user, \OC_Log::ERROR);
			return null;
		}
		$i = 0;
		$output = [];
		$ret = 0;
		$ok = false;
		\OCP\Util::writeLog('files_sharding', 'Syncing with command: '.
				__DIR__."/sync_user.sh -u '".$user."' -s ".$server, \OC_Log::WARN);
		do{
			if($i>=self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Syncing not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			
			$syncedFiles = exec("LANG=en_US.UTF-8 ".__DIR__."/sync_user.sh -u '".$user."' -s ".$server." | grep 'Synced files:' | awk -F ':' '{printf \$NF}'",
					$output, $ret);
			
			if($ret==0){
				\OCP\Util::writeLog('files_sharding', 'Synced '.$syncedFiles.' files for '.$user.' from '.$server, \OC_Log::WARN);
			}
			elseif($ret==124){
				\OCP\Util::writeLog('files_sharding', 'Timeout while syncing files for '.$user.
						' from '.$server.' : '.implode("\n", $output), \OC_Log::ERROR);
				break;
			}
			else{
				\OCP\Util::writeLog('files_sharding', 'Problem syncing '.$syncedFiles.' files for '.$user.' from '.$server, \OC_Log::WARN);
			}
			
			++$i;
		}
		// Some may have files that change while sync is running - let's just trust rclone for other cases than migration
		while($priority!=self::$USER_SERVER_PRIORITY_PRIMARY && !is_numeric($syncedFiles) ||
				$priority==self::$USER_SERVER_PRIORITY_PRIMARY && is_numeric($syncedFiles) && $syncedFiles!=0);
		
		$ret = null;
		$access = null;
		if(/*$syncedFiles==0 && */$i<=self::$MAX_SYNC_ATTEMPTS){
			// Set r/w if this is a new primary server. Here we insist that all files must be copied over.
			$ok = true;
			if($priority==self::$USER_SERVER_PRIORITY_PRIMARY && $syncedFiles==0){
				$access = self::$USER_ACCESS_ALL;
				// Get list of shared file mappings: ID -> path and update item_source on oc_share table on master with new IDs
				/*$ok = $ok &&*/ self::updateUserSharedFiles($user);
				// Get exported metadata (by path) via remote metadata web API and insert metadata on synced files by using local metadata web API
				// TODO: abstract this via a hook
				if(\OCP\App::isEnabled('meta_data')){
					include_once('metadata/lib/tags.php');
					$ok = $ok && \OCA\meta_data\Tags::updateUserFileTags($user, $serverURL);
				}
				// Get group folders in files_accounting from previous primary server
				if(\OCP\App::isEnabled('user_group_admin')){
					$ok = $ok && self::syncDir($user, $serverURL.'/remote.php/groupfolders',
							$user.'/user_group_admin');
				}
				// Get bills from previous primary server
				if(\OCP\App::isEnabled('files_accounting')){
					$ok = $ok && self::syncDir($user, $serverURL.'/remote.php/usage',
							$user.'/files_accounting');
				}
			}
			if($ok){
				$ret = $publicServerURL;
			}
		}
		$now = time();
		// Update last_sync in any case. If we only update on success, another attempt
		// will be made in 5 minutes. Ungortunate if the server times out because of load.
		// Let's give the server 24h to recover...
		self::setServerForUser($user, null, $priority, $access, $now);
		return $ret;
	}
	
	public static function deleteUser($user) {
		self::disableUser($user);
		$i = 0;
		do{
			\OCP\Util::writeLog('files_sharding', 'Deleting user '.$user, \OC_Log::WARN);
			if($i>self::$MAX_SYNC_ATTEMPTS){
				\OCP\Util::writeLog('files_sharding', 'ERROR: Deletion not working. Giving up after '.$i.' attempts.', \OC_Log::ERROR);
				break;
			}
			$remainingFiles = shell_exec(__DIR__."/delete_user.sh -u \"".$user." | grep 'Remaining files:' | awk -F ':' '{printf $NF}'");
			++$i;
		}
		while(!is_numeric($remainingFiles) || is_numeric($remainingFiles) && $remainingFiles!=0);
	}
	
	public static function disableUser($user) {
		return self::setServerForUser($user, null, self::$USER_SERVER_PRIORITY_DISABLED, self::$USER_ACCESS_NONE);
	}
	
	public static function enableUser($user) {
		// First check if user has been disabled
		$currentServerInfo = self::getUserServerInfo($user, self::$USER_SERVER_PRIORITY_DISABLED);
		if(empty($currentServerInfo)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: Cannot enable non-disabled or non-existing user', \OC_Log::ERROR);
			return false;
		}
		return self::setServerForUser($user, $currentServerInfo['id'], self::$USER_SERVER_PRIORITY_PRIMARY, self::$USER_ACCESS_ALL);
	}
	
	public static function updateUserSharedFiles($user_id){
		$loggedin_user = \OCP\USER::getUser();
		if(isset($user_id)){
			if(isset($loggedin_user) && $user_id!=$loggedin_user){
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
		// Get all files/folders shared by user
		$sharedItems = self::getItemsSharedByUser($user_id);
		// Correction array to send to master
		$newIdMap = array('user_id'=>$user_id);
		foreach($sharedItems as $share){
			$path = $share['path'];
			// Get files/folders owned by user (locally) with the path of $share
			$file = self::dbGetUserFile('files'.$path, $user_id);
			\OCP\Util::writeLog('files_sharding', 'Share: '.'files'.$path.'-->'.$share['item_source'].'!='.$file['fileid'], \OC_Log::WARN);
			// If empty, file syncing probably failed - back off
			if(!empty($file) && $share['item_source']!=$file['fileid']){
				$newIdMap[$share['item_source']] = $file['fileid'];
			}
		}
		if(isset($old_user) && $old_user){
			self::restoreUser($old_user);
		}
		// Send the correction array to master
		$ret = self::ws('update_share_item_sources', $newIdMap);
		if($ret===null || $ret===false){
			\OCP\Util::writeLog('files_sharding', 'updateUserSharedFiles error', \OC_Log::ERROR);
			return false;
		}
		return true;
	}
	
	public static function setPasswordHash($user_id, $pwHash) {
		$oldHash = self::dbGetPwHash($user_id);
		if(!$oldHash && $oldHash!==""){
			$query = \OC_DB::prepare('INSERT INTO `*PREFIX*users` (`uid`, `password`) VALUES (?, ?)');
			$result = $query->execute(array($user_id, $pwHash));
		}
		else{
			$query = \OC_DB::prepare('UPDATE `*PREFIX*users` SET `password` = ? WHERE `uid` = ?');
			$result = $query->execute(array($pwHash, $user_id));
		}
		return $result ? true : false;
	}
	
	public static function setNumericStorageID($user_id, $numericId) {
		$query = \OC_DB::prepare('UPDATE `*PREFIX*storages` SET `numeric_id` = ? WHERE `id` = ?');
		$result = $query->execute(array($numericId, 'home::'.$user_id));
		return $result ? true : false;
	}
	
	public static function getPasswordHash($user_id, $serverURL=null){
		if($serverURL==null){
			$serverURL = self::getMasterInternalURL();
		}
		if(self::isMaster()/* || self::onServerForUser($user_id)*/){
			return self::dbGetPwHash($user_id);
		}
		else{
			$res = self::ws('get_pw_hash', array('user_id'=>$user_id), true, true, $serverURL);
			if(!empty($res['error'])){
				\OC_Log::write('files_sharding',"Password error. ".serialize($res), \OC_Log::WARN);
			}
			if(empty($res['pw_hash'])){
				\OC_Log::write('files_sharding',"No password returned for user ".$user_id.": ".serialize($res), \OC_Log::WARN);
			}
			return $res['pw_hash'];
		}
	}
	
	public static function dbGetPwHash($user_id){
		$query = \OC_DB::prepare( "SELECT `password` FROM `*PREFIX*users` WHERE `uid` = ?" );
		$result = $query->execute( array($user_id))->fetchRow();
		if(!$result){
			return $result;
		}
		return $result['password'];
	}
	
	public static function getOneTimeToken($user_id, $forceNew=false){
		if($forceNew || !apc_exists(self::$SECOND_FACTOR_CACHE_KEY_PREFIX.$user_id)){
			$token = md5($user_id . time ());
			apc_store(self::$SECOND_FACTOR_CACHE_KEY_PREFIX.$user_id, $token, 10*60 /*keep 10 minutes*/);
		}
		else{
			$token = apc_fetch(self::$SECOND_FACTOR_CACHE_KEY_PREFIX.$user_id);
		}
		return $token;
	}
	
	public static function checkOneTimeToken($user_id, $token){
		$storedToken = apc_fetch(self::$SECOND_FACTOR_CACHE_KEY_PREFIX.$user_id);
		return $token===$storedToken;
	}
	
	public static function emailOneTimeToken($user_id, $token){
		try{
			$email = \OCP\Config::getUserValue($user_id, 'settings', 'email');
			$displayName = \OCP\User::getDisplayName($user_id);
			//$systemFrom = \OCP\Config::getSystemValue('fromemail', '');
			$systemFrom = \OCP\Util::getDefaultEmailAddress('no-reply');
			$defaults = new \OCP\Defaults();
			$senderName = $defaults->getName();
			$subject = "Two-factor token";
			$message = $token;
			\OCP\Util::sendMail($email, $displayName, $subject, $message, $systemFrom, $senderName);
			return true;
		}
		catch(\Exception $e){
			\OCP\Util::writeLog('User_Group_Admin',
					'A problem occurred while sending the e-mail to '.$email.'. Please revisit your settings.',
					\OCP\Util::ERROR);
			return false;
		}
	}
	
	public static function getUserHomeServerAccess($userId){
		$serverInfo = self::getUserServerInfo($userId);
		if(empty($serverInfo)){
			return self::$USER_ACCESS_ALL;
		}
		$serverId = $serverInfo['id'];
		$access = self::getUserServerAccess($serverId, $userId);
		return $access;
	}
	
	public static function checkAdminCert(){
		if(!empty($_SERVER['SSL_CLIENT_VERIFY']) &&
				($_SERVER['SSL_CLIENT_VERIFY']=='SUCCESS' || $_SERVER['SSL_CLIENT_VERIFY']=='NONE')){
					//$issuerDN = !empty($_SERVER['SSL_CLIENT_I_DN'])?$_SERVER['SSL_CLIENT_I_DN']:
					(!empty($_SERVER['REDIRECT_SSL_CLIENT_I_DN'])?$_SERVER['REDIRECT_SSL_CLIENT_I_DN']:'');
					$clientDN = !empty($_SERVER['SSL_CLIENT_S_DN'])?$_SERVER['SSL_CLIENT_S_DN']:
					(!empty($_SERVER['REDIRECT_SSL_CLIENT_S_DN'])?$_SERVER['REDIRECT_SSL_CLIENT_S_DN']:'');
					$servers = self::getServersList();
					$clientDNArr = self::tokenizeDN($clientDN);
					foreach($servers as $server){
						if(empty($server['x509_dn'])){
							continue;
						}
						$serverDNArr = self::tokenizeDN($server['x509_dn']);
						\OC_Log::write('files_sharding','Checking subject '.$server['x509_dn'].
								'<->'.$clientDN, \OC_Log::INFO);
						if($serverDNArr==$clientDNArr){
							\OC_Log::write('files_sharding','Subject '.$server['x509_dn'].' OK', \OC_Log::INFO);
							return true;
						}
					}
		}
		return false;
	}
	
	public static function tokenizeDN($dn_){
		$ret = [];
		try{
			$dn = trim($dn_);
			if(substr($dn, 0, 1)=="/"){
				$dnArr = explode("/", $dn);
			}
			else{
				$dnArr = explode(",", $dn);
			}
			foreach($dnArr as $el){
				if(empty($el)){
					continue;
				}
				if(strpos($el, "=")===false){
					\OC_Log::write('files_sharding', 'WARNING: could not parse DN '.$el, \OC_Log::WARN);
					continue;
				}
				$keyVal = explode("=", trim($el));
				$ret[trim($keyVal[0])] = trim($keyVal[1]);
			}
		}
		catch(\Exception $e){
			\OC_Log::write('files_sharding', 'ERROR: could not parse DN '.$dn_.'.'.$e->getMessage(), \OC_Log::ERROR);
		}
		return $ret;
	}
	
	/**
	 * Check that the requesting IP address is allowed to get confidential
	 * information.
	 * UPDATE: now alternatively checks client certificate instead.
	 */
	public static function checkIP(){
		
		if(self::checkAdminCert()){
			return true;
		}
		
		if(self::$trustednets===null){
			$tnet = \OCP\Config::getSystemValue('trustednet', '');
			$tnet = trim($tnet);
			$tnets = explode(' ', $tnet);
			self::$trustednets = array_map('trim', $tnets);
			if(count(self::$trustednets)==1 && substr(self::$trustednets[0], 0, 8)==='TRUSTED_'){
				self::$trustednets = [];
			}
		}
		foreach(self::$trustednets as $trustednet){
			if(strpos($_SERVER['REMOTE_ADDR'], $trustednet)===0){
				\OC_Log::write('files_sharding', 'Remote IP '.$_SERVER['REMOTE_ADDR'].' OK', \OC_Log::DEBUG);
				return true;
			}
		}
		\OC_Log::write('files_sharding', 'Remote IP '.$_SERVER['REMOTE_ADDR'].
				' not trusted --> '.$_SERVER['REQUEST_URI'], \OC_Log::WARN);
		return false;
	}
	
	/**
	 * On the master, item_source is the fileid of the item slave,
	 * file_source is the fileid of the dummy item created on the master.
	 * 
	 * @param unknown $itemSource
	 * @param string $itemType
	 * @param boolean $sharedWithMe
	 * @return unknown
	 */
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
	
	public static function getItemSource($fileSource, $itemType='file', $sharedWithMe=false) {
		if($sharedWithMe){
			$master_to_slave_id_map = \OCP\Share::getItemsSharedWith($itemType);
		}
		else{
			$master_to_slave_id_map = \OCP\Share::getItemsShared($itemType);
		}
		foreach($master_to_slave_id_map as $item1=>$data1){
			if($master_to_slave_id_map[$item1]['file_source'] == $fileSource){
				$ret = $master_to_slave_id_map[$item1]['item_source'];
				return $ret;
			}
		}
		return $fileSource;
	}
	
	/**
	 * 
	 * @param unknown $user_id
	 * @param unknown $itemSource - The file ID on the home/slave server hosting
	 *                               the physical file
	 * @param string $itemType
	 * @return boolean permissions if the user has access to the file, otherwise false
	 */
	public static function checkAccess($user_id, $itemSource, $itemType=null){
		if(empty($user_id) || empty($itemSource)){
			return false;
		}
		$cache_key = $user_id.':'.$itemSource.':'.(!empty($itemType)?$itemType:'');
		if(apc_exists($cache_key)){
			$response = apc_fetch($cache_key);
			return $response;
		}
		$ret = false;
		\OCP\Util::writeLog('files_sharding', 'Getting shared items for '.$user_id, \OC_Log::WARN);
		$user = self::switchUser($user_id);
		$itemsSharedWithUser = self::getItemsSharedWithUser($user_id);
		// TODO: consider using \OCP\Share::getUsersSharingFile instead
		foreach($itemsSharedWithUser as $data){
			\OCP\Util::writeLog('files_sharding', 'Checking access of '.$user_id. ' to '.
					$itemSource.'<->'.$data['itemsource'], \OC_Log::INFO);
			if((int)$data['itemsource'] === (int)$itemSource){
				//$ret = true;
				\OCP\Util::writeLog('files_sharding', 'DATA: '.serialize($data), \OC_Log::WARN);
				$ret = $data['permissions'];
				break;
			}
		}
		self::restoreUser($user);
		apc_add($cache_key, $ret, 20);
		return $ret;
	}
	
	public static function checkAccessRecursively($user_id, $itemSource, $owner, $group='',
			$path=''){
		$user = self::switchUser($owner);
		if(empty($itemSource) && !empty($path)){
			$itemSource = self::getFileId($path, $owner, $group);
		}
		$ret = false;
		while(!empty($itemSource) && $itemSource!=-1){
			$fileInfo = self::getFileInfo(null, $owner, $itemSource, null, $user_id, $group);
			$fileType = $fileInfo->getType()===\OCP\Files\FileInfo::TYPE_FOLDER?'folder':'file';
			if(empty($fileInfo['parent']) || $itemSource==$fileInfo['parent'] || empty($fileInfo['path'])){
				break;
			}
			$res = self::checkAccess($user_id, $itemSource/*$fileInfo->getId()*/, $fileType);
			if(!empty($res)){
				$ret = $res;
				break;
			}
			\OC_Log::write('files_sharding', 'Parent: '.$itemSource.'-->'.$fileInfo['fileid'].'-->'.$fileInfo['parent'], \OC_Log::WARN);
			$itemSource = $fileInfo['parent'];
		}
		self::restoreUser($user);
		return $ret;
	}
	
	public static function getItemsSharedWithUser($user_id, $itemType=null){
		if(self::isMaster()){
			if(empty($itemType)){
				$sharedFiles = \OCP\Share::getItemsSharedWithUser('file', $user_id, \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS);
				$sharedFolders = \OCP\Share::getItemsSharedWithUser('folder', $user_id, \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS);
			}
			else{
				$sharedFiles = \OCP\Share::getItemsSharedWithUser($itemType, $user_id, \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS);
				$sharedFolders = array();
			}
			foreach($sharedFiles as &$item){
				$item['itemsource'] = self::getshareItemSource($item['fileid']/*file_source*/);
			}
			foreach($sharedFolders as &$item){
				$item['itemsource'] = self::getshareItemSource($item['fileid']/*file_source*/);
			}
		}
		else{
			if(empty($itemType)){
				$sharedFiles =  self::ws('getItemsSharedWithUser',
						array('itemType' => 'file', 'user_id' => $user_id, 'shareWith' => $user_id, 'format' => \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS));
				$sharedFolders =  self::ws('getItemsSharedWithUser',
						array('itemType' => 'folder', 'user_id' => $user_id, 'shareWith' => $user_id, 'format' => \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS));
			}
			else{
				$sharedFiles =  self::ws('getItemsSharedWithUser',
						array('itemType' => $itemType, 'user_id' => $user_id, 'shareWith' => $user_id, 'format' => \OC_Shard_Backend_File::FORMAT_GET_FOLDER_CONTENTS));
				$sharedFolders = array();
			}
		}
		$result = array();
		if(!empty($sharedFiles)){
			$result = array_unique(array_merge($result, $sharedFiles), SORT_REGULAR);
		}
		if(!empty($sharedFolders)){
			$result = array_unique(array_merge($result, $sharedFolders), SORT_REGULAR);
		}
		return $result;
	}
	
	/**
	 * Get all items shared by user.
	 * @param $user_id
	 * @return array
	 */
	public static function getItemsSharedByUser($user_id){
		if(self::isMaster()){
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
			foreach($ret as &$share){
				$path = \OC\Files\Filesystem::getPath($ret['file_source']);
				$ret['path'] = $path;
			}
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
	
	public static function rename($owner, $id, $dir, $name, $newname, $group='', $inStorage=''){
		$user_id = \OCP\USER::getUser();
		if($owner && $owner!==$user_id){
			\OC_Util::teardownFS();
			if(!empty($group)){
				$groupDir = '/'.$owner.'/user_group_admin/'.$group;
				\OC\Files\Filesystem::init($owner, $groupDir);
			}
			//\OC\Files\Filesystem::initMountPoints($owner);
			\OC_User::setUserId($owner);
			\OC_Util::setupFS($owner);
			\OCP\Util::writeLog('files_sharding', 'Owner: '.$owner.', user: '.\OCP\USER::getUser(), \OC_Log::WARN);
		}
		else{
			if(!empty($group)){
				\OC\Files\Filesystem::tearDown();
				$groupDir = '/'.$user_id.'/user_group_admin/'.$group;
				\OC\Files\Filesystem::init($user_id, $groupDir);
			}
			unset($user_id);
		}
		if($inStorage){
			\OC_Util::teardownFS();
			\OC\Files\Filesystem::init(\OCP\USER::getUser(), '/'.\OCP\USER::getUser().'/files_external/storage/');
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
	
	public static function deleteFileShare($owner, $id){
		
		if(empty($owner)){
			\OCP\Util::writeLog('files_sharing','ERROR: no owner given.'.':'.$id, \OCP\Util::WARN);
			return false;
		}
		if(empty($id)){
			\OCP\Util::writeLog('files_sharing','ERROR: No ID given: '.$owner, \OCP\Util::WARN);
			return false;
		}

		\OC_Log::write('OCP\Share', 'Deleting share: '.$id.':'.$owner, \OC_Log::WARN);
		
		$result = false;

		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*share` WHERE `uid_owner` = ? AND `item_source` = ?');
		$result = $query->execute(array($owner, $id));
		if($result === false) {
			\OC_Log::write('OCP\Share', 'Couldn\'t update share table for '.$owner.' --> '.$id, \OC_Log::ERROR);
		}

		return $result;
	}
	
	// From https://stackoverflow.com/questions/7497733/how-can-i-use-php-to-check-if-a-directory-is-empty
	private function dir_is_empty($dir) {
		$handle = \OC\Files\Filesystem::opendir($dir);
		while (false !== ($entry = \OC\Files\Filesystem::readdir($handle))) {
			if ($entry != "." && $entry != "..") {
				//closedir($handle);
				return false;
			}
		}
		//closedir($handle);
		return true;
	}
	
	/**
	 * Delete the fake file/directory that was created on the master on sharing.
	 * @param unknown $owner
	 * @param unknown $path
	 * @param unknown $group
	 * @return boolean
	 */
	public static function deleteFileShareTarget($owner, $path, $group){
		$ret = true;
		if(!empty($group)){
			\OC\Files\Filesystem::tearDown();
			$groupDir = '/'.$owner.'/user_group_admin/'.$group;
			\OC\Files\Filesystem::init($owner, $groupDir);
		}
		else{
			\OC\Files\Filesystem::tearDown();
			$homeDir = '/'.$owner.'/files/';
			\OC\Files\Filesystem::init($owner, $homeDir);
		}
		if(\OC\Files\Filesystem::file_exists($path) &&
				(\OC\Files\Filesystem::is_file($path) ||  self::dir_is_empty($path))){
					\OC_Log::write('OCP\Share', 'Deleting file/dir '.$owner.' --> '.$path.' --> '.$group, \OC_Log::WARN);
			if(!empty(trim($path)) && trim($path)!="/"){
				// Deleting the fake file/directory triggers the hook again, thus creating an infinite loop.
				// Unless we first disable our hook:
				\OC_Hook::clear('OC_Filesystem', 'delete');
				\OC_Hook::clear('OC_Filesystem', 'post_delete');
				$ret = \OC\Files\Filesystem::unlink($path);
				// with the below approach, we'd need to manually clear the db record in oc_filecache
				/*$view = \OC\Files\Filesystem::getView();
				$tank_dir = \OCP\Config::getSystemValue('datadirectory', '');
				$absPath = $view->getAbsolutePath($path);
				$fullPath = $tank_dir .$absPath;
				unlink($fullPath);*/
			}
		}
		return $ret;
	}
	
	public static function getShareFileTarget($item_source){
		$query = \OC_DB::prepare('SELECT `file_target` FROM `*PREFIX*share` WHERE `item_source` = ?');
		$result = $query->execute(array($item_source));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get share file target for '.$item_source.
					', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$row = $result->fetchRow();
		return($row['file_target']);
	}
	
	
	public static function getShareItemSource($file_source){
		$query = \OC_DB::prepare('SELECT `item_source` FROM `*PREFIX*share` WHERE `file_source` = ?');
		$result = $query->execute(array($file_source));
		if(\OCP\DB::isError($result)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not get share item source for '.$file_source.
					', '.\OC_DB::getErrorMessage($result), \OC_Log::ERROR);
		}
		$row = $result->fetchRow();
		return($row['item_source']);
	}

	public static function switchUser($owner, $force=false){
		$user_id = \OC_User::getUser();
		\OCP\Util::writeLog('files_sharding', 'Logged in: '.\OC_User::isLoggedIn().', user: '.$user_id.', owner: '.$owner." : ".$_SERVER['REQUEST_URI'], \OC_Log::WARN);
		if(($owner || $force) && $owner!==$user_id){
			\OC_Util::teardownFS();
			//\OC\Files\Filesystem::initMountPoints($owner);
			//\OC::$session->reopen();
			$session_id = session_id();
			$instanceId = \OC_Config::getValue('instanceid', null);
			//setcookie($instanceId, "", time() - 3600);
			//unset($_COOKIE[$instanceId]);
			//session_destroy();
			\OC_User::setUserId($owner);
			\OC_Util::setupFS($owner);
			\OCP\Util::writeLog('files_sharding', 'Owner: '.$owner.', user: '.
					\OCP\USER::getUser().' : '.$instanceId.' --> '.$_COOKIE[$instanceId].' : '.$session_id.' : '.session_id().' : '.(empty(\OC_User::getUserSession()->getUser())?'':\OC_User::getUserSession()->getUser()->getUID()), \OC_Log::WARN);
			return $user_id;
		}
		else{
			return null;
		}
	}
	
	public static function restoreUser($user_id, $force=false){
		if(!$force && $user_id==\OCP\USER::getUser()){
			return;
		}
		// If not done, the user shared with will now be logged in as $owner
		try{
			\OC_Util::teardownFS();
			\OC_User::setUserId($user_id);
			\OC_Util::setupFS($user_id);
			session_write_close();
		}
		catch(\Exception $e){
			\OCP\Util::writeLog('files_sharding', 'Could not restore user '.$user_id.'. '.$e.getTraceAsString(), \OC_Log::WARN);
		}
	}

	public static function unserialize($session_data) {
		$method = ini_get("session.serialize_handler");
		switch ($method) {
			case "php":
				return self::unserialize_php($session_data);
				break;
			case "php_binary":
				return self::unserialize_phpbinary($session_data);
				break;
			default:
				throw new Exception("Unsupported session.serialize_handler: " . $method . ". Supported: php, php_binary");
		}
	}
	
	private static function unserialize_php($session_data) {
		$return_data = array();
		$offset = 0;
		while ($offset < strlen($session_data)) {
			if (!strstr(substr($session_data, $offset), "|")) {
				throw new Exception("invalid data, remaining: " . substr($session_data, $offset));
			}
			$pos = strpos($session_data, "|", $offset);
			$num = $pos - $offset;
			$varname = substr($session_data, $offset, $num);
			$offset += $num + 1;
			$data = unserialize(substr($session_data, $offset));
			$return_data[$varname] = $data;
			$offset += strlen(serialize($data));
		}
		return $return_data;
	}
	
	private static function unserialize_phpbinary($session_data) {
		$return_data = array();
		$offset = 0;
		while ($offset < strlen($session_data)) {
			$num = ord($session_data[$offset]);
			$offset += 1;
			$varname = substr($session_data, $offset, $num);
			$offset += $num;
			$data = unserialize(substr($session_data, $offset));
			$return_data[$varname] = $data;
			$offset += strlen(serialize($data));
		}
		return $return_data;
	}
	
	
	// Get logged-in user session on master
	public static function getUserSession($sessionId, $forceLogout=true){
		include_once('files_sharding/lib/filesessionhandler.php');
		$handler = new FileSessionHandler('/tmp');
		$encoded_session = $handler->getSession($sessionId, $forceLogout);
		if(!empty($encoded_session)){
			$session = self::unserialize($encoded_session);
			\OC_Log::write('files_sharding', 'Session '.serialize($session), \OC_Log::WARN);
			return $session;
		}
		else{
			return null;
		}
	}

	public static function getFileInfo($path, $owner, $id, $parentId='', $user= '', $group='', $inStorage=false){
		$info = null;
		
		$user = empty($user)?\OC_User::getUser():$user;
		
		//if(($id || $parentId) && $owner){
			// For a shared directory get info from server holding the data
			if(!empty($owner) && !self::onServerForUser($owner)){
				$dataServer = self::getServerForUser($owner, true);
				if(!$dataServer){
					$dataServer = self::getMasterInternalURL();
				}
				if($id){
					$data = self::ws('getFileInfoData',
							array('user_id' => $user, 'path'=>urlencode($path), 'id'=>$id, 'owner'=>$owner,
									'group'=>urlencode($group)),
							false, true, $dataServer);
				}
				elseif($parentId){
					$parentData = self::ws('getFileInfoData',
							array('user_id' => $user, 'id'=>$parentId, 'owner'=>$owner,
									'group'=>urlencode($group)),
							false, true, $dataServer);
					$dirPath = preg_replace('|^files/|','/', $parentData['internalPath']);
					if(!empty($group)){
						$dirPath = preg_replace('|^user_group_admin/[^/]+/|','/', $parentData['internalPath']);
					}
					$pathinfo = pathinfo($path);
					$data = self::ws('getFileInfoData',
							array('user_id' => $user, 'path'=>urlencode($dirPath.'/'.$pathinfo['basename']), 'owner'=>$owner,
									'group'=>urlencode($group)),
							false, true, $dataServer);
				}
				else{
					$data = self::ws('getFileInfoData',
							array('user_id' => $user, 'path'=>urlencode($path), 'owner'=>$owner,
									'group'=>urlencode($group)),
							false, true, $dataServer);
				}
				if($data){
					//$configDataDirectory = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT."/data");
					//\OC\Files\Filesystem::mount('\OC\Files\Storage\Local', array('datadir'=>$configDataDirectory), '/');
					//$storage = \OC\Files\Filesystem::getStorage($data['path']);
					include_once('files_sharding/lib/shardedstorage.php');
					$storage = new \OC\Files\Storage\Sharded(array('userid'=>$user));
					$info = new \OC\Files\FileInfo($data['path'], $storage, $data['internalPath'], $data);
					\OCP\Util::writeLog('files_sharding', 'Returning file info for '.$data['path'].'-->'.serialize($data), \OC_Log::INFO);
				}
			}
			else{
				if(!empty($owner) && $owner!=\OC_User::getUser()){
					$user_id = self::switchUser($owner);
				}
				if(!empty($group)){
					//$user_id = !empty($user_id)?$user_id:$user;
					$groupOwner = \OC_User::getUser();
					\OCP\Util::writeLog('files_sharding', 'Using group '.$owner.':'.$groupOwner.':'.$group, \OC_Log::WARN);
					\OC\Files\Filesystem::tearDown();
					$groupDir = '/'.$groupOwner.'/user_group_admin/'.$group;
					\OC\Files\Filesystem::init($groupOwner, $groupDir);
				}
				if($inStorage){
					\OC_Util::teardownFS();
					\OC\Files\Filesystem::init(\OC_User::getUser(), '/'.\OC_User::getUser().'/files_external/storage/');
				}
				if(!empty($id)){
					$path = \OC\Files\Filesystem::getPath($id);
				}
				elseif(!empty($parentId)){
					$parentPath = \OC\Files\Filesystem::getPath($parentId);
					$path = $parentPath . '/' . basename($path);
				}
				\OCP\Util::writeLog('files_sharding', 'Getting info for '.$parentId.':'.$id.':'.$path.':'.
						\OC_User::getUser().':'.$owner.':'.session_status(), \OC_Log::INFO);
				$info = \OC\Files\Filesystem::getFileInfo($path);
			}
		//}
		/*else{
			// For non-shared directories, file information is kept on the slave
			if(!empty($owner) && $owner!=$user){
				$user_id = self::switchUser($owner);
			}
			if(!empty($group)){
				$user_id = !empty($user_id)?$user_id:$user;
				$groupOwner = \OC_User::getUser();
				\OCP\Util::writeLog('files_sharding', 'Using group '.$groupOwner.':'.$group, \OC_Log::WARN);
				\OC\Files\Filesystem::tearDown();
				$groupDir = '/'.$groupOwner.'/user_group_admin/'.$group;
				\OC\Files\Filesystem::init($groupOwner, $groupDir);
			}
			if($id){
				$path = \OC\Files\Filesystem::getPath($id);
			}
			\OCP\Util::writeLog('files_sharding', 'Getting info for '.$user.':'.\OC\Files\Filesystem::getRoot().
					':'.$path, \OC_Log::WARN);
			$info = \OC\Files\Filesystem::getFileInfo($path);
		}*/
		
		if(isset($user_id) && $user_id){
			self::restoreUser($user_id);
		}
		if(!empty($group) && self::onServerForUser($owner)){
			\OC\Files\Filesystem::tearDown();
			// When this is caused by a user accessing a shared group file via webdav on a server
			// where he does not exist, it will fail with the exception 'Backends provided no user object for' ...
			try{
				\OC\Files\Filesystem::init(\OC_User::getUser(), "/".\OC_User::getUser()."/files");
			}
			catch(\OC\User\NoUserException $e){
			}
		}
		
		\OCP\Util::writeLog('files_sharding', 'User/info: '.\OC_User::getUser().':'.$user.':'.
				$owner.'/'.(empty($info)?'':$info->getPath()), \OC_Log::WARN);
		
		return $info;
	}
	
	public static function moveTmpFile($tmpFile, $path, $dirOwner, $dirId, $group='', $inStorage=false){
		$endPath = $path;
		$user = \OCP\USER::getUser();
		if($dirId){
			$dirMeta = self::getFileInfo(null, $dirOwner, $dirId, null, '', $group);
			$dirPath = preg_replace('|^files/|','/', $dirMeta->getInternalPath());
			$dirPath = preg_replace('|^user_group_admin/[^/]*/|','/', $dirPath);
			$endPath = $dirPath.'/'.basename($path);
		}
		if(self::inDataFolder($endPath, $user, $group)){
			$dataServer = self::getServerForFolder($endPath, $user, true);
		}
		if(empty($dataServer) && $dirOwner){
			if(self::inDataFolder($endPath, $dirOwner, $group)){
				$dataServer = self::getServerForFolder($endPath, $dirOwner, true);
			}
			// For a shared directory send data to server holding the directory
			if(empty($dataServer) && !self::onServerForUser($dirOwner)){
				$dataServer = self::getServerForUser($dirOwner, true);
				if(!$dataServer){
					$dataServer = self::getMasterInternalURL();
				}
			}
		}
		\OCP\Util::writeLog('files_sharding', 'Data server '.$dataServer.':'.$group.':'.$user.':'.$dirOwner, \OC_Log::WARN);
		if(!empty($dataServer)){
			return self::putFile($tmpFile, $dataServer, $dirOwner, $endPath, $group);
		}
		else{
			if($dirOwner!=\OCP\USER::getUser()){
				$user_id = self::switchUser($dirOwner);
			}
			if(!empty($group)){
				\OC\Files\Filesystem::tearDown();
				$groupDir = '/'.$dirOwner.'/user_group_admin/'.$group;
				\OC\Files\Filesystem::init($dirOwner, $groupDir);
			}
			if($inStorage){
				//\OC_Util::teardownFS();
				\OC\Files\Filesystem::init($user, '/'.$user.'/files_external/storage/');
				//$manager = \OC\Files\Filesystem::getMountManager();
				//$mount = $manager->find('/'.$user.'/files_external/storage/');
				//$storage = $mount->getStorage();
				\OCP\Util::writeLog('files_sharding', 'Moving tmp file to '.\OC\Files\Filesystem::getRoot().':'.$endPath, \OC_Log::WARN);
			}
		}
		// This triggers writeHook() from files_sharing, which calls correctFolders(), ..., getFileInfo(),
		// which fails when in group folders.
		if(empty($group)){
			$ret = \OC\Files\Filesystem::fromTmpFile($tmpFile, $endPath);
		}
		else{
			$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
			$fullEndPath = $dataDir.'/'.
			//\OCP\USER::getUser().'/'.
			// When uploading to a directory shared from a group folder, $dirOwner will be set and getRoot will include the userid
			(empty($dataServer)&&!empty($groupDir)&&empty($dirOwner)?\OCP\USER::getUser().'/':'/').
				trim(\OC\Files\Filesystem::getRoot(), '/').'/'.trim($endPath, '/');
			\OCP\Util::writeLog('files_sharding', 'Moving tmp file: '.$dirOwner.'->'.$groupDir.'->'.$tmpFile.'->'.$dataDir.'->'.$endPath.'->'.
			\OC\Files\Filesystem::getRoot().'->'.$fullEndPath.':'.file_exists($tmpFile).':'.\OCP\USER::getUser(), \OC_Log::WARN);
			try{
				$ret = rename($tmpFile, $fullEndPath);
			}
			catch(\Exception $e){
				\OCP\Util::writeLog('files_sharding', 'ERROR moving tmp file: '.$e.getTraceAsString(), \OC_Log::ERROR);
			}
			finally{
				self::restoreUser($user);
				if($inStorage){
				}
			}
		}
		
		if(isset($user_id) && $user_id){
			self::restoreUser($user_id);
		}
		
		return $ret;
		
	}

	public static function putFile($tmpFile, $dataServer, $dirOwner, $path, $group=''){
		
		$url = $dataServer .
		(\OCP\App::isEnabled('user_group_admin')?(empty($group)?(\OC::$WEBROOT.'/remote.php/mydav/'):'/groupfolders/'.rawurlencode($group).'/'):
				(\OC::$WEBROOT.'/remote.php/webdav/')) .
			implode('/', array_map('rawurlencode', explode('/', $path)));
		
			\OCP\Util::writeLog('files_sharding', 'PUTTING '.$dirOwner.':'.$tmpFile.':'.filesize($tmpFile).'-->'.$url, \OC_Log::WARN);
		
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
		
		if(!empty(self::getWSCert())){
			\OCP\Util::writeLog('files_sharding', 'Authenticating '.$url.' with cert '.self::$wsCert.
					' and key '.self::$wsKey, \OC_Log::INFO);
			curl_setopt($curl, CURLOPT_CAINFO, self::$wsCACert);
			curl_setopt($curl, CURLOPT_SSLCERT, self::$wsCert);
			curl_setopt($curl, CURLOPT_SSLKEY, self::$wsKey);
			//curl_setopt($curl, CURLOPT_SSLCERTPASSWD, '');
			//curl_setopt($curl, CURLOPT_SSLKEYPASSWD, '');
		}
		
		$res = curl_exec ($curl);
		fclose($fh);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if($status===0 || $status>=300){
			\OCP\Util::writeLog('files_sharding', 'ERROR: could not put. '.$url.':'.$status.':'.$res, \OC_Log::ERROR);
			return null;
		}
		return true;
	}
	
	public static function buildFileStorageStatistics($dir, $owner=null, $id=null, $group=null){
		//return Array('uploadMaxFilesize' => -1);
		//return \OCA\Files\Helper::buildFileStorageStatistics($dir);
		
		$user = \OCP\USER::getUser();
		/*if(empty($user)){
			$groupDir = '/'.$owner.'/user_group_admin/'.$group;
			\OC\Files\Filesystem::init($owner, $groupDir);
			$user = \OCP\USER::getUser();
		}
		if(empty($user)){
			\OCP\Util::writeLog('files_sharding', 'ERROR: cannot proceed as nobody', \OCP\Util::ERROR);
			return array();
		}*/
		$group_dir_owner = $user;
		
		if(!empty($owner)&& $owner!=$user){
			$old_user = $user;
			$user = $owner;
			\OC_User::setUserId($owner);
			\OC_Util::setupFS($owner);
			$group_dir_owner = $owner;
		}
		
		if(!empty($id)){
			// Check if this shared dir originates in a group dir
			$dir = \OC\Files\Filesystem::getPath($id);
			$glen = strlen('/user_group_admin/');
			if(\OCP\App::isEnabled('user_group_admin') &&
					substr($dir, 0, $glen)==='/user_group_admin/'){
				$gIndex = strpos($dir, '/', $glen);
				$group = substr($dir, $glen, $gIndex-$glen);
			}
		}
		
		if(\OCP\App::isEnabled('user_group_admin') && !empty($group)){
			$groupInfo = \OC_User_Group_Admin_Util::getGroupInfo($group);
			//$group_dir_owner = $groupInfo['owner'];
			$groupUserFreequota = !empty($groupInfo['user_freequota'])?$groupInfo['user_freequota']:0;
			\OC\Files\Filesystem::tearDown();
			$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
			\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
		}
		
		$l = new \OC_L10N('files_sharding');
		
		// information about storage capacities
		//$storageInfo = \OC_Helper::getStorageInfo($dir);
		if(\OCP\App::isEnabled('files_accounting')){
			$personalStorage = \OCA\Files_Accounting\Storage_Lib::personalStorage($user, empty($group), $group);
			$free = (int)$personalStorage['free_space'];
			$used = ((int)$personalStorage['files_usage'])+((int)$personalStorage['trash_usage']);
			$total = (int)$personalStorage['total_space'];
			$relative = round(($used / $total) * 10000) / 100;
		}
		else{
			// Not sure why $dir was used here. An empty dir will
			// have size 0 and thus relative 0.
			$storageInfo = \OC_Helper::getStorageInfo("/");
			$free = $storageInfo['free'];
			$relative = (int)$storageInfo['relative'];
			$used = (int)$storageInfo['used'];
			$total = (int)$storageInfo['total'];
		}

		if(\OCP\App::isEnabled('user_group_admin') && !empty($group)){
			$total = \OCP\Util::computerFileSize($groupUserFreequota);
			$free = $total - $used;
			$relative = empty($total)?INF:round(($used / $total) * 10000) / 100;
		}
		
		$maxUploadFileSize = \OCP\Util::maxUploadFilesize($dir, $free);
		$maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);
		$maxHumanFileSize = $l->t('Upload (max. %s)', array($maxHumanFileSize));

		$ret = array('uploadMaxFilesize' => $maxUploadFileSize,
				'maxHumanFilesize'  => $maxHumanFileSize,
				'freeSpace' => $free,
				'usedSpace' => $used,
				'usedSpaceHuman' => \OCP\Util::humanFileSize($used),
				'totalSpace' => $total,
				'usedSpacePercent'  => $relative);
		
		if(isset($old_user)){
			self::restoreUser($old_user);
		}

		return $ret;
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
	
	public static function resolveReShare($linkItem){
		\OCP\Util::writeLog('files_sharding', 'Resolving '.serialize($linkItem), \OC_Log::WARN);
		if(isset($linkItem['parent'])){
			$parent = $linkItem['parent'];
			while(isset($parent)){
				$query = \OC_DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `id` = ?', 1);
				$item = $query->execute(array($parent))->fetchRow();
				if(isset($item['parent']) && $item['parent']!=-1){// These conditions are the only difference to \OCP\Share::resolveReShare()
					$parent = $item['parent'];
				}
				else{
					if(!empty($item['id']) && $item['id']!=-1){
						\OCP\Util::writeLog('files_sharding', 'Returning parent '.serialize($item), \OC_Log::WARN);
						return $item;
					}
					break;
				}
			}
		}
		\OCP\Util::writeLog('files_sharding', 'Returning original item', \OC_Log::WARN);
		return $linkItem;
	}
	
	public static function migrateUser($olduid, $uid){
		$datadir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . '/data');
		$backupdir = \OC_Config::getValue("backupdirectory", '/tmp');
		// Just in case - backup the newly created user
		\OCP\Util::writeLog('files_sharding', 'Backing up files of user '.$uid.': '.
				$datadir.'/'.$uid.'-->'.$backupdir.'/'.$uid, \OC_Log::ERROR);
		rename($datadir.'/'.$uid, $backupdir.'/'.$uid);
		// Now move the existing/migrated user's data to the new location
		\OCP\Util::writeLog('files_sharding', 'Migrating files of user '.$olduid.': '.
				$datadir.'/'.$olduid.'-->'.$datadir.'/'.$uid, \OC_Log::ERROR);
		rename($datadir.'/'.$olduid, $datadir.'/'.$uid);
		// Delete the user $olduid
		$query = \OC_DB::prepare('DELETE FROM `*PREFIX*users` WHERE LOWER(`uid`) = ?');
		$query->execute(array($olduid));
	}
	
	public static function getFullPath($file, $dir, $owner='', $id='', $group='', $dirId=''){
		$user_id = \OCP\USER::getUser();
		$group_dir_owner = $user_id;
		if(!empty($owner) && $owner!=$user_id){
			\OC_Util::tearDownFS();
			$group_dir_owner = $owner;
			\OC_User::setUserId($owner);
			\OC_Util::setupFS($owner);
		}
		if(!empty($group) && !empty($group_dir_owner)){
			\OC\Files\Filesystem::tearDown();
			$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
			\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
		}
		if(!empty($id)){
			$path = \OC\Files\Filesystem::getPath($id);
			$dir = substr($path, 0, strrpos($path, '/'));
		}
		
		if(empty($id) && !empty($dirId)){
			$dir = \OC\Files\Filesystem::getPath($dirId);
		}
		$path = empty($path)?$dir.'/'.$file:$path;
		$fullPath = \OC\Files\Filesystem::getLocalFile($path);
		if(\OCP\USER::getUser()!=$user_id){
			self::restoreUser($user_id);
		}
		return $fullPath;
	}
	
	public static function serveFiles($files, $dir, $owner='', $id='',
			$group='', $dirId='', $inStorage=false){
		$user_id = \OCP\USER::getUser();
		$files_list = json_decode($files);
		// in case we get only a single file
		if(!is_array($files_list)) {
			$files_list = array(rawurldecode($files));
		}
		else{
			$files_list = array_map('rawurldecode', $files_list);
		}
		
		\OCP\Util::writeLog('files_sharding', 'FILES '.count($files_list).':'.$files.
				':'.$dir.':'.$owner.':'.$id.':'.$group.':'.$dirId.':'.$inStorage, \OC_Log::WARN);
		
		$group_dir_owner = $user_id;
		try{
			
			if(!empty($owner) && $owner!=$user_id){
				\OC_Util::tearDownFS();
				$group_dir_owner = $owner;
				\OC_User::setUserId($owner);
				\OC_Util::setupFS($owner);
			}
			if(!empty($group) && !empty($group_dir_owner)){
				\OC\Files\Filesystem::tearDown();
				$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
				\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
			}
			if(!empty($id) && !$inStorage){
				$path = \OC\Files\Filesystem::getPath($id);
				$dir = substr($path, 0, strrpos($path, '/'));
			}
			
			if($inStorage){
				\OC_Util::teardownFS();
				\OC\Files\Filesystem::init($user_id, '/'.$user_id.'/files_external/storage/');
			}
			
			if(empty($id) && !empty($dirId) && !$inStorage){
				$dir = \OC\Files\Filesystem::getPath($dirId);
			}
			
			// Now serve the file(s)
			if($inStorage){
				\OC_Files::get($dir, $files_list, $_SERVER['REQUEST_METHOD'] == 'HEAD');
			}
			elseif(isset($_SERVER['HTTP_RANGE']) && count($files_list)==1){
				//ob_start();
				$path = empty($path)?$dir.'/'.$files_list[0]:$path;
				$fullPath = \OC\Files\Filesystem::getLocalFile($path);
				\OCP\Util::writeLog('files_sharding', 'HTTP_RANGE: '.$_SERVER['HTTP_RANGE'], \OCP\Util::WARN);
				$mimetype = \OC_Helper::getSecureMimeType(\OC\Files\Filesystem::getMimeType($path));
				self::switchUser($user_id, true);
				self::rangeServe($fullPath, $mimetype);
				//ob_end_flush();
			}
			elseif(count($files_list)>1){
				// Bypass the use of zipstreamer as it produces archives not readable by the archive utility on macs
				$fullDirPath = \OC\Files\Filesystem::getLocalFile($dir);
				\OCP\Util::writeLog('files_sharding', 'Zipping '.$fullDirPath.':'.$path.':'.$dir.
						"CMD: cd '".$fullDirPath."'; zip -r - '".implode($files_list, "' '")."'", \OC_Log::WARN);
				self::sendZipHeaders(basename($fullDirPath).".zip");
				self::switchUser($user_id, true);
				passthru("PATH=\$PATH:/usr/local/bin; cd '".$fullDirPath."'; zip -r - '".implode($files_list, "' '")."'");
			}
			elseif(count($files_list)==1){
				// Bypass the use of zipstreamer as it produces archives not readable by the archive utility on macs
				$path = empty($path)?$dir.'/'.$files_list[0]:$path;
				$fullPath = \OC\Files\Filesystem::getLocalFile($path);
				$info = \OC\Files\Filesystem::getFileInfo($path);
				\OCP\Util::writeLog('files_sharding', 'TYPE '.$fullPath.':'.$path.':'.$files_list[0].':'.$dir.':'.$user_id.':'.$owner.' --> '.(empty($info)?"":$info->getType()), \OC_Log::WARN);
				if(!empty($info) && $info->getType()=='dir'){
					self::switchUser($user_id, true);
					self::sendZipHeaders(basename($fullPath).".zip");
					passthru("PATH=\$PATH:/usr/local/bin; cd '".dirname($fullPath)."'; zip -r - '".basename($fullPath)."'");
				}
				else{
					\OC_Files::get($dir, $files_list, true);
					self::restoreUser($user_id, true);
					//session_write_close();
					//\OC_Files::get($dir, $files_list, $_SERVER['REQUEST_METHOD'] == 'HEAD');
					//\OC_Files::get($dir, $files_list, true);
					readfile($fullPath);
				}
			}
			else{
				// RANGE is only for serving single media files
				session_write_close();
				\OC_Files::get($dir, $files_list, $_SERVER['REQUEST_METHOD'] == 'HEAD');
			}
		
		}
		catch(\Exception $e){
			\OCP\Util::writeLog('files_sharding', 'ERROR serving file file: '.$e.getTraceAsString(), \OC_Log::ERROR);
		}
		finally {
			self::restoreUser($user_id, true);
			//session_write_close();
		}
		
		// This has no effect when downloading zip archives via zipstreamer,
		// as OC_Files::get calls ob_end and returns
		//\OCP\Util::writeLog('files_sharding', 'Logging out '.$owner.'-->'.$user_id, \OC_Log::WARN);
		/*if(!empty($owner) && $owner!=$user_id || !empty($group) && !empty($group_dir_owner)){
			\OC_Util::tearDownFS();
			\OC_User::setUserId($user_id);
			\OC_Util::setupFS($user_id);
		}*/
	}
	
	// From zipstreamer
	public static function sendZipHeaders($archiveName){
		header('Pragma: public');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s T'));
		header('Expires: 0');
		header('Accept-Ranges: bytes');
		//header('Connection: Keep-Alive');
		header('Content-Type: application/zip');
		header( 'Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($archiveName)
				. '; filename="' . rawurlencode($archiveName) . '"' );
		header('Content-Transfer-Encoding: binary');
	}
	
	// From https://mobiforge.com/design-development/content-delivery-mobile-devices#byte-ranges
	public static function rangeServe($file, $mimetype) {
		session_write_close();
		$fp = @fopen($file, 'rb');
		$size   = filesize($file); // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
		// Now that we've gotten so far without errors we send the accept range header
		/* At the moment we only support single ranges.
		 * Multiple ranges requires some more work to ensure it works correctly
		 * and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		 *
		 * Multirange support annouces itself with:
		 * header('Accept-Ranges: bytes');
		 *
		 * Multirange content must be sent with multipart/byteranges mediatype,
		 * (mediatype = mimetype)
		 * as well as a boundry header to indicate the various chunks of data.
		 */
		//header("Accept-Ranges: 0-$length");
		header("Accept-Ranges: bytes");
		// header('Accept-Ranges: bytes');
		// multipart/byteranges
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		$c_start = $start;
		$c_end   = $end;
		// Extract the range string
		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		// Make sure the client hasn't sent us a multibyte range
		if(strpos($range, ',') !== false){
			OCP\Util::writeLog('share', 'ERROR: Multibyte range not supported', \OCP\Util::ERROR);
			// (?) Shoud this be issued here, or should the first
			// range be used? Or should the header be ignored and
			// we output the whole content?
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			// (?) Echo some info to the client?
			return;
		}
		// If the range starts with an '-' we start from the beginning
		// If not, we forward the file pointer
		// And make sure to get the end byte if spesified
		$range = trim($range);
		if($range[0] == '-'){
			// The n-number of the last bytes is requested
			$c_start = $size - substr($range, 1);
		}
		else{
			$range  = explode('-', $range);
			$c_start = $range[0];
			$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
		}
		/* Check the range and make sure it's treated according to the specs.
		 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
		 */
		// End bytes cannot be larger than $end.
		$c_end = ($c_end > $end) ? $end : $c_end;
		// Validate the requested range and return an error if it's not correct.
		if($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size){
			\OCP\Util::writeLog('share', 'ERROR: Bad range', \OCP\Util::ERROR);
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			// (?) Echo some info to the client?
			return;
		}
		$start  = empty($c_start)?0:$c_start;
		$end    = $c_end;
		$length = $end - $start + 1; // Calculate new content length
		fseek($fp, $start);
		header('HTTP/1.1 206 Partial Content');
		// Notify the client the byte range we'll be outputting
		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: $length");
		header('Content-Type: '.$mimetype);
		header('Content-Disposition: inline; filename="'.basename($file).'"');
		\OCP\Util::writeLog('files_sharing', 'Reading file '.$file.' : '.$start.' --> '.$end, \OC_Log::WARN);
		// Start buffered download
		$buffer = 1024 * 8;
		while(!feof($fp) && ($p = ftell($fp)) <= $end){
			if($p + $buffer > $end){
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $end - $p + 1;
			}
			set_time_limit(0); // Reset time limit for big files
			echo fread($fp, $buffer);
			flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
		}
		\OCP\Util::writeLog('files_sharing', 'Done reading file '.$file, \OC_Log::WARN);
		fclose($fp);
	}
	
}

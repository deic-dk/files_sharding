<?php


namespace OCA\FilesSharding;

require_once 'files_sharding/lib/session.php';
require_once 'files_sharding/lib/lib_files_sharding.php';

// Register a custom session handler.

class FileSessionHandler {
	private $savePath;
	private $ocUserDatabase;	
	private $quota;
	private $freequota;

	function __construct($savePath) {
		\OC_Log::write('files_sharding',"Constructing session", \OC_Log::INFO);
		$this->savePath = $savePath;
		$this->ocUserDatabase = new \OC_User_Database();
	}

	function open($savePath, $sessionName){
		\OC_Log::write('files_sharding',"Opening session ".$sessionName, \OC_Log::INFO);
		if (!is_dir($this->savePath)) {
			mkdir($this->savePath, 0777);
		}
		return true;
	}

	function close(){
		return true;
	}

	function read($id){
		$data = null;
		if(is_readable("$this->savePath/sess_$id")){
			$data = (string)@file_get_contents("$this->savePath/sess_$id");
			$parsed_data = \Session::unserialize($data);
		}
		// If no valid session found locally, try to get one from the master
		if(empty($parsed_data['user_id']) && isset($_COOKIE[\OCA\FilesSharding\Lib::$LOGIN_OK_COOKIE])){
			\OC_Log::write('files_sharding',"Getting session ".$id, \OC_Log::WARN);
			$data = $this->getSession($id);
			$parsed_data = \Session::unserialize($data);
		}
		if(!empty($parsed_data['user_id']) && !empty($data)){
			\OC_Log::write('files_sharding',"Session data: ".$data, \OC_Log::DEBUG);
			return $data;
		}
		if(!Lib::getAllowLocalLogin($_SERVER['HTTP_HOST'])){
			\OC_Log::write('files_sharding',"Local login not allowed on ".$_SERVER['HTTP_HOST'], \OC_Log::WARN);
			$master_url = Lib::getMasterURL();
			if($master_url){
				header('Location: ' . $master_url."index.php?logout=true&requesttoken=".\OC_Util::callRegister());
				exit;
			}
		}
		else{
			return $data;
		}
	}

	function write($id, $data){
		\OC_Log::write('files_sharding',"Writing session ".$id, \OC_Log::INFO);
		return file_put_contents("$this->savePath/sess_$id", $data) === false ? false : true;
		//return $this->putSession();
	}

	function destroy($id){
		$file = "$this->savePath/sess_$id";
		if (file_exists($file)) {
			\OC_Log::write('files_sharding',"Deleting session file ".$file, \OC_Log::WARN);
			unlink($file);
		}
		return true;
	}

	function gc($maxlifetime){
		foreach (glob("$this->savePath/sess_*") as $file) {
			if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
				unlink($file);
			}
		}
		return true;
	}
	
	function getSession($id){
		$ch = curl_init();
		$masterinturl = Lib::getMasterInternalURL();
		$url = $masterinturl . "apps/files_sharding/ws/get_session.php";
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('id'=>$id));
		$certArr = \OCA\FilesSharding\Lib::getWSCert();
		if(!empty($certArr)){
			\OCP\Util::writeLog('files_sharding', 'Using cert '.$certArr['certificate_file'].
					' and key '.$certArr['key_file'], \OC_Log::WARN);
			curl_setopt($ch, CURLOPT_CAINFO, $certArr['ca_file']);
			curl_setopt($ch, CURLOPT_SSLCERT, $certArr['certificate_file']);
			curl_setopt($ch, CURLOPT_SSLKEY, $certArr['key_file']);
			//curl_setopt($ch, CURLOPT_SSLCERTPASSWD, '');
			//curl_setopt($ch, CURLOPT_SSLKEYPASSWD, '');
		}
		$ret = curl_exec($ch);
		curl_close($ch);
		$res = json_decode($ret);
		if(empty($res->{'session'}) || !empty($res->{'error'})){
			\OC_Log::write('files_sharding',"NO session from ".$url." for ID ".$id.":".serialize($ret), \OC_Log::WARN);
			return null;
		}
		$session = \Session::unserialize($res->{'session'});
		//\OC_Log::write('files_sharding',"Got session ".$session['user_id'].":".$session['oc_mail'].":".join("|", $session['oc_groups']).":".$session['oc_display_name'].":".serialize($session), \OC_Log::WARN);
		if(empty($session['user_id'])){
			\OC_Log::write('files_sharding',"NO user_id, cannot proceed", \OC_Log::WARN);
			//return null;
			$masterurl = Lib::getMasterURL();
			$logout_url = $masterurl."index.php?logout=true";
			header('Location: ' . $logout_url);
			exit;
		}
		if(isset($session['user_id']) && !$this->ocUserDatabase->userExists($session['user_id'])) {
			\OC_Log::write('files_sharding',"User ".$session['user_id']." does not exist, creating.",
					\OC_Log::WARN);
			if(!empty($session['oc_mail']) && $session['user_id']!=$session['oc_mail'] &&
					\OC_User::userExists($session['oc_mail'])){
						\OCA\FilesSharding\Lib::migrateUser($session['oc_mail'], $session['user_id']);
			}
			$this->createUser($session['user_id'], $session['oc_storage_id'], $session['oc_numeric_storage_id']);
			$this->setupUser($session['user_id'], $session['oc_mail'], $session['oc_display_name'],
					$session['oc_groups'], $session['oc_quota'], $session['oc_freequota']);
		}
		else{
			\OC_Log::write('files_sharding',"User already exists, syncing: ".$session['user_id']."/".
					$session['oc_display_name'], \OC_Log::WARN);
			$this->setupUser($session['user_id'], $session['oc_mail'], $session['oc_display_name'],
					$session['oc_groups'], $session['oc_quota'], $session['oc_freequota']);
		}
		return $res->{'session'};
	}
	
	function createUser($uid, $storageid, $numericstorageid){
		\OC_Log::write('files_sharding',"Creating user: ".$uid, \OC_Log::WARN);
		$password = \OC_Util::generateRandomBytes(20);
    $user_created = $this->ocUserDatabase->createUser($uid, $password);
		if($user_created && $this->ocUserDatabase->userExists($uid)){
			self::set_numeric_storage_id($storageid, $numericstorageid);
			$userDir = '/'.$uid.'/files';
			\OC\Files\Filesystem::init($uid, $userDir);
		}
	}
	
	// Notice: we only ever end up here when we're not master (see end of app.php)
	function setupUser($uid, $mail, $displayname, $groups, $quota=null, $freequota=null){
		if(!$this->ocUserDatabase->userExists($uid)) {
			return;
		}
		\OC_Log::write('files_sharding',"Setting up user: ".$uid."/".$displayname." with quota ".$quota.
				', freequota '.$freequota.', mail '.$mail, \OC_Log::WARN);
		$pwHash = \OCA\FilesSharding\Lib::getPasswordHash($uid);
		if(!\OCA\FilesSharding\Lib::setPasswordHash($uid, $pwHash)){
			\OC_Log::write('files_sharding',"Error setting user password for user".$uid, \OC_Log::ERROR);
		}
		if (isset($mail)) {
			self::update_mail($uid, $mail);
		}
		// Groups live on master.
		// No point in seting up groups from SAML-created session when it's already done on master... (?)
		/*if (isset($groups) && \OCP\App::isEnabled('user_saml')) {
			require_once 'user_saml/user_saml.php';
			$samlBackend = new \OC_USER_SAML();
			self::update_groups($uid, $groups, $samlBackend->protectedGroups, true);
		}*/
		if (isset($displayname)) {
			self::update_display_name($uid, $displayname);
		}
		else{
			self::update_display_name_from_master($uid);
		}
		if (!empty($quota) || $quota==='0') {
			$this->update_quota($uid, $quota);
		}
		// This is for local (non-redirected) logins (no passed-on session) or empty quota for user on master.
		else{
			\OCP\Util::writeLog('Files_Sharding', 'QUOTA: '.$quota, \OCP\Util::WARN);
			$this->update_quota_from_master($uid);
		}
		if (isset($freequota)) {
			$this->update_freequota($uid, $freequota);
		}
		else{
			$this->update_freequota_from_master($uid);
		}
		
		$valid_quota = empty($this->quota) || $this->quota==="default"?
			\OC_Appconfig::getValue('files', 'default_quota'):$this->quota;
		$valid_freequota = empty($this->freequota) || $this->freequota==="default"?
			\OC_Appconfig::getValue('files', 'default_freequota'):$this->freequota;
		// Bump up quota if smaller than freequota
		if(!empty($valid_quota) && !empty($valid_freequota) &&
				\OCP\Util::computerFileSize($valid_quota)<\OCP\Util::computerFileSize($valid_freequota)){
			\OC_Log::write('saml','Bumping up quota '.$this->quota." to match freequota ".$this->freequota, \OC_Log::WARN);
			// We don't modify quota in DB, just the effective value for this session.
			// See also hooks.php.
			//$this->update_quota($uid, $valid_freequota);
			$this->quota = $valid_freequota;
		}
	}
	
	private static function set_numeric_storage_id($id, $numeric_id){
		\OC_Log::write('saml','Setting numeric storage id: '.$id."-->".$numeric_id, \OC_Log::WARN);
		if(empty($id) || empty($numeric_id)){
			return;
		}
		$sql = 'SELECT `numeric_id` FROM `*PREFIX*storages` WHERE `id` = ?';
		$result = \OC_DB::executeAudited($sql, array($id));
		$existing_numeric_id = -1;
		if($row = $result->fetchRow()){
			$existing_numeric_id = $row['numeric_id'];
		}
		if($existing_numeric_id!==$numeric_id){
			if($existing_numeric_id===-1){
				$sql = 'INSERT INTO `*PREFIX*storages` (`id`, `numeric_id`) VALUES(?, ?)';
				\OC_DB::executeAudited($sql, array($id, $numeric_id));
			}
			else{
				$sql = 'UPDATE `*PREFIX*storages` SET `numeric_id` = ? WHERE `id` = ?';
				\OC_DB::executeAudited($sql, array($numeric_id, $id));
			}
		}
	}
		
	// From user_saml
	// TODO: abstract to avoid code duplication
	static function update_mail($uid, $email) {
		if ($email != \OC_Preferences::getValue($uid, 'settings', 'email', '')) {
			\OC_Preferences::setValue($uid, 'settings', 'email', $email);
			\OC_Log::write('files_sharding','Set email "'.$email.'" for the user: '.$uid, \OC_Log::WARN);
		}
	}
	
	/*static function update_groups($uid, $groups, $protectedGroups=array(), $just_created=false) {

		if(!$just_created && !empty($groups) && !\OCP\App::isEnabled('user_group_admin')) {	
			$old_groups = \OC_Group::getUserGroups($uid);
			foreach($old_groups as $group) {
				if(!in_array($group, $protectedGroups) && !in_array($group, $groups)) {
					\OC_Group::removeFromGroup($uid,$group);
					\OC_Log::write('files_sharding','Removed "'.$uid.'" from the group "'.$group.'"', \OC_Log::WARN);
				}
			}
		}

		foreach($groups as $group) {
			if (preg_match( '/[^a-zA-Z0-9 _\.@\-\/]/', $group)) {
				\OC_Log::write('files_sharding','Invalid group "'.$group.'", allowed chars "a-zA-Z0-9" and "_.@-/" ',\OC_Log::WARN);
			}
			else {
				if(!\OC_Group::inGroup($uid, $group)){
					if(!\OC_Group::groupExists($group)){
						\OC_Group::createGroup($group);
						\OC_Log::write('files_sharding','New group created: '.$group, \OC_Log::WARN);
					}
					\OC_Group::addToGroup($uid, $group);
					\OC_Log::write('files_sharding','Added "'.$uid.'" to the group "'.$group.'"', \OC_Log::WARN);
				}
			}
		}
	}*/
	
	static function update_display_name($uid, $displayName) {
		// I inject directly into the database here rather than using the method setDisplayName(), 
		// which doesn't work. -CB 
		$query = \OC_DB::prepare('UPDATE `*PREFIX*users` SET `displayname` = ? WHERE LOWER(`uid`) = ?');                            
		$query->execute(array($displayName, $uid));
		//OC_User::setDisplayName($uid, $displayName);
	}

	private function update_quota($uid, $quota) {
		if (!empty($quota) || $quota==='0') {
			\OCP\Config::setUserValue($uid, 'files', 'quota', $quota);
			$this->quota = $quota;
		}
	}
	
	private function update_freequota($uid, $freequota) {
		if (isset($freequota)) {
			\OCP\Config::setUserValue($uid, 'files_accounting', 'freequota', $freequota);
			$this->freequota = $freequota;
		}
	}
	
	//
	
	private function update_quota_from_master($uid) {
		if(!\OCP\App::isEnabled('files_accounting') || \OCA\FilesSharding\Lib::isMaster()){
			return;
		}
		$personalStorage = \OCA\FilesSharding\Lib::ws('personalStorage', array('key'=>'quotas', 'userid'=>$uid),
				false, true, null, 'files_accounting');
		if (isset($personalStorage['quota'])) {
			\OCP\Util::writeLog('Files_Sharding', 'Updating quota for '.$uid.': '.$this->quota.' --> '.
					$personalStorage['quota'], \OCP\Util::WARN);
			\OCP\Config::setUserValue($uid, 'files', 'quota', $personalStorage['quota']);
			$this->quota = $personalStorage['quota'];
		}
		// Update defaults
		$localDefaultQuota = \OC_Appconfig::getValue('files', 'default_quota');
		\OCP\Util::writeLog('Files_Sharding', 'Updating default quotas: '.$localDefaultQuota.' --> '.
				$personalStorage['default_quota'], \OCP\Util::WARN);
		if((!empty($personalStorage['default_quota']) || $personalStorage['default_quota']==='0') &&
				$personalStorage['default_quota']!=$localDefaultQuota){
			\OC_Appconfig::setValue('files', 'default_quota', $personalStorage['default_quota']);
		}
		$localDefaultFreeQuota = \OC_Appconfig::getValue('files_accounting', 'default_freequota');
		if((!empty($personalStorage['default_freequota']) || $personalStorage['default_freequota']==='0') &&
				$personalStorage['default_freequota']!=$localDefaultFreeQuota){
			\OC_Appconfig::setValue('files_accounting', 'default_freequota', $personalStorage['default_freequota']);
		}
	}

	private function update_freequota_from_master($uid) {
		if(!\OCP\App::isEnabled('files_accounting') || \OCA\FilesSharding\Lib::isMaster()){
			return;
		}
		$personalStorage = \OCA\FilesSharding\Lib::ws('personalStorage', array('key'=>'quotas', 'userid'=>$uid),
				false, true, null, 'files_accounting');
		if (isset($personalStorage['freequota'])) {
			\OCP\Config::setUserValue($uid, 'files_accounting', 'freequota', $personalStorage['freequota']);
			$this->freequota = $personalStorage['freequota'];
		}
	}
	
	private static function update_display_name_from_master($uid){
		$displayNames = \OCA\FilesSharding\Lib::ws('getDisplayNames', array('search'=>$uid));
		foreach($displayNames as $userid=>$name){
			if($userid==$uid){
				self::update_display_name($uid, $name);
				break;
			}
		}
	}
	
	function putSession($id = '', $data = ''){
	
		if(empty($id)){
			return;
		}
		$masterinturl = Lib::getMasterInternalURL();
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $masterinturl."apps/files_sharding/ws/put_session.php");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('id'=>$id, 'session'=>$data));
		$certArr = \OCA\FilesSharding\Lib::getWSCert();
		if(!empty($certArr)){
			\OCP\Util::writeLog('files_sharding', 'Using cert '.$certArr['certificate_file'].
					' and key '.$certArr['key_file'], \OC_Log::WARN);
			curl_setopt($ch, CURLOPT_CAINFO, $certArr['ca_file']);
			curl_setopt($ch, CURLOPT_SSLCERT, $certArr['certificate_file']);
			curl_setopt($ch, CURLOPT_SSLKEY, $certArr['key_file']);
			//curl_setopt($ch, CURLOPT_SSLCERTPASSWD, '');
			//curl_setopt($ch, CURLOPT_SSLKEYPASSWD, '');
		}
		$ret = curl_exec($ch);
		curl_close ($ch);
		$res = json_decode($ret);
		\OC_Log::write('files_sharding',"Put session ".$id.": ".serialize($res), \OC_Log::WARN);
		return empty($res->{'error'});
	}
	
}
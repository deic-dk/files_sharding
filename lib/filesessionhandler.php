<?php


namespace OCA\FilesSharding;

require_once 'files_sharding/lib/session.php';
require_once 'files_sharding/lib/lib_files_sharding.php';

// Register a custom session handler.

class FileSessionHandler {
	private $savePath;
	private $ocUserDatabase;

	private static $LOGIN_OK_COOKIE = "oc_ok";

	function __construct($savePath) {
		\OC_Log::write('files_sharding',"Constructing session", \OC_Log::WARN);
		$this->savePath = $savePath;
		$this->ocUserDatabase = new \OC_User_Database();
	}

	function open($savePath, $sessionName){
		\OC_Log::write('files_sharding',"Opening session ".$sessionName, \OC_Log::WARN);
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
		if(empty($parsed_data['user_id']) && isset($_COOKIE[self::$LOGIN_OK_COOKIE])){
			\OC_Log::write('files_sharding',"Getting session ".$id, \OC_Log::WARN);
			$data = $this->getSession($id);
		}
		if(!empty($data)){
			\OC_Log::write('files_sharding',"Session data: ".$data, \OC_Log::DEBUG);
			return $data;
		}
		//header('Location: ' . "https://".Lib::masterfq."/index.php?logout=true");
		//exit;
	}

	function write($id, $data){
		\OC_Log::write('files_sharding',"Writing session ".$id, \OC_Log::WARN);
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
		curl_setopt($ch, CURLOPT_URL, "https://".Lib::masterinternalip."/apps/files_sharding/ws/get_session.php");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('id'=>$id));
		$ret = curl_exec($ch);
		curl_close ($ch);
		$res = json_decode($ret);
		if(empty($res->{'session'}) || !empty($res->{'error'})){
			\OC_Log::write('files_sharding',"NO session.", \OC_Log::WARN);
			return null;
		}
		$session = \Session::unserialize($res->{'session'});
		//\OC_Log::write('files_sharding',"Got session ".$session['user_id'].":".$session['oc_mail'].":".join("|", $session['oc_groups']).":".$session['oc_display_name'].":".serialize($session), \OC_Log::WARN);
		if(empty($session['user_id'])){
			\OC_Log::write('files_sharding',"NO user_id, cannot proceed", \OC_Log::WARN);
			//return null;
			$LOGOUT_URL = "https://".Lib::masterinternalip."/index.php?logout=true";
			header('Location: ' . $LOGOUT_URL);
			exit;
		}
		if(isset($session['user_id']) && !$this->ocUserDatabase->userExists($session['user_id'])) {
			$this->createUser($session['user_id'], $session['oc_mail'], $session['oc_display_name'], $session['oc_groups']);
			$this->setupUser($session['user_id'], $session['oc_mail'], $session['oc_display_name'], $session['oc_groups']);
		}
		else{
			\OC_Log::write('files_sharding',"User already exists, syncing: ".$session['user_id'], \OC_Log::WARN);
			$this->setupUser($session['user_id'], $session['oc_mail'], $session['oc_display_name'], $session['oc_groups']);
		}
		return $res->{'session'};
	}
	
	function createUser($uid, $mail, $displayname, $groups){
		\OC_Log::write('files_sharding',"Creating user: ".$uid, \OC_Log::WARN);
		$password = \OC_Util::generateRandomBytes(20);
    $user_created = $this->ocUserDatabase->createUser($uid, $password);
     if($user_created && $this->ocUserDatabase->userExists($uid)) {
			\OC_Util::setupFS($uid);
    }
	}
	
	function setupUser($uid, $mail, $displayname, $groups){
		\OC_Log::write('files_sharding',"Setting up user: ".$uid, \OC_Log::WARN);
		if($this->ocUserDatabase->userExists($uid)) {
			$password = self::getPassword($uid);
			if(!$this->setPassword($uid, $password)){
				\OC_Log::write('files_sharding',"Error setting user password for user".$uid, \OC_Log::ERROR);
			}
     if (isset($mail)) {
        self::update_mail($uid, $mail);
      }
      if (isset($groups)) {
				$samlBackend = new \OC_USER_SAML();
        self::update_groups($uid, $groups, $samlBackend->protectedGroups, true);
      }
      if (isset($displayname)) {
        self::update_display_name($uid, $displayname);
      }
		}
	}
	
	 function setPassword($uid, $password) {
			$query = \OC_DB::prepare('UPDATE `*PREFIX*users` SET `password` = ? WHERE `uid` = ?');
			$result = $query->execute(array($password, $uid));
			return $result ? true : false;
	}
	
	static function getPassword($id){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://".Lib::masterinternalip."/apps/files_sharding/ws/get_pw_hash.php");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('id'=>$id));
		$ret = curl_exec($ch);
		curl_close ($ch);
		$res = json_decode($ret);
		if(empty($res->{'password'}) || !empty($res->{'error'})){
			\OC_Log::write('files_sharding',"No password returned. ".$res->{'error'}, \OC_Log::WARN);
			return null;
		}
		return $res->{'password'};
	}
		
	// From user_saml
	// TODO: abstract to avoid code duplication
	static function update_mail($uid, $email) {
		if ($email != \OC_Preferences::getValue($uid, 'settings', 'email', '')) {
			\OC_Preferences::setValue($uid, 'settings', 'email', $email);
			\OC_Log::write('files_sharding','Set email "'.$email.'" for the user: '.$uid, \OC_Log::WARN);
		}
	}
	
	static function update_groups($uid, $groups, $protectedGroups=array(), $just_created=false) {

		if(!$just_created) {
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
				if (!\OC_Group::inGroup($uid, $group)) {
					if (!\OC_Group::groupExists($group)) {
						\OC_Group::createGroup($group);
						\OC_Log::write('files_sharding','New group created: '.$group, \OC_Log::DEBUG);
					}
					\OC_Group::addToGroup($uid, $group);
					\OC_Log::write('files_sharding','Added "'.$uid.'" to the group "'.$group.'"', \OC_Log::DEBUG);
				}
			}
		}
	}
	
	static function update_display_name($uid, $displayName) {
		// I inject directly into the database here rather than using the method setDisplayName(), 
		// which doesn't work. -CB 
		$query = \OC_DB::prepare('UPDATE `*PREFIX*users` SET `displayname` = ? WHERE LOWER(`uid`) = ?');                            
		$query->execute(array($displayName, $uid));
		//OC_User::setDisplayName($uid, $displayName);
	}

//
	
	function putSession($id = '', $data = ''){
	
		if(empty($id)){
			return;
		}
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://".Lib::masterinternalip."/apps/files_sharding/ws/put_session.php");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('id'=>$id, 'session'=>$data));
		$ret = curl_exec($ch);
		curl_close ($ch);
		$res = json_decode($ret);
		\OC_Log::write('files_sharding',"Put session ".$id.": ".serialize($res), \OC_Log::WARN);
		return empty($res->{'error'});
	}
	
}
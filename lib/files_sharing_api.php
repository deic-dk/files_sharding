<?php
/**
 * ownCloud
 *
 * @author Bjoern Schiessle
 * @copyright 2013 Bjoern Schiessle schiessle@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *f
 */

namespace OCA\Files\Share_files_sharding;

\OC_Hook::clear('OC_Filesystem', 'setup');
\OCP\Util::connectHook('OC_Filesystem', 'setup', 'OCA\FilesSharding\Hooks', 'noSharedSetup');

class Api {
	
	public static function getItemShared($itemType, $itemSource){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			$itemShared = \OCP\Share::getItemShared($itemType, $itemSource);
		}
		else{
			\OCP\Util::writeLog('files_sharding', 'OCA\Files\Share_files_sharding::getItemShared '.
					\OC_User::getUser().":".$itemType.":".$itemSource, \OC_Log::WARN);
			$itemShared = \OCA\FilesSharding\Lib::ws('getItemShared', array('user_id' => \OC_User::getUser(),
					'itemType' => $itemType, 'itemSource' => empty($itemSource)?null:$itemSource));
		}
		/*foreach($itemShared as &$share){
			$path = \OC\Files\Filesystem::getPath($share['item_source']);
			$share['path'] = $path;
		}*/
		return $itemShared;
	}

	public static function getItemSharedWithBySource($itemType, $itemSource){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			return \OCP\Share::getItemSharedWithBySource($itemType, $itemSource);
		}
		else{
			return \OCA\FilesSharding\Lib::ws('getItemSharedWithBySource', array('user_id' => \OC_User::getUser(),
					'itemType' => $itemType,'itemSource' => $itemSource));
		}
	}
	
	public static function shareItem($itemType, $itemSource, $shareType, $shareWith, $permissions){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			return \OCP\Share::shareItem($itemType, $itemSource, $shareType, $shareWith, $permissions);
		}
		else{
			return \OCA\FilesSharding\Lib::ws('share_action',
					array('user_id' => \OC_User::getUser(), 'action' => 'share', 'itemType' => $itemType,'itemSource' => $itemSource,
								'shareType' => $shareType, 'shareWith' => $shareWith, 'permissions' => $permissions), true, true);
		}
	}
	
	private static function setPermissions($itemType, $itemSource, $shareType, $shareWith, $permissions){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			return \OCP\Share::setPermissions($itemType, $itemSource, $shareType, $shareWith, $permissions);
		}
		else{
			return \OCA\FilesSharding\Lib::ws('share_action',
					array('user_id' => \OC_User::getUser(), 'action' => 'setPermissions', 'itemType' => $itemType,'itemSource' => $itemSource,
								'shareType' => $shareType, 'shareWith' => $shareWith, 'permissions' => $permissions), true, true);
		}
	}
	
	private static function setExpirationDate($itemType, $itemSource, $date, $shareTime){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			return \OCP\Share::setExpirationDate($itemType, $itemSource, $date, $shareTime);
		}
		else{
			return \OCA\FilesSharding\Lib::ws('share_action',
					array('user_id' => \OC_User::getUser(), 'action' => 'setExpirationDate', 'itemType' => $itemType,'itemSource' => $itemSource,
								'date' => $date, 'shareTime' => $shareTime), true, true);
		}
	}
	
	private static function unShare($itemType, $itemSource, $shareType, $shareWith){
		if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
			return \OCP\Share::unShare($itemType, $itemSource, $shareType, $shareWith);
		}
		else{
			return \OCA\FilesSharding\Lib::ws('share_action',
					array('user_id' => \OC_User::getUser(), 'action' => 'unshare', 'itemType' => $itemType,'itemSource' => $itemSource,
							'shareType' => $shareType, 'shareWith' => $shareWith), true, true);
		}
	}

/**
	 * get all shares
	 *
	 * @param array $params option 'file' to limit the result to a specific file/folder
	 * @return \OC_OCS_Result share information
	 */
	public static function getAllShares($params) {
		if (isset($_GET['shared_with_me']) && $_GET['shared_with_me'] !== 'false') {
				return self::getFilesSharedWithMe();
			}
		// if a file is specified, get the share for this file
		if (isset($_GET['path'])) {
			$params['itemSource'] = self::getFileId($_GET['path']);
			$params['path'] = $_GET['path'];
			$params['itemType'] = self::getItemType($_GET['path']);

			/*if ( isset($_GET['reshares']) && $_GET['reshares'] !== 'false' ) {
				$params['reshares'] = true;
			} else {*/
				$params['reshares'] = false;
			//}

			if (isset($_GET['subfiles']) && $_GET['subfiles'] !== 'false') {
				return self::getSharesFromFolder($params);
			}
			return self::collectShares($params);
		}

		$shares = self::getItemShared('file', null);
		$isMaster = \OCA\FilesSharding\Lib::isMaster();
		if ($shares === false) {
			return new \OC_OCS_Result(null, 404, 'could not get shares');
		} else {
			foreach ($shares as &$share) {
				// item_source is the id on the slave, file_source is the fake id on the master 
				//$share['file_source'] = $share['item_source'];
				// Since I'm listing files shared by me, I'm on my home server.
				// Set path accordingly.
				// Well, actually API requests are now redirected to the master,
				// so for those, use file_source, for ajax calls still use item_source.
				if($isMaster){
					if($share['file_source']){
						$share['path'] = \OCA\FilesSharding\Lib::getFilePath($share['file_source']);
					}
				}
				else{
					if($share['item_source']){
						$share['path'] = \OCA\FilesSharding\Lib::getFilePath($share['item_source']);
					}
				}
				\OCP\Util::writeLog('files_sharding', 'Got item shared '.
						$share['file_source'].'-->'.$share['path'], \OC_Log::INFO);
				//
				if ($share['item_type'] === 'file' && isset($share['path'])) {
					$share['mimetype'] = \OC_Helper::getFileNameMimeType($share['path']);
					if (\OC::$server->getPreviewManager()->isMimeSupported($share['mimetype'])) {
						$share['isPreviewAvailable'] = true;
					}
				}
				// Set group if in a group folder
				$fileInfo = \OCA\FilesSharding\Lib::getFileInfo($share['path'], null, $share['item_source'], null);
				if($fileInfo['path']=='files' && \OCP\App::isEnabled('user_group_admin')){
					\OCP\Util::writeLog('files_sharding', 'Getting group for '.$share['item_source'].
						':'.$fileInfo['path'], \OC_Log::INFO);
					$group = \OC_User_Group_Admin_Util::getGroup($share['item_source']);
					if(!empty($group)){
						$share['group'] = $group['group'];
						$share['path'] = $group['path'];
					}
				}
			}
			\OCP\Util::writeLog('files_sharding', 'Got items shared '.serialize($shares), \OC_Log::INFO);
			return new \OC_OCS_Result($shares);
		}

	}

	/**
	 * get share information for a given share
	 *
	 * @param array $params which contains a 'id'
	 * @return \OC_OCS_Result share information
	 */
	public static function getShare($params) {

		$s = self::getShareFromId($params['id']);
		$params['itemSource'] = $s['file_source'];
		$params['itemType'] = $s['item_type'];
		$params['specificShare'] = true;

		return self::collectShares($params);
	}

	/**
	 * collect all share information, either of a specific share or all
	 *        shares for a given path
	 * @param array $params
	 * @return \OC_OCS_Result
	 */
	private static function collectShares($params) {

		$itemSource = $params['itemSource'];
		$itemType = $params['itemType'];
		$getSpecificShare = isset($params['specificShare']) ? $params['specificShare'] : false;

		if ($itemSource !== null) {
			$shares = self::getItemShared($itemType, $itemSource);
			$receivedFrom = self::getItemSharedWithBySource($itemType, $itemSource);
			// if a specific share was specified only return this one
			if ($getSpecificShare === true) {
				foreach ($shares as $share) {
					if ($share['id'] === (int) $params['id']) {
						$shares = array('element' => $share);
						break;
					}
				}
			} else {
				$path = $params['path'];
				foreach ($shares as $key => $share) {
					$shares[$key]['path'] = $path;
				}
			}


			// include also reshares in the lists. This means that the result
			// will contain every user with access to the file.
			/*if (isset($params['reshares']) && $params['reshares'] === true) {
				$shares = self::addReshares($shares, $itemSource);
			}*/

			if ($receivedFrom) {
				foreach ($shares as $key => $share) {
					$shares[$key]['received_from'] = $receivedFrom['uid_owner'];
					$shares[$key]['received_from_displayname'] = \OCP\User::getDisplayName($receivedFrom['uid_owner']);
				}
			}
		} else {
			$shares = null;
		}

		if ($shares === null || empty($shares)) {
			return new \OC_OCS_Result(null, 404, 'share doesn\'t exist');
		} else {
			return new \OC_OCS_Result($shares);
		}
	}

	/**
	 * add reshares to a array of shares
	 * @param array $shares array of shares
	 * @param int $itemSource item source ID
	 * @return array new shares array which includes reshares
	 */
	/*private static function addReshares($shares, $itemSource) {

		// if there are no shares than there are also no reshares
		$firstShare = reset($shares);
		if ($firstShare) {
			$path = $firstShare['path'];
		} else {
			return $shares;
		}

		$select = '`*PREFIX*share`.`id`, `item_type`, `*PREFIX*share`.`parent`, `share_type`, `share_with`, `file_source`, `path` , `*PREFIX*share`.`permissions`, `stime`, `expiration`, `token`, `storage`, `mail_send`, `mail_send`';
		$getReshares = \OC_DB::prepare('SELECT ' . $select . ' FROM `*PREFIX*share` INNER JOIN `*PREFIX*filecache` ON `file_source` = `*PREFIX*filecache`.`fileid` WHERE `*PREFIX*share`.`file_source` = ? AND `*PREFIX*share`.`item_type` IN (\'file\', \'folder\') AND `uid_owner` != ?');
		$reshares = $getReshares->execute(array($itemSource, \OCP\User::getUser()))->fetchAll();

		foreach ($reshares as $key => $reshare) {
			if (isset($reshare['share_with']) && $reshare['share_with'] !== '') {
				$reshares[$key]['share_with_displayname'] = \OCP\User::getDisplayName($reshare['share_with']);
			}
			// add correct path to the result
			$reshares[$key]['path'] = $path;
		}

		return array_merge($shares, $reshares);
	}*/

	/**
	 * get share from all files in a given folder (non-recursive)
	 * @param array $params contains 'path' to the folder
	 * @return \OC_OCS_Result
	 */
	private static function getSharesFromFolder($params) {
		$path = $params['path'];
		$view = new \OC\Files\View('/'.\OCP\User::getUser().'/files');

		if(!$view->is_dir($path)) {
			return new \OC_OCS_Result(null, 400, "not a directory");
		}

		$content = $view->getDirectoryContent($path);

		$result = array();
		foreach ($content as $file) {
			// workaround because folders are named 'dir' in this context
			$itemType = $file['type'] === 'file' ? 'file' : 'folder';
			$share = self::getItemShared($itemType, $file['fileid']);
			if($share) {
				$receivedFrom =  self::getItemSharedWithBySource($itemType, $file['fileid']);
				reset($share);
				$key = key($share);
				if ($receivedFrom) {
					$share[$key]['received_from'] = $receivedFrom['uid_owner'];
					$share[$key]['received_from_displayname'] = \OCP\User::getDisplayName($receivedFrom['uid_owner']);
				}
				$result = array_merge($result, $share);
			}
		}

		return new \OC_OCS_Result($result);
	}

	/**
	 * get files shared with the user
	 * @return \OC_OCS_Result
	 */
	public static function getFilesSharedWithMe() {
		try{
			if(!\OCP\App::isEnabled('files_sharding') || \OCA\FilesSharding\Lib::isMaster()){
				$shares = \OCP\Share::getItemsSharedWith('file');
			}
			else{
				$shares = \OCA\FilesSharding\Lib::ws('getItemsSharedWith', array('user_id' => \OC_User::getUser(),
						'itemType' => 'file'));
			}
			foreach ($shares as &$share) {
				if ($share['item_type'] === 'file') {
					$share['mimetype'] = \OC_Helper::getFileNameMimeType($share['file_target']);
					if (\OC::$server->getPreviewManager()->isMimeSupported($share['mimetype'])) {
						$share['isPreviewAvailable'] = true;
					}
				}
			}
			$result = new \OC_OCS_Result($shares);
		}
		catch (\Exception $e) {
			$result = new \OC_OCS_Result(null, 403, $e->getMessage());
		}

		return $result;

	}
	
	public static function lookupShare($itemType, $itemSource, $shareType, $shareWith=null, $token=null){
		$shares = self::getItemShared($itemType, $itemSource);
		if(is_string($token)) { //public link share
			foreach ($shares as $share) {
				if ($share['token']==$token) {
					return $share;
				}
			}
		}
		else{
			foreach ($shares as $share) {
				if((empty($shareWith) || $share['share_with']==$shareWith) &&
						$share['share_type']==$shareType && $share['item_source']==$itemSource) {
					return $share;
				}
			}
		}
		return null;
	}

	/**
	 * create a new share
	 * @param array $params
	 * @return \OC_OCS_Result
	 */
	public static function createShare($params) {
		
		\OCP\Util::writeLog('files_sharing', 'Creating share:'.serialize($params).
				'-->'.serialize($_GET).'-->'.serialize($_POST), \OCP\Util::WARN);
		$path = isset($_POST['path']) ? $_POST['path'] : null;

		if($path === null) {
			return new \OC_OCS_Result(null, 400, "please specify a file or folder path");
		}
		$itemSource = self::getFileId($path);
		$fileSource = self::getFileId($path);
		$itemType = self::getItemType($path);

		if($itemSource === null) {
			return new \OC_OCS_Result(null, 404, "wrong path, file/folder doesn't exist.");
		}

		$shareWith = isset($_POST['shareWith']) ? $_POST['shareWith'] : null;
		$shareType = isset($_POST['shareType']) ? (int)$_POST['shareType'] : null;

		switch($shareType) {
			case \OCP\Share::SHARE_TYPE_USER:
				$permissions = isset($_POST['permissions']) ? (int)$_POST['permissions'] : 31;
				break;
			case \OCP\Share::SHARE_TYPE_GROUP:
				$permissions = isset($_POST['permissions']) ? (int)$_POST['permissions'] : 31;
				break;
			case \OCP\Share::SHARE_TYPE_LINK:
				//allow password protection
				$shareWith = isset($_POST['password']) ? $_POST['password'] : null;
				//check public link share
				$publicUploadEnabled = \OC::$server->getAppConfig()->getValue('core', 'shareapi_allow_public_upload', 'yes');
				if(isset($_POST['publicUpload']) && $publicUploadEnabled !== 'yes') {
					return new \OC_OCS_Result(null, 403, "public upload disabled by the administrator");
				}
				$publicUpload = isset($_POST['publicUpload']) ? $_POST['publicUpload'] : 'false';
				// read, create, update (7) if public upload is enabled or
				// read (1) if public upload is disabled
				$permissions = $publicUpload === 'true' ? 7 : 1;
				break;
			default:
				return new \OC_OCS_Result(null, 400, "unknown share type");
		}
		
		if(!empty($_GET) && !empty($_GET['item_source']) && $_GET['item_source']!=$itemSource){
			$itemSource = $_GET['item_source'];
		}
		$exists = false;
		if(empty(self::lookupShare($itemType, $itemSource, $shareType, $shareWith)) &&
				empty(self::lookupShare($itemType, $fileSource, $shareType, $shareWith))){
			// Only do this if files_sharing action has not been run.
			// In that case, just do the fix below.
			$exists = true;
			try{
				$token = self::shareItem(
						$itemType,
						$fileSource,
						$shareType,
						$shareWith,
						$permissions
						);
			} catch (\Exception $e) {
				return new \OC_OCS_Result(null, 403, $e->getMessage());
			}
		}
		if(!empty($_GET) && !empty($_GET['item_source']) && \OCA\FilesSharding\Lib::isMaster()){
			// Now we need to fix the entries in the database to match the original itemSource, otherwise the js view will
			// not catch the shared items, i.e. getItems() from share.php will not.
			if($fileSource!=$itemSource){
				\OCP\Util::writeLog('files_sharding', 'Updating item_source '.$fileSource.'-->'.$itemSource, \OC_Log::WARN);
				$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `item_source` = ? WHERE `item_source` = ? AND `file_source` = ?');
				$query->execute(array($itemSource, $fileSource, $fileSource));
			}
		}

		if (!$exists || $token) {
			$data = array();
			$data['id'] = 'unknown';
			$shares = self::getItemShared($itemType, $itemSource);
			if(is_string($token)) { //public link share
				foreach ($shares as $share) {
					if ($share['token'] === $token) {
						$data['id'] = $share['id'];
						break;
					}
				}
				$url = \OCP\Util::linkToPublic('files&t='.$token);
				$data['url'] = $url; // '&' gets encoded to $amp;
				$data['token'] = $token;

			} else {
				foreach ($shares as $share) {
					if ($share['share_with'] === $shareWith && $share['share_type'] === $shareType) {
						$data['id'] = $share['id'];
						break;
					}
				}
			}
			
			if(!$exists){
				// This is to prevent the registered files_sharing action to kick in
				// and try to modify an ID that no longer exists.
				// Yes, shareItem modifies the ID for links. Go figure...
				\OCP\Util::writeLog('files_sharing', 'SUCCESS', \OCP\Util::WARN);
				echo '<?xml version="1.0"?>
<ocs>
 <meta>
  <status>ok</status>
  <statuscode>100</statuscode>
  <message/>
 </meta>
 <data>
  <id>'.$data['id'].'</id>'.
  (empty($data['token'])?'':'<token>'.$data['token'].'</token>').
  '</data>
</ocs>';
  exit();
			}
			
			return new \OC_OCS_Result($data);
		} else {
			return new \OC_OCS_Result(null, 404, "couldn't share file");
		}
	}

	/**
	 * update shares, e.g. password, permissions, etc
	 * @param array $params shareId 'id' and the parameter we want to update
	 *                      currently supported: permissions, password, publicUpload
	 * @return \OC_OCS_Result
	 */
	public static function updateShare($params) {

		$share = self::getShareFromId($params['id']);
		\OCP\Util::writeLog('files_sharing', 'Updating share '.$params['id'].'-->'.serialize($share), \OCP\Util::WARN);
		if(!isset($share['file_source'])) {
			return new \OC_OCS_Result(null, 404, "wrong share Id, share doesn't exist. ".$params['id']);
		}
		
		try {
			if(isset($params['_put']['permissions'])) {
				return self::updatePermissions($share, $params);
			} elseif (isset($params['_put']['password'])) {
				return self::updatePassword($share, $params);
			} elseif (isset($params['_put']['publicUpload'])) {
				return self::updatePublicUpload($share, $params);
			} elseif (isset($params['_put']['expireDate'])) {
				return self::updateExpireDate($share, $params);
			}
		} catch (\Exception $e) {

			return new \OC_OCS_Result(null, 400, $e->getMessage());
		}

		return new \OC_OCS_Result(null, 400, "Wrong or no update parameter given");

	}

	/**
	 * update permissions for a share
	 * @param array $share information about the share
	 * @param array $params contains 'permissions'
	 * @return \OC_OCS_Result
	 */
	private static function updatePermissions($share, $params) {

		$itemSource = $share['item_source'];
		$itemType = $share['item_type'];
		$shareWith = $share['share_with'];
		$shareType = $share['share_type'];
		$permissions = isset($params['_put']['permissions']) ? (int)$params['_put']['permissions'] : null;

		$publicUploadStatus = \OC::$server->getAppConfig()->getValue('core', 'shareapi_allow_public_upload', 'yes');
		$publicUploadEnabled = ($publicUploadStatus === 'yes') ? true : false;


		// only change permissions for public shares if public upload is enabled
		// and we want to set permissions to 1 (read only) or 7 (allow upload)
		if ( (int)$shareType === \OCP\Share::SHARE_TYPE_LINK ) {
			if ($publicUploadEnabled === false || ($permissions !== 7 && $permissions !== 1)) {
				return new \OC_OCS_Result(null, 400, "can't change permission for public link share");
			}
		}

		try {
			$return = self::setPermissions(
					$itemType,
					$itemSource,
					$shareType,
					$shareWith,
					$permissions
					);
		} catch (\Exception $e) {
			return new \OC_OCS_Result(null, 404, $e->getMessage());
		}

		if ($return) {
			return new \OC_OCS_Result();
		} else {
			return new \OC_OCS_Result(null, 404, "couldn't set permissions");
		}
	}

	/**
	 * enable/disable public upload
	 * @param array $share information about the share
	 * @param array $params contains 'publicUpload' which can be 'yes' or 'no'
	 * @return \OC_OCS_Result
	 */
	private static function updatePublicUpload($share, $params) {

		$publicUploadEnabled = \OC::$server->getAppConfig()->getValue('core', 'shareapi_allow_public_upload', 'yes');
		if($publicUploadEnabled !== 'yes') {
			return new \OC_OCS_Result(null, 403, "public upload disabled by the administrator");
		}

		if ($share['item_type'] !== 'folder' ||
				(int)$share['share_type'] !== \OCP\Share::SHARE_TYPE_LINK ) {
			return new \OC_OCS_Result(null, 400, "public upload is only possible for public shared folders");
		}

		// read, create, update (7) if public upload is enabled or
		// read (1) if public upload is disabled
		$params['_put']['permissions'] = $params['_put']['publicUpload'] === 'true' ? 7 : 1;

		return self::updatePermissions($share, $params);

	}

	/**
	 * set expire date for public link share
	 * @param array $share information about the share
	 * @param array $params contains 'expireDate' which needs to be a well formated date string, e.g DD-MM-YYYY
	 * @return \OC_OCS_Result
	 */
	private static function updateExpireDate($share, $params) {
		// only public links can have a expire date
		if ((int)$share['share_type'] !== \OCP\Share::SHARE_TYPE_LINK ) {
			return new \OC_OCS_Result(null, 400, "expire date only exists for public link shares");
		}

		try {
			$expireDateSet = self::setExpirationDate($share['item_type'], $share['item_source'], $params['_put']['expireDate'], (int)$share['stime']);
			$result = ($expireDateSet) ? new \OC_OCS_Result() : new \OC_OCS_Result(null, 404, "couldn't set expire date");
		} catch (\Exception $e) {
			$result = new \OC_OCS_Result(null, 404, $e->getMessage());
		}

		return $result;

	}

	/**
	 * update password for public link share
	 * @param array $share information about the share
	 * @param array $params 'password'
	 * @return \OC_OCS_Result
	 */
	private static function updatePassword($share, $params) {

		$itemSource = $share['item_source'];
		$fileSource = $share['file_source'];
		$itemType = $share['item_type'];

		if( (int)$share['share_type'] !== \OCP\Share::SHARE_TYPE_LINK) {
			return  new \OC_OCS_Result(null, 400, "password protection is only supported for public shares");
		}

		$shareWith = isset($params['_put']['password']) ? $params['_put']['password'] : null;

		if($shareWith === '') {
			$shareWith = null;
		}

		$items = self::getItemShared($itemType, $fileSource);
		\OCP\Util::writeLog('files_sharing', 'Updating '.$fileSource.'-->'.serialize($items), \OCP\Util::WARN);
		
		$checkExists = false;
		foreach ($items as $item) {
			if($item['share_type'] === \OCP\Share::SHARE_TYPE_LINK) {
				$checkExists = true;
				$permissions = $item['permissions'];
			}
		}

		if (!$checkExists) {
			return  new \OC_OCS_Result(null, 404, "share doesn't exists, can't change password");
		}

		try {
			$result = self::shareItem(
					$itemType,
					$fileSource,
					\OCP\Share::SHARE_TYPE_LINK,
					$shareWith,
					$permissions
					);
		} catch (\Exception $e) {
			return new \OC_OCS_Result(null, 403, $e->getMessage());
		}
		// Now we need to fix the entries in the database to match the original itemSource, otherwise the js view will
		// not catch the shared items, i.e. getItems() from share.php will not.
		if($fileSource!=$itemSource && \OCA\FilesSharding\Lib::isMaster()){
			\OCP\Util::writeLog('files_sharding', 'Updating item_source '.$fileSource.'-->'.$itemSource, \OC_Log::WARN);
			$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `item_source` = ? WHERE `item_source` = ? AND `file_source` = ?');
			$query->execute(array($itemSource, $fileSource, $fileSource));
		}
		
		if($result) {
			\OCP\Util::writeLog('files_sharing', 'SUCCESS', \OCP\Util::WARN);
echo '<?xml version="1.0"?>
<ocs>
 <meta>
  <status>ok</status>
  <statuscode>100</statuscode>
  <message/>
 </meta>
 <data/>
</ocs>';
			// This is to prevent the registered files_sharing action to kick in
			// and try to modify an ID that no longer exists.
			// Yes, shareItem modifies the ID for links. Go figure...
			exit();
			return new \OC_OCS_Result();
		}

		return new \OC_OCS_Result(null, 404, "couldn't set password");
	}

	/**
	 * unshare a file/folder
	 * @param array $params contains the shareID 'id' which should be unshared
	 * @return \OC_OCS_Result
	 */
	public static function deleteShare($params) {

		$share = self::getShareFromId($params['id']);
		$fileSource = isset($share['file_source']) ? $share['file_source'] : null;
		$itemType = isset($share['item_type']) ? $share['item_type'] : null;;

		if($fileSource === null) {
			return new \OC_OCS_Result(null, 404, "wrong share ID, share doesn't exist.");
		}

		$shareWith = isset($share['share_with']) ? $share['share_with'] : null;
		$shareType = isset($share['share_type']) ? (int)$share['share_type'] : null;

		if( $shareType === \OCP\Share::SHARE_TYPE_LINK) {
			$shareWith = null;
		}

		try {
			$return = self::unshare(
					$itemType,
					$fileSource,
					$shareType,
					$shareWith);
		} catch (\Exception $e) {
			return new \OC_OCS_Result(null, 404, $e->getMessage());
		}

		if ($return) {
			return new \OC_OCS_Result();
		} else {
			$msg = "Unshare Failed, ".$itemType.":".$fileSource.":".$shareType.":".$shareWith;
			return new \OC_OCS_Result(null, 404, $msg);
		}
	}

	/**
	 * get file ID from a given path
	 * @param string $path
	 * @return string fileID or null
	 */
	private static function getFileId($path) {

		$view = new \OC\Files\View('/'.\OCP\User::getUser().'/files');
		$fileId = null;
		$fileInfo = $view->getFileInfo($path);
		if ($fileInfo) {
			$fileId = $fileInfo['fileid'];
		}

		return $fileId;
	}

	/**
	 * get itemType
	 * @param string $path
	 * @return string type 'file', 'folder' or null of file/folder doesn't exists
	 */
	private static function getItemType($path) {
		$view = new \OC\Files\View('/'.\OCP\User::getUser().'/files');
		$itemType = null;

		if ($view->is_dir($path)) {
			$itemType = "folder";
		} elseif ($view->is_file($path)) {
			$itemType = "file";
		}

		return $itemType;
	}

	/**
	 * get some information from a given share
	 * @param int $shareID
	 * @return array with: item_source, share_type, share_with, item_type, permissions
	 */
	private static function getShareFromId($shareID) {
		if(!\OCA\FilesSharding\Lib::isMaster()){
			$itemShared = \OCA\FilesSharding\Lib::ws('share_fetch',
					array('user_id' => \OC_User::getUser(), 'fetch' => 'getShareFromId',
							'shareId' => $shareID ));
			return $itemShared['data'];
		}
		$sql = 'SELECT * FROM `*PREFIX*share` WHERE `id` = ?';
		$args = array($shareID);
		$query = \OCP\DB::prepare($sql);
		$result = $query->execute($args);
		
		\OCP\Util::writeLog('files_sharing', 'Getting share :'.$shareID.': -->'.$sql, \OCP\Util::WARN);
		
		if (\OCP\DB::isError($result)) {
			\OCP\Util::writeLog('files_sharing', 'Could not get share'.\OC_DB::getErrorMessage($result), \OCP\Util::ERROR);
			return null;
		}
		if ($share = $result->fetchRow()) {
			return $share;
		}

		return null;

	}

}

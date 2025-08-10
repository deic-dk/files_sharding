<?php

/**
* ownCloud files_sharding app
*
* @author Frederik Orellana
* @copyright 2014 Frederik Orellana
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
* You should have received a copy of the GNU Lesser General Public 
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
* 
*/

OCP\JSON::checkAppEnabled('files_sharding');

if(!\OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

require_once('lib/base.php');

\OCP\Util::writeLog('share_action', 'POST: '.serialize($_POST), \OCP\Util::WARN);

function checkTokenExists($token, $itemSource){
	$query = \OC_DB::prepare('SELECT `item_source` FROM `*PREFIX*share` WHERE `token` = ?');
	$result = $query->execute(Array($token));
	if(\OCP\DB::isError($result)){
		\OCP\Util::writeLog('sharing', \OC_DB::getErrorMessage($result), \OC_Log::ERROR);
	}
	$i = 0;
	while($row = $result->fetchRow()){
		++$i;
		if($row['item_source']!=$itemSource){
			throw new Exception('Token '.$token.' already used. '.$row['item_source'].'!='.$itemSource);
		}
		if($i>1){
			\OCP\Util::writeLog('sharing', 'ERROR: Duplicate entries found for token:file_source '.$token.' : '.$itemSource, \OCP\Util::ERROR);
		}
	}
}

\OCP\Util::writeLog('files_sharding', 'Sharing, '.serialize($_POST), \OC_Log::WARN);

$user_id = $_POST['user_id'];
\OC_User::setUserId($user_id);
\OC_Util::setupFS($user_id);

$group = '';
if(OCP\App::isEnabled('user_group_admin') && !empty($_POST['groupFolder'])){
	$group = $_POST['groupFolder'];
	OC_User_Group_Admin_Util::createGroupFolder($group);
	\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$user_id.'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($user_id, $groupDir);
}

$shareType = (int)$_POST['shareType'];

// Folders shared with a group can be shared via link by group members if allowed.
// We need to have the share owner be the file owner.
if(!empty($_POST['owner'])){
	$orig_user_id = \OCA\FilesSharding\Lib::switchUser($_POST['owner']);
	\OCP\Util::writeLog('files_sharding', 'Switched from user '.$orig_user_id.' to '.\OCP\User::getUser(), \OC_Log::WARN);
	// If itemPath is empty, owner and resharer are on different silos.
	// Look it up.
	if(empty($_POST['itemPath'])){
		$fileInfo = \OCA\FilesSharding\Lib::getFileInfo('', $_POST['owner'],
				$_POST['itemSource'], '', '', $group);
		$_POST['itemPath'] = preg_replace("|^".\OC\Files\Filesystem::getRoot()."|", "", $fileInfo->getPath());
		\OCP\Util::writeLog('files_sharding', 'Got itemPath '.$_POST['itemPath'], \OC_Log::WARN);
	}
}

if(isset($_POST['myItemSource'])&&$_POST['myItemSource']){
	// On the master, file_source holds the id of the dummy file
	//$_POST['itemSource'] = \OCA\FilesSharding\Lib::getFileSource($_POST['myItemSource'], $_POST['itemType']);
	$masterItemSource = \OCA\FilesSharding\Lib::getFileSource($_POST['myItemSource'], $_POST['itemType']);
}

switch($_POST['action']){
	case 'share':
		if(isset($_POST['shareType']) && isset($_POST['permissions'])){
			try{
				// Create file/folder if not there
				// urldecode should be done automatically...
				$file_path = $_POST['itemPath'];
				if(($_POST['itemType'] === 'file' or $_POST['itemType'] === 'folder')){
					if(!empty($file_path) && !OC\Files\Filesystem::file_exists($file_path)){
						$parent_path = dirname($file_path);
						if(!OC\Files\Filesystem::file_exists($parent_path)){
							\OCP\Util::writeLog('files_sharding', 'Creating '.$parent_path, \OC_Log::WARN);
							OC\Files\Filesystem::mkdir($parent_path, '0770', true);
						}
						if($_POST['itemType']==='file'){
							OC\Files\Filesystem::touch($file_path);
						}
						if($_POST['itemType']==='folder'){
							\OCP\Util::writeLog('files_sharding', 'Creating '.$file_path, \OC_Log::WARN);
							OC\Files\Filesystem::mkdir($file_path);
							//mkdir($file_path, '0770', true);
						}
					}
				}
				// We need to set the itemSource to a file/folder that exists on the server, otherwise shareItem will complain
				$itemMasterSource = \OCA\FilesSharding\Lib::getFileId($file_path,
						empty($_POST['owner'])?$user_id:$_POST['owner'], $group);
				
				$itemSourceName = isset($_POST['itemSourceName']) ? urldecode($_POST['itemSourceName']) : null;
				if($shareType===OCP\Share::SHARE_TYPE_LINK && empty($_POST['shareWith'])){
					$shareWith = null;
				}
				else{
					$shareWith = $_POST['shareWith'];
				}
				
				// If the user has migrated, a group folder will already have been shared - but now with the
				// file_source on the old home server. Just unshare it.
				$checkItemSource = \OCA\FilesSharding\Lib::getItemSource($itemMasterSource, $_POST['itemType']);
				if($checkItemSource!=$itemMasterSource && !empty($_POST['myItemSource']) && $checkItemSource!=$_POST['myItemSource']){
					\OCP\Util::writeLog('sharing', "Unsharing group folder " . $itemMasterSource, \OCP\Util::WARN);
					OCP\Share::unshare($_POST['itemType'], $itemMasterSource, $_POST['shareType'], $shareWith);
				}
				\OCP\Util::writeLog('sharing', "Sharing " . $itemMasterSource.":".$itemSourceName, \OCP\Util::WARN);
				if(empty($itemMasterSource)){
					\OCP\Util::writeLog('sharing', "Not sharing " . $itemSourceName.". No source given", \OCP\Util::WARN);
					break;
				}
				try{
					$token = OCP\Share::shareItem(
							$_POST['itemType'],
							$itemMasterSource,
							$shareType,
							$shareWith,
							$_POST['permissions'],
							$itemSourceName,
							(!empty($_POST['expirationDate']) ? new \DateTime($_POST['expirationDate']) : null)
							);
				}
				catch(\Exception $ee){
					\OCA\FilesSharding\Lib::restoreUser($user_id, true);
					\OC_JSON::error(array('data' => array('message' => $ee->getMessage())));
				}
				\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
				// Now we need to fix the entries in the database to match the original itemSource, otherwise the js view will
				// not catch the shared items, i.e. getItems() from share.php will not.
				if($_POST['itemSource']!==$itemMasterSource){
					\OCP\Util::writeLog('files_sharding', 'Updating item_source '.$_POST['itemSource'].'-->'.$itemMasterSource, \OC_Log::WARN);
					$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `item_source` = ? WHERE `item_source` = ? AND `file_source` = ?');
					$query->execute(array($_POST['itemSource'], $itemMasterSource, $itemMasterSource));
				}
				// Now set parent to -1 to prevent showing the item in the file listing
				$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `parent` = ? WHERE `item_source` = ?');
				$query->execute(array(-1, $_POST['itemSource']));
				
				// Also get rid of the $shareTypeGroupUserUnique entries made by share.php because the generated
				// target does not match the shared target
				// Notice that \OC\Share\Constants::$shareTypeGroupUserUnique is protected, so we hardcode 2.
				$query = \OC_DB::prepare('DELETE FROM `*PREFIX*share` WHERE `share_type` = ? AND `uid_owner` = ? AND `item_source` = ?');
				$query->execute(array(2, $user_id, $_POST['itemSource']));
				
				// For reshares (via link) of subfolders of a shared folder, checkReshare() , calling
				// getItemSharedWithBySource(), calling getItems() - which returns reset($collectionItems) from share.php,
				// returns the top-level parent/shared folder, causing put() to insert the item_target and file_target
				// of the parent/shared folder.
				// Not sure what the motivation was for that... We fix that up:
				if($shareType===OCP\Share::SHARE_TYPE_LINK){
					$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `item_target` = ?, `file_target` = ? WHERE `share_type` = ? AND `item_source` = ? AND `file_source` = ?');
					$query->execute(array($itemMasterSource, '/'.$itemSourceName, OCP\Share::SHARE_TYPE_LINK, $_POST['itemSource'], $itemMasterSource));
				}
				
				\OCP\Util::writeLog('sharing', 'Sharing '.$_POST['itemSource'].'. Password: '.$shareWith.', token: '.$token, \OCP\Util::WARN);
				
				// FO: Allow any string to be used as token.
				if(isset($_POST['token']) && !empty($_POST['token'])){
					checkTokenExists($_POST['token'], $_POST['itemSource']);
					\OCP\Util::writeLog('sharing', "token:item_source " . $_POST['token'].":".$_POST['itemSource'], \OCP\Util::WARN);
					$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `token` = ? WHERE `item_source` = ? AND `share_type` = ? AND `token` = ?');
					$query->execute(array($_POST['token'], $_POST['itemSource'], $shareType, $token));
					$token = $_POST['token'];
				}
				if(is_string($token)){
					OC_JSON::success(array('data' => array('token' => $token, 'file_source'=>$itemMasterSource, 'item_source'=>intval($_POST['itemSource']))));
				}
				else{
					OC_JSON::success(array('file_source'=>$itemMasterSource));
				}
			}
			catch(Exception $exception){
				OC_JSON::error(array('data' => array('message' => $exception->getMessage())));
			}
		}
		break;
	case 'unshare':
		if(isset($_POST['shareType'])){
			if((int)$_POST['shareType']===OCP\Share::SHARE_TYPE_LINK && empty($_POST['shareWith'])) {
				$shareWith = null;
			}
			else {
				$shareWith = $_POST['shareWith'];
			}
			\OCP\Util::writeLog('sharing', "Unsharing " . $masterItemSource, \OCP\Util::WARN);
			try{
				$return = OCP\Share::unshare($_POST['itemType'], $masterItemSource, $_POST['shareType'], $shareWith);
			}
			catch(\Exception $e){
				\OCA\FilesSharding\Lib::restoreUser($user_id, true);
				\OC_JSON::error(array('data' => array('message' => $e->getMessage())));
			}
			\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
			($return) ? OC_JSON::success() : OC_JSON::error();
		}
		else{
			OC_JSON::error(array('data' => array('message' => 'Missing parameter')));
		}
		break;
	case 'setPermissions':
		if (isset($_POST['shareType']) && isset($_POST['shareWith']) && isset($_POST['permissions'])) {
			try{
				$return = OCP\Share::setPermissions(
						$_POST['itemType'],
						$masterItemSource,
						$_POST['shareType'],
						$_POST['shareWith'],
						$_POST['permissions']
						);
			}
			catch(\Exception $e){
				\OCA\FilesSharding\Lib::restoreUser($user_id, true);
				\OC_JSON::error(array('data' => array('message' => $e->getMessage())));
			}
			\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
			($return) ? OC_JSON::success() : OC_JSON::error();
		}
		else{
			OC_JSON::error(array('data' => array('message' => 'Missing parameter')));
		}
		break;
	case 'setExpirationDate':
		if(isset($_POST['date']) && isset($_POST['itemSource'])){
			try{
				$shareTime = isset($_POST['shareTime']) ? $_POST['shareTime'] : null;
				\OCP\Util::writeLog('sharing', "Setting expiration date " . $_POST['itemSource'] . "-->" .$_POST['date'], \OCP\Util::WARN);
				$return = OCP\Share::setExpirationDate($_POST['itemType'], $_POST['itemSource'], $_POST['date'], $shareTime);
			}
			catch(\Exception $e){
				\OCA\FilesSharding\Lib::restoreUser($user_id, true);
				OC_JSON::error(array('data' => array('message' => $e->getMessage())));
			}
			\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
			($return) ? OC_JSON::success() : OC_JSON::error();
		}
		else{
			OC_JSON::error(array('data' => array('message' => 'Missing parameter')));
		}
		break;
	case 'informRecipients':
		$l = OC_L10N::get('core');
		$itemType = $_POST['itemType'];
		$itemSource = $masterItemSource;
		$recipient = $_POST['recipient'];
		$recipientList = [];
		if($shareType===\OCP\Share::SHARE_TYPE_USER){
			$recipientList[] = $recipient;
		}
		elseif($shareType===\OCP\Share::SHARE_TYPE_GROUP){
			$recipientList = \OC_Group::usersInGroup($recipient);
		}
		// don't send a mail to the user who shared the file
		$recipientList = array_diff($recipientList, array(\OCP\User::getUser()));
		$mailNotification = new OC\Share\MailNotifications($user_id);
		try{
			$result = $mailNotification->sendInternalShareMail($recipientList, $itemSource, $itemType);
			\OCP\Share::setSendMailStatus($itemType, $itemSource, $shareType, $recipient, true);
		}
		catch(\Exception $e){
			\OCA\FilesSharding\Lib::restoreUser($user_id, true);
			\OC_JSON::error(array('data' => array('message' => $e->getMessage())));
		}
		\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
		if(empty($result)){
			OCP\JSON::success();
		}
		else{
			OCP\JSON::error(array(
				'data' => array(
					'message' => $l->t("Couldn't send mail to following users: %s ",
							implode(', ', $result)
						)
					)
				)
			);
		}
		break;
	case 'informRecipientsDisabled':
		$itemSource = $masterItemSource;
		$itemType = $_POST['itemType'];
		$recipient = $_POST['recipient'];
		try{
			\OCP\Share::setSendMailStatus($itemType, $itemSource, $shareType, $recipient, false);
		}
		catch(\Exception $e){
			\OCA\FilesSharding\Lib::restoreUser($user_id, true);
			\OC_JSON::error(array('data' => array('message' => $e->getMessage())));
		}
		\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
		OCP\JSON::success();
		break;

	case 'email':
		// read post variables
		$link = $_POST['link'];
		$file = $_POST['file'];
		$to_address = $_POST['toaddress'];
		$mailNotification = new \OC\Share\MailNotifications($user_id);
		$expiration = null;
		if(isset($_POST['expiration']) && $_POST['expiration'] !== '') {
			try{
				$date = new DateTime($_POST['expiration']);
				$expiration = $date->getTimestamp();
			}
			catch (Exception $e) {
				\OCP\Util::writeLog('sharing', "Couldn't read date: " . $e->getMessage(), \OCP\Util::ERROR);
			}
		}
		try{
			$result = $mailNotification->sendLinkShareMail($to_address, $file, $link, $expiration);
		}
		catch(\Exception $e){
			\OCA\FilesSharding\Lib::restoreUser($user_id, true);
			\OC_JSON::error(array('data' => array('message' => $e->getMessage())));
		}
		\OCA\FilesSharding\Lib::restoreUser($orig_user_id);
		if(empty($result)){
			\OCP\JSON::success();
		}
		else{
			$l = OC_L10N::get('core');
			OCP\JSON::error(
				array(
					'data' => array(
						'message' => $l->t("Couldn't send mail to following users: %s ",
							implode(', ', $result)
						)
					)
				)
			);
		}
		break;
}


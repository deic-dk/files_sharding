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

if(!OCA\FilesSharding\Lib::checkIP()){
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

if(isset($_POST['myItemSource'])&&$_POST['myItemSource']){
	// On the master, file_source holds the id of the dummy file
	//$_POST['itemSource'] = OCA\FilesSharding\Lib::getFileSource($_POST['myItemSource'], $_POST['itemType']);
	$masterItemSource = OCA\FilesSharding\Lib::getFileSource($_POST['myItemSource'], $_POST['itemType']);
}

switch ($_POST['action']) {
	case 'share':
		if (isset($_POST['shareType']) && isset($_POST['shareWith']) && isset($_POST['permissions'])) {
			try {
				// TODO: Get rid of this hack
				
				/*
				if(!empty($_POST['groupFolder']) && !empty($itemMasterSource) && OC\Files\Filesystem::file_exists($file_path)){
					$fields = "(`share_type`, `share_with`, `uid_owner`, `parent`, `item_type`, ".
					"`item_source`, `item_target`, `file_source`, `file_target`, `permissions`, ".
					"`stime`, `accepted`, `expiration`, `token`, `mail_send`)";
					$values = [$shareType, $shareWith, $user_id, -1, $_POST['itemType'], 
							$_POST['itemSource'], "/".$itemMasterSource, $itemMasterSource, $file_path, $_POST['permissions'],
							time(), 0, null, null, 0];
					$query = \OC_DB::prepare('INSERT INTO `*PREFIX*share`' . $fields .
							' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
					$query->execute($values);
					break;
				}
				*/
				
				// Create file/folder if not there
				$file_path = urldecode($_POST['itemPath']);
				if(($_POST['itemType'] === 'file' or $_POST['itemType'] === 'folder')){
					if(!OC\Files\Filesystem::file_exists($file_path)){
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
				$itemMasterSource = OCA\FilesSharding\Lib::getFileId($file_path, $user_id, $group);
				
				$shareType = (int)$_POST['shareType'];
				$shareWith = $_POST['shareWith'];
				$itemSourceName = isset($_POST['itemSourceName']) ? urldecode($_POST['itemSourceName']) : null;
				if ($shareType === OCP\Share::SHARE_TYPE_LINK && $shareWith == '') {
					$shareWith = null;
				}
				
				// If the user has migrated, a group folder will already have been shared - but now with the
				// file_source on the old home server. Just unshare it.
				$checkItemSource = OCA\FilesSharding\Lib::getItemSource($itemMasterSource, $_POST['itemType']);
				if($checkItemSource!=$itemMasterSource && $checkItemSource!=$_POST['myItemSource']){
					\OCP\Util::writeLog('sharing', "Unsharing group folder " . $itemMasterSource, \OCP\Util::WARN);
					OCP\Share::unshare($_POST['itemType'], $itemMasterSource, $_POST['shareType'], $shareWith);
				}
				
				$token = OCP\Share::shareItem(
						$_POST['itemType'],
						$itemMasterSource,
						$shareType,
						$shareWith,
						$_POST['permissions'],
						$itemSourceName,
						(!empty($_POST['expirationDate']) ? new \DateTime($_POST['expirationDate']) : null)
				);
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
				// FO: Allow any string to be used as token.
				if(isset($_POST['token']) && !empty($_POST['token'])){
					checkTokenExists($_POST['token'], $_POST['itemSource']);
					\OCP\Util::writeLog('sharing', "token:item_source " . $_POST['token'].":".$_POST['itemSource'], \OCP\Util::WARN);
					$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `token` = ? WHERE `item_source` = ? AND `share_type` = ? AND `token` = ?');
					$query->execute(array($_POST['token'], $_POST['itemSource'], $shareType, $token));
					$token = $_POST['token'];
				}
				if (is_string($token)) {
					OC_JSON::success(array('data' => array('token' => $token, 'file_source'=>$itemMasterSource)));
				} else {
					OC_JSON::success(array('file_source'=>$itemMasterSource));
				}
			} catch (Exception $exception) {
				OC_JSON::error(array('data' => array('message' => $exception->getMessage())));
			}
		}
		break;
	case 'unshare':
		if (isset($_POST['shareType']) && isset($_POST['shareWith'])) {
			if ((int)$_POST['shareType'] === OCP\Share::SHARE_TYPE_LINK && $_POST['shareWith'] == '') {
				$shareWith = null;
			} else {
				$shareWith = $_POST['shareWith'];
			}
			//$file_path = urldecode($_POST['itemPath']);
			//$return = OCP\Share::unshare($_POST['itemType'], $_POST['itemSource'], $_POST['shareType'], $shareWith);
			//$itemMasterSource = OCA\FilesSharding\Lib::getFileId($file_path, $user_id);
			//$itemMasterSource = OCA\FilesSharding\Lib::getFileSource($_POST['itemSource'], $_POST['itemType'], false);
			\OCP\Util::writeLog('sharing', "Unsharing " . $masterItemSource, \OCP\Util::WARN);
			$return = OCP\Share::unshare($_POST['itemType'], $masterItemSource, $_POST['shareType'], $shareWith);
			($return) ? OC_JSON::success() : OC_JSON::error();
		}
		break;
	case 'setPermissions':
		if (isset($_POST['shareType']) && isset($_POST['shareWith']) && isset($_POST['permissions'])) {
			$return = OCP\Share::setPermissions(
					$_POST['itemType'],
					$masterItemSource,
					$_POST['shareType'],
					$_POST['shareWith'],
					$_POST['permissions']
			);
			($return) ? OC_JSON::success() : OC_JSON::error();
		}
		break;
	case 'setExpirationDate':
		if (isset($_POST['date'])) {
			try {
				$shareTime = isset($_POST['shareTime']) ? $_POST['shareTime'] : null;
				$return = OCP\Share::setExpirationDate($_POST['itemType'], $masterItemSource, $_POST['date'], $shareTime);
				($return) ? OC_JSON::success() : OC_JSON::error();
			} catch (\Exception $e) {
				OC_JSON::error(array('data' => array('message' => $e->getMessage())));
			}
		}
		break;
	case 'informRecipients':
		$l = OC_L10N::get('core');
		$shareType = (int) $_POST['shareType'];
		$itemType = $_POST['itemType'];
		$itemSource = $masterItemSource;
		$recipient = $_POST['recipient'];
		
		if($shareType === \OCP\Share::SHARE_TYPE_USER) {
			$recipientList[] = $recipient;
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
			$recipientList = \OC_Group::usersInGroup($recipient);
		}
		// don't send a mail to the user who shared the file
		$recipientList = array_diff($recipientList, array(\OCP\User::getUser()));
		$mailNotification = new OC\Share\MailNotifications($user_id);
		$result = $mailNotification->sendInternalShareMail($recipientList, $itemSource, $itemType);

		\OCP\Share::setSendMailStatus($itemType, $itemSource, $shareType, $recipient, true);

		if (empty($result)) {
			OCP\JSON::success();
		} else {
			OCP\JSON::error(array(
			'data' => array(
			'message' => $l->t("Couldn't send mail to following users: %s ",
			implode(', ', $result)
			)
			)
			));
		}
		break;
	case 'informRecipientsDisabled':
		$itemSource = $masterItemSource;
		$shareType = $_POST['shareType'];
		$itemType = $_POST['itemType'];
		$recipient = $_POST['recipient'];
		\OCP\Share::setSendMailStatus($itemType, $itemSource, $shareType, $recipient, false);
		OCP\JSON::success();
		break;

	case 'email':
		// read post variables
		$link = $_POST['link'];
		$file = $_POST['file'];
		$to_address = $_POST['toaddress'];
		$mailNotification = new \OC\Share\MailNotifications($user_id);
		$expiration = null;
		if (isset($_POST['expiration']) && $_POST['expiration'] !== '') {
			try {
				$date = new DateTime($_POST['expiration']);
				$expiration = $date->getTimestamp();
			} catch (Exception $e) {
				\OCP\Util::writeLog('sharing', "Couldn't read date: " . $e->getMessage(), \OCP\Util::ERROR);
			}

		}

		$result = $mailNotification->sendLinkShareMail($to_address, $file, $link, $expiration);
		if(empty($result)) {
			\OCP\JSON::success();
		} else {
			$l = OC_L10N::get('core');
			OCP\JSON::error(array(
			'data' => array(
			'message' => $l->t("Couldn't send mail to following users: %s ",
			implode(', ', $result)
			)
			)
			));
		}

		break;
}


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

$user_id = $_GET['user_id'];
\OC_User::setUserId($user_id);
\OC_Util::setupFS($user_id);

if(OCP\App::isEnabled('user_group_admin')){
	$group = empty($_POST['group'])?'':$_POST['group'];
	if(!empty($group)){
		\OC\Files\Filesystem::tearDown();
		$groupDir = '/'.$user_id.'/user_group_admin/'.$group;
		\OC\Files\Filesystem::init($user_id, $groupDir);
	}
}

switch ($_GET['fetch']) {
	case 'getItemsSharedStatuses':
		if (isset($_GET['itemType'])) {
			//$return = OCP\Share::getItemsShared($_GET['itemType']);
			//\OCP\Util::writeLog('sharing', 'SHARED: '.$user_id.'-->'.serialize($return), \OCP\Util::WARN);
			$return = OCP\Share::getItemsShared($_GET['itemType'], OCP\Share::FORMAT_STATUSES);
			$master_to_slave_id_map = OCP\Share::getItemsShared($_GET['itemType']);
			// Set item_source - apparently OCP\Share::FORMAT_STATUSES causes this not to be set
			foreach($return as $item=>$data){
				foreach($master_to_slave_id_map as $item1=>$data1){
					if($master_to_slave_id_map[$item1]['file_source'] == $item){
						$return[$item]['item_source'] = $master_to_slave_id_map[$item1]['item_source'];
						break;
					}
				}
			}
			\OCP\Util::writeLog('sharing', 'SHARED: '.$user_id.'-->'.serialize($return), \OCP\Util::WARN);
			is_array($return) ? OC_JSON::success(array('data' => $return)) : OC_JSON::error();
		}
		break;
	case 'getItem':
		if(isset($_GET['myItemSource'])&&$_GET['myItemSource']){
			// On the master, file_source holds the id of the dummy file
			$_GET['itemSource'] = OCA\FilesSharding\Lib::getFileSource($_GET['myItemSource'], $_GET['itemType'],
					$_GET['sharedWithMe']);
		}
		if (isset($_GET['itemType']) && isset($_GET['itemSource'])
			/*&& isset($_GET['checkReshare'])
			&& isset($_GET['checkShares'])*/) {
			if ($_GET['checkReshare'] == 'true') {
				$reshare = OCP\Share::getItemSharedWithBySource(
					$_GET['itemType'],
					$_GET['itemSource'],
					OCP\Share::FORMAT_NONE,
					null,
					true,
					$user_id
				);
			} else {
				$reshare = false;
			}
			if ($_GET['checkShares'] == 'true') {
				$shares = OCP\Share::getItemShared(
					$_GET['itemType'],
					$_GET['itemSource'],
					OCP\Share::FORMAT_NONE,
					null,
					true,
					$user_id
				);
			} else {
				$shares = false;
			}
			$myshares = [];
			foreach($shares as $share){
				\OCP\Util::writeLog('sharing', 'SHARE: '.$user_id.':'.$_GET['itemSource'].
						'-->'.$share['path'].'-->'.$share['uid_owner'], \OCP\Util::WARN);
				if($share['uid_owner'] == $user_id){
					$myshares[] = $share;
				}
			}
			OC_JSON::success(array('data' => array('reshare' => $reshare, 'shares' => $myshares)));
		}
		break;
	case 'getShareFromId':
		if(isset($_GET['shareId'])){
			$sql = 'SELECT * FROM `*PREFIX*share` WHERE `id` = ?';
			$args = array($_GET['shareId']);
			$query = \OCP\DB::prepare($sql);
			$result = $query->execute($args);
			while($row = $result->fetchRow()){
				\OCP\Util::writeLog('sharing', 'SHARE: '.$user_id.':'.$_GET['shareId'].'-->'.serialize($row), \OCP\Util::WARN);
				OC_JSON::success(array('data' => $row));
			}
		}
		else{
			OC_JSON::error();
		}
		break;
	case 'getShareWithEmail':
		$result = array();
		if (isset($_GET['search'])) {
			$cm = OC::$server->getContactsManager();
			if (!is_null($cm) && $cm->isEnabled()) {
				$contacts = $cm->search($_GET['search'], array('FN', 'EMAIL'));
				foreach ($contacts as $contact) {
					if (!isset($contact['EMAIL'])) {
						continue;
					}

					$emails = $contact['EMAIL'];
					if (!is_array($emails)) {
						$emails = array($emails);
					}

					foreach($emails as $email) {
						$result[] = array(
							'id' => $contact['id'],
							'email' => $email,
							'displayname' => $contact['FN'],
						);
					}
				}
			}
		}
		OC_JSON::success(array('data' => $result));
		break;
	case 'getShareWith':
		if (isset($_GET['search'])) {
			$shareWithinGroupOnly = OC\Share\Share::shareWithGroupMembersOnly();
			$shareWith = array();
	// 		if (OC_App::isEnabled('contacts')) {
	// 			// TODO Add function to contacts to only get the 'fullname' column to improve performance
	// 			$ids = OC_Contacts_Addressbook::activeIds();
	// 			foreach ($ids as $id) {
	// 				$vcards = OC_Contacts_VCard::all($id);
	// 				foreach ($vcards as $vcard) {
	// 					$contact = $vcard['fullname'];
	// 					if (stripos($contact, $_GET['search']) !== false
	// 						&& (!isset($_GET['itemShares'])
	// 						|| !isset($_GET['itemShares'][OCP\Share::SHARE_TYPE_CONTACT])
	// 						|| !is_array($_GET['itemShares'][OCP\Share::SHARE_TYPE_CONTACT])
	// 						|| !in_array($contact, $_GET['itemShares'][OCP\Share::SHARE_TYPE_CONTACT]))) {
	// 						$shareWith[] = array('label' => $contact, 'value' => array('shareType' => 5, 'shareWith' => $vcard['id']));
	// 					}
	// 				}
	// 			}
	//			}
			$groups = OC_Group::getGroups($_GET['search']);
			if ($shareWithinGroupOnly) {
				$usergroups = OC_Group::getUserGroups(OC_User::getUser());
				$groups = array_intersect($groups, $usergroups);
			}
			if(OCP\App::isEnabled('user_group_admin') && !empty($_GET['search'])){
				$ownedGroups = OC_User_Group_Admin_Util::getOwnerGroups(OC_User::getUser(), false, $_GET['search'].'%');
				$ownedGroupNames = array_column($ownedGroups, 'gid');
				foreach($ownedGroupNames as $gid){
					if(!in_array($gid, $groups)){
						$groups[] = $gid;
					}
				}
			}
			$count = 0;
			$users = array();
			$limit = 0;
			$offset = 0;
			while ($count < 15 && count($users) == $limit) {
				$limit = 15 - $count;
				if ($shareWithinGroupOnly) {
					$users = OC_Group::displayNamesInGroups($usergroups, $_GET['search'], $limit, $offset);
				} else {
					$users = OC_User::getDisplayNames($_GET['search'], $limit, $offset);
					// share alias mock-up; added by Christian
					if(\OCP\App::isEnabled('user_alias')){
						require_once('user_alias/lib/user_alias.php');
						$users += OC_User_Alias::getAliases($_GET['search'], $limit, $offset);
					}
				}
				$offset += $limit;
				$itemSharesUids = empty($_GET['itemSharesUids'])?array():
					json_decode($_GET['itemSharesUids']);
				foreach ($users as $uid => $displayName) {
					if (!in_array($uid, $itemSharesUids)
						&& $uid != OC_User::getUser()) {
						$shareWith[] = array(
							'label' => $displayName,
							'value' => array(
								'shareType' => OCP\Share::SHARE_TYPE_USER,
								'shareWith' => $uid)
						);
						$count++;
					}
				}
			}
			$count = 0;

			// enable l10n support
			$l = OC_L10N::get('core');
			
			$itemSharesGroups = empty($_GET['itemSharesGroups'])?array():
				json_decode($_GET['itemSharesGroups']);
				
			foreach ($groups as $group) {
				if ($count < 15) {
					if (!in_array($group, $itemSharesGroups)) {
						$shareWith[] = array(
							'label' => $group,
							'value' => array(
								'shareType' => OCP\Share::SHARE_TYPE_GROUP,
								'shareWith' => $group
							)
						);
						$count++;
					}
				} else {
					break;
				}
			}
			$sorter = new \OC\Share\SearchResultSorter($_GET['search'],
													   'label',
													   new \OC\Log());
			usort($shareWith, array($sorter, 'sort'));
			OC_JSON::success(array('data' => $shareWith));
		}
		break;
}

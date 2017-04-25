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
		if (isset($_GET['itemType'])
			&& isset($_GET['itemSource'])
			&& isset($_GET['checkReshare'])
			&& isset($_GET['checkShares'])) {
			if ($_GET['checkReshare'] == 'true') {
				$reshare = OCP\Share::getItemSharedWithBySource(
					$_GET['itemType'],
					$_GET['itemSource'],
					OCP\Share::FORMAT_NONE,
					null,
					true
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
					true
				);
			} else {
				$shares = false;
			}
			\OCP\Util::writeLog('sharing', 'SHARES: '.$user_id.':'.$_GET['itemSource'].'-->'.serialize($shares), \OCP\Util::WARN);
			OC_JSON::success(array('data' => array('reshare' => $reshare, 'shares' => $shares)));
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
				foreach ($users as $uid => $displayName) {
					if ((!isset($_GET['itemShares'])
						|| !is_array($_GET['itemShares'][OCP\Share::SHARE_TYPE_USER])
						|| !in_array($uid, $_GET['itemShares'][OCP\Share::SHARE_TYPE_USER]))
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

			foreach ($groups as $group) {
				if ($count < 15) {
					if (!isset($_GET['itemShares'])
						|| !isset($_GET['itemShares'][OCP\Share::SHARE_TYPE_GROUP])
						|| !is_array($_GET['itemShares'][OCP\Share::SHARE_TYPE_GROUP])
						|| !in_array($group, $_GET['itemShares'][OCP\Share::SHARE_TYPE_GROUP])) {
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

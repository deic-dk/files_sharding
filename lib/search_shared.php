<?php
/**
 * ownCloud
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
 *
 */

namespace OCA\FilesSharding;

use \OCP\User;
use \OCA\meta_data\Tags;
use \OCA\FilesSharding;


class SearchShared extends \OC_Search_Provider {

  function search($query) {
		$user_id = \OCP\USER::getUser();
		$sharedItems = Lib::getItemsSharedWithUser($user_id);
		if(empty($sharedItems)){
			return array();
		}
		$allServers = Lib::getServersList();
		$owners = array();
		$serverIDs = array();
		$currentServerId = Lib::dbLookupServerId($_SERVER['REMOTE_ADDR']);
		foreach($sharedItems as $item){
			if(!in_array($item['owner'], $owners)){
				$owners[] = $item['owner'];
				$serverID = Lib::lookupServerIdForUser($user_id);
				/*if($serverID==$currentServerId){
					continue;
				}*/
				if(in_array($serverID, $serverIDs)){
					continue;
				}
				$serverIDs[] = $serverID;
			}
		}
		\OCP\Util::writeLog('search', 'Searching servers '.serialize($allServers), \OC_Log::WARN);
		$results = array();
		foreach($allServers as $server){
			if(!in_array($server['id'], $serverIDs)){
				continue;
			}
			if(isset($server['internal_url']) && !empty($server['internal_url'])){
				$matches = Lib::ws('search', Array('user_id'=>$user_id, 'query'=>$query), true, true,
						$server['internal_url']);
				$res = array();
				foreach($sharedItems as $item){
					foreach($matches as $match){
						if($item['item_source']===$match['fileid']){
							$res[] = $match;
						}
					}
				}
				//$results[$server['url']] = $res;
				$results = array_merge($results, $res);
			}
		}
		return $results;
		
  }
}


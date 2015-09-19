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
		$serverUsers = Lib::getServerUsers($sharedItems);
		$storage = \OC\Files\Filesystem::getStorage('/');
		$cache = $storage->getCache();
		$results = array();
		$allServers = Lib::getServersList();
		foreach($allServers as $server){
			if(!array_key_exists($server['id'], $serverUsers)){
				continue;
			}
			\OCP\Util::writeLog('search', 'Searching server '.$server['internal_url'], \OC_Log::WARN);
			if(!isset($server['internal_url']) && !empty($server['internal_url'])){
				continue;
			}
			foreach($serverUsers[$server['id']] as $owner){
				$matches = Lib::ws('search', Array('user_id'=>$owner, 'query'=>$query), true, true,
						$server['internal_url']);
				$res = array();
				foreach($matches as $match){
					foreach($sharedItems as $item){
						if(in_array($match, $res)){
							continue;
						}
						if(isset($item['fileid']) && isset($match['id']) && $item['fileid']==$match['id']){
							$match['server'] = $server['internal_url'];
							$match['owner'] = $owner;
							$res[] = $match;
							continue;
						}
						// Check if match is in a shared folder or subfolders thereof
						if($cache->getMimetype($item['mimetype']) === 'httpd/unix-directory'){
							$len = strlen($item['owner_path'])+1;
							\OCP\Util::writeLog('search', 'Matching '.$match['link'].':'.$item['owner_path'].' --> '.$server['internal_url'].
										' --> '.$owner, \OC_Log::WARN);
							if(substr($match['link'], 0, $len)===$item['owner_path'].'/'){
								$match['server'] = $server['internal_url'];
								$match['owner'] = $owner;
								$res[] = $match;
								continue;
							}
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


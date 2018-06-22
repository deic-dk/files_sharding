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

//$dir = urldecode($_GET['dir']);
// The superglobals $_GET and $_REQUEST are already decoded
$dir = isset($_GET['dir'])?$_GET['dir']:'';
$id = isset($_GET['id']) ? $_GET['id'] : null;
$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$sortAttribute = isset($_GET['sortAttribute']) ? $_GET['sortAttribute'] : '';
$sortDirection = isset($_GET['sortDirection']) ? $_GET['sortDirection'] : '';
$group = isset($_GET['group']) ? $_GET['group'] : '';

if(!empty($id) && !empty($owner)){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
	if(!empty($group)){
		\OC\Files\Filesystem::tearDown();
		$groupDir = '/'.$owner.'/user_group_admin/'.$group;
		\OC\Files\Filesystem::init($owner, $groupDir);
	}
	$path = \OC\Files\Filesystem::getPath($id);
	\OCP\Util::writeLog('files_sharding', 'Path: '.$path, \OC_Log::WARN);
	$files = \OCA\Files\Helper::getFiles($path, $sortAttribute, $sortDirection);
}
else{
	$user_id = $_GET['user_id'];
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	\OCP\Util::writeLog('files_sharding', 'No id or owner: '.$owner.':'.$id, \OC_Log::WARN);
	$files = \OCA\Files\Helper::getFiles($dir, $sortAttribute, $sortDirection);
}

$data = OCA\Files\Helper::formatFileInfos($files);

foreach($data as &$file){
	$file['path'] = $path.'/'.$file['name'];
}

\OCP\Util::writeLog('files_sharding', 'Returning files for '.$owner.':'.$id.':'.$dir.':'.$path.'-->'.serialize($data), \OC_Log::DEBUG);

OCP\JSON::encodedPrint($data);


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
//$path = isset($_GET['path'])? urldecode($_GET['path']) : '';
// The superglobals $_GET and $_REQUEST are already decoded
$path = isset($_GET['path'])? $_GET['path'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : null;
$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

if($owner){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
}
elseif($user_id){
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	\OCP\Util::writeLog('files_sharding', 'No id or owner: '.$owner.':'.$id, \OC_Log::WARN);
}

if($id){
	$path = \OC\Files\Filesystem::getPath($id);
}

\OCP\Util::writeLog('files_sharding', 'Path: '.$path, \OC_Log::WARN);
$info = \OC\Files\Filesystem::getFileInfo($path);

if(!$info){
	\OCP\Util::writeLog('files_sharding', 'File not found '.$owner.'-->'.$id.':'.$path, \OC_Log::WARN);
	OCP\JSON::encodedPrint('');
	exit;
}

$data = $info->getData();
//$data['directory'] = $path;
$data['path'] = $info->getpath();
//$data['storage'] = $info->getStorage();
//\OCP\Util::writeLog('files_sharding', 'STORAGE: '.serialize($data['storage']), \OC_Log::WARN);
$data['internalPath'] = $info->getInternalPath();

\OCP\Util::writeLog('files_sharding', 'Returning file info for '.$owner.'-->'.$id.':'.$path.':'.$data['path'], \OC_Log::WARN);

OCP\JSON::encodedPrint($data);


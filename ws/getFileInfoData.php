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

$dir = urldecode($_GET['dir']);
$id = isset($_GET['id']) ? $_GET['id'] : null;
$owner = isset($_GET['owner']) ? $_GET['owner'] : '';

if(!empty($id) && !empty($owner)){
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
	//$view = new \OC\Files\View('/'.$owner.'/files');
	//$path = $view->getPath($id);
	$path = \OC\Files\Filesystem::getPath($id);
	\OCP\Util::writeLog('files_sharding', 'Path: '.$path, \OC_Log::WARN);
	//$dirInfo = $view->getFileInfo($path);
	$dirInfo = \OC\Files\Filesystem::getFileInfo($path);
}
else{
	$user_id = $_GET['user_id'];
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	\OCP\Util::writeLog('files_sharding', 'No id or owner: '.$owner.':'.$id, \OC_Log::WARN);
	$dirInfo = \OC\Files\Filesystem::getFileInfo($dir);
	$path = $dir;
}
$data = $dirInfo->getData();
$data['directory'] = $path;
$data['path'] = $dirInfo->getpath();
$data['storage'] = $dirInfo->getStorage();
$data['internalPath'] = $dirInfo->getInternalPath();

\OCP\Util::writeLog('files_sharding', 'Returning file info for '.$owner.'-->'.$id.':'.$dir.':'.$path.':'.$data['path'], \OC_Log::WARN);

OCP\JSON::encodedPrint($data);


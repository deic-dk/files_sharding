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

$itemType = $_GET['itemType'];
$itemSource = $_GET['itemSource'];

$user_id = $_GET['user_id'];
\OC_User::setUserId($user_id);
\OC_Util::setupFS($user_id);

\OCP\Util::writeLog('files_sharding', 'Getting item shared '.\OC_User::getUser().":".$itemType.":".$itemSource, \OC_Log::WARN);

if(isset($_GET['myItemSource'])&&$_GET['myItemSource']){
	// On the master, file_source holds the id of the dummy file
	$itemSource = OCA\FilesSharding\Lib::getFileSource($_GET['myItemSource'], $_GET['itemType']);
}

$itemShared = \OCP\Share::getItemShared($itemType, empty($itemSource)?null:$itemSource);

// Nope - this should be done on the calling slave
/*foreach($itemShared as &$share){
	$path = \OC\Files\Filesystem::getPath($share['file_source']);
	$share['path'] = $path;
}*/

\OCP\Util::writeLog('files_sharding', 'Returning item shared '.$itemSource.'-->'.serialize($itemShared), \OC_Log::WARN);

OCP\JSON::encodedPrint($itemShared);


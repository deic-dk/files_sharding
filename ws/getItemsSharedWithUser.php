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

$itemType = isset($_GET['itemType'])?$_GET['itemType']:'file';
$format = isset($_GET['format'])?$_GET['format']:\OCP\Share::FORMAT_NONE;
$parameters = isset($_GET['parameters'])?$_GET['parameters']:null;
$limit = isset($_GET['limit'])?$_GET['limit']:-1;
$includeCollections = isset($_GET['includeCollections'])?$_GET['includeCollections']:false;

$user_id = $_GET['user_id'];

//$itemsShared = OCP\Share::getItemsSharedWithUser($itemType, $user_id, $format, $parameters, $limit, $includeCollections);
$itemsShared = OCA\FilesSharding\Lib::getItemsSharedWithUser($user_id, $itemType);

foreach($itemsShared as &$item){
	$item['owner_path'] = OCA\FilesSharding\Lib::getFilePath($item['fileid'], $item['uid_owner']);
}
\OCP\Util::writeLog('files_sharding', 'Returning items shared '.serialize($itemsShared), \OC_Log::DEBUG);

OCP\JSON::encodedPrint($itemsShared);


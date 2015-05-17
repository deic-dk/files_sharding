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
$format = isset($_GET['format'])?$_GET['format']:\OCP\Share::FORMAT_NONE;
$parameters = isset($_GET['parameters'])?$_GET['parameters']:null;
$limit = isset($_GET['limit'])?$_GET['limit']:-1;
$includeCollections = isset($_GET['includeCollections'])?$_GET['includeCollections']:false;

$user_id = $_GET['user_id'];
\OC_User::setUserId($user_id);
\OC_Util::setupFS($user_id);


$itemsShared = \OCP\Share::getItemsSharedWith($itemType, $format, $parameters, $limit, $includeCollections);

\OCP\Util::writeLog('files_sharding', 'Returning items shared '.serialize($itemsShared), \OC_Log::WARN);

OCP\JSON::encodedPrint($itemsShared);


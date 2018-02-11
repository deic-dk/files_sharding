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

$search = $_GET['search'];
$limit = isset($_GET['limit'])?$_GET['limit']:null;
$offset = isset($_GET['offset'])?$_GET['offset']:null;

$users = OC_User::getDisplayNames($search, $limit, $offset);
if(\OCP\App::isEnabled('user_alias')){
	require_once('user_alias/lib/user_alias.php');
	$users += OC_User_Alias::getAliases($search, $limit, $offset);
}

\OCP\Util::writeLog('files_sharding', 'Returning users '.serialize($users), \OC_Log::WARN);

OCP\JSON::encodedPrint($users);



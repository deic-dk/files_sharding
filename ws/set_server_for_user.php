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
	$ret['error'] = "Network not secure";
	http_response_code(401);
	exit;
}

include_once("files_sharding/lib/lib_files_sharding.php");

$priority = isset($_GET['priority'])?$_GET['priority']:null;
$access = isset($_GET['access'])?$_GET['access']:null;
$last_sync = isset($_GET['last_sync'])?$_GET['last_sync']:0;
$user_id = isset($_GET['user_id'])?$_GET['user_id']:null;
$server_id = isset($_GET['server_id'])?$_GET['server_id']:null;
if(empty($server_id) &&
		(int)$priority!=OCA\FilesSharding\Lib::$USER_SERVER_PRIORITY_DISABLED &&
		(int)$access!==OCA\FilesSharding\Lib::$USER_ACCESS_NONE){
	$server_id = OCA\FilesSharding\Lib::dbLookupServerId($_SERVER['REMOTE_ADDR']);
}

$ret = OCA\FilesSharding\Lib::dbSetServerForUser($user_id, $server_id, $priority, $access, $last_sync);

if($ret){
	OCP\JSON::encodedPrint($ret);
}
else{
	OCP\JSON::error();
}

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
//OCP\JSON::checkLoggedIn();
if(!OCA\FilesSharding\Lib::checkIP()){
	$ret['error'] = "Network not secure";
	http_response_code(401);
	exit;
}

include_once("files_sharding/lib/lib_files_sharding.php");

$folder = $_POST['folder'];
$internal = !empty($_POST['internal'])&&$_POST['internal']=='yes';
$user_id = $_POST['user_id'];
$group = empty($_POST['group'])?'':$_POST['group'];
$url = OCA\FilesSharding\Lib::dbGetServerForFolder($folder, $user_id, $internal, $group);
$ret = empty($url)?'':$url;

OCP\JSON::encodedPrint($ret);

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

$ret = array();

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}


if(!isset($_POST['user_id']) || !isset($_POST['folder'])){
	http_response_code(401);
	exit;
}

$folder = $_POST['folder'];
$user_id = $_POST['user_id'];
$group = empty($_POST['group'])?'':$_POST['group'];

if(!OCA\FilesSharding\Lib::removeDataFolder($folder, $user_id, $group)){
	$ret['error'] = "Failed removing data folder";
}
else{
	$ret = OCA\FilesSharding\Lib::dbGetDataFoldersList($user_id);
}

OCP\JSON::encodedPrint($ret);



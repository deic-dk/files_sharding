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
	http_response_code(401);
	exit;
}

//\OC_Util::setupFS($user_id);

$user_id = $_GET['user'];

// Get all files/folders shared by user
$sharedItems = \OCA\FilesSharding\Lib::getItemSharedByUser($user_id);

// Correction array to send to master
$newIdMap = array('user_id'=>$user_id);

foreach($sharedItems as $share){
	$path = \OC\Files\Filesystem::getPath($share['file_source']);
	// Get files/folders owned by user (locally) with the path of $share
	$file = OCA\FilesSharding\Lib::dbGetUserFile('files'.$path, $user_id);
	\OCP\Util::writeLog('files_sharding', 'Share: '.$file['path'].'==='.'files'.$path, \OC_Log::WARN);
	if($share['item_source']!=$file['fileid']){
		$newIdMap[$share['item_source']] = $file['fileid'];
	}
}

// Send the correction array to master
$ret = \OCA\FilesSharding\Lib::ws('update_share_item_sources', $newIdMap);

if($ret){
	OCP\JSON::encodedPrint($newIdMap);
}
else{
	OCP\JSON::error($ret);
}

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

$user_id = $_GET['user'];

//\OC_Util::setupFS($user_id);

function isInteger($val) {
	return isset($val) && (!empty($val) || $val==='0' || $val===0) && is_int($val);
}
$map = array_values(array_filter($_GET, array(__CLASS__, 'isInteger')));

// Send the correction array to master
$ret = \OCA\FilesSharding\Lib::updateShareItemSources($user_id, $map);

OCP\JSON::encodedPrint($ret);

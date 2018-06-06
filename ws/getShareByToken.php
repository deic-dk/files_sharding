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

$token = $_GET['t'];
$checkPasswordProtection = false;
if(isset($_GET['checkPasswordProtection'])){
	$checkPasswordProtection = $_GET['checkPasswordProtection']==='1';
}
if(isset($_GET['public_link_authenticated'])){
	$public_link_authenticated = $_GET['public_link_authenticated'];
}

if(!empty($public_link_authenticated)){
	\OC::$session->set('public_link_authenticated', $public_link_authenticated);
}

$linkItem = OCP\Share::getShareByToken($token, $checkPasswordProtection);

\OCP\Util::writeLog('files_sharding', 'Returning linkItem '.serialize($linkItem), \OC_Log::WARN);

OCP\JSON::encodedPrint($linkItem);


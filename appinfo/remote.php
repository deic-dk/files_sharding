<?php

/**
* files_sharding - ownCloud plugin for horisontal scaling
*
* @author  Frederik Orellana
* @copyright 2014 Frederik Orellana frederik@orellana.dk
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
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

require_once 'sharder/lib/lib_sharder.php';
require_once 'sharder/lib/Normalizer.php';

OC_Log::write('sharder','Remote access',OC_Log::INFO);
OCP\App::checkAppEnabled('sharder');

$FILES_BASE = "/files";
$PUBLIC_BASE = "/public";
$DATA_BASE = "/Data";

$requestFix = new Normalizer($_SERVER['REQUEST_URI']);
$requestUri = $requestFix->normalize();

$baseUri = "/remote.php/dav";
// Known aliases
if(strpos($requestUri, $FILES_BASE."/")===0){
	$baseuri = $FILES_BASE;
}
elseif(strpos($requestUri, $PUBLIC_BASE."/")===0){
	$baseuri = $PUBLIC_BASE;
}

$reqPath = substr($requestUri, strlen($baseUri));

if(strpos($requestUri, $PUBLIC_BASE."/")===0){
	$token = preg_replace("/^\/([^\/]+)\/*/", "$1", $reqPath);
	$user = OC_Sharder::getShareOwner($token);
}
else{
	$user = $_SERVER['PHP_AUTH_USER'];
}

// Sharded paths take first priority
if(strpos($reqPath, $DATA_BASE."/")===0 && strlen($reqPath)>strlen($DATA_BASE)+2){
	$dataPath = substr($reqPath, strlen($DATA_BASE)+1);
	$dataFolder = preg_replace("/\/.*$", "", $dataPath);
}

$server = OC_Sharder::getServerForFolder($dataFolder);

// Default to sharding on user
if($server===null || trim($server)===''){
	// If I'm the head-node, look up in DB
	if($_SERVER['REMOTE_ADDR']===$_SERVER['SERVER_ADDR']){
		$server = OC_Sharder::dbLookupServerForUser($user);
	}
	// Otherwise, ask head-node
	else{
		$server = OC_Sharder::wsLookupServerForUser($user);
	}
}

// Serve
if($server===$_SERVER['SERVER_NAME']){
	include('chooser/appinfo/remote.php');
}
// Redirect
else{
	OC_Log::write('sharder','Redirecting to: https://' . $server . $reqPath, OC_Log::WARN);
	header('Location: https://' . $server . $reqPath);
}

exit();





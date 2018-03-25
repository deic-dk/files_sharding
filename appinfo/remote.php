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

require_once 'files_sharding/lib/lib_files_sharding.php';
require_once 'files_sharding/lib/Normalizer.php';

OC_Log::write('files_sharding','Remote access',OC_Log::DEBUG);
OCP\App::checkAppEnabled('files_sharding');
OCP\App::checkAppEnabled('chooser');

$FILES_BASE = "/files";
$PUBLIC_BASE = "/public";

$requestFix = new URL\Normalizer($_SERVER['REQUEST_URI']);
$requestUri = $requestFix->normalize();

$baseUri = OC::$WEBROOT."/remote.php/davs";
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
	$user = OCA\FilesSharding\Lib::getShareOwner($token);
}
else{
	if(!empty($_SERVER['PHP_AUTH_USER'])){
		$user = $_SERVER['PHP_AUTH_USER'];
	}
	else{
		$user = \OC_User::getUser();
	}
}

// Sharded paths take first priority
if(OCA\FilesSharding\Lib::inDataFolder($reqPath)){
	$serverUrl = OCA\FilesSharding\Lib::getNextServerForFolder($reqPath, $user);
}

// Trusting HTTP_REFERER. Not really safe, but worst case: a malicious user cannot find his files or fills up a machine.
// Best case: we save a rest lookup.
$redirected_from = null;
if(isset($_SERVER['HTTP_REFERER'])){
	$parsedReferer = parse_url($_SERVER['HTTP_REFERER']);
	$redirected_from = isset($parsedReferer['host']) ? $parsedReferer['host'] : null;
}
$masterUrl = OCA\FilesSharding\Lib::getMasterURL();
$parsedMaster = parse_url($masterUrl);
$master = isset($parsedMaster['host']) ? $parsedMaster['host'] : null;

// Serve
if($redirected_from===$master /*|| empty($redirected_from)*/){
	\OCP\Util::writeLog('files_sharding', 'Serving', \OC_Log::INFO);
	include('chooser/appinfo/remote.php');
}
else{
	// Default to sharding on user
	if(!isset($serverUrl) || trim($serverUrl)===''){
		$serverUrl = OCA\FilesSharding\Lib::getServerForUser($user);
	}
	$parsedUrl = parse_url($serverUrl);
	$server = isset($parsedUrl['host']) ? $parsedUrl['host'] : null;
	if(isset($_SERVER['HTTP_HOST']) && $server===$_SERVER['HTTP_HOST'] ||
			isset($_SERVER['SERVER_NAME']) && $server===$_SERVER['SERVER_NAME']){
		\OCP\Util::writeLog('files_sharding', 'Serving, '.$server, \OC_Log::INFO);
		include('chooser/appinfo/remote.php');
	}
	// Redirect
	elseif(isset($server)){
		OC_Log::write('files_sharding','Redirecting to: ' . $server .' :: '. $_SERVER['REQUEST_URI'], OC_Log::WARN);
		// In the case of a move request, a header will contain the destination
		// with hard-wired host name. Change this host name on redirect.
		if(!empty($_SERVER['HTTP_DESTINATION'])){
			$destination = preg_replace('|^'.$masterUrl.'|', $serverUrl, $_SERVER['HTTP_DESTINATION']);
			header("Destination: " . $destination);
		}
		header("HTTP/1.1 307 Temporary Redirect");
		header("Location: " . $serverUrl . $_SERVER['REQUEST_URI']);
	}
	else{
		// Don't give a not found - sync clients will start deleting local files.
		//http_response_code(404);
		//throw new \Exception('Invalid Host '.$server);
		\OCP\Util::writeLog('files_sharding', 'Serving, '.$server, \OC_Log::INFO);
		include('chooser/appinfo/remote.php');
	}	
}

exit();


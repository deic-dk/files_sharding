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

$SHARED_BASE = OC::$WEBROOT."/shared";
$FILES_BASE = OC::$WEBROOT."/files";
$GRID_BASE = OC::$WEBROOT."/grid";
$PUBLIC_BASE = OC::$WEBROOT."/public";
$GROUP_BASE = OC::$WEBROOT."/group";
$SHARINGIN_BASE_NC = OC::$WEBROOT."/sharingin/remote.php/webdav";
$SHARINGIN_BASE = OC::$WEBROOT."/sharingin";
$SHARINGOUT_BASE = OC::$WEBROOT."/sharingout";
$INGEST_BASE = OC::$WEBROOT."/ingest";
$ADDCERT_BASE = OC::$WEBROOT."/addcert";
$REMOVECERT_BASE = OC::$WEBROOT."/removecert";

// User VLANs allowing private data transfers to/from Kube containers
$tnet = \OCP\Config::getSystemValue('uservlannet', '');
$tnet = trim($tnet);
$tnets = explode(' ', $tnet);
$uservlannets = array_map('trim', $tnets);
if(count($uservlannets)==1 && substr($uservlannets[0], 0, 10)==='USER_VLAN_'){
	$uservlannets = [];
}

$requestFix = new URL\Normalizer($_SERVER['REQUEST_URI']);
$requestUri = $requestFix->normalize();

$group = '';

$baseUri = OC::$WEBROOT."/remote.php/davs";
// Known aliases
if(strpos($requestUri, $SHARED_BASE."/")===0){
	$baseuri = $SHARED_BASE;
}
elseif(strpos($requestUri, $GRID_BASE."/")===0){
	$baseuri = $GRID_BASE;
}
elseif(strpos($requestUri, $PUBLIC_BASE."/")===0){
	$baseuri = $PUBLIC_BASE;
}
elseif(strpos($requestUri, $GROUP_BASE."/")===0){
	$baseuri = $GROUP_BASE;
	$group = preg_replace("|^".$GROUP_BASE."/|", "", $requestUri);
	$group = preg_replace("|/.*$|", "", $group);
}
elseif(strpos($requestUri, $SHARINGIN_BASE_NC."/")===0){
	$baseuri = $SHARINGIN_BASE_NC;
}
elseif(strpos($requestUri, $SHARINGIN_BASE."/")===0){
	$baseuri = $SHARINGIN_BASE;
}
elseif(strpos($requestUri, $SHARINGOUT_BASE."/")===0){
	$baseuri = $SHARINGOUT_BASE;
}
elseif(strpos($requestUri, $INGEST_BASE."/")===0){
	$baseuri = $INGEST_BASE;
}
elseif(strpos($requestUri, $PUBLIC_BASE."/")===0){
	$baseuri = $PUBLIC_BASE;
}
elseif(strpos($requestUri, $ADDCERT_BASE."/")===0){
	$baseuri = $ADDCERT_BASE;
}
elseif(strpos($requestUri, $REMOVECERT_BASE."/")===0){
	$baseuri = $REMOVECERT_BASE;
}

$reqPath = preg_replace('|^'.$baseUri.'|', "", $requestUri);

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
if(OCA\FilesSharding\Lib::inDataFolder($reqPath, $user, $group)){
	$serverUrl = OCA\FilesSharding\Lib::getServerForFolder($reqPath, $user, false);
	$serverInternalUrl = OCA\FilesSharding\Lib::getServerForFolder($reqPath, $user, true);
	// Check if this is a MKCOL and if there's enough space.
	// If not, assign a new server to this folder.
	// For this request we still create the folder on this server, but for subsequent requests in this folder
	// we will then redirect.
	if(strtolower(strtolower($_SERVER['REQUEST_METHOD']))=='mkcol' && OCP\App::isEnabled('files_sharding')){
		$newServerUrl = OCA\FilesSharding\Lib::setServerForFolder($reqPath, $user, $group);
		// Also create the folder on the new server
		$arr = array('user_id'=>$user, 'dir'=>urlencode(dirname($reqPath)), 'foldername'=>urlencode(basename($reqPath)),
				'group'=>urlencode($group));
		if(!empty($newServerUrl) && (empty($serverUrl) || $newServerUrl!=$serverUrl)){
			$result = \OCA\FilesSharding\Lib::ws('newfolder', $arr, true, true, $newServerUrl);
		}
	}
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
$masterInternalUrl = OCA\FilesSharding\Lib::getMasterInternalURL();
$parsedMasterInternal = parse_url($masterInternalUrl);
$masterInternal = isset($parsedMasterInternal['host']) ? $parsedMasterInternal['host'] : null;

// Serve
if($redirected_from===$master || $redirected_from===$masterInternal || /*empty($redirected_from) ||*/
		// Internal replication/backup sync clients
		stripos($_SERVER['HTTP_USER_AGENT'], "(FreeBSD) mirall")!==false &&
		OCA\FilesSharding\Lib::checkIP() ||
		preg_match('|^/*sharingout/.*|', $reqPath)){
	\OCP\Util::writeLog('files_sharding', 'Serving', \OC_Log::INFO);
	include('chooser/appinfo/remote.php');
}
else{
	// Default to sharding on user
	if(!isset($serverUrl) || trim($serverUrl)===''){
		$serverUrl = OCA\FilesSharding\Lib::getServerForUser($user, false);
		$serverInternalUrl = OCA\FilesSharding\Lib::getServerForUser($user, true);
	}
	$parsedUrl = parse_url($serverUrl);
	$parsedInternalUrl = parse_url($serverInternalUrl);
	$server = isset($parsedUrl['host']) ? $parsedUrl['host'] : null;
	$serverInternal = isset($parsedInternalUrl['host']) ? $parsedInternalUrl['host'] : null;
	if(!empty($_SERVER['HTTP_HOST']) && !empty($server) && $server===$_SERVER['HTTP_HOST'] ||
			!empty($_SERVER['SERVER_NAME']) && !empty($server) && $server===$_SERVER['SERVER_NAME'] ||
			/*proxying shared files or backing up*/
			!empty($_SERVER['HTTP_HOST']) && !empty($serverInternal) && $serverInternal===$_SERVER['HTTP_HOST'] ||
			!empty($_SERVER['SERVER_NAME']) && !empty($serverInternal) && $serverInternal===$_SERVER['SERVER_NAME'] ||
			/*/storage to kube*/
			!empty($_SERVER['HTTP_HOST']) && !empty($uservlannets) &&
			array_sum(array_map(function($net){return strpos($_SERVER['HTTP_HOST'], $net)===0?1:0;}, $uservlannets))>0 ||
			!empty($_SERVER['SERVER_NAME']) && !empty($uservlannets) &&
			array_sum(array_map(function($net){return strpos($_SERVER['SERVER_NAME'], $net)===0?1:0;}, $uservlannets))>0
			){
		\OCP\Util::writeLog('files_sharding', 'Serving, '.$server, \OC_Log::INFO);
		include('chooser/appinfo/remote.php');
	}
	// Redirect
	elseif(isset($server)){
		OC_Log::write('files_sharding','Redirecting to: ' . $server .' :: '. $baseuri .' :: '.$reqPath.' :: '.$requestUri, OC_Log::WARN);
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


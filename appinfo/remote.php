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
$vnet = \OCP\Config::getSystemValue('uservlannet', '');
$vnet = trim($vnet);
$vnets = explode(' ', $vnet);
$uservlannets = array_map('trim', $vnets);
if(count($uservlannets)==1 && substr($uservlannets[0], 0, 10)==='USER_VLAN_'){
	$uservlannets = [];
}
// Trusted internal networks
$tnet = \OCP\Config::getSystemValue('trustednet', '');
$tnet = trim($tnet);
$tnets = explode(' ', $tnet);
$trustednets = array_map('trim', $tnets);
if(count($trustednets)==1 && substr($trustednets[0], 0, 8)==='TRUSTED_'){
	$trustednets = [];
}

$requestFix = new URL\Normalizer($_SERVER['REQUEST_URI']);
$requestUri = $requestFix->normalize();

$group = '';

$baseUri = OC::$WEBROOT."/remote.php/davs";
// Known aliases
if(strpos($requestUri, $SHARED_BASE."/")===0){
	$baseUri = $SHARED_BASE;
}
elseif(strpos($requestUri, $GRID_BASE."/")===0){
	$baseUri = $GRID_BASE;
}
elseif(strpos($requestUri, $PUBLIC_BASE."/")===0){
	$baseUri = $PUBLIC_BASE;
}
elseif(strpos($requestUri, $GROUP_BASE."/")===0){
	$baseUri = $GROUP_BASE;
	$group = preg_replace("|^".$GROUP_BASE."/|", "", $requestUri);
	$group = preg_replace("|/.*$|", "", $group);
}
elseif(strpos($requestUri, $SHARINGIN_BASE_NC."/")===0){
	$baseUri = $SHARINGIN_BASE_NC;
}
elseif(strpos($requestUri, $SHARINGIN_BASE."/")===0){
	$baseUri = $SHARINGIN_BASE;
}
elseif(strpos($requestUri, $SHARINGOUT_BASE."/")===0){
	$baseUri = $SHARINGOUT_BASE;
}
elseif(strpos($requestUri, $INGEST_BASE."/")===0){
	$baseUri = $INGEST_BASE;
}
elseif(strpos($requestUri, $ADDCERT_BASE."/")===0){
	$baseUri = $ADDCERT_BASE;
}
elseif(strpos($requestUri, $REMOVECERT_BASE."/")===0){
	$baseUri = $REMOVECERT_BASE;
}

$reqPath = preg_replace('|^'.$baseUri.'|', "", $requestUri);

if(strpos($requestUri, $PUBLIC_BASE."/")===0){
	$token = preg_replace("/^\/([^\/]+)$/", "$1", $reqPath);
	if(empty($token) || $token==$reqPath){
		$token = preg_replace("/^\/([^\/]+)\/.*$/", "$1", $reqPath);
	}
	if(!empty($token) && $token!=$reqPath){
		$res = OCA\FilesSharding\Lib::getPublicShare($token);
		if(!empty($res) && !empty($res['uid_owner'])){
			$user = $res['uid_owner'];
			$sharePath = OCA\FilesSharding\Lib::getFilePath($res['item_source'], $user);
			$baseUri = $PUBLIC_BASE."/".$token;
			$reqPath = preg_replace('|^'.$baseUri.'|', "", $requestUri);
			$_SERVER['BASE_DIR'] = '/files/'.'/'.$sharePath.$reqPath;
			$baseUri = $requestUri;
			$_SERVER['BASE_URI'] = $baseUri;
		}
	}
	if(empty($user)){
		// getPublicShare failed. This may or may not be a share from a group folder. Just try.
		$token = preg_replace("/^\/([^\/]+)\/([^\/]+)\/*.*$/", "$2", $reqPath);
		$group = preg_replace("/^\/([^\/]+)\/([^\/]+)\/*.*$/", "$1", $reqPath);
		if(!empty($group) && !empty($token) && $token!=$reqPath){
			$res = OCA\FilesSharding\Lib::getPublicShare($token);
			if(!empty($res) && !empty($res['uid_owner'])){
				$user = $res['uid_owner'];
				$sharePath = OCA\FilesSharding\Lib::getFilePath($res['item_source'], $user, urldecode($group));
				\OC_User::setUserId($user);
				$baseUri = $PUBLIC_BASE."/".$group."/".$token;
				$reqPath = preg_replace('|^'.$baseUri.'|', "", $requestUri);
				$_SERVER['BASE_DIR'] = '/'.$user.'/user_group_admin/'.urldecode($group).$sharePath.$reqPath;
				$baseUri = $requestUri;
				$_SERVER['BASE_URI'] = $baseUri;
			}
			\OCP\Util::writeLog('files_sharding', 'Request user: '.$user.'-->'.$baseUri.'-->'.serialize($res), \OC_Log::WARN);
		}
	}
	// If share is password protected, check password
	if(!empty($res) && !empty($res['share_with'])){
		$forcePortable = (CRYPT_BLOWFISH != 1);
		$hasher = new PasswordHash(8, $forcePortable);
		if(!($hasher->CheckPassword($_SERVER['PHP_AUTH_PW'].OC_Config::getValue('passwordsalt', ''),
				$res['share_with']))) {
					header('HTTP/1.0 403 Forbidden');
					exit();
				}
	}
	// If share is readonly, set $_SERVER['READ_ONLY']
	if(!empty($res) && !empty($res['permissions'])<=\OCP\PERMISSION_CREATE){
		$_SERVER['READ_ONLY'] = true;
	}
	
}
else{
	if(!empty($_SERVER['PHP_AUTH_USER'])){
		$user = $_SERVER['PHP_AUTH_USER'];
	}
	else{
		$user = \OC_User::getUser();
	}
}

\OCP\Util::writeLog('files_sharding', 'Request path: '.$reqPath.'-->'.(empty($_SERVER['BASE_DIR'])?'':$_SERVER['BASE_DIR']).'-->'.$requestUri.'-->'.$baseUri.'-->'.$PUBLIC_BASE, \OC_Log::INFO);

// Sharded paths take first priority
if(!empty($user) && OCA\FilesSharding\Lib::inDataFolder($reqPath, $user, $group)){
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
			\OCA\FilesSharding\Lib::ws('newfolder', $arr, true, true, $newServerUrl);
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
		preg_match('|^/*sharingout/.*|', $reqPath) ||
		// chooser/share_objecttree will take care of redirecting sharingin
		$baseUri == $SHARINGIN_BASE || $baseUri == $SHARINGIN_BASE_NC
		){
			\OCP\Util::writeLog('files_sharding', 'Serving, '.$user.': '.
					$redirected_from.'<->'.$master.'<->'.$masterInternal.':'.$baseUri .'<->'. $SHARINGIN_BASE, \OC_Log::INFO);
	include('chooser/appinfo/remote.php');
}
else{
	// Default to sharding on user
	if(!isset($serverUrl) || trim($serverUrl)===''){
		$serverUrl = OCA\FilesSharding\Lib::getServerForUser($user, false);
		$serverInternalUrl = OCA\FilesSharding\Lib::getServerForUser($user, true);
		$serverPrivatelUrl = OCA\FilesSharding\Lib::internalToPrivate($serverInternalUrl);
	}
	$parsedUrl = parse_url($serverUrl);
	$parsedInternalUrl = parse_url($serverInternalUrl);
	$parsedPrivateUrl = parse_url($serverPrivatelUrl);
	$server = isset($parsedUrl['host']) ? $parsedUrl['host'] : null;
	$serverInternal = isset($parsedInternalUrl['host']) ? $parsedInternalUrl['host'] : null;
	$serverPrivate = isset($parsedPrivateUrl['host']) ? $parsedPrivateUrl['host'] : null;
	if(!empty($_SERVER['HTTP_HOST']) && !empty($server) && $server===$_SERVER['HTTP_HOST'] ||
			!empty($_SERVER['SERVER_NAME']) && !empty($server) && $server===$_SERVER['SERVER_NAME'] ||
			/*proxying shared files or backing up*/
			!empty($_SERVER['HTTP_HOST']) && !empty($serverInternal) && $serverInternal===$_SERVER['HTTP_HOST'] ||
			!empty($_SERVER['SERVER_NAME']) && !empty($serverInternal) && $serverInternal===$_SERVER['SERVER_NAME'] ||
			/*/storage to kube*/
			!empty($_SERVER['HTTP_HOST']) && !empty($serverPrivate) && $serverPrivate===$_SERVER['HTTP_HOST'] ||
			!empty($_SERVER['SERVER_NAME']) && !empty($serverPrivate) && $serverPrivate===$_SERVER['SERVER_NAME']
			//!empty($_SERVER['HTTP_HOST']) && !empty($uservlannets) &&
			//array_sum(array_map(function($net){return strpos($_SERVER['HTTP_HOST'], $net)===0?1:0;}, $uservlannets))>0 ||
			//!empty($_SERVER['SERVER_NAME']) && !empty($uservlannets) &&
			//array_sum(array_map(function($net){return strpos($_SERVER['SERVER_NAME'], $net)===0?1:0;}, $uservlannets))>0
			){
		\OCP\Util::writeLog('files_sharding', 'Serving, '.$server, \OC_Log::INFO);
		include('chooser/appinfo/remote.php');
	}
	// Redirect
	elseif(isset($server)){
		$redirectUrl = $serverUrl;
		// Redirect internally if an internal request is made
		if(!empty($_SERVER['HTTP_HOST']) &&
			 array_sum(array_map(function($net){return strpos($_SERVER['HTTP_HOST'], $net)===0?1:0;}, $uservlannets))>0 ||
			 !empty($_SERVER['SERVER_NAME']) &&
			 !empty($uservlannets) &&
			 array_sum(array_map(function($net){return strpos($_SERVER['SERVER_NAME'], $net)===0?1:0;}, $uservlannets))>0){
			$redirectUrl = $serverPrivatelUrl;
		}
		elseif(!empty($_SERVER['HTTP_HOST']) &&
			 array_sum(array_map(function($net){return strpos($_SERVER['HTTP_HOST'], $net)===0?1:0;}, $trustednets))>0 ||
			 !empty($_SERVER['SERVER_NAME']) && !empty($uservlannets) &&
			 array_sum(array_map(function($net){return strpos($_SERVER['SERVER_NAME'], $net)===0?1:0;}, $trustednets))>0){
			$redirectUrl = $serverInternalUrl;
		}
		OC_Log::write('files_sharding','Redirecting to: ' . $redirectUrl . ' :: ' . $server .' :: '. $baseUri .' :: '.$reqPath.' :: '.$requestUri, OC_Log::WARN);
		// In the case of a move request, a header will contain the destination
		// with hard-wired host name. Change this host name on redirect.
		if(!empty($_SERVER['HTTP_DESTINATION'])){
			$destination = preg_replace('|^'.$masterUrl.'|', $redirectUrl, $_SERVER['HTTP_DESTINATION']);
			header("Destination: " . $destination);
		}
		header("HTTP/1.1 307 Temporary Redirect");
		header("Location: " . $redirectUrl . $_SERVER['REQUEST_URI']);
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


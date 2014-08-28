<?php

/**
* ownCloud
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

/**
 * On the server, /files, /public and /remote.php/webdav should be
 * mod_rewritten to /remote.php/dav, on slaves, to /remote.php/mydav
 * - like below.
 */
 
/*

#
# Pretty and persistent URLs  
#
# Web interface - shares
RewriteRule ^shared/(.*)$ public.php?service=files&t=$1 [QSA,L]
# WebDAV - personal
RewriteRule ^files/(.*) remote.php/mydav/$1 [QSA,L]
# WebDAV - shares
RewriteRule ^public/(.*) remote.php/mydav/$1 [QSA,L]
#
# Hide /Data
#
RewriteCond %{HTTP_USER_AGENT} ^.*(csyncoC|mirall)\/.*$
#RewriteCond %{HTTP_USER_AGENT} ^.*(curl|cadaver)\/.*$
RewriteCond %{REQUEST_METHOD} PROPFIND
RewriteRule ^remote.php/webdav/*$ https://data.deic.dk/remote.php/mydav/ [QSA,L]

*/

require_once 'sharder/lib/lib_sharder.php';

OC_Log::write('sharder','Remote access',OC_Log::INFO);
OCP\App::checkAppEnabled('sharder');

$baseUri = "/remote.php/dav";
// Known aliases
if(strpos($_SERVER['REQUEST_URI'], "/files/")===0){
	$baseuri = "/files";
}
elseif(strpos($_SERVER['REQUEST_URI'], "/public/")===0){
	$baseuri = "/public";
}

$reqPath = substr($_SERVER['REQUEST_URI'], strlen($baseUri));

if(strpos($_SERVER['REQUEST_URI'], "/public/")===0){
	$token = preg_replace("/^\/([^\/]+)\/*/", "$1", $reqPath);
	$user = OC_Sharder::getShareOwner($token);
}
else{
	$user = $_SERVER['PHP_AUTH_USER'];
}

// Sharded paths take first priority
$ocPath = OC_Sharder::getOcPath($user, $_SERVER['REQUEST_URI']);
$server = OC_Sharder::getServerForPath($ocPath);

// Default to sharding on user
if($server===null || trim($server)===''){
	$server = OC_Sharder::getServerForUser($user);
}

// Redirect
if($server===$_SERVER['SERVER_NAME']){
	include('chooser/appinfo/remote.php');
}
else{
	$redirectUri = preg_replace("/^\/remote.php\/dav\/", "/remote.php/webdav/", $_SERVER['REQUEST_URI']);
	OC_Log::write('sharder','User: '.$user.', server: '.$server.$redirectUri, OC_Log::WARN);
	header('Location: https://' . $server . $reqPath);
}

exit();





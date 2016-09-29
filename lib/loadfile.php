<?php
/**
 * ownCloud - files_texteditor
 *
 * @author Tom Needham
 * @author Frederik Orellana
 * @copyright 2011 Tom Needham contact@tomneedham.com
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
 * This file is meant to be included from either an ajax or a we script.
 */

$user = \OCP\User::getUser();
$dir = isset($_GET['dir']) ? $_GET['dir'] : '';
$filename = isset($_GET['file']) ? $_GET['file'] : '';

$id = isset($_GET['id']) ? $_GET['id'] : '';
$owner = isset($_GET['owner']) ? $_GET['owner'] : '';
$group = isset($_GET['group']) ? $_GET['group'] : '';
$group_dir_owner = $user;

if(empty($filename)){
	OCP\JSON::error(array('data' => array( 'message' => 'Invalid file path supplied.')));
	exit;
}

if(!empty($owner) && $owner!=$user){
	$group_dir_owner = $owner;
	\OC_Util::tearDownFS();
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
}

if(!empty($group) && !empty($group_dir_owner)){
	\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
}
if(!empty($id)){
	$path = \OC\Files\Filesystem::getPath($id);
	$dir = substr($path, 0, strrpos($path, '/'));
}
\OCP\Util::writeLog('files_texteditor', 'ID: '.$id.', user: '.\OCP\User::getUser(), \OC_Log::WARN);
// Set the session key for the file we are about to edit.
$path = $dir.'/'.$filename;
$writeable = \OC\Files\Filesystem::isUpdatable($path);
$mime = \OC\Files\Filesystem::getMimeType($path);
$mtime = \OC\Files\Filesystem::filemtime($path);
$filecontents = \OC\Files\Filesystem::file_get_contents($path);
$encoding = mb_detect_encoding($filecontents."a", "UTF-8, WINDOWS-1252, ISO-8859-15, ISO-8859-1, ASCII", true);
if ($encoding == "") {
	// set default encoding if it couldn't be detected
	$encoding = 'ISO-8859-15';
}
$filecontents = iconv($encoding, "UTF-8", $filecontents);
OCP\JSON::success(array('data' => array(
	'filecontents' => $filecontents,
	'writeable' => $writeable,
	'mime' => $mime,
	'mtime' => $mtime))
);
if(!empty($owner) && $owner!=$user){
	\OC_Util::tearDownFS();
	\OC_User::setUserId($user);
	\OC_Util::setupFS($user);
}


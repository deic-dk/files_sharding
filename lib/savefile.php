<?php
/**
 * ownCloud - files_texteditor
 *
 * @author Tom Needham
 * @author Frederik Orellana
 * @copyright 2013 Tom Needham tom@owncloud.com
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

$path = isset($_POST['path']) ? $_POST['path'] : '';
$user = isset($_POST['user']) ? $_POST['user'] : '';
$id = isset($_POST['id']) ? $_POST['id'] : '';
$owner = isset($_POST['owner']) ? $_POST['owner'] : '';
$group = isset($_POST['group']) ? $_POST['group'] : '';
$group_dir_owner = $user;

if(!empty($owner) && $owner!=$user){
	$group_dir_owner = $owner;
	\OC_Util::tearDownFS();
	\OC_User::setUserId($owner);
	\OC_Util::setupFS($owner);
}
else{
	$currentUser = \OCP\User::getUser();
	if(empty($user) && !empty($currentUser)){
		\OC_Util::setupFS();
	}
	else{
		$group_dir_owner = $user;
		\OC_Util::tearDownFS();
		\OC_User::setUserId($user);
		\OC_Util::setupFS($user);
	}
}
if(!empty($group) && !empty($group_dir_owner)){
	\OC\Files\Filesystem::tearDown();
	$groupDir = '/'.$group_dir_owner.'/user_group_admin/'.$group;
	\OC\Files\Filesystem::init($group_dir_owner, $groupDir);
}
if(!empty($id)){
	$path = \OC\Files\Filesystem::getPath($id);
}

\OCP\Util::writeLog('files_texteditor', 'PATH: '.$id.':'.$path.', user: '.\OCP\User::getUser(), \OC_Log::WARN);

// Get parameters
$filecontents = $_POST['filecontents'];
$mtime = isset($_POST['mtime']) ? $_POST['mtime'] : '';

$l = OC_L10N::get('files_texteditor');

if($path != '' && $mtime != '') {
	// Get file mtime
	$filemtime = \OC\Files\Filesystem::filemtime($path);
	/*if($mtime != $filemtime) { // This fires randomly. No idea why the file's mtime is changed spontaneously
		// Then the file has changed since opening
		OCP\JSON::error(array('data' => array( 'message' =>
				($l->t('Cannot save file as it has been modified since opening')).$mtime.' != '.$filemtime)));
		OCP\Util::writeLog(
			'files_texteditor',
				"File: ".$path." modified since opening. ".$mtime.' != '.$filemtime,
			OCP\Util::ERROR
		);
	} else {*/
		// File same as when opened, save file
		if(\OC\Files\Filesystem::isUpdatable($path)) {
			$filecontents = iconv(mb_detect_encoding($filecontents), "UTF-8", $filecontents);
			\OC\Files\Filesystem::file_put_contents($path, $filecontents);
			// Clear statcache
			clearstatcache();
			// Get new mtime
			$newmtime = \OC\Files\Filesystem::filemtime($path);
			$newsize = \OC\Files\Filesystem::filesize($path);
			OCP\JSON::success(array('data' => array('mtime' => $newmtime, 'size' => $newsize)));
		} else {
			// Not writeable!
			OCP\JSON::error(array('data' => array( 'message' => $l->t('Insufficient permissions'))));
			OCP\Util::writeLog(
				'files_texteditor',
				"User does not have permission to write to file: ".$path,
				OCP\Util::ERROR
				);
		}
	//}
} else if($path == '') {
	OCP\JSON::error(array('data' => array( 'message' => $l->t('File path not supplied'))));
	OCP\Util::writeLog('files_texteditor','No file path supplied', OCP\Util::ERROR);
} else if($mtime == '') {
	OCP\JSON::error(array('data' => array( 'message' => $l->t('File mtime not supplied'))));
	OCP\Util::writeLog('files_texteditor','No file mtime supplied' ,OCP\Util::ERROR);
}

if(!empty($owner) && $owner!=$user){
	\OC_User::setUserId($user);
	\OC_Util::setupFS($user);
}


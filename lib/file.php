<?php
/**
* ownCloud
*
* @author Bjoern Schiessle, Michael Gapczynski
* @copyright 2012 Michael Gapczynski <mtgap@owncloud.com>
 *           2014 Bjoern Schiessle <schiessle@owncloud.com>
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
*/

require_once('lib/public/share.php');

class OC_Shard_Backend_File implements OCP\Share_Backend_File_Dependent {

	const FORMAT_SHARED_STORAGE = 0;
	const FORMAT_GET_FOLDER_CONTENTS = 1;
	const FORMAT_FILE_APP_ROOT = 2;
	const FORMAT_OPENDIR = 3;
	const FORMAT_GET_ALL = 4;
	const FORMAT_PERMISSIONS = 5;
	const FORMAT_TARGET_NAMES = 6;

	private $path;

	public function isValidSource($itemSource, $uidOwner) {
		$path = \OC\Files\Filesystem::getPath($itemSource);
		if ($path) {
			// FIXME: attributes should not be set here,
			// keeping this pattern for now to avoid unexpected
			// regressions
			$this->path = basename($path);
			return true;
		}
		return false;
	}

	public function getFilePath($itemSource, $uidOwner) {
		if (isset($this->path)) {
			$path = $this->path;
			$this->path = null;
			return $path;
		}
		return false;
	}
	
	/**
	 * check if file name already exists and generate unique target
	 * 
	 * Adapted version of generateUniqueTarget from helper.php
	 *
	 * @param string $path
	 * @param array $excludeList
	 * @param \OC\Files\View $view
	 * @return string $path
	 */
	private static function myGenerateUniqueTarget($path, $excludeList, $view) {
		
		\OC_Log::write('core', 'Non-unique path ' .$path, \OC_Log::WARN);
		return $path;
	
		// We don't need this: We only show shared files in the browser under 'Shared with me'
		// and they are identified by ID, not name. So the same name may appear several times,
		// which can be confusing, but IMO less confusing than changing the target name.
		
		/*$pathinfo = pathinfo($path);
		$ext = (isset($pathinfo['extension'])) ? '.'.$pathinfo['extension'] : '';
		$name = $pathinfo['filename'];
		$dir = $pathinfo['dirname'];
		$i = 2;
		while ($view->file_exists($path) || in_array($path, $excludeList)) {
			$path = \OC\Files\Filesystem::normalizePath($dir . '/' . $name . ' ('.$i.')' . $ext);
			$i++;
		}
		
		\OC_Log::write('core', 'Unique path ' .$path, \OC_Log::WARN);
		
		return $path;*/
	}
	

	/**
	 * create unique target
	 * @param string $filePath
	 * @param string $shareWith
	 * @param string $exclude
	 * @return string
	 */
	public function generateTarget($filePath, $shareWith, $exclude = null) {
		
		\OC_Log::write('core', 'Generating target for ' . $filePath, \OC_Log::WARN);
		
		$shareFolder = \OCA\Files_Sharing\Helper::getShareFolder();
		$target = \OC\Files\Filesystem::normalizePath($shareFolder . '/' . basename($filePath));

		// for group shares we return the target right away
		if ($shareWith === false) {
			return $target;
		}

		\OC\Files\Filesystem::initMountPoints($shareWith);
		$view = new \OC\Files\View('/' . $shareWith . '/files');

		if (!$view->is_dir($shareFolder)) {
			$dir = '';
			$subdirs = explode('/', $shareFolder);
			foreach ($subdirs as $subdir) {
				$dir = $dir . '/' . $subdir;
				if (!$view->is_dir($dir)) {
					$view->mkdir($dir);
				}
			}
		}

		if(\OCA\FilesSharding\Lib::isMaster()){
			$excludeList = \OCP\Share::getItemsSharedWithUser('file', $shareWith, self::FORMAT_TARGET_NAMES);
		}
		else{
			$excludeList =  \OCA\FilesSharding\Lib::ws('getItemsSharedWithUser',
					array('user_id' => \OC_User::getUser(), 'format' => self::FORMAT_TARGET_NAMES));
		}
		
		if (is_array($exclude)) {
			$excludeList = array_merge($excludeList, $exclude);
		}
				
		return self::myGenerateUniqueTarget($target, $excludeList, $view);
	}

	public function formatItems($items, $format, $parameters = null) {
		if ($format == self::FORMAT_SHARED_STORAGE) {
			// Only 1 item should come through for this format call
			return array(
				'parent' => $items[key($items)]['parent'],
				'path' => $items[key($items)]['path'],
				'storage' => $items[key($items)]['storage'],
				'permissions' => $items[key($items)]['permissions'],
				'uid_owner' => $items[key($items)]['uid_owner'],
			);
		} else if ($format == self::FORMAT_GET_FOLDER_CONTENTS) {
			$files = array();
			foreach ($items as $item) {
				$file = array();
				$file['fileid'] = $item['file_source'];
				$file['storage'] = $item['storage'];
				$file['path'] = $item['file_target'];
				$file['parent'] = $item['file_parent'];
				$file['name'] = basename($item['file_target']);
				$file['mimetype'] = $item['mimetype'];
				$file['mimepart'] = $item['mimepart'];
				$file['mtime'] = $item['mtime'];
				$file['encrypted'] = $item['encrypted'];
				$file['etag'] = $item['etag'];
				$file['uid_owner'] = $item['uid_owner'];
				$file['displayname_owner'] = $item['displayname_owner'];
				$file['permissions'] = $item['permissions'];

				$storage = \OC\Files\Filesystem::getStorage('/'.\OCP\User::getUser().'/');
				$cache = $storage->getCache();
				if ($item['encrypted'] or ($item['unencrypted_size'] > 0 and $cache->getMimetype($item['mimetype']) === 'httpd/unix-directory')) {
					$file['size'] = $item['unencrypted_size'];
					$file['encrypted_size'] = $item['size'];
				} else {
					$file['size'] = $item['size'];
				}
				$files[] = $file;
			}
			return $files;
		} else if ($format == self::FORMAT_OPENDIR) {
			$files = array();
			foreach ($items as $item) {
				$files[] = basename($item['file_target']);
			}
			return $files;
		} else if ($format == self::FORMAT_GET_ALL) {
			$ids = array();
			foreach ($items as $item) {
				$ids[] = $item['file_source'];
			}
			return $ids;
		} else if ($format === self::FORMAT_PERMISSIONS) {
			$filePermissions = array();
			foreach ($items as $item) {
				$filePermissions[$item['file_source']] = $item['permissions'];
			}
			return $filePermissions;
		} else if ($format === self::FORMAT_TARGET_NAMES) {
			$targets = array();
			foreach ($items as $item) {
				$targets[] = $item['file_target'];
			}
			return $targets;
		}
		return array();
	}

}

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

// Init owncloud
require_once('base.php');
require_once('apps/chooser/appinfo/apache_note_user.php');

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

include_once("files_sharding/lib/loadfile.php");

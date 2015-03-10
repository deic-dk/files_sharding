<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\User::checkLoggedIn();

OCP\Util::addscript('files_sharding', 'personalsettings');

$errors = Array();

$tmpl = new OCP\Template('files_sharding', 'personalsettings');
$tmpl->assign('sites_list', OCA\FilesSharding\Lib::dbGetSitesList());

$user_id = OCP\USER::getUser();
$user_server_id = OCA\FilesSharding\Lib::dbLookupServerIdForUser($user_id, 0);
if(empty($user_server_id)){
	$user_server_url = OCA\FilesSharding\Lib::getMasterURL();
	$user_server_hostname = OCA\FilesSharding\Lib::getMasterHostName();
	$user_server_id =  OCA\FilesSharding\Lib::dbLookupServerId($user_server_hostname);
}
else{
	$user_server_url = OCA\FilesSharding\Lib::dbLookupServerURL($user_server_id);
}
$user_home_site = OCA\FilesSharding\Lib::dbGetSite($user_server_id);

$tmpl->assign('user_server_id', $user_server_id);
$tmpl->assign('user_server_url', $user_server_url);
$tmpl->assign('user_home_site', $user_home_site);

$user_backup_server_id = OCA\FilesSharding\Lib::dbLookupServerIdForUser($user_id, 1);
if(!empty($user_backup_server_id)){
	$user_backup_server_url = OCA\FilesSharding\Lib::dbLookupServerURL($user_backup_server_id);
	$user_backup_site = OCA\FilesSharding\Lib::dbGetSite($user_backup_server_id);
	$tmpl->assign('user_backup_server_id', $user_backup_server_id);
	$tmpl->assign('user_backup_server_url', $user_backup_server_url);
	$tmpl->assign('user_backup_site', $user_backup_site);
}


return $tmpl->fetchPage();
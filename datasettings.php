<?php

OCP\JSON::checkAppEnabled('files_sharding');
OCP\User::checkLoggedIn();

//OCP\Util::addscript('files_sharding', 'personalsettings');
//OCP\Util::addStyle('files_sharding', 'personalsettings');

$errors = Array();

$tmpl = new OCP\Template('files_sharding', 'datasettings');

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

// List of sites to choose for backup
$user_server_internal_url = OCA\FilesSharding\Lib::dbLookupInternalServerURL($user_server_id);
$user_server_internal_host = parse_url($user_server_internal_url, PHP_URL_HOST);
$internalIpIsNumeric = ip2long($user_server_internal_host) !== false;
if($internalIpIsNumeric){
	$tmpl->assign('sites_list', OCA\FilesSharding\Lib::dbGetSitesList(true));
}
else{
	// We cannot backup from sites that do  not have a numeric internal IP.
	// Non-numeric internal IP sites are those not on the trusted net, i.e.
	// the command-line sync client used, cannot be authenticated by IP.
	// The command-line sync client unfortunately cannot use a client certificate for authentication.
	$tmpl->assign('sites_list', array());
}

$user_backup_server_id = OCA\FilesSharding\Lib::dbLookupServerIdForUser($user_id, 1);
if(!empty($user_backup_server_id)){
	$user_backup_server_url = OCA\FilesSharding\Lib::dbLookupServerURL($user_backup_server_id);
	$user_backup_site = OCA\FilesSharding\Lib::dbGetSite($user_backup_server_id);
	$user_backup_server_lastsync = OCA\FilesSharding\Lib::dbLookupLastSync($user_backup_server_id, $user_id);
	$user_backup_server_nextsync = (empty($user_backup_server_lastsync)?time():$user_backup_server_lastsync) + OCA\FilesSharding\Lib::$USER_SYNC_INTERVAL_SECONDS;
	$tmpl->assign('user_backup_server_id', $user_backup_server_id);
	$tmpl->assign('user_backup_server_url', $user_backup_server_url);
	$tmpl->assign('user_backup_site', $user_backup_site);
	$tmpl->assign('user_backup_server_lastsync', $user_backup_server_lastsync);
	$tmpl->assign('user_backup_server_nextsync', $user_backup_server_nextsync);
}
$yesterday = time() - 24*60*60;
$synced_user_backup_server_id = OCA\FilesSharding\Lib::dbLookupServerIdForUser($user_id, 1, $yesterday);
if(!empty($synced_user_backup_server_id)){
	$synced_user_backup_server_url = OCA\FilesSharding\Lib::dbLookupServerURL($user_backup_server_id);
	$synced_user_backup_site = OCA\FilesSharding\Lib::dbGetSite($user_backup_server_id);
	$tmpl->assign('synced_user_backup_server_id', $synced_user_backup_server_id);
	$tmpl->assign('synced_user_backup_server_url', $synced_user_backup_server_url);
	$tmpl->assign('synced_user_backup_site', $synced_user_backup_site);
}

return $tmpl->fetchPage();
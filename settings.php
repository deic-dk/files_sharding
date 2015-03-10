<?php

OC_Util::checkAdminUser();

OCP\Util::addscript('files_sharding', 'settings');

$tmpl = new OCP\Template( 'files_sharding', 'settings');

$tmpl->assign('servers_list', OCA\FilesSharding\Lib::dbGetServersList());

return $tmpl->fetchPage();
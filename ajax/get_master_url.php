<?php

OCP\JSON::checkAppEnabled('files_sharding');

$internal = isset($_GET['internal'])?$_GET['internal'] && $_GET['internal']!=="false" && $_GET['internal']!=="no":false;
$user_id = isset($_GET['user_id'])?$_GET['user_id']:null;

$url = $internal?OCA\FilesSharding\Lib::getMasterInternalURL():OCA\FilesSharding\Lib::getMasterURL();

$ret = Array('url' => $url);

OCP\JSON::encodedPrint($ret);

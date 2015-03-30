<?php

// Check if we are a user
OC_JSON::checkLoggedIn();

$query=(isset($_GET['query']))?$_GET['query']:'';
$ret = array();
if($query) {
	$results = OCA\FilesSharding\Lib::searchAllServers($query);
	foreach($results as $url=>$result){
		foreach($result as &$res){
			$res['link'] = $url.$res['link'];
		}
		$ret = array_merge($ret, $result);
		\OCP\Util::writeLog('search', 'Search results: '.json_encode($result), \OC_Log::WARN);
	}
	OC_JSON::encodedPrint($ret);
}
else {
	echo 'false';
}

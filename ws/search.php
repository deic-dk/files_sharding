<?php

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

class OC_Sharded_Search_Result extends OC_Search_Result {
	
	public $parentdir;
	public $parentid;
	
	public function __construct($searchResult) {
		$this->id = $searchResult->id;
		$this->name = $searchResult->name;
		$this->link = $searchResult->link;
		$this->type = $searchResult->type;
	}
}

$query=(isset($_POST['query']))?$_POST['query']:'';
$user_id=(isset($_POST['user_id']))?$_POST['user_id']:'';

if(!isset($_POST['user_id'])){
	http_response_code(401);
	exit;
}

if($query) {
	\OC_User::setUserId($user_id);
	\OC_Util::setupFS($user_id);
	OC_Search::removeProvider('OC\Search\Provider\File');
	OC_Search::removeProvider('OCA\Search_Lucene\Lucene');
	OC_Search::removeProvider('OCA\FilesSharding\SearchShared');
	$result = \OC::$server->getSearch()->search($query);
	$shardedResult = [];
	foreach($result as $res){
		if(empty($res->link) || empty($res->id)){// The search indices are apparently often messed up, discard bad hits
			continue;
		}
		$shRes = new OC_Sharded_Search_Result($res);
		$shRes->userid = $user_id;
		$shRes->parentdir = dirname($res->link);
		$shRes->parentid = empty($shRes->parentdir)?'':\OCA\FilesSharding\Lib::getFileId($shRes->parentdir, $user_id);
		$shardedResult[] = $shRes;
	}
	\OCP\Util::writeLog('search', 'Search results: '.json_encode($shardedResult), \OC_Log::WARN);
	OC_JSON::encodedPrint($shardedResult);
}
else {
	echo 'false';
}
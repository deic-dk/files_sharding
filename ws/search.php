<?php

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

class OC_Sharded_Search_Result extends OC_Search_Result {
	
	public $parentdir;
	public $parentid;
	public $userid;
	
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
	$extendedResult = [];
	foreach($result as $res){
		if(empty($res->link)){
			continue;
		}
		$exRes = new OC_Sharded_Search_Result($res);
		$exRes->userid = $user_id;
		$exRes->parentdir = dirname($res->link);
		$exRes->parentid = empty($exRes->parentdir)?'':\OCA\FilesSharding\Lib::getFileId($exRes->parentdir, $user_id);
		$extendedResult[] = $exRes;
	}
	//\OCP\Util::writeLog('search', 'Search results: '.json_encode($result), \OC_Log::WARN);
	OC_JSON::encodedPrint($extendedResult);
}
else {
	echo 'false';
}
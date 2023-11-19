<?php

namespace OCA\Search_Lucene;

require_once('apps/search_lucene/lib/lucene.php');

use \OC\Files\Filesystem;
use \OCP\User;
use \OCP\Util;

class MyLucene extends \OCA\Search_Lucene\Lucene {

	/**
	 * opens or creates the users lucene index
	 * 
	 * stores the index in <datadirectory>/<user>/lucene_index
	 * 
	 * @author Jörn Dreyer <jfd@butonic.de>
	 *
	 * @return Zend_Search_Lucene_Interface 
	 */
	public static function openOrCreate($user = null) {
	
	\OCP\Util::writeLog('files_sharding', 'DIR: '.getcwd(), \OC_Log::DEBUG);
	chdir(\OC::$SERVERROOT.'/apps/search_lucene/3rdparty');
	\OC::$CLASSPATH['Zend_Search_Lucene'] = 'search_lucene/3rdparty/Zend/Search/Lucene.php';
	\OC::$CLASSPATH['Zend_Search_Lucene_Analysis_Analyzer'] = 'search_lucene/3rdparty/Zend/Search/Lucene/Analysis/Analyzer.php';

		if ($user == null) {
			$user = User::getUser();
		}

		try {
			
			\Zend_Search_Lucene_Analysis_Analyzer::setDefault(
				new \Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive()
			); //let lucene search for numbers as well as words
			
			// Create index
			//$ocFilesystemView = OCP\Files::getStorage('search_lucene'); // encrypt the index on logout, decrypt on login

			$indexUrl = \OC_User::getHome($user) . '/lucene_index';
			if (file_exists($indexUrl)) {
				$index = \Zend_Search_Lucene::open($indexUrl);
			} else {
				$index = \Zend_Search_Lucene::create($indexUrl);
				//todo index all user files
			}
		} catch ( Exception $e ) {
			Util::writeLog(
				'search_lucene',
				$e->getMessage().' Trace:\n'.$e->getTraceAsString(),
				Util::ERROR
			);
			return null;
		}
		

		return $index;
	}

	/**
	 * performs a search on the users index
	 *
	 * @author Jörn Dreyer <jfd@butonic.de>
	 *
	 * @param string $query lucene search query
	 * @return array of OC_Search_Result
	 */
	public function search($query){
		$results=array();
		if ( $query !== null ) {
			// * query * kills performance for bigger indices
			// query * works ok
			// query is still best
			//FIXME emulates the old search but breaks all the nice lucene search query options
			//$query = '*' . $query . '*';
			//if (strpos($query, '*')===false) {
			//	$query = $query.='*'; // append query *, works ok
			//	TODO add end user guide for search terms ...
			//}
			try {
				$index = self::openOrCreate();
				//default is 3, 0 needed to keep current search behaviour
				//Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(0);
				
				//$term  = new Zend_Search_Lucene_Index_Term($query);
				//$query = new Zend_Search_Lucene_Search_Query_Term($term);
				
				$hits = $index->find($query);
				
				//limit results. we cant show more than ~30 anyway. TODO use paging later
				for ($i = 0; $i < 30 && $i < count($hits); $i++) {
					$results[] = self::asOCSearchResult($hits[$i]);
				}
				
			} catch ( Exception $e ) {
				Util::writeLog(
						'search_lucene',
						$e->getMessage().' Trace:\n'.$e->getTraceAsString(),
						Util::ERROR
						);
			}
			
		}
		return $results;
	}

	/**
	 * converts a zend lucene search object to a OC_SearchResult
	 *
	 * Example:
	 * 
	 * Text | Some Document.txt
	 *      | /path/to/file, 148kb, Score: 0.55
	 * 
	 * @author Jörn Dreyer <jfd@butonic.de>
	 *
	 * @param Zend_Search_Lucene_Search_QueryHit $hit The Lucene Search Result
	 * @return OC_Search_Result an OC_Search_Result
	 */
	private static function asOCSearchResult(\Zend_Search_Lucene_Search_QueryHit $hit) {

		$mimeBase = self::baseTypeOf($hit->mimetype);

		switch($mimeBase){
			case 'audio':
				$type='Music';
				break;
			case 'text':
				$type='Text';
				break;
			case 'image':
				$type='Images';
				break;
			default:
				if ($hit->mimetype=='application/xml') {
					$type='Text';
				} else {
					//$type='Files';
					$type='file';
				}
		}

		switch ($hit->mimetype) {
			case 'httpd/unix-directory':
				$url = Util::linkTo('files', 'index.php') . '?dir='.$hit->path;
				$type='folder';
				break;
			default:
				$url = \OC::$server->getRouter()->generate('download', array('file'=>$hit->path));
		}
		
		/*return new \OC_Search_Result(
				basename($hit->path),
				dirname($hit->path)
				. ', ' . \OCP\Util::humanFileSize($hit->size)
				. ', Score: ' . number_format($hit->score, 2),
				$url,
				$type,
				dirname($hit->path)
		);*/
		
		$id = \OCA\FilesSharding\Lib::getFileId($hit->path);
		$user_id = \OCP\USER::getUser();
		$ret = new \OC_Search_Result(
			$id,
			'<a class="filelink" href="#">'.basename($hit->path) . '</a> (<a class="dirlink" href="#">'. dirname($hit->path) . '</a>, '
				. \OCP\Util::humanFileSize($hit->size) .
			 (substr($_SERVER['REQUEST_URI'], -14)=='/ws/search.php'?
				', owner: ' . \OCP\User::getDisplayName($user_id):
			 	', Score: ' . number_format($hit->score, 2)).
				')',
			$hit->path,//$url,
			$type
		);
		return $ret;
	}


}

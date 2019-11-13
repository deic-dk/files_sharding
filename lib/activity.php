<?php

use OC\L10N\Factory;
use OCP\Activity\IExtension;
use OCP\Activity\IManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use \OCP\User;

class ServerSync_Activity implements IExtension {
	protected $l;
	protected $languageFactory;
	protected $URLGenerator;
	protected $activityManager;
	protected $config;
	protected $helper;
	public function __construct(Factory $languageFactory, IURLGenerator $URLGenerator, IManager $activityManager, IConfig $config) {
		$this->languageFactory = $languageFactory;
		$this->URLGenerator = $URLGenerator;
		$this->l = $this->getL10N();
		$this->activityManager = $activityManager;
		$this->config = $config;
	}
	/**
	 * @param string|null $languageCode
	 * @return IL10N
	 */
	protected function getL10N($languageCode = null) {
		return $this->languageFactory->get('files_sharding', $languageCode);
	}
	/**
	 * The extension can return an array of additional notification types.
	 * If no additional types are to be added false is to be returned
	 *
	 * @param string $languageCode
	 * @return array|false
	 */
	public function getNotificationTypes($languageCode) {
		$l = $this->getL10N($languageCode);
		return [\OCA\FilesSharding\Lib::TYPE_SERVER_SYNC => (string) $l->t('Files have been <strong>backed up</strong>'),];
	}
	/**
	 * For a given method additional types to be displayed in the settings can be returned.
	 * In case no additional types are to be added false is to be returned.
	 *
	 * @param string $method
	 * @return array|false
	 */
	public function getDefaultTypes($method) {
		if ($method === 'stream') {
			$settings = array();
			$settings[] = \OCA\FilesSharding\Lib::TYPE_SERVER_SYNC;
			return $settings;
		}
		return false;
	}
	/**
	 * The extension can translate a given message to the requested languages.
	 * If no translation is available false is to be returned.
	 *
	 * @param string $app
	 * @param string $text
	 * @param array $params
	 * @param boolean $stripPath
	 * @param boolean $highlightParams
	 * @param string $languageCode
	 * @return string|false
	 */
	public function translate($app, $text, $params, $stripPath, $highlightParams, $languageCode) {
		if ($app !== 'files_sharding') {
			return false;
		}
		switch ($text) {
			case 'sync_finished':
				$newparams = $params;
				if(\OCA\FilesSharding\Lib::isMaster()){
					$strippedID = preg_replace('|<strong>(.*)</strong>|', '$1', $params[1]);
					$newparams[1] = '<strong>'.\OCA\FilesSharding\Lib::dbLookupServerURL($strippedID).'</strong>';
				}
				return (string) $this->l->t('Your files have been synchronized from %1$s to %2$s', $newparams);
			case 'migration_finished':
				$newparams = $params;
				if(\OCA\FilesSharding\Lib::isMaster()){
					//$strippedID = preg_replace('/<strong>(.*)</strong>/', '$1', $params[1]);
					//$newparams[1] = '<strong>'.\OCA\FilesSharding\Lib::dbLookupServerURL($strippedID).'</strong>';
				}
				return (string) $this->l->t('Your files have been migrated to %1$s.<br><strong>Please change URL</strong> in the settings of your sync clients!',
					$newparams);
			case 'sync_problem_encountered':
				$newparams = $params;
				return (string) $this->l->t('Your files have NOT been fully backed up.',
						$newparams);
			default:
				return false;
		}
	}
	/**
	 * The extension can define the type of parameters for translation
	 *
	 * Currently known types are:
	 * * file		=> will strip away the path of the file and add a tooltip with it
	 * * username	=> will add the avatar of the user
	 *
	 * @param string $app
	 * @param string $text
	 * @return array|false
	 */
	function getSpecialParameterList($app, $text) {
		if ($app === 'files_sharding') {
					return [
						0 => 'file',
						1 => 'username',
					];
			}
		return false;
	}
	/**
	 * A string naming the css class for the icon to be used can be returned.
	 * If no icon is known for the given type false is to be returned.
	 *
	 * @param string $type
	 * @return string|false
	 */
	public function getTypeIcon($type) {
		if ($type == \OCA\FilesSharding\Lib::TYPE_SERVER_SYNC) {
			return 'icon-chart-area';}
	}
	/**
	 * The extension can define the parameter grouping by returning the index as integer.
	 * In case no grouping is required false is to be returned.
	 *
	 * @param array $activity
	 * @return integer|false
	 */
	public function getGroupParameter($activity) {
		return false;
	}
	/**
	 * The extension can define additional navigation entries. The array returned has to contain two keys 'top'
	 * and 'apps' which hold arrays with the relevant entries.
	 * If no further entries are to be added false is no be returned.
	 *
	 * @return array|false
	 */
		public function getNavigation() {
			}
	/**
	 * The extension can check if a customer filter (given by a query string like filter=abc) is valid or not.
	 *
	 * @param string $filterValue
	 * @return boolean
	 */
	public function isFilterValid($filterValue) {
		return true;
	}
	/**
	 * The extension can filter the types based on the filter if required.
	 * In case no filter is to be applied false is to be returned unchanged.
	 *
	 * @param array $types
	 * @param string $filter
	 * @return array|false
	 */
	public function filterNotificationTypes($types, $filter) {
		return false;
	}
	/**
	 * For a given filter the extension can specify the sql query conditions including parameters for that query.
	 * In case the extension does not know the filter false is to be returned.
	 * The query condition and the parameters are to be returned as array with two elements.
	 * E.g. return array('`app` = ? and `message` like ?', array('mail', 'ownCloud%'));
	 *
	 * @param string $filter
	 * @return array|false
	 */
	public function getQueryForFilter($filter) {
		return false;
	}
}

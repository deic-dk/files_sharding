<?php

namespace OCA\FilesSharding\BackgroundJob;
	

class UpdateFree extends \OC\BackgroundJob\TimedJob {

	public function __construct() {
		// Run all 60 Minutes
		$this->setInterval(60 * 60);
	}

	protected function run($argument) {
		\OCA\FilesSharding\Lib::updateFree();
	}
	
}
<?php

namespace OCA\FilesSharding;

class PracticalSession extends \OC\Session\Internal {

	/*
	This is required because in some cases, the custom session handler is destroyed
	before the OC session class.  As a result, the OC session class doesn't
	get the chance to store the data it has aggregated in the $_SESSION array
	prior to the custom handler writing it to disk (or wherever).

	This change puts session value changes directly into the $_SESSION array, so
	that when the session handler is closed, the current data is written to disk.
	*/
	public function set($key, $value) {
		parent::set($key, $value);
		$_SESSION[$key] = $value;
	}
}
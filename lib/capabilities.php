<?php

namespace OCA\FilesSharding; 

class Capabilities {
	
	public static function getCapabilities() {
		return new \OC_OCS_Result(array(
			'capabilities' => array(
				'files_sharing' => array(
					'api_enabled' => true,
					'public' => array(
						'enabled' => true,
						'password' => array('enforced' => false),
						'expire_date' => array('enabled' => false),
						'send_mail' => false,
						'upload' => true,
						'upload_files_drop' => false
					),
					'resharing' => false,
					'user' => array('send_mail' => false, 'expire_date' => array('enabled' => 'true')),
					'group_sharing' => true,
					'group' => array('send_mail' => false, 'expire_date' => array('enabled' => 'true')),
				),
			),
		));
	}
	
}

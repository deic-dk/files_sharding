<?php

OC_API::register('get', '/cloud/capabilities', array('OCA\FilesSharding\Capabilities', 'getCapabilities'),
		'files_sharding', OC_API::USER_AUTH);

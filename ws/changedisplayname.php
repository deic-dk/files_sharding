<?php

require_once __DIR__ . '/../../../lib/base.php';

if(!OCA\FilesSharding\Lib::checkIP()){
	http_response_code(401);
	exit;
}

$l=OC_L10N::get('settings');

$username = isset($_POST["username"]) ? $_POST["username"] : OC_User::getUser();
$displayName = $_POST["displayName"];

// Return Success story
if( OC_User::setDisplayName( $username, $displayName )) {
	OC_JSON::success(array("data" => array( "message" => $l->t('Your full name has been changed.'), "username" => $username, 'displayName' => $displayName )));
}
else{
	OC_JSON::error(array("data" => array( "message" => $l->t("Unable to change full name"), 'displayName' => OC_User::getDisplayName($username) )));
}



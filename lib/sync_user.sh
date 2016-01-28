#!/bin/bash

OC_CMD="/usr/local/bin/owncloudcmd"
OC_LOCAL_DATA_ROOT="/tank/data/owncloud"
OC_REMOTE_BASE_DIR="/remote.php/webdav/"

#
# Script for one-way syncing of a user from a remote ownCloud server to a local one.
# Copyright Frederik Orellana, 2016
#

function usage(){
	echo "Usage: sync_user.sh [-u user] [-p password] [folder] [URL]"
	exit -1
}

while getopts "u:p:o:" flag; do
case "$flag" in
    u) user="-u \"$OPTARG\"";;
    p) pass="-p \"$OPTARG\"";;
    s) server="$OPTARG";;
    \?) echo "Invalid option: -$OPTARG" >&2; usage;;
		*) usage;;
esac
done

shift $((OPTIND-1))

if [ -z "$1" -a -n "$user" ]; then
	folder="$OC_LOCAL_DATA_ROOT/user"
else
	folder="\"$1\""
fi

if [ -z "$2" -a -n "$server" ]; then
	url="https://${server}$OC_REMOTE_BASE_DIR"
else
	url="\"$2\""
fi

if [ -z "$url" ]; then
	usage
fi

## Sync files
$OC_CMD $user $pass $folder $url

## Run local file scan
cd /user/local/www/owncloud
php console.php files:scan $user

if [ "$?" != "0" ]; then
	echo "ERROR: Could not scan files" 2>&1
	exit 1
fi

## Get list of shared file mappings: ID -> path and update item_source on oc_share table on master with new IDs
curl --insecure "https://localhost/index.php/apps/files_sharding/ws/update_user_shared_files.php\?user=$user"

## Get exported metadata (by path) via remote metadata web API and insert metadata on synced files by using local metadata web API
curl --insecure "https://localhost/index.php/apps/meta_data/ws/updateFileTags.php\?user=$user\&url=$url"



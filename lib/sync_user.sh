#!/bin/bash

OC_CMD="/usr/local/bin/owncloudcmd"
OC_LOCAL_DATA_ROOT="/tank/data/owncloud"
OC_REMOTE_BASE_DIR="/remote.php/webdav/"

#
# Script for one-way syncing of a user from a remote ownCloud server to a local one.
# Copyright Frederik Orellana, 2016
#

function usage(){
	echo "Usage: sync_user.sh [-u user] [-s server] [-p password] [folder URL]"
	exit -1
}

while getopts "u:p:s:" flag; do
case "$flag" in
    u) user="-u \"$OPTARG\"";;
    p) pass="-p \"$OPTARG\"";;
    s) server="$OPTARG";;
    \?) echo "Invalid option: -$OPTARG" >&2; usage;;
		*) usage;;
esac
done

shift $((OPTIND-1))

if [ -n "$1" ]; then
	folder="\"$1\""
elif [ -n "$user" ]
	folder="$OC_LOCAL_DATA_ROOT/$user"
fi

if [ -n "$server" ]; then
	url="https://${server}$OC_REMOTE_BASE_DIR"
elif [ -n "$2" ]
		url="\"$2\""
fi

if [ -z "$folder" -o -z "$url" ]; then
	usage
fi

# Check if script is already running for this user
is_running=`ps auxw | grep sync_user.sh | grep "$user" | grep -v grep`
if [ "$is_running" != "" ]; then
	echo "User $user is already being synced"
	exit 0
fi

# Get number of files to begin with
files_start=`find "$folder" | wc -l`

## Sync files
$OC_CMD $user $pass $folder $url

## Run local file scan
cd /user/local/www/owncloud
php console.php files:scan $user

if [ "$?" != "0" ]; then
	echo "ERROR: Could not scan files" 2>&1
	exit 1
fi

# Get number of files after sync
files_end=`find "$folder" | wc -l`
# Print the difference
synced_files=$((files_end-files_start))
echo "Synced files:$synced_files"


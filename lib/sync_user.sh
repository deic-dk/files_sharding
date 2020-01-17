#!/usr/local/bin/bash

#OC_CMD="/usr/local/bin/owncloudcmd"
RCLONE_CMD="/usr/local/bin/rclone"
OC_ROOT="/usr/local/www/owncloud"
OC_LOCAL_DATA_ROOT="/tank/data/owncloud"
OC_REMOTE_BASE_DIR="/remote.php/webdav/"

SYNC_TIMEOUT=14400s

export OWNCLOUD_MAX_PARALLEL=1

#
# Script for one-way syncing of a user from a remote ownCloud server to a local one.
# Copyright Frederik Orellana, 2016
#

function usage(){
	echo "Usage: sync_user.sh [-u user] [-s server] [-p password] [folder] [URL]"
	echo "server and  URL are mutually exclusive. If both are given, server has priority"
	exit -1
}

# NOTICE: users should be created before calling this script and the correct
#         numeric storage ID should be set. It must be the same on the two servers
#         and on the master; otherwise, updating shared files and metadata will not work.
function create_user(){
	passwd=`echo -n "secret" | openssl dgst -sha1 | awk '{print $NF}'`
	cd $OC_ROOT
	dbname=`php -r 'include "config/config.php"; print($CONFIG["dbname"]);'`
	dbuser=`php -r 'include "config/config.php"; print($CONFIG["dbuser"]);'`
	dbpassword=`php -r 'include "config/config.php"; print($CONFIG["dbpassword"]);'`
	dbtableprefix=`php -r 'include "config/config.php"; print($CONFIG["dbtableprefix"]);'`
	echo "INSERT INTO ${dbname}.${dbtableprefix}users (uid,password) values('$1','$passwd');" | \
	mysql -u${dbuser} -p${dbpassword};
}

# From https://gist.github.com/cdown/1163649
function urlencode() {
    # urlencode <string>

    local length="${#1}"
    for (( i = 0; i < length; i++ )); do
        local c="${1:i:1}"
        case $c in
            [a-zA-Z0-9.~_-]) printf "$c" ;;
            *) printf '%s' "$c" | xxd -p -c1 |
                   while read c; do printf '%%%s' "$c"; done ;;
        esac
    done
}

function urldecode() {
    # urldecode <string>

    local url_encoded="${1//+/ }"
    printf '%b' "${url_encoded//%/\\x}"
}

while getopts "u:p:s:" flag; do
case "$flag" in
    u) user="$OPTARG";;
    p) password="$OPTARG";;
    s) server="$OPTARG";;
    \?) echo "Invalid option: -$OPTARG" >&2; usage;;
		*) usage;;
esac
done

shift $((OPTIND-1))

if [ -n "$1" ]; then
	folder="$1"
elif [ -n "$user" ]; then
	folder="$OC_LOCAL_DATA_ROOT/$user/files"
fi

if [ -n "$server" ]; then
	url="https://${server}$OC_REMOTE_BASE_DIR"
elif [ -n "$2" ]; then
		url="$2"
fi

if [ -z "$folder" -o -z "$url" ]; then
	usage
fi

# Check if script is already running for this user
syncs_running=`ps auxw | grep "sync_user.sh" | grep "$user" | grep -v grep | grep -v bash`
is_running=`ps auxw | grep "sync_user.sh" | grep "$user" | grep -v grep | grep -v bash | wc -l`
if [ $is_running -gt 1 ]; then
	echo "User $user is already being synced. $syncs_running"
	exit 0
fi

# Get number of files to begin with
files_start=`find "$folder" | wc -l`

ls "$folder" >& /dev/null || mkdir -p "$folder"

echo $url | grep @
if [ $? -eq 0 -o -z $user ]; then
	base_url=`echo $url | sed -E 's|(.*://[^/]+)/.*|\1|'`
else
	# Apparently not necessary
	#user=`urlencode $user`
	#password=`urlencode $password`
	base_url=`echo $url | sed -E "s|(.*://)([^/]+)/.*|\1$user:$password@\2|"`
fi

remote_path=`echo $url | sed -E 's|.*://[^/]+||'`

## Sync files
echo timeout $SYNC_TIMEOUT $RCLONE_CMD sync --no-check-certificate --webdav-url $base_url :webdav:$remote_path "$folder" 
timeout $SYNC_TIMEOUT $RCLONE_CMD sync --no-check-certificate --webdav-url $base_url :webdav:$remote_path "$folder" 

RET=$?

if [ "$RET" != "0" ]; then
	echo "ERROR: Synchronization timed out" 2>&1
	exit $RET
fi

## Create user if he does not exist
php "$OC_ROOT/console.php" user:lastseen "$user" | grep 'not exist'
if [ $? -eq 0 ]; then
	create_user $user
fi

## Run local file scan
php "$OC_ROOT/console.php" files:scan "$user" > /dev/null

if [ "$?" != "0" ]; then
	echo "ERROR: Could not scan files" 2>&1
	exit 1
fi

# Get number of files after sync
files_end=`find "$folder" | wc -l`
# Print the difference
synced_files=$((files_end-files_start))
echo "Synced files:$synced_files"


#!/usr/local/bin/bash

OC_CMD="/usr/local/bin/owncloudcmd"
OC_ROOT="/usr/local/www/owncloud"
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

while getopts "u:p:s:" flag; do
case "$flag" in
    u) user="$OPTARG";;
    p) pass="$OPTARG";;
    s) server="$OPTARG";;
    \?) echo "Invalid option: -$OPTARG" >&2; usage;;
		*) usage;;
esac
done

shift $((OPTIND-1))

if [ -n "$1" ]; then
	folder="\"$1\""
elif [ -n "$user" ]; then
	folder="$OC_LOCAL_DATA_ROOT/$user/files"
fi

if [ -n "$server" ]; then
	url="https://${server}$OC_REMOTE_BASE_DIR"
elif [ -n "$2" ]; then
		url="\"$2\""
fi

if [ -z "$folder" -o -z "$url" ]; then
	usage
fi

# Check if script is already running for this user
is_running=`exec ps auxw | grep "sync_user.sh" | grep "$user" | grep -v grep | wc -l`;
if [ $is_running -gt 2 ]; then
	echo "User $user is already being synced. $is_running"
	exit 0
fi

# Get number of files to begin with
files_start=`find "$folder" | wc -l`

## Sync files
if [ -z "$pass" ]; then
	password=""
else
	password="-p '\"\"'"
fi
ls "$folder" >& /dev/null || mkdir -p "$folder"
$OC_CMD --non-interactive -u "$user" $password "$folder" $url

## Create user if he does not exist
php "$OC_ROOT/console.php" user:lastseen "$user" | grep 'not exist'
if [ $? -eq 0 ]; then
	create_user $user
fi

## Run local file scan
php "$OC_ROOT/console.php" files:scan "$user"

if [ "$?" != "0" ]; then
	echo "ERROR: Could not scan files" 2>&1
	exit 1
fi

# Get number of files after sync
files_end=`find "$folder" | wc -l`
# Print the difference
synced_files=$((files_end-files_start))
echo "Synced files:$synced_files"


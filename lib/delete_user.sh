#!/usr/local/bin/bash

OC_LOCAL_DATA_ROOT="/tank/data/owncloud"

#
# Script for deleting a local ownCloud user.
# Copyright Frederik Orellana, 2016
#

function usage(){
	echo "Usage: delete_user.sh user"
	exit -1
}

user=$1
folder="$OC_LOCAL_DATA_ROOT/$user"

if [ -z "$user" ]; then
	usage
fi

# Check if script is already running for this user
is_running=`ps auxw | grep delete_user.sh | grep "$user" | grep -v grep`
if [ "$is_running" != "" ]; then
	echo "User $user is already being deleted"
	exit 0
fi

## Delete user
cd /user/local/www/owncloud
php console.php user:delete $user

if [ "$?" != "0" ]; then
	echo "ERROR: Could not delete user" 2>&1
	exit 1
fi

# Print number of files after deletion
files_end=`find "$folder" | wc -l`
echo "Remaining files:$files_end"


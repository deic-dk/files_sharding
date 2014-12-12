files_sharding
=======
#### ownCloud app implementing scale-out on a group of ownCloud servers

The purpose of the app is to allow horizontal scaling of storage capacity and performance
without resorting to distributed file systems, SAN or NAS technology, but by letting each
server in a group of ownCloud servers be responsible for a segment of the namespace and
redirect accordingly.

The app requires redirection to be done on a master server. Such functionality is provided
by an accompanying app: "user_saml"- which is a fork of the original user_saml by Sixto
Martin.

## User sharding

The default is to shard on username, keep each user on one server and limit users to e.g.
0.5 TB each for personal files. Each server with, say 70 TB, could then host, say 100
users and keep 20 TB for those users who want to buy more personal space.

## Path sharding

Path-sharded folders, i.e. data folders (under /Data) are to be used for scientific data.
They can span multiple servers and have no upper limit on their size beyond the limit given
by the size of the customer's wallet.
They are not synced and are expected to be accessed either via WebDAV clients for large-
scale data management, via the web interface for casual data management, via direct curl
from compute servers or scripted curl from large-scale off-site data providers or consumers.

## Quotas and redirects

To use more space than their personal quota, users have three options:

1) Buy more personal space - up to what's free on the server they're on and some limit -
   say 5 TB. We'll have to wait and see and then tune the quotas, limits and number of
   users to avoid manually migrating users as far as possible.
   
   The mapping of users to servers is kept in a database table:

   `files_sharding_user_servers`: `id` (int), `user` (String), `server_id` (int), `current` (bool)
   
   queried via the method

         getNextServerForUser()

2) Create a (path-sharded) folder under /Data and buy space for this. Propfind'ing this
   folder will return a remote URL pointing to a folder-slave-server.
   On the head server, a list of servers {slave1, slave2, slave3, ...}, is kept for each
   such folder. The method
   
         getNextServerForFolder()
         
   takes a folder and a host name as arguments and returns the next element from the list -
   i.e. on which server to start looking for the given path. 
   
   The method
   
         getCurrentServerForFolder()
         
   takes a folder and a host name as arguments and returns the name of the server that should
   currently be used for writing files.

   The method for setting this name,
   
         setCurrentServerForFolder(),
   
   is called by a folder-slave-server when running out of space and redirecting.

   Physically, the above services will keep their state in a MySQL table on the head-
   server:
   
   `files_sharding_folder_servers`: `id` (int), `folder_id` (int), `server_id` (int), `current` (bool)
      
   The path of a folder is looked up in `oc_files_cache` via `\OCP\Files\Folder::getId()`.
   The name of the server is looked up in another table:
   
   `files_sharding_servers`: `id` (int), `name` (string)
   
   When slave1 runs out of space, put, copy, move and mkcol trigger creation of a file
   or folder in the system folder 'files_sharding' folder (on the same level as
   'files_versions' etc.) and a redirect to slave2 (a header 'location: https://slave2/...').
   Future propfinds on the file or folder on slave1 will receive a URI pointing to slave2.
   Future put, get etc. on the file or folder on slave1 will simply receive a redirect to
   slave2.
   
   When slave2 runs out of space, the same happens, now with redirects to slave3. For
   subsequent requests to the created file/folder, slave1, which has no clue what has
   happened with this file/folder, when it doesn't find the file/folder and also not a
   'files_sharding'-match, redirects directly to slave3.
   
   When slave3 runs out of space, the same happens, now with redirects to slave4. slave1
   redirects subsequent requests to slave3, which redirects to slave4.
   
   To avoid out of space situations due to race conditions, the ownCloud function for
   getting (local) free space is modified to take into account files in the process of
   being written. The quota-related functions of ownCloud are modified to sum up space used
   on slave1, slave2, ...
 
   
3) Assuming the user is member of a group, say DTU, and assuming the owner of the group
   DTU has bought, say 300 TB of space for a folder /Data/DTU, shared it with the group,
   and ticked off 'individual data folders', the user can simply place his data in the folder
   /Data/DTU. Or - if he want to share data - put it in /Shared/DTU.
   
   Ticking off 'individual data folders', first checks if any group member already has a
   folder by the name /data/DTU and returns an error in such a case. Otherwise, it causes
   a folder /Data/DTU to be created for all current and future group members. These folders
   are simply path-sharded folders with a quota determined by the DTU group owner.
   
## Implementation

### WebDAV

Requests will be intercepted via mod_rewrite rules like below.

/files, /public, /shared and /remote.php/webdav (for sync clients) are mod_rewritten
to /remote.php/dav, i.e. remote.php from files_sharding. Then, 3 things can happen:

1) If the item is not found in the file system, but a match is found in
   'files_sharding', the client is redirected to the relevant folder-slave-server.

2) If the item is not found in the file system, and a match is not found in
   'files_sharding', the client is redirected to the next folder-slave-server, found
   via `getNextServerForFolder()`.

3) If on the server hosting the item in question - i.e. if the item is found in
   the file system chooser/appinfo/remote.php is fired up (files/appinfo/remote.php
   if chooser is not installed).
   Special care is taken in the case of a delete request on folder-sharded items:
   If not the result of a redirect, it is redirected to the previous server of the
   sharded folder, found via the method
   
         getPreviousServerForFolder(),

   from where it is redirected back, but only after a possible 'files_sharding'
   link has been deleted.

``` bash
#
# Shard /files, /shared and /public
#
# Web interface - shares
RewriteRule ^shared/(.*)$ public.php?service=files&t=$1 [QSA,L]
# WebDAV - personal
RewriteRule ^files/(.*) remote.php/dav/$1 [QSA,L]
# WebDAV - shares
RewriteRule ^public/(.*) remote.php/dav/$1 [QSA,L]
#
# Hide /files/Data when redirected from head-server
#
RewriteCond %{HTTP_REFERER} .
RewriteCond %{HTTP_REFERER} ^https://data\.deic\.dk [NC]
RewriteCond %{HTTP_USER_AGENT} ^.*(csyncoC|mirall)\/.*$
RewriteCond %{REQUEST_METHOD} PROPFIND
RewriteRule ^remote.php/webdav/*$ /remote.php/mydav/ [QSA,L]
#
# Otherwise we're on the head-server and redirect sync clients
# to /remote.php/dav
#
RewriteCond %{HTTP_USER_AGENT} ^.*(csyncoC|mirall)\/.*$
RewriteRule ^remote.php/webdav/*$ /remote.php/dav/ [QSA,L]
```
*mod_rewrite rules*

### Web interface

Redirects are implemented by patching the files app via our theme.
The same goes for the following features:

- When a user creates a folder in /Data, he is greeted with a popup, asking him about the
  max size and the price. The price will be lower for smaller max sizes.
- The first time, say, a DTU user visits, /Data/DTU or /Shared/DTU from the web interface,
  he is informed about the characteristics of the respective folder (ownership, quota).

## Integration of other apps

The affected apps will be those that require access to files and directories not on the
server a user happens to land on by redirection.

Obviously this is true for the files app - which will be dealt with as described above.
In particular, a method,

      exists(),

will be implemented that can be used for put, copy and move requests.

Apart from files, one can think of apps that allow searching across files owned by others or
files in /Data. These include the Pictures and Documents apps - and the Tags/metadata app
currently under development.

A method for searching across all servers will be implemented and attempted used via our theme.

## Performance
   
files_sharding can cause performance degradation in at least two ways:

### Redirects

   For user sharding, there should be max one of these per file/folder request and typically,
   when browsing directories with the web interface or a WebDAV client, only one in total:
   An initial propfind on / on the head-server will return URLs to, say, slave1, and all
   subsequent requests will go directly to slave1.
   Direct propfinds on, /some/dir, will be redirected to, say, slave1, and all subsequent
   requests will go directly to slave1.
   
   It remains to be seen if this is also so for the sync clients, or if they will insist on
   using connecting to the head-server - in particular for put and mkcol. If it is not so, an
   easy work-around is to display a user's slave-server in his preferences and instruct him to
   use this as sync URL.
   
   For path-sharded folders, there may be multiple redirects per file/folder request, but again,
   with WebDAV clients, we expect there to be only one up front - and then perhaps a few more -
   depending on how many slave-servers the folder is spread over.
   
   Since path-sharded folders are not synced, the only other worry is direct curl requests.
   This is how data is expected to be staged in and out from compute servers, so it is
   important. But since such staging requests are expected to be with reasonable time in
   between and concern relatively large files. Redirects should not matter, as they only
   affect latency and not throughput.
   
### Slave-servers querying the head-server DB

   These are issued:
   - on redirects
   - when checking quotas, i.e. on all put and copy requests
 
To lessen this extra load, quota and folder-slave-map query results are cached on the slave-
servers.
   
   
   
   
   
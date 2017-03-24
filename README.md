files_sharding
=======
#### ownCloud/Nextcloud app, implementing scale-out on a group of servers

The purpose of the app is to allow horizontal scaling of storage capacity and performance
without resorting to distributed file systems, SAN or NAS technology, but by letting each
server in a group of ownCloud servers be responsible for a segment of the user and/or
folders and redirect accordingly.

## User sharding

The idea is to shard on username, keep each user on one server and limit users to e.g.
0.5 TB each for personal files. Each server with, say 70 TB, could then host, say 100
users and keep 20 TB for users with special needs.

## Implementation

### WebDAV

Requests will be intercepted via mod_rewrite rules like below. Notice
that there are dependencies on both the theme `deic_theme_oc7`and the app
`chooser`.

``` bash
# Web interface - shares
RewriteRule ^shared/([^/]*)/([^/]*)\?*(.*)$ themes/deic_theme_oc7/apps/files_sharing/public.php?g=$1&t=$2&$3 [QSA,L]
RewriteRule ^shared/([^/]*)\?*(.*)$ themes/deic_theme_oc7/apps/files_sharing/public.php?t=$1&$2 [QSA,L]
# WebDAV - personal
RewriteRule ^files/(.*) remote.php/mydav/$1 [QSA,L]
# WebDAV - shares
RewriteRule ^public/(.*) remote.php/mydav/$1 [QSA,L]
```
*mod_rewrite rules*

### Web interface

Redirects are implemented via a `post_login` hook.

## Integration of other apps

The affected apps will be those that require access to files and directories not on the
server a user happens to land on by redirection - in particular files_sharing.

Apps not written by us have been customized via the theme `deic_theme_oc7`.

### File sharing

Shared files are diplayed in the web interface under "Shared by me" and "Shared with me".
They are _not_ seen by the WebDAV and sync clients.

## Performance

`files_sharding` can cause performance degradation when many users are accessing shared files.
In this case, the central server will be receive many database queries and there will be a
lot of inter-server communication, all leading to increased latency and lower performance.


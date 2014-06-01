sharder
=======
ownCloud app implementing http redirects and namespace sharing on a group of owncloud
servers
-------

The purpose of the app is to allow horizontal scaling of storage capacity and performance
without resorting to distributed file systems, SAN or NAS technology, but by letting each
server in a group of ownCloud servers be responsible for a segment of the namespace and
redirecting accordingly.

The group of servers should be loadbalanced and the server recieving the initial request
will then redirect to the server holding the requested file unless this server happens to
be itself.

The sharding algorithm uses the user name as key and therefore cannot be implemented
directly by the load balancer.
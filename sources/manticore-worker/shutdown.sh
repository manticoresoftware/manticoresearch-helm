#!/bin/sh

curl $BALANCER_URL
searchd --stopwait >> /var/lib/manticore/replication/stopHook.log 2>&1

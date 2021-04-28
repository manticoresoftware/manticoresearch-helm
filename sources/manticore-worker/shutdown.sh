#!/bin/sh

searchd --stopwait >> /var/lib/manticore/replication/stopHook.log 2>&1
curl $BALANCER_URL

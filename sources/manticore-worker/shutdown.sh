#!/bin/sh

searchd --stopwait
curl $BALANCER_URL

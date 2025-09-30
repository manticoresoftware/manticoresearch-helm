#!/bin/sh

cp -f $CONFIGMAP_PATH /etc/manticoresearch/manticore.conf
exec /usr/bin/supervisord -n -c /etc/supervisord.conf

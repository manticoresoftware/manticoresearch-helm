#!/bin/bash

if [ ! -d "/var/lib/manticore/log" ]; then
  mkdir -p "/var/lib/manticore/log"
fi

if [ ! -d "/var/lib/manticore/data" ]; then
  mkdir -p "/var/lib/manticore/data"
fi

while [ -z "$(ls -A /var/lib/manticore/)" ]
do
  echo "Waiting for volume mount"
  sleep 1;
done

echo "Mount success"

/usr/bin/supervisord -n -c /etc/supervisord.conf

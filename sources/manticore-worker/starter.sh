#!/bin/sh

if [ ! -d "/var/lib/manticore/log" ]; then
  mkdir -p "/var/lib/manticore/log"
fi

if [ ! -d "/var/lib/manticore/data" ]; then
  mkdir -p "/var/lib/manticore/data"
fi

if [ ! -d "/var/lib/manticore/replication" ]; then
  mkdir -p "/var/lib/manticore/replication"
fi

while [ -z "$(ls -A /var/lib/manticore/)" ]
do
echo "Waiting for mount volume"
tree -ugp /var/lib/manticore/
sleep 1;
done
echo "Work end"

if [ ! -d "/var/lib/manticore/log" ]; then
  mkdir -p "/var/lib/manticore/log"
fi

tree -ugp /var/lib/manticore/


php /etc/manticoresearch/replica.php &

exec searchd --nodetach

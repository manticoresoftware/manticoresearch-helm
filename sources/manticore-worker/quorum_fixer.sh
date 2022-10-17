#!/bin/bash

while [ true ]; do
  php quorum.php &
  sleep $QUORUM_RUN_INTERVAL
done

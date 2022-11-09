#!/bin/bash

if [ "$QUORUM_RECOVERY" = true ] ; then
    while [ true ]; do
      php quorum.php &
      sleep $QUORUM_RUN_INTERVAL
    done
fi

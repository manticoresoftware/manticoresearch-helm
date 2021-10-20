#!/bin/bash

if [ ! -z $OPTIMIZE_RUN_INTERVAL ]; then
    while [ true ]; do
      php optimize.php &
      sleep $OPTIMIZE_RUN_INTERVAL
    done
fi


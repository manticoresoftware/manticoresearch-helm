#!/bin/bash

while [ true ]; do
  php observer.php &
  sleep $OBSERVER_RUN_INTERVAL
done

#!/bin/bash

while [ true ]; do
  php observer.php &
  sleep $RUN_INTERVAL
done

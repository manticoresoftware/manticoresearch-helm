#!/bin/bash
BUILD_TAG=dev
echo $BUILD_TAG
docker build --no-cache -t registry.gitlab.com/manticoresearch/helm-charts/worker:$BUILD_TAG .
docker push registry.gitlab.com/manticoresearch/helm-charts/worker:$BUILD_TAG

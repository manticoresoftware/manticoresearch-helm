#!/bin/bash

#################################################################################################
#                                                                                               #
#   !!! Read about https://gitlab.com/manticoresearch/helm-charts/-/wikis/Versioning first !!!  #
#                                                                                               #
#################################################################################################

BUILD_TAG=$(cat ../../charts/manticoresearch/Chart.yaml | grep appVersion | cut -d" " -f2)
echo $BUILD_TAG
docker build --no-cache --platform linux/amd64 -t manticoresearch/helm-balancer:$BUILD_TAG .
docker push manticoresearch/helm-balancer:$BUILD_TAG

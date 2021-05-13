#!/bin/bash

#################################################################################################
#                                                                                               #
#   !!! Read about https://gitlab.com/manticoresearch/helm-charts/-/wikis/Versioning first !!!  #
#                                                                                               #
#################################################################################################

BUILD_TAG=0.0.1
echo $BUILD_TAG
docker build --no-cache -t registry.gitlab.com/manticoresearch/helm-charts/balancer:$BUILD_TAG .
docker push registry.gitlab.com/manticoresearch/helm-charts/balancer:$BUILD_TAG

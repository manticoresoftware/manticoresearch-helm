#!/bin/bash

#################################################################################################
#                                                                                               #
#   !!! Read about https://gitlab.com/manticoresearch/helm-charts/-/wikis/Versioning first !!!  #
#                                                                                               #
#################################################################################################

BUILD_TAG=5.0.2.3
echo $BUILD_TAG
docker build --no-cache -t manticoresearch/helm-worker:$BUILD_TAG .
docker push manticoresearch/helm-worker:$BUILD_TAG

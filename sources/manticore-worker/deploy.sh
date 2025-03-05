#!/bin/bash

#################################################################################################
#                                                                                               #
#   !!! Read about https://gitlab.com/manticoresearch/helm-charts/-/wikis/Versioning first !!!  #
#                                                                                               #
#################################################################################################

BUILD_TAG=$(cat ../../charts/manticoresearch/Chart.yaml | grep appVersion | cut -d" " -f2)
echo $BUILD_TAG


docker buildx create --name mybuilder --use
docker buildx inspect mybuilder --bootstrap

docker buildx build \
--platform linux/amd64 \
--no-cache \
--push \
-t manticoresearch/helm-worker:$BUILD_TAG .
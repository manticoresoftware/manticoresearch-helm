#!/usr/bin/env bash


rm -rf k3s.yaml k3s_copy.yaml

docker stop k3s
docker stop kubectl

docker rm k3s
docker rm kubectl


touch k3s.yaml
docker run -d --name k3s -v $(pwd)/k3s.yaml:/etc/rancher/k3s/k3s.yaml --privileged -it rancher/k3s server

sleep 30

docker build -t manticoresearch/helm-test-kit:0.0.1 .

cp k3s.yaml k3s_copy.yaml

chmod 0777 k3s_copy.yaml

K3SIP=$(docker inspect k3s -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' | head -n1)

sed -i "s/127.0.0.1/${K3SIP}/g" k3s_copy.yaml

# CLT record guide
#RUN_ARGS="-v "$(pwd)/clt/local_development/k3s_copy.yaml:/tmp/output/kubeconfig-latest.yaml"" /work/clt/clt test -d -t clt/tests/deploy.rec manticoresearch/helm-test-kit:0.0.1
# export KUBECONFIG=/tmp/output/kubeconfig-latest.yaml
# kubectl get nodes


#!/usr/bin/env bash


rm -rf k3s.yaml k3s_copy.yaml

docker stop k3s
docker stop kubectl

docker rm k3s
docker rm kubectl


touch k3s.yaml
docker run -d --name k3s -v $(pwd)/k3s.yaml:/etc/rancher/k3s/k3s.yaml --privileged -it rancher/k3s server

sleep 30

docker build -t kubectl:latest .

cp k3s.yaml k3s_copy.yaml

chmod 0777 k3s_copy.yaml

K3SIP=$(docker inspect k3s -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' | head -n1)

sed -i "s/127.0.0.1/${K3SIP}/g" k3s_copy.yaml

# CLT record guide
# RUN_ARGS="-v "$(pwd)/clt/local_development/k3s_copy.yaml:/root/.kube/config"" /work/clt/clt record kubectl:latest
# export KUBECONFIG=/root/.kube/config
# kubectl get nodes

# Docker start guide
# docker run --name kubectl -v "$(pwd)/clt/local_development/k3s_copy.yaml:/root/.kube/config" -it --rm kubectl:latest

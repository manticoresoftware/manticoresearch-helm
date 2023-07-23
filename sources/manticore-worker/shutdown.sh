#!/bin/sh

echo "Start manticore worker graceful shutdown"
searchd --stopwait

KUBE_TOKEN=$(cat /var/run/secrets/kubernetes.io/serviceaccount/token)
CONTENT=$(curl -sSk -H "Authorization: Bearer $KUBE_TOKEN" \
"https://$KUBERNETES_SERVICE_HOST:$KUBERNETES_PORT_443_TCP_PORT/api/v1/namespaces/$NAMESPACE/pods/?labelSelector=app.kubernetes.io/component=balancer&app.kubernetes.io/instance=$INSTANCE_LABEL")

# TODO pass there port according values yaml
curl -v $(echo $CONTENT | jq '.items[].status.podIP + ":8080"')

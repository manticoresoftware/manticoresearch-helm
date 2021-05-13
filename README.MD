# Install

```
git clone ...
cd ../chart/
helm install manticore -n manticore --create-namespace .
```

# Quick start guide:

1) Wait until all pods are moved to running state:

```
kubectl --namespace manticore get po
```

2) Forward worker port to your local machine:

```
export WORKER_NAME=$(kubectl get pods --namespace manticore -l "app.kubernetes.io/name=manticoresearch,app.kubernetes.io/instance=manticore,label=manticore-worker" -o jsonpath="{.items[0].metadata.name}")
kubectl --namespace manticore port-forward $WORKER_NAME 7306:9306
```

3) Connect to the worker:

```
mysql -h0 -P7306
```

4) Create your first table:

```
CREATE TABLE idx(title text);
```

5) Add table to cluster:

```
ALTER CLUSTER cluster ADD idx;
```

6) Add some data to your table:

```
INSERT INTO cluster:idx (title) VALUES ('dog is brown'), ('mouse is small');
```

7) Forward worker port to your local machine:

```
export BALANCER_NAME=$(kubectl get pods --namespace manticore -l "app.kubernetes.io/name=manticoresearch,app.kubernetes.io/instance=manticore,name=manticore-manticoresearch-balancer" -o jsonpath="{.items[0].metadata.name}")
kubectl --namespace manticore port-forward $BALANCER_NAME 6306:9306
```

8) Search through the balancer:

```
mysql -h0 -P6306 -e "SELECT * FROM idx WHERE match('dog')"
```

9) Scale the cluster:

```
kubectl scale statefulsets manticore-manticoresearch-worker -n manticore --replicas=5
```

# Variables

| Variable | Description | Default |
|---|---|---|
| balancer.runInterval | Interval how often we check changes in worker scheme (new tables) | 5
| balancer.image.repository | Repository for getting balancer image | Manticore Search Gitlab
| balancer.image.tag | Balancer image version | The same as chart version
| balancer.image.pullPolicy | Balancer image updating policy | Always
| balancer.service.balancer.port | Balancer service port (for searchd) | 9306
| balancer.service.balancer.targetPort | Balancer service targetPort (for searchd) | 9306
| balancer.service.observer.port | Balancer service port (for observer) | 8080
| balancer.service.observer.targetPort | Balancer service targetPort (for observer) | 8080
| balancer.config.path | Path to balancer config | /etc/manticoresearch/configmap.conf
| balancer.config.path | Balancer config (only searchd section) | searchd<br>      {<br>        listen = 9306:mysql41<br>        log = /var/log/manticore/searchd.log<br>        query_log = /var/log/manticore/query.log<br>        query_log_format = sphinxql<br>        pid_file = /var/run/manticore/searchd.pid<br>        binlog_path = /var/lib/manticore/data<br>      }
| worker.replicaCount | Count of worker replicas | 3
| worker.clusterName | Name of replication cluster | cluster
| worker.image.repository | Repository for getting worker image | Manticore Search Gitlab
| worker.image.tag | Worker image version | The same as chart version
| worker.image.pullPolicy | Worker image updating policy | Always
| worker.service.port | Worker service port | 9306
| worker.service.targetPort | Worker service targetPort | 9306
| worker.volume.size | Size of worker storage (mounted volume) | 1Gi
| worker.config.path | Path to worker config | /etc/manticoresearch/configmap.conf
| worker.config.path | Worker config (only searchd section). <br>**Important**: you must always pass `$ip` placeholder to config for normally replication resolving | searchd<br>      {<br>        listen = 9306:mysql41<br>        listen = 9301:mysql_vip<br>        listen = $ip:9312<br>        listen = $ip:9315-9325:replication<br>        binlog_path = /var/lib/manticore/data<br>        log = /var/lib/manticore/log/searchd.log<br>        query_log = /var/log/manticore/query.log<br>        query_log_format = sphinxql<br>        pid_file = /var/run/manticore/searchd.pid<br>        data_dir = /var/lib/manticore<br>        shutdown_timeout = 25s<br>      }
| imagePullSecrets | Name of image pull secret | [name: registry-manticore]
| nameOverride | Allows to override chart name | ""
| fullnameOverride | Allows to override full chart name | ""
| serviceAccount.annotations | Allows to add SA annotations | ""
| serviceAccount.name | Service account name | "manticore-sa"
| podAnnotations | Allows to set pods annotations for worker and balancer | {}
| podSecurityContext | Allows to set pods security contexts | {}
| securityContext | Allows to set security contexts | {}
| resources | Allows to set resources like mem or cpu requests and limits | {}
| nodeSelector | Allows to set node selector for worker and balancer | {}
| tolerations | Allows to set tolerations | {}
| affinity | Allows to set affinity | {}
| exporter.enabled | Enable Prometheus exporter pod | true
| exporter.image.repository | Repository for getting exporter image | Manticore Search Gitlab
| exporter.image.tag | Exporter image version | The same as chart version
| exporter.image.pullPolicy | Exporter image updating policy | Always

# Uninstall

`helm uninstall manticore -n manticore`

**Note it will remove all your data!**

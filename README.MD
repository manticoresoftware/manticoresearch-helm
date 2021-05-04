# Quick Start

1) Wait until all pods moved to running state
   ```bash
   kubectl get po
   ```

2) Forward worker port to your local machine:
   ```bash
   WORKER_NAME=$(kubectl get pods  -l "label=manticore-worker" -o jsonpath="{.items[0].metadata.name}")
   kubectl port-forward $WORKER_NAME 7306:9306
   ```

3) Connect to worker
   ```bash
   mysql -h0 -P7306 -e "SHOW TABLES"
   ```

4) Create your first table
   ```bash
   mysql -h0 -P7306 -e "CREATE TABLE t(str text)"
   ```

5) Add table to cluster
   ```bash
   mysql -h0 -P7306 -e "ALTER CLUSTER mycluster ADD t"
   ```

6) Add some data to your table
   ```bash
   mysql -h0 -P7306 -e "INSERT INTO mycluster:t (str) VALUES ('My First Match'), ('My Second Match')"
   ```

7) Forward worker port to your local machine:
   ```bash   
   BALANCER_NAME=$(kubectl get pods -l "name=manticore-manticoresearch-balancer" -o jsonpath="{.items[0].metadata.name}")
   kubectl port-forward $BALANCER_NAME 6306:9306
   ```

8) Connect to balancer
   ```bash
   mysql -h0 -P6306 -e "SHOW TABLES"
   ```   


9) Check matches
   ```bash
   mysql -h0 -P6306 -e "SELECT * FROM t WHERE match('my')"
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
| worker.replicaCount | Count of worker replicas | 1
| worker.clusterName | Name of replication cluster | mycluster
| worker.image.repository | Repository for getting worker image | Manticore Search Gitlab
| worker.image.tag | Worker image version | The same as chart version
| worker.image.pullPolicy | Worker image updating policy | Always
| worker.service.port | Worker service port | 9306
| worker.service.targetPort | Worker service targetPort | 9306
| worker.volume.size | Size of worker storage (mounted volume) | 1Gi
| worker.config.path | Path to worker config | /etc/manticoresearch/configmap.conf
| worker.config.path | Worker config (only searchd section). <br>**Important**: you must always pass `$ip` placeholder to config for normally replication resolving | searchd<br>      {<br>        listen = 9306:mysql41<br>        listen = 9301:mysql_vip<br>        listen = $ip:9312<br>        listen = $ip:9315-9325:replication<br>        binlog_path = /var/lib/manticore/data<br>        log = /var/lib/manticore/log/searchd.log<br>        query_log = /var/log/manticore/query.log<br>        query_log_format = sphinxql<br>        pid_file = /var/run/manticore/searchd.pid<br>        data_dir = /var/lib/manticore<br>        shutdown_timeout = 25s<br>      }
| imagePullSecrets | Name of image pull secret | [name: registry-manticore]
| nameOverride | Allow to override chart name | ""
| fullnameOverride | Allow to override full chart name | ""
| serviceAccount.annotations | Allow to add SA annotations | ""
| serviceAccount.name | Service account name | "manticore-sa"
| podAnnotations | Allow to set pods annotations for worker and balancer | {}
| podSecurityContext | Allow to set pods security contexts | {}
| securityContext | Allow to set security contexts | {}
| resources | Allow to set resources like mem or cpu requests and limits | {}
| nodeSelector | Allow to set node selector for worker and balancer | {}
| tolerations | Allow to set tolerations | {}
| affinity | Allow to set affinity | {}


# Versioning

We use the standard versioning scheme `Major`.`Minor`.`Path`

The helmchart version changes **along** with the package version. That avoids the situation when **worker** has version `0.0.1` and **chart** `0.0.2`

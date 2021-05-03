
# Версионирование
## Major.Minor.Path

версия хелм чарта меняется вместе с версией пакетов. Соответственно не может быть ситуации что у нас воркер версии 0.0.1 а чарт 0.0.2

# Quick Start

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




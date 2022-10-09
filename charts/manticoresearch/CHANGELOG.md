### 5.0.22
We added replication mode switcher. This feature can take one of two values `multi-master` or `master-slave`.

In master-master mode has no matter which node you use for writing and reading. This is a more simple way but you can't control the relevance of data at nodes in case the emergency shutdown

Master-slave expects that you will write only on zero replicas, so there will be most actual data. In case the cluster was broken all nodes will try to connect at zero node

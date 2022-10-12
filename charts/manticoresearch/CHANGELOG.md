### 5.0.22
Added replication mode switcher. Now you can choose between `multi-master` and `master-slave`.

In the `multi-master` mode it doesn't matter to which node you write and from what node you read. This is a simpler and more efficient approach, but in case of an emergency cluster shutdown when all the nodes are down at the same time you have to recover the cluster  manually.

The `master-slave` mode expects that you write only to node 0, so it's guaranteed to always have the most actual data. Then in case the cluster is fully shut down and then back up all other nodes will connect to the node 0.

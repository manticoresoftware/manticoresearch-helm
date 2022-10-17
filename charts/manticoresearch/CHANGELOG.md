### 5.0.23

Added better handling of replication quorum issues. Now in case replication breaks due to no quorum, k8s probe will run a repair script which will fix the cluster at node 0.

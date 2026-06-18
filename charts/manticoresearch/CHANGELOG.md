### 25.0.0-20260618

* Switched to ManticoreSearch 25.0.0
* Exposed binary protocols to worker and balancer services 
* Add binary port listener to balancer
* Enhanced probes for improved support of large volumes
* Introduced config maps and additional volumes to better support wordforms
* Added searchd start flags support
* Added configurable pod labels for traffic-routing integrations
* Support persistent balancer agent
* Added `worker.mlock.enabled` and validation for `access_* = mlock` configs that require `IPC_LOCK`
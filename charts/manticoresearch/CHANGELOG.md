### 29.0.2-20260831

* Added `worker.persistence.volumeAttributesClassName` for assigning a Kubernetes VolumeAttributesClass to worker PVCs

### 29.0.2-20260817

* Added `worker.mlock.enabled` and validation for `access_* = mlock` configs that require `IPC_LOCK`
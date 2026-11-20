### 32.1.3

* Updated the worker and balancer images to Manticore Search 32.1.3.
* **Upgrade warning:** Rolling upgrades from 29.9.0 are not supported for replication clusters because the replication protocol changed. Follow the [cluster upgrade instructions](https://manual.manticoresearch.com/Changelog#Upgrading-from-29.9.0) before upgrading.

### 29.9.0.1

* Fixed worker and balancer images to use Manticore Search 29.9.0.

### 29.9.0

* Switched to ManticoreSearch version 29.9.0
* feat: add worker mlock IPC_LOCK support

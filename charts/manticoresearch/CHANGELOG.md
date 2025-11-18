### 14.1.0

* **Feat:** Switched to ManticoreSearch 14.1.0

  **Breaking change:** If you are updating from a previous version, worker pods will crash due to the [protocol version change](https://manual.manticoresearch.com/dev/Changelog#Version-14.1.0)

  To **fix this**, scale the worker StatefulSet down to zero, wait until it fully shuts down, and then scale it back up to the previous replica count.
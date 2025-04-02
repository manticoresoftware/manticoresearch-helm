### 9.2.14

* 🚀 Release Manticore Search 9.2.14
* ⚠️ BREAKING CHANGE – [Issue #manticoresearch/3186]
  * This is automatically handled in the default worker configuration.
  * If you are using a custom configuration, make sure to add:

    ```
    server_id = $server_id
    ```

    inside the [searchd] section of your config.

    This lets the chart automatically assign a unique `server_id` based on the StatefulSet instance.

---

### ℹ️ If you encounter an error during update:
1. Make sure that your worker's config has the `server_id` section
2. Scale down the worker StatefulSet to 0
3. Scale it back up to the previous number of replicas.
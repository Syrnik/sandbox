# Sandbox App — Developer Notes

## Working with Models

All sandbox models must be instantiated through the `sandbox()->getModel('Name')` factory, not directly:

```php
// Correct
$model = sandbox()->getModel('Snippet');
$model = sandbox()->getModel('Folder');
$model = sandbox()->getModel('Environment');

// Wrong — bypasses configured DB connection
$model = new sandboxSnippetModel();
```

This ensures the database connection configured in `wa-config/apps/sandbox/config.php` (`db_connection` key) is used. If the key is absent or not found in `wa-config/db.php`, falls back to `'default'`.

**Exception:** `sandboxHelper` receives the DB type via its constructor `$dbType` parameter (passed from `sandboxConfig::getDbType()`). When instantiating `sandboxHelper` outside of `sandboxConfig` (e.g., in `sandboxExecutor`), pass `sandbox()->getDbType()` explicitly:

```php
$helper = new sandboxHelper($contactId, sandbox()->getDbType());
```

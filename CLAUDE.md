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

**Note:** `sandboxHelper`'s constructor takes a single `$contactId` parameter — it resolves the DB connection lazily and internally via `sandbox()->getModel('Variable')` on first use, not through a constructor argument:

```php
$helper = new sandboxHelper($contactId);
```

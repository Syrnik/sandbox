> [Русский / Russian](README.ru.md)

# Sandbox for Webasyst

An interactive sandbox for developing and debugging PHP and Smarty code directly
in the Webasyst backend.

[![PHP Compatibility](https://github.com/Syrnik/sandbox/actions/workflows/main.yml/badge.svg)](https://github.com/Syrnik/sandbox/actions/workflows/main.yml)

## Features

- PHP code editor with syntax highlighting (ACE editor)
- Smarty template editor with a cheat sheet of available variables
- Environments — named sets of variables injected at execution time
- Snippet library with folders, shared access, and quick search
- Personal user variables, persistent between runs

---

## PHP API — `sandboxHelper`

A helper for working with personal user variables from PHP snippet code.

```php
$helper = new sandboxHelper($contactId);
```

| Method | Description |
|--------|-------------|
| `set(string $name, mixed $value): void` | Save a variable. Non-string values are serialized automatically |
| `get(string $name, mixed $default = null): mixed` | Get a variable. Returns `$default` if not found |
| `delete(string $name): void` | Delete a variable |
| `getAll(): array` | Get all user variables |

### Example (PHP editor)

```php
$helper = new sandboxHelper(wa()->getUser()->getId());

$helper->set('counter', 42);
$helper->set('last_run', new DateTime());

echo $helper->get('counter');        // 42
echo $helper->get('missing', 'n/a'); // n/a

print_r($helper->getAll());

$helper->delete('counter');
```

---

## PHP API — `sandboxConfig` / `sandbox()`

The global `sandbox()` function returns a `sandboxConfig` object — a thin wrapper
around `sandboxHelper`, bound to the currently authenticated user.

```php
sandbox()->setVar('key', $value);
$value = sandbox()->getVar('key', $default);
sandbox()->deleteVar('key');
$all = sandbox()->getAllVars();
```

| Method | Description |
|--------|-------------|
| `setVar(string $name, mixed $value): void` | Save a variable for the current user |
| `getVar(string $name, mixed $default = null): mixed` | Get a variable for the current user |
| `deleteVar(string $name): void` | Delete a variable for the current user |
| `getAllVars(): array` | Get all variables for the current user |

### Example (PHP editor)

```php
// Cumulative run counter
$count = sandbox()->getVar('runs', 0) + 1;
sandbox()->setVar('runs', $count);
echo "Run #$count";
```

---

## Smarty API — `$wa->sandbox`

`sandboxViewHelper` exposes the same methods in Smarty templates via the standard
`waAppViewHelper` mechanism. Available as `$wa->sandbox`.

| Expression | Description |
|-----------|-------------|
| `{$wa->sandbox->getVar('name')}` | Output a variable for the current user |
| `{$wa->sandbox->getVar('name', 'default')}` | Output a variable with a fallback value |
| `{$wa->sandbox->getAllVars()}` | Get an array of all variables |
| `{$wa->sandbox->setVar('name', $value)}` | Save a variable |
| `{$wa->sandbox->deleteVar('name')}` | Delete a variable |

### Example (Smarty editor)

```smarty
{* Read a variable set from PHP *}
<p>Last value: {$wa->sandbox->getVar('last_value', '—')}</p>

{* Iterate over all saved variables *}
{foreach $wa->sandbox->getAllVars() as $var}
    <b>{$var.name}</b>: {$var.value}<br>
{/foreach}
```

---

## Environments

An environment is a named set of variables (key → value). At execution time,
variables are injected directly via `extract()` — as plain PHP and Smarty variables.
This makes it easy to parameterize snippets without changing the code.

```php
// If the selected environment has variables host=db.local, port=3306
echo $host; // db.local
echo $port; // 3306
```

In a Smarty template:

```smarty
{$host} {* db.local *}
{$port} {* 3306 *}
```

> Environment variable values are **always strings**. The UI does not support arrays,
> objects, or `null`: an empty field is saved as an empty string `""`. If you need an
> array, store a JSON string and parse it manually: `$data = json_decode($json_var, true)`.

> If a variable name conflicts with an internal executor variable, the environment
> variable receives an `env_` prefix (e.g., `$env_code`).

Shared environments are visible to all users; personal ones are visible only to the owner.

---

## Database Connection Configuration

The app supports using a separate database connection other than `'default'`. This is
useful when one project is used across multiple configurations (local and shared dev
database), and snippet data must always be stored in a specific database.

### 1. Add a named connection to `wa-config/db.php`

```php
return [
    'default' => [ /* ... */ ],

    'sandbox_local' => [
        'host'     => 'localhost',
        'port'     => false,
        'user'     => 'root',
        'password' => '',
        'database' => 'my_local_db',
        'type'     => 'mysqli',
    ],
];
```

### 2. Create `wa-config/apps/sandbox/config.php`

```php
<?php
return [
    'db_connection' => 'sandbox_local',
    'contact_id'    => 1,   // optional — see below
];
```

This file is read automatically by the framework. If the file is missing or the
specified key is not found in `db.php`, the app falls back to the `'default'` connection.

### Fixing contact_id

The ID of the same user can differ across Webasyst installations. Personal snippets
(non-shared) are filtered by `contact_id`, so without a fixed ID they may be
inaccessible when switching between installations.

Add the `contact_id` key with the required value — the app will use it instead of
the currently authenticated user's ID:

```php
return [
    'db_connection' => 'sandbox_local',
    'contact_id'    => 1,
];
```

If the key is not set, `wa()->getUser()->getId()` is used (default behavior).

> **Important:** When connecting to a new database, the app's tables will not exist
> there. You can create them in two ways:
> - **Via Webasyst:** go to the Installer section → reinstall the Sandbox app
>   (the app uses the standard migration mechanism via `lib/config/db.php`).
> - **Manually:** copy all tables with the `sandbox_` prefix from the original database.

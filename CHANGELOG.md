# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] — 2026-05-13

### Added
- Recent snippets: the sidebar shows recently run and saved snippets.
- Selected environment is now persisted in localStorage between sessions.
- Output panel: compact font (class `small`, `line-height: 1.2`).

## [1.1.0] — 2026-05-11

### Added
- Support for a custom database connection and fixed `contact_id`. Create
  `wa-config/apps/sandbox/config.php` with a `db_connection` key pointing to a named
  connection from `wa-config/db.php`. Falls back to the `default` connection if the
  config file is absent or the key is not found.
- `sandboxConfig::getModel(string $name)` — a model factory that guarantees the
  configured database connection is used.
- Protection against calling app models from within a Smarty template context.

## [1.0.3] — 2026-04-16

### Fixed
- Long words now wrap correctly in snippet card descriptions.
- "Create snippet" button was opening the editor with the last snippet's content
  instead of an empty editor.
- Syntax highlighting for PHP code without an explicit `<?php` tag.

## [1.0.2] — 2026-04-14

### Fixed
- `wa_dump()` output was shown in the ajax error modal instead of the result panel.

## [1.0.1] — 2026-04-13

### Fixed
- Smarty template was not executed when the "Execute Smarty" checkbox was unchecked.
- Editor and result columns are now always equal width.

## [1.0.0] — 2026-04-12

### Added
- PHP and Smarty editors with syntax highlighting (ACE editor).
- Environments — named sets of variables injected at execution time.
- Snippet library with folders, nesting, search, and shared access.
- Export and import of snippets, folders, and environments to JSON.
- Personal user variables (`sandboxHelper`, `sandboxConfig`, `$wa->sandbox`).
- Snippet duplication and moving between folders.
- Bulk access management (share / restrict for folders and snippets).

# MODX Vapor

**Site teleporter for MODX Revolution** — creates a complete snapshot of a MODX site (database + files) as a `.transport.zip` package, ready for import on another server.

> This is a port of [MODX-Club/vapor](https://github.com/MODX-Club/vapor) for **MODX 3.x / xPDO 3 / PHP 8.2+**.

---

## 📋 Table of Contents

- [What is Vapor](#what-is-vapor)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Running via CLI](#running-via-cli)
  - [Running via Browser](#running-via-browser)
  - [Custom Options](#custom-options)
- [Importing the Package](#importing-the-package)
- [Troubleshooting](#troubleshooting)
- [MODX 3 / xPDO 3 Adaptation](#modx-3--xpdo-3-adaptation)
  - [Summary of Changes](#summary-of-changes)
  - [Detailed Technical Reference](#detailed-technical-reference)
- [Changelog](#changelog)
- [License](#license)

---

## What is Vapor

MODX Vapor is a PHP script that extracts a complete snapshot of a MODX site — including all database content, files, and components — into a standard MODX transport package (`.transport.zip`). This package can then be imported into any other MODX Revolution installation.

Typical use cases:

- **Site migration** between servers
- **Full backup** in transportable format
- **Cloning** a site for development/staging
- **Deploying** a pre-built site to MODX Cloud or any MODX host

---

## Requirements

- **MODX Revolution 3.x** (tested on 3.2.3-pl)
- **PHP 8.1+** (tested on 8.2)
- **PHP Zip extension** (`ext-zip`)
- All standard [MODX 3 requirements](https://docs.modx.com/3.x/en/getting-started/server-requirements)

> ℹ️ For MODX 2.x / PHP 5–7, use the [original Vapor](https://github.com/MODX-Club/vapor).

---

## Installation

1. Copy the `vapor/` directory into your `MODX_BASE_PATH` (the web root, next to `index.php`).

   ```
   /var/www/html/
   ├── index.php
   ├── core/
   ├── manager/
   ├── connectors/
   ├── assets/
   └── vapor/          ← here
       ├── vapor.php
       ├── model/
       └── scripts/
   ```

2. Make sure the directory is readable/writable by the web server or PHP CLI user.

---

## Usage

Vapor can be run from the **CLI** or from a **web browser**. CLI is recommended for large sites to avoid timeouts.

The snapshot will be created in `MODX_CORE_PATH . 'packages/'` with the name shown in the script output.

### Running via CLI

```bash
cd /var/www/html
php vapor/vapor.php
```

Expected output:

```
Completed extracting package: localhost-260819.1759.23-3.2.3-pl
Vapor execution completed without exception in 12.7s
```

The `.transport.zip` file will be in `core/packages/`:

```bash
ls -lh core/packages/localhost-*.transport.zip
```

### Running via Browser

Navigate to:

```
http://your-site-url/vapor/vapor.php
```

Wait for the process to complete. The download path will be shown on screen.

> ⚠️ For large sites, browser execution may time out. Use CLI instead.

### Custom Options

Create a `vapor/config.php` file to customize the export:

```php
<?php
return array(
    'excludeFiles' => array(),
    'excludeExtraTables' => array(),
    'excludeExtraTablePrefix' => array(),
);
```

| Option | Description |
|---|---|
| `excludeFiles` | Array of file/directory names to exclude from `MODX_BASE_PATH` (no trailing `/` on directories). |
| `excludeExtraTables` | Array of non-core database tables to exclude from the package. |
| `excludeExtraTablePrefix` | Array of non-core tables that should **not** get the target's `table_prefix` prepended on import. Only relevant if the source site does not use a `table_prefix`. |

---

## Troubleshooting

### Review the Vapor log

Each run creates a log file in `core/cache/logs/`:

```bash
cat core/cache/logs/vapor-*.log
```

Check for errors:

```bash
grep -iE "error|fail|could not" core/cache/logs/vapor-*.log
```

### Known non-critical warnings

| Warning | Cause | Impact |
|---|---|---|
| `Could not load package metadata for Sterc\FormIt\Model` | FormIt uses a legacy model format | None |
| `Creation of dynamic property modPackageBuilder::$modx is deprecated` | PHP 8.2 deprecation in MODX core | None |

### Common issues

#### `Table doesn't exist` after import

If non-core tables (e.g., `seosuite_resource`, `ms3_customer_tokens`) are missing after import, ensure you're using the **adapted version** of Vapor (this fork). The original version has a bug where `vaporVehicle` is not loaded correctly in xPDO 3 — see [§ 2.11 in the adaptation doc](#211-vaporvehicle--fqn-for-custom-tables-critical-import-fix).

#### `Call to a member function checkPolicy() on null`

This occurs in CLI mode when `$modx->user` is anonymous. The adapted version sets a real user (ID 1) in CLI mode. Ensure you're running the adapted `vapor.php`.

---

## MODX 3 / xPDO 3 Adaptation

The original Vapor was written for MODX 2.x / PHP 5–7. This fork includes all changes needed for MODX 3.2.x / xPDO 3 / PHP 8.2.

### Summary of Changes

| # | Change | Reason |
|---|---|---|
| 1 | `strftime()` → `date()` | Removed in PHP 8.1 |
| 2 | `each()` → `foreach` | Removed in PHP 8.0 |
| 3 | `xPDO::OPT_SETUP` removed | Breaks `_initContext()` in MODX 3 |
| 4 | CLI user injection | `hasPermission()` crashes without a real user |
| 5 | FQN for `modPackageBuilder` | Short class aliases are lazy in xPDO 3 |
| 6 | FQN for `$modx->call()` | `call()` doesn't resolve short names |
| 7 | FQN for `vehicle_class` (`xPDOFileVehicle`) | `loadClass()` needs namespaced names |
| 8 | **FQN `\vaporVehicle` for custom tables** | **Critical: tables not created on import without this** |
| 9 | `getTableName()` empty check | Abstract classes return empty string |
| 10 | `getOption('database')` fallback | Can be `NULL` in MODX 3 |
| 11 | Removed `version_compare('2.2.0')` block | Dashboard/media source classes always exist in MODX 3 |
| 12 | `vaporVehicle` extends `xPDO\Transport\xPDOVehicle` | Correct base class in xPDO 3 |
| 13 | `install()` returns `true` | Original always returned `false` |
| 14 | `instanceof` uses FQN in resolvers | Short names don't work without preloading |
| 15 | Added new MODX 3 classes | `modAccessNamespace`, `modExtensionPackage`, etc. |
| 16 | Removed obsolete MODX 2 classes | `modAction`, `modAccessAction`, `modClassMap` |

### Detailed Technical Reference

For the full technical documentation of all changes — including code diffs, xPDO 3 internals analysis, and the import pipeline walkthrough — see **[VAPOR_MODX3_ADAPTATION.md](VAPOR_MODX3_ADAPTATION.md)**.

Key sections in that document:

- **§1** — MODX class list changes (added/removed/unchanged)
- **§2** — All changes in `vapor.php` (11 items with before/after code)
- **§3** — Changes in `vaporvehicle.class.php`
- **§4** — Changes in resolver/validator scripts
- **§5** — Import pipeline on the target server (step-by-step)
- **§7** — API compatibility reference table
- **§9** — Change history

---

## Changelog

### 1.1.0-beta — Port to MODX 3 / xPDO 3 / PHP 8.2

- **PHP 8.2 compatibility:** `strftime()` → `date()`, `each()` → `foreach`
- **xPDO 3 namespaces:** all `vehicle_class`, `modPackageBuilder`, `modx->call()`, and `instanceof` use FQN
- **Critical fix:** `vaporVehicle` uses `\vaporVehicle` (FQN) instead of `vehicle_package = 'vapor'` — fixes custom tables not being created on import
- **CLI mode:** injects user ID 1 to prevent `checkPolicy()` crash
- **`xPDO::OPT_SETUP`** removed — was blocking `_initContext()` in MODX 3
- **`vaporVehicle::install()`** now returns `true` on success
- **`vaporVehicle`** extends `xPDO\Transport\xPDOVehicle` (not `xPDOTransportVehicle`)
- **New MODX 3 classes** added: `modAccessNamespace`, `modExtensionPackage`, `modDeprecatedCall`, `modDeprecatedMethod`, `modUserGroupSetting`
- **Obsolete MODX 2 classes** removed: `modAction`, `modAccessAction`, `modClassMap`
- **`getTableName()`** empty result check for abstract classes
- **`getOption('database')`** fallback to empty string
- **Resolvers** use FQN for `instanceof` checks

---

## License

MODX Vapor is Copyright 2012 by MODX, LLC.

GPL v2 or (at your option) any later version.

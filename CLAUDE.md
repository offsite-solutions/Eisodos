# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Eisodos is a PHP 8.4+ (tested on 8.5) page generation framework with singleton-based architecture. It provides configuration management, template processing, internationalization, logging, and database abstraction.

This repository is the **framework source** (`offsite-solutions/eisodos`). Plugin packages live in sibling directories:

- `/Users/baxi/Work/_eisodos/Connectors/{Oracle,PDOPgSQL,PDOSQLServer,MDB2}` — DB connectors
- `/Users/baxi/Work/_eisodos/SQLParser` — `<%SQL%…%SQL%>` template parser

For porting a legacy `udSCGI` application to Eisodos, see the deep reference manual at [`documentation/migration_from_udSCGI.md`](documentation/migration_from_udSCGI.md). It covers bootstrap shapes, config layout, helper endpoints, gotchas, and step-by-step recipe — derived from six completed real migrations (drp-v2, greengo frontend / backoffice / partnerportal, ldu backoffice, duvenbeck v3 backoffice).

## Running Tests

Tests are located in `tests/` and are standalone PHP scripts (no PHPUnit):

```bash
php tests/test_configLoader_INI.php
php tests/test_configLoader_JSON.php
php tests/test_configLoader_INI_environment.php
php tests/test_templateEngine_1.php
php tests/test_templateEngine_2_callbacks.php
```

## Architecture

### Singleton Pattern

All framework modules extend `Abstracts/Singleton.php` and are accessed as static properties on the main `Eisodos` class:

```php
Eisodos::$parameterHandler   // Parameter management (GET/POST/SESSION/cookies merged)
Eisodos::$configLoader       // INI/JSON config file loading with sections
Eisodos::$templateEngine     // Template loading, parsing, callback execution
Eisodos::$translator         // Multi-language support with language IDs
Eisodos::$logger             // PSR-3 style logging with multiple outputs
Eisodos::$mailer             // PHPMailer-based email sending
Eisodos::$render             // Page generation orchestration
Eisodos::$dbConnectors       // Database connector registry
Eisodos::$utils              // Utility functions
```

### Bootstrap Example (minimal, single-file)

For small apps with one entry point:

```php
<?php
use Eisodos\Eisodos;
use Eisodos\Connectors\ConnectorPDOPgSQL;
use Eisodos\Parsers\CallbackFunctionParser;
use Eisodos\Parsers\CallbackFunctionShortParser;

require_once '../vendor/autoload.php';

try {
    Eisodos::getInstance()->init([__DIR__, 'myapp']);

    Eisodos::$render->start(
        ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
        [],  // Cache options
        [],  // Template engine options
        ''   // Debug level (trace/debug/info/warning/error/critical)
    );

    Eisodos::$templateEngine->registerParser(new CallbackFunctionParser());
    Eisodos::$templateEngine->registerParser(new CallbackFunctionShortParser());
    require_once(__DIR__ . '/_callbacks.php');
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

    Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL());
    Eisodos::$dbConnectors->connector()->connect();
} catch (Exception $e) {
    if (!isset(Eisodos::$logger)) { die($e->getMessage()); }
    Eisodos::$logger->writeErrorLog($e);
    exit(1);
}

// ... application code ...

Eisodos::$render->finish();     // HTML output
// or Eisodos::$render->finishRaw();  // JSON/raw output
```

### Recommended Application Layout (`src/`-based, four-file bootstrap)

For any app with more than one entry point (greengo / ldu / duvenbeck pattern). All four recent migrations share this exact shape — copy it for any new app:

```
<appname>/
├── composer.json              # production manifest (pinned, no dev deps)
├── composer.dev.json          # OPTIONAL: dev manifest with /opt/local-dev/composer/... path repos
├── composer_update.sh         # prod composer wrapper
├── composer_update_dev.sh     # dev composer wrapper (COMPOSER=composer.dev.json composer update ...)
├── vendor/
├── src/                       # all PHP entry points + bootstrap
│   ├── __eisodos.php          # framework wiring only (Eisodos init + connector register)
│   ├── _init.php              # 3-liner: requires __eisodos.php, _autoload.php, _init_parameters.php
│   ├── _autoload.php          # SPL autoloader for the app namespace (e.g. `myapp\`)
│   ├── _init_parameters.php   # LANG → derived params (DateFormat, JSDateFormat, PHPDateFormat, …)
│   ├── _callbacks.php         # callback_default() — uses Eisodos::$utils->safe_array_value()
│   ├── index.php              # typically a redirect to the homepage URL
│   ├── ls_client.php          # OPTIONAL: LangServer (translation server) client
│   ├── bo_fileHandler.php     # OPTIONAL: file ingestion / serving endpoint
│   ├── fe_fileHandler.php     # OPTIONAL: frontend → backoffice file proxy
│   ├── bo_mailSender.php      # OPTIONAL: JSON-driven mail dispatcher
│   ├── bo_pushGateway.php     # OPTIONAL: outbound JSON push
│   ├── bo_printDocument.php   # OPTIONAL: print template renderer
│   ├── <Module>.php           # one file per business module, namespace `<appname>`
│   └── assets/
│       └── templates/         # *.template — Eisodos template syntax
├── i18n/                      # generated.txt / translated.txt / langids.txt
└── config/                    # OPTIONAL — often externalized to Docker mount
```

**Naming and conventions used across every recent migration:**

| File / dir | Convention |
| --- | --- |
| `__eisodos.php` | Two leading underscores. Framework wiring only — never app logic. |
| `_init.php` | Includes `__eisodos.php`, `_autoload.php`, `_init_parameters.php` in that order. |
| `_autoload.php` | Declares `namespace <appname>` and registers an SPL loader (see below). |
| `_init_parameters.php` | Pure `setParam(...)` for LANG-derived values; no business logic. |
| `_callbacks.php` | Uses `Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob') === '...'` for every branch. |
| Domain module class | One file per class, `namespace <appname>;`, classname = filename without `.php`. |
| Indentation | 2 spaces in PHP files. Don't reformat to 4. |

#### `__eisodos.php` — framework wiring (no app logic)

```php
<?php
use Eisodos\Eisodos;
use Eisodos\Connectors\ConnectorPDOPgSQL;       // or ConnectorOCI8 / ConnectorPDOSQLSrv / ConnectorMDB2
use Eisodos\Parsers\CallbackFunctionParser;
use Eisodos\Parsers\CallbackFunctionShortParser;

require_once '../vendor/autoload.php';

try {
    Eisodos::getInstance()->init([__DIR__, 'myapp']);
    Eisodos::$render->start(
        ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
        [], [], ''
    );
    require_once(__DIR__ . '/_callbacks.php');
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

    Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL());
    // Lazy connection: do NOT call ->connect() unless you need fail-fast bootstrap.
} catch (Exception $e) {
    if (!isset(Eisodos::$logger)) { die($e->getMessage()); }
    Eisodos::$logger->writeErrorLog($e);
    exit(1);
}
```

The app-name argument to `init([$dir, $name])` is **decoupled from the directory**; it drives config-file naming (`{env}-<name>.conf`), `$_applicationname`, log paths. Pick it to match the deployed config files.

#### `_init.php` — keep it tiny

```php
<?php
require_once __DIR__ . '/__eisodos.php';
require_once('_autoload.php');
require_once('_init_parameters.php');
```

#### `_autoload.php` — SPL autoloader for the app namespace

```php
<?php
namespace myapp;

spl_autoload_register(function ($class) {
    if (str_starts_with($class, __NAMESPACE__)) {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . explode('\\', $class)[1] . '.php');
    }
});
```

Resolves `myapp\Foo` → `src/Foo.php`. **One level only** — subnamespaces silently fail. Use Composer PSR-4 if you need nesting.

#### `_init_parameters.php` — LANG → derived format parameters

```php
<?php
use Eisodos\Eisodos;

if (Eisodos::$parameterHandler->neq("LANG", "")) {
    Eisodos::$parameterHandler->setParam("LANG",
        strtoupper(Eisodos::$parameterHandler->getParam('LANG')), true);
}

foreach (['', 'JS', 'PHP'] as $family) {
    foreach (['DateFormat', 'DateTimeFormat', 'DateTimeHMFormat', 'TimeFormat'] as $kind) {
        $configKey = Eisodos::$parameterHandler->getParam("LANG") . '.' . strtoupper($family . $kind);
        Eisodos::$parameterHandler->setParam($family . $kind,
            Eisodos::$parameterHandler->getParam($configKey));
    }
}

Eisodos::$parameterHandler->setParam("LANG_SMALL",
    strtolower(Eisodos::$parameterHandler->getParam("LANG")));
```

Requires `HU.DATEFORMAT=...`, `HU.JSDATEFORMAT=...`, `HU.PHPDATEFORMAT=...` (and equivalents per language in `Langs=`) in config. Templates then reference `$DateFormat` / `$JSDateFormat` / `$PHPDateFormat`.

#### Choosing `_init.php` vs `__eisodos.php` for an entry point

- **`include '_init.php'`** — HTML pages, anything that loads app-namespaced classes, anything that uses `$DateFormat`-family params.
- **`require_once __DIR__ . '/__eisodos.php'`** — helper endpoints that need framework only (`ls_client.php`, `bo_mailSender.php`, `bo_pushGateway.php`). Skips autoloader + date params.

### Framework Directory Structure

```
src/Eisodos/
├── Abstracts/Singleton.php      # Base singleton implementation
├── Interfaces/                   # DBConnectorInterface, ParserInterface
├── Parsers/                      # CallbackFunction parsers
├── config/                       # Default config templates (common_pre_*.conf)
├── Eisodos.php                   # Main entry point
├── ParameterHandler.php          # Parameter merging
├── ConfigLoader.php              # Config file loading
├── TemplateEngine.php            # Template processing
├── Render.php                    # Page generation
├── Logger.php                    # Logging system
├── Translator.php                # i18n support
├── Mailer.php                    # Email via PHPMailer
├── DBConnectors.php              # DB registry
└── Utils.php                     # Utilities
```

## Configuration

Config files are INI or JSON format with sections. Environment is selected from a one-line `config/environment` file (`dev`, `local`, `test`, `live`). The entry config file is `{env}-{appname}.conf`.

```ini
[Env]                 ; environment variables (putenv'd)

[PreInclude]          ; loaded BEFORE this file's [Config]
1=/app/dist/vendor/offsite-solutions/eisodos/src/Eisodos/config/common_pre_dev.conf:Config
2=global.conf:Config

[PostInclude]         ; loaded AFTER this file
1=version.conf:Version

[Database]            ; DB connector options (see below)
driver=pgsql
user=myapp
host=localhost
dbname=myapp

[Config]              ; merged into the parameter handler at startup
TemplateDir=$_applicationDir/templates/
.ErrorLog=$_applicationDir/logs/$_applicationName-error.log
ErrorOutput=File,Mail
```

Key sections:
- `[Config]` — Main parameters (loaded into ParameterHandler)
- `[Database]` — Mandatory when registering a DB connector (see below)
- `[PreInclude]` / `[PostInclude]` — Config file includes with format `file:Section`
- `[Env]` — Environment variables to set

**Readonly parameters**: prefix with `.` to prevent HTTP-parameter override (e.g., `.ErrorLog`, `.TemplateDir`). Without the dot, an attacker can redirect logs via `?errorlog=...`.

**Always PreInclude the shipped defaults.** `common_pre_{dev,test,live}.conf` lives under `vendor/offsite-solutions/eisodos/src/Eisodos/config/` and sets sensible defaults for `EnableInlineCallback`, `DebugMessages`, `ShowMissingTemplate`, `TitleString`, etc. Don't duplicate those keys in your per-app file — rely on the include.

**Variable substitution** in values: `$_applicationDir`, `$_applicationName`, `$_environment`.

**Config externalization (Docker pattern).** Every greengo / ldu / duvenbeck deployment keeps `config/` outside the app repo and mounts it at runtime from `/Users/baxi/Work/_docker_images/applications/<app>/config/config/` into `/etc/app/config` inside the container (with `EISODOS_CONFIG_PATH=/etc/app/config`). drp-v2 checks `config/` in. Both are valid; choose based on whether the app ships as a Docker image with environment-specific overrides.

### `.params` file — input filter

```
exclude;SESSIONID                                # don't accept from HTTP at all
session;LANG                                     # store in $_SESSION
cookie;EDITOR                                    # store as cookie
encoded;CSID                                     # udSDecode() before applying
session;SESSION_*                                # wildcard (trailing *)
input;P_LANGUAGE;/^(HU|EN|CZ)$/i;HU;Nyelvhiba    # validate; fall back to HU on mismatch
session;LANG;/^(HU|EN|CZ)$/i;HU;Nyelvhiba        # rules can stack
```

Format: `<scope>;<NAME>;<regex>;<default>;<error_msg>`. Scopes: `exclude`, `session`, `cookie`, `permanent`, `encoded`, `cookie_encoded`, `session_encoded`, `protected`, `protected_encoded`, `input`, `skip`.

## Parameter Handling

```php
// Get/set parameters
$value = Eisodos::$parameterHandler->getParam('key', 'default');
Eisodos::$parameterHandler->setParam('key', 'value', $sessionStored, $cookieStored);

// Comparison methods
Eisodos::$parameterHandler->eq('status', 'active')   // equals
Eisodos::$parameterHandler->neq('error', '')         // not equals (common for empty check)
Eisodos::$parameterHandler->isOn('DEBUG')            // T, ON, 1, TRUE, YES, Y
Eisodos::$parameterHandler->isOff('CACHE')           // F, OFF, 0, FALSE, NO, N

// Reference another parameter with ^ prefix
Eisodos::$parameterHandler->setParam('current', '^default_lang');
```

### Built-in Dynamic Variables

| Variable | Description |
|----------|-------------|
| `$seq`, `$seq0`, `$seql`, `$seqbit` | Sequence counter, reset, last value, modulo 2 |
| `$currdate` | Current year |
| `$random` | Random 8-char string |
| `$_sessionid` | Session ID |
| `$https` | Protocol (http/https) |
| `$lnbr` | Line break (PHP_EOL) |
| `$env_VARNAME` | Environment variable |

## Template Syntax

```html
$parameterName              <!-- Parameter reference -->
$param~='default';          <!-- With default value -->
$templateabs_header_main    <!-- Embed header.main.template -->
[:LANG_ID,default:]         <!-- Language translation -->
##comment line              <!-- Removed in output -->
```

### Callback Functions

Short syntax `[%...%]`:
```html
<!-- Equality check - returns string -->
[%funcjob=eqs;param=is_active;value=Y;true=active;false=inactive%]

<!-- Equality check - returns template -->
[%funcjob=eq;param=show_search;value=T;true=search.box;false=empty%]

<!-- Case/switch -->
[%funcjob=cases;param=status;ACTIVE=green;PENDING=yellow;else=gray%]

<!-- Date functions -->
[%funcjob=today%]
[%funcjob=now%]
[%funcjob=lastweek%]
```

Full syntax `<%FUNC%...%FUNC%>`:
```html
<%FUNC%
_function_name=renderUserCard
@name=$current_user_name
role=admin
%FUNC%>
```

### Common Template Patterns

**Boolean display:**
```html
<span class="[%funcjob=eqs;param=prop_value;value=Y;true=text-success;false=text-danger%]">
    [%funcjob=eqs;param=prop_value;value=Y;true=[:YES,Yes:];false=[:NO,No:]%]
</span>
```

**Status with color coding:**
```html
<span class="text-[%funcjob=cases;param=status;ACTIVE=success;PENDING=warning;DELETED=danger;else=secondary%]">
    $status
</span>
```

**Form select with selected state:**
```html
<select name="status">
    <option value="ACTIVE" [%funcjob=eqs;param=status;value=ACTIVE;true=selected;false=%]>Active</option>
    <option value="INACTIVE" [%funcjob=eqs;param=status;value=INACTIVE;true=selected;false=%]>Inactive</option>
</select>
```

**Date filter defaults:**
```html
<input type="text" name="date_from" value="$date_from~='[%funcjob=lastweek%]';">
<input type="text" name="date_to" value="$date_to~='[%funcjob=today%]';">
```

## Callback Function Implementation

```php
function callback_default(array $LFuncParams = []): mixed {
    $funcjob = Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob');

    // Equality check - returns string
    if ($funcjob === 'eqs') {
        if (Eisodos::$parameterHandler->eq(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param'),
            Eisodos::$utils->safe_array_value($LFuncParams, 'value')
        )) {
            return Eisodos::$utils->safe_array_value($LFuncParams, 'true');
        }
        return Eisodos::$utils->safe_array_value($LFuncParams, 'false');
    }

    // Case/switch - returns string
    if ($funcjob === 'cases') {
        $paramValue = Eisodos::$parameterHandler->getParam(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param')
        );
        return Eisodos::$utils->safe_array_value(
            $LFuncParams, $paramValue,
            Eisodos::$utils->safe_array_value($LFuncParams, 'else')
        );
    }

    return '';
}
```

## Common funcjob branches

Every recent migration's `_callbacks.php` ships the same date/time helper set. Implement these in the order templates need them:

| funcjob | Returns |
| --- | --- |
| `eq` / `eqs` | Conditional template / scalar based on `param == value`. |
| `case` / `cases` | Template / scalar based on `param` value mapped to a `<value>=<retval>;…;else=<fallback>` table. |
| `today` | `date($DateFormat)` |
| `today0` | `date($DateFormat) . ' 00:00:00'` |
| `todayhm` | `date($DateTimeHMFormat)` |
| `now` | `date($DateTimeFormat)` |
| `nowhm` | `date($DateTimeHMFormat)` |
| `lasthour` / `lasthourhm` | One hour ago. |
| `lastweek` / `lastweek0` | Seven days ago (with or without `' 00:00:00'`). |
| `lastyear` | One year ago. |
| `currentmonth` | First day of current month. |
| `0day` | Literal `'1900-01-01'` (sentinel "no date"). |
| `ymd` / `ymdhis` | `date('Ymd')` / `date('YmdHis')` (format-independent). |
| `ishtml` | `preg_match('/<[^>]+>/', $param)` → 'T'/'F'. |
| `userhelp_convert` | Markdown-ish: convert `((ID))` → `<a href="#ID">…</a>`. |
| `stars` | Font Awesome 5-star rating from an integer param. |

## Database Connector Registration

```php
use Eisodos\Connectors\ConnectorPDOPgSQL;       // or ConnectorOCI8 / ConnectorPDOSQLSrv / ConnectorMDB2

Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL(), 0);
Eisodos::$dbConnectors->connector()->connect();       // eager — reads [Database]
```

`connector()` (no args) → index 0. `connector($n)` → index `$n`. Multiple DBs use multiple `[Database*]` sections — pass the section name to `connect('Reporting')`.

**Lazy vs eager.** All four recent migrations skip the `->connect()` call in `__eisodos.php` (lazy). First `query()` opens the connection. Use eager when every request hits DB and you want fail-fast bootstrap, or when you emit `connectSQL` that subsequent code depends on.

**Query helpers** — replace legacy `getSQL*`/`runSQL*` with `query(RT_*, $sql, $resultRef)`:

```php
$db = Eisodos::$dbConnectors->connector();

$row    = []; $db->query(RT_FIRST_ROW,              $sql, $row);     // [col=>val]
$val    =     $db->query(RT_FIRST_ROW_FIRST_COLUMN, $sql);           // scalar
$arr    = []; $db->query(RT_ALL_KEY_VALUE_PAIRS,    $sql, $arr);     // [col0=>col1,...]
$col    = []; $db->query(RT_ALL_FIRST_COLUMN_VALUES,$sql, $col);     // [col0, col0, ...]
$rows   = []; $db->query(RT_ALL_ROWS,               $sql, $rows);    // [[col=>val,...],...]
$by_id  = []; $db->query(RT_ALL_ROWS_ASSOC,         $sql, $by_id, ['indexFieldName' => 'ID']);
              $db->query(RT_NO_ROWS,                $sql);           // SET/DDL — no fetch

$db->executeDML("DELETE FROM t WHERE id < 1000");
$db->executePreparedDML($sql, $types, $data);
$db->executeStoredProcedure('proc_name', $bound, $result);
```

**SQL parameter formatting** (on the connector):

| Helper | Behaviour |
| --- | --- |
| `nullStrParam('NAME', $isStr, $maxLen)` | Empty → `NULL`. Quotes/escapes string values. |
| `defaultStrParam('NAME', ...)` | Empty → `DEFAULT`. |
| `nullStr($value, ...)` / `defaultStr($value, ...)` | Same, for literal values. |
| `toList($csv, $isStr, ...)` | CSV → `IN (...)`-suitable list. |

`$maxLen > 0` throws `RuntimeException` if the value exceeds it — silent truncation can never reach the DB.

## Helper Endpoints (production apps)

Every greengo / ldu / duvenbeck migration ships a recurring set of helper scripts under `src/`. Use them as canonical patterns; full skeletons live in [`documentation/migration_from_udSCGI.md`](documentation/migration_from_udSCGI.md) §14:

- **`ls_client.php`** — LangServer (translation server) JSON client. Dispatches on `LS_ACTION` ∈ `{keys, tags, defaults, status, push, request}`. Auth via `LS_AUTH_KEY` parameter compared to `LangServerAuthKey` config.
- **`bo_fileHandler.php`** — server-side file operations (`SourceType`, `TargetType`, `FileOperation` matrix). Authoritative parameter contract is the docblock at the top of `bo_fileHandler.php` itself.
- **`fe_fileHandler.php`** — frontend bridge. POSTs to BO handler over HTTP via `FileHandler.URL` config. Check `errorcode !== '0'` (string comparison!) before streaming.
- **`bo_mailSender.php`** — accepts `data` (JSON) and calls `Eisodos::$mailer->sendMail(...)`. Disable `CollectLangIDs` and `IncludeStatistic` for these.
- **`bo_pushGateway.php`** — outbound JSON push. Parameterise the auth header via config (`PushGateway.AuthHeader`); don't inline secrets.
- **`bo_printDocument.php`** — renders `print.<document_id>.main` template.

These bootstrap with `require_once __DIR__ . '/__eisodos.php'` (framework only) — they don't need the autoloader or `_init_parameters.php`.

## Composer Setup

PHP `^8.4` is the floor. Required composer skeleton:

```json
{
  "type": "project",
  "minimum-stability": "dev",
  "require": {
    "php": "^8.4",
    "offsite-solutions/eisodos": "@dev",
    "offsite-solutions/eisodos-db-connector-pdo-pgsql": "@dev",
    "ext-curl": "*"
  },
  "require-dev": {
    "roave/security-advisories": "dev-latest"
  },
  "config": { "vendor-dir": "./vendor" }
}
```

For dev with locally symlinked Eisodos sources, ship a parallel `composer.dev.json` with `repositories` pointing at `/opt/local-dev/composer/eisodos/...` (Docker mount target) and use `COMPOSER=composer.dev.json composer update`. Two wrapper shell scripts (`composer_update.sh`, `composer_update_dev.sh`) at the repo root are the standard. Full reference: [`documentation/migration_from_udSCGI.md`](documentation/migration_from_udSCGI.md) §3.1.

## Code Conventions

- Parameter names are always lowercase internally
- Config values support variable substitution: `$_applicationDir`, `$_applicationName`
- Template files use `.template` extension
- Template naming: `header.main.template` → `$templateabs_header_main`
- Language files are key=value pairs with `#` comments
- **Always** use `Eisodos::$utils->safe_array_value()` for array access — never raw `$arr['key']` (PHP 8 warnings on missing keys)
- Use `neq('param', '')` for empty checks instead of direct comparison
- Use `Eisodos::$render->logout()` to drop a session — not `session_destroy()`
- Use `addToResponse()` for normal output; treat `Eisodos::$render->Response = ''` as an exception-handler escape hatch only
- 2-space indentation in PHP files (matches every recent migration)
- PHP `^8.4` floor — code uses readonly properties, `: mixed` returns, `str_starts_with()`
- App namespace is decoupled from directory; `init([$dir, 'myapp'])` only drives config-file prefix and `$_applicationname`

## Further Reading

- [`documentation/migration_from_udSCGI.md`](documentation/migration_from_udSCGI.md) — deep reference manual for porting legacy apps. Covers bootstrap variants, config layout, every helper endpoint, the full sanity-audit grep list, and gotchas. Built from six real completed migrations.
- `Connectors/{Oracle,PDOPgSQL,PDOSQLServer,MDB2}/README.md` — per-connector `[Database]` keys.
- `tests/test_*.php` — runnable single-file PHPUnit-less examples.

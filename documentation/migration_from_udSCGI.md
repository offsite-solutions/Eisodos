# Migration Guide: udSCGI → Eisodos

This is a **reference manual** for porting legacy PHP web applications built
on the `udSCGI` framework to the modern **Eisodos** framework
(`offsite-solutions/eisodos`) plus its plugin packages
(`Connectors/*`, `SQLParser`). It is exhaustive on purpose — every recurring
pattern from past migrations is documented so that a future migration can be
performed mechanically.

The document is based on six real, completed migrations — two pure
udSCGI→Eisodos pairs and four udSCGI+Tholos→Eisodos+Tholos pairs (the
Tholos pieces are out of scope for this guide; ignore them when reading
those projects):

| Legacy site | Migrated site | Notes |
| --- | --- | --- |
| `/Users/baxi/Work/drp/` | `/Users/baxi/Work/drp-v2/sites/dev/drp/` | Pure Eisodos (no Tholos). Reference for **Layout A** (§3.2). |
| `/Users/baxi/Work/greengo/frontend/3Development/portal-v3/` | `/Users/baxi/Work/greengo/frontend/3Development/portal-v5/` | Pure Eisodos. Reference for split bootstrap idea (early form). |
| `/Users/baxi/Work/greengo/backoffice/3Development/portal/` | `/Users/baxi/Work/greengo/backoffice/3Development/v5/` | Tholos-layered. Eisodos parts only — reference for **Layout B** (§3.2) and the four-file bootstrap (§4.3–4.5). |
| `/Users/baxi/Work/greengo/partnerportal/3Development/portal/portal-v4/` | `/Users/baxi/Work/greengo/partnerportal/3Development/portal/portal-v5/` | Tholos-layered. Same bootstrap shape as greengo-bo; companion frontend. |
| `/Users/baxi/Work/ldu/backoffice-svn/portal/` | `/Users/baxi/Work/ldu/backoffice/v2/` | Tholos-layered. App namespace `nop_bo`. |
| `/Users/baxi/Work/duvenbeck_backoffice/portal/` | `/Users/baxi/Work/duvenbeck_v3/backoffice/` | Tholos→Tholos under the hood, but the Eisodos bootstrap was rewritten — useful reference for the four-file split applied to a non-greengo namespace. |

Reference repositories used throughout:

| Purpose | Path |
| --- | --- |
| Legacy framework source | `/Users/baxi/Work/_bone/includes/udSCGI.php` (+ `udSDelphiPHP.php`) |
| Eisodos core | `/Users/baxi/Work/_eisodos/Base` |
| DB connectors | `/Users/baxi/Work/_eisodos/Connectors/{Oracle,PDOPgSQL,PDOSQLServer,MDB2}` |
| SQL parser | `/Users/baxi/Work/_eisodos/SQLParser` |
| Production config skeletons | `/Users/baxi/Work/_docker_images/applications/{greengo,ldu,langserver}` |
| Docker base images | `/Users/baxi/Work/_docker/{BasicApp,TholosApp,BaseImages,...}` |
| Tholos framework (optional add-on) | `/Users/baxi/Work/_tholos`, `/Users/baxi/Work/_tholos_editor/Base`, `/Users/baxi/Work/_tholos_builder/Base` |

> ⚠️ **Tholos out of scope.** Four of the reference projects ship Tholos
> on top of Eisodos. This guide deliberately documents only the Eisodos
> pieces (bootstrap, config, parameter handling, DB, callbacks, helper
> endpoints). When reading those projects, **skip** everything Tholos:
> `tholos/`, `_tholos_*.php`, `tholos.conf` includes, `_run.php`
> (Tholos dispatcher), the `Tholos\Tholos` / `Tholos\TCustomModule` /
> `Tholos\TComponent` use-statements, and any class extending
> `TCustomModule`. The patterns this guide extracts (four-file
> bootstrap, `_autoload.php`, `_init_parameters.php`, `ls_client.php`,
> the file-handler split, `composer.dev.json` workflow) are pure-Eisodos
> patterns that happen to be in use inside those Tholos-layered apps.
> §22 sketches how to add Tholos *afterwards*, once the bare Eisodos
> migration is complete.

---

## Table of contents

1. [Conceptual model](#1-conceptual-model)
2. [Cheat sheet — udSCGI vs Eisodos surface](#2-cheat-sheet--udscgi-vs-eisodos-surface)
3. [Composer setup and filesystem layout](#3-composer-setup-and-filesystem-layout)
4. [Bootstrap conversion](#4-bootstrap-conversion)
5. [Configuration file migration](#5-configuration-file-migration)
6. [Parameter handling](#6-parameter-handling)
7. [Database access](#7-database-access)
8. [Templates, parsers, callbacks](#8-templates-parsers-callbacks)
9. [Logging and error handling](#9-logging-and-error-handling)
10. [Internationalisation (i18n)](#10-internationalisation-i18n)
11. [Mailer](#11-mailer)
12. [Helper-function mapping (`udSDelphiPHP.php`)](#12-helper-function-mapping-udsdelphiphpphp)
13. [Multi-database setups](#13-multi-database-setups)
14. [Raw / AJAX / API / CLI entry points](#14-raw--ajax--api--cli-entry-points)
15. [Wizard parameters](#15-wizard-parameters)
16. [Encoded / signed parameters and CSRF posture](#16-encoded--signed-parameters-and-csrf-posture)
17. [Active versions (`DevVersion`, `ActiveVersions`)](#17-active-versions-devversion-activeversions)
18. [Performance and caching](#18-performance-and-caching)
19. [Step-by-step migration recipe](#19-step-by-step-migration-recipe)
20. [Common gotchas and known pitfalls](#20-common-gotchas-and-known-pitfalls)
21. [Glossary](#21-glossary)
22. [Optional: layering Tholos on top of Eisodos](#22-optional-layering-tholos-on-top-of-eisodos)

---

## 1. Conceptual model

### 1.1 What `udSCGI` is

`udSCGI` is a **single ~3,200-line god-class** that, per request, performs
*all* of the following:

- INI configuration loading from `config/{env}-{appname}.conf` (environment
  selected via `config/environment`, plus optional `configOverwrites_`
  array passed to the constructor).
- Merging HTTP `$_GET` / `$_POST` / `$_COOKIE` / `$_SESSION` into a single
  flat dictionary `$_params[name]['value'|'flag']`.
- Filtering and validating those inputs via a per-app `<appname>.params`
  file.
- Cookie / session management (cookie domain, path, secure, HttpOnly,
  SameSite from config; optional DB-backed session).
- Template loading from disk (`.template` files), with a homegrown
  syntax: `$param`, `$param~='default';`, `[:LANG_ID,default:]`,
  `$templateabs_<id>`, `##` line comments, `[#…#]` short callbacks,
  `[%…%]` modern short callbacks, `<%FUNC%…%FUNC%>` long callbacks,
  `<%SQL%…%SQL%>` inline SQL, `${param}` etc.
- Multi-language translation, language-ID collection / persistence.
- Up to **four** `MDB2_Driver_Common` database connections accessible
  as `$c->db, $c->db2, $c->db3, $c->db4` (and `$c->dba[N]`).
- Error handling and dispatch (Mail / Screen / File / `@callback`).
- PhpConsole-based remote debug.

It is **instantiated once per request**:

```php
$c = new udSCGI($appName, $configPath, $callbackFunctionName,
                $disableHTMLCache, $useDBSession, $disableDatabase,
                $configOverwrites);
```

and finalized with `$c->finish()` (HTML/HTML-cached output) or
`$c->finishRaw()` (JSON / API output).

### 1.2 What `Eisodos` is

Eisodos is the same idea **split into single-responsibility singletons**
behind a single `Eisodos` facade, plus a plugin model for DB connectors,
template parsers and callbacks. Every module is independently testable
and can be swapped or extended.

```
Eisodos::$parameterHandler   ← addParam / getParam / eq / neq / isOn / isOff / ...
Eisodos::$configLoader       ← INI/JSON config files, [PreInclude]/[PostInclude]
Eisodos::$templateEngine     ← getTemplate / parseTemplateText / addToResponse / parsers
Eisodos::$translator         ← getLangText / translateText / getLanguageIDs
Eisodos::$logger             ← trace/debug/info/notice/warning/error/critical/alert/emergency
Eisodos::$mailer             ← PHPMailer-backed sendMail()
Eisodos::$render             ← start() / finish() / finishRaw() / logout() / currentPageURL()
Eisodos::$dbConnectors       ← registerDBConnector / connector(index)
Eisodos::$utils              ← safe_array_value / ODecode / replace_all / isInteger / ...
```

The framework class itself (`Eisodos\Eisodos`) is a thin Singleton:
`Eisodos::getInstance()->init([$applicationDir, $applicationName])` only
records the directory and name. The actual heavy lifting happens in
`Eisodos::$render->start(...)`.

### 1.3 Plugin packages

Eisodos is intentionally **un-opinionated**: parsers, callbacks and DB
connectors are opt-in plug-ins. You register exactly what your app
uses.

| Package | Path | Eisodos class | Provides |
| --- | --- | --- | --- |
| `offsite-solutions/eisodos-db-connector-oci8` | `Connectors/Oracle` | `Eisodos\Connectors\ConnectorOCI8` | Oracle / OCI8 |
| `offsite-solutions/eisodos-db-connector-pdo-pgsql` | `Connectors/PDOPgSQL` | `Eisodos\Connectors\ConnectorPDOPgSQL` | PostgreSQL / PDO |
| `offsite-solutions/eisodos-db-connector-pdo-sqlsrv` | `Connectors/PDOSQLServer` | `Eisodos\Connectors\ConnectorPDOSQLSrv` | MS SQL Server / PDO |
| `offsite-solutions/eisodos-db-connector-mdb2` | `Connectors/MDB2` | `Eisodos\Connectors\ConnectorMDB2` | Legacy MDB2 bridge — useful for **drop-in compatibility** with the old `$c->db->query()` style during migration |
| `offsite-solutions/eisodos-sql-parser` | `SQLParser` | `Eisodos\Parsers\SQLParser` | `<%SQL%…%SQL%>` template parser |

Out-of-the-box, the Eisodos core also ships these parsers:

- `Eisodos\Parsers\CallbackFunctionParser` — `<%FUNC%…%FUNC%>` long form
  (multi-line key=value, parsed `@key`, optional `_include`,
  `_function_name`, `_parameter_prefix`).
- `Eisodos\Parsers\CallbackFunctionShortParser` — `[%…%]` and `[#…#]`
  short forms (semicolon-separated `key=value;...`).

---

## 2. Cheat sheet — udSCGI vs Eisodos surface

This is the **complete** replacement table; if your migration needs a
symbol that is not listed here, it is either part of `udSDelphiPHP.php`
(see §12) or completely deprecated.

### 2.1 Bootstrap / lifecycle

| udSCGI | Eisodos | Notes |
| --- | --- | --- |
| `new udSCGI($appName, $configPath, $cb, $disableHTMLCache, $useDBSession, $disableDatabase, $configOverwrites)` | `Eisodos::getInstance()->init([$appDir, $appName])` ⊕ `Eisodos::$render->start([opts], [cache], [tpl], $logLevel)` | Two phases. `start()` does config load, parameter merge, session, error handler, response buffer. |
| `udSCGI::getInstance(...)` | `Eisodos::getInstance()` | The static singleton accessor. |
| `$c->finish()` | `Eisodos::$render->finish()` | Closes session, flushes response, disconnects DBs, persists language IDs. |
| `$c->finish(true)` (don't close DBs) | `Eisodos::$render->finish()` — DBs are closed regardless; if you need to keep them open use a custom Render subclass. | Rarely needed; just open them again in the next request. |
| `$c->finishRaw($saveSession, $handleLangs)` | `Eisodos::$render->finishRaw($saveSession, $handleLangs)` | Same. Use for JSON / AJAX endpoints. |

### 2.2 Parameter handling

| udSCGI | Eisodos | Notes |
| --- | --- | --- |
| `$c->getParam('NAME', 'default')` | `Eisodos::$parameterHandler->getParam('NAME', 'default')` | Case-insensitive, identical dynamic variables (`$seq*`, `$random`, `$currdate`, `$_sessionid`, `$_applicationname`, `$_environment`, `$https`, `$lnbr`, `$_`). |
| `$c->addParam('NAME', $val, $session, $cookie)` | `Eisodos::$parameterHandler->setParam('NAME', $val, $session, $cookie)` | **Rename `addParam` → `setParam`**. Same flag semantics (`session=true` → stored in session; `cookie=true` → stored as cookie). |
| `$c->eq('NAME', 'V', $def, $ci=true, $trim=true)` | `Eisodos::$parameterHandler->eq('NAME', 'V', $def, $ci, $trim)` | Identical. Value starting with `^` is dereferenced as another parameter name. |
| `$c->neq(...)` | `Eisodos::$parameterHandler->neq(...)` | Identical. |
| _n/a_ | `Eisodos::$parameterHandler->isOn('FLAG')` / `isOff('FLAG')` | New helpers: `T,ON,1,TRUE,YES,Y` (and inverse). Use them; cleaner than `eq('FLAG','T')`. |
| `$c->getParamNames($regex)` | `Eisodos::$parameterHandler->getParamNames($regex)` | Identical. |
| `$c->clearResult()` | `Eisodos::$parameterHandler->clean()` clears parameters; for the response use the template engine. | Rarely needed in practice. |
| `$c->storeCurrentURL('p')` | `Eisodos::$render->storeCurrentURL('p')` | Same. Stores `currentPageURL()` into a session parameter. |
| `$c->currentPageURL()` | `Eisodos::$render->currentPageURL()` | Same. |
| `$c->wizClear('w')` / `getWizardParams('w')` | No first-class wizard API; emulate with `getParamNames('/^wiz\.w\./')` and `setParam(... '')`. See §15. |  |
| `$c->udSCode($s) / udSDecode($s)` | `Eisodos::$parameterHandler->udSCode($s)` / `udSDecode($s)` | Same algorithm (hex+swap), bit-compatible with legacy data. |
| `$c->codeToHexa($s)` / `decodeFromHexa($s)` | Not present on the facade; use `bin2hex()` / `hex2bin()` (PHP native). | The udSCGI implementation is just hex encoding. |
| `$c->_params` (private) | `Eisodos::$parameterHandler->getParameterArray()` | Read-only snapshot. |
| n/a | `Eisodos::$parameterHandler->mergeParameterArray($arr)` | Bulk merge. |

### 2.3 Template engine

| udSCGI | Eisodos | Notes |
| --- | --- | --- |
| `$c->getTemplate($id, $vals, $add, $disableParsing, $disableLangParsing, $row, $raiseOnMissing)` | `Eisodos::$templateEngine->getTemplate($id, $vals, $add, $disableParsing, $disableLangParsing, $row, $raiseOnMissing)` | Same signature. Returns parsed string. |
| `$c->getMultiTemplate([ids], ...)` | `Eisodos::$templateEngine->getMultiTemplate([ids], ...)` | Same. |
| `$c->parseTemplateText($text, $vals, $add, $varPrefix)` | `Eisodos::$templateEngine->parseTemplateText($text, $vals, $add, $varPrefix)` | Same. |
| `$c->replaceParamInString($text)` | `Eisodos::$templateEngine->replaceParamInString($text)` | Same. |
| `$c->Response .= 'x'` | `Eisodos::$templateEngine->addToResponse('x')` | The public `Response` field is gone — always use `addToResponse`. |
| `$c->addToResponse(...)` (if present in the app) | `Eisodos::$templateEngine->addToResponse(...)` | Same. |
| `$c->templateFixer` | Template-engine option `templateFixer` (in `templateEngineOptions_`). | Defaults to a single space. |
| `$c->ParseUserFunc = false` | Don't register `CallbackFunctionParser` / `CallbackFunctionShortParser`. | The legacy switch is replaced by parser opt-in. |
| Default callback name | `Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default')` | Free-function name (string). |
| `_doCallback / _doUserCallback / _replaceSQL` (private) | Built into `CallbackFunctionParser`, `CallbackFunctionShortParser`, `SQLParser` plugins. | See §8. |

### 2.4 Translator

| udSCGI | Eisodos |
| --- | --- |
| `$c->getLangText('ID', $textParams, $findHashmarked)` | `Eisodos::$translator->getLangText('ID', $textParams, $findHashmarked)` |
| `$c->translateText($page, $findHashmarked)` | `Eisodos::$translator->translateText($page, $findHashmarked)` |
| `$c->getLangTextForTranslate($id, $lang, $userEdited)` | `Eisodos::$translator->getLangTextForTranslate($id, $lang, $userEdited)` |
| `$c->getLanguageIDs()` | `Eisodos::$translator->getLanguageIDs()` |
| `$c->loadMasterLanguageFile($force)` | `Eisodos::$translator->loadMasterLanguageFile($force)` (called by `Render::start()`) |
| `$c->userLanguageIDs` (public field) | `Eisodos::$translator->getLanguageIDs()` |
| `[:LANG_ID,default:]` template syntax | unchanged; auto-parsed by `Translator::parse()` when `TranslateLanguageTags=T` |

### 2.5 Database

| udSCGI | Eisodos |
| --- | --- |
| `$c->db` | `Eisodos::$dbConnectors->connector()` (index 0) |
| `$c->db2 / db3 / db4` | `Eisodos::$dbConnectors->connector(1) / connector(2) / connector(3)` |
| `$c->dba[N]` | `Eisodos::$dbConnectors->connector(N-1)` |
| `$c->openDB() / openDB2() / openDB3() / openDB4()` | `Eisodos::$dbConnectors->connector(N)->connect()` |
| `$c->openDBA(N) / getDBByIndex(N)` | `Eisodos::$dbConnectors->connector(N)` (auto-connects on demand if registered) |
| `$c->db->query($sql)` (raw MDB2) | `Eisodos::$dbConnectors->connector()->query(RT_*, $sql, $arr)` (see §7) |

### 2.6 Logging / errors

| udSCGI | Eisodos |
| --- | --- |
| `$c->writedebug($s)` | `Eisodos::$logger->debug($s)` (or `trace/info/...`) |
| `PC::debug($s)` (PhpConsole) | `Eisodos::$logger->debug($s)` |
| `$c->writeErrorLog($throwable, $info, $extraMails)` | `Eisodos::$logger->writeErrorLog($throwable, $info, $extraMails)` |
| `ErrorOutput=Mail,Screen,File` | unchanged |
| `ErrorMailTo, ErrorMailFrom, ErrorMailSubject` | unchanged (subject is a recent addition; see commit `22b5ffd`) |
| `@callback_name` in `ErrorOutput` | unchanged |
| _no levels_ | PSR-3 levels: `trace, debug, info, notice, warning, error, critical, alert, emergency` |
| `DEBUGGERSTORAGE`, `setPostponeStorage`, `DebugPassword` (PhpConsole config) | Removed. Use `DEBUGLEVELS`, `DEBUGOUTPUTS`, `DEBUGTOURL`, `DEBUGURLPREFIX`. |

### 2.7 Misc / utilities

| udSCGI / `udSDelphiPHP.php` | Eisodos | Notes |
| --- | --- | --- |
| `sa($arr, 'k', 'd', $ci)` | `Eisodos::$utils->safe_array_value($arr, 'k', 'd', $ci)` | Same semantics. |
| `D_pos($needle, $haystack)` | `strpos($haystack, $needle) + 1` (D_pos is 1-indexed, 0 = not found) | Common pattern: `D_pos($x, $s) == 1` → `strpos($s, $x) === 0` ("starts with"). |
| `D_copy($s, $from, $count)` | `substr($s, $from - 1, $count)` | 1-indexed source, beware! |
| `D_replace($s, $search, $rep, $all=true, $noCase=true)` | `Eisodos::$utils->replace_all($s, $search, $rep, $all, $noCase)` | Same semantics. |
| `D_isint($x, $allowNeg)` / `D_isfloat($x, $allowNeg)` | `Eisodos::$utils->isInteger($x, $allowNeg)` / `isFloat($x, $allowNeg)` | |
| `D_gen_uuid()` | `Eisodos::$utils->generateUUID()` | RFC4122 v4. |
| `D_in($char, $chars)` | `in_array($char, $chars, true)` | Native. |
| `D_array2text($a)` | `print_r($a, true)` | Native. |
| `str_replace_count / str_ireplace_count` | `Eisodos::$utils->str_replace_count / str_ireplace_count` | Same. |
| `D_healApostrof($c, $s)` | None — use `Eisodos::$dbConnectors->connector()->nullStr($s, true)` for SQL escaping. | The legacy `D_healApostrof` predates parameter binding. |
| `validEmail($s)` | `filter_var($s, FILTER_VALIDATE_EMAIL)` | Native. |
| `compare_dates / compare_dates_hu` | Native `DateTime` or app-local helper. | Not framework-provided. |
| `modulus($x, $y)` | `$x % $y` | Native. |
| `udS_resizeImage / udS_deleteImage / udS_renamePic / udS_numPics` | None — keep your own helpers or factor them into an app-local class. | Image handling is **not** an Eisodos responsibility. |

For SQL formatting helpers (`np`, `nnp`, `n`, `nn`, `nlist`) see §7.3.
For DB row helpers (`getSQL`, `getSQLback`, `getSQLtoArray*`,
`runSQL`, `runSQLprep`) see §7.2.

---

## 3. Composer setup and filesystem layout

### 3.1 `composer.json`

Bare Eisodos (the greengo-v5 minimal example,
`/Users/baxi/Work/greengo/frontend/3Development/portal-v5/composer.json`):

```json
{
  "description": "<Your application>",
  "type": "project",
  "authors": [
    { "name": "Offsite Solutions Ltd.", "email": "info@offsite-solutions.com" }
  ],
  "require": {
    "php": "^8.4",
    "offsite-solutions/eisodos": "@dev",
    "ext-gd": "*",
    "ext-json": "*",
    "ext-zlib": "*"
  },
  "require-dev": {
    "roave/security-advisories": "dev-latest"
  },
  "config": { "vendor-dir": "./vendor" }
}
```

Add the relevant DB connector(s) and the SQL parser when you need them.
The drp-v2 SQL-Server case (`/Users/baxi/Work/drp-v2/sites/dev/drp/composer.json`):

```json
{
  "require": {
    "offsite-solutions/eisodos": "1.0.0",
    "offsite-solutions/eisodos-db-connector-mdb2": "1.0.0",
    "offsite-solutions/eisodos-sql-parser": "1.0.0",
    "phpoffice/phpspreadsheet": "1.14.1"
  },
  "require-dev": {
    "roave/security-advisories": "dev-master"
  }
}
```

#### When to use which connector

| Legacy DB driver | Recommended new connector | Composer dep |
| --- | --- | --- |
| MS SQL Server (MDB2 `sqlsrv://...`) — **drop-in keep** | `ConnectorMDB2` | `eisodos-db-connector-mdb2` |
| MS SQL Server — **rewrite** | `ConnectorPDOSQLSrv` | `eisodos-db-connector-pdo-sqlsrv` |
| Oracle (MDB2 `oci8://...`) | `ConnectorOCI8` | `eisodos-db-connector-oci8` |
| PostgreSQL (MDB2 `pgsql://...`) | `ConnectorPDOPgSQL` | `eisodos-db-connector-pdo-pgsql` |

The MDB2 connector exists specifically to let you migrate the framework
**without rewriting every SQL helper** at the same time. drp-v2 used it
this way; downstream rewriting to PDO can happen as a separate
follow-up.

#### Development with linked sources

Two patterns are in use. Pick one and stay consistent across the app:

**Pattern A — single `composer.json` with `x-repositories` (drp-v2 style).**
Keeps everything in one file; rename `x-repositories` → `repositories`
locally when you need the path repos. Composer ignores `x-`-prefixed keys.

```json
"x-repositories": [
  { "type": "path", "url": "/Users/baxi/Work/_eisodos/Base",            "options": { "symlink": true } },
  { "type": "path", "url": "/Users/baxi/Work/_eisodos/Connectors/MDB2", "options": { "symlink": true } },
  { "type": "path", "url": "/Users/baxi/Work/_eisodos/SQLParser",       "options": { "symlink": true } }
]
```

**Pattern B — parallel `composer.json` + `composer.dev.json` (greengo / ldu /
duvenbeck style).** Ship two manifests side by side. Prod uses
`composer.json` (no path repos, version pins, `--no-dev`). Dev uses
`composer.dev.json` with real `repositories` pointing at a Docker-mounted
symlink target like `/opt/local-dev/composer/...`. Switch by setting the
`COMPOSER` env var.

`composer.dev.json` (greengo backoffice — Docker-mounted symlinks):

```json
{
  "repositories": [
    { "type": "path", "url": "/opt/local-dev/composer/eisodos/Base",              "options": { "symlink": true } },
    { "type": "path", "url": "/opt/local-dev/composer/eisodos/SQLParser",         "options": { "symlink": true } },
    { "type": "path", "url": "/opt/local-dev/composer/eisodos/Connectors/Oracle", "options": { "symlink": true } }
  ],
  "require": {
    "php": "^8.4",
    "offsite-solutions/eisodos": "@dev",
    "offsite-solutions/eisodos-db-connector-oci8": "@dev",
    "ext-simplexml": "*", "ext-curl": "*", "ext-zip": "*"
  },
  "config": { "vendor-dir": "./vendor" }
}
```

Ship two thin wrapper scripts at the repo root so devs don't have to
remember the flags:

`composer_update.sh` (prod):

```bash
if [ ! -d "./vendor/composer" ]; then
  /usr/bin/composer install --no-interaction --prefer-dist --no-dev --no-ansi --optimize-autoloader
else
  /usr/bin/composer update  --no-interaction --prefer-dist --no-dev --no-ansi --no-cache --optimize-autoloader
fi
```

`composer_update_dev.sh` (dev — `COMPOSER=` env var tells Composer which
manifest to read):

```bash
COMPOSER=composer.dev.json composer update --no-interaction --prefer-dist --no-dev --no-ansi --no-cache --optimize-autoloader
```

The Docker runtime mounts the host's `/Users/.../_eisodos` into
`/opt/local-dev/composer/eisodos` inside the container (see
`/Users/baxi/Work/_docker_images/applications/<app>/run.sh`), so framework
edits on the host are picked up live by the dev composer flow. Pattern B
is the default for every greengo / ldu / duvenbeck migration; Pattern A is
appropriate for smaller, single-developer apps like drp-v2.

> **PHP version floor.** Every recent migration pins `"php": "^8.4"`. The
> framework itself uses readonly properties, typed `: mixed` returns, and
> `str_starts_with()` — do **not** target an older PHP minor.

### 3.2 Recommended filesystem layout

Two layouts are in active use. Use **Layout A** for new pure-Eisodos apps
and small projects; use **Layout B** for anything that ships a Docker
image or has multiple entry-point scripts (greengo, ldu, duvenbeck).

#### Layout A — flat (drp-v2 style)

```
<appname>/
├── _init.php           # framework bootstrap (was: udSCGI's "new udSCGI(...)")
├── _callbacks.php      # callback_default() definition
├── index.php           # page entry points (one per .php)
├── ...                 # cms.php, cms_reg.php, getAPIResult.php, ajax.php, ...
├── composer.json
├── vendor/
├── config/
│   ├── environment                  # one line: dev | local | test | live
│   ├── <env>-<appname>.conf         # e.g. dev-drp.conf  (entry config)
│   ├── common_pre_<env>.conf        # OPTIONAL local copy; Eisodos ships one (see §5.1)
│   ├── global.conf                  # OPTIONAL shared org-wide defaults
│   ├── version.conf                 # PostInclude'd, generated at deploy
│   ├── <appname>.params             # parameter filter (same syntax as udSCGI)
├── templates/          # *.template — syntax unchanged
├── languages/          # *.lang files — syntax unchanged
└── logs/               # generated at runtime
```

#### Layout B — `src/`-based (greengo / ldu / duvenbeck style)

```
<appname>/
├── composer.json              # production manifest (pinned, no dev deps)
├── composer.dev.json          # dev manifest with path repos (see §3.1 Pattern B)
├── composer.lock
├── composer_update.sh         # prod composer wrapper
├── composer_update_dev.sh     # dev composer wrapper
├── vendor/                    # populated by composer
├── src/                       # all PHP entry points + bootstrap
│   ├── __eisodos.php          # framework-only bootstrap (no app logic)
│   ├── _init.php              # app bootstrap: requires __eisodos.php, _autoload.php, _init_parameters.php
│   ├── _autoload.php          # SPL autoloader for the app namespace (e.g. `greengo\`)
│   ├── _init_parameters.php   # app-specific parameter derivation (LANG → DateFormat / JSDateFormat / ...)
│   ├── _callbacks.php         # callback_default() — uses Eisodos::$utils->safe_array_value()
│   ├── index.php              # typically a one-line redirect to /DASHBOARD/index/ etc.
│   ├── ls_client.php          # OPTIONAL: LangServer (translation server) client (§14.4)
│   ├── bo_fileHandler.php     # OPTIONAL: file ingestion / serving endpoint (§14.5)
│   ├── fe_fileHandler.php     # OPTIONAL: frontend → backoffice file proxy (§14.5)
│   ├── bo_mailSender.php      # OPTIONAL: mail dispatcher (§14.6)
│   ├── bo_printDocument.php   # OPTIONAL: print template renderer
│   ├── bo_pushGateway.php     # OPTIONAL: outbound JSON push
│   ├── <DomainModule>.php     # one file per business module (namespace `<appname>`)
│   └── assets/
│       ├── templates/         # *.template — Eisodos template syntax
│       ├── css/  js/  img/  sound/  fonts/  config/
├── i18n/                      # generated.txt / translated.txt / langids.txt — managed by ls_client
├── legacy/                    # OPTIONAL placeholder for not-yet-converted legacy code
├── CLAUDE.md                  # OPTIONAL team/agent guidance (greengo-bo ships one)
└── config/                    # see "Config externalization" below
```

**The `config/` directory may or may not live inside the repo.** Every
greengo / ldu / duvenbeck migration **externalizes config**: it is not
checked in here, but mounted at runtime from
`/Users/baxi/Work/_docker_images/applications/<app>/config/config/` into
the container at `/etc/app/config` (with `EISODOS_CONFIG_PATH=/etc/app/config`).
drp-v2 checks it in. Both are valid; decide based on whether the app
ships as a Docker image with environment-specific overrides.

The environment switch (`config/environment`) and `.params` file format
are **unchanged** from udSCGI — only the framework that reads them has
been replaced. Likewise `.template` and `.lang` file syntax.

**Naming and conventions observed across all four recent migrations:**

| File / dir | Convention |
| --- | --- |
| `__eisodos.php` | Two leading underscores. Framework wiring only — never app logic. |
| `_init.php` | One underscore. Includes `__eisodos.php`, `_autoload.php`, `_init_parameters.php` in that order. |
| `_autoload.php` | Declares `namespace <appname>` and registers an SPL loader (see §4.6). |
| `_init_parameters.php` | Pure `Eisodos::$parameterHandler->setParam(...)` — no logic beyond derivations (see §4.7). |
| `_callbacks.php` | Body uses `Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob') === '...'` for every branch. |
| Domain module class | One file per class, `namespace <appname>;`, classname = filename without `.php`. |
| Indentation | 2 spaces in PHP files. Don't reformat to 4. |

---

## 4. Bootstrap conversion

### 4.1 Legacy udSCGI bootstrap

`/Users/baxi/Work/drp/_init.php` (almost the minimum udSCGI bootstrap):

```php
<?php
  include("_callbacks.php");
  require_once("includes/udSDelphiPHP.php");
  $c = new udSCGI("drp", "./config", 'callback_default');
?>
```

A larger one — `/Users/baxi/Work/greengo/.../portal-v3/hu/_init.php`
(abbreviated):

```php
<?php
  include("_callbacks.php");
  require_once("includes/udSDelphiPHP.php");

  function declParams($c, $names) { /* legacy global-promotion helper */ }
  function setSessionStarted($c)  { /* sets SESSIONSTARTED=T */ }
  function doSessionStart($c)     { /* lang detection + API config bootstrap */ }
  function doCheckParticipantID($c) { /* checksum check */ }

  $c = new udSCGI("greengo_fe", __DIR__ . "/config", 'callback_default');

  if ($c->eq("last_release", "")) $c->addParam("last_release", date("YmdHis"));
  if ($c->neq("LANG", "HU") and $c->neq("LANG", "EN") and $c->neq("LANG", "")) $c->addParam("LANG", "EN", true);
  if ($c->eq("LANG", "")) $c->addParam("LANG", "HU", true);
  if ($c->neq("LANG", "")) $c->addParam("LANG", strtoupper($c->getParam('LANG')), true);

  if ($c->neq('SETLANG', '')) {
    setSessionStarted($c);
    $c->addParam('LANG', strtoupper($c->getParam('SETLANG')), true);
  } else if ($c->eq('SESSIONSTARTED', '')) {
    doSessionStart($c);
  }
  doCheckParticipantID($c);
  /* ... */
```

What the constructor does on its own (you do not call any of these
explicitly):

1. Reads `config/environment` → `_environment` prefix.
2. Loads `config/{env}-drp.conf` and processes `[PreInclude]/[PostInclude]`.
3. Boots PhpConsole.
4. Opens primary DB (`MDB2::connect` via `openDB(true)`).
5. Starts session (custom cookie params from config; optional DB-backed).
6. Loads `$_COOKIE`, `$_GET`+`$_POST` into `_params`, filtered by
   `<appname>.params`.
7. Handles `Redirect=...`, `Wizard=...`, `LastPostID/PostID` (replay
   detection via `RePost=T`), `CRC` (page-reload detection via
   `Reload=T`).

### 4.2 Eisodos bootstrap — single-file variant (drp-v2)

`/Users/baxi/Work/drp-v2/sites/dev/drp/_init.php`:

```php
<?php
  /** NISZ - Dohányregisztrátori Portál */
  session_start();   // see §4.4

  use Eisodos\Connectors\ConnectorMDB2;
  use Eisodos\Eisodos;
  use Eisodos\Parsers\CallbackFunctionParser;
  use Eisodos\Parsers\CallbackFunctionShortParser;
  use Eisodos\Parsers\SQLParser;

  require_once __DIR__ . '/vendor/autoload.php';

  try {
    Eisodos::getInstance()->init([__DIR__, 'drp']);

    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],          // cache options: ['disableHTMLCache' => true|false]
      [],          // template engine options
      'trace'      // log level: '' | critical | error | warning | info | debug | trace
    );

    Eisodos::$templateEngine->registerParser(new CallbackFunctionParser());
    Eisodos::$templateEngine->registerParser(new CallbackFunctionShortParser());
    require_once('_callbacks.php');
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

    Eisodos::$templateEngine->registerParser(new SQLParser());
    Eisodos::$dbConnectors->registerDBConnector(new ConnectorMDB2());
    Eisodos::$dbConnectors->connector()->connect();
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) { die($e->getMessage()); }
    Eisodos::$logger->writeErrorLog($e);
    exit(1);
  }
```

### 4.3 Eisodos bootstrap — split variant (greengo-v5 / ldu / duvenbeck)

For any app with more than one entry-point file, split the bootstrap.
**Every recent migration uses the same four-file shape** under `src/`:

```
src/__eisodos.php        ← framework wiring only (Eisodos init + connector register)
src/_init.php            ← top-level glue: requires the three files below in order
src/_autoload.php        ← SPL autoloader for the app namespace
src/_init_parameters.php ← LANG → derived parameters (DateFormat, JSDateFormat, …)
```

Page / API / CLI entry-points then start with one line:

```php
require_once(__DIR__ . '/__eisodos.php');   // framework only — minimal bootstrap (CLI helpers, ls_client)
// — OR —
include __DIR__ . '/_init.php';             // full bootstrap (HTML pages, business endpoints)
```

#### `__eisodos.php` (framework wiring — never app logic)

Canonical body, copy verbatim and change only the application name and
the connector class:

```php
<?php
  use Eisodos\Eisodos;
  use Eisodos\Connectors\ConnectorOCI8;            // or ConnectorPDOPgSQL / ConnectorPDOSQLSrv / ConnectorMDB2
  use Eisodos\Parsers\CallbackFunctionParser;
  use Eisodos\Parsers\CallbackFunctionShortParser;

  require_once '../vendor/autoload.php';           // src/ → ../vendor — keep relative!

  try {
    Eisodos::getInstance()->init([__DIR__, 'greengo_bo']);   // 2nd arg = config-file prefix, not the directory

    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [], [],
      ''                                          // '' = use DEBUGLEVELS from config
    );

    require_once(__DIR__ . '/_callbacks.php');
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

    Eisodos::$dbConnectors->registerDBConnector(new ConnectorOCI8());
    // NOTE: do NOT call ->connect() here — see §7.1 "Lazy connection"
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) { die($e->getMessage()); }
    Eisodos::$logger->writeErrorLog($e);
    exit(1);
  }
```

> **Parser registration.** Whether you call
> `registerParser(new CallbackFunctionParser())` /
> `CallbackFunctionShortParser()` in `__eisodos.php` depends on which
> template tags you use. **Register them unless something downstream
> (e.g. a layered framework) registers them for you.** drp-v2 / greengo
> frontend register them explicitly; greengo-backoffice / ldu omit them
> because their layered runtime registers them later. If templates render
> with `<%FUNC%...%FUNC%>` or `[%...%]` showing through verbatim, you
> forgot a parser.

> **`init([$dir, $name])` — name is decoupled from the directory.** Both
> partnerportal (`portal-v5/src/`) and the duvenbeck v3 backoffice use
> the application name `'greengo_pp'` despite living in different
> top-level directories. The name only drives config file prefixes
> (`{env}-greengo_pp.conf`, log paths, `$_applicationname`). It can be
> arbitrary; pick it to match the deployed config-file naming, not the
> repo path.

#### `_init.php` (chain the three pieces — keep tiny)

```php
<?php
  require_once __DIR__ . '/__eisodos.php';
  require_once('_autoload.php');
  require_once('_init_parameters.php');
```

That is the *entire* file in greengo-bo, ldu-bo, partnerportal-v5 and
duvenbeck-v3. Any per-app glue (extra `require_once`, conditional
language fixups, custom session handlers) goes **here**, never in
`__eisodos.php`.

#### When to skip `_init.php` and require `__eisodos.php` directly

Helper endpoints that don't need the autoloader or derived parameters —
for example `ls_client.php`, `bo_mailSender.php`, `bo_pushGateway.php` —
include only `__eisodos.php`. This avoids paying for namespace class
loading and language-format computation on requests that never touch
either. Use the full `_init.php` for HTML pages, the `_run.php`-style
dispatchers, and any request that loads app-namespaced classes.

### 4.4 The `_autoload.php` SPL autoloader

Every `src/`-layout migration drops Composer PSR-4 in favor of a tiny
hand-rolled SPL loader. The shape is identical across all four
migrations — only the `namespace` line changes:

```php
<?php
  namespace greengo;     // duvenbeck_bo, nop_bo, partnerportal — pick your app namespace

  spl_autoload_register(function ($class) {
    if (str_starts_with($class, __NAMESPACE__)) {
      require_once(__DIR__ . DIRECTORY_SEPARATOR . explode('\\', $class)[1] . '.php');
    }
  });
```

Resolution: `greengo\COR_LOGIN` → `src/COR_LOGIN.php`. The loader is
**flat by design** — it only looks at the second namespace segment, so
all classes live directly in `src/`, one file per class. Subnamespaces
(`greengo\foo\Bar`) are silently ignored; if you need them, switch to
Composer PSR-4 in `composer.json` instead of extending the loader.

Top-level entry-point scripts (`index.php`, `bo_fileHandler.php`,
`bo_mailSender.php`, `ls_client.php`, `_run.php`) are **not**
namespaced — they are URL targets, not autoloaded classes.

### 4.5 The `_init_parameters.php` LANG-derived parameters

Every Hungarian/multi-lingual migration uses `LANG` to select per-locale
date/time format strings. Each format family is defined in config as
`HU.DATEFORMAT=Y-m-d`, `EN.DATEFORMAT=Y-m-d`, etc., and the bootstrap
copies the locale-specific value into the unsuffixed parameter the
templates and PHP code actually read (`DateFormat`,
`PHPDateTimeFormat`, …). The canonical file body:

```php
<?php
  use Eisodos\Eisodos;

  # set LANG to uppercase — config keys are uppercase
  if (Eisodos::$parameterHandler->neq("LANG", "")) {
    Eisodos::$parameterHandler->setParam(
      "LANG",
      strtoupper(Eisodos::$parameterHandler->getParam('LANG')),
      true                                  // session-persistent
    );
  }

  # three families × four widths = 12 derived params
  foreach (['', 'JS', 'PHP'] as $family) {
    foreach (['DateFormat', 'DateTimeFormat', 'DateTimeHMFormat', 'TimeFormat'] as $kind) {
      $configKey = Eisodos::$parameterHandler->getParam("LANG") . '.' . strtoupper($family . $kind);
      Eisodos::$parameterHandler->setParam(
        $family . $kind,
        Eisodos::$parameterHandler->getParam($configKey)
      );
    }
  }

  Eisodos::$parameterHandler->setParam("LANG_SMALL", strtolower(Eisodos::$parameterHandler->getParam("LANG")));
```

In the actual migrated files the loop is expanded out to twelve explicit
`setParam` calls (see
`/Users/baxi/Work/greengo/backoffice/3Development/v5/src/_init_parameters.php`
for the verbatim form). Either form is fine; pick what matches the rest
of the codebase.

The required config entries — put them in `global.conf` or a dedicated
`i18n.conf` PreInclude:

```ini
HU.DATEFORMAT=Y-m-d
HU.DATETIMEFORMAT=Y-m-d H:i:s
HU.DATETIMEHMFORMAT=Y-m-d H:i
HU.TIMEFORMAT=H:i:s
HU.JSDATEFORMAT=YYYY-MM-DD
HU.JSDATETIMEFORMAT=YYYY-MM-DD HH:mm:ss
HU.JSDATETIMEHMFORMAT=YYYY-MM-DD HH:mm
HU.JSTIMEFORMAT=HH:mm:ss
HU.PHPDATEFORMAT=Y-m-d
HU.PHPDATETIMEFORMAT=Y-m-d H:i:s
HU.PHPDATETIMEHMFORMAT=Y-m-d H:i
HU.PHPTIMEFORMAT=H:i:s
# repeat for each language in `Langs=`
```

**Skip this file** only if the app is single-language and never uses
`$DateFormat` / `$JSDateFormat` / `$PHPDateFormat` in templates — verify
with `grep -RIn '\$\(JS\|PHP\)\?Date' src/assets/templates/`.

Use the split pattern when:

- More than one entry-point file shares the same bootstrap.
- You want to write framework-level tests (`__eisodos.php` is then
  reusable).
- You want to keep each piece small enough to read.

### 4.6 Sessions

`udSCGI` always called `session_start()` from its constructor's
`_loadSessionVariables()` after applying `COOKIE_DOMAIN`,
`COOKIE_PATH`, `COOKIE_SECURE`, `COOKIE_HTTPONLY`, `COOKIE_SAMESITE`
from `[Config]`. Eisodos still respects all those keys and computes
`getCookieParams()` the same way.

**Three concrete migration strategies, all valid:**

1. **Don't touch it** — let `Render::start()` start the session itself.
   `__eisodos.php` for greengo-backoffice, greengo-partnerportal,
   ldu-backoffice, and duvenbeck-v3-backoffice all do this. Their
   `.params` files contain `session;LANG` and other `session;*` rules
   and still work, so `Render::start()` is starting the session in time
   for the parameter merge. **This is the recommended default.**

2. **Call `session_start()` at the very top of `_init.php`** —
   drp-v2 does this. Use only if you have application code that
   already needs `$_SESSION` populated *before* `Render::start()`
   completes (rare; usually only when you've pre-wired a non-standard
   session save handler).

3. **Register a custom session save handler before `Render::start()`** —
   for Redis-backed sessions or DB-backed sessions. Replaces the
   `$useDBSession_` constructor flag from udSCGI. Do
   `session_set_save_handler(...)` in `__eisodos.php` *before* the
   `Render::start()` call.

Earlier guidance ("call `session_start()` yourself when `.params`
declares `session;NAME` rules") turned out to be overcautious — the
four recent migrations show it isn't required for normal cookie-backed
sessions.

### 4.7 `__construct` parameters → equivalent Eisodos config / options

| udSCGI constructor argument | Eisodos equivalent |
| --- | --- |
| `$applicationName_` | Second element of `Eisodos::getInstance()->init([$dir, $name])`. |
| `$configPath_` | First element (`__DIR__`) becomes `_applicationDir`; configs are read from `$configPath/config/`. |
| `$callbackFunctionName_` | `Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default')`. |
| `$disableHTMLCache_` | `['disableHTMLCache' => true]` in the `$cacheOptions_` argument of `Render::start()`. |
| `$useDBSession_` | No first-class equivalent. Register a custom PHP session save handler (Redis, DB, file) before `Render::start()`. |
| `$disableDatabase_` | Don't call `Eisodos::$dbConnectors->registerDBConnector(...)`. |
| `$configOverwrites_` | Merge into `[Config]` programmatically via `Eisodos::$parameterHandler->setParam(...)` immediately after `Render::start()`. |

---

## 5. Configuration file migration

### 5.1 Filename, environment, includes

| udSCGI | Eisodos |
| --- | --- |
| `config/drp.conf` (single file) or `config/{env}-drp.conf` | `config/{env}-drp.conf` (entry) + `config/common_pre_{env}.conf` + optional `global.conf`/`i18n.conf`/... |
| `config/environment` (single-line file: `dev`, `live`, `local`, `test`) | unchanged |
| Inside `.conf`: `[PreInclude]` / `[PostInclude]` with lines `1=file.conf:Section` | unchanged |

Eisodos ships per-env defaults in
`/Users/baxi/Work/_eisodos/Base/src/Eisodos/config/common_pre_{dev,test,live}.conf`.
Reference them via `[PreInclude]` so your per-app config stays minimal:

```ini
[PreInclude]
1=/app/dist/vendor/offsite-solutions/eisodos/src/Eisodos/config/common_pre_dev.conf:Config
2=global.conf:Config
```

### 5.2 Sections

```ini
[Env]                ; environment variables (putenv'd)
PATH=/usr/local/bin:/usr/bin

[PreInclude]         ; loaded BEFORE this file's [Config]
1=common_pre_dev.conf:Config
2=global.conf:Config

[PostInclude]        ; loaded AFTER this file
1=version.conf:Version

[Database]           ; DB connector options (see §7)
driver=pgsql
user=eisodos
host=...
...

[Config]             ; merged into the parameter handler at startup
TemplateDir=$_applicationDir/templates/
.ErrorLog=$_applicationDir/logs/$_applicationName-error.log
ErrorOutput=File,Mail
```

Variable substitution available in values:

- `$_applicationDir` → first arg to `init([...])`.
- `$_applicationName` → second arg to `init([...])`.
- `$_environment` → content of `config/environment` (without trailing
  dash).

`.ErrorLog=...` — the leading dot marks the parameter **readonly** so
URL parameters cannot override it. Use this for every security-sensitive
or hard-to-rotate value (log paths, template dir, app URLs).

### 5.3 Real-world examples

**drp-v2 dev** (`/Users/baxi/Work/drp-v2/sites/dev/drp/config/dev-drp.conf`):

```ini
[Env]

[PreInclude]
1=common_pre_dev.conf:config

[PostInclude]
1=drp.conf:DRP

[Database]
Login=sqlsrv://_svc_regisztratoradmin:Mva4FYcj@10.27.2.182:1433/regisztratoradmin?CharacterSet=UTF-8&TrustServerCertificate=1&Authentication=SqlPassword
ConnectSQL=SET ANSI_NULLS ON;SET ANSI_WARNINGS ON;SET NOCOUNT ON;

[Config]
EditorURL=https://drp2.nemzetidohany.gov.hu:457/_editor.php
MainAddress=https://drp2.nemzetidohany.gov.hu:457/
ErrorOutput=Screen,File
ErrorLog=/var/log/sites/dev-drp-error.log

[DRP]
UploadPath=/mnt/kepfeltoltes_dev/
SAPViews=[dbo].
```

**greengo backoffice** (`/Users/baxi/Work/_docker_images/applications/greengo/backoffice/config/config/greengo_bo.conf` —
the production-grade pattern):

```ini
[PreInclude]
1=/app/dist/vendor/offsite-solutions/eisodos/src/Eisodos/config/common_pre_dev.conf:Config
2=global.conf:Config
3=i18n.conf:Config
4=tholos.conf:Config
5=filehandler.conf:FileHandler

[PostInclude]
1=version.conf:Version
2=custom.conf:Config

[Database]
connectMode=
username=greengo
password=GreenGo2018
connection=DEV
characterSet=
autoCommit=false
connectSQL=ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS';...
caseQuery=lower
caseStoredProcedure=upper

[Config]
MainAddress=https://greengo-bo-v5-docker.offsite-solutions.com:10344/
TemplateDir=/app/dist/src/assets/templates/
APP_HOMEPAGE_URL=/DASHBOARD/index/
APP_LOGOUT_URL=/COR_LOGIN/logout/
APP_ENV=DOCKER

COOKIE_DOMAIN=greengo-bo-v5-docker.offsite-solutions.com
COOKIE_SECURE=F
COOKIE_PATH=/
COOKIE_HTTPONLY=T
COOKIE_SAMESITE=

.ErrorLog=/var/log/application/$_applicationName-error.log
ErrorMailTo=greengo-alert@offsite-solutions.com
ErrorOutput=File,Mail
ErrorMailFrom=error-report@greengo.com

FileHandler.URL=https://greengo-bo-v5-docker.offsite-solutions.com:10344/bo_fileHandler.php
FileUploadPath=/app/files/files/tmp
```

**greengo `global.conf`** (cross-application shared keys — promote things
into here to keep per-app configs small):

```ini
[Config]
ImgPDir=
EditorURL=

# default cookie settings
COOKIE_PATH=/
COOKIE_SECURE=T
COOKIE_HTTPONLY=T
COOKIE_SAMESITE=

# SMTP settings
SMTP.host=smtp.email.eu-frankfurt-1.oci.oraclecloud.com
SMTP.port=587
SMTP.username=...
SMTP.password=...
MailLog=/var/log/application/mail_sender.log

# Parameter handling globals
CollectParamsToFile=
LangOpenChar=¤
LangCloseChar=¤
TrimTrailingPer=F
DisableCurlybracesReplace=T

# Title generation
TitleReplace=
TitleReplaceTag=tholosDocumentTitle
TitleCut=T

# Language settings
Langs=HU,EN
DefLang=EN
CollectLangIDs=F
ShowLangids=F
Lang=HU
LangIDFile=/app/dist/i18n/langids.txt
UserLangIDFile=/app/dist/i18n/user_langids.txt
ShowMissingTemplate=F
IncludeStatistic=F
```

**`common_pre_dev.conf`** as shipped by Eisodos
(`/Users/baxi/Work/_eisodos/Base/src/Eisodos/config/common_pre_dev.conf`)
already provides:

- `TemplateDir=$_applicationDir/templates/`
- `EnableInlineCallback=T`, `EnableTemplateAbs=T`, `EnableParamCallback=T`
- `DebugMessages=T`, `DebugErrors=T`, `DebugExceptions=T`
- `ErrorOutput=Screen,File`
- `ErrorLog=$_applicationDir/logs/$_applicationName-error.log`
- `Langs=`, `MultiLang=F`, `DefLang=HU`, `TranslateLanguageTags=T`
- `CollectParamsToFile=$_applicationDir/logs/$_applicationname-parameters.log`
- `LoopCount=1000`, `AlwaysNoCache=F`, `ShowMissingTemplate=T`
- `TitleString=%TITLE%`, `TitleReplace=<h1>`, `TitleEmptyHU=...`

If a key was set in the legacy `<appname>.conf` to the same value as the
`common_pre_*.conf` default, **delete it from the per-app file** and
rely on the include.

### 5.4 The `<appname>.params` filter file

The `.params` file is unchanged in syntax. Each non-comment, non-empty
line is a rule. Two grammar versions are supported simultaneously
(the `udSCGI` constructor merges both):

#### V2 (preferred, used by every migrated app)

`<scope>;<NAME>;<regex>;<default>;<error_msg>`

| scope | meaning |
| --- | --- |
| `exclude` | Drop the parameter silently. |
| `session` | Store in `$_SESSION` (persisted by `setParam(..., true)`). |
| `cookie` | Store as a cookie. |
| `permanent` | Same as `cookie` but the rule line takes the form `permanent;NAME=ttlSeconds`. |
| `encoded` | Decode with `udSDecode` before applying. |
| `cookie_encoded` / `session_encoded` | Cookie/session **and** decode. |
| `protected` / `protected_encoded` | Session-store **and** require a matching `csid` signature (anti-tamper). |
| `input` | Only validate (no storage choice). Used to apply the regex check + default. |
| `skip` | Skip from collected-params logging. |

Wildcards: a trailing `*` matches by prefix, e.g. `session;CMS*`.

The `regex` column is optional. When provided it is applied **after**
decoding and overrides the parameter value with `<default>` (or
redirects when `<error_msg>` is a URL).

Example used by drp-v2 (`/Users/baxi/Work/drp-v2/sites/dev/drp/config/drp.params`):

```
exclude;SESSIONID
exclude;EMPTY
exclude;CGI
exclude;MAINADDRESS
exclude;LASTACTIVE
exclude;RELOAD
exclude;CRC
exclude;NOSESSION
exclude;RURL
exclude;USID
exclude;ALLOWADMIN
session;LANG
cookie;EDITOR
encoded;CSID
encoded;REDIRECT
cookie;LANGEDIT
cookie;DEVVERSION
session;CMS*
session;DEBUGMODE
```

**Canonical skeleton across greengo / ldu / duvenbeck.** All four recent
migrations ship a nearly identical `.params` file. Use this as the
copy-paste starting point for any new multi-lingual app; adjust the
language regex and drop `session;CMS*` / `session;DEBUGMODE` if you
don't have those URL prefixes:

```
exclude;SESSIONID
exclude;EMPTY
exclude;CGI
exclude;MAINADDRESS
exclude;LASTACTIVE
exclude;RELOAD
exclude;CRC
exclude;NOSESSION
exclude;RURL
exclude;USID
exclude;ALLOWADMIN
exclude;LANGIDFILE                                # important: keep — prevents log-path override
session;LANG
cookie;EDITOR
encoded;CSID
encoded;REDIRECT
cookie;LANGEDIT
cookie;DEVVERSION
session;SESSION_*                                 # wildcard — captures every SESSION_<X> param
input;P_LANGUAGE;/^(HU|EN|CZ)$/i;HU;Nyelvhiba    # validate incoming language switcher
session;LANG;/^(HU|EN|CZ)$/i;HU;Nyelvhiba        # validate stored language too
```

The validation lines use the V2 form
`<scope>;<NAME>;<regex>;<default>;<error_msg>`. When the regex does not
match, the parameter is replaced with `<default>` and `<error_msg>` is
either logged (any text) or used as a redirect target (if it starts
with `/` or `http`). The Hungarian word `Nyelvhiba` ("language error")
is logged on mismatch.

#### V1 (legacy, still parsed; recognise it in old configs)

A one-char prefix on the parameter name:

| Prefix | Meaning |
| --- | --- |
| `-NAME` | exclude |
| `+NAME` | encoded |
| `cNAME` / `pNAME=ttl` | cookie / permanent cookie |
| `sNAME` | session |
| `xNAME` | protected |
| `CNAME` / `SNAME` / `XNAME` | encoded + cookie / session / protected |

Wildcards still work via trailing `*`. New code should use V2.

### 5.5 Active config namespaces

Sections you may want to keep separate from `[Config]`:

- `[Database]` (mandatory for any DB connector).
- `[FileHandler]`, `[Mail]`, `[Tholos]` etc. — namespace-prefixed
  parameters loaded into the main parameter dictionary via the
  `[PostInclude]` mechanism. Example greengo uses
  `5=filehandler.conf:FileHandler` to pull a whole file under one
  section name.

---

## 6. Parameter handling

### 6.1 Built-in dynamic variables

Same as udSCGI, preserved verbatim:

| Variable | Meaning |
| --- | --- |
| `$seq`, `$seq0`, `$seql`, `$seqbit`, `$seqlbit` | Sequence #1 counter, reset, last, modulo-2, last-modulo-2 |
| `$seq2`, `$seq20`, `$seq2l`, `$seq2bit`, `$seq2lbit` | Sequence #2 (independent) |
| `$currdate` | `date('Y')` |
| `$lnbr` | Line break (PHP_EOL) |
| `$random` | New random 8-char `[a-z]` string; also stored as `lastrandom` |
| `$_` | Literal `_` (useful for escaping inside parameter names) |
| `$_applicationname` | Application name |
| `$_environment` | Environment (`dev-`, `live-`, …) |
| `$_sessionid` | `session_id()` |
| `$https` | `'https'` or `'http'` |
| `$env_VARNAME` | `getenv('VARNAME')` |

### 6.2 The `^` parameter-of-parameter reference

`udSCGI::eq('NAME', '^OTHER')` compared the value of `NAME` to the value
of the parameter `OTHER`. The same indirection works in Eisodos and
also applies to `addParam` / `setParam` — passing `^OTHER` as the value
sets it to whatever `OTHER` currently holds:

```php
Eisodos::$parameterHandler->setParam('CURRENT_LANG', '^DEFAULT_LANG');
```

### 6.3 Trim / trailing slash handling

The `TRIMINPUTPARAMS=T` and `TRIMTRAILINGPER=T` defaults from
`common_pre_*.conf` still apply: inputs are trimmed; a trailing `/` is
removed when `TRIMTRAILINGPER=T`. Override only if you know why.

### 6.4 Collected-params logging

Set `CollectParamsToFile=<path>` to dump every observed parameter that is
**not** covered by `.params` — useful to find missing filter rules. The
file is written on `finish()` (and rewritten only when content
changes).

### 6.5 `RELOAD` / `PostID` / `RePost` semantics

Identical to udSCGI:

- `CRC` is stored in the session; if a new request's parameters produce
  the same CRC, `Reload=T` is set. Useful for form deduplication.
- `LastPostID` + `PostID` mechanism produces `RePost=T` when a POST is
  replayed (`PostID <= LastPostID`).

These auto-set parameters are typically listed as `exclude;` in
`.params` so users can't override them.

---

## 7. Database access

### 7.1 Registering connectors

Single primary DB:

```php
use Eisodos\Connectors\ConnectorPDOPgSQL;

Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL(), 0);
Eisodos::$dbConnectors->connector()->connect();          // reads [Database]
```

`connector()` without arguments returns the connector registered at
index 0. `connector($n)` returns the one at index `$n`. You can also use
the alias `Eisodos::$dbConnectors->db()` (same as `connector(0)`).

#### Eager vs lazy connection

**Lazy is the default for greengo / ldu / duvenbeck.** Their
`__eisodos.php` only registers the connector and never calls
`->connect()`. The first `connector()->query(...)` (or the first
`<%SQL%>` template parse) opens the connection. Skipping eager connect
is what lets helper endpoints (`ls_client.php`, `bo_pushGateway.php`,
`bo_printDocument.php` for static templates) start without ever opening
a DB session — measurable on Oracle/OCI8 where login is slow.

**Eager connect (drp-v2 style)** — `->connect()` immediately after
`registerDBConnector(...)` — is appropriate when:

- Every request hits the DB and you'd rather fail fast on a misconfigured
  `[Database]` section than at the first query.
- You need the connection alive before another piece of bootstrap runs
  (e.g. a session save handler that reads from DB).
- You're emitting `connectSQL` (Oracle session-level `ALTER SESSION SET
  NLS_DATE_FORMAT=...`) that subsequent code assumes is in effect.

If you choose lazy and a bootstrap-time error reveals that you needed
eager, add the `->connect()` line — it's a one-line change.

#### Per-connector configuration sections

The relevant `[Database]` keys are connector-specific. Refer to the
shipped READMEs:

- `Connectors/Oracle/README.md` — `connectMode`, `username`,
  `password`, `connection` (TNS name), `characterSet` (default
  `AL32UTF8`), `autoCommit`, `connectSQL`.
- `Connectors/PDOPgSQL/README.md` — `driver=pgsql|uri:///dsn`, `user`,
  `password`, `host`, `port`, `dbname`, `sslmode`, `sslcert`, `sslkey`,
  `sslrootcert`, `connectTimeout`, `prefetchSize`, `persistent`,
  `namedNotation` (`:=` or `=>`), `case` (`natural|lower|upper`),
  `stringifyFetches`, `autoCommit`, `options`, `connectSQL`.
- `Connectors/PDOSQLServer/README.md` — `driver=sqlsrv`, `DBName`,
  `server` (`host,port`), `username`, `password`, `options`,
  `connectSQL`. Recommended `connectSQL=SET ANSI_NULLS ON;SET
  ANSI_WARNINGS ON;SET NOCOUNT ON;`.
- `Connectors/MDB2` — uses the legacy `Login=driver://user:pass@host/db`
  DSN format so existing MDB2 `[Database]` sections in legacy configs
  work unchanged.

#### Multiple sections / multiple databases

```ini
[Database]
driver=pgsql
host=primary
dbname=app

[Reporting]
driver=pgsql
host=reporting
dbname=reports
```

```php
Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL(), 0);
Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL(), 1);
Eisodos::$dbConnectors->connector(0)->connect('Database');
Eisodos::$dbConnectors->connector(1)->connect('Reporting');
```

See §13 for the full multi-DB pattern.

### 7.2 Query helpers — result-transformation constants

| Legacy helper | Eisodos call | Constant | Result shape |
| --- | --- | --- | --- |
| `$db->query($sql)` (raw MDB2) | `connector()->query(RT_RAW, $sql)` | `RT_RAW = 0` | Native resultset (rarely needed). |
| `getSQL($db, $sql, &$row)` | `connector()->query(RT_FIRST_ROW, $sql, $row)` | `RT_FIRST_ROW = 1` | `[col=>val]` (first row only). |
| `getSQLback($db, $sql)` | `connector()->query(RT_FIRST_ROW_FIRST_COLUMN, $sql)` | `RT_FIRST_ROW_FIRST_COLUMN = 2` | scalar (first row, first col). |
| `getSQLtoArray($db, $sql, &$arr)` | `connector()->query(RT_ALL_KEY_VALUE_PAIRS, $sql, $arr)` | `RT_ALL_KEY_VALUE_PAIRS = 3` | `[col0 => col1, ...]`. |
| `getSQLtoArray0($db, $sql, &$arr)` | `connector()->query(RT_ALL_FIRST_COLUMN_VALUES, $sql, $arr)` | `RT_ALL_FIRST_COLUMN_VALUES = 4` | `[col0, col0, ...]`. |
| `getSQLtoArrayFull($db, $sql, &$arr)` | `connector()->query(RT_ALL_ROWS, $sql, $arr)` | `RT_ALL_ROWS = 5` | `[[col=>val,...], ...]`. |
| `getSQLtoArrayFull0($db, $sql, $idx, &$arr)` | `connector()->query(RT_ALL_ROWS_ASSOC, $sql, $arr, ['indexFieldName' => $idx])` | `RT_ALL_ROWS_ASSOC = 6` | `[$idxValue => [col=>val,...], ...]`. |
| n/a (e.g. `SET NLS_LANG=...`) | `connector()->query(RT_NO_ROWS, $sql)` | `RT_NO_ROWS = 7` | No fetch — avoids `oci_fetch_all` warnings on no-result SQL. |

The interface signature:

```php
public function query(
    int     $resultTransformation_,
    string  $SQL_,
    ?array  &$queryResult_   = null,
    ?array  $getOptions_     = [],     // currently: 'indexFieldName'
    ?string $exceptionMessage_ = ''
): mixed;
```

Real diff from `drp/cms_reglist.php` → `drp-v2/.../cms_reglist.php`:

```php
// udSCGI
$lastupload = getSQLback(
    $c->db,
    "select max(convert(nvarchar,dat_letrehoz,102)) from transfer "
    . "where registratorid=" . np($c, "user_registratorid", false)
);

// Eisodos
$lastupload = Eisodos::$dbConnectors->connector()->query(
    RT_FIRST_ROW_FIRST_COLUMN,
    "select max(convert(nvarchar,dat_letrehoz,102)) \n"
    . "from transfer where registratorid="
    . Eisodos::$dbConnectors->connector()->nullStrParam("user_registratorid", false)
);
```

`getLastQueryColumns()` / `getLastQueryTotalRows()` give you metadata
about the most recent query without re-executing it (handy for
pagination UIs that used to read `$resultSet->numRows()`).

### 7.3 SQL parameter formatting

| Legacy helper | Eisodos method (on the connector) | Notes |
| --- | --- | --- |
| `np($c, 'NAME', $isStr, $maxLen, $exc, $withComma)` | `connector()->nullStrParam('NAME', $isStr, $maxLen, $exc, $withComma)` | Empty → SQL `NULL`. Quotes/escapes string values. |
| `nnp($c, 'NAME', ...)` | `connector()->defaultStrParam('NAME', ...)` | Empty → SQL `DEFAULT`. |
| `n($value, $isStr, ...)` | `connector()->nullStr($value, $isStr, ...)` | Like `np` but for a literal value. |
| `nn($value, $isStr, ...)` | `connector()->defaultStr($value, $isStr, ...)` | Like `nnp` but for a literal value. |
| _n/a_ | `connector()->emptySQLField($value, ...)` | Generic: returns `keyword` (default `NULL`) when empty, otherwise the formatted value. |
| `nlist($csv, $isStr, ...)` | `connector()->toList($csv, $isStr, ...)` | Comma-separated list → `IN (...)`-suitable list. |

All of these throw `RuntimeException` when `$maxLen > 0` and the actual
value is longer (so SQL truncation can never reach the DB silently).
Use a non-empty `$exception` argument when you want a custom error
message instead of the default.

### 7.4 DML, prepared statements, stored procedures

`udSCGI` apps wrote `$c->db->query($insertSql)` and inspected MDB2
errors manually. Eisodos provides typed helpers:

```php
$db = Eisodos::$dbConnectors->connector();

// transactions
$db->startTransaction();              // optional savepoint name as $savePoint_
$db->commit();
$db->rollback();
$db->inTransaction(): bool;

// plain DML
$affected = $db->executeDML("delete from t where id < 1000");
                                      // returns affected-rows count
                                      // or false on error if you pass $throwException_ = false

// prepared
$db->executePreparedDML(
    "INSERT INTO t (id, n) VALUES (?, ?)",
    ['integer','string'],            // dataTypes
    [42, 'name']                     // data
);

// prepared (named placeholders, with binding)
$bound = [];
$db->bind($bound, 'ID',    'integer', 100);
$db->bind($bound, 'C_INT', 'integer', 123);
$db->bind($bound, 'C_DATE','date',    date('Y-m-d'));
$db->bind($bound, 'C_CLOB','clob',    $longText);
$db->executePreparedDML2(
    "INSERT INTO EISODOS_TEST1 (ID, C_INT, C_DATE, C_CLOB) VALUES (:ID, :C_INT, :C_DATE, :C_CLOB)",
    $bound
);

// stored procedures
$bound = [];
$db->bind($bound, 'P_ID',           'integer', '',  'IN_OUT');
$db->bind($bound, 'P_NAME',         'string',  'X', 'IN');
$db->bind($bound, 'P_OUT_INT',      'integer', '',  'OUT');
$db->bind($bound, 'P_OUT_DATE',     'date',    '',  'OUT');
$db->bind($bound, 'P_ERROR_CODE',   'integer', '',  'OUT');
$db->bindParam($bound, 'P_USER_ID', 'integer');   // value pulled from Eisodos parameter
$result = [];
$db->executeStoredProcedure('test_sp', $bound, $result);
$db->commit();
print_r($result);   // ['P_ID' => 22, 'P_OUT_INT' => 124, ...]
```

Supported `dataType_` values across connectors: `integer`, `int`,
`float`, `string`, `char`, `date`, `datetime`, `clob`. Direction:
`IN` (default), `OUT`, `IN_OUT`.

### 7.5 Named-notation operator

Some procedures use the `:=` Oracle-style named notation, others use
PostgreSQL's `=>`. Set `[Database] namednotation=:=` or `namednotation==>`
so that prepared SQL emitted by `executePreparedDML2` /
`executeStoredProcedure` matches your dialect.

### 7.6 Case folding

Both PostgreSQL and Oracle connectors expose `case=lower|upper|natural`
to force column-name case in result arrays. drp/greengo backoffices use
`caseQuery=lower, caseStoredProcedure=upper` to match historical
expectations of the templates (which reference columns by uppercase
names).

### 7.7 No more PEAR

All `(new PEAR)->isError(...)` calls have to be removed. `query()`
returns `false` on error (only when you pass `$throwException_ = false`,
otherwise it throws). The last error message is exposed via the
`LastSQLError` parameter (`Eisodos::$parameterHandler->getParam('LastSQLError')`).

### 7.8 Disconnect

`Render::finish()` disconnects every registered connector unless the
connector itself was marked persistent in config. You don't normally
need to disconnect manually.

---

## 8. Templates, parsers, callbacks

### 8.1 Template file format and syntax

Unchanged. The Eisodos `TemplateEngine` reads the same `.template`
files udSCGI used.

| Syntax | Meaning |
| --- | --- |
| `$parameterName` | Substitute parameter value (empty if not set). |
| `$param~='default';` | Substitute with default. Ends with a literal `;`. |
| `$templateabs_<id>` | Inline another template (resolves to `<id>.template`). Enabled by `EnableTemplateAbs=T`. |
| `[:LANG_ID,default text:]` | i18n lookup. Parsed by `Translator::parse()` when `TranslateLanguageTags=T`. |
| `##comment line` | Removed from output. Comment marker configurable via `COMMENTMARK`. |
| `[#funcjob=...;p=v;...#]` | **Legacy** short callback (some older apps). Same parser. |
| `[%funcjob=...;p=v;...%]` | Short callback (modern). Parsed by `CallbackFunctionShortParser`. |
| `<%FUNC% _function_name=foo key=val @key=parsed %FUNC%>` | Long callback. Parsed by `CallbackFunctionParser`. |
| `<%SQL% DB=db1; SQL=...; ROW=...; %SQL%>` | Inline SQL block. Parsed by `SQLParser` (separate package). |
| `{# urlencoded text #}` | URL-decoded block (legacy `_changeEncode`). |
| `\{` / `\}` | Escaped `{` / `}`. |

Active-version-aware template names (`header.v3.template`, etc.) are
supported via the `ActiveVersions` config key — see §17.

### 8.2 Parser registration

Eisodos does not auto-register any parser. Pick exactly what your
templates use:

```php
use Eisodos\Parsers\CallbackFunctionParser;       // <%FUNC%...%FUNC%>
use Eisodos\Parsers\CallbackFunctionShortParser;  // [%...%]  and  [#...#]
use Eisodos\Parsers\SQLParser;                    // <%SQL%...%SQL%>

Eisodos::$templateEngine->registerParser(new CallbackFunctionParser());
Eisodos::$templateEngine->registerParser(new CallbackFunctionShortParser());
Eisodos::$templateEngine->registerParser(new SQLParser());
```

If a parser is not registered, its tag syntax is left verbatim in the
output. This is the intended behaviour — pay only for what you use.

### 8.3 Callback function — signature change

`udSCGI` callback signature:

```php
function callback_default($c, $LFuncParams = []) { ... }
```

Eisodos callback signature — two valid shapes in the wild:

```php
// drp-v2 style (matches engine interface verbatim, two args):
function callback_default(array $LFuncParams = [], string $parameterPrefix_ = '') { ... }

// greengo / ldu / duvenbeck style (one-arg, explicit `: mixed` return):
function callback_default(array $LFuncParams = []): mixed { ... }
```

Both work — the engine passes a second argument but PHP ignores it when
the function signature only declares one. **Prefer the second form for
new callbacks**: it makes the return type explicit and matches what every
recent migration emits. Keep the two-arg form only if your callback
actually inspects `$parameterPrefix_` (the value passed via
`_parameter_prefix=` in `<%FUNC%...%FUNC%>` blocks — rare in practice).

The `$c` first-argument is gone — use the `Eisodos::$...` facades inside
the function instead.

Register the function once:

```php
Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');
```

**Always wrap key access in `Eisodos::$utils->safe_array_value()`.** The
direct-bracket access `$LFuncParams['funcjob']` will emit a PHP 8 warning
on every template tag that omits a key. The recent migrations
universally do:

```php
if (Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob') === 'eq') { ... }
```

This both silences the warning and lets the callback degrade to the
fallthrough `return '';` instead of bombing on malformed templates.

### 8.4 Real callback rewrite

From `drp/_callbacks.php`:

```php
function callback_default($c, $LFuncParams = array()) {
  if ($LFuncParams["funcjob"] == "eq") {
    if ($c->eq($LFuncParams["param"], $LFuncParams["value"])) {
      return $c->getTemplate($LFuncParams["true"], array(), false);
    }
    return $c->getTemplate($LFuncParams["false"], array(), false);
  }
  /* ... */
}
```

…becomes (`drp-v2/.../_callbacks.php`):

```php
use Eisodos\Eisodos;

function callback_default($LFuncParams = array(), $parameterPrefix_ = '') {
  if (!array_key_exists("funcjob", $LFuncParams)) {
    Eisodos::$logger->alert("No funcjob in callback!");
  }
  if ($LFuncParams["funcjob"] === "eq") {
    if (Eisodos::$parameterHandler->eq($LFuncParams["param"], $LFuncParams["value"])) {
      return Eisodos::$templateEngine->getTemplate($LFuncParams["true"], array(), false);
    }
    return Eisodos::$templateEngine->getTemplate($LFuncParams["false"], array(), false);
  }
  /* ... */
  return "";
}
```

### 8.5 Common `funcjob` patterns

Pre-built funcjobs that almost every migrated app implements. Use the
following table as a reference when rewriting a `_callbacks.php`:

| `funcjob=` | Behaviour | Eisodos implementation skeleton |
| --- | --- | --- |
| `eqs` | If `param == value` return `true` string, else `false` string. | See callback skeleton in §8.4 (returns scalar). |
| `eq` | Like `eqs` but `true`/`false` are template IDs to expand. | See §8.4. |
| `case` | Like `cases` but the matched value is a template ID to expand. | greengo / ldu callbacks both ship this. |
| `cases` | switch/case on `param` value; returns `<value>=<retval>;...;else=<fallback>` mapping. | `safe_array_value($params, $paramValue, safe_array_value($params, 'else'))`. |
| `tempbyparam` / `getparambyname` | Indirection: return the template named by the value of `paramname`, or return the value of the parameter named by `paramname`. | See greengo `_callbacks.php`. |
| `nl2br` | `nl2br($param)` | trivial. |
| `incparam` | Increment a numeric parameter in-place; return ''. | trivial. |
| `navi` | URL building from `_navigation.json`. | greengo-specific. |
| `funct` / `cmsfunct` / `regfunct` | Conditional template expansion with multi-condition support and `paramset`. | greengo-specific; preserve the conditions/template/return semantics. |
| `userfunc` | (auto-injected for `[#...#]`) — user-defined fallback. | Implement whatever the legacy code did under `userfunc`. |
| `isadmin` / `isadmint` | Role checks; return scalar / template. | trivially port from legacy. |

**Date/time helpers — almost every greengo / ldu / duvenbeck `_callbacks.php`
ships this exact set.** Copy-paste skeleton (returns formatted date
strings; the format is driven by the LANG-derived `DateFormat` /
`DateTimeFormat` parameters from §4.5):

| `funcjob=` | Returns |
| --- | --- |
| `today` | `date($DateFormat)` |
| `today0` | `date($DateFormat) . ' 00:00:00'` |
| `todayhm` | `date($DateTimeHMFormat)` (today + current hour:minute) |
| `now` | `date($DateTimeFormat)` |
| `nowhm` | `date($DateTimeHMFormat)` |
| `lasthour` | one hour ago, `$DateTimeFormat` |
| `lasthourhm` | one hour ago, `$DateTimeHMFormat` |
| `lastweek` | seven days ago, `$DateFormat` |
| `lastweek0` | seven days ago + `' 00:00:00'` |
| `lastyear` | one year ago, `$DateFormat` |
| `currentmonth` | first day of current month, `$DateFormat` |
| `0day` | literal `'1900-01-01'` (sentinel "no date") |
| `ymd` | `date('Ymd')` (raw, format-independent) |
| `ymdhis` | `date('YmdHis')` |

**Other recurring helpers across the four migrations:**

| `funcjob=` | Behaviour |
| --- | --- |
| `ishtml` | `preg_match('/<[^>]+>/', $param)` — returns 'T'/'F' string. |
| `userhelp_convert` | Markdown-ish: convert `((ID))` into `<a href="#ID">…</a>` internal links and substitute `((version))` with the app version. |
| `stars` | Render a Font Awesome 5-star rating row driven by an integer param value. |

Mechanical rewrites you'll always apply inside the callback body:

- `$c->getParam` → `Eisodos::$parameterHandler->getParam`
- `$c->addParam` → `Eisodos::$parameterHandler->setParam`
- `$c->eq` / `$c->neq` → `Eisodos::$parameterHandler->eq` / `neq`
- `$c->getTemplate` → `Eisodos::$templateEngine->getTemplate`
- `$c->getMultiTemplate` → `Eisodos::$templateEngine->getMultiTemplate`
- `$c->addToResponse` → `Eisodos::$templateEngine->addToResponse`
- `sa($a, 'k', 'd')` → `Eisodos::$utils->safe_array_value($a, 'k', 'd')`
- `D_replace` → `Eisodos::$utils->replace_all` (or `str_replace`)
- `D_pos($needle, $haystack) == 1` → `strpos($haystack, $needle) === 0`
- `D_copy($s, $start, $len)` → `substr($s, $start - 1, $len)` (1-indexed!)
- `D_isint` → `Eisodos::$utils->isInteger`
- `PC::debug` → `Eisodos::$logger->debug`

Read every `$LFuncParams[...]` through `Eisodos::$utils->safe_array_value`
so missing keys don't trigger PHP 8 warnings (see §8.3).

Mechanical rewrites you'll always apply inside the callback body:

- `$c->getParam` → `Eisodos::$parameterHandler->getParam`
- `$c->addParam` → `Eisodos::$parameterHandler->setParam`
- `$c->eq` / `$c->neq` → `Eisodos::$parameterHandler->eq` / `neq`
- `$c->getTemplate` → `Eisodos::$templateEngine->getTemplate`
- `$c->getMultiTemplate` → `Eisodos::$templateEngine->getMultiTemplate`
- `$c->addToResponse` → `Eisodos::$templateEngine->addToResponse`
- `sa($a, 'k', 'd')` → `Eisodos::$utils->safe_array_value($a, 'k', 'd')`
- `D_replace` → `Eisodos::$utils->replace_all` (or `str_replace`)
- `D_pos($needle, $haystack) == 1` → `strpos($haystack, $needle) === 0`
- `D_copy($s, $start, $len)` → `substr($s, $start - 1, $len)` (1-indexed!)
- `D_isint` → `Eisodos::$utils->isInteger`
- `PC::debug` → `Eisodos::$logger->debug`

Read every `$LFuncParams[...]` through `Eisodos::$utils->safe_array_value`
so missing keys don't trigger PHP 8 warnings (greengo-v5 does this
thoroughly — copy that pattern).

### 8.6 Long-form callback (`<%FUNC%...%FUNC%>`)

Body is a multi-line list of `key=value` (and `>>SEP=multi-line<<SEP`)
pairs. Reserved keys:

| Key | Effect |
| --- | --- |
| `_include=file.php` | `require_once` the file before invoking the callback. |
| `_function_name=name` | Call `name($parameterPrefix, $LFuncParams)` **instead of** the default callback. |
| `_parameter_prefix=prefix` | For every key in the body, also `setParam("$prefix_$key", $value)` (lowercased). |
| `@key=value` | The `value` is parsed (`$param` substitution etc.) before being put in `$LFuncParams`. |
| `key>>SEP=line1` ... `SEP<<` | Multi-line value. The separator can be any string starting with `>>`. |

Eisodos preserves this verbatim. Register `CallbackFunctionParser` and
keep your existing templates working.

### 8.7 Inline SQL (`<%SQL%...%SQL%>`)

Register the parser (`new SQLParser()`) and your templates work
unchanged. The recognised structure parameters are identical to the
legacy `_replaceSQL`:

| Key | Default | Meaning |
| --- | --- | --- |
| `DB` | `db1` | Connector index (`db1` = index 0, `db2` = index 1, …) **or** a parameter name whose value is the integer index. |
| `SQL` | _required_ | The SQL to execute. `$parameter` substitution is applied. |
| `ROW` | _required_ | Template ID (or comma-separated list) used per result row; or `@__funcname` to call a user function per row. |
| `HEAD`, `FOOT` | empty | Wrapper templates around the whole result. |
| `ROWNULL`, `HEADNULL`, `FOOTNULL` | empty | Templates used when the result set is empty. |
| `PAGEFIRST`, `PAGELAST`, `PAGEINNER` | empty | Pagination header/footer templates. |
| `NOHEADPAGE`, `NOFOOTPAGE` | `F`/empty | Disable head/foot on paginated requests. |
| `ROWFROM` | `1` | 1-based starting row. |
| `ROWCOUNT` | `0` | Page size (0 = all). |
| `TABLECOLS` | `1` | Render result as a multi-column table; emits `TABLEROWBEGIN`/`TABLEROWEND` every N rows. |
| `TABLEROWBEGIN`, `TABLEROWEND` | empty | Templates wrapping each "table row" (group of `TABLECOLS` result rows). |
| `GROUP`, `GROUPBEGIN`, `GROUPEND` | empty | Group-break templates. |
| `CONVERTLATIN2UTF8`, `CONVERTUTF82LATIN` | `F` | Encoding conversion of column values. |

Per-row parameters set automatically:

| Parameter | Value |
| --- | --- |
| `SQL<colname>` | Column value (uppercased col name). |
| `sql<colname>_<key>` | If `<colname>` starts with `json__`, every JSON top-level key is auto-exposed. |
| `SQLROWRELCOUNT` | Row number within this page (1-based). |
| `SQLROWABSCOUNT` | Absolute row number across pages. |
| `SQLTABLEROWCOUNT` | Table-row counter when `TABLECOLS > 0`. |
| `SQLNEXTPAGE`, `SQLPREVPAGE` | Computed pagination offsets. |

If `ENABLEINLINESQL=F` is set in `[Config]`, every `<%SQL%>` block is
replaced with `<!-- SQL not allowed -->`. Useful for staging
environments where you want to be sure no templates run ad-hoc SQL.

---

## 9. Logging and error handling

### 9.1 API

```php
Eisodos::$logger->trace($msg);
Eisodos::$logger->debug($msg);
Eisodos::$logger->info($msg);
Eisodos::$logger->notice($msg);
Eisodos::$logger->warning($msg);
Eisodos::$logger->error($msg);
Eisodos::$logger->critical($msg);
Eisodos::$logger->alert($msg);
Eisodos::$logger->emergency($msg);
Eisodos::$logger->log($msg, 'debug', $senderObject);
Eisodos::$logger->writeErrorLog($throwable, $debugInfo, $extraEmailRecipients);
Eisodos::$logger->writeOutLogLine($line);
Eisodos::$logger->sendOutLogToUrl();        // forwards collected log to DEBUGTOURL endpoint
$linesArr = Eisodos::$logger->getDebugLog();
```

### 9.2 Configuration keys

Identical to udSCGI for the user-visible bits:

| Key | Meaning |
| --- | --- |
| `ErrorOutput=Mail,Screen,File,@callback_name` | Comma-separated channels. `Mail`: send via Mailer. `Screen`: append to response (development!). `File`: append to `ErrorLog`. `@name`: invoke `name(['Message'=>..., 'File'=>..., 'Line'=>..., 'Trace'=>..., 'Parameters'=>..., 'Debug'=>...])`. |
| `ErrorLog=<path>` | File destination. Always mark `.ErrorLog` to lock down. |
| `ErrorMailTo=a@b,c@d` | Recipient list for `Mail` channel. |
| `ErrorMailFrom=...` | Sender. |
| `ErrorMailSubject=...` | Subject line; configurable (commit `22b5ffd`). |
| `DebugMessages=T/F` | Enable `Eisodos::$logger->debug` and friends in output. |
| `DebugExceptions=T/F` | Catch all exceptions through `writeErrorLog`. |
| `DebugErrors=T/F` | Convert PHP errors to log entries. |
| `DEBUGLEVELS=trace,debug,info` | Comma-separated list of active levels. |
| `DEBUGOUTPUTS=Screen,File` | Channels for normal log output. |
| `DEBUGURLPREFIX=DEBUG_` | Enable per-request override via URL params (`?DEBUG_DebugLevel=trace`). |
| `DEBUGPASSWORD=...` | Password gate for the URL override. |
| `DEBUGTOURL=http://...` | When set, the in-memory log is POSTed to this URL on `finish()`. |
| `DebugRequestLog=T/F` | Log the full request envelope. |
| `IncludeStatistic=T/F` | Append timing statistic comment to output. |

`Render::start()` installs `Eisodos::$logger->writeErrorLog` as
`set_exception_handler` automatically when `ErrorOutput` is non-empty.

### 9.3 Per-request debug override

```
GET /page.php?DEBUG_DebugLevel=trace&DEBUG_DebugToUrl=https://my.tunnel/dbg
```

Set `DEBUGURLPREFIX=DEBUG_` and `DEBUGPASSWORD=<secret>`; pass
`DEBUG_password=<secret>` along with the override params. Persists for
the session (`SessionDebugLevel`, `SessionDebugToUrl`).

---

## 10. Internationalisation (i18n)

Mostly preserved. Key knobs in `[Config]`:

| Key | Meaning |
| --- | --- |
| `Langs=HU,EN,CZ` | Comma-separated list of language IDs. |
| `DefLang=HU` | Default language. |
| `DefTemplateLang=HU` | Default template language (when multi-template). |
| `MultiLang=T/F` | Multi-language mode. |
| `Lang=...` | Per-session current language. |
| `LangIDFile=/path/langids.txt` | Persistent store of collected language IDs (`COLLECTLANGIDS=T`). |
| `UserLangIDFile=/path/user_langids.txt` | User-edited overrides. |
| `CollectLangIDs=T/F` | Persist new language IDs encountered at runtime. |
| `ShowLangids=T/F` | Render language ID inline (debugging). |
| `LangOpenChar=¤` / `LangCloseChar=¤` | Override the `[:` / `:]` delimiters (greengo uses `¤`). |
| `TranslateLanguageTags=T/F` | Auto-translate `[:ID,default:]` tags. |
| `LANGFROMHEADER=T/F` / `LANGHEADER=...` | Detect language from `Accept-Language` or a named header. |

All of these were honoured by udSCGI in identical form.

### 10.1 API

```php
$text  = Eisodos::$translator->getLangText('WELCOME_MSG', ['user'=>$name]);
$page  = Eisodos::$translator->translateText($pageHtml);
$ids   = Eisodos::$translator->getLanguageIDs();
Eisodos::$translator->loadMasterLanguageFile();      // forced reload
Eisodos::$translator->setCollectLangIDs(true);
```

### 10.2 File format

Language files (`languages/<lang>.lang`) are unchanged: `KEY=value`
lines, `#` comments, `\n` literal escapes.

`Translator::finish()` (called from `Render::finish()`) persists newly
discovered language IDs to `LangIDFile` when `CollectLangIDs=T` and the
CRC of the in-memory dictionary differs from when the request started
(commits `2874a4d`, `1545061` improved the collection logic).

---

## 11. Mailer

The `Eisodos\Mailer` singleton is a thin wrapper around `PHPMailer`.
Replace any handwritten `PEAR Mail`, `mail()`, or
`sendErrorMail()` helper from the legacy code with:

```php
Eisodos::$mailer->sendMail(
    string $to_,                                  // 'a@b' or 'name <a@b>' or comma/semicolon list
    string $subject_,
    string $body_,                                // HTML
    string $from_,                                // 'a@b' or 'name <a@b>'
    array  $filesToAttach_       = [],            // paths
    array  $fileStringsToAttach_ = [],            // [['content'=>'...', 'filename'=>'x.pdf']]
    string $cc_      = '',
    string $bcc_     = '',
    string $replyTo_ = ''
): bool;
```

It reads SMTP settings from `[Config]` keys:

- `SMTP.host`, `SMTP.port`, `SMTP.username`, `SMTP.password`
- `MailLog=/path/mail_sender.log` (when set, every PHPMailer exception is logged here)

Error mail (when `ErrorOutput=Mail`) uses `ErrorMailFrom`,
`ErrorMailTo`, `ErrorMailSubject`. Pass extra one-off recipients as
the third argument to `writeErrorLog`.

---

## 12. Helper-function mapping (`udSDelphiPHP.php`)

The `udSDelphiPHP.php` shim is **completely removed** during migration.
Every function in it has a direct replacement. Use this table top-to-
bottom on a search/replace pass.

### 12.1 String / array helpers

| Legacy | Replacement |
| --- | --- |
| `D_pos($needle, $haystack)` (1-indexed; 0 = not found) | `strpos($haystack, $needle) + 1` — but most uses compare to a constant. Common patterns: `D_pos($x, $s) > 0` → `strpos($s, $x) !== false`. `D_pos($x, $s) == 1` → `strpos($s, $x) === 0` (starts-with). |
| `D_copy($s, $from, $count)` | `substr($s, $from - 1, $count)` (`$from` is 1-indexed!) |
| `D_replace($s, $search, $rep, $all, $ci)` | `Eisodos::$utils->replace_all($s, $search, $rep, $all, $ci)` — same semantics. |
| `D_in($char, $charsArr)` | `in_array($char, $charsArr, true)` |
| `D_array2text($a)` | `print_r($a, true)` |
| `D_isint($x, $allowNeg)` | `Eisodos::$utils->isInteger($x, $allowNeg)` |
| `D_isfloat($x, $allowNeg)` | `Eisodos::$utils->isFloat($x, $allowNeg)` |
| `D_gen_uuid()` | `Eisodos::$utils->generateUUID()` |
| `D_pad($s, $len, $pad)` | `str_pad($s, $len, $pad)` (native) |
| `D_healApostrof($c, $s)` | Use the connector's `nullStr()` / `nullStrParam()` instead — the legacy helper predates parameter binding. |
| `sa($arr, 'k', 'd', $ci)` | `Eisodos::$utils->safe_array_value($arr, 'k', 'd', $ci)` |
| `str_replace_count(...)` | `Eisodos::$utils->str_replace_count(...)` |
| `str_ireplace_count(...)` | `Eisodos::$utils->str_ireplace_count(...)` |
| `modulus($x, $y)` | `$x % $y` |
| `validEmail($e)` | `filter_var($e, FILTER_VALIDATE_EMAIL) !== false` |
| `compare_dates / compare_dates_hu` | Native `DateTime` / `DateInterval`, or a small app-local helper. |

### 12.2 SQL formatting helpers (already covered in §7.3)

| Legacy | Replacement |
| --- | --- |
| `np($c, 'N', $isStr, $maxLen, $exc, $cma)` | `connector()->nullStrParam('N', ...)` |
| `nnp($c, 'N', ...)` | `connector()->defaultStrParam('N', ...)` |
| `n($val, $isStr, ...)` | `connector()->nullStr($val, ...)` |
| `nn($val, ...)` | `connector()->defaultStr($val, ...)` |
| `nlist($csv, ...)` | `connector()->toList($csv, ...)` |

### 12.3 SQL execution helpers (already covered in §7.2)

| Legacy | Replacement |
| --- | --- |
| `getSQL($db, $sql, &$row)` | `connector()->query(RT_FIRST_ROW, $sql, $row)` |
| `getSQLback($db, $sql)` | `connector()->query(RT_FIRST_ROW_FIRST_COLUMN, $sql)` |
| `getSQLtoArray($db, $sql, &$arr)` | `connector()->query(RT_ALL_KEY_VALUE_PAIRS, $sql, $arr)` |
| `getSQLtoArray0($db, $sql, &$arr)` | `connector()->query(RT_ALL_FIRST_COLUMN_VALUES, $sql, $arr)` |
| `getSQLtoArrayFull($db, $sql, &$arr)` | `connector()->query(RT_ALL_ROWS, $sql, $arr)` |
| `getSQLtoArrayFull0($db, $sql, $idx, &$arr)` | `connector()->query(RT_ALL_ROWS_ASSOC, $sql, $arr, ['indexFieldName' => $idx])` |
| `runSQL($db, $sql)` | `connector()->executeDML($sql)` |
| `runSQLprep($db, $sql, $types, $data)` | `connector()->executePreparedDML($sql, $types, $data)` |

### 12.4 File / image helpers

The legacy `udS_resizeImage`, `udS_deleteImage`, `udS_renamePic`,
`udS_numPics`, `rename_` helpers have **no framework replacement** —
they are app-domain. Move them into an app-local class
(e.g. `app/ImageHelper.php`) and refactor at your own pace.

---

## 13. Multi-database setups

`udSCGI` exposed `$c->db, $c->db2, $c->db3, $c->db4` as named fields.
Eisodos uses **indexed registrations**.

### 13.1 Registration

```ini
[Database]            ; primary
driver=pgsql
host=primary.host
dbname=app

[Database2]           ; secondary (arbitrary section name)
driver=sqlsrv
DBName=reporting
server=reporting.host,1433
username=ro
password=...
```

```php
Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOPgSQL(),   0);
Eisodos::$dbConnectors->registerDBConnector(new ConnectorPDOSQLSrv(),  1);

Eisodos::$dbConnectors->connector(0)->connect('Database');
Eisodos::$dbConnectors->connector(1)->connect('Database2');
```

### 13.2 Mechanical rewrite

| Legacy | Eisodos |
| --- | --- |
| `$c->db->query($sql)` | `Eisodos::$dbConnectors->connector()->query(RT_RAW, $sql)` |
| `$c->db2->query($sql)` | `Eisodos::$dbConnectors->connector(1)->query(RT_RAW, $sql)` |
| `getSQL($c->db3, $sql, $r)` | `Eisodos::$dbConnectors->connector(2)->query(RT_FIRST_ROW, $sql, $r)` |
| `$c->openDB3()` | implicit — `connector(2)->connect('Database3')` on bootstrap. |

The `<%SQL% DB=db2 %SQL%>` template syntax still resolves
`db<N>` → connector index `N-1`. To switch databases at runtime via a
parameter, do `DB=param_name_whose_value_is_1` (parser dereferences).

---

## 14. Raw / AJAX / API / CLI entry points

### 14.1 HTML page

```php
<?php
  use Eisodos\Eisodos;
  include('_init.php');
  Eisodos::$templateEngine->getTemplate('page.main.head');
  Eisodos::$templateEngine->getTemplate('page.main.body');
  Eisodos::$templateEngine->getTemplate('page.main.foot');
  Eisodos::$render->finish();
```

### 14.2 JSON / AJAX / API endpoint

```php
<?php
  use Eisodos\Eisodos;
  include('_init.php');

  header('Content-Type: application/json');
  $payload = ['ok' => true, 'data' => [...]];
  Eisodos::$templateEngine->addToResponse(json_encode($payload));

  // false: don't save session (idempotent GETs)
  // false: don't persist language IDs
  Eisodos::$render->finishRaw(false, false);
```

`finishRaw` differs from `finish` in that:

- It does not call `Translator::finish()` to write LangIDs unless
  `$handleLanguages = true`.
- It does not call `_doResponse(true)` template wrapping.
- Optionally saves session (`$saveSessionVariables = true`).

### 14.3 CLI / cron script

```php
<?php
  use Eisodos\Eisodos;
  require_once __DIR__ . '/_init.php';
  /* run the job */
  Eisodos::$dbConnectors->connector()->executeDML('CALL nightly_job()');
  Eisodos::$render->finishRaw(false, false);
```

Cron scripts should typically:

- Set `ALWAYSNOCACHE=T` (or `disableHTMLCache` cache option).
- Disable session persistence (`finishRaw(false, ...)`).
- Read parameters from `argv` (replace any `_GET/_POST` reading with a
  small CLI parser, then `Eisodos::$parameterHandler->setParam(...)`).

### 14.4 LangServer client (`ls_client.php`)

Every greengo / ldu / duvenbeck migration ships an `ls_client.php`
endpoint that exchanges translation data with an external LangServer
service. It bootstraps the framework only (`__eisodos.php`, not
`_init.php` — autoload and date params are not needed) and dispatches
on a `LS_ACTION` parameter. Skeleton:

```php
<?php
  use Eisodos\Eisodos;
  require_once __DIR__ . "/__eisodos.php";

  function post_data(array $data): string {
    $opts = ['http' => [
      'header'  => "Content-type: application/x-www-form-urlencoded\r\n"
                 . "Accept-Encoding: gzip,deflate,sdch\r\n"
                 . "Accept-Charset:UTF-8,*;q=0.5\r\n",
      'method'  => 'POST',
      'content' => http_build_query(
        array_merge($data,
          ['ls_auth_key' => Eisodos::$parameterHandler->getParam('LangServerAuthKey')]),
        '', '&', PHP_QUERY_RFC3986)
    ]];
    $content = file_get_contents(
      Eisodos::$parameterHandler->getParam('LangServerURL'),
      false,
      stream_context_create($opts)
    );
    if ($content === false) { throw new RuntimeException('Response error!'); }
    foreach ($http_response_header as $h) {
      if (stripos($h, 'content-encoding') !== false && stripos($h, 'gzip') !== false) {
        $content = gzinflate(substr($content, 10, -8));
      }
    }
    return $content;
  }

  // 1. auth — only `request` is unauthenticated; everything else requires the key
  if (Eisodos::$parameterHandler->neq('LS_ACTION', 'request')
      && Eisodos::$parameterHandler->getParam('LangServerAuthKey', '*')
         != Eisodos::$parameterHandler->getParam('LS_AUTH_KEY', '#')) {
    http_response_code(403);
    Eisodos::$templateEngine->addToResponse('Unauthorized request');
    Eisodos::$render->finishRaw(false);
    exit;
  }

  // 2. dispatch — supported actions: keys | tags | defaults | status | push | request
  if (Eisodos::$parameterHandler->eq('LS_ACTION', 'keys')) {
    header('Content-type: application/json');
    Eisodos::$templateEngine->addToResponse(
      json_encode(Eisodos::$translator->getLanguageIDs(), JSON_THROW_ON_ERROR));
    Eisodos::$render->finish();
    exit;
  }
  // ... `tags` → translator->userLanguageIDs ...
  // ... `defaults` → Translator::getLangTextForTranslate($key, "#", false) per key ...
  // ... `status` → file_get_contents(USERLANGIDFILE . '.status') ...
  // ... `push` → file_put_contents(USERLANGIDFILE, payload) + status JSON sibling ...
  // ... `request` → post_data(...) to the language server ...
```

Required config (typically in `global.conf`):

```ini
LangServerURL=https://langserver.internal/api
LangServerAuthKey=<shared-secret>
UserLangIDFile=/app/dist/i18n/user_langids.txt
```

The endpoint **must finish with `Eisodos::$render->finish()` or
`finishRaw(false)`** for each branch; otherwise the global output buffer
flushes whatever was already accumulated, which corrupts the JSON
response. Always include the `exit` after `finish()` — multiple
branches fall through otherwise.

### 14.5 File handler split: `bo_fileHandler.php` + `fe_fileHandler.php`

The recurring pattern for file ingestion (used by greengo, ldu,
duvenbeck) is a **two-endpoint split**:

- **`bo_fileHandler.php`** — server-side. Accepts a richly-parameterised
  set of file operations (`SourceType` ∈ FILE/STDIN/PARAMETER/URL/OBJECT/FILELIST/OBJECTS,
  `TargetType` ∈ FILE/OBJECT, `FileOperation` ∈ create/read/move/copy/delete/append/zip).
  Lives in `src/`. The canonical authoritative parameter contract is the
  Hungarian-language docblock at the top of
  `/Users/baxi/Work/greengo/backoffice/3Development/v5/src/bo_fileHandler.php` —
  read it before changing any handling logic. Bootstraps with
  `include '_init.php'` (needs the namespace autoloader for image
  helpers).

- **`fe_fileHandler.php`** — public/frontend bridge. POSTs to the BO
  handler over HTTP using the `FileHandler.URL` config key, exposing a
  much narrower surface (`FileOperation=READ`, `SourceType=OBJECT`).
  Bootstraps with `require_once __DIR__ . '/__eisodos.php'` only — it
  doesn't autoload any classes. Used to bridge a public portal into the
  back-office file store without exposing the BO endpoint directly.

The wiring config you must set:

```ini
FileHandler.URL=https://backoffice.internal/bo_fileHandler.php
FileHandler.FILE.BasePath=/app/files/files
FileUploadPath=/app/files/files/tmp
```

The FE handler's request body shape:

```php
$fileHandler = [
  'FileOperation'     => 'READ',
  'SourceType'        => 'OBJECT',
  'SourceObject'      => basename(Eisodos::$parameterHandler->getParam('SourceObject')),
  'SourceFileName'    => basename(Eisodos::$parameterHandler->getParam('SourceFileName')),
  'SourceFileDate'    => Eisodos::$parameterHandler->getParam('SourceFileDate'),
  'OriginalFileName'  => basename(Eisodos::$parameterHandler->getParam(
                                   'OriginalFileName',
                                   Eisodos::$parameterHandler->getParam('filename'))),
  'Caller'            => Eisodos::$parameterHandler->getParam('Filehandler.Caller'),
  'ResponseType'      => 'JSON',
  'SuppressLogError'  => 'T',
];
$ctx = stream_context_create(['http' => [
  'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
  'method'  => 'POST',
  'content' => http_build_query($fileHandler),
]]);
$result = file_get_contents(
  Eisodos::$parameterHandler->getParam('FileHandler.URL'),
  false, $ctx
);
```

The BO handler returns JSON `{"errorcode":"0", "originalFileName":..., "sourceFileSize":..., ...}`;
the FE handler decodes that, then streams the file content back to the
browser with appropriate `Pragma`/`Expires`/`Content-Type` headers.
Always check `errorcode !== '0'` before streaming — silent failures
otherwise return an empty body.

### 14.6 Helper endpoints (`bo_mailSender.php`, `bo_pushGateway.php`, `bo_printDocument.php`)

Three common helper endpoints — all bootstrap framework-only with
`require_once __DIR__ . '/__eisodos.php'` (no autoloader needed):

**`bo_mailSender.php`** — accepts a `data` parameter (JSON body) and
sends mail via `Eisodos::$mailer->sendMail(...)`. Disables noise:

```php
require_once __DIR__ . '/__eisodos.php';

Eisodos::$parameterHandler->setParam('CollectLangIDs', 'F');
Eisodos::$parameterHandler->setParam('IncludeStatistic', 'F');

try {
  $data = json_decode(
    Eisodos::$parameterHandler->getParam('data'),
    true, 512, JSON_THROW_ON_ERROR
  );
  // ... validate from/to/subject/body ...
  // ... fetch attachments via FileHandler.URL ...
  Eisodos::$mailer->sendMail(
    $data['to'], $data['subject'], $data['body'], $data['from'],
    $attachmentPaths, $attachmentStrings,
    $data['cc'] ?? '', $data['bcc'] ?? '', $data['replyTo'] ?? ''
  );
} catch (Throwable $e) {
  // structured JSON error
}
Eisodos::$render->finishRaw(false, false);
```

**`bo_pushGateway.php`** — outbound JSON push to a configurable URL.
Accepts `P_URL` and `P_JSON` parameters. The auth header is currently
hardcoded in greengo-bo — when migrating, parameterise it through config
(`PushGateway.AuthHeader=...`) instead of inlining.

```php
require_once __DIR__ . '/__eisodos.php';

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL            => Eisodos::$parameterHandler->getParam('P_URL'),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_SSL_VERIFYPEER => false,           // greengo-bo current — review before reusing
  CURLOPT_POST           => 1,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/json',
    'Authorization: Basic ' . Eisodos::$parameterHandler->getParam('PushGateway.AuthHeader'),
  ],
  CURLOPT_POSTFIELDS     => Eisodos::$parameterHandler->getParam('P_JSON', '{}'),
]);
Eisodos::$templateEngine->addToResponse(curl_exec($ch));
curl_close($ch);
Eisodos::$render->finish();
```

**`bo_printDocument.php`** — renders `print.<document_id>.main` from
`src/assets/templates/` and outputs HTML. Bootstraps with the full
`_init.php` because print templates often reference language-derived
date formats:

```php
include '_init.php';
Eisodos::$templateEngine->getTemplate(
  'print.' . Eisodos::$parameterHandler->getParam('document_id') . '.main'
);
Eisodos::$render->finish();
```

**Choosing `__eisodos.php` vs `_init.php` for new helpers** — use the
narrower bootstrap when the endpoint touches none of: app-namespaced
classes, language-derived date params, the SPL autoloader. Otherwise
use `_init.php`. Don't paste both.

---

## 15. Wizard parameters

udSCGI had first-class wizard storage: input parameters starting with
`w` were copied into `WIZ.<wizardname>.<paramname>` session-stored
keys. `getWizardParams('wizname')` extracted them; `wizClear('wizname')`
emptied them.

Eisodos has no first-class wizard API. Recreate it with normal
parameter operations:

```php
// store wizard params from a POST
foreach ($_POST as $k => $v) {
  if (str_starts_with(strtolower($k), 'w') && !in_array($k, ['wizard', 'wizlap'], true)) {
    Eisodos::$parameterHandler->setParam('WIZ.MYWIZ.' . $k, $v, true /*session*/);
  }
}

// fetch them
$wizardParams = [];
foreach (Eisodos::$parameterHandler->getParamNames('/^wiz\.mywiz\./i') as $name) {
  $shortKey = substr($name, strlen('WIZ.MYWIZ.'));
  $wizardParams[$shortKey] = Eisodos::$parameterHandler->getParam($name);
}

// clear
foreach (Eisodos::$parameterHandler->getParamNames('/^wiz\.mywiz\./i') as $name) {
  Eisodos::$parameterHandler->setParam($name, '', true);
}
```

Most migrated apps do not use the wizard mechanism — verify with
`grep -RIn 'wiz' templates/` and `grep -RIn 'WIZ\\.' .` before porting.

---

## 16. Encoded / signed parameters and CSRF posture

udSCGI's `protected` parameters required a matching `csid` value
(generated as `udSCode(SESSIONID)`). Eisodos preserves `udSCode` /
`udSDecode` on `ParameterHandler`, and the `.params` file rules
(`protected;`, `protected_encoded;`, `encoded;`) still work the same
way during input parameter loading.

The mechanical effect: when a parameter is declared `protected;NAME`,
its inbound value is rejected unless `udSDecode($_GET['csid']) ==
SESSIONID` (or the remote address matches `ALLOWADMIN`).

For any new code, prefer one of:

- Server-side state with proper CSRF tokens (use a per-form synchronizer
  token stored in session, generated with `random_bytes()`).
- HTTP-only, SameSite cookies for session-bound state.

The udSCGI `csid` mechanism is preserved so you don't have to rewrite
every form during migration — but treat it as a legacy compatibility
shim, not a new security primitive.

---

## 17. Active versions (`DevVersion`, `ActiveVersions`)

`udSCGI` supported template fan-out by version prefix: with
`ActiveVersions=v3,v2`, `getTemplate('header')` would look up
`header.v3.template`, `header.v2.template`, then `header.template` in
order. The `DevVersion` URL parameter could prepend an extra version
chain for QA-only templates.

Eisodos keeps this. The relevant config keys are:

- `[Versions] ActiveVersions=v3,v2`
- `TemplateVersionAlert=T` — warns when editor edits a non-latest version.
- URL: `?DevVersion=v4` prepends `v4` and adds the configured
  `[v4]` section to the parameter handler.

Most migrated apps **dropped** active versions in favor of feature
flags. If your legacy app relied on them, verify your post-migration
template list still contains all the version-suffixed files.

---

## 18. Performance and caching

| Concern | udSCGI | Eisodos |
| --- | --- | --- |
| Template caching | In-process `_templateCache` for the duration of one request only. | Same — in-process per-request. |
| HTML caching | `$disableHTMLCache_` constructor flag + `ALWAYSNOCACHE=T` config. | `['disableHTMLCache' => true]` in cache options, or `ALWAYSNOCACHE=T`. |
| Output buffering | `ob_start()` in the constructor; flushed in `finish()`. | `ob_start()` in `Render::start()`; flushed in `finish()`. |
| Statistics injection | `IncludeStatistic=T` appends a timing comment. | unchanged. |
| Persistent DB connections | Driver-specific (MDB2 `persistent`). | Per-connector `persistent` flag in `[Database]`. |
| Memory profile | `udSCGI` keeps full template cache, language file, params and PhpConsole stack per request. | Slightly lighter (PhpConsole is gone). |

Two simple optimisations to apply during migration:

1. **Strip unused parsers.** If your templates have no `<%SQL%>`, skip
   `SQLParser`. If you have no `<%FUNC%>`, skip the long-form parser.
2. **Use `RT_NO_ROWS`** for SET/DDL statements (e.g.
   `SET NLS_DATE_FORMAT=...`) — avoids fetch-overhead and warnings.

---

## 19. Step-by-step migration recipe

This is the linear procedure used in drp-v2, greengo-v5 (frontend),
greengo-backoffice-v5, greengo-partnerportal-v5, ldu-backoffice-v2 and
duvenbeck-v3-backoffice. Follow it top-to-bottom; the app should be
runnable after each numbered step.

### 19.1 Layout decision (before step 1)

Choose **Layout A (flat)** or **Layout B (`src/`-based)** from §3.2.
Stick with the one you choose — partial layouts confuse autoloaders and
Docker mounts. Quick rule:

- Single developer, single environment, < ~5 entry points → **A**.
- Docker-built, multi-environment, multiple helper endpoints (file
  handler, mailer, ls_client, push gateway) → **B**.

### 19.2 The recipe

1. **Inventory.**
    - List every `.php` entry point and `.template` file.
    - `grep -RIn 'Login=' config/*.conf` to find DB drivers.
    - Check templates for `<%SQL%`, `<%FUNC%`, `[#`, `[%`. Each one
      decides which parser you need.
    - `grep -RIn '\$c->' .` produces the search/replace working set.
    - `grep -RIn '^\\s*session;' config/*.params` — list `session;` rules
      to understand whether a custom session handler is needed.

2. **Composer.**
    - Create `composer.json` (§3.1). PHP floor: `"php": "^8.4"`.
    - Pull in the relevant DB connector(s) and `eisodos-sql-parser` if
      needed.
    - For Layout B, also create `composer.dev.json` with the
      `/opt/local-dev/composer/...` symlink repos.
    - Ship `composer_update.sh` + `composer_update_dev.sh` wrappers
      (Layout B; see §3.1 Pattern B).
    - `./composer_update.sh` (or `composer install` for Layout A).

3. **Filesystem.**
    - Layout A: copy `templates/`, `languages/`, `config/` into the new
      project root.
    - Layout B: place `templates/`, `assets/`, `i18n/` under `src/` (or
      `src/assets/` for templates); externalize `config/` to
      `_docker_images/applications/<app>/config/config/` and mount at
      runtime.
    - Delete from `includes/`:
      - `udSCGI.php`, `udSDelphiPHP.php`, `udSEditor*.php`
      - `PhpConsole*`, `PhpConsole.php.deprecated`
      - `MDB2.php`, `MDB2/`, `PEAR.php`
      - `Mail.php`, `Mail/`

   The Eisodos framework itself does **not** need a hand-rolled vendor
   copy. Anything else legacy code requires (`StoredProcedureHelper.php`)
   you can either delete or convert to an app-local class.

4. **`config/environment`.** Create the one-line env file (`dev`,
   `local`, `test`, `live`).

5. **Config rewrite.**
    - Rename `<appname>.conf` → `{env}-<appname>.conf`.
    - Add `[PreInclude]` that pulls in
      `common_pre_{env}.conf` (from the Eisodos vendor) and (optionally)
      shared `global.conf` / `i18n.conf` etc.
    - Move DB connection info into a `[Database]` section in the
      connector-specific format (§7.1).
    - Mark sensitive keys readonly with a leading `.`.
    - If using `_init_parameters.php`: ensure `HU.DATEFORMAT=...`,
      `HU.JSDATEFORMAT=...`, `HU.PHPDATEFORMAT=...` (and equivalents
      per language in `Langs=`) are present — see §4.5.

6. **Bootstrap.**
    - Layout A: replace `_init.php` with the §4.2 single-file template.
    - Layout B: create the four-file split from §4.3 — `__eisodos.php`,
      `_init.php`, `_autoload.php`, `_init_parameters.php`. Use one app
      namespace consistently (e.g. `greengo`, `nop_bo`, `duvenbeck_bo`).
    - Decide eager vs lazy DB connection (§7.1) — default: lazy, no
      `->connect()` call in `__eisodos.php`.
    - Verify the page loads (`?DEBUGMESSAGES=T`).

7. **Callbacks.** Open `_callbacks.php`.
    - Change signature from `function callback_default($c, $LFuncParams)`
      to `function callback_default(array $LFuncParams = []): mixed`
      (or the two-arg form if you read `$parameterPrefix_`).
    - Apply the search/replaces from §8.5.
    - Wrap every `$LFuncParams[...]` access in
      `Eisodos::$utils->safe_array_value`.
    - Port the date/time helper funcjob set (§8.5) — even small apps
      tend to use `today`, `now`, `lastweek`, `ymdhis`.

8. **Each page entry.** For every `.php` file in the app:
    - Replace `$c = new udSCGI(...)` / `require_once 'includes/udSCGI.php'`
      with `include '_init.php'` (full bootstrap) **or**
      `require_once __DIR__ . '/__eisodos.php'` (framework only — for
      helpers that don't need the autoloader or date params; see §14.6).
    - Apply `$c->` → `Eisodos::$...` rewrites from §2.
    - Replace SQL helpers with `connector()->query(RT_*, ...)`.
    - Replace `np($c, 'name', ...)` with
      `connector()->nullStrParam('name', ...)`.
    - Replace `$c->finish()` / `$c->finishRaw()` with
      `Eisodos::$render->finish()` / `finishRaw()`.
    - Replace `PC::debug(...)` with `Eisodos::$logger->debug(...)`.

9. **Helper endpoints.** Port the standard helper set if the legacy app
   had any (§14.4–14.6):
    - `ls_client.php` — LangServer client.
    - `bo_fileHandler.php` + `fe_fileHandler.php` — file operations.
    - `bo_mailSender.php` — JSON-driven mail dispatcher.
    - `bo_pushGateway.php` — outbound JSON push (parameterise the auth
      header — don't inline like greengo-bo currently does).
    - `bo_printDocument.php` — print template renderer.

10. **Globals.** Where the legacy code did `global $c;` in helper
    functions, simply drop the `global` and use `Eisodos::$...` directly
    — every singleton is statically accessible.

11. **Templates.** No content changes are required except:
    - Remove `<%SQL%` blocks if you decide not to register `SQLParser`.
    - Replace any handwritten references to `$response_string`
      (rare).
    - Verify any custom `LangOpenChar`/`LangCloseChar` is preserved in
      `[Config]`.

12. **Cron / CLI scripts.** Same rewrite as page entries, but always
    call `Eisodos::$render->finishRaw(false, false)` at the end so no
    HTML wrapping occurs.

13. **Smoke test.**
    - Hit each entry point with `RELOAD=T`, `DEBUGMESSAGES=T`.
    - Watch `.ErrorLog` and `CollectParamsToFile`.
    - Compare rendered HTML byte-for-byte against the legacy site for at
      least the most-used pages — diff catches missed `$c->` translations
      and missing config keys.
    - Run any cron jobs at least once in dev with the new bootstrap.
    - For Layout B with Docker: `cd /Users/baxi/Work/_docker_images/applications/<app>/ && ./run.sh <repo-path> latest`.

14. **Sanity audit.**
    - `grep -RIn '\\$c->' src/` — must return nothing.
    - `grep -RIn 'PC::' src/` — must return nothing.
    - `grep -RIn 'getSQL\\b\\|getSQLback\\|getSQLtoArray' src/` — must
      return nothing.
    - `grep -RIn 'D_pos\\|D_copy\\|D_replace\\|D_isint\\|D_gen_uuid' src/` —
      must return nothing.
    - `grep -RIn 'new PEAR' src/` — must return nothing.
    - `grep -RIn 'PEAR_Error\\|MDB2_Driver_Common\\|MDB2::' src/` — must
      return nothing (unless using `ConnectorMDB2`'s internals).
    - `grep -RIn 'np(\\$c,' src/` — must return nothing.
    - `grep -RIn 'udSCGI' src/` — must return nothing.
    - `grep -RIn '\\$LFuncParams\\[' src/_callbacks.php` — should return
      **nothing** (use `safe_array_value` instead). Tolerate matches
      inside string literals.
    - `php -l` every file under `src/` (and `src/_callbacks.php` in
      particular).

---

## 20. Common gotchas and known pitfalls

1. **`session_start()` ordering — usually NOT required.** The four
   recent migrations (greengo-bo, greengo-pp, ldu-bo, duvenbeck-v3) all
   omit `session_start()` from bootstrap and rely on `Render::start()`.
   Earlier guidance in this guide to "always call it yourself when
   `.params` has `session;…` rules" was overcautious. Only call it
   yourself when you have a custom session save handler that must be
   active before `Render::start()` runs (Redis / DB session), or when
   you need `$_SESSION` populated before any code in `__eisodos.php`
   itself reads from it.

2. **Readonly config keys.** udSCGI silently allowed query-string
   parameters to override config values. In Eisodos, prefix the key
   with `.` (e.g. `.ErrorLog=...`) or an attacker can redirect logs
   via `?errorlog=...`.

3. **`D_copy` is 1-indexed**, PHP `substr` is 0-indexed. When porting
   tight string code, this is the most common off-by-one source.

4. **`D_pos` returns 1 for "at position 0"**, 0 for "not found". The
   idiom `D_pos($x, $s) == 1` means "starts with"; the PHP equivalent
   is `strpos($s, $x) === 0`, **not** `strpos($s, $x) == 1`.

5. **PhpConsole removed.** Every `PC::debug(...)` call compile-fails.
   Replace with `Eisodos::$logger->debug(...)`. Remove
   `DEBUGGERSTORAGE`, `setPostponeStorage(...)`, `Connector::setPassword`
   references.

6. **`addToResponse` is the documented API, but `Render::Response` still
   exists.** The public `Eisodos::$render->Response` property is
   reachable for low-level recovery — every recent migration's catch
   block clears it as `Eisodos::$render->Response = '';` before writing
   an error message via `addToResponse`. Don't *write* to it from
   normal code; treat it as an exception-handler escape hatch only.
   Normal output always goes through `Eisodos::$templateEngine->addToResponse()`.

7. **PEAR `isError()` checks are gone.** `query()` returns `false` on
   error (when you opt out of exceptions); `executeDML` and friends
   throw by default. The most recent error message lives in the
   `LastSQLError` parameter.

8. **`MDB2_FETCHMODE_ASSOC` constants no longer exist.** `query(RT_*,
   ...)` already returns associative arrays.

9. **`udSession` DB-backed session driver is gone.** If
   `$useDBSession_ = true` was used, switch to a standard PHP session
   handler (Redis / DB / file) registered before `Render::start()`.

10. **`AppVersion` / active versions.** If the legacy app used
    `DevVersion`/`ActiveVersions` to fan out templates, verify your
    template list still contains the suffixed files. Most apps drop
    this feature.

11. **`utf8_to_latin2_hun` / `utf8_encode`.** udSCGI flipped encodings
    via in-template flags (`CONVERTUTF82LATIN=T` /
    `CONVERTLATIN2UTF8=T`). The SQL parser still respects them but
    most modern apps run UTF-8 end-to-end and can drop these. PHP 8.2+
    removed `utf8_encode`; replace with `mb_convert_encoding` if you
    must.

12. **Case sensitivity for `funcjob` strings.** Legacy code wrote
    `$LFuncParams["funcjob"] == "eq"`; the Eisodos rewrite typically
    moves to `===` and the template-side strings are case-sensitive.
    Make sure the template tokens and the callback comparisons agree.

13. **Cookie parameters.** `COOKIE_SAMESITE=None` requires
    `COOKIE_SECURE=T` (browsers reject otherwise). Set explicitly per
    domain via `COOKIE_SAMESITE.<domain>=...`.

14. **`Logout` parameter behaviour.** The canonical Eisodos way to drop
    a session is `Eisodos::$render->logout()`. Don't manually call
    `session_destroy()` + `header('Location:...')` unless your UX
    requires it — drp-v2's `_initcms.php` retains the manual approach
    for backwards compatibility, but new code should use `logout()`.

15. **Empty `LFuncParams['funcjob']` warnings.** Wrap every callback
    body in
    `if (!array_key_exists('funcjob', $LFuncParams)) { Eisodos::$logger->alert('No funcjob in callback!'); }`
    to surface bad templates immediately. (Or use the cleaner
    `safe_array_value` form — see §8.3.)

16. **Lazy DB connection silently swallows config errors.** If
    `__eisodos.php` only calls `registerDBConnector(...)` and never
    `->connect()`, a malformed `[Database]` section won't blow up at
    bootstrap — it surfaces on the first query, often deep inside a
    template parse. When debugging "the page renders but my SQL block
    returns nothing", check `Eisodos::$parameterHandler->getParam('LastSQLError')`
    and consider temporarily adding an eager `->connect()` to surface
    the real error early.

17. **Application name in `init([$dir, $name])` is decoupled from the
    directory.** It drives config file naming (`{env}-<name>.conf`),
    `$_applicationname`, log paths, and `CollectParamsToFile`. The
    duvenbeck-v3 backoffice uses `'greengo_pp'` despite living under
    `duvenbeck_v3/`. Pick the name to match the deployed config files,
    not the repo path — and verify both sides agree before going live.

18. **`__eisodos.php`'s `require_once '../vendor/autoload.php'` is
    relative to `src/`.** Layout B always nests bootstrap under `src/`
    and vendor at the repo root, so `..` resolves correctly. If you
    flatten the layout later (move `__eisodos.php` to the repo root),
    you must change this to `./vendor/autoload.php` or `__DIR__ .
    '/vendor/autoload.php'`.

19. **`_autoload.php`'s loader is one-level only.** `<appname>\Foo\Bar`
    will fail because the loader does `explode('\\', $class)[1] . '.php'`
    and ignores anything past segment 1. If you need subnamespaces,
    switch to Composer PSR-4 in `composer.json` instead of extending the
    SPL loader — it's not designed for nesting.

20. **`fe_fileHandler.php` silently returns empty body on errors.** The
    decoded JSON from the BO handler ships an `errorcode` field that's a
    *string* `"0"` on success. Check it via `!== '0'`, not `!= 0`
    (integer mismatch). All four migrations got this right; new code
    tends to get it wrong.

21. **`bo_pushGateway.php` ships with `CURLOPT_SSL_VERIFYPEER = false`
    and a hardcoded auth header in greengo-bo.** When porting,
    parameterise the auth header through config
    (`PushGateway.AuthHeader=...`) and remove the `VERIFYPEER` flag (or
    bring your own CA bundle) before reusing in any new app.

---

## 21. Glossary

| Term | Meaning |
| --- | --- |
| **udSCGI** | The legacy single-class framework (`_bone/includes/udSCGI.php`). |
| **udSDelphiPHP** | Companion procedural helpers (`D_pos`, `D_copy`, `D_replace`, `np`, `getSQL`, ...). |
| **Eisodos** | The replacement framework: `offsite-solutions/eisodos` (singletons, plugins). |
| **Connector** | Eisodos DB driver plugin implementing `DBConnectorInterface`. |
| **Parser** | Eisodos template-syntax plugin implementing `ParserInterface` (openTag/closeTag/parse/enabled). |
| **Default callback** | A user-defined PHP function (typically `callback_default` in `_callbacks.php`) invoked for every `[%…%]` and `<%FUNC%…%FUNC%>` block. |
| **`_init.php`** | Bootstrap file that initialises Eisodos and registers parsers/callbacks/connectors. |
| **`__eisodos.php`** | Optional sub-bootstrap that holds **only** framework wiring (greengo-v5 pattern). |
| **`<appname>.params`** | Per-app parameter filter file (rules: `exclude|session|cookie|encoded|protected|permanent|input|skip`). |
| **`[PreInclude]` / `[PostInclude]`** | INI sections that bring other config files into the current load. |
| **Active versions** | `ActiveVersions=v3,v2` enables suffix-based template fan-out. |
| **`<%SQL%…%SQL%>`** | Inline-SQL template parser provided by `eisodos-sql-parser`. |
| **`<%FUNC%…%FUNC%>`** | Long-form callback parser shipped with Eisodos core. |
| **`[%…%]`** | Short-form callback parser shipped with Eisodos core (also accepts `[#…#]`). |
| **`[:ID,default:]`** | Translator tag, replaced by `Eisodos::$translator`. |
| **`Render::start()`** | The real bootstrap: config load, parameter merge, session, error handler, output buffer. |
| **`Render::finish()` / `finishRaw()`** | Closes output buffer, persists session/lang IDs, disconnects DBs. |
| **MDB2 bridge** | `Eisodos\Connectors\ConnectorMDB2` lets the legacy MDB2 DSN strings keep working while you migrate. |
| **Tholos** | Optional CMS-like add-on (separate framework at `/Users/baxi/Work/_tholos/`). |

---

## 22. Optional: layering Tholos on top of Eisodos

Several production apps (greengo backoffice, ldu backoffice/frontend,
duvenbeck backoffice) layer the **Tholos** framework on top of Eisodos
for richer CMS-like features. Tholos lives at
`/Users/baxi/Work/_tholos/` with companion editor/builder packages at
`/Users/baxi/Work/_tholos_editor/Base` and
`/Users/baxi/Work/_tholos_builder/Base`.

**This migration guide is not about Tholos** — it targets the bare
Eisodos baseline. If your destination application also wants Tholos:

1. Complete the udSCGI → Eisodos migration first.
2. Add `tholos.conf` to `[PreInclude]` (see
   `_docker_images/applications/greengo/backoffice/config/config/tholos.conf`).
3. Bring in the Tholos composer dependency and its plugins.
4. Switch entry-point templates to the Tholos-driven ones.

The duvenbeck migration
(`/Users/baxi/Work/duvenbeck/3Development/frontend/trunk` →
`/Users/baxi/Work/duvenbeck_backoffice/portal`) is a useful **Tholos**
reference. Its source side is **already Tholos**, not udSCGI — so use
**drp** or **greengo** for udSCGI→Eisodos patterns and only consult the
duvenbeck pair for Tholos-specific questions.

---

## Appendix A — File-level diff anchors

Concrete file pairs to consult during a migration:

### Layout A reference (drp-v2 / greengo frontend)

| Legacy | Eisodos |
| --- | --- |
| `/Users/baxi/Work/drp/_init.php` | `/Users/baxi/Work/drp-v2/sites/dev/drp/_init.php` |
| `/Users/baxi/Work/drp/_initcms.php` | `/Users/baxi/Work/drp-v2/sites/dev/drp/_initcms.php` |
| `/Users/baxi/Work/drp/_callbacks.php` | `/Users/baxi/Work/drp-v2/sites/dev/drp/_callbacks.php` |
| `/Users/baxi/Work/drp/cms.php` | `/Users/baxi/Work/drp-v2/sites/dev/drp/cms.php` |
| `/Users/baxi/Work/drp/cms_reglist.php` | `/Users/baxi/Work/drp-v2/sites/dev/drp/cms_reglist.php` |
| `/Users/baxi/Work/drp/config/drp.conf` | `/Users/baxi/Work/drp-v2/sites/dev/drp/config/dev-drp.conf` + `common_pre_dev.conf` |
| `/Users/baxi/Work/drp/config/drp.params` | `/Users/baxi/Work/drp-v2/sites/dev/drp/config/drp.params` (unchanged) |
| `/Users/baxi/Work/greengo/.../portal-v3/hu/_init.php` | `/Users/baxi/Work/greengo/.../portal-v5/hu/_init.php` + `__eisodos.php` |
| `/Users/baxi/Work/greengo/.../portal-v3/hu/_callbacks.php` | `/Users/baxi/Work/greengo/.../portal-v5/hu/_callbacks.php` |

### Layout B reference (Tholos-layered, but Eisodos parts apply 1:1 — ignore the Tholos pieces)

The four migrations below are the canonical reference for the
`src/`-based Layout B and the four-file bootstrap split (§4.3–4.5). Use
them as a copy-paste source for `__eisodos.php`, `_init.php`,
`_autoload.php`, `_init_parameters.php`, the helper endpoints
(§14.4–14.6), and the `composer.json` / `composer.dev.json` /
`composer_update*.sh` skeletons. **Skip everything Tholos-related** in
these projects (`tholos/`, `tholos.conf`, `_run.php`, classes extending
`Tholos\TCustomModule`, anything under namespace use of `Tholos\*`) —
those don't belong in a pure-Eisodos target.

| Migration pair | Legacy (udSCGI/Tholos) | Migrated (Eisodos/Tholos) |
| --- | --- | --- |
| greengo backoffice | `/Users/baxi/Work/greengo/backoffice/3Development/portal/` | `/Users/baxi/Work/greengo/backoffice/3Development/v5/` |
| greengo partnerportal | `/Users/baxi/Work/greengo/partnerportal/3Development/portal/portal-v4/` | `/Users/baxi/Work/greengo/partnerportal/3Development/portal/portal-v5/` |
| ldu backoffice | `/Users/baxi/Work/ldu/backoffice-svn/portal/` | `/Users/baxi/Work/ldu/backoffice/v2/` |
| duvenbeck backoffice | `/Users/baxi/Work/duvenbeck_backoffice/portal/` | `/Users/baxi/Work/duvenbeck_v3/backoffice/` |

Concrete files to read first for the Eisodos parts:

- `<migrated>/composer.json` and `<migrated>/composer.dev.json` — §3.1.
- `<migrated>/composer_update.sh` and `<migrated>/composer_update_dev.sh` — §3.1.
- `<migrated>/src/__eisodos.php` — §4.3 canonical body.
- `<migrated>/src/_init.php` — §4.3 (three-liner).
- `<migrated>/src/_autoload.php` — §4.4.
- `<migrated>/src/_init_parameters.php` — §4.5.
- `<migrated>/src/_callbacks.php` — §8.3, §8.5.
- `<migrated>/src/ls_client.php` — §14.4.
- `<migrated>/src/bo_fileHandler.php` + `<migrated>/src/fe_fileHandler.php` — §14.5.
- `<migrated>/src/bo_mailSender.php`, `bo_pushGateway.php`, `bo_printDocument.php` — §14.6.
- `<migrated>/CLAUDE.md` (greengo-backoffice only) — team-facing summary
  of the same patterns this guide documents; good cross-check.

Production-ready configuration skeletons (Tholos-on-Eisodos but the
Eisodos parts apply 1:1):

- `/Users/baxi/Work/_docker_images/applications/greengo/backoffice/config/config/{greengo_bo.conf, global.conf, i18n.conf, tholos.conf, filehandler.conf, greengo_bo.params}`
- `/Users/baxi/Work/_docker_images/applications/greengo/frontend/config/...`
- `/Users/baxi/Work/_docker_images/applications/greengo/partnerportal/config/config/{greengo_pp.conf, ...}`
- `/Users/baxi/Work/_docker_images/applications/ldu/backoffice/config/config/{nop_bo.conf, global.conf, i18n.conf, tholos.conf, filehandler.conf, nop_bo.params}`
- `/Users/baxi/Work/_docker_images/applications/ldu/frontend/config/config/{nop_pp.conf, ...}`
- `/Users/baxi/Work/_docker_images/applications/langserver/config/...`

Base Docker images used by the above apps live under
`/Users/baxi/Work/_docker/{BaseImages, BasicApp, TholosApp,
TholosBuilder, TholosEditor, www}`. **`BasicApp`** is the right
starting point for a pure-Eisodos image; **`TholosApp`** adds Tholos on
top.

---

## Appendix B — DBConnectorInterface quick-reference

Full interface at
`/Users/baxi/Work/_eisodos/Base/src/Eisodos/Interfaces/DBConnectorInterface.php`.

```php
connected(): bool;
connect(string $section = 'Database', array $extra = [], bool $persistent = false): void;
disconnect(bool $force = false): void;

startTransaction(string|null $savePoint = null);
commit(): void;
rollback(string|null $savePoint = null): void;
inTransaction(): bool;

executeDML(string $sql, bool $throwOnError = true): int|bool;
executePreparedDML(string $sql, array $types, array &$data, bool $throwOnError = true): int|bool;
executePreparedDML2(string $sql, array $bound, bool $throwOnError = true): int|bool;

bind(array &$bound, string $name, string $type, string $value, string $inOut = 'IN');
bindParam(array &$bound, string $paramName, string $type);
executeStoredProcedure(string $procName, array $in, array &$out, bool $throwOnError = true, int $case = CASE_UPPER): bool;

query(int $resultTransform, string $sql, ?array &$result = null,
      ?array $options = [], ?string $exceptionMessage = ''): mixed;

getLastQueryColumns(): array;
getLastQueryTotalRows(): int;
getConnection(): mixed;

emptySQLField(mixed $value,    bool $isStr = true, int $maxLen = 0, string $exc = '', bool $comma = false, string $keyword = 'NULL'): string;
nullStr(mixed $value,          bool $isStr = true, int $maxLen = 0, string $exc = '', bool $comma = false): string;
toList(mixed $value,           bool $isStr = true, int $maxLen = 0, string $exc = '', bool $comma = false): string;
defaultStr(mixed $value,       bool $isStr = true, int $maxLen = 0, string $exc = '', bool $comma = false): string;
nullStrParam(string $param,    bool $isStr = true, int $maxLen = 0, string $exc = '', bool $comma = false): string;
defaultStrParam(string $param, bool $isStr = true, int $maxLen = 0, string $exc = '', bool $comma = false): string;

DBSyntax(): string;
```

Result-transformation constants (`Eisodos\Interfaces\DBConnectorInterface`):

```
RT_RAW                   = 0   // raw driver resultset
RT_FIRST_ROW             = 1   // [col=>val] (first row)
RT_FIRST_ROW_FIRST_COLUMN= 2   // scalar
RT_ALL_KEY_VALUE_PAIRS   = 3   // [col0=>col1,...]
RT_ALL_FIRST_COLUMN_VALUES = 4 // [col0,...]
RT_ALL_ROWS              = 5   // [[col=>val,...],...]
RT_ALL_ROWS_ASSOC        = 6   // [keyVal=>[col=>val,...],...]
RT_NO_ROWS               = 7   // no fetch
```

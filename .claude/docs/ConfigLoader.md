# ConfigLoader Class

Manages configuration file loading from INI or JSON formats with environment-based configuration support.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/ConfigLoader.php`

## Overview

The `ConfigLoader` class handles loading and parsing configuration files for the Eisodos framework. It supports both INI and JSON file formats, environment-based configuration, version management, and configuration caching.

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `CONFIG_TYPE_INI` | `0` | INI file format |
| `CONFIG_TYPE_JSON` | `1` | JSON file format |

## Methods

### init(array $options_): void

Initializes the configuration loader.

**Parameters:**
- `$options_` - Array containing:
  - `configPath` - Configuration file's path (default: `./config`)
  - `configType` - Format of config files (`CONFIG_TYPE_INI` or `CONFIG_TYPE_JSON`)
  - `environment` - Environment variable prefix
  - `overwrites` - Array of configuration value overwrites

**Example:**
```php
Eisodos::$configLoader->init([
    'configPath' => '/var/www/app/config',
    'configType' => ConfigLoader::CONFIG_TYPE_INI,
    'environment' => 'production',
    'overwrites' => [
        'DEBUG' => 'F',
        'CACHE_ENABLED' => 'T'
    ]
]);
```

### importConfigSection(string \$section_, string \$configFile_ = '', bool $addToParameters_ = true): array

Loads a configuration file's section into the parameters array.

**Parameters:**
- `$section_` - Section name (e.g., `[SECTION_NAME]` in INI files)
- `$configFile_` - Configuration file's name (optional)
- `$addToParameters_` - If true, adds keys to global parameter list

**Returns:** Array of parsed key-value pairs

**Example:**
```php
// Load FileHandler configuration dynamically
Eisodos::$configLoader->importConfigSection(
    'FileHandler',
    Eisodos::$parameterHandler->getParam('FileHandler.configFile')
);
```

### initVersioning(string $developerVersion_): void

Initializes version handling for the template engine.

**Parameters:**
- `$developerVersion_` - Developer version identifier

### loadParameterFilters(array &$parameterFilters_): void

Loads parameter filter rules from the `.params` file.

**Parameters:**
- `$parameterFilters_` - Reference to array that will be populated with filter lines

### getActiveVersions(): array

Returns the list of active version prefixes.

**Returns:** Array of version prefixes (e.g., `['v3.', 'v2.', '']`)

## Real-World Configuration Structure

### Main Application Configuration

The main configuration file uses PreInclude/PostInclude for modular configuration:

```ini
# app.conf - Main application config
[Env]

[PreInclude]
1=/app/dist/vendor/offsite-solutions/eisodos/src/Eisodos/config/common_pre_dev.conf:Config
2=global.conf:Config
3=i18n.conf:Config
4=tholos.conf:Config
5=filehandler.conf:Config

[PostInclude]
1=version.conf:Version
2=custom.conf:Config

[Database]
connectMode=
username=dbuser
password=dbpassword
connection=DEV
characterSet=
autoCommit=false
connectSQL=ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS';ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS';ALTER SESSION SET NLS_SORT='hungarian';
caseQuery=lower
caseStoredProcedure=upper

[Config]
MainAddress=https://app.com:10344/
TemplateDir=/app/dist/src/assets/templates/

# Application environment
APP_HOMEPAGE_URL=/DASHBOARD/index/
APP_LOGOUT_URL=/COR_LOGIN/logout/
APP_ENV=DEV
APP_ENV_COLOR=purple
APP_SEARCH_URL=/SEARCH/index/?SEARCH_TERM=

# Cookie settings
COOKIE_DOMAIN=app.offsite-solutions.com
COOKIE_SECURE=F
COOKIE_PATH=/
COOKIE_HTTPONLY=T
COOKIE_SAMESITE=

# Microservices
Frontend_URL=https://frontend.com
JAVA_MS_COM_URL=http://ms.internal.com:18088

# Error handling (readonly with . prefix)
.ErrorLog=/var/log/application/$_applicationName-error.log
ErrorMailTo=alert@offsite-solutions.com
ErrorOutput=File,Mail
ErrorMailFrom=error-report@example.com

# Third-party integrations
FileHandler.URL=https://localhost.com:10344/bo_fileHandler.php
FileUploadPath=/app/files/files/tmp
```

### Global Settings Configuration

Shared settings across all environments:

```ini
# global.conf
[Config]
# Default cookie settings
COOKIE_PATH=/
COOKIE_SECURE=T
COOKIE_HTTPONLY=T
COOKIE_SAMESITE=

# SMTP settings
SMTP.host=smtp.email.com
SMTP.port=587
SMTP.username=username
SMTP.password=password
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
TitleConcat=F

# Language settings
Langs=HU,EN
DefLang=EN
CollectLangIDs=F
ShowLangids=F
Lang=HU
LangIDFile=/app/dist/i18n/generated.txt
UserLangIDFile=/app/dist/i18n/translated.txt
ShowMissingTemplate=F
IncludeStatistic=F

# FileHandler access control
FileHandler.AllowDeny.FE=Allow
FileHandler.Allow.FE=
FileHandler.Deny.FE=

FileHandler.AllowDeny.BO=Deny
FileHandler.Allow.BO=
FileHandler.Deny.BO=

FileHandler.AllowDeny.MOBILE=Allow
FileHandler.Allow.MOBILE=OBJECT:r,OBJECT2:w
FileHandler.Deny.MOBILE=

FileHandler.ObjectMapping=small_object=OBJECT,large_object=OBJECT2
```

### Internationalization Configuration

Language and date format settings per locale:

```ini
# i18n.conf
[Config]
# Database date formats
OCI8.DATE.FORMAT=Y-m-d H:i:s
OCI8.DATE.SPFORMAT=Y-m-d
OCI8.DATETIME.FORMAT=Y-m-d H:i:s
OCI8.DATETIME.SPFORMAT=Y-m-d H:i:s
OCI8.NUMBER.DECIMAL_SEPARATOR=.

PGSQL.DATE.FORMAT=Y-m-d
PGSQL.DATE.SPFORMAT=Y-m-d
PGSQL.DATETIME.FORMAT=Y-m-d H:i:s
PGSQL.NUMBER.DECIMAL_SEPARATOR=.

JSON.DATE.FORMAT=Y-m-d
JSON.DATE-TIME.FORMAT=Y-m-d\TH:i:s
JSON.NUMBER.DECIMAL_SEPARATOR=.

TotalRowCountSQL=COUNT(1) OVER () AS totalrowcount

# Hungarian locale
HU.DATEFORMAT=YYYY.MM.DD.
HU.DATETIMEFORMAT=YYYY.MM.DD. hh24:mi:ss
HU.DATETIMEHMFORMAT=YYYY.MM.DD. hh24:mi
HU.TIMEFORMAT=hh24:mi
HU.JSDATEFORMAT=yyyy.MM.dd.
HU.JSDATETIMEFORMAT=yyyy.MM.dd. HH:mm:ss
HU.PHPDATEFORMAT=Y.m.d.
HU.PHPDATETIMEFORMAT=Y.m.d. H:i:s
HU.NLS_SORT=HUNGARIAN

# English locale
EN.DATEFORMAT=DD-MM-YYYY
EN.DATETIMEFORMAT=DD-MM-YYYY hh24:mi:ss
EN.JSDATEFORMAT=dd-MM-yyyy
EN.PHPDATEFORMAT=d-m-Y
EN.PHPDATETIMEFORMAT=d-m-Y H:i:s
EN.NLS_SORT=GERMAN

# Universal format (ISO)
UNIVERSAL.DATEFORMAT=YYYY-MM-DD
UNIVERSAL.DATETIMEFORMAT=YYYY-MM-DD hh24:mi:ss
UNIVERSAL.JSDATEFORMAT=yyyy-MM-dd
UNIVERSAL.PHPDATEFORMAT=Y-m-d
UNIVERSAL.PHPDATETIMEFORMAT=Y-m-d H:i:s

NULLParameter.False=false
NULLParameter.True=true
```

### FileHandler Object Configuration

Per-object file storage settings:

```ini
# filehandler.conf
[FileHandler]
FileHandler.FILE.BasePath=/app/files/

FileHandler.OBJECT.BasePath=/app/files/files/filehandler/OBJECT
FileHandler.OBJECT.ArchivePath=/app/files/files/archive/OBJECT
FileHandler.OBJECT.DateMask=Y/m/d
FileHandler.OBJECT.Extras={"thumbnail":{"width":"200","rotation":"auto"}}
```

### Tholos MVC Framework Configuration

Settings for the Tholos MVC integration:

```ini
# tholos.conf
[Config]
Tholos.EnableRoleManager=true
Tholos.debugLevel=error,debug
Tholos.JSDebugLevel=debug
Tholos.BoolFalse=n,0,false,f
Tholos.BoolTrue=*
Tholos.CacheMethod=file
Tholos.mPDF={"tempDir":"/var/tmp/app","mode":"A4","setAutoTopMargin":"stretch","setAutoBottomMargin":"stretch"}
Tholos.TGrid.Scrollable=false
Tholos.TGrid.ScrollableY=false

Tholos.debugToFile=/var/log/application/app_debug.log
Tholos.ApplicationCache=/app/dist/tholos/local/dist/TholosApplicationCache.php
Tholos.ApplicationCacheDir=/app/dist/tholos/local/dist/
Tholos.TemplateDir=/app/dist/vendor/offsite-solutions/tholos/assets/templates/
Tholos.CacheDir=/app/cache/
TholosAssetsDir=/vendor/offsite-solutions/tholos/assets
TholosHelpFile=/help/app.html

Tholos.SessionExpiredURL=/COR_USERS/logout/

# HTTP request headers for microservice calls
Tholos.HTTPRequestHeader=Content-Type: application/json\nX-Auth-Referrer: $_applicationname\nX-Tholos-Language: $LANG

# Access logging with parameter substitution
Tholos.AccessLog=/var/log/application/tholos.access.log
Tholos.AccessLog.Format=[%THOLOS_SESSIONID] session=%_SESSIONID~='-'; login=%LOGINID~='-'; start=%Tholos_App_StartDate init=%Tholos_App_InitTime end=%Tholos_App_FinishTime path=/%THOLOS_ROUTE/%THOLOS_ACTION/%THOLOS_PARTIAL~='*';/ session_id=%P_SESSION_ID
```

## Parameter Filters File (.params)

The `.params` file defines how input parameters are handled:

```
# Exclude system parameters from user input
exclude;SESSIONID
exclude;EMPTY
exclude;CGI
exclude;MAINADDRESS
exclude;MAINADDRESSCGI
exclude;LASTACTIVE
exclude;RELOAD
exclude;CRC
exclude;NOSESSION
exclude;RURL
exclude;USID
exclude;ALLOWADMIN
exclude;LANGIDFILE

# Session-stored parameters
session;LANG
session;SESSION_*

# Cookie-stored parameters
cookie;EDITOR
cookie;LANGEDIT
cookie;DEVVERSION

# Encoded (base64) parameters
encoded;CSID
encoded;REDIRECT

# Input validation with regex and error handling
input;P_LANGUAGE;/^(HU|EN|CZ)$/i;HU;Nyelvhiba
session;LANG;/^(HU|EN|CZ)$/i;HU;Nyelvhiba
```

**Filter Commands:**

| Command | Description |
|---------|-------------|
| `exclude` | Reject parameter (not loaded from input) |
| `session` | Store in session |
| `cookie` | Store as cookie |
| `permanent` | Cookie with expiration (days): `permanent;name=30` |
| `protected` | Requires CSID verification |
| `encoded` | Base64 decode on input |
| `input` | Accept with optional validation |
| `skip` | Don't load from session (wildcards supported) |

**Filter Format:**
```
command;parametername;type;typeerror;errorlog
```

- `type` - `text`, `numeric`, or regex pattern `/^pattern$/`
- `typeerror` - Redirect URL on validation failure
- `errorlog` - Error message for logging

## Environment Detection

The environment is determined in the following order:

1. Content of `{configPath}/environment` file
2. `environment` option in init parameters
3. `{applicationName}_ENVIRONMENT` environment variable
4. Environment variable `EISODOS_CONFIG_PATH` can override config path

## Configuration Loading Order

1. **Env** section - Environment variables (putenv)
2. **PreInclude** section - Files to load before main config (numbered for ordering)
3. **Config** section - Main configuration values
4. **Versions** section - Template versioning setup
5. Version-specific sections (in reverse order)
6. **PostInclude** section - Files to load after main config
7. Constructor overwrites

## Configuration Best Practices

1. **Use PreInclude for shared settings** - Common configs, i18n, framework settings
2. **Use PostInclude for environment overrides** - version.conf, custom.conf
3. **Namespace hierarchical settings** - `SMTP.host`, `FileHandler.ObjectName.BasePath`
4. **Use readonly prefix (.)** - For error logs and sensitive paths: `.ErrorLog`
5. **Separate concerns** - One file per domain (filehandler.conf, i18n.conf, tholos.conf)
6. **Use wildcards in .params** - `SESSION_*` for pattern matching

## Directory Structure

```
config/
├── myapp.conf              # Main configuration (references others)
├── myapp.params            # Parameter filters
├── global.conf             # Shared global settings
├── i18n.conf               # Internationalization formats
├── tholos.conf             # MVC framework settings
├── filehandler.conf        # File storage configuration
├── version.conf            # Version information (PostInclude)
├── custom.conf             # Environment-specific overrides (PostInclude)
└── environment             # Environment indicator file (DEV/PROD/etc.)
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [ParameterHandler](ParameterHandler.md) - Parameter management
- [TemplateEngine](TemplateEngine.md) - Template processing

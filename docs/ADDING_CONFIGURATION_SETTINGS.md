# Adding a Configuration Setting

This document describes every code change required when adding a setting to
`config/pialert.conf`. Pi.Alert configuration is Python syntax, but it is
treated as untrusted input: it is parsed and validated without execution.

Do not add a setting in only one place. A setting written by the web editor
must be declared in the PHP allowlist, rendered in the configuration template,
and accepted by the Python validator.

## Checklist

Use an uppercase name such as `EXAMPLE_SETTING`. For every new setting,
complete the applicable steps below.

1. Add its default to both `config/pialert.conf` and the standalone
   `config/pialert.example.conf`.
2. Add a user-facing row to `docs/PIALERT_CONF.md` when users may configure it.
3. Add the key and its type to `get_config_schema()` in
   `front/php/server/files.php`.
4. Add the rendered assignment to the `$config_template` in
   `SaveConfigFile()` in the same PHP file.
5. Add the key to the corresponding allowlist in
   `back/config_validation.py`.
6. Add or extend regression tests in `back/test_config_validation.py`.
7. Run the validation and syntax checks listed at the end of this document.

The PHP and Python allowlists must always contain the same setting names and
types. The Python validator rejects unknown keys, duplicate keys, missing
required keys, invalid AST nodes, and invalid values.

The example must contain every key in `ALL_KEYS` exactly once, but it is never
read by the updater, installer, backend, or WebGUI. It is a standalone file for
users and can be copied to `pialert.conf` manually.

The updater does not depend on section headings or setting order. It preserves
every existing assignment and adds missing keys from the internal defaults in
`install/migrate_pialert_config.py`. Add every new setting there as well.
Deprecated keys are removed by their exact assignment name; never remove a
range between two comment headings.

## Choose the Correct Type

### Boolean

Use a boolean only for a true/false setting.

```python
EXAMPLE_ENABLED = False
```

* PHP: add the key to the `bool` group in `get_config_schema()`.
* PHP template: render it with `convert_bool(...)`.
* Python: add it to `BOOLEAN_KEYS`.

Do not accept `1`, `0`, `yes`, `no`, strings, or arbitrary Python
expressions. Only Python `True` and `False` are valid.

### Integer

Use an integer for counters, limits, durations, and ports.

```python
EXAMPLE_PORT = 1234
```

* PHP: add the key to the `int` group.
* PHP template: emit only the validated decimal integer, never quote it.
* Python: add `'EXAMPLE_PORT': (minimum, maximum)` to `INTEGER_RULES`.

Select meaningful bounds. Ports must use `1..65535`; an integer setting must
not accidentally accept booleans, floats, exponent notation, or hexadecimal
values.

### String

Use a string for text, paths, host names, URLs, identifiers, and secrets.

```python
EXAMPLE_SERVER = 'example.invalid'
```

* PHP: add the key to the `string` group.
* PHP template: quote the value as a Python string literal and escape it with
  `escape_python_config_string()`; never concatenate an unescaped request
  value into the template.
* Python: add the key to `STRING_KEYS`.

Strings must not introduce a new Python statement. The Python validator
rejects NUL bytes and line breaks. Do not trim, case-normalize, whitelist
characters, or otherwise transform secrets such as passwords, API keys, and
tokens.

### List of strings

Use a list only when the setting is genuinely a collection.

```python
EXAMPLE_IGNORE_LIST = ['one', 'two']
```

* PHP: add the key to the `list` group and parse every item as data, not as
  a Python expression.
* PHP template: serialize every item as a separately quoted Python string.
* Python: add the key to `LIST_KEYS`.
* Python: add a dedicated item validator in `validate_values()` when items
  have a domain-specific format, for example MAC addresses, IP addresses, or
  interface names.

Never accept a prebuilt Python list from the request. Enforce item count and
item length limits.

### Special syntax

Only use `SPECIAL_KEYS` when a setting needs a deliberately restricted
syntax, such as `DHCP_SERVER_ADDRESS` or `SCAN_SUBNETS`. Implement matching
PHP parsing/rendering and Python AST/type validation together. Do not allow
arbitrary expressions as a shortcut.

The only currently allowed non-literal expressions are the exact compatibility
expressions for `DB_PATH` and `LOG_PATH`. New compatibility expressions
require an explicit, narrowly scoped AST check in `_literal()`.

## Required PHP Changes

`front/php/server/files.php` is the web editor's configuration write path.

1. Add the setting to `get_config_schema()` with the correct type. Required
   settings must be present in a saved editor document. If a setting must be
   optional for old installations, set `required` to `false`, provide a
   safe default, and add the same default to Python's `OPTIONAL_DEFAULTS`.
2. Add one assignment to `$config_template`, placed in the appropriate
   section. Keep the generated file valid Python syntax.
3. Do not bypass `validate_and_replace_pialert_config()`. It writes a
   temporary file, invokes the AST validator, creates a backup, and atomically
   replaces the active configuration only after validation succeeds.
4. If a dedicated endpoint modifies this setting, route it through the same
   helper. Do not use `file_put_contents(... pialert.conf ...)` directly.
5. Preserve masked secret values exactly as the existing secret handling does;
   do not log the value or include it in an HTTP error.

The raw configuration editor is intentionally protected by
`assert_config_editor_keys()`. Adding a key to the template without adding it
to the schema makes editor saves fail.

## Required Python Changes

`back/config_validation.py` is the source of truth for safe loading.

1. Add the key to exactly one of `BOOLEAN_KEYS`, `INTEGER_RULES`,
   `STRING_KEYS`, `LIST_KEYS`, or `SPECIAL_KEYS`.
2. Add type-specific bounds or item validation in `validate_values()`.
3. If the setting is optional, add its safe default to `OPTIONAL_DEFAULTS`.
4. Do not add it to `ALL_KEYS` manually; that set is composed from the
   typed allowlists.

The independent entry points `back/pialert.py`,
`back/pialert_reporting_test.py`, and `back/pialert_tools.py` already use
the shared loader. No per-script validation code is normally needed for a new
generic setting.

If a setting is a secret used by `pialert.py` or
`pialert_reporting_test.py`, keep the existing order intact:

1. structurally load the configuration;
2. run `recover_sensitive_config_values()`;
3. call `validate_loaded_config()`.

Only add a secret name to an existing recovery list when it requires the same
legacy backslash compatibility. Secrets are opaque strings: do not trim,
normalize, or print them.

## Tests

Extend `back/test_config_validation.py` with at least:

* one valid configuration case containing the new setting;
* invalid type cases;
* invalid range cases for integers;
* invalid item cases for lists;
* an AST payload such as a function call instead of a literal;
* a default-value case when the setting is optional.

Run these checks from the Pi.Alert root:

```bash
php -l front/php/server/files.php
python3 -m py_compile back/config_validation.py back/validate_pialert_config.py
python3 -m unittest back/test_config_validation.py
python3 back/validate_pialert_config.py \\
  config/pialert.conf --expected-pialert-path /opt/pialert
python3 back/validate_pialert_config.py \\
  config/pialert.example.conf --expected-pialert-path /opt/pialert
```

Finally, save a harmless value through the web editor and confirm that the
generated `config/pialert.conf` contains the expected Python literal. Never
use production secrets in tests or paste them into logs, issue reports, or
diffs.

# Adding a Configuration Setting

This document describes every code change required when adding a setting to
`config/pialert.conf`. Pi.Alert configuration uses a deliberately restricted
subset of Python syntax. It is always parsed as untrusted data and is never
executed.

The Python schema and validator in `back/config_validation.py` are the source
of truth for both the backend and the raw WebEditor. The WebEditor must not
maintain a second PHP type map or rebuild values with regular expressions.

## Checklist

Use an uppercase name such as `EXAMPLE_SETTING`. For every new setting:

1. Add its default to the installation configuration and to the standalone
   `config/pialert.example.conf`.
2. Add the same default to `DEFAULT_ASSIGNMENTS_SOURCE` in
   `install/migrate_pialert_config.py`.
3. Add a user-facing row to `docs/PIALERT_CONF.md` when users may configure it.
4. Add the key to exactly one typed group in `back/config_validation.py`.
5. Add bounds or a domain-specific value validator.
6. If the setting is optional for old installations, add its safe default to
   `OPTIONAL_DEFAULTS`.
7. If it is a secret displayed by the WebEditor, add it to
   `MASKED_SECRET_KEYS`.
8. Add or extend regression tests in `back/test_config_validation.py` and,
   when editor behavior is relevant, `back/test_config_editor.py`.
9. Run all checks listed at the end of this document.

The example configuration must contain every key in `ALL_KEYS` exactly once.
It is never read, modified, backed up, migrated, or deleted by the updater,
backend, or WebGUI. Users may manually copy it to `pialert.conf`.

The updater does not depend on section headings or key order. It preserves
existing assignments and adds missing keys from its internal defaults.
Deprecated settings are removed by exact assignment name, never by deleting a
range between comment headings.

## Supported Configuration Syntax

The loader accepts comments, blank lines, and one assignment per known key.
Values are limited to the types documented below. General expressions,
function calls, imports, attribute access, comprehensions, dictionaries,
tuples, f-strings, and string concatenation are rejected.

The complete file is limited to 524,288 bytes (512 KiB), measured before UTF-8
decoding. Invalid UTF-8 is rejected.

### Boolean

Use a boolean only for a true/false setting:

```python
EXAMPLE_ENABLED = False
```

Add the key to `BOOLEAN_KEYS`. Only the Python literals `True` and `False`
are valid. Do not accept numbers or strings such as `1`, `0`, `yes`, or
`no`.

### Integer

Use an integer for counters, limits, durations, and ports:

```python
EXAMPLE_PORT = 1234
```

Add the key and meaningful inclusive bounds to `INTEGER_RULES`. Ports use
`1..65535`. The validator must not accidentally accept booleans, floats,
exponent notation, or hexadecimal notation.

### String

Use a string for text, paths, host names, URLs, identifiers, and secrets:

```python
EXAMPLE_SERVER = 'example.invalid'
```

Add the key to `STRING_KEYS` and add narrower length or content checks where
the domain requires them. Strings may not contain NUL bytes or line breaks.
Do not trim, case-normalize, or otherwise transform opaque secrets.

The WebEditor preserves a valid string literal as data. Quotes, backslashes,
commas, equal signs, hash characters, and Unicode must survive a complete
editor save/load round trip with the same runtime value.

### List of strings

Use a list only for a genuine collection:

```python
EXAMPLE_LIST = ['one', 'two']
```

Add the key to `LIST_KEYS`. Use `require_string_list()` and provide a
specific item validator for domain formats such as IP addresses, MAC addresses,
Telegram destinations, or interface names. Enforce item-count and item-length
limits. Every element remains an independent string; a comma inside an element
must never be treated as a separator by PHP.

### Special syntax

Use `SPECIAL_KEYS` only when a setting intentionally accepts more than one
type or has a restricted grammar. Examples are `DHCP_SERVER_ADDRESS` and
`SCAN_SUBNETS`.

Implement the grammar once in `back/config_validation.py` and reuse the same
parser or argument builder at runtime. The WebEditor invokes this validator and
therefore does not need matching PHP parsing code.

The only allowed non-literal expressions are the exact compatibility
expressions for `DB_PATH` and `LOG_PATH`. Any new compatibility expression
requires a narrowly scoped AST check in `_literal()`.

Duplicate keys are normally invalid. The sole editor-side compatibility
exception is `SCAN_SUBNETS`: if the submitted editor text contains several
active assignments, `back/config_editor.py` keeps the last semantically valid
assignment from that submitted text and removes the others before normal
validation. It must never take this value from the pre-save backup. Do not
copy or generalize this exception for new settings.

## WebEditor and Atomic Storage

`front/php/server/config_file.php` contains the shared PHP filesystem and
editor helpers. `front/php/server/files.php` only coordinates the authenticated
request.

A normal new setting requires no PHP schema or output-template change. The
editor submits the complete file to `back/config_editor.py`, which uses the
same non-executing parser as the backend. Do not reintroduce `parse_ini_string()`,
line-oriented value regular expressions, comma splitting, or a PHP
configuration template.

Every configuration write must use
`validate_and_replace_pialert_config()`. It validates a temporary candidate
and atomically replaces the active file. Dedicated endpoints that modify one
setting must use the same helper rather than writing `pialert.conf` directly.

The raw editor save has an additional mandatory sequence:

1. acquire the configuration write lock;
2. create and verify a byte-identical backup of the current
   `pialert.conf` as `pialert-prev.bak`, replacing an existing backup;
3. abort without changing the active file if any backup step fails;
4. restore unchanged masked secrets only from that newly created backup;
5. validate the complete candidate with the shared Python validator;
6. atomically replace the active file while still holding the same lock.

A stale backup must never be used as a fallback for masked values.

## Secrets

Add every secret shown by the WebEditor to `MASKED_SECRET_KEYS` in
`back/config_validation.py`. The mask shown in the browser is not a valid
replacement secret. When the submitted value exactly matches the mask derived
from the newly verified backup, `back/config_editor.py` restores the real
value before validation and storage.

New secret values are serialized as Python string data. Neither real values nor
masks may appear in HTTP errors, journals, server logs, test output, or diffs.

If a secret is consumed by `pialert.py` or `pialert_reporting_test.py` and
requires the legacy opaque-backslash compatibility layer, add it to the
corresponding runtime recovery list. Preserve this order:

1. structurally parse the configuration;
2. recover the legacy opaque value;
3. run typed validation.

## Tests

For every new setting, add at least:

- a valid value;
- invalid type cases;
- invalid range or length cases;
- invalid list-item cases where applicable;
- an AST payload such as a function call;
- an optional-default case when applicable;
- a WebEditor round trip when the value can be changed there;
- masked-secret retention and replacement cases for secrets.

Run from the Pi.Alert root:

```bash
php -l front/php/server/config_file.php
php -l front/php/server/files.php
python3 -m py_compile \
  back/config_validation.py back/config_editor.py back/validate_pialert_config.py
python3 -m unittest back/test_config_validation.py back/test_config_editor.py
php tests/php/test_config_editor_roundtrip.php
python3 back/validate_pialert_config.py \
  config/pialert.conf --expected-pialert-path /opt/pialert
python3 back/validate_pialert_config.py \
  config/pialert.example.conf --expected-pialert-path /opt/pialert
```

Automated editor tests must use temporary files and must never modify the
active `config/pialert.conf`. Never use production secrets in fixtures or
paste them into logs, issue reports, or diffs.

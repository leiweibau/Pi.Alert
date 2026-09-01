#!/usr/bin/env python3
"""Prepare and mask Pi.Alert configuration files without executing them."""
from __future__ import print_function

import argparse
import ast
import json
import os
import re
import sys
import warnings

from config_validation import (
    DEPRECATED_KEYS,
    MASKED_SECRET_KEYS,
    MAX_CONFIG_BYTES,
    OPTIONAL_DEFAULTS,
    ConfigValidationError,
    build_arpscan_arguments,
    load_pialert_config_source,
    validate_loaded_config,
)


def read_source(path):
    try:
        with open(path, 'rb') as handle:
            data = handle.read(MAX_CONFIG_BYTES + 1)
    except OSError as exc:
        raise ConfigValidationError('configuration file is not readable') from exc
    if len(data) > MAX_CONFIG_BYTES:
        raise ConfigValidationError('configuration file is too large')
    try:
        return data.decode('utf-8')
    except UnicodeDecodeError as exc:
        raise ConfigValidationError('configuration file is not valid UTF-8') from exc


def _assignment_nodes(source, path='<configuration>'):
    try:
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', SyntaxWarning)
            tree = ast.parse(source, filename=path, mode='exec')
    except SyntaxError as exc:
        raise ConfigValidationError('configuration file has invalid syntax') from exc
    result = {}
    for statement in tree.body:
        if (isinstance(statement, ast.Assign) and len(statement.targets) == 1 and
                isinstance(statement.targets[0], ast.Name)):
            result[statement.targets[0].id] = statement
    return result


def _byte_line_offsets(source):
    offsets = []
    total = 0
    for line in source.splitlines(keepends=True):
        offsets.append(total)
        total += len(line.encode('utf-8'))
    if not offsets or source.endswith(('\n', '\r')):
        offsets.append(total)
    return offsets


def _node_byte_span(source, node):
    offsets = _byte_line_offsets(source)
    try:
        start = offsets[node.lineno - 1] + node.col_offset
        end = offsets[node.end_lineno - 1] + node.end_col_offset
    except (AttributeError, IndexError) as exc:
        raise ConfigValidationError('configuration location is invalid') from exc
    return start, end


def _replace_byte_spans(source, replacements):
    data = source.encode('utf-8')
    last_start = len(data) + 1
    for start, end, replacement in sorted(replacements, reverse=True):
        if start < 0 or end < start or end > len(data) or end > last_start:
            raise ConfigValidationError('configuration replacements overlap')
        data = data[:start] + replacement.encode('utf-8') + data[end:]
        last_start = start
    if len(data) > MAX_CONFIG_BYTES:
        raise ConfigValidationError('configuration file is too large')
    return data.decode('utf-8')


def _standalone_statement_line_span(source, statement):
    """Return complete byte-aligned lines for a standalone assignment."""
    lines = source.splitlines(keepends=True)
    try:
        first_line = lines[statement.lineno - 1]
        last_line = lines[statement.end_lineno - 1]
    except (AttributeError, IndexError) as exc:
        raise ConfigValidationError('configuration location is invalid') from exc

    first_prefix = first_line.encode('utf-8')[:statement.col_offset].decode('utf-8')
    last_suffix = last_line.encode('utf-8')[statement.end_col_offset:].decode('utf-8')
    if first_prefix.strip() or (last_suffix.strip() and
                                not last_suffix.lstrip().startswith('#')):
        raise ConfigValidationError(
            'duplicate SCAN_SUBNETS assignments must use separate lines')

    offsets = _byte_line_offsets(source)
    start = offsets[statement.lineno - 1]
    try:
        end = offsets[statement.end_lineno]
    except IndexError:
        end = len(source.encode('utf-8'))
    return start, end


def normalize_editor_scan_subnets(source, path='<editor>'):
    """Keep only the last valid submitted SCAN_SUBNETS assignment.

    This is intentionally an editor-only compatibility rule. The normal
    configuration loader remains strict and rejects duplicate assignments.
    """
    try:
        with warnings.catch_warnings():
            warnings.simplefilter('ignore', SyntaxWarning)
            tree = ast.parse(source, filename=path, mode='exec')
    except SyntaxError as exc:
        raise ConfigValidationError('configuration file has invalid syntax') from exc

    assignments = []
    for statement in tree.body:
        if (isinstance(statement, ast.Assign) and len(statement.targets) == 1 and
                isinstance(statement.targets[0], ast.Name) and
                statement.targets[0].id == 'SCAN_SUBNETS'):
            assignments.append(statement)

    if len(assignments) <= 1:
        return source, 0

    last_valid = None
    for statement in assignments:
        try:
            value = ast.literal_eval(statement.value)
            build_arpscan_arguments(value)
        except (ConfigValidationError, TypeError, ValueError):
            continue
        last_valid = statement

    if last_valid is None:
        raise ConfigValidationError(
            'SCAN_SUBNETS has no valid submitted assignment')

    replacements = []
    spans = []
    for statement in assignments:
        span = _standalone_statement_line_span(source, statement)
        if any(span[0] < existing[1] and span[1] > existing[0]
               for existing in spans):
            raise ConfigValidationError(
                'duplicate SCAN_SUBNETS assignments must use separate lines')
        spans.append(span)
        if statement is not last_valid:
            replacements.append((span[0], span[1], ''))

    return _replace_byte_spans(source, replacements), len(replacements)


def _editor_values(source, path='<configuration>'):
    values = load_pialert_config_source(source, path, validate=False)
    nodes = _assignment_nodes(source, path)
    return values, nodes


def mask_secret(value):
    if not isinstance(value, str) or value == '':
        return value
    if len(value) <= 2:
        return '*' * len(value)
    return value[0] + ('*' * (len(value) - 2)) + value[-1]


def mask_config_source(source, path='<configuration>'):
    values, nodes = _editor_values(source, path)
    replacements = []
    for key in MASKED_SECRET_KEYS:
        value = values.get(key)
        if not isinstance(value, str) or value == '' or key not in nodes:
            continue
        start, end = _node_byte_span(source, nodes[key].value)
        replacements.append((start, end, repr(mask_secret(value))))
    return _replace_byte_spans(source, replacements)


def _remove_deprecated_assignments(source):
    if not DEPRECATED_KEYS:
        return source
    pattern = re.compile(
        r'^[ \t]*#?[ \t]*(?:' + '|'.join(re.escape(key) for key in DEPRECATED_KEYS) +
        r')[ \t]*=.*(?:\r?\n|$)', re.MULTILINE)
    return pattern.sub('', source)


def prepare_editor_candidate(submitted_source, backup_source,
                             expected_pialert_path,
                             submitted_path='<editor>', backup_path='<backup>'):
    backup_values, _ = _editor_values(backup_source, backup_path)
    validate_loaded_config(backup_values, expected_pialert_path)

    submitted_source, removed_scan_assignments = normalize_editor_scan_subnets(
        submitted_source, submitted_path)
    submitted_values, submitted_nodes = _editor_values(
        submitted_source, submitted_path)
    replacements = []
    restored_values = dict(submitted_values)
    changed_secrets = []

    for key in MASKED_SECRET_KEYS:
        if key not in submitted_nodes:
            continue
        old_value = backup_values.get(key, OPTIONAL_DEFAULTS.get(key, ''))
        new_value = submitted_values.get(key)
        if isinstance(old_value, str) and new_value == mask_secret(old_value):
            restored_values[key] = old_value
        if isinstance(restored_values.get(key), str):
            start, end = _node_byte_span(
                submitted_source, submitted_nodes[key].value)
            replacements.append((start, end, repr(restored_values[key])))
        if restored_values.get(key) != old_value:
            changed_secrets.append(key)

    candidate = _replace_byte_spans(submitted_source, replacements)
    candidate = _remove_deprecated_assignments(candidate)

    present_keys = set(_assignment_nodes(candidate, submitted_path))
    missing_optional = sorted(set(OPTIONAL_DEFAULTS) - present_keys)
    if missing_optional:
        if candidate and not candidate.endswith('\n'):
            candidate += '\n'
        candidate += '\n# Settings added by the Pi.Alert configuration editor\n'
        for key in missing_optional:
            candidate += '{} = {!r}\n'.format(key, OPTIONAL_DEFAULTS[key])

    if len(candidate.encode('utf-8')) > MAX_CONFIG_BYTES:
        raise ConfigValidationError('configuration file is too large')
    candidate_values = load_pialert_config_source(
        candidate, submitted_path, expected_pialert_path)

    return candidate, {
        'changed_secrets': sorted(changed_secrets),
        'password_changed': 'PIALERT_WEB_PASSWORD' in changed_secrets,
        'key_count': len(candidate_values),
        'scan_subnets_assignments_removed': removed_scan_assignments,
    }


def write_source(path, source):
    data = source.encode('utf-8')
    if len(data) > MAX_CONFIG_BYTES:
        raise ConfigValidationError('configuration file is too large')
    try:
        with open(path, 'wb') as handle:
            written = handle.write(data)
            handle.flush()
            os.fsync(handle.fileno())
    except OSError as exc:
        raise ConfigValidationError('configuration output is not writable') from exc
    if written != len(data):
        raise ConfigValidationError('configuration output is incomplete')


def main():
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(dest='action', required=True)

    mask_parser = subparsers.add_parser('mask')
    mask_parser.add_argument('--input', required=True)
    mask_parser.add_argument('--output', required=True)

    prepare_parser = subparsers.add_parser('prepare')
    prepare_parser.add_argument('--input', required=True)
    prepare_parser.add_argument('--backup', required=True)
    prepare_parser.add_argument('--output', required=True)
    prepare_parser.add_argument('--expected-pialert-path', required=True)

    args = parser.parse_args()
    try:
        if args.action == 'mask':
            source = read_source(args.input)
            write_source(args.output, mask_config_source(source, args.input))
            return 0

        submitted = read_source(args.input)
        backup = read_source(args.backup)
        candidate, metadata = prepare_editor_candidate(
            submitted, backup, args.expected_pialert_path,
            args.input, args.backup)
        write_source(args.output, candidate)
        print(json.dumps(metadata, sort_keys=True, separators=(',', ':')))
        return 0
    except (ConfigValidationError, OSError, ValueError) as exc:
        print('Configuration editor error: {}'.format(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())

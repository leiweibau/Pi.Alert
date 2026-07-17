#!/usr/bin/env python3
"""Validate a Pi.Alert Python configuration without executing it."""
from __future__ import print_function

import argparse
import sys

from config_validation import ConfigValidationError, load_pialert_config


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('config_path')
    parser.add_argument('--expected-pialert-path')
    args = parser.parse_args()
    try:
        load_pialert_config(args.config_path, args.expected_pialert_path)
    except ConfigValidationError as exc:
        print('Invalid Pi.Alert configuration: {}'.format(exc), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())


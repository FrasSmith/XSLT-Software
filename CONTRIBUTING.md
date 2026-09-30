# Contributing

Thank you for contributing to PPTX Checker.

## Development requirements

- PHP 8.1 or newer
- Composer
- DOM, JSON, libxml, mbstring, XSL and ZIP PHP extensions

Install development dependencies locally:

```bash
composer install
composer test
```

Do not commit `vendor/`, `.phpunit.cache/`, uploaded presentations, credentials, or deployment-specific configuration.

## Changes

Please keep changes focused, add regression tests for behavior changes, and update the README when public behavior or deployment requirements change. Run `composer test` before opening a pull request.

## Pull requests

Describe the user-visible behavior, implementation approach, tests run, and any deployment or migration considerations. Do not include confidential presentations or personal data in fixtures.

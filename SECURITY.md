# Security policy

## Reporting a vulnerability

Please do not report security vulnerabilities through public GitHub issues.

Contact the maintainers privately with:

- A description of the vulnerability
- Reproduction steps or a proof of concept
- Affected versions
- Any suggested mitigation

Allow reasonable time for investigation and a fix before public disclosure.

## Deployment security

The API is unauthenticated by default and should be protected with authentication and rate limiting in production. Keep `src/`, `resources/`, `templates/`, and `vendor/` outside the public document root where possible.

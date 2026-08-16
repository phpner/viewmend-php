# Security policy

## Supported versions

No public version has been released. A supported-version matrix will be added with the first approved release.

## Reporting a vulnerability

A private vulnerability-reporting contact has not yet been designated and is required before the first public release. Until then, do not publish sensitive vulnerability details or credentials in a public issue.

## Credential handling

- Load ViewMend API tokens from the application's secret store or environment.
- Never commit tokens in source code, fixtures, snapshots, logs, or CI output.
- Rotate a token immediately if it may have been exposed.
- Treat event metadata and URLs as potentially sensitive application data.

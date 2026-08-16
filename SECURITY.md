# Security policy

## Supported versions

No public version has been released. A supported-version matrix will be added with the first approved release.

## Reporting a vulnerability

A dedicated vulnerability-reporting address will be published before the first public release.

During pre-release development, report suspected vulnerabilities privately to the project maintainers through the established project communication channel. Include the affected version or commit, impact, reproduction steps, and any proposed mitigation. Never include production API tokens, authorization headers, customer payloads, or other credentials.

## Credential handling

- Load ViewMend API tokens from the application's secret store or environment.
- Never commit tokens in source code, fixtures, snapshots, logs, or CI output.
- Rotate a token immediately if it may have been exposed.
- Treat event metadata and URLs as potentially sensitive application data.

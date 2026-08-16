# Security policy

## Supported versions

| Version | Supported |
| --- | --- |
| 1.x | Yes |
| Below 1.0 | No |

## Reporting a vulnerability

Send vulnerability reports privately to [support@viewmend.com](mailto:support@viewmend.com) with the subject `Security report: viewmend/sdk`.

Include:

- the affected version;
- a description of the impact;
- steps to reproduce the issue;
- a safe proof of concept, if available.

Do not publish sensitive vulnerability details, credentials, or an undisclosed vulnerability in a public GitHub Issue.

## Credential handling

- Load ViewMend API tokens from the application's secret store or environment.
- Never commit tokens in source code, fixtures, snapshots, logs, or CI output.
- Rotate a token immediately if it may have been exposed.
- Treat event metadata and URLs as potentially sensitive application data.

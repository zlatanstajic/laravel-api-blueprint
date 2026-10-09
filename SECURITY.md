# Security

## Reporting a vulnerability

Report suspected vulnerabilities privately to [contact@zlatanstajic.com](mailto:contact@zlatanstajic.com). Do not post sensitive details in a public issue or pull request.

Include the affected commit or version, steps to reproduce, expected and observed behavior, and the potential impact. Use a minimal example with synthetic data; omit passwords, tokens, personal information, and environment files.

Verify reports against the current `master` branch when possible. Older revisions may differ; include the exact revision you tested. Fixes are published through the repository after the issue is addressed.

## Deployment responsibility

This project is a reference API. Review authentication, configuration, dependencies, and credentials before deploying it. Keep todo routes behind `auth:sanctum`; ownership scoping depends on an authenticated user. The setup command recreates the database and is intended for a disposable development environment.

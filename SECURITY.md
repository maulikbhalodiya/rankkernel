# Security Policy

## Supported versions
RankKernel is under active development. Security fixes are applied to the latest `main` branch.

## Reporting a vulnerability
If you find a security issue, please do not open a public issue. Report privately:
- GitHub profile: https://github.com/maulikbhalodiya
- Private advisory: https://github.com/maulikbhalodiya/rankkernel/security/advisories/new

Include a clear description, reproduction steps, impact assessment, and a suggested fix if possible.

Maintainer response target: acknowledgement within 72 hours.

## Security practices
- Input validation and sanitization at system boundaries
- Context-aware output escaping (HTML, attribute, JSON, URL)
- Parameterized SQL via `$wpdb->prepare`
- WordPress nonce verification for forms and AJAX
- Capability checks before sensitive operations
- HMAC-SHA256 signing for cross-domain trust

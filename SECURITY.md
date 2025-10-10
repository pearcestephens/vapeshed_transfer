# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 4.x     | :white_check_mark: |
| 3.x     | :x:                |
| < 3.0   | :x:                |

## Reporting a Vulnerability

We take the security of the Vapeshed Transfer Engine seriously. If you have discovered a security vulnerability, we appreciate your help in disclosing it to us in a responsible manner.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via one of the following methods:

1. **Email**: Send details to security@vapeshed.co.nz
2. **GitHub Security Advisory**: Use the [GitHub Security Advisory](https://github.com/pearcestephens/vapeshed_transfer/security/advisories/new) feature

### What to Include

Please include the following information in your report:

- Type of vulnerability (e.g., SQL injection, XSS, authentication bypass)
- Full paths of affected source file(s)
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### Response Timeline

- **Initial Response**: Within 48 hours of submission
- **Status Update**: Within 7 days with assessment and planned fix timeline
- **Fix Development**: Depending on severity, typically within 30 days
- **Public Disclosure**: After fix is deployed and users have had time to update

### Security Update Process

1. Vulnerability is received and assigned to a primary handler
2. Problem is confirmed and affected versions are determined
3. Code is audited to find any similar problems
4. Fixes are prepared for all supported releases
5. Fixes are released and security advisory is published

## Security Best Practices

When deploying this application:

### Required Security Measures

1. **Environment Variables**: Never commit `.env` files or expose credentials
2. **HTTPS Only**: Always use HTTPS/TLS in production
3. **Database Access**: Use least-privilege database accounts
4. **File Permissions**: Ensure proper file permissions (storage/ should be writable, config/ should be read-only)
5. **Updates**: Keep all dependencies up to date

### Security Headers

The application implements the following security headers:
- Content-Security-Policy (CSP)
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Strict-Transport-Security (HSTS)
- Referrer-Policy: strict-origin-when-cross-origin

### CSRF Protection

All state-changing operations require CSRF tokens. Tokens are validated server-side.

### Input Validation

All user input is validated and sanitized using the application's validation layer.

### SQL Injection Prevention

All database queries use prepared statements with parameter binding.

## Known Security Considerations

### Rate Limiting

The application implements rate limiting for:
- API endpoints
- Authentication attempts
- Resource-intensive operations

### Audit Logging

Security-relevant events are logged:
- Authentication attempts (success/failure)
- Privilege changes
- Data exports
- Administrative actions

## Acknowledgments

We appreciate the security research community and will acknowledge responsible disclosure in our security advisories (unless you prefer to remain anonymous).

## Contact

For any questions about this policy, please contact: security@vapeshed.co.nz

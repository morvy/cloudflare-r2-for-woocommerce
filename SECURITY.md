# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Reporting a Vulnerability

**Please do not open public issues for security vulnerabilities.**

To report a security vulnerability, please email: **moped@jepan.sk**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

You should receive a response within 48 hours. If the issue is confirmed, a fix will be released as soon as possible depending on complexity.

## Security Measures

This plugin implements:
- **AES-256-GCM encryption** for storing credentials
- **Pre-signed URLs** with expiration for file downloads
- **WooCommerce's access control** for download permissions
- **WordPress nonces** for form submissions
- **Input sanitization** and output escaping
- **Prepared statements** for database queries

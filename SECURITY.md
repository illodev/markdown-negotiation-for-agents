# Security Policy

## Supported Versions

The following versions of Markdown Negotiation for Agents are currently supported with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability, please report it responsibly by emailing:

**[hello@illodev.com](mailto:hello@illodev.com)**

Include the following in your report:

- A description of the vulnerability and its potential impact
- Steps to reproduce the issue
- PHP version, WordPress version, and plugin version
- Any proof-of-concept code (if applicable)

### What to Expect

- **Acknowledgement**: You will receive a response within **48 hours** confirming we have received your report.
- **Assessment**: We will investigate and assess the issue within **7 days**.
- **Resolution**: Critical vulnerabilities will be patched and released within **14 days** of confirmation.
- **Credit**: With your permission, we will credit you in the release notes and `CHANGELOG.md`.

---

## Disclosure Policy

We follow a coordinated disclosure policy:

1. Reporter submits vulnerability details privately.
2. We confirm and investigate the report.
3. We develop and test a fix.
4. We release the patched version.
5. We publish a security advisory after the patch is widely deployed (typically 7 days after release).

We ask that you give us a reasonable amount of time to address the issue before any public disclosure.

---

## Scope

The following are **in scope** for security reports:

- Content injection or XSS via the Markdown output pipeline
- Unauthenticated access to private or password-protected content
- Rate limiter bypass leading to denial of service
- Remote code execution of any kind
- Authorization bypass (accessing posts the user shouldn't see)
- Information disclosure of sensitive WordPress data via the REST API or Markdown endpoint
- Header injection

The following are **out of scope**:

- Vulnerabilities in WordPress core, themes, or other plugins
- Issues affecting only development/testing environments
- Self-XSS requiring administrator-level access
- Theoretical vulnerabilities without a practical exploit path

---

## Security Best Practices for Users

- Keep the plugin updated to the latest version.
- Restrict access to the REST API endpoints using the `jetstaa_mna_rest_permission` filter if needed.
- Configure rate limiting in **Settings → Markdown Negotiation**.
- Review the per-post disable option for sensitive content.

---

## GPG Key

We do not currently publish a GPG key. For sensitive communications, use the email address above with a note that you will send an encrypted follow-up and we will coordinate accordingly.

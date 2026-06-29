# Changelog
All notable changes to this project will be documented in this file.

---
## [1.0.0] - 2026-06-29
### Added
- Initial module scaffold.
- Added configurable captcha protection for Contact, Register, Login, and Forgot password forms.
- Added captcha providers for Google reCAPTCHA v2, Google reCAPTCHA v3, Cloudflare Turnstile, ALTCHA, and ALTCHA Sentinel.
- Added local ALTCHA challenge generation through a dedicated front controller.
- Added email validation with blocked emails, blocked domains, wildcard patterns, and disposable-domain blocking.
- Added contact message validation with blocked text and maximum link count.
- Added a back-office captcha key test button for configured providers.
- Added strict server-side validation for captcha settings, credentials, email validation lists, and message validation settings.

### Fixed
- Added validator-compliant license headers and file comments.
- Fixed compatibility analyzer warning in message link counting.

# Changelog
All notable changes to this project will be documented in this file.

---
## [1.0.2] - 2026-06-30
### Added
- Added checkout support for guest checkout, checkout customer creation, and checkout login forms.
- Added a dedicated `Captcha during checkout` switch so merchants can decide whether Register and Login captcha settings also apply inside checkout.
- Added an optional `Skip captcha for logged-in customers` setting.
- Added a back-office button to update the local disposable email domain list from `https://disposable.github.io/disposable-email-domains/domains_mx.txt` with validation and timestamped backup.
- Added automatic cleanup for disposable email domain backups, keeping the 5 most recent files.
- Added configurable discouraged email domains with a non-blocking front-office warning popup.
- Added front-office handling for checkout AJAX refresh events so captcha widgets are reattached after checkout step updates.
- Added translations for English, Italian, Spanish, German, French, Portuguese, Polish, Romanian, and Dutch.

### Changed
- Changed module license to MIT.
- Kept captcha, email validation, and message validation independent: disabling or skipping captcha no longer disables email or message validation.
- Improved README documentation with checkout behavior, logged-in customer bypass, validation independence, and implementation notes.

### Fixed
- Fixed local ALTCHA challenge responses returning an error HTTP status after checkout state changes.
- Fixed hidden checkout forms receiving captcha widgets.
- Fixed repeated captcha verification in the same request by caching validation results per form type.
- Fixed stale captcha notification messages when returning to the checkout personal information step after customer identity was already validated.

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

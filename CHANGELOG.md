# Changelog
All notable changes to this project will be documented in this file.

---
## [Unreleased]

---
## [1.0.10] - 2026-07-03
### Changed
- Removed the back-office public link to the bundled disposable email domain list.
- Updated the bundled disposable email domain list.

### Fixed
- Blocked direct browser access to internal disposable-domain data files.
- Fixed missing multishop fallback for spam validation settings when a shop does not have shop-specific values.
- Made validation log rows read-only to avoid empty edit pages from row clicks.

---
## [1.0.9] - 2026-07-03
### Fixed
- Fixed coding standard warnings in GeoIP reader imports and file-level spacing.

---
## [1.0.8] - 2026-07-03
### Added
- Added optional failed validation logging with configurable retention, plus separate switches for issued captcha and successful validation logging.
- Added a back-office validation log controller showing ID, IP, optional GeoLite2 location, failed validation location, attempted email for email validation failures, user agent, and date.
- Added a visible back-office Spam Protection menu under Configure with module configuration and validation log links.

---
## [1.0.7] - 2026-07-02
### Fixed
- Removed the deleted admin-login-before hook registration and method while keeping admin login validation through `actionDispatcher`.
- Fixed validator license warnings by removing blank lines before file comments.

---
## [1.0.6] - 2026-07-02
### Fixed
- Fixed the back-office captcha key test URL and POST payload handling for PrestaShop 9 multishop back offices.
- Fixed captcha widget placement in Hummingbird forms when submit buttons are inside flex or action containers.
- Fixed local ALTCHA styling on Warehouse when Smart cache for JavaScript serves an older aggregated theme bundle.
- Fixed local ALTCHA challenge URLs on multishop back-office login pages so the challenge uses the current admin request domain.

---
## [1.0.5] - 2026-07-02
### Fixed
- Fixed validator compatibility warnings by removing direct `Context` class usage from hook registration fallbacks.
- Fixed coding standard warnings in the `1.0.4` upgrade script and AJAX admin-login captcha response.

---
## [1.0.4] - 2026-07-02
### Added
- Added an optional **Captcha on back-office login** switch that injects the configured captcha provider into the PrestaShop employee login page.
- Added legacy admin login server-side validation for blocked login attempts.
- Added `upgrade-1.0.4.php` to initialize the new setting disabled and register the new admin login hooks on existing installations.

### Changed
- Updated README implementation notes to document `displayAdminLogin` and the back-office login captcha behavior.

### Fixed
- Fixed legacy PrestaShop back-office AJAX login submissions so the solved captcha token is included in the manual `AdminLogin` request payload.
- Fixed reCAPTCHA v3 fallback handling on the back-office login page so low-score attempts can render and submit the configured visible fallback captcha.
- Fixed back-office reCAPTCHA v3 normal-score submissions by generating a fresh final token after the pre-check, avoiding reuse of the token already verified by Google.

---
## [1.0.3] - 2026-07-01
### Added
- Added an AJAX pre-check fallback flow for reCAPTCHA v3 so a visible configured captcha can be shown when the v3 score is too low.
- Added a visible reCAPTCHA protection notice next to forms protected by invisible reCAPTCHA v3.
- Added README FAQ examples for privacy policy and cookie policy wording for each supported captcha provider.
- Added README FAQ explaining that reCAPTCHA v3 cannot be forced to show the checkbox.
- Added localized ALTCHA and ALTCHA Sentinel widget labels and visible status messages.

### Changed
- Hid the fixed Google reCAPTCHA v3 badge when the module renders its visible reCAPTCHA protection notice next to protected forms.
- Changed the reCAPTCHA v3 front-office error message so it no longer asks customers to validate an invisible captcha manually.
- Styled the local ALTCHA widget with a light boxed layout, `#cccccc` outer border, stronger black checkbox border, and the module logo inside the challenge box at `64x64` pixels.
- Added translated alternative text to the local ALTCHA module logo.

### Fixed
- Fixed ALTCHA and ALTCHA Sentinel fallback submissions after a solved fallback challenge.
- Fixed reCAPTCHA v3 first-submit handling so protected forms keep their submit button data after the token is generated.
- Fixed missing front-office error notification after a blocked captcha submission redirects back to the protected form.
- Fixed ALTCHA Sentinel API key tests so they use the configured Sentinel URL and API key consistently.

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

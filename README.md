# Tec Spam Guard

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![Built for PrestaShop](https://img.shields.io/badge/Built%20for-PrestaShop-DF0067?logo=prestashop&logoColor=white)  

> PrestaShop anti-spam module for front-office forms.

Tec Spam Guard by Tecnoacquisti.com® protects contact, registration, login, checkout identity, and password reset forms with configurable captcha providers, email validation, disposable-domain blocking, and contact message checks.

## Features

- Captcha providers: Google reCAPTCHA v2, Google reCAPTCHA v3, Cloudflare Turnstile, ALTCHA, and ALTCHA Sentinel.
- Per-form captcha switches for Contact, Register, Login, Checkout identity, and Forgot password.
- Optional checkout captcha switch: checkout registration and login follow the normal Register and Login captcha settings only when checkout captcha is enabled.
- Optional captcha bypass for logged-in customers.
- Email validation for Contact and Register by default, with optional validation on Login and Forgot password.
- Email validation works independently from captcha settings, including during checkout when enabled for Register or Login.
- Disposable email domain blocking from a local list.
- Non-blocking warning popup for configured discouraged email domains, allowing customers to continue after confirming the warning.
- Manual back-office update for the local disposable email domain list from `https://disposable.github.io/disposable-email-domains/domains_mx.txt`, with validation and timestamped backup.
- Manual blocked email addresses, blocked domains, and wildcard email patterns.
- Contact message validation with blocked words or phrases and a maximum link count.
- Masked secret fields in the back office so existing credentials are not exposed or accidentally cleared.
- Back-office captcha key test button for Google reCAPTCHA, Cloudflare Turnstile, ALTCHA, and ALTCHA Sentinel.
- Server-side validation for all module configuration fields before values are saved.

## Compatibility

- PrestaShop 1.7, 8.x, and 9.x.
- PHP 7.0+.
- No dependency on Tec Block AI Bots.
- Released under the MIT License.

## Protected Forms

The module focuses on native PrestaShop front-office forms:

- Contact us
- Customer registration
- Customer login
- Checkout guest/customer creation
- Checkout login
- Forgot password

Additional forms such as newsletter, product reviews, quick order, and stock notifications can be added later with the same form descriptor pattern.

## Captcha Activation

Captcha can be enabled separately for each supported form. The checkout has an extra control because checkout registration and login are the same logical form types as normal registration and login, but merchants may want a different user experience during order creation.

The checkout rule is:

- `Captcha on registration form` controls registration captcha globally.
- `Captcha on login form` controls login captcha globally.
- `Captcha during checkout` decides whether those Register and Login captcha settings also apply inside the checkout.

If `Captcha during checkout` is disabled, the checkout does not ask for captcha on registration or login, even if the normal Register or Login captcha settings are enabled. This affects only captcha. Email validation remains independent and still runs when the related Register or Login email validation switch is enabled.

The `Skip captcha for logged-in customers` option can be enabled to avoid asking authenticated customers to solve captcha challenges. This is useful on account, checkout, and contact flows where a logged-in customer has already passed the shop authentication step. This option only skips captcha; email validation and message validation continue to run when enabled.

## Email And Message Validation

Email validation is independent from captcha. When enabled for a form, the module checks the submitted email server-side even if captcha is disabled for that form or skipped for the current customer. Email validation can block:

- exact email addresses;
- entire domains;
- wildcard patterns such as `*@example.com`;
- disposable email domains from the bundled local list.

The same Email validation tab also includes `Discouraged email domains`. This list is advisory, not blocking. When `Show warning for discouraged email domains` is enabled and a customer uses one of those domains on a protected email-validation form, the front office shows a confirmation popup explaining that the address often has delivery problems and recommending another email address. If the customer confirms, the form can still be submitted. The default advisory list is `libero.it`, `virgilio.it`, `tiscali.it`, `tin.it`, `t-online.de`, `aol.com`, `tim.it`, `aruba.it`, `outlook.it`, `outlook.com`, `hotmail.com`, `live.it`, and `live.com`.

Contact message validation is also independent from captcha. It can block configured words or phrases and reject contact messages with too many links.

The bundled disposable email domain file can be reviewed from the Information tab in the back office. The same tab also includes a manual update button that downloads the maintained `domains_mx.txt` list from `disposable.github.io`, validates the downloaded domains, creates a timestamped backup of the current local file, and then replaces `data/disposable_domains.txt`. The module keeps the 5 most recent disposable-domain backups and removes older backup files automatically after a successful update.

## Captcha Notes

ALTCHA local challenges are generated by the module through the `altchachallenge` front controller and signed with the configured HMAC secret. ALTCHA Sentinel uses the configured Sentinel challenge URL and verifies the solved payload server-side.

For Google reCAPTCHA v3, the default action is `tec_spamguard` and the default minimum score is `0.50`.

## Captcha Provider Comparison

The best captcha service depends on the shop risk profile, privacy requirements, expected traffic, and operational capacity. The table below gives a quick comparison of the providers supported by the module.

Prices and commercial terms can change. Always verify current provider pricing, data processing terms, and regional compliance requirements before choosing a production setup.

| Solution | Effectiveness | Protection type | Weak point | GDPR | Limits |
|---|---|---|---|---|---|
| Google reCAPTCHA v2 | High against simple automated spam, familiar to users | Interactive challenge and risk signals from Google | Can interrupt checkout and form conversion; depends on Google services; accessibility can vary by challenge | Requires Google data processing assessment, privacy notice updates, and consent/legal basis review | External service dependency; possible quota, pricing, or policy changes; visible challenge can add friction |
| Google reCAPTCHA v3 | Good for low-friction protection when scores are tuned | Invisible risk scoring based on user behavior and Google signals | Score tuning can produce false positives or false negatives; no visible proof for the merchant without monitoring | Requires Google data processing assessment, privacy notice updates, and consent/legal basis review | External service dependency; score thresholds need monitoring; may be less predictable on low-traffic shops |
| Cloudflare Turnstile | High for most form spam with low user friction | Managed challenge and browser/device attestation from Cloudflare | Depends on Cloudflare availability and risk engine; behavior can change as Cloudflare updates the service | Generally privacy-oriented compared with traditional tracking captcha, but still requires reviewing Cloudflare terms and data flows | External service dependency; merchant must keep provider keys and domains correctly configured |
| ALTCHA | Good against basic automated spam and bot submissions | Self-hosted proof-of-work challenge signed by the module | Less advanced against targeted attackers, human spam, and sophisticated browser automation | Strong privacy profile because the local challenge can run without third-party captcha calls | Protection depends on HMAC secret safety, challenge lifetime, and server-side verification; no managed threat intelligence |
| ALTCHA Sentinel | High when deployed and tuned correctly, especially for adaptive bot protection | Self-hosted Sentinel backend with adaptive captcha and threat detection | Requires infrastructure owned or managed by the merchant, such as VPS, Docker, storage, updates, monitoring, and backups | Strong data-residency profile because the protection stack can run on merchant-controlled infrastructure | Paid license plus infrastructure cost; public pricing references vary by plan, with Professional pricing reported from about EUR 99/month and Enterprise references around EUR 799/month or more; verify current ALTCHA pricing before deployment |

## Implementation Notes

Tec Spam Guard does not use PrestaShop overrides. It does not replace core controllers, core classes, theme templates, or checkout templates.

The module is implemented through standard PrestaShop extension points:

- `displayHeader` loads the front-office JavaScript and captcha provider script only on pages that may contain protected forms.
- `actionDispatcher` detects submitted native forms before the target front controller processes the request.
- `actionContactFormSubmitBefore` and `actionSubmitAccountBefore` are used as native hook fallbacks where PrestaShop exposes them.
- A dedicated `altchachallenge` module front controller generates local ALTCHA challenges.
- Small form descriptor classes identify supported forms and read submitted values without coupling the validators to a specific controller implementation.

The front-office script injects captcha widgets into visible supported forms and listens to checkout refresh events so the widget is reattached when PrestaShop updates checkout steps through AJAX. Server-side validation remains the source of truth: a form submission is rejected when the configured captcha, email, or message rule fails, regardless of client-side behavior.

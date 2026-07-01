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

The local ALTCHA widget is styled by the module so it appears in a light `#f8f8f8` box with a `#cccccc` border, a more visible black checkbox border, and the module `logo.png` displayed inside the same box at `64x64` pixels. This visual customization is applied only to the local ALTCHA provider, including when local ALTCHA is used as the reCAPTCHA v3 fallback; ALTCHA Sentinel keeps the provider's standard widget layout.

For Google reCAPTCHA v3, the default action is `tec_spamguard` and the default minimum score is `0.50`.

When Google reCAPTCHA v3 is active, the module shows the notice `This site is protected by reCAPTCHA.` next to each protected form because the captcha itself is invisible.

When Google reCAPTCHA v3 is active, merchants can select a configured visible captcha provider as a fallback. The fallback select appears after `reCAPTCHA v3 minimum score` and only lists providers with complete stored credentials. If the v3 AJAX pre-check returns a score below the configured threshold, the module keeps the customer on the same form and renders the selected fallback captcha before the final submit.

## FAQ

### Why do I not see a checkbox with reCAPTCHA v3?

Google reCAPTCHA v3 is an invisible captcha. It does not show the classic "I am not a robot" checkbox and normally does not ask the customer to solve an interactive challenge.

When reCAPTCHA v3 is active, the module loads Google's script in the background, requests a token for the configured action, and verifies that token server-side. Google returns a score from `0.0` to `1.0`; the module accepts the form only when the score is equal to or higher than the configured minimum score.

If you want customers to see a checkbox or an interactive challenge, use Google reCAPTCHA v2 or another visible captcha provider instead.

### Can I force reCAPTCHA v3 to show the checkbox?

No. Google reCAPTCHA v3 cannot be forced to show the classic checkbox. The invisible score-based behavior is part of how reCAPTCHA v3 works.

If you want customers to see the "I am not a robot" checkbox or a visible interactive challenge, choose Google reCAPTCHA v2 as the captcha provider and configure keys generated for reCAPTCHA v2. reCAPTCHA v2 and v3 keys are not interchangeable.

### What should I write in the privacy policy and cookie policy?

This module cannot make a shop GDPR-compliant by itself. The merchant remains responsible for choosing the captcha provider, selecting the legal basis, signing any required data processing agreement, updating the privacy policy, and configuring the cookie banner or consent platform according to the applicable law and legal advice.

The examples below are practical starting points for the supported providers. They must be reviewed by the merchant's privacy consultant before production use.

#### Google reCAPTCHA v2

Privacy policy example:

> We use Google reCAPTCHA v2 on selected forms to protect the website from spam, abuse, and automated submissions. reCAPTCHA may process technical information such as IP address, browser and device data, user interaction signals, and the page where the protected form is used. The processing is used to verify whether the form is submitted by a human visitor or by automated software. Google acts according to the applicable reCAPTCHA data processing terms.

Cookie policy example:

> Google reCAPTCHA v2 may use cookies or similar technologies that are necessary for the security check and bot protection. These technologies are used to prevent spam and abuse on protected forms. If your legal assessment requires prior consent for this provider, configure the consent platform so that reCAPTCHA scripts are loaded only after consent.

Useful references:

- Google reCAPTCHA / Google Cloud Fraud Defense FAQ: [FAQ](https://docs.cloud.google.com/recaptcha/docs/faq)
- Google reCAPTCHA product page: [Product page](https://cloud.google.com/security/products/recaptcha)

#### Google reCAPTCHA v3

Privacy policy example:

> We use Google reCAPTCHA v3 on selected forms to protect the website from spam, abuse, and automated submissions. reCAPTCHA v3 works in the background and returns a risk score for the submitted action. The service may process technical information such as IP address, browser and device data, user interaction signals, the configured action name, and the page where the protected form is used. We use the score only to decide whether the form submission should be accepted or rejected for security reasons.

Cookie policy example:

> Google reCAPTCHA v3 may use cookies or similar technologies for risk analysis and bot protection. Because reCAPTCHA v3 is invisible, the website shows the notice "This site is protected by reCAPTCHA." near protected forms. If your legal assessment requires prior consent for this provider, configure the consent platform so that reCAPTCHA scripts are loaded only after consent.

Useful references:

- Google reCAPTCHA / Google Cloud Fraud Defense FAQ: [FAQ](https://docs.cloud.google.com/recaptcha/docs/faq)
- Google reCAPTCHA v3 documentation: [v3 documentation](https://developers.google.com/recaptcha/docs/v3)

#### Cloudflare Turnstile

Privacy policy example:

> We use Cloudflare Turnstile on selected forms to protect the website from spam, abuse, and automated submissions. Turnstile verifies whether a visitor can submit the protected form by processing security signals such as IP address, browser and device information, request data, and interaction signals. The processing is used only for bot detection and website security.

Cookie policy example:

> Cloudflare Turnstile may use strictly necessary cookies or similar technologies for bot detection and form security. These technologies are used to protect the website and its forms from automated abuse. If optional Turnstile features such as pre-clearance are enabled in the Cloudflare configuration, additional cookies such as clearance cookies may be used and should be described separately.

Useful references:

- Cloudflare Turnstile privacy policy: [Privacy policy](https://www.cloudflare.com/turnstile-privacy-policy/)
- Cloudflare Turnstile documentation: [Documentation](https://developers.cloudflare.com/turnstile/)

#### ALTCHA

Privacy policy example:

> We use ALTCHA on selected forms to protect the website from spam, abuse, and automated submissions. ALTCHA uses a local proof-of-work challenge generated by this website and verified by our server. The captcha solution does not require a third-party captcha provider for the local challenge. We process the challenge response and normal security data related to the form submission, such as IP address and technical request data, only to verify the form submission and prevent abuse.

Cookie policy example:

> The local ALTCHA captcha used by this website does not require third-party captcha cookies. Any technical data processed by the challenge is used to protect forms from spam and automated abuse. If the website uses separate security, session, or firewall cookies outside this module, those cookies should be listed separately.

Useful references:

- ALTCHA open-source captcha: [Open-source captcha](https://altcha.org/open-source-captcha/)
- ALTCHA project repository: [GitHub repository](https://github.com/altcha-org/altcha)

#### ALTCHA Sentinel

Privacy policy example:

> We use ALTCHA Sentinel on selected forms to protect the website from spam, abuse, and automated submissions. Sentinel is deployed on infrastructure controlled by us or by our appointed hosting/provider. It may process technical and security information such as IP address, browser and device data, request metadata, risk signals, challenge results, and security logs. The processing is used to detect automated abuse, verify form submissions, and maintain website security.

Cookie policy example:

> ALTCHA Sentinel may use technical cookies or similar technologies when required by the deployed Sentinel configuration to provide bot protection, challenge verification, abuse prevention, and security logging. These technologies should be classified according to the actual Sentinel deployment and the merchant's legal assessment, normally as security or strictly necessary technologies when they are essential for form protection.

Useful references:

- ALTCHA Sentinel overview: [Overview](https://altcha.org/)
- ALTCHA Sentinel install documentation: [Install documentation](https://altcha.org/docs/v2/sentinel/install/)
- ALTCHA enterprise and data ownership notes: [Enterprise notes](https://altcha.org/docs/v2/enterprise/)

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

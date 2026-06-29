<?php

/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
 */

namespace TecSpamGuard\Captcha;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TurnstileProvider extends RecaptchaV2Provider
{
    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function getId()
    {
        return 'turnstile';
    }

    public function getLabel()
    {
        return 'Cloudflare Turnstile';
    }

    public function getScriptUrl($langIso)
    {
        $lang = preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', (string) $langIso) ? $langIso : 'en';

        return self::SCRIPT_URL . '?hl=' . rawurlencode($lang);
    }

    public function getResponseFieldName()
    {
        return 'cf-turnstile-response';
    }

    public function verify($token, $secret, $remoteIp)
    {
        return $this->postVerify(self::VERIFY_URL, [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);
    }

    public function testKeys($siteKey, $secret)
    {
        $siteKey = trim((string) $siteKey);
        $secret = trim((string) $secret);

        if ($siteKey === '' || $secret === '') {
            return [
                'success' => false,
                'message' => 'Both the site key and the secret key must be set.',
            ];
        }

        $result = $this->verify('tec_spamguard_test_token', $secret, '127.0.0.1');
        if (!empty($result['success'])) {
            return ['success' => true, 'message' => 'Captcha keys appear to be valid.'];
        }

        if (in_array('invalid-input-secret', $result['errors'], true)) {
            return [
                'success' => false,
                'message' => 'The secret key is rejected by Cloudflare. Check the value in the Turnstile dashboard.',
            ];
        }

        if (in_array('invalid-input-response', $result['errors'], true)) {
            return [
                'success' => true,
                'message' => 'Captcha keys appear to be valid. Cloudflare accepted the secret and rejected the test token as expected.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Captcha validation failed: ' . implode(', ', $result['errors']),
        ];
    }
}

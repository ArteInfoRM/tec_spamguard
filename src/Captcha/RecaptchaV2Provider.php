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

class RecaptchaV2Provider implements CaptchaProviderInterface
{
    public const SCRIPT_URL = 'https://www.google.com/recaptcha/api.js';
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function getId()
    {
        return 'recaptcha_v2';
    }

    public function getLabel()
    {
        return 'Google reCAPTCHA v2';
    }

    public function getScriptUrl($langIso)
    {
        $lang = preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', (string) $langIso) ? $langIso : 'en';

        return self::SCRIPT_URL . '?hl=' . rawurlencode($lang);
    }

    public function getResponseFieldName()
    {
        return 'g-recaptcha-response';
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
                'message' => 'The secret key is rejected by Google. Check the value in the reCAPTCHA admin console.',
            ];
        }

        if (in_array('invalid-input-response', $result['errors'], true)) {
            return [
                'success' => true,
                'message' => 'Captcha keys appear to be valid. Google accepted the secret and rejected the test token as expected.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Captcha validation failed: ' . implode(', ', $result['errors']),
        ];
    }

    protected function postVerify($url, array $payload)
    {
        $data = $this->postVerifyData($url, $payload);
        if (isset($data['_transport_error'])) {
            return ['success' => false, 'errors' => [$data['_transport_error']]];
        }
        if (isset($data['_malformed_response'])) {
            return ['success' => false, 'errors' => ['malformed-response']];
        }

        return [
            'success' => !empty($data['success']),
            'errors' => isset($data['error-codes']) && is_array($data['error-codes']) ? array_values(array_map('strval', $data['error-codes'])) : [],
        ];
    }

    protected function postVerifyData($url, array $payload)
    {
        if ((string) $payload['secret'] === '' || (string) $payload['response'] === '') {
            return ['success' => false, 'error-codes' => ['missing-input']];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_errno($ch) ? curl_error($ch) : '';
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return ['_transport_error' => 'transport: http=' . $httpCode . ($curlErr ? ' err=' . $curlErr : '')];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['_malformed_response' => true];
        }

        return $data;
    }
}

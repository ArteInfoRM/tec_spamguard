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

class AltchaProvider implements CaptchaProviderInterface
{
    public const SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/altcha@3.1.0/dist/main/altcha.js';
    public const ALGORITHM = 'SHA-256';

    private $hideFooter;
    private $hideLogo;

    public function __construct($hideFooter = false, $hideLogo = false)
    {
        $this->hideFooter = (bool) $hideFooter;
        $this->hideLogo = (bool) $hideLogo;
    }

    public function getId()
    {
        return 'altcha';
    }

    public function getLabel()
    {
        return 'ALTCHA';
    }

    public function getScriptUrl($langIso)
    {
        unset($langIso);

        return self::SCRIPT_URL;
    }

    public function getResponseFieldName()
    {
        return 'altcha';
    }

    public function getWidgetAttributes()
    {
        return [
            'hideFooter' => $this->hideFooter,
            'hideLogo' => $this->hideLogo,
        ];
    }

    public function createChallenge($secret, $difficulty, $expiresInSeconds)
    {
        $secret = trim((string) $secret);
        $difficulty = max(1, min(3, (int) $difficulty));
        $maxNumber = [1 => 50000, 2 => 250000, 3 => 1000000][$difficulty];
        $number = random_int(0, $maxNumber);
        $salt = bin2hex(random_bytes(12)) . '?expires=' . (time() + max(60, min(3600, (int) $expiresInSeconds)));
        $challenge = hash('sha256', $salt . $number);

        return [
            'algorithm' => self::ALGORITHM,
            'challenge' => $challenge,
            'salt' => $salt,
            'signature' => hash_hmac('sha256', $challenge, $secret),
            'maxnumber' => $maxNumber,
        ];
    }

    public function verify($token, $secret, $remoteIp)
    {
        unset($remoteIp);

        if ((string) $token === '' || (string) $secret === '') {
            return ['success' => false, 'errors' => ['missing-input']];
        }

        $payload = $this->decodePayload((string) $token);
        if (!is_array($payload)) {
            return ['success' => false, 'errors' => ['malformed-payload']];
        }

        foreach (['algorithm', 'challenge', 'number', 'salt', 'signature'] as $field) {
            if (!isset($payload[$field]) || (string) $payload[$field] === '') {
                return ['success' => false, 'errors' => ['missing-' . $field]];
            }
        }

        if ((string) $payload['algorithm'] !== self::ALGORITHM) {
            return ['success' => false, 'errors' => ['invalid-algorithm']];
        }

        $params = $this->extractSaltParams((string) $payload['salt']);
        if (isset($params['expires']) && (int) $params['expires'] < time()) {
            return ['success' => false, 'errors' => ['expired-challenge']];
        }

        $number = (int) $payload['number'];
        $expectedChallenge = hash('sha256', (string) $payload['salt'] . $number);
        $expectedSignature = hash_hmac('sha256', $expectedChallenge, (string) $secret);

        if (!hash_equals($expectedChallenge, (string) $payload['challenge'])
            || !hash_equals($expectedSignature, (string) $payload['signature'])) {
            return ['success' => false, 'errors' => ['invalid-solution']];
        }

        return ['success' => true, 'errors' => []];
    }

    public function testKeys($siteKey, $secret)
    {
        unset($siteKey);

        $secret = trim((string) $secret);
        if ($secret === '') {
            return [
                'success' => false,
                'message' => 'ALTCHA HMAC secret must be set.',
            ];
        }

        if (!preg_match('/^[A-Za-z0-9._~+\/=-]{16,256}$/', $secret)) {
            return [
                'success' => false,
                'message' => 'ALTCHA HMAC secret must contain 16 to 256 safe characters.',
            ];
        }

        $challenge = $this->createChallenge($secret, 1, 300);
        if (empty($challenge['challenge']) || empty($challenge['signature']) || empty($challenge['salt'])) {
            return [
                'success' => false,
                'message' => 'ALTCHA challenge generation failed.',
            ];
        }

        return [
            'success' => true,
            'message' => 'ALTCHA configuration appears to be valid.',
        ];
    }

    private function decodePayload($token)
    {
        $decoded = base64_decode((string) $token, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    private function extractSaltParams($salt)
    {
        $parts = explode('?', (string) $salt, 2);
        if (count($parts) < 2) {
            return [];
        }

        $params = [];
        parse_str($parts[1], $params);

        return $params;
    }
}

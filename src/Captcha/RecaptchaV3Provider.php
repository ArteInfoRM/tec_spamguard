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

class RecaptchaV3Provider extends RecaptchaV2Provider
{
    private $action;
    private $minScore;

    public function __construct($action = 'tec_spamguard', $minScore = 0.5)
    {
        $this->action = preg_match('/^[A-Za-z0-9_\/.-]{1,64}$/', (string) $action) ? (string) $action : 'tec_spamguard';
        $this->minScore = max(0.0, min(1.0, (float) $minScore));
    }

    public function getId()
    {
        return 'recaptcha_v3';
    }

    public function getLabel()
    {
        return 'Google reCAPTCHA v3';
    }

    public function getScriptUrl($langIso)
    {
        unset($langIso);

        return self::SCRIPT_URL;
    }

    public function verify($token, $secret, $remoteIp)
    {
        $data = $this->postVerifyData(self::VERIFY_URL, [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);
        if (isset($data['_transport_error'])) {
            return ['success' => false, 'errors' => [$data['_transport_error']]];
        }
        if (isset($data['_malformed_response'])) {
            return ['success' => false, 'errors' => ['malformed-response']];
        }
        if (empty($data['success'])) {
            return [
                'success' => false,
                'errors' => isset($data['error-codes']) && is_array($data['error-codes']) ? array_values(array_map('strval', $data['error-codes'])) : [],
            ];
        }

        $score = isset($data['score']) ? (float) $data['score'] : 0.0;
        $action = isset($data['action']) ? (string) $data['action'] : '';
        if ($action !== $this->action || $score < $this->minScore) {
            return ['success' => false, 'errors' => ['score-or-action']];
        }

        return ['success' => true, 'errors' => []];
    }
}

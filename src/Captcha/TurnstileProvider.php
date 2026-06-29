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
    const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

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
}

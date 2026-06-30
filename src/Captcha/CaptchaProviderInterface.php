<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

namespace TecSpamGuard\Captcha;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface CaptchaProviderInterface
{
    public function getId();

    public function getLabel();

    public function getScriptUrl($langIso);

    public function getResponseFieldName();

    public function verify($token, $secret, $remoteIp);

    public function testKeys($siteKey, $secret);
}

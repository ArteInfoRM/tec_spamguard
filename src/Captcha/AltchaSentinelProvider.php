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

class AltchaSentinelProvider extends AltchaProvider
{
    private $baseUrl;
    private $apiKey;

    public function __construct($baseUrl, $apiKey, $hideFooter = false, $hideLogo = false)
    {
        parent::__construct($hideFooter, $hideLogo);
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->apiKey = (string) $apiKey;
    }

    public function getId()
    {
        return 'altcha_sentinel';
    }

    public function getLabel()
    {
        return 'ALTCHA Sentinel';
    }

    public function getChallengeUrl()
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            return '';
        }

        return $this->baseUrl . '/v1/challenge?apiKey=' . rawurlencode($this->apiKey);
    }

    public function verify($token, $secret, $remoteIp)
    {
        unset($remoteIp);

        if ((string) $token === '' || $this->baseUrl === '' || (string) $secret === '') {
            return ['success' => false, 'errors' => ['missing-input']];
        }

        $ch = curl_init($this->baseUrl . '/v1/verify/signature');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'payload' => (string) $token,
            'apiKey' => $this->apiKey,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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
            return ['success' => false, 'errors' => ['transport: http=' . $httpCode . ($curlErr ? ' err=' . $curlErr : '')]];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'errors' => ['malformed-response']];
        }

        return ['success' => !empty($data['verified']), 'errors' => []];
    }

    public function testKeys($siteKey, $secret)
    {
        $siteKey = rtrim(trim((string) $siteKey), '/');
        $secret = trim((string) $secret);

        if ($siteKey === '' || $secret === '') {
            return [
                'success' => false,
                'message' => 'ALTCHA Sentinel URL and API key must both be set.',
            ];
        }

        if (!filter_var($siteKey, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'ALTCHA Sentinel URL must be a valid absolute URL.',
            ];
        }

        $scheme = parse_url($siteKey, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return [
                'success' => false,
                'message' => 'ALTCHA Sentinel URL must use HTTP or HTTPS.',
            ];
        }

        $ch = curl_init($siteKey . '/v1/challenge?apiKey=' . rawurlencode($secret));
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
            return [
                'success' => false,
                'message' => 'ALTCHA Sentinel challenge request failed: HTTP ' . $httpCode . ($curlErr ? ' - ' . $curlErr : ''),
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['challenge'])) {
            return [
                'success' => false,
                'message' => 'ALTCHA Sentinel returned an invalid challenge response.',
            ];
        }

        return [
            'success' => true,
            'message' => 'ALTCHA Sentinel configuration appears to be valid.',
        ];
    }
}

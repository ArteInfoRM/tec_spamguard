<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

declare(strict_types=1);

namespace TecSpamGuard\Protection;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Short-lived signed form metadata, independent from CAPTCHA verification. */
final class FormProof
{
    /** Return the fifty allowed, non-personal field names. */
    public static function names(): array
    {
        $names = [];
        foreach (['entry', 'record', 'detail', 'request', 'reference'] as $prefix) {
            foreach (['note', 'extra', 'memo', 'hint', 'tag', 'label', 'remark', 'appendix', 'annotation', 'supplement'] as $suffix) {
                $names[] = $prefix . '_' . $suffix;
            }
        }

        return $names;
    }

    /** Issue a proof bound to a shop, session and form, with the chosen field name. */
    public static function issue(string $secret, string $session, int $shop, string $form, string $field): string
    {
        $data = [$shop, $form, $field, time(), bin2hex(random_bytes(16)), hash_hmac('sha256', $session, $secret)];
        $payload = base64_encode(json_encode($data));

        return $payload . '.' . hash_hmac('sha256', $payload, $secret);
    }

    /** Validate shape, signature, binding and age before using any submitted metadata. */
    public static function verify($token, string $secret, string $session, int $shop, string $form): array
    {
        if (!is_string($token) || strlen($token) > 1024 || substr_count($token, '.') !== 1 || $session === '') {
            return [];
        }
        list($payload, $signature) = explode('.', $token, 2);
        if (!hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return [];
        }
        $data = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($data) || count($data) !== 6 || $data[0] !== $shop || $data[1] !== $form
            || !in_array($data[2], self::names(), true) || !is_int($data[3])
            || $data[3] > time() + 30 || $data[3] < time() - 1800
            || !is_string($data[4]) || !preg_match('/^[a-f0-9]{32}$/D', $data[4])
            || !is_string($data[5]) || !hash_equals(hash_hmac('sha256', $session, $secret), $data[5])) {
            return [];
        }

        return ['field' => $data[2], 'nonce' => $data[4], 'remaining' => max(1, $data[3] + 1800 - time())];
    }
}

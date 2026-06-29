<?php

/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
 */

namespace TecSpamGuard\Validation;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmailValidator
{
    private $blockedEmails;
    private $blockedDomains;
    private $blockedPatterns;
    private $blockDisposable;
    private $disposableFile;

    public function __construct(array $blockedEmails, array $blockedDomains, array $blockedPatterns, $blockDisposable, $disposableFile)
    {
        $this->blockedEmails = $blockedEmails;
        $this->blockedDomains = $blockedDomains;
        $this->blockedPatterns = $blockedPatterns;
        $this->blockDisposable = (bool) $blockDisposable;
        $this->disposableFile = (string) $disposableFile;
    }

    public function isAllowed($email)
    {
        $email = \Tools::strtolower(trim((string) $email));
        if (!\Validate::isEmail($email)) {
            return true;
        }

        if (in_array($email, $this->blockedEmails, true)) {
            return false;
        }

        $domain = $this->getDomain($email);
        if ($domain !== '' && in_array($domain, $this->getBlockedDomains(), true)) {
            return false;
        }

        foreach ($this->blockedPatterns as $pattern) {
            if ($this->matchesWildcard($pattern, $email)) {
                return false;
            }
        }

        return true;
    }

    private function getDomain($email)
    {
        $parts = explode('@', (string) $email, 2);

        return isset($parts[1]) ? \Tools::strtolower($parts[1]) : '';
    }

    private function getBlockedDomains()
    {
        $domains = $this->blockedDomains;
        if ($this->blockDisposable && is_file($this->disposableFile)) {
            $domains = array_merge($domains, $this->parseLines((string) \Tools::file_get_contents($this->disposableFile)));
        }

        return array_values(array_unique(array_filter($domains)));
    }

    private function parseLines($content)
    {
        $lines = preg_split('/\R/', (string) $content);
        $result = [];
        foreach ($lines as $line) {
            $line = \Tools::strtolower(trim((string) $line));
            if ($line !== '' && strpos($line, '#') !== 0) {
                $result[] = $line;
            }
        }

        return $result;
    }

    private function matchesWildcard($pattern, $value)
    {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            return false;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';

        return (bool) preg_match($regex, (string) $value);
    }
}

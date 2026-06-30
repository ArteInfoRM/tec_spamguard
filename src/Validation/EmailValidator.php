<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

namespace TecSpamGuard\Validation;

if (!defined('_PS_VERSION_')) {
    exit;
}

class EmailValidator
{
    /**
     * Parsed disposable domains by file path.
     *
     * @var array
     */
    private static $disposableDomainsCache = [];

    private $blockedEmails;
    private $blockedDomains;
    private $blockedPatterns;
    private $blockDisposable;
    private $disposableFile;
    private $blockedDomainMap;

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
        if ($domain !== '' && isset($this->getBlockedDomainMap()[$domain])) {
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

    /**
     * Return blocked domains as a lookup map.
     *
     * @return array
     */
    private function getBlockedDomainMap()
    {
        if (is_array($this->blockedDomainMap)) {
            return $this->blockedDomainMap;
        }

        $domains = [];
        foreach ($this->blockedDomains as $domain) {
            $domain = \Tools::strtolower(trim((string) $domain));
            if ($domain !== '') {
                $domains[$domain] = true;
            }
        }

        if ($this->blockDisposable && is_file($this->disposableFile)) {
            foreach ($this->getDisposableDomains($this->disposableFile) as $domain) {
                $domains[$domain] = true;
            }
        }

        $this->blockedDomainMap = $domains;

        return $this->blockedDomainMap;
    }

    /**
     * Return parsed disposable domains for a file path.
     *
     * @param string $file Disposable domains file path
     *
     * @return array
     */
    private function getDisposableDomains($file)
    {
        $file = (string) $file;
        if (!isset(self::$disposableDomainsCache[$file])) {
            self::$disposableDomainsCache[$file] = $this->parseLines((string) \Tools::file_get_contents($file));
        }

        return self::$disposableDomainsCache[$file];
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

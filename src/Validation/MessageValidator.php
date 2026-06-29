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

class MessageValidator
{
    private $blockedTexts;
    private $maxLinks;

    public function __construct(array $blockedTexts, $maxLinks)
    {
        $this->blockedTexts = $blockedTexts;
        $this->maxLinks = max(0, (int) $maxLinks);
    }

    public function isAllowed($message)
    {
        $message = trim((string) $message);
        if ($message === '' || \Tools::strlen($message) < 3) {
            return true;
        }

        $lower = \Tools::strtolower($message);
        foreach ($this->blockedTexts as $text) {
            if ($text !== '' && strpos($lower, \Tools::strtolower($text)) !== false) {
                return false;
            }
        }

        if ($this->countLinks($message) > $this->maxLinks) {
            return false;
        }

        return true;
    }

    private function countLinks($message)
    {
        preg_match_all('#https?://|www\.#i', (string) $message, $matches);

        return count($matches[0]);
    }
}

<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

declare(strict_types=1);

namespace TecSpamGuard\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

class B2bRequestLoginForm extends AbstractForm
{
    public function getType(): string
    {
        return 'login';
    }

    public function isSubmitted(): bool
    {
        return $this->input('submit_b2brequest_step') === 'login';
    }

    public function getEmail(): string
    {
        return $this->input('login_email');
    }
}

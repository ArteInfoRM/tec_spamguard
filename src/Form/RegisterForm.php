<?php

/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
 */

namespace TecSpamGuard\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RegisterForm extends AbstractForm
{
    public function getType()
    {
        return 'register';
    }

    public function isSubmitted()
    {
        if (version_compare(_PS_VERSION_, '8.0', '<')) {
            return $this->context->controller instanceof \AuthController
                && ($this->hasSubmit('submitCreate') || $this->hasSubmit('submitAccount'));
        }

        return class_exists('RegistrationController')
            && $this->context->controller instanceof \RegistrationController
            && $this->hasSubmit('submitCreate');
    }

    public function getEmail()
    {
        return $this->input('email');
    }
}

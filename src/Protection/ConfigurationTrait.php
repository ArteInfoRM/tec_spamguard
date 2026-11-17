<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

namespace TecSpamGuard\Protection;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Configuration UI and strict server-side validation for advanced protection. */
trait ConfigurationTrait
{
    /** Return typed bounds and allowlists for a configuration section. */
    private function guardRules($abuse)
    {
        if ($abuse) {
            return [
                'ABUSE_ENABLED' => [0, 1], 'ABUSE_REGISTER' => [0, 1], 'ABUSE_CONTACT' => [0, 1],
                'ABUSE_PASSWORD' => [0, 1], 'ABUSE_MODE' => ['monitor', 'block'],
                'ABUSE_SCORE' => [1, 100], 'ABUSE_BUDGET' => [1, 100000], 'ABUSE_API_KEY' => 'secret',
            ];
        }
        $rules = ['GUARD_HONEYPOT' => [0, 1], 'GUARD_FIELD' => FormProof::names(), 'GUARD_LIMITS' => [0, 1]];
        foreach (['REGISTER', 'CONTACT', 'PASSWORD'] as $form) {
            $rules['GUARD_' . $form] = [0, 1];
            foreach (['CAPTCHA' => [1, 100], 'TRAP' => [1, 20], 'WINDOW' => [1, 1440], 'PAUSE' => [1, 1440],
                'ADMISSIONS' => [1, 1000], 'PERIOD' => [1, 1440], 'IP' => [1, 10000], 'COOLDOWN' => [0, 3600]] as $suffix => $range) {
                $rules['GUARD_' . $form . '_' . $suffix] = $range;
            }
        }

        return $rules;
    }

    /** Validate every submitted field before persisting any changes. */
    private function saveProtectionConfiguration($abuse)
    {
        $values = [];
        foreach ($this->guardRules($abuse) as $suffix => $rule) {
            $key = self::CONFIG_PREFIX . $suffix;
            $value = \Tools::getValue($key);
            if (!is_string($value)) {
                return $this->displayError($this->l('Invalid advanced protection setting.'));
            }
            if ($rule === 'secret') {
                $value = trim($value);
                if ($value === '' || strpos($value, '*') !== false) {
                    $value = (string) $this->guardConfig($suffix);
                }
                if ($value !== '' && !preg_match('/^[a-zA-Z0-9]{20,256}$/D', $value)) {
                    return $this->displayError($this->l('Invalid AbuseIPDB API key format.'));
                }
            } elseif (is_int($rule[0])) {
                if (!preg_match('/^(0|[1-9][0-9]{0,5})$/D', $value) || (int) $value < $rule[0] || (int) $value > $rule[1]) {
                    return $this->displayError($this->l('A protection limit is outside the allowed range.'));
                }
                $value = (int) $value;
            } elseif (!in_array($value, $rule, true)) {
                return $this->displayError($this->l('Invalid protection option.'));
            }
            $values[$key] = $value;
        }
        if ($abuse && $values[self::CONFIG_PREFIX . 'ABUSE_ENABLED'] === 1
            && $values[self::CONFIG_PREFIX . 'ABUSE_API_KEY'] === '' && !$this->isAbuseManagedByWaf()) {
            return $this->displayError($this->l('Enter an AbuseIPDB API key before enabling checks.'));
        }
        // Require one shop to avoid silently overwriting inherited multishop settings.
        if (\Shop::getContext() !== \Shop::CONTEXT_SHOP) {
            return $this->displayError($this->l('Select one shop before saving advanced protection settings.'));
        }
        foreach ($values as $key => $value) {
            if (!\Configuration::updateValue($key, $value, false,
                (int) $this->context->shop->id_shop_group, (int) $this->context->shop->id)) {
                return $this->displayError($this->l('The protection settings could not be saved.'));
            }
        }

        return $this->displayConfirmation($this->l('Protection settings saved.'));
    }

    /** Build two independent forms before the existing validation logs tab. */
    private function protectionForm($abuse)
    {
        $labels = [
            'GUARD_HONEYPOT' => $this->l('Enable invisible honeypot protection'),
            'GUARD_FIELD' => $this->l('Honeypot field name'),
            'GUARD_LIMITS' => $this->l('Enable attempt limits'),
            'ABUSE_ENABLED' => $this->l('Enable AbuseIPDB checks'),
            'ABUSE_API_KEY' => $this->l('AbuseIPDB API key'),
            'ABUSE_MODE' => $this->l('Reputation action'),
            'ABUSE_SCORE' => $this->l('Minimum abuse confidence score'),
            'ABUSE_BUDGET' => $this->l('Maximum API calls per day for this shop'),
        ];
        $formNames = ['REGISTER' => $this->l('Registration'), 'CONTACT' => $this->l('Contact'), 'PASSWORD' => $this->l('Password link request')];
        $suffixNames = [
            'CAPTCHA' => $this->l('CAPTCHA errors before suspension'),
            'TRAP' => $this->l('Filled honeypots before suspension'),
            'WINDOW' => $this->l('Error counting window (minutes)'),
            'PAUSE' => $this->l('Suspension duration (minutes)'),
            'ADMISSIONS' => $this->l('Maximum admitted submissions per identity'),
            'PERIOD' => $this->l('Submission counting window (minutes)'),
            'IP' => $this->l('Maximum admitted submissions per IP'),
            'COOLDOWN' => $this->l('Minimum interval per identity (seconds)'),
        ];
        foreach ($formNames as $form => $label) {
            $labels['GUARD_' . $form] = $label . ' - ' . $this->l('advanced protection');
            $labels['ABUSE_' . $form] = $label . ' - AbuseIPDB';
            foreach ($suffixNames as $suffix => $description) {
                $labels['GUARD_' . $form . '_' . $suffix] = $label . ' - ' . $description;
            }
        }
        $inputs = [];
        foreach ($this->guardRules($abuse) as $suffix => $rule) {
            $input = ['name' => self::CONFIG_PREFIX . $suffix, 'label' => $labels[$suffix], 'type' => 'text'];
            if ($rule === 'secret') {
                $input['maxlength'] = 256;
                $input['autocomplete'] = 'off';
                $input['desc'] = $this->l('Stored keys are masked. Leave empty or keep the mask to preserve the saved key.');
            } elseif ($rule === [0, 1]) {
                $input['type'] = 'switch';
                $input['is_bool'] = true;
                $input['values'] = $this->getSwitchValues();
            } elseif (!is_int($rule[0])) {
                $input['type'] = 'select';
                $options = [];
                foreach ($rule as $value) {
                    $options[] = ['id' => $value, 'name' => $value];
                }
                $input['options'] = ['query' => $options, 'id' => 'id', 'name' => 'name'];
            } else {
                $input['class'] = 'fixed-width-sm';
                $input['maxlength'] = 6;
                $input['desc'] = sprintf($this->l('Allowed range: %d to %d.'), $rule[0], $rule[1]);
            }
            $inputs[] = $input;
        }
        $description = $abuse
            ? $this->l('Checks run only on submitted forms after local validation. Results are cached for one hour. Unavailable checks do not block customers. No automatic abuse reports are sent. Enable successful validation logs to inspect monitor and unavailable outcomes. The web server must resolve trusted proxy addresses into REMOTE_ADDR. The daily budget is local to this shop; other modules and shops may share the account quota.')
            : $this->l('Native registration, checkout identity, contact and password-link forms are supported. Invisible checks also apply to logged-in customers. Every invalid request is rejected; thresholds control temporary suspensions. IP error thresholds are five times the session thresholds. Admitted submission counters reserve attempts before core processing, not confirmed deliveries. Registration uses session identity; contact and password requests use a hashed email identity. Existing open forms remain valid for up to 30 minutes after a field-name change.');
        if (!$abuse) {
            $this->context->smarty->assign('sg_advanced_description', $description);
            $description = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/advanced_help.tpl');
        }
        if ($abuse && $this->isAbuseManagedByWaf()) {
            $description = $this->l('AbuseIPDB is enabled in the Tec Block AI Bots WAF for this shop. Tec Spam Guard skips its own API checks while that option remains enabled. This does not imply that the WAF checks every form submission.') . ' ' . $description;
        }

        return ['form' => [
            'legend' => ['title' => $abuse ? $this->l('AbuseIPDB') : $this->l('Advanced protection'), 'icon' => 'icon-shield'],
            'description' => $description,
            'input' => $inputs,
            'submit' => ['title' => $this->l('Save')],
        ]];
    }
}

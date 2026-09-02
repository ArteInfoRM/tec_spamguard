<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

declare(strict_types=1);

namespace TecSpamGuard\Integration;

use TecSpamGuard\Form\FormInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface FormIntegrationInterface
{
    /**
     * Return a stable lowercase integration identifier.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Check whether the integration owns the current front-office page.
     *
     * @param \Context $context Current PrestaShop context
     *
     * @return bool
     */
    public function supportsCurrentPage(\Context $context): bool;

    /**
     * Return the standard form types exposed on the current page.
     *
     * @param \Context $context Current PrestaShop context
     *
     * @return string[]
     */
    public function getFormTypes(\Context $context): array;

    /**
     * Return the submitted form descriptor, or null when no protected form was submitted.
     *
     * @param \Context $context Current PrestaShop context
     *
     * @return FormInterface|null
     */
    public function getSubmittedForm(\Context $context): ?FormInterface;

    /**
     * Return additional CSS selectors keyed by standard form type.
     *
     * @return array<string, string[]>
     */
    public function getFormSelectors(): array;

    /**
     * Return the URL used after a rejected submission.
     *
     * @param \Context $context Current PrestaShop context
     * @param string $formType Standard protected form type
     *
     * @return string
     */
    public function getReturnUrl(\Context $context, string $formType): string;
}

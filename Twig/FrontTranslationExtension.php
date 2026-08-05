<?php

declare(strict_types=1);

namespace PayPlugOney\Twig;

use PayPlugOney\PayPlugOney;
use Thelia\Core\Translation\Translator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Translates the module's front-office templates against its own catalogue.
 *
 * The native `|trans` filter cannot be used here. It resolves through the Symfony translator,
 * which only carries the theme's `messages` catalogue — module catalogues are registered on
 * Thelia\Core\Translation\Translator instead, so `|trans({}, 'payplugoney.fo.flexy')` silently
 * falls back to the English source string.
 *
 * The Twig back-office bridges the two with a `translator` decorator, but it does so **only for
 * `/admin` requests** (deliberately, to leave the front office untouched) and it ships inside
 * the composer-installed `default-twig` theme, so it can neither be relied on nor extended from
 * here. Hence a module-local filter: it works regardless of which themes are installed.
 *
 * Back-office templates keep using `|trans({}, 'payplugoney.bo.default-twig')` — the decorator
 * covers them.
 */
final class FrontTranslationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('payplugoney_trans', $this->trans(...)),
        ];
    }

    /**
     * Not marked `is_safe`: the return value is plain text and must keep being autoescaped by
     * Twig. Catalogue entries are editable from the back-office.
     *
     * @param array<string, string|int|float> $parameters
     */
    public function trans(?string $message, array $parameters = []): string
    {
        return $this->translator->trans(
            $message,
            $parameters,
            PayPlugOney::FRONT_TRANSLATION_DOMAIN
        );
    }
}

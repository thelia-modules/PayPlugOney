<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use PayPlugOney\Service\Dto\OneySimulationOption;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\MoneyFormat;

/**
 * Renders the instalment breakdown of an Oney plan as HTML.
 *
 * Built in PHP rather than in a template, deliberately:
 *
 *  - The markup is consumed by PaymentModuleOption::setDescription(), which travels
 *    through the JSON API. It is a legal/financial disclosure, not themeable design.
 *  - On Thelia 3 it could not be rendered from a template anyway: ParserInterface resolves
 *    to ParserFallback, whose render() and setTemplateDefinition() are `: never` and throw.
 *  - A single implementation serves Thelia 2 and Thelia 3, instead of two twin templates in
 *    two different engines that would inevitably drift apart.
 *
 * SECURITY — this class is the only escaping barrier. The consuming theme has to output the
 * description with |raw (Twig autoescapes by default), and neither API Platform nor
 * json_encode neutralises tags. Therefore every interpolated value is escaped here:
 * translator strings are editable from the back-office, and currency formats come from the
 * database. Simulation figures are already cast to int/float by OneySimulationOption, which
 * makes them structurally safe.
 */
final class OneySimulationHtmlRenderer
{
    /**
     * Front-office translation domain: reuses the keys already shipped in
     * I18n/frontOffice/default rather than duplicating them under the module's root domain.
     */
    private const TRANSLATION_DOMAIN = 'payplugoney.fo.default';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function render(OneySimulationOption $option): string
    {
        $rows = [
            $this->buildRow(
                $this->bold($this->trans('For amount of : ')),
                $this->bold($this->money($option->orderAmountInCents))
            ),
            $this->buildDownPaymentRow($option),
        ];

        foreach ($option->installmentAmountsInCents as $index => $amountInCents) {
            $rows[] = $this->buildRow(
                $this->escape($this->trans('Installment n° ').($index + 1).' : '),
                $this->bold($this->money($amountInCents))
            );
        }

        $rows[] = $this->buildRow(
            $this->bold($this->trans('Total :')),
            $this->bold($this->money($option->getTotalAmountInCents()))
        );

        return \sprintf(
            '<span class="oneyPopin_option" data-type="%s"><ul>%s</ul></span>',
            $this->escape($option->code),
            implode('', $rows)
        );
    }

    private function buildDownPaymentRow(OneySimulationOption $option): string
    {
        // This single translation key intentionally carries markup (<b> around the two
        // substituted values), so the pattern is passed through unescaped while the values
        // themselves are escaped. It is the only such exception in this renderer.
        $financingCost = Translator::getInstance()->trans(
            '(Financing cost : <b>%cost</b> APR : <b>%apr %</b>)',
            [
                '%cost' => $this->money($option->totalCostInCents),
                '%apr' => $this->escape(number_format($option->effectiveAnnualPercentageRate, 2)),
            ],
            self::TRANSLATION_DOMAIN
        );

        return \sprintf(
            '<li><span>%s</span><span>%s</span><small>%s</small></li>',
            $this->trans('Intake : '),
            $this->bold($this->money($option->downPaymentAmountInCents)),
            $financingCost
        );
    }

    private function buildRow(string $label, string $value): string
    {
        return \sprintf('<li><span>%s</span><span>%s</span></li>', $label, $value);
    }

    private function bold(string $content): string
    {
        return '<b>'.$content.'</b>';
    }

    /**
     * Formats cents using the current currency. MoneyFormat is the only formatter available
     * on both Thelia 2 and Thelia 3.
     */
    private function money(int $amountInCents): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            // No request (CLI, worker): fall back to a plain decimal rather than crash.
            return $this->escape(number_format($amountInCents / 100, 2, ',', ' '));
        }

        return $this->escape(
            MoneyFormat::getInstance($request)->formatByCurrency($amountInCents / 100)
        );
    }

    private function trans(string $message): string
    {
        return $this->escape(Translator::getInstance()->trans($message, [], self::TRANSLATION_DOMAIN));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }
}

<?php
/**
 * Kommerce_HyvaThemeFix
 *
 * Sostituisce, in modo centralizzato e senza toccare il tema, i template di
 * rendering prezzo elencati in `templateMap` (configurabile via di.xml).
 *
 * Perché un plugin su setTemplate() e non un override di tema:
 * - sopravvive a qualunque aggiornamento di Hyvä (composer update),
 * - funziona identico su qualsiasi tema (Hyvä, Luma, un futuro redesign),
 * - è portabile: basta copiare il modulo in un nuovo progetto e installarlo.
 */

declare(strict_types=1);

namespace Kommerce\HyvaThemeFix\Plugin\Pricing;

use Magento\Framework\Pricing\Render\PriceBox;

class TemplateOverridePlugin
{
    /**
     * @var array
     */
    private $templateMap;

    /**
     * @param array $templateMap Mappa ['template_originale' => 'template_sostitutivo']
     *                           popolata via etc/di.xml
     */
    public function __construct(array $templateMap = [])
    {
        $this->templateMap = $templateMap;
    }

    /**
     * @param PriceBox $subject
     * @param string $template
     * @return array
     */
    public function beforeSetTemplate(PriceBox $subject, $template)
    {
        if (isset($this->templateMap[$template])) {
            return [$this->templateMap[$template]];
        }

        return [$template];
    }
}

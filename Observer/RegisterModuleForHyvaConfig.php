<?php
/**
 * Kommerce_HyvaThemeFix
 *
 * Registra il modulo in app/etc/hyva-themes.json così che `hyva-sources`
 * includa view/frontend/tailwind/module.css nel build Tailwind del tema.
 *
 * Vedi https://docs.hyva.io/hyva-themes/compatibility-modules/technical-deep-dive.html#registering-a-module-for-inclusion-in-hyva-themesjson
 */

declare(strict_types=1);

namespace Kommerce\HyvaThemeFix\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterModuleForHyvaConfig implements ObserverInterface
{
    private ComponentRegistrar $componentRegistrar;

    public function __construct(ComponentRegistrar $componentRegistrar)
    {
        $this->componentRegistrar = $componentRegistrar;
    }

    public function execute(Observer $event): void
    {
        $config = $event->getData('config');
        if ($config === null) {
            return;
        }

        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Kommerce_HyvaThemeFix');
        if ($path === null) {
            return;
        }

        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];

        // Path relativo alla root del progetto (BP), come da convenzione Hyvä.
        $extensions[] = ['src' => substr($path, strlen(BP) + 1)];

        $config->setData('extensions', $extensions);
    }
}

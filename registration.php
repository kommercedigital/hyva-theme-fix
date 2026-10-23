<?php
/**
 * Kommerce_HyvaThemeFix
 *
 * Corregge il rendering del prezzo "vecchio" (barrato) per prodotti simple,
 * configurable e bundle quando
 * Store > Configuration > Sales > Tax > Price Display Settings = "Including and Excluding Tax".
 *
 * Bug Magento core di riferimento: https://github.com/magento/magento2/issues/37989
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Kommerce_HyvaThemeFix',
    __DIR__
);

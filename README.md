# Kommerce_HyvaThemeFix

Modulo Magento 2 che corregge il rendering del prezzo quando **Store > Configuration >
Sales > Tax > Price Display Settings > Display Prices In Catalog** è impostato su
**"Including and Excluding Tax"** (entrambi i prezzi). Copre prodotti **simple/virtual/
downloadable**, **configurable** e **bundle**. Pensato per essere installato identico
in più progetti Hyvä (o Luma) senza mai toccare il tema.

## Installazione

```bash
composer config repositories.hyva-theme-fix vcs https://github.com/kommercedigital/hyva-theme-fix
composer require kommercedigital/hyva-theme-fix
bin/magento module:enable Kommerce_HyvaThemeFix
bin/magento setup:upgrade
bin/magento cache:flush
```

### Rigenerare il CSS di Tailwind (Hyvä)

Poiché il modulo aggiunge stili CSS specifici per Hyvä, è necessario rieseguire il build di Tailwind del tema per includere il nuovo `module.css` nel CSS finale. Il comando esatto dipende dal setup del tuo tema, ad esempio:

```bash
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm run build
```

Consulta la documentazione del tuo tema Hyvä per il comando corretto.

## I problemi che risolve

Con la visualizzazione "Both" attiva, quando un prodotto ha uno special price:

- il prezzo pieno IVA inclusa viene barrato correttamente,
- il suo corrispettivo **IVA esclusa non viene barrato** e non riceve l'etichetta
  "Tasse escluse", apparendo come un numero scollegato dal resto del price box.

**Causa:** i template `final_price.phtml` di Magento_Catalog, Magento_ConfigurableProduct
e Magento_Bundle (da cui derivano anche le versioni adattate da Hyvä) passano
`'skip_adjustments' => true` sul blocco `old-price`. Questo flag impedisce il calcolo
dell'aggiustamento fiscale (l'importo IVA esclusa) solo su quella riga. È un bug
Magento core documentato in <https://github.com/magento/magento2/issues/37989>, e Hyvä
stesso ha dovuto applicare più volte fix analoghi su
`Magento\Tax\Pricing\Render\Adjustment` per casi simili.

Nel template bundle il flag compare **in tre punti diversi** (caso "As low as" a
prezzo singolo, caso range "From/To", caso prezzo singolo senza range): tutti e tre
sono stati corretti.

## Come lo risolve

Invece di sovrascrivere i template nel tema (fragile, si "rompe" ad ogni
`composer update` di Hyvä, va duplicato per ogni progetto), il modulo:

1. registra un **plugin `beforeSetTemplate`** su
   `Magento\Framework\Pricing\Render\PriceBox`, la classe base reale da cui derivano
   `Magento\Catalog\Pricing\Render\FinalPriceBox` (simple) e, a cascata, le versioni
   configurable e bundle, per qualsiasi tipo di prodotto;
2. il plugin sostituisce, in base a una mappa dichiarata in `etc/di.xml`, il template
   originale con la versione corretta fornita dal modulo stesso;
3. i nuovi template sono identici agli originali, con l'unica differenza che
   `skip_adjustments` è stato rimosso;
4. per la **PDP** (che Hyvä renderizza con `Magento_Catalog::product/view/price.phtml`,
   non con `final_price.phtml`) il modulo fornisce gli stili in
   `view/frontend/tailwind/module.css`. Il modulo si registra in
   `app/etc/hyva-themes.json` tramite l'observer `hyva_config_generate_before`, così
   `hyva-sources` include il CSS nel build Tailwind del tema (meccanismo ufficiale
   Hyvä: <https://docs.hyva.io/hyva-themes/compatibility-modules/technical-deep-dive.html>).
   Gli stili raggruppano le quattro righe PDP in due righe chiare (prezzo pieno
   barrato + prezzo finale in evidenza, ciascuno con la quota IVA esclusa accanto) e
   stiliscono il badge sconto di PLP.

Il fix è **autoconsistente nel modulo**: l'unico passo richiesto sul tema è il
rebuild di Tailwind (`npm run build`), perché il CSS del modulo entra in
`styles.css` come per qualunque modulo Hyvä-compatible.

## File del modulo

```
Kommerce_HyvaThemeFix/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── di.xml                                          # plugin + mappa template
│   └── frontend/events.xml                             # registrazione in hyva-themes.json
├── Observer/RegisterModuleForHyvaConfig.php             # observer Hyvä
├── Plugin/Pricing/TemplateOverridePlugin.php            # plugin generico
└── view/frontend/
    ├── tailwind/module.css                             # stili PLP + PDP
    └── templates/product/price/
    ├── final_price.phtml                                # simple/virtual/downloadable
    ├── final_price_configurable.phtml                    # configurable
    └── final_price_bundle.phtml                          # bundle
```

## Installazione

```bash
# Opzione A — copia diretta in app/code
cp -r Kommerce Magento_ROOT/app/code/

# Opzione B — via composer (repository path/vcs)
composer config repositories.kommerce-hyva-theme-fix path ../packages/module-hyva-theme-fix
composer require kommerce/module-hyva-theme-fix:*

bin/magento module:enable Kommerce_HyvaThemeFix
bin/magento setup:upgrade         # rigenera anche app/etc/hyva-themes.json
bin/magento setup:di:compile      # se compiled mode attivo
bin/magento cache:flush

# Rebuild Tailwind del tema: il CSS del modulo entra in styles.css
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm run build
```

Se `app/etc/hyva-themes.json` non includesse il modulo (es. per cache
`compiled_config` rimasta stale su un'installazione esistente), forza la
rigenerazione con `bin/magento hyva:config:generate` e ripeti il rebuild del tema.

## Test

1. Store > Configuration > Sales > Tax > Price Display Settings:
   - Display Product Prices In Catalog = **Including and Excluding Tax**
2. Su un **prodotto simple** con special price attivo, verifica in PLP e PDP che:
   - il prezzo pieno sia barrato su entrambe le righe (IVA inclusa e IVA esclusa),
   - il prezzo finale risalti visivamente,
   - compaia il badge sconto (se configurato).
3. Su un **prodotto configurable**:
   - con special price sul prodotto padre → stesso comportamento del simple, visibile
     solo in PDP (in PLP il prezzo barrato non è mostrato per design, dato che le
     varianti possono avere prezzi diversi tra loro);
   - con varianti a prezzi diversi tra loro → verifica che compaia "A partire da X"
     con l'importo corretto sia IVA inclusa che esclusa.
4. Su un **prodotto bundle**:
   - con "Price View" impostato su "As low as" → una sola cifra, eventualmente barrata
     se c'è uno sconto;
   - con "Price Range" attivo e opzioni a prezzo variabile → verifica "Da X" e "A Y",
     entrambi correttamente barrati se scontati;
   - con un prezzo fisso unico → comportamento identico al prodotto simple.
5. Su prodotti **senza** special price, verifica che nulla sia cambiato rispetto a
     prima (il caso "prezzo base" non era interessato dal bug).

## Estendere il fix ai prodotti grouped

Il pattern è identico, perché il plugin è agganciato alla classe base condivisa da
tutti i product type:

1. Copia `vendor/magento/module-grouped-product/view/base/templates/product/price/final_price.phtml`
   in questo modulo, es. `final_price_grouped.phtml`.
2. Rimuovi `'skip_adjustments' => true` dove presente.
3. Aggiungi in `etc/di.xml`, dentro `templateMap`:

```xml
<item name="Magento_GroupedProduct::product/price/final_price.phtml" xsi:type="string">Kommerce_HyvaThemeFix::product/price/final_price_grouped.phtml</item>
```

Non l'ho incluso di default perché i prodotti grouped in genere non mostrano un
prezzo proprio da barrare (elencano i child product con i loro prezzi individuali):
verifica prima se il bug si presenta davvero nel tuo caso concreto.

## Nota sui template configurable/bundle

Le versioni corrette qui incluse ricalcano la struttura standard dei template core
(`getPriceType()`, `showMinimalPrice()`, `getPriceView()`, `showRangePrice()`, ecc.),
che è stabile da diverse minor version di Magento 2.4.x. Prima di andare in
produzione, confronta comunque questi due file con gli equivalenti reali della tua
installazione:

- `vendor/magento/module-configurable-product/view/base/templates/product/price/final_price.phtml`
- `vendor/magento/module-bundle/view/base/templates/product/price/final_price.phtml`

per intercettare eventuali differenze minori introdotte da patch successive alla tua
versione specifica, e per riportare eventuali personalizzazioni che Hyvä avesse già
applicato al layout (es. classi CSS aggiuntive) prima di sostituirle con le tue.

## Andare oltre: le etichette "Tasse incluse/escluse"

Con il fix sopra, la riga IVA esclusa del prezzo barrato torna a passare per lo
stesso meccanismo di `Magento\Tax\Pricing\Render\Adjustment` già usato (con
successo) dalla riga del prezzo finale — quindi eredita automaticamente la stessa
etichetta che già vedi funzionare correttamente oggi sul prezzo finale.

Se invece vuoi *forzare* un'etichetta esplicita e sempre visibile ("IVA esclusa" /
"IVA inclusa") anche dove Magento di norma non la mostra (il comportamento standard
è: solo il prezzo "non primario" rispetto alla configurazione riceve un'etichetta),
lo stesso identico meccanismo di override — plugin `beforeSetTemplate` + voce in
`templateMap` — si applica a `Magento\Tax\Pricing\Render\Adjustment` (template
`Magento_Tax::render/adjustment.phtml`). In quel caso parti dal file reale della tua
installazione (`vendor/magento/module-tax/view/base/templates/render/adjustment.phtml`)
come base, invece che da zero, per non rompere microdata/schema.org che Magento
inserisce in quel template.

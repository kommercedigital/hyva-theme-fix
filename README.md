# Kommerce_HyvaThemeFix

Modulo per **Magento 2.4.7+** con tema **Hyvä** che raccoglie correzioni e miglioramenti essenziali di rendering e stile per il frontend, separati in due aree principali:

1. **Fix Prezzi (Rendering e Stili)**: Corregge il calcolo e il rendering del prezzo barrato (special price) quando la configurazione prezzi è impostata su IVA inclusa ed esclusa (*Including and Excluding Tax*), coprendo prodotti simple, virtual, downloadable, configurable e bundle. Include inoltre gli stili Tailwind dedicati per PLP e PDP.
2. **Fix PageBuilder (Tipografia)**: Applica automaticamente la classe tipografica `prose` (Tailwind Typography) alle righe PageBuilder con layout *contained*, garantendo una corretta formattazione dei contenuti CMS su tema Hyvä.

Pensato per essere installato nei progetti Hyvä senza dover modificare i template del tema.

## Installazione

```bash
composer config repositories.hyva-theme-fix vcs https://github.com/kommercedigital/hyva-theme-fix
composer require kommercedigital/hyva-theme-fix
bin/magento module:enable Kommerce_HyvaThemeFix
bin/magento setup:upgrade
bin/magento cache:flush
```

### Rigenerare il CSS di Tailwind (Hyvä)

Poiché il modulo aggiunge stili CSS specifici per Hyvä, è necessario rieseguire la compilazione di Tailwind del tema per includere `module.css` (che include a sua volta `prices.css` e `pagebuilder.css`) nel CSS compilato finale:

```bash
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm run build
```

Il modulo si registra automaticamente in `app/etc/hyva-themes.json` tramite l'observer `hyva_config_generate_before`, consentendo a `hyva-sources` di scansionare e compilare i file Tailwind forniti dal modulo.

---

## 1. Fix Prezzi (Rendering e Stili)

### Il problema del rendering
Quando in **Store > Configuration > Sales > Tax > Price Display Settings > Display Prices In Catalog** è selezionato **"Including and Excluding Tax"** (entrambi i prezzi) e un prodotto ha uno special price:
- Il prezzo pieno IVA inclusa viene barrato correttamente.
- Il prezzo pieno **IVA esclusa non viene barrato** e non riceve la corretta etichetta, risultando disallineato e fuorviante.

**Causa:** I template `final_price.phtml` standard di Magento (`Magento_Catalog`, `Magento_ConfigurableProduct`, `Magento_Bundle`) passano `'skip_adjustments' => true` sul blocco `old-price`. Questo parametro inibisce il calcolo dell'aggiustamento fiscale (IVA esclusa) solo per quella riga (bug noto di Magento core: <https://github.com/magento/magento2/issues/37989>). Nel template bundle il flag compare in 3 punti differenti (prezzo singolo "As low as", range "From/To", prezzo singolo fisso).

### Come viene risolto nel backend
Invece di sovrascrivere file nel tema:
1. Registra un **plugin `beforeSetTemplate`** (`Plugin/Pricing/TemplateOverridePlugin.php`) su `Magento\Framework\Pricing\Render\PriceBox`.
2. Sostituisce i template core originali con le versioni corrette del modulo (`view/frontend/templates/product/price/`), in cui il parametro `skip_adjustments` è stato rimosso.

### Stili Tailwind Prezzi (`view/frontend/tailwind/prices.css`)
Fornisce le regole CSS necessarie per visualizzare chiaramente i prezzi su Hyvä:
- **PDP** (`Magento_Catalog::product/view/price.phtml`): Raggruppa i quattro valori (pieno IVA incl./escl. e finale IVA incl./escl.) in due righe ordinate (prezzo pieno barrato + prezzo finale in evidenza, ciascuno affiancato dalla quota IVA esclusa).
- **PLP / Liste / Widget** (`.kommerce-price-box`): Gestione visiva del badge sconto (`-X%`), prezzo pieno barrato, prezzo in evidenza e range di prezzo bundle / configurable ("A partire da").

---

## 2. Fix PageBuilder (`view/frontend/tailwind/pagebuilder.css`)

### Il problema con PageBuilder su Hyvä
I blocchi di contenuto creati con Magento PageBuilder (righe di testo, tabelle, liste, titoli) su Hyvä perdono spesso la formattazione tipografica standard poiché Tailwind effettua il reset CSS globale e non applica stili di default ai tag HTML generati nell'editor WYSIWYG/PageBuilder.

### Come viene risolto
Il file `pagebuilder.css` aggancia la direttiva `@apply prose;` di Tailwind Typography alle righe di PageBuilder con contenitore:

```css
[data-content-type="row"] {
    &[data-appearance="contained"] {
        @apply prose;
    }
}
```

Questo assicura che paragrafi, titoli, elenchi puntati, link e tabelle inseriti tramite PageBuilder mantengano una resa tipografica armoniosa, leggibile e coerente con il design system del tema Hyvä.

---

## File del modulo

```
Kommerce_HyvaThemeFix/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── di.xml                                          # Plugin + mappa template override
│   └── frontend/events.xml                             # Registrazione in hyva-themes.json
├── Observer/RegisterModuleForHyvaConfig.php             # Observer per inclusione Tailwind Hyvä
├── Plugin/Pricing/TemplateOverridePlugin.php            # Plugin intercettazione template prezzi
└── view/frontend/
    ├── tailwind/
    │   ├── module.css                                  # Entry point Tailwind (@import prices & pagebuilder)
    │   ├── prices.css                                  # Stili per price box PLP e PDP
    │   └── pagebuilder.css                             # Stili tipografici (prose) per PageBuilder
    └── templates/product/price/
        ├── final_price.phtml                           # Template simple/virtual/downloadable
        ├── final_price_configurable.phtml              # Template configurable
        └── final_price_bundle.phtml                    # Template bundle
```

---

## Test e Verifica

### Verifica Prezzi
1. In **Store > Configuration > Sales > Tax > Price Display Settings**:
   - Imposta **Display Product Prices In Catalog** = **Including and Excluding Tax**.
2. **Prodotto Simple** con Special Price:
   - Verifica in PLP e PDP che il prezzo pieno sia barrato su entrambe le righe (IVA inclusa ed esclusa) e che il prezzo finale sia in risalto.
3. **Prodotto Configurable**:
   - Con special price sul padre: verifica la corretta visualizzazione in PDP.
   - Con varianti a prezzi diversi: verifica l'indicazione "A partire da" con entrambi gli importi.
4. **Prodotto Bundle**:
   - Verifica i casi "As low as", "Price Range" (Da/A) e prezzo fisso: tutte le voci scontate devono mostrare entrambi i valori barrati.
5. **Prodotti senza sconto**:
   - Verifica che i prezzi base vengano mostrati regolarmente.

### Verifica PageBuilder
1. Crea o modifica una pagina CMS / Blocco contenente una riga PageBuilder con appearance **Contained**.
2. Inserisci elementi tipografici misti (intestazioni H2/H3, elenchi puntati/numerati, paragrafi formattati, tabelle o citazioni).
3. Verifica nel frontend che gli stili tipografici (`prose`) siano applicati correttamente garantendo leggibilità e spaziature adeguate.

---

## Estendere il fix ai prodotti grouped

Il pattern è identico, poiché il plugin è agganciato alla classe base condivisa da tutti i product type:

1. Copia `vendor/magento/module-grouped-product/view/base/templates/product/price/final_price.phtml` in questo modulo (es. `final_price_grouped.phtml`).
2. Rimuovi `'skip_adjustments' => true` dove presente.
3. Aggiungi in `etc/di.xml`, dentro `templateMap`:

```xml
<item name="Magento_GroupedProduct::product/price/final_price.phtml" xsi:type="string">Kommerce_HyvaThemeFix::product/price/final_price_grouped.phtml</item>
```

---

## Nota sui template configurable/bundle

Le versioni corrette fornite dal modulo ricalcano la struttura standard dei template Magento 2.4.7+ (`getPriceType()`, `showMinimalPrice()`, `getPriceView()`, `showRangePrice()`, ecc.). In caso di personalizzazioni specifiche del tema o patch particolari, confronta i file con:

- `vendor/magento/module-configurable-product/view/base/templates/product/price/final_price.phtml`
- `vendor/magento/module-bundle/view/base/templates/product/price/final_price.phtml`

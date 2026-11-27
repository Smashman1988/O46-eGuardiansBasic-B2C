# Smashman (Marcel) is the best!

## Versionierung und Baseline

Der [Optimierungstransfer zwischen B2C und B2B](docs/B2B-OPTIMIZATION-TRANSFER.md) dokumentiert je Paket die Messwerte, die getrennten Teststände und offene Übertragungen.

Vorbereitet am 2026-10-30: **GuardiansBasic B2C – Baseline 1.0.7**.
Die Plugin-Version ist `1.0.7`; für die spätere Veröffentlichung ist `v1.0.7`
vorgesehen. Die Abnahme im PlentyONE-Test-Pluginset steht noch aus.
Git-Tag und GitHub-Release werden erst nach erfolgreicher Abnahme erstellt.

Es gilt Semantic Versioning im Schema `MAJOR.MINOR.PATCH`:

- **PATCH:** Fehlerkorrekturen und kleine kompatible Verbesserungen.
- **MINOR:** Neue, rückwärtskompatible Funktionen.
- **MAJOR:** Grundlegende oder nicht rückwärtskompatible Änderungen.
- Die Versionsnummer bei Vorbereitung eines neuen Releases erhöhen, nicht für jeden Commit.
- `plugin.json` und beide Changelogs bei der Version synchron halten; Datum und Änderungsbeschreibungen beider Changelogs müssen übereinstimmen.
- GitHub-Versionstags verwenden das Format `vMAJOR.MINOR.PATCH`; jeder freigegebene Release erhält einen eigenen Git-Tag.
- Git-Tags erst nach erfolgreicher Abnahme erstellen; bestehende Versionstags niemals verändern oder wiederverwenden.

## Pluginset-unabhängige B2C-Ressourcen (2026-10-30)

Eigene Ressourcen verwenden in Twig `plugin_path('GuardiansBasic')`; der Name ist
in `plugin.json` definiert. Ceres ist dort als Abhängigkeit angegeben und verwendet
`plugin_path('Ceres')`, einschließlich `pluginPathCeres`. Amazon Pay verwendet
`plugin_path('AmazonPayCheckout')`, entsprechend den vorhandenen Aufrufen und
Konfigurationsschlüsseln in `resources/views/Content/AmazonPayCheckout/javascript.twig`.
Das jeweilige Plugin muss weiterhin im Pluginset aktiviert sein.
Siehe [PlentyONE-Dokumentation](https://developers.plentymarkets.com/en-gb/developers/main/plentyshop-plugins/theme-plugins.html).

Das Manifest wird dynamisch unter `documents/manifest.json` eingebunden. Seine
acht Icons verwenden `../images/icon/...`, relativ zur tatsächlichen Manifest-URL.
Damit bleiben sie im selben Pluginset und Plugin. Das JSON enthält kein Twig.
Eigenständige CSS- und JavaScript-Dateien enthielten keine festen Pluginset-Pfade.
Vorhandene relative Font-URLs und URLs unter `frontend/` bleiben unverändert.

### Bestandsaufnahme und Änderungen

Untersucht: 2.594 Dateien ohne Git-Metadaten. Gefunden und korrigiert: 104 Referenzen,
davon 100 auf GuardiansBasic, zwei auf Ceres und zwei auf AmazonPayCheckout.
Es verbleiben keine festen numerischen Pluginset-Ressourcenpfade im Repository.

| Geänderte Ressourcendatei | Korrigierte Referenzen |
| --- | ---: |
| `resources/documents/manifest.json` | 8 |
| `resources/views/Category/Content/CategoryContent.twig` | 1 |
| `resources/views/Category/Item/CategoryItem.twig` | 1 |
| `resources/views/Category/Item/CategoryItemBrands.twig` | 1 |
| `resources/views/Category/Item/Partials/CategoryListItem.twig` | 1 |
| `resources/views/Category/Item/Partials/CategoryListItem2.twig` | 1 |
| `resources/views/Item/SingleItem.twig` | 2 |
| `resources/views/PageDesign/PageDesign.twig` | 16 |
| `resources/views/PageDesign/Partials/Footer.twig` | 24 |
| `resources/views/PageDesign/Partials/Head.twig` | 46 |
| `resources/views/PageDesign/Partials/Header/Header.twig` | 3 |

Zusätzlich aktualisiert: diese README sowie `meta/documents/changelog_de.md` und
`meta/documents/changelog_en.md`. Nur die Ressourcenbasen wurden geändert;
Ladereihenfolge, Attribute, Bedingungen, Fallbacks, Dateinamen und Versionssuffixe
bleiben erhalten. Auf bereits geänderten Zeilen wurden abschließende Leerzeichen entfernt.

### Lokale Validierung und offene Prüfung

- Vollständige Nachsuche nach numerischen, allgemeinen, maskierten und URL-kodierten
  Pluginpfaden: keine festen Pluginset-Pfade verblieben. Die CDN-URLs in
  `resources/css/checkout.css` und der daraus erzeugten `resources/css/checkout.min.css` zeigen
  auf Zahlungs-/Versandbilder unter `frontend/Payments/` und sind keine Plugin-Ressourcen.
- Alle 15 JSON-Dateien sind parsebar; das Manifest enthält keine Twig-Syntax.
- Alle 57 unterschiedlichen konkret referenzierten eigenen Ressourcendateien und
  `images/vendors/` existieren lokal. Artikelabhängige Herstellerlogo-Dateinamen
  müssen zusätzlich mit realen Artikeldaten geprüft werden.
- Acht Manifest-Icons gegen vier synthetische Pluginset-Basen aufgelöst: 32/32 erfolgreich.
- Twig 3.30.0: neun der zehn geänderten Templates vor und nach der Änderung parsebar.
  `CategoryItemBrands.twig` verwendet in Zeile 26 bereits zuvor die ältere Syntax
  `for children in category.children if children.details[0]`, die Twig 3 nicht unterstützt.
  Dieser identische Bestandsbefund bleibt unverändert; keine neue Parserabweichung.
  Plenty-Funktionen und -Filter wurden für die Syntaxprüfung registriert, nicht ausgeführt.
- Twig-URL-Ausdrücke mit synthetischem `plugin_path()` für vier Pluginsets gerendert:
  368/368 erfolgreich, einschließlich des neuen Ausdrucks in `CategoryItemBrands.twig`.
  Herstellerlogo-Ersetzung mit fehlendem, leerem, ersetzbarem und externem Logo: 96/96 erfolgreich.
- Vergleich aller elf Ressourcendateien mit normalisierten Ressourcenbasen und
  abschließenden Leerzeichen: 11/11 ohne weitere Quelltextänderung.
- `git diff --check`: keine Fehler.

Dies ist lokale Validierung, keine PlentyONE- oder Shop-Abnahme. Die installierten
Ceres- und AmazonPayCheckout-Ressourcen liegen nicht in diesem Repository; ihre
Verfügbarkeit kann lokal nicht nachgewiesen werden. Bei der separaten Testbereitstellung
die tatsächlichen Ressourcen-URLs und HTTP-Status prüfen, einschließlich Flaggen,
Fonts, Manifest-Icons, Herstellerlogos sowie Amazon-Pay-CSS und -JavaScript.
Startseite, Kategorie, Suche, Artikelseite/Quickview und Checkout auf Layout,
Lazy Loading, Preloading, Skriptausführung und Amazon Pay prüfen.
Es wurden keine PlentyONE-Einstellungen, Pluginsets oder Bereitstellungen verändert.


## Lokale Theme-Prüfung

Die Referenz für die übernommenen Storefront-Funktionen ist B2B `1f154b9`. B2C verwendet `GuardiansBasic` und seine eigenen Kategorien, Sprachziele und Checkout-Einstellungen.

Mit den Dev-Abhängigkeiten aus `package-lock.json` baut `npm run build` die CSS- und JavaScript-Dateien. `npm test` prüft die Pluginidentität, die erzeugten Dateien, Preisformate und die API- und Kontodialoge. Bei Änderungen an ausgelieferten Bytes die betroffenen Einträge in `resources/asset-versions.json` erhöhen. Der Build überprüft die SHA-256-Werte im Manifest.

`npm test` führt auch `npm run test:plentyone` aus. Der Test parst produktive PHP-Dateien, löst Namespaces und Importe auf und meldet Fundstellen mit Datei und Zeile. Der versionierte Stand in `tests/fixtures/plentyone-stable7.json` ermöglicht die Offline-Prüfung. Bei Änderungen der Plattformfreigaben die beiden Namenslisten mit der [offiziellen Stable7-Referenz](https://developers.plentymarkets.com/en-gb/interface/stable7/NativePHPFunctionsAndClasses.html) abgleichen.

Die PHP-/Twig- und Browsertests verwenden lokale Testdaten. `tests/ceres-list-query.php` erwartet den Pfad zu Ceres `src`. `tests/seo_pagination.php` und `tests/pdp_render_contract.php` erwarten den Twig-Autoloader und den Pfad zur JSON-Ergebnisdatei. Der lokale Vergleichsbericht liegt unter `.cache/b2c-parity/Auftrag-04-Produktkarten-Grids.html`.

Größen auf den Karten und Merklistenoptionen stammen aus denselben konkreten Varianten. Die Produktliste liefert den Nettobestand ihrer bereits geladenen Varianten. Fehlende Bestände werden je Seitenaufruf für höchstens 100 weitere IDs gemeinsam gelesen und im Speicher wiederverwendet. Beim Speichern prüft IO die gewählte ID frisch. Beim Öffnen der Merkliste verwendet die Bereinigung die reguläre IO-Listenantwort. Ceres hält die Liste für die aktuelle Seite im Speicher und prüft sie bei einer Rückkehr aus dem Browser-Verlauf erneut.

### Lokale Browserprüfung

`npm run test:standalone` führt die Einzelprogramme mit Twig 2 und 3, PHP-Lint und dem Ceres-Merklistentest aus. Der Ceres-Test verwendet die im selben Lauf gerenderte Produktdetailseite. Die Laufprotokolle liegen unter `.cache/standalone/`. `npm run test:standalone -- --list` zeigt das Inventar. Auch `npm test` meldet fehlende Zuordnungen neuer Einzelprogramme.

Für den vollständigen Lauf können `O46_CERES_ROOT`, `O46_IO_ROOT`, `O46_CARD_PHOTOS`, `O46_TWIG2_AUTOLOAD`, `O46_TWIG_AUTOLOAD` und `O46_PHP` auf die lokalen Referenzprojekte, Testbilder, Autoloader und PHP zeigen. Unter Windows kann `O46_PHP_EXT` auf das Verzeichnis mit mbstring zeigen.

Nach `npm ci` stellt `npx playwright install chromium firefox webkit` die zu diesem Lockfile passenden lokalen Browser bereit. Die Twig-Fixtures benötigen PHP mit mbstring und einen Twig-Autoloader. Deren Pfade werden über `O46_PHP` und `O46_TWIG_AUTOLOAD` gesetzt.

Mit `npm run test:cross -- --project=chromium` läuft das Desktopprofil. `--project=iphone` startet lokale WebKit-Emulation, `--list` zeigt die verfügbaren Tests. Chrome und Edge verwenden die installierten Browserkanäle. HTML-Bericht, JSON und Fehlertraces liegen in `.cache/cross-browser/`. Die Fixture fängt Netzwerkanfragen ab. Eine CI-Ausführung würde einen separat eingerichteten Workflow erfordern. Für echtes iOS und den aktuellen Plenty-Katalog ist eine native Abnahme erforderlich.

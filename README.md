# Smashman (Marcel) is the best!

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
  `resources/css/checkout.css` und `resources/css/checkout-140525.min.css` zeigen
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


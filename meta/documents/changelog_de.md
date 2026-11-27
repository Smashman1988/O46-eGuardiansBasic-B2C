# Release Notes für GuardiansBasic

## Unveröffentlicht · B2C develop

- Produktkarten, Artikeldetailseite und Schnellansicht teilen ein Merklistenherz. Die gespeicherte Variante lässt sich direkt entfernen. Bei mehreren möglichen Varianten zeigt die Größenauswahl die konkreten Optionen. Gäste und Kunden sehen bestätigte Änderungen auch auf der Merklistenseite und in anderen Tabs.

- Auf schmalen Displays bleibt z. B. 179,88 € in einer Zeile. Bei längeren Beträgen kann die Währung weiterhin unter dem Betrag stehen.
- Warenkorbsymbol, Mengenanzeige und Abstände zu Konto und Merkliste orientieren sich wieder am ursprünglichen Header. Bei schmalen Displays bleibt die Logobreite erhalten. Lange Währungskürzel können unter dem Betrag stehen.
- Auf der Produktseite funktioniert der Merkliste-Fallback auch bei fehlender ID-Liste unter striktem Twig.

- Canonical und Sprachverweise stehen wieder im HTML-Head. Sechs historische CSS- und JS-Dateien sind für bereits gecachte Seiten unter den ursprünglichen Pfaden verfügbar.

- Die italienische und spanische Hauptsuche verwendet auch bei direkter Initialisierung zwölf Artikel je Seite.

- Menge und Summe im Header erscheinen erst mit dem bestätigten Warenkorbstand. Bei langsamer Sitzung entfällt die zwischenzeitliche Nullanzeige.

- Cachebare Header verwenden ein neutrales Warenkorbsymbol bis zur Aktualisierung in der Gast- oder Kundensitzung. Navigation, leere Warenkorbvorschau, Empfehlungen und IT-/ES-Suchvorschläge vermeiden wiederholte Datenzugriffe.

- Der Header verwendet auf cachebaren Seiten die Warenkorbmenge aus IO. Die Markenübersicht liest je Kachel die Markendetails einmal und liefert weniger HTML aus.

- Die Variantenauswahl verwendet einen Index je Artikeldatensatz. Infinite Scroll registriert beim Nachladen nur die Bilder der neu hinzugefügten Karten. Verspätete Variantenantworten können die aktuelle Auswahl und deren Kaufbutton nicht mehr überschreiben.

- Produktlisten halten für Größenanzeigen nur die erste benötigte Attributgruppe im Speicher. Die Schnellansicht überspringt die Warenkorb-, Merklisten- und Canonical-Abfragen der vollständigen Produktseite.
- Auf der Newsletter-Anmeldeseite erhalten die Textwidgets ihren Standard-Innenabstand bereits vor dem Laden des Hauptstylesheets.

- Produktdetailseite und Schnellansicht ermitteln verfügbare Größen und Farben in einem Durchlauf über die Varianten. Die Attributbild-Vorschau lädt gemeinsam verwendete Bilder je Aufruf einmal. Der Sprachwechsel verwendet vorhandene Übersetzungsdetails der aktuellen Kategorie.
- Die Warenkorbvorschau verwendet denselben Währungsdatensatz für alle Positionen und Summen.

- Der Seitenkopf löst den Pluginpfad für Schriften, Icons und Stylesheets einmal auf. Der Build entfernt doppelte Deklarationen aus dem Critical CSS jeder Seitenart. Historische Asset-Kopien und ungenutzte Theme-Dateien wurden entfernt.

- Die Größen- und Farbauswahl liest die Verkaufbarkeitsdaten mehrerer Varianten gemeinsam. Bei fehlenden oder mehrdeutigen Datensätzen verwendet sie die IO-Einzelprüfung.
- Login und Newsletter-Anmeldung laden das Facettenmodul, sobald ein Filterwidget vorhanden ist. Der frühere Fußball-Berater wurde aus dem Theme entfernt.

- Idealo überträgt Sonderzeichen in Positionsdaten korrekt und fragt identische Varianten je Bestellung einmal ab. Treffen derselbe Adtraction-Partner im Bestell- und URL-Referrer zusammen, lädt sein Skript einmal. Salenti erhält die bisherigen Adressfelder auch bei Anführungszeichen und Zeilenumbrüchen korrekt.

- Startseite und Warenkorb verwenden ihre responsiven Spaltenbreiten bereits vor dem Laden des Hauptstylesheets.
- Die Suchseite zeigt eine lokalisierte Hauptüberschrift mit Trefferzahl. Persönliche Systemseiten erhalten NOINDEX und behalten strengere bestehende Robots-Regeln.
- Produktdaten für Suchmaschinen verwenden vorhandene Variantenmaße mit Einheit und ein belegtes Preisablaufdatum, sofern verfügbar.
- Die Cookie-Informationen beschreiben die siebentägige Ausblendung des Newsletter-Popups. Die Warenkorbausgabe für den dataLayer liest die Positionen einmal.

- ShopBuilder-Kategorien prüfen die tatsächliche Seitengrenze mit Varianten-IDs. Die zwölf sichtbaren Produkte erhalten weiterhin alle Kartenfelder. Das Newsletter-Popup verwendet den Checkout-Kontext und die Seitenkonfiguration für seine Ausschlüsse.
- Direkte Variantenlinks, etwa nach einem Sprachwechsel, zeigen Größe und Farbe der verlinkten Variante an. Der Kaufbutton verwendet die aktuelle Sprache auch für Screenreader.
- Auch bei bereits gerenderten ShopBuilder-Artikellisten verwenden Canonical, Robots und Seitentitel die tatsächlich angezeigte Seite. Leere Folgeseiten bleiben auf NOINDEX.

- Der Sprachdialog nutzt die übersetzte Kategorie, Variante oder konfigurierte IO-Systemroute auch auf NOINDEX-Seiten. Fehlende Sprachziele werden im Dialog als nicht verfügbar angezeigt.
- Paginierte ShopBuilder-Kategorielisten prüfen die gemeldete Trefferzahl anhand der tatsächlich abrufbaren Artikeldokumente. Artikelzähler, Seitenlinks, Infinite Scroll und SEO verwenden dieselbe Seitengrenze.

- Der Kontoschalter verwendet in allen fünf Sprachen die übersetzte Beschriftung auch für Screenreader.
- Das Merken eines Artikels mit nur einer Variante verwendet auch ohne Größenauswahl dessen aktive Varianten-ID.
- Die Sprachauswahl bleibt nach dem Ceres-Mount im Dialog verfügbar. Kategorieseiten prüfen fremdsprachige Ziele anhand der zur Zielsprache geladenen Kategorie.
- Das Newsletter-Popup erscheint nach dem Scrollen oben rechts und verlinkt die lokale Anmeldung mit 7 % Rabatt. Der Sprachdialog zeigt die fünf Shopsprachen direkt im Theme an und hält die Navigation auf dem aktuellen Shophost.
- Produktbewertungen erscheinen einmal auf der Detailseite. Startseiten- und Aktionskarten verwenden die gewählte Währung und die lokalisierten Kartenbeschriftungen für die gesamte Liste. Der Footer verwendet denselben Pluginpfad für Zahlungs- und Versandlogos.
- Das Outlet46-Logo nutzt den Platz im kompakten Header besser und erreicht auf großen Bildschirmen 247 px Breite.
- Home & Living ersetzt Letzte Größe in den fünf Sprachmenüs. IT und ES durchsuchen den englischen Textindex mit lokalisierten Produktdaten. DE, EN und FR verwenden weiterhin IO.
- Galerie und Kaufbereich teilen sich ab 768 px eine Zeile. Einzelvarianten zeigen den Hinweis auf knappen Bestand nur bei bekannter Stückzahl. Auf kleinen Geräten nutzen Auswahl und Kaufbutton die volle Breite. Das Markenverzeichnis verlinkt belegte Buchstaben und die erste Zahlengruppe und benötigt keine Artikel- oder Facettenabfrage.
- Gefilterte, sortierte und mehrdeutige Folgeseiten liefern bereinigte Canonicals. Produkt- und Quickview-Links übernehmen die gewählte Währung einmalig. Frühe Menü-, Filter- und Warenkorbgeometrie sowie zwei ältere Twig-Vorlagen wurden korrigiert.
- Über die B2B-Commits bis einschließlich `1f154b9` abgeglichen. Produktkarten, Bildpriorität, Navigation, Suche, Filter, Galerie, Merklisten-, Konto- und Warenkorbdialoge verwenden die gemeinsamen Korrekturen.
- Paginierte Hauptlisten fragen zwölf Artikel ab. Teaser behalten ihre konfigurierte Anzahl. Sprachverweise auf Folgeseiten setzen einen belegten Zielbestand voraus.
- Bestätigte Mengenänderungen aktualisieren Summen und UVP-Anzeige. B2C zeigt die DE-Versandfreigrenze bei 54 € an. Die vorhandene Einzelkauf- und Bruttodarstellung bleibt maßgeblich.
- Reproduzierbare CSS-/JS-Lieferdateien mit inhaltsabhängigen Asset-Versionen und SHA-256-Prüfung.

## v1.0.7 (2026-10-30)

### Geändert

- Feste Pluginset-Ressourcenbasen in B2C-Twig-Templates durch `plugin_path()` für GuardiansBasic, Ceres und AmazonPayCheckout ersetzt; Dateinamen, Ladereihenfolge und Ressourcenattribute bleiben erhalten.
- Herstellerlogo-Ersetzungsziele dynamisch aufgelöst; vorhandene Fallbacks und Platzhalter bleiben erhalten.
- Manifest-Icons relativ zur dynamisch eingebundenen Manifest-URL aufgelöst, ohne Twig im JSON.

## v0.0.1 (2017-08-02)

### Hinzugefügt

- Initiale Plugin-Dateien.
- Funktionalität zum Überschreiben von CSS, Templates und Partials durch Aktivierung der Templates in der Plugin-Config.

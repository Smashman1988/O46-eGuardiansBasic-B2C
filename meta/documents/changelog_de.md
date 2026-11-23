# Release Notes für GuardiansBasic

## Unveröffentlicht · B2C develop

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

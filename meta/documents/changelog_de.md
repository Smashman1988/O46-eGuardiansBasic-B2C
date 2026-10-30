# Release Notes für CeresVanilla

## Unveröffentlicht (2026-10-30)

### Geändert

- Feste Pluginset-Ressourcenbasen in B2C-Twig-Templates durch `plugin_path()` für GuardiansBasic, Ceres und AmazonPayCheckout ersetzt; Dateinamen, Ladereihenfolge und Ressourcenattribute bleiben erhalten.
- Herstellerlogo-Ersetzungsziele dynamisch aufgelöst; vorhandene Fallbacks und Platzhalter bleiben erhalten.
- Manifest-Icons relativ zur dynamisch eingebundenen Manifest-URL aufgelöst, ohne Twig im JSON.

## v0.0.1 (2017-08-02)

### Hinzugefügt

- Initiale Plugin-Dateien.
- Funktionalität zum Überschreiben von CSS, Templates und Partials durch Aktivierung der Templates in der Plugin-Config.

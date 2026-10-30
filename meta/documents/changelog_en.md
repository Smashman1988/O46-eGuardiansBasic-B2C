# Release Notes for GuardiansBasic

## v1.0.7 (2026-10-30)

### Changed

- Replaced fixed plugin-set resource bases in B2C Twig templates with `plugin_path()` for GuardiansBasic, Ceres and AmazonPayCheckout, preserving filenames, load order and resource attributes.
- Resolved manufacturer-logo replacement targets dynamically while preserving existing fallbacks and placeholders.
- Resolved manifest icons relative to the dynamically linked manifest URL, without Twig in JSON.

## v0.0.1 (2017-08-02)

### Added

- Initial plugin files.
- Functionality for overriding CSS, templates and partials by activating the respective template in the plugin config.

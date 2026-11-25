# Release Notes for GuardiansBasic

## Unreleased · B2C develop

- The newsletter popup opens at the top right after scrolling and links to the local signup page for the 7% discount. The language dialog renders all five shop languages directly in the theme and keeps navigation on the current storefront host.
- Product pages display reviews once. Homepage and promotional cards share the selected currency and localized labels for each list. The footer reuses its plugin path for payment and shipping logos.
- The Outlet46 logo makes fuller use of the compact header and reaches a width of 247 px on large screens.
- Home & Living replaces Last Size in all five menus. Italian and Spanish use the English text index with localized product data. German, English and French retain native IO search.
- The gallery and purchase area share a row from 768 px. On small screens, options and the buy button use the full width. The brand directory links to populated initials and the first numeric group and loads without a product or facet query. Single variations show the low-stock quantity only when it is available.
- Filtered, sorted and ambiguous follow-up pages use clean canonicals. Product and Quickview links normalize the selected currency. Early menu, filter and basket styles and two legacy Twig templates have been corrected.
- Ported shared product cards, image loading, navigation, search, filters, gallery, wishlist, account and basket dialog fixes from B2B commits through `1f154b9`.
- Paginated main lists request twelve items. Teasers use their configured limits. Paginated alternate-language URLs require evidence of inventory on the target page.
- Confirmed quantity updates refresh basket totals and recommended-price comparisons. The B2C German shipping message uses the approved €54 threshold. Single-item purchases use the existing B2C gross-price display.
- Added reproducible CSS and JavaScript bundles with per-file asset versions and SHA-256 verification.

## v1.0.7 (2026-10-30)

### Changed

- Replaced fixed plugin-set resource bases in B2C Twig templates with `plugin_path()` for GuardiansBasic, Ceres and AmazonPayCheckout, preserving filenames, load order and resource attributes.
- Resolved manufacturer-logo replacement targets dynamically while preserving existing fallbacks and placeholders.
- Resolved manifest icons relative to the dynamically linked manifest URL, without Twig in JSON.

## v0.0.1 (2017-08-02)

### Added

- Initial plugin files.
- Functionality for overriding CSS, templates and partials by activating the respective template in the plugin config.

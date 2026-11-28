# Release Notes for GuardiansBasic

## Unreleased · B2C develop

- Product cards, product pages, quick view and the wish list confirm a change once the shared IO session read shows the requested state. If a recently saved guest variation disappears after navigating, the shop restores that variation and updates open wish list pages.

- Known multi-device limitation. If the same variation is removed and saved again on a second device, an older guest removal can delete the new account entry at the next sign-in.

- Signing in also transfers newly saved guest favourites when IO has already initialised its wishlist service for the guest.

- Confirmed wish list removals survive logout and a browser restart. When a signed-out customer removes a variation previously saved in their account, the shop applies the removal the next time they sign in to that account. This also covers confirmed out-of-stock removals.

- A wishlist write that finishes during session revalidation now refreshes the current session. The basket confirmation stays visible for three seconds after the latest confirmed addition. Legacy copies of the confirmation HTML are removed from browser storage for all five shop languages. Signing in or out immediately after a wishlist change waits for the write and its session reconciliation. Other open tabs pick up restored guest favourites.

- After sign-out, the same browser also shows the last confirmed account favourites. Signing in imports only new guest favourites. Personal entries stay in their respective customer accounts.

- The wish list picker shows sorted attribute choices and relevant pack sizes. Personal heart states are resolved in the browser. Signing out keeps personal wishlists in their respective accounts. With local storage available, guest favourites survive session expiry and merge into the current account on successful login.

- Product cards, size selection and the wishlist picker use saleable variations with positive net stock. IO checks the variation again when saving. Opening the wishlist removes confirmed sold-out or missing variations from the current owner’s list. Inconclusive stock checks retain saved entries.

- Wishlist variation IDs and attribute values are validated using PHP functionality supported by PlentyONE.

- Product cards, product details and quick view share a wishlist heart. Customers and guests can remove a saved variation directly or choose a specific variation when several match. Confirmed changes also appear on the wishlist page and in other tabs.

- On narrow screens, amounts such as 179,88 € fit on one line. Longer amounts can still wrap before the currency.
- Restored the original basket icon placement and spacing beside the account and wishlist. The mobile logo retains its width, and long currency labels can wrap beneath the amount.
- The product-page wishlist fallback also handles a missing ID list under strict Twig.

- Canonical and language links render in the HTML head again. Six historical CSS and JS files retain their original paths for cached pages.

- The Italian and Spanish search results use twelve items per page when IO initializes the search context directly.

- The header shows quantity and subtotal after basket hydration, including on slow connections.

- Cacheable headers use a neutral basket badge until the session updates it. Navigation, empty basket previews, recommendations and Italian/Spanish search suggestions avoid repeated data access.

- The header reads the basket quantity from IO on cacheable pages. The brand directory reads each tile's details once and sends less HTML.

- Variant selection reuses an index per product data set. Infinite scroll registers images on newly appended cards. Late variant responses can no longer overwrite the current selection or its buy button.

- Product lists retain only the first size attribute group needed by the card. Quickview skips basket, wishlist and canonical lookups used by the full product page.
- Newsletter signup text widgets receive their default padding before the main stylesheet loads.

- Product pages and quickview determine available sizes and colours in one pass over the variations. Each attribute image preview loads shared images once. Language switching reuses translation details already loaded for the current category.
- The basket preview uses the same currency data for all line items and totals.

- The page head resolves the theme asset path once for fonts, icons and stylesheets. The build removes duplicate critical CSS declarations for each page type. Historical asset copies and unused theme files have been removed.

- Size and colour selectors fetch saleability data for multiple variations together. Missing or ambiguous records use the original IO check.
- Login and newsletter sign-up pages load the facet module when a filter widget is present. The former football selector has been removed from the theme.

- Idealo preserves special characters in order lines and looks up each variation once per order render. When the order and URL refer to the same Adtraction partner, its script loads once. Salenti receives the existing address fields intact, including quotes and line breaks.

- The home page and basket use their responsive column widths before the main stylesheet loads.
- Search shows one localized main heading with the result count. Private system pages use NOINDEX and preserve stricter existing robots directives.
- Structured product data uses measured variation dimensions with units and a verified price expiry date when available.
- The cookie information describes the seven-day newsletter popup dismissal. The basket dataLayer output reads the basket items once.

- ShopBuilder categories verify the last page using variation IDs. The twelve displayed products still receive their full card data. The newsletter popup uses the checkout context and page configuration for its exclusions.
- Direct variation links, including language switches, show the linked size and colour. The basket button also uses the current language for its accessible name.
- Canonical URLs, robots and page titles now use the displayed page even when ShopBuilder supplies a previously rendered item list. Empty pages remain NOINDEX.

- The language picker uses the translated category, variation or configured IO system route on NOINDEX pages as well. Missing translations are shown as unavailable.
- Paginated ShopBuilder category lists verify the reported hit count against the documents the search actually returns. The displayed total, page links, infinite scroll and SEO use the same boundary.

- The account button also uses its translated label for screen readers in all five languages.
- Saving a single-variation product now uses its active variation ID when no size selector is rendered.
- The language picker survives the Ceres mount. Category pages verify translated destinations against the category loaded for the target language.
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

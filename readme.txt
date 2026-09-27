=== CalcForge ===
Contributors: mtariqsmd
Tags: block, gutenberg, mortgage, calculator, finance, loans, real estate, amortization
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

An interactive mortgage calculator block for the block editor, with live monthly payments, charts and an amortization schedule.

== Description ==

CalcForge adds a **Mortgage Calculator** block to the block editor. Drop it on any page and visitors get a working, self-contained mortgage calculator with live results, charts, and a full amortization schedule.

= Key features =

* **Live calculation.** Monthly payment, financed principal, total interest, and total paid update instantly as visitors type or drag a slider.
* **Down payment support.** Interest is correctly charged on the financed principal only, not the full purchase price.
* **Twenty-four built-in skins.** Twelve light and twelve dark: Classic Light, Elegant Dark, Ocean Blue, Sunset Warm, Forest Green, Midnight Violet, Rose Quartz, Minimal Slate, Royal Grape, Aqua Fresh, Mocha Cream, Cyber Neon, Emerald Nights, Crimson Dusk, Graphite, Copper Forge, Royal Sapphire, Amber Gold, Cobalt Blue, Fuchsia Bloom, Mint Fresh, Sandstone, Lemon Zest, and Steel Blue — chosen per block.
* **Colour and typography controls.** Override the accent, secondary accent, label, and field text/background/border colours, pick a font family, and adjust the payment figure's size and weight — all per block, without writing CSS.
* **Dependency-free SVG charts.** A payment-composition donut and a balance-over-time line chart with cumulative interest, drawn in plain SVG. No charting library, no external requests.
* **Amortization schedule.** Annual rows showing principal paid, interest paid, and remaining balance, collapsible on the front end.
* **Works without JavaScript.** The block is fully server-rendered, so results and the schedule are correct even with scripting disabled. JavaScript is progressive enhancement that adds live recalculation and the charts.
* **Responsive by container.** The layout adapts to the block's own width rather than the viewport, so columns stack, sliders wrap, and the schedule scrolls neatly inside narrow columns.
* **Customisable currency.** Set the symbol per block or site-wide, and place it before ($99) or after (99 €) the amount.
* **No tracking, no external services.** The plugin makes no network requests, loads no third-party fonts, and stores no personal data.

= Site-wide defaults =

Under **Settings → CalcForge** you can set the defaults every new calculator block starts from: currency symbol, interest rate, decimal precision, loan amount, down payment, term, default skin, default chart type, and whether the amortization table is shown by default. Individual blocks can still override any of these in the editor.

= Developer friendly =

All mortgage math lives in pure, reusable PHP functions, mirrored by an equivalent JavaScript implementation for instant client-side feedback. A parity test in the repository asserts the two agree exactly.

The plugin also exposes a REST endpoint and a set of documented actions and filters for extending defaults, results, currency, and asset loading.

== Installation ==

1. Upload the `calcforge` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress admin **Plugins** screen.
2. Activate the plugin. Go to **Plugins → Installed Plugins** and click **Activate** under CalcForge.
3. Optionally set your site-wide defaults under **Settings → CalcForge**.
4. Edit any post or page, open the block inserter, search for "Mortgage Calculator", and drag the block into the content area. It appears in the custom **CalcForge** category.

== Frequently Asked Questions ==

= Does the calculator work without JavaScript? =

Yes. The block is server-rendered: the initial results and amortization table are computed in PHP from the block's attributes, so the calculator is usable with scripting disabled. JavaScript only adds live recalculation while typing and draws the charts.

= Why does the amortization table show years instead of months? =

A 30-year loan produces 360 monthly rows, which would bloat every page load. Rows are aggregated annually to keep pages fast; the underlying schedule is still calculated month by month, so the per-year figures are accurate.

= Can I change the currency symbol? =

Yes. Set it per block using the block's "Currency symbol" setting, site-wide under **Settings → CalcForge**, or programmatically with the `calcforge_currency_symbol` filter. Each block can also place the symbol before the amount ($99) or after it (99 €) using the "Currency position" setting. Amounts are formatted with your locale's number separators.

= Is there a REST API? =

Yes. Send a `POST` request to `/wp-json/calcforge/v1/calculate` with a JSON body such as `{ "amount": 300000, "down_payment": 60000, "interest_rate": 6.5, "term_years": 30 }`. Pass `"with_schedule": true` to include the amortization schedule in the response.

The endpoint is a stateless calculator that returns only public arithmetic and no private data, so it is open to unauthenticated requests by design. If you need to require authentication, return `false` from the `calcforge_rest_calculate_allowed` filter.

= Can I insert the calculator with a shortcode? =

The block is the only supported insertion method. The underlying calculation functions are plain, reusable PHP, so you can register your own shortcode if you need one.

= Which actions and filters are available? =

**Filters**

* `calcforge_default_attributes` — override the default loan amount, rate, term, and other block attributes.
* `calcforge_default_settings` — override the default admin settings.
* `calcforge_calculation_result` — modify the computed result before output, for example to convert currency.
* `calcforge_currency_symbol` — replace the resolved currency symbol.
* `calcforge_enqueue_assets` — return `false` to disable the plugin's front-end CSS and JavaScript and bundle your own.
* `calcforge_rest_calculate_allowed` — return `false` to require authentication for the REST calculation endpoint.

**Actions**

* `calcforge_before_calculator_render` and `calcforge_after_calculator_render` — fired immediately before and after the block HTML is generated.
* `calcforge_settings_saved` — fired after the admin settings have been sanitized and saved.

= Does the plugin collect or transmit any data? =

No. See the Privacy section below.

= Requirements =

* WordPress 6.4 or higher.
* PHP 7.4 or higher.
* A block theme or a classic theme with the block editor enabled.

== Privacy ==

CalcForge does not collect, store, or transmit any personal data.

The calculator runs entirely in the browser and on your own server. Visitor input is never saved to the database, never sent to the plugin author, and never used for analytics. The only data the plugin persists is your own site-wide settings (currency symbol, defaults, and so on), stored in a single `calcforge_settings` option in your site's database. That option is deleted automatically when you uninstall the plugin.

== Changelog ==

= 1.2.0 =
* New: site-wide defaults for loan amount, down payment, loan term, skin, and chart type under Settings → CalcForge.
* New: per-block toggles to show/hide the results summary and the range sliders.
* New: currency symbol position option per block — before the amount ($99) or after (99 €).
* New: twelve additional design skins — Emerald Nights, Crimson Dusk, Graphite, Copper Forge, Royal Sapphire, Amber Gold, Cobalt Blue, Fuchsia Bloom, Mint Fresh, Sandstone, Lemon Zest, and Steel Blue (twenty-four in total).
* New: seven additional design skins — Midnight Violet, Rose Quartz, Minimal Slate, Royal Grape, Aqua Fresh, Mocha Cream, and Cyber Neon (twelve in total).
* New: per-block Colors panel to override accent, secondary accent, label text, and field text/background/border colors.
* New: font family selector for the calculator (theme default, modern sans, classic serif, monospace).
* New: the front-end bundle is now also enqueued from the render callback, so assets load correctly in templates and block widgets where `has_block()` cannot detect the block.
* Improved: layout now adapts to the block's container width — columns stack, sliders wrap, charts reflow, and the amortization table scrolls inside narrow columns.
* Fixed: editor script dependencies are now read from the generated asset manifest, preventing load-order issues in the block editor.
* Fixed: the compiled block metadata is regenerated on every build so the registered block name, editor handles, and CSS class names can no longer drift from the source.
* Fixed: font weight labels in the editor are now translatable.
* Removed: an unused empty bootstrap method, and the duplicated calculation helpers are no longer loaded twice at runtime.
* Removed: development-only files (tests, build tooling and the duplicated helper source) from the distributed plugin.

= 1.1.0 =
* New: five visual skins — Classic Light, Elegant Dark, Ocean Blue, Sunset Warm, Forest Green.
* New: range sliders paired with every number input for quick value adjustments.
* New: SVG charts — payment composition donut and balance over time line chart (no external libraries).
* New: block sidebar settings to show/hide charts, choose the chart type (donut, line, or both), toggle the amortization table, and override the payment font size and weight.

= 1.0.0 =
* Initial release: Mortgage Calculator block, settings page, REST endpoint, and amortization schedule.

== Upgrade Notice ==

= 1.2.0 =
Site-wide defaults for loan amount, down payment, loan term, skin, and chart type are now available under Settings → CalcForge.

= 1.0.0 =
Initial release.

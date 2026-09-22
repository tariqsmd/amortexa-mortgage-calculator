=== MT Gutenberg Blocks ===
Contributors: tariq
Tags: block, mortgage, calculator, finance, gutenberg
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A native Gutenberg block that adds an interactive mortgage calculator with live monthly payment results and an amortization schedule.

== Description ==

MT Gutenberg Blocks registers a dynamic Gutenberg block that renders an interactive mortgage calculator anywhere on your site.

Features:

* Live monthly payment calculation as visitors type.
* Slider + number input pairs for loan amount, down payment, rate, and term.
* Twelve skins — Classic Light, Elegant Dark, Ocean Blue, Sunset Warm, Forest Green, Midnight Violet, Rose Quartz, Minimal Slate, Royal Grape, Aqua Fresh, Mocha Cream, Cyber Neon.
* Per-block Colors panel: override the accent, secondary accent, labels, field text/background/border; plus a whole-calculator font family selector.
* Dependency-free SVG charts: payment composition donut and balance-over-time line chart with cumulative interest.
* Down payment support — interest is charged on the financed principal only.
* Annual amortization schedule (principal vs. interest vs. remaining balance per year).
* Fully server-rendered output: results display correctly even without JavaScript (charts are progressive enhancement).
* All calculation math lives in pure PHP helpers and is mirrored client-side for instant feedback.
* REST endpoint (`/wp-json/mtgb/v1/calculate`) for headless or third-party use.
* Developer friendly: actions and filters to modify defaults, results, currency, and assets.

The plugin follows WordPress Coding Standards, escapes all output, sanitizes all input, and is fully translatable.

= Shortcodes? =

Not yet — the block is the single supported insertion method. The calculation helpers are reusable if you want to register your own shortcode via the exposed PHP API.

== Installation ==

1. Upload the `mt-gutenberg-blocks` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins screen.
3. Edit any post or page, open the block inserter, and search for "MT Mortgage Calculator".
4. Optional: configure site-wide defaults under Settings → MT Mortgage Calculator.
5. Run `npm install` and `npm run build` inside the plugin folder when developing.

== Frequently Asked Questions ==

= Does the calculator work without JavaScript? =

Yes. The block is server-rendered: the initial results and amortization table are computed in PHP from the block's attributes. JavaScript only adds live recalculation while typing.

= Why does the amortization table show years instead of months? =

A 30-year loan would produce 360 table rows on every page load. Rows are aggregated annually to keep pages fast; the underlying schedule logic is monthly.

= Can I change the currency symbol? =

Yes — per block via its "Currency symbol" setting, site-wide via Settings → MT Mortgage Calculator, or programmatically with the `mtgb_currency_symbol` filter. Each block can also place the symbol before or after amounts via its "Currency position" setting.

= Is there a REST API? =

Yes. POST to `/wp-json/mtgb/v1/calculate` with JSON body `{ "amount": 300000, "interest_rate": 6.5, "term_years": 30, "down_payment": 60000 }`. Requests must include the standard `X-WP-Nonce` header.

= Which filters and actions are available? =

* Filter `mtgb_default_attributes` — change default loan amount, rate, term, etc.
* Filter `mtgb_calculation_result` — modify computed results before output.
* Filter `mtgb_currency_symbol` — override the currency symbol.
* Filter `mtgb_enqueue_assets` — return false to disable front-end CSS/JS and bundle your own.
* Action `mtgb_before_calculator_render` / `mtgb_after_calculator_render` — wrap block output.
* Action `mtgb_settings_saved` — fired after admin settings are updated.

== Changelog ==

= 1.2.0 =
* New: site-wide defaults for loan amount, down payment, loan term, skin, and chart type under Settings → MT Mortgage Calculator.
* New: per-block toggles to show/hide the results summary and the range sliders.
* New: currency symbol position option per block — before the amount ($99) or after (99 €).
* New: seven additional design skins — Midnight Violet, Rose Quartz, Minimal Slate, Royal Grape, Aqua Fresh, Mocha Cream, and Cyber Neon (twelve in total).
* New: per-block Colors panel to override accent, secondary accent, label text, field text/background/border colors.
* New: font family selector for the calculator (theme default, modern sans, classic serif, monospace).
* Improved: layout now adapts to the block's container width — columns stack, sliders wrap, charts reflow, and the amortization table scrolls inside narrow columns.
* Fixed: editor script dependencies are now read from the generated asset manifest, preventing load-order issues in the block editor.

= 1.1.0 =
* New: five visual skins — Classic Light, Elegant Dark, Ocean Blue, Sunset Warm, Forest Green.
* New: range sliders paired with every number input for quick value adjustments.
* New: SVG charts — payment composition donut and balance-over-time line chart (no external libraries).
* New: block sidebar settings to show/hide charts, choose chart type (donut, line, or both), toggle the amortization table, and override payment font size and weight.

= 1.0.0 =
* Initial release: MT Mortgage Calculator block, settings page, REST endpoint, amortization schedule.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

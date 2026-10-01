# CalcForge

A native WordPress block that adds an interactive mortgage calculator to any post or page — with live monthly payment results, down-payment support, an amortization schedule, twenty-four design skins, dependency-free SVG charts, and full server-side rendering that works without JavaScript.

---

## Features

| Feature | Details |
| --- | --- |
| Live calculations | Monthly payment, financed principal, total interest, and total paid update as visitors type. |
| Sliders | Every input is paired with a range slider (loan amount, down payment, rate, term), kept in sync both ways. |
| Twenty-four skins | Twelve light (Classic Light, Ocean Blue, Sunset Warm, Forest Green, Rose Quartz, Minimal Slate, Royal Grape, Aqua Fresh, Mocha Cream, Amber Gold, Cobalt Blue, Fuchsia Bloom, Mint Fresh, Sandstone, Lemon Zest, Steel Blue) and the dark set (Elegant Dark, Midnight Violet, Cyber Neon, Emerald Nights, Crimson Dusk, Graphite, Copper Forge, Royal Sapphire) — selectable per block. |
| Charts | Dependency-free SVG charts: payment-composition donut and balance-over-time line chart with cumulative interest; show/hide and pick the chart type per block. |
| Per-block styling | Colors panel to override accent, secondary accent, label text, and field text/background/border. Payment font family, size, and weight overrides. |
| Responsive layout | Columns stack, sliders wrap, charts reflow, and the amortization table scrolls inside narrow containers. |
| Amortization schedule | Annual rows (principal / interest / remaining balance) aggregated from month-by-month math. |
| No-JS support | The block is fully server-rendered; JavaScript is progressive enhancement only. |
| Settings page | Site-wide defaults for currency symbol, interest rate, decimal precision, amortization visibility, and starting values (Settings → CalcForge). |
| Shortcode | `[calcforge]` accepts every block attribute. Settings → Shortcode has an input per attribute and a live sample shortcode that rebuilds and copies as you change them. |
| Widget | Appearance → Widgets has a Mortgage Calculator widget for any sidebar or footer, wrapping the same shortcode. Blank fields follow the site defaults. |
| REST endpoint | `POST /wp-json/calcforge/v1/calculate` for headless/third-party use. Open by design, rate limited per client. |
| Extensible | Actions and filters around rendering, defaults, results, currency, and assets. |
| Standards | WPCS-clean (`phpcs.xml.dist`), fully translatable, escaped output, sanitized input, multisite-aware uninstall. |

## Installation

1. Copy the `calcforge` folder into `wp-content/plugins/` (or upload the zip via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin on the **Plugins** screen.
3. Insert the **Mortgage Calculator** block from the inserter (category: *calcforge*).
4. Optional: configure site-wide defaults under **Settings → CalcForge**.

The distributed plugin ships a compiled `build/` directory, so no build step is required to run it.

## Development

```bash
npm install             # install dependencies (@wordpress/scripts)
npm start               # development build with watch
npm run build           # production build -> build/
npm run lint:js         # ESLint (WordPress config)
npm run lint:css        # Stylelint (WordPress config)
npm run lint:pkg-json   # package.json lint
npm run lint:md         # Markdown lint
npm test                # parity test + jsdom checks for the settings UI
npm run test:admin      # jsdom checks for the settings tabs and shortcode builder
npm run make-pot        # regenerate languages/calcforge.pot
npm run render-assets   # rasterise .wordpress-org/assets into wp.org PNGs
npm run dist            # build dist/trunk, dist/tags/<version> and the release zip
npm run release         # build + make-pot + render-assets + dist
composer install        # PHPCS / WPCS dev tooling
composer lint           # WPCS phpcs against phpcs.xml.dist
```

### Architecture overview

Runtime PHP lives in a single file on purpose — WordPress.org reviewers can audit the whole plugin without chasing `require` statements. Only `src/render.php` is loaded separately, because it is the block's server-side template.

```text
calcforge.php  Everything: header, helpers, block registration,
                                render pipeline, assets, REST, settings, i18n,
                                activation/deactivation, option migration
uninstall.php                   Multisite-aware data deletion
src/block.json                  Block metadata (API v3) - editor script/style handles
src/index.js                    Registration + i18n wrapper
src/save.js                     Returns null (dynamic block)
src/render.php                  Server-rendered template (escaped output)
src/view.js                     Front-end enhancement (live recalc + schedule rebuild)
src/edit/index.js               Editor entry: inspector controls + live preview
src/edit/controls.js            Sidebar panels
src/edit/preview.js             Editor preview (mirrors front-end markup)
src/utils/calculator.js         JS mirror of the PHP math (source of truth: PHP)
src/utils/charts.js             SVG chart builders
src/editor.scss, src/style.scss Block editor and front-end styles

includes/class-widget.php        Classic widget wrapping the shortcode
includes/helpers.php             Development copy of the math, used by tests/parity.php
tests/parity.php                Asserts the PHP and JS implementations agree
tests/js/calc.mjs               JavaScript side of the parity test
tests/js/admin.mjs              jsdom checks for the settings tabs and shortcode builder
tools/make-pot.cjs              POT generator (PHP + JS + block.json)
tools/render-assets.cjs         SVG to PNG rasteriser for the directory listing
tools/build-dist.cjs            Release packaging (trunk / tags / zip)
.wordpress-org/assets/          Vector icon + banner sources for WordPress.org
```

`includes/helpers.php` is **not** shipped. It exists so the parity test can load the math in isolation; at runtime the main plugin file provides the same functions.

### Hooks reference

#### Filters

| Hook | Purpose |
| --- | --- |
| `calcforge_default_attributes` | Override default loan amount, rate, term, etc. |
| `calcforge_calculation_result` | Modify computed results before output (e.g., currency conversion). |
| `calcforge_currency_symbol` | Replace the currency symbol per render. |
| `calcforge_enqueue_assets` | Return `false` to disable plugin CSS/JS and bundle your own. |
| `calcforge_default_settings` | Override admin setting defaults. |
| `calcforge_rest_calculate_allowed` | Return `false` to require authentication for the REST endpoint. |
| `calcforge_rest_calculate_rate_limit` | Change the per-client request budget. Return `0` to disable. |
| `calcforge_rest_calculate_rate_window` | Change the rate limit window in seconds. |
| `calcforge_rate_limit_client_key` | Change the identifier used to bucket rate limited requests. |
| `calcforge_widget_fields` | Add or remove fields on the calculator widget. |

#### Actions

| Hook | Purpose |
| --- | --- |
| `calcforge_before_calculator_render` | Fires before block HTML is generated. |
| `calcforge_after_calculator_render` | Fires after block HTML is generated. |
| `calcforge_settings_saved` | Fires after admin settings are sanitized and saved. |

---

Publishing this plugin to WordPress.org is tracked in a local, untracked
`PUBLISHING.md` checklist rather than in this file.

## License

CalcForge is released under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

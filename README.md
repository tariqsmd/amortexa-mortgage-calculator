# Amortexa Mortgage Calculator

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
| Settings page | Site-wide defaults for currency symbol, interest rate, decimal precision, amortization visibility, and starting values (Settings → Amortexa). |
| Shortcode | `[amortexa-mortgage-calculator]` accepts every block attribute. Settings → Shortcode has an input per attribute and a live sample shortcode that rebuilds and copies as you change them. |
| Widget | Appearance → Widgets has a Mortgage Calculator widget for any sidebar or footer, wrapping the same shortcode. Blank fields follow the site defaults. |
| REST endpoint | `POST /wp-json/amortexa-mortgage-calculator/v1/calculate` for headless/third-party use. Open by design, rate limited per client. |
| Extensible | Actions and filters around rendering, defaults, results, currency, and assets. |
| Standards | WPCS-clean (`phpcs.xml.dist`), fully translatable, escaped output, sanitized input, multisite-aware uninstall. |

## Installation

1. Copy the `amortexa-mortgage-calculator` folder into `wp-content/plugins/` (or upload the zip via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin on the **Plugins** screen.
3. Insert the **Mortgage Calculator** block from the inserter (category: *amortexa-mortgage-calculator*).
4. Optional: configure site-wide defaults under **Settings → Amortexa**.

The distributed plugin ships a compiled `build/` directory, so no build step is required to run it.

### Hooks reference

#### Filters

| Hook | Purpose |
| --- | --- |
| `amortexa_default_attributes` | Override default loan amount, rate, term, etc. |
| `amortexa_calculation_result` | Modify computed results before output (e.g., currency conversion). |
| `amortexa_currency_symbol` | Replace the currency symbol per render. |
| `amortexa_enqueue_assets` | Return `false` to disable plugin CSS/JS and bundle your own. |
| `amortexa_default_settings` | Override admin setting defaults. |
| `amortexa_rest_calculate_allowed` | Return `false` to require authentication for the REST endpoint. |
| `amortexa_rest_calculate_rate_limit` | Change the per-client request budget. Return `0` to disable. |
| `amortexa_rest_calculate_rate_window` | Change the rate limit window in seconds. |
| `amortexa_rate_limit_client_key` | Change the identifier used to bucket rate limited requests. |
| `amortexa_widget_fields` | Add or remove fields on the calculator widget. |

#### Actions

| Hook | Purpose |
| --- | --- |
| `amortexa_before_calculator_render` | Fires before block HTML is generated. |
| `amortexa_after_calculator_render` | Fires after block HTML is generated. |
| `amortexa_settings_saved` | Fires after admin settings are sanitized and saved. |

---

## License

Amortexa is released under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

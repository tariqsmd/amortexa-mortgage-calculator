# CalcForge

A native WordPress block that adds an interactive mortgage calculator to any post or page — with live monthly payment results, down-payment support, an amortization schedule, twenty-four design skins, dependency-free SVG charts, and full server-side rendering that works without JavaScript.

- **Plugin name:** CalcForge
- **Slug:** `calcforge`
- **Block:** `calcforge/mortgage-calculator` (category: *calcforge*)
- **Text domain:** `calcforge`
- **License:** GPLv2 or later
- **Requires at least:** WordPress 6.4 / PHP 7.4

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
| REST endpoint | `POST /wp-json/calcforge/v1/calculate` for headless/third-party use, nonce-protected. |
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
npm test                # cross-language PHP/JS math parity test
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

includes/helpers.php            Development copy of the math, used by tests/parity.php
tests/parity.php                Asserts the PHP and JS implementations agree
tests/js/calc.mjs               JavaScript side of the parity test
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

#### Actions

| Hook | Purpose |
| --- | --- |
| `calcforge_before_calculator_render` | Fires before block HTML is generated. |
| `calcforge_after_calculator_render` | Fires after block HTML is generated. |
| `calcforge_settings_saved` | Fires after admin settings are sanitized and saved. |

---

## Publishing this plugin to WordPress.org

### 1. Compliance checklist

- [x] **GPLv2 or later** license, declared in the plugin header, `readme.txt`, `composer.json`, and shipped as `LICENSE`.
- [x] **No obfuscated code**, no phone-home tracking, no external service calls without disclosure.
- [x] **Escaped output / sanitized input** everywhere (`esc_html`, `esc_attr`, `wp_json_encode`, `sanitize_text_field`, clamped numerics).
- [x] **Nonces verified** for state-changing routes (`X-WP-Nonce` on the REST endpoint; Settings API handles the options form).
- [x] **Capability checks** — `manage_options` for settings, `activate_plugins` for activation.
- [x] **Unique prefixes** — functions, classes, options, hooks, and handles are all prefixed `calcforge_` / `CalcForge_` / `CALCFORGE_`.
- [x] **No development files shipped** — `npm run dist` uses an explicit allowlist, so `tests/` (which calls `shell_exec()`), `tools/`, and `node_modules/` can never reach a public release.
- [x] **Translation-ready** — every string goes through the `calcforge` text domain; the POT is generated from PHP, JS, and `block.json`.
- [x] **Multisite-aware uninstall** — deletes the settings option on every site in the network.
- [x] **Listing assets** — icon and banners generated from versioned vector sources.
- [ ] **Add screenshots** — the `== Screenshots ==` section is intentionally omitted for now, so the listing has no images yet. Add `screenshot-1.png` … `screenshot-N.png` to `.wordpress-org/assets/` and restore the section in `readme.txt` before the listing is live.
- [ ] **Run final QA** — `npm run release` passes, then activate on a clean install and insert the block.
- [ ] **Verify the slug** is still free at <https://wordpress.org/plugins/calcforge/> before submitting.

### 2. Build the submission package

```bash
npm run release
```

This produces:

```text
dist/
├── assets/                        listing artwork (sibling of trunk in SVN, NOT in the zip)
│   ├── icon.svg, icon-128x128.png, icon-256x256.png
│   └── banner-772x250.png, banner-1544x500.png
├── trunk/                         the plugin exactly as users install it
├── tags/1.2.0/                    immutable copy of the same tree
└── calcforge.zip <- upload this for review
```

> Do **not** use `wp-scripts plugin-zip`. It uses a hardcoded allowlist that omits `src/render.php`, which the render callback `include`s — the resulting plugin would fatal on the front end.

### 3. Submit for review

1. Log in to [WordPress.org](https://wordpress.org/plugins/developers/add/).
2. Paste the plugin **name** exactly as in the header: `CalcForge`.
3. Upload `dist/calcforge.zip`.
4. Accept the guidelines agreement and submit.

#### What happens next

- You get an automated email confirming the slug reservation. The slug should come out as `calcforge`.
- A human reviewer examines the code. Queues range from a few days to several weeks — do not resubmit duplicates while waiting.
- If changes are requested, reply in the same email thread with a new zip.
- On approval you receive `https://plugins.svn.wordpress.org/calcforge` with `trunk/`, `tags/`, and `assets/`.

### 4. Push releases to SVN

`npm run dist` already produces the exact SVN layout, so the commit is a copy.

```bash
svn co https://plugins.svn.wordpress.org/calcforge calcforge-svn

robocopy dist\trunk    calcforge-svn\trunk  /MIR
robocopy dist\assets   calcforge-svn\assets /MIR
robocopy dist\tags\1.2.0 calcforge-svn\tags\1.2.0 /MIR

svn ci -m "CalcForge 1.2.0"
```

The two `assets` directories are different things and must not be confused:

- `dist/assets/` — a **sibling of `trunk/`**. WordPress.org reads the icon, banners, and screenshots from here.
- `trunk/assets/` — CSS shipped **inside** the plugin. Never put listing artwork here.

WordPress.org serves the **highest numeric tag** matching `Stable tag` in `trunk/readme.txt`. Keep both in sync.

### 5. Versioning a release

Update these in lockstep, then re-run `npm run release`:

| Location | Field |
| --- | --- |
| `calcforge.php` | `Version:` header **and** `CALCFORGE_VERSION` constant |
| `src/block.json` (and the rebuilt `build/block.json`) | `"version"` |
| `readme.txt` | `Stable tag:` **and** a new `== Changelog ==` entry |
| `package.json` / `composer.json` | `"version"` |

`tools/build-dist.cjs` reads the version from the plugin header, so `dist/tags/` always matches it.

### 6. Keeping the listing healthy

- **Tested up to** — update `readme.txt` when major WordPress versions land; the directory flags stale listings.
- **Security reports** — monitor the Patchstack channel tied to your slug.
- **Support forum** — watch `wordpress.org/support/plugin/calcforge/`.
- **Translations** — become available on translate.wordpress.org automatically after release; re-run `npm run make-pot` whenever you add UI text.
- **Never edit published tags** — always cut a new tag; treat them as immutable.

### 7. Useful links

- Submission form: <https://wordpress.org/plugins/developers/add/>
- Detailed plugin guidelines: <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>
- SVN access and etiquette: <https://developer.wordpress.org/plugins/wordpress-org/manage-your-svn-repository/>
- readme.txt spec: <https://wordpress.org/plugins/readme.txt>
- Plugin assets guide: <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>

---

## License

CalcForge is released under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

# Mortgage Calculator Block

A native WordPress Gutenberg block that adds an interactive mortgage calculator anywhere in the block editor — with live monthly payment results, down-payment support, an annual amortization schedule, light/dark themes, a REST calculation endpoint, and full server-side rendering that works without JavaScript.

- **Plugin name:** Mortgage Calculator Block
- **Slug:** `mortgage-calculator-block`
- **Text domain:** `mortgage-calculator-block`
- **License:** GPLv2 or later
- **Requires at least:** WordPress 6.4 / PHP 7.4

---

## Features

| Feature | Details |
| --- | --- |
| Live calculations | Monthly payment, financed principal, total interest, and total paid update as visitors type. |
| Sliders | Every input is paired with a range slider (loan amount, down payment, rate, term), kept in sync both ways. |
| Twelve skins | Classic Light, Elegant Dark, Ocean Blue, Sunset Warm, Forest Green, Midnight Violet, Rose Quartz, Minimal Slate, Royal Grape, Aqua Fresh, Mocha Cream, Cyber Neon — selectable per block. |
| Charts | Dependency-free SVG charts: payment-composition donut and balance-over-time line chart with cumulative interest; show/hide and pick the chart type per block. |
| Typography | Per-block payment font size and weight overrides. |
| Amortization schedule | Annual rows (principal / interest / remaining balance) aggregated from month-by-month math. |
| No-JS support | The block is fully server-rendered; JavaScript is progressive enhancement only. |
| Settings page | Site-wide defaults for currency symbol, interest rate, decimal precision, and amortization visibility (Settings → Mortgage Calculator). |
| REST endpoint | `POST /wp-json/mcb/v1/calculate` for headless/third-party use, nonce-protected. |
| Extensible | Actions and filters around rendering, defaults, results, currency, and assets. |
| Standards | WPCS-ready (`phpcs.xml.dist`), fully translatable, escaped output, sanitized input. |

## Installation

1. Copy the `mortgage-calculator-block` folder into `wp-content/plugins/` (or upload the zip via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin on the **Plugins** screen.
3. Insert the **Mortgage Calculator** block from the inserter (category: *Mortgage Tools*).
4. Optional: configure defaults under **Settings → Mortgage Calculator**.

The repository ships a compiled `build/` directory, so no build step is required to run it.

## Development

```bash
npm install        # install dependencies (@wordpress/scripts)
npm run start      # development build with watch
npm run build      # production build → build/
npm run lint:js    # ESLint (WordPress config)
npm run lint:css   # Stylelint (WordPress config)
composer install   # dev tooling
composer lint      # WPCS phpcs against phpcs.xml.dist
```

Source lives in `src/`; webpack extends the default `@wordpress/scripts` config only to compile the extra front-end `view.js` entry.

### Architecture overview

```
src/block.json          Block metadata (API v3) — editor script/style handles
src/index.js            Registration + i18n wrapper
src/edit.js             Inspector controls + live preview (mirrors front-end markup)
src/save.js             Returns null (dynamic block)
src/render.php          Server-rendered template (escaped output)
src/view.js             Front-end enhancement (live recalc + schedule rebuild)
src/utils/calculator.js JS mirror of the PHP math (single source of truth: PHP)
includes/helpers.php    Pure, unit-testable mortgage math + attribute sanitizing
includes/class-mcb-block-registration.php  register_block_type + render pipeline
includes/class-mcb-assets.php              Conditional enqueue (has_block + widgets)
includes/class-mcb-rest-api.php            POST mcb/v1/calculate
includes/class-mcb-settings.php            Settings API screen
includes/class-mcb-i18n.php                Text domain loading
```

### Hooks reference

**Filters**

| Hook | Purpose |
| --- | --- |
| `mcb_default_attributes` | Override default loan amount, rate, term, etc. |
| `mcb_calculation_result` | Modify computed results before output (e.g., currency conversion). |
| `mcb_currency_symbol` | Replace the currency symbol per render. |
| `mcb_enqueue_assets` | Return `false` to disable plugin CSS/JS and bundle your own. |
| `mcb_default_settings` | Override admin setting defaults. |

**Actions**

| Hook | Purpose |
| --- | --- |
| `mcb_before_calculator_render` | Fires before block HTML is generated. |
| `mcb_after_calculator_render` | Fires after block HTML is generated. |
| `mcb_settings_saved` | Fires after admin settings are sanitized and saved. |

---

# Publishing this plugin to WordPress.org

This section is the complete submission playbook: what to prepare, how to submit for review, and how to push releases to the WordPress.org SVN repository once approved.

## 1. Before you submit — compliance checklist

WordPress.org reviews plugins against the [Plugin Developer Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) and automated checks. This plugin is already aligned, but verify before submitting:

- [x] **GPLv2 or later** license, declared in the plugin header, `readme.txt`, and `composer.json`.
- [x] **No obfuscated code**, no phone-home tracking, no external service calls without disclosure.
- [x] **Escaped output / sanitized input everywhere** (`esc_html__`, `esc_attr`, `wp_json_encode`, `sanitize_text_field`, clamped numerics).
- [x] **Nonces verified** for state-changing routes (`X-WP-Nonce` on the REST endpoint; Settings API handles the options form).
- [x] **Capability checks** (`manage_options` for settings, `edit_posts` fallback for editor REST calls).
- [x] **Unique prefixes** — functions/classes/options/hooks are prefixed `mcb_` / `MCB_` / `MortgageCalculatorBlock`.
- [x] **Translation-ready** — every string uses `MCB_TEXT_DOMAIN`; `languages/mortgage-calculator-block.pot` included.
- [x] **`readme.txt`** follows the standard format (Tested up to, Stable tag, Changelog, FAQ).
- [ ] **Run final QA**: `npm run build && npm run lint:js && npm run lint:css` pass; activate on a clean WordPress install and insert the block once.
- [ ] **Bump versions together** if releasing (see *Versioning a release* below).

Also validate the readme locally:

```bash
npm install -g wp-readme-parser   # or any validator
# WordPress.org also runs its own parser during review.
```

## 2. Create the submission package

The review system accepts either a zip upload or a public download link. Build a clean zip:

```powershell
# From the repository root (PowerShell)
git archive --format=zip --prefix=mortgage-calculator-block/ -o ../mortgage-calculator-block.zip HEAD
```

`git archive` respects `.gitignore`, so `node_modules/` never leaks into the package while the compiled `build/` directory does ship.

Double-check the zip contains, at minimum:

```
mortgage-calculator-block/
├── mortgage-calculator-block.php   ← valid plugin header
├── includes/, src/, build/, languages/, assets/
├── uninstall.php
└── readme.txt
```

## 3. Submit for review

1. Log in to [WordPress.org](https://wordpress.org/plugins/developers/add/) with your account.
2. Paste the plugin **name** exactly as in the header: `Mortgage Calculator Block`.
3. Provide the zip (or link) from step 2 and a short description.
4. Accept the guidelines agreement and submit.

**What happens next**

- You'll get an automated email confirming the plugin slug reservation (usually within minutes–hours). The slug should come out as `mortgage-calculator-block`.
- A human reviewer examines the code. Queues vary from a few days to several weeks — do not resubmit duplicates while waiting.
- If changes are requested, reply to the review email, fix the code, and re-upload through the same thread.
- On approval you receive your SVN repository:
  `https://plugins.svn.wordpress.org/mortgage-calculator-block`
  with `trunk/`, `tags/`, and `assets/` directories (10 MB commit limit per commit).

## 4. Push the first release to SVN

Once approved, publish `trunk` and tag it. Example using TortoiseSVN's bundled CLI or SlikSVN on Windows:

```bash
# 1. Check out the (empty) repository
svn co https://plugins.svn.wordpress.org/mortgage-calculator-block mcb-svn
cd mcb-svn

# 2. Copy plugin files into trunk/ (exclude repo-only files)
robocopy ..\mortgage-calculator-block trunk /E /XD node_modules .git .github /XF README.md composer.lock phpunit.xml.dist

# 3. Stage everything new
svn add --force .
svn add --force assets     # assets/ holds listing artwork, not code

# 4. Commit trunk
svn ci -m "Initial release of Mortgage Calculator Block 1.0.0"

# 5. Tag the release so users can pin to it
svn cp trunk tags/1.0.0 -m "Tag 1.0.0"
```

WordPress.org serves the **highest numeric tag** whose version matches the `Stable tag` in `trunk/readme.txt`. Keep both in sync.

### Listing assets (`assets/` directory)

These files power the plugin directory page and are **never** loaded by sites:

| File | Purpose |
| --- | --- |
| `assets/banner-772x250.(png\|jpg)` | Standard banner |
| `assets/banner-1544x500.(png\|jpg)` | Retina banner |
| `assets/icon-256x256.(png\|svg)` | Icon (also 128x128 / svg) |
| `assets/screenshot-1.png` | The calculator inserted in a page |
| `assets/screenshot-2.png` | Editor controls / settings screen |

Screenshots map to the `== Screenshots ==` section order if you add one to `readme.txt`.

## 5. Versioning a release

Every release updates these four places **in lockstep**, then re-runs the build:

| Location | Field |
| --- | --- |
| `mortgage-calculator-block.php` | `Version:` header **and** `MCB_VERSION` constant |
| `src/block.json` (+ rebuilt `build/block.json`) | `"version"` |
| `readme.txt` | `Stable tag:` **and** a new `== Changelog ==` entry |
| `package.json` / `composer.json` | `"version"` (optional but tidy) |

Release flow:

```bash
npm run build                      # refresh build/ artifacts
git commit -am "Release 1.0.1"     # repo history
git tag 1.0.1 && git push --tags   # repo tagging

cd mcb-svn
robocopy ..\mortgage-calculator-block trunk /E /XD node_modules .git
svn ci -m "Update to 1.0.1"
svn cp trunk tags/1.0.1 -m "Tag 1.0.1"
```

## 6. Keeping the listing healthy

- **Tested up to** — update `readme.txt` when major WordPress versions land; the directory flags stale listings.
- **Security reports** — monitor the [Patchstack](https://patchstack.com/wordpress-plugins/) channel tied to your slug; respond within the disclosed timeline.
- **Support forum** — watch `wordpress.org/support/plugin/mortgage-calculator-block/`; responsiveness affects listing quality signals.
- **Translations** — strings become available on translate.wordpress.org automatically after release; keep the POT current when adding UI text.
- **Never edit published tags** — always cut a new tag; treat them as immutable.

## 7. Useful links

- Submission form: https://wordpress.org/plugins/developers/add/
- Detailed plugin guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- SVN access & etiquette: https://developer.wordpress.org/plugins/wordpress-org/manage-your-svn-repository/
- readme.txt spec: https://wordpress.org/plugins/readme.txt
- Assets guide: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

---

## License

Mortgage Calculator Block is released under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

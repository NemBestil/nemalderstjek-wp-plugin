# Nem Alderstjek — WordPress Plugin

WordPress/WooCommerce plugin that adds age verification at checkout using **MitID** (Danish digital ID), via the NemAlderstjek.dk service. Products can be flagged with a minimum age requirement (15/16/18/21); if any item in the cart requires verification, the customer is redirected through MitID before the order completes. Used by Danish e-commerce stores that sell age-restricted goods (alcohol, tobacco, etc.).

The plugin also integrates with Stripe Express Checkout (Apple Pay / Google Pay) and DIBS/Nexi Checkout, and verification status is stored on the resulting order.

## Stack & compatibility

- **WordPress plugin**, requires WooCommerce.
- **Minimum PHP: 7.4** — do not use PHP 8.0+ features. Common pitfalls to avoid:
  - `str_contains()`, `str_starts_with()`, `str_ends_with()`
  - `match` expressions, named arguments, nullsafe `?->`
  - Constructor property promotion, enums, readonly, `never`/`mixed` types
- The plugin self-updates from GitHub releases via `updater.php` (not from the WordPress.org plugin directory).

## Files

| File | Purpose |
| --- | --- |
| `nem-alderstjek.php` | Main plugin file — hooks, checkout flow, AJAX endpoints, admin notices. |
| `wc-integration.php` | WooCommerce Integration settings page (API token, callback URL, default required age). |
| `updater.php` | Plugin updater that polls the GitHub Releases API for new versions. |
| `nem-alderstjek.js` | Frontend JS (loaded on every page in the footer when an API token is set). |
| `readme.txt` | WordPress.org-style plugin readme. |
| `.github/workflows/release.yml` | Auto-creates a GitHub Release when the version in `nem-alderstjek.php` changes on `main`. |

## Bumping the version

Three places must be updated together — they must all match:

1. **`readme.txt`** — `Stable tag: X.Y.Z`
2. **`nem-alderstjek.php`** — `Version: X.Y.Z` header
3. **`nem-alderstjek.php`** — `const NA8K_VERSION = 'X.Y.Z';`

Pushing the change to `main` triggers `.github/workflows/release.yml`, which creates a GitHub Release tagged `vX.Y.Z`. The updater then surfaces the new version to existing installs.

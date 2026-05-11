# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this plugin does

WooCommerce Multi-Store HPOS is a WordPress plugin that lets a single WooCommerce installation manage multiple physical stores/warehouses. Each store has its own independent stock and price per product. The active store is stored in the WooCommerce session and governs all product display, cart, and checkout behavior for that customer.

## Development environment

This plugin lives inside a full WordPress + WooCommerce installation:

```
wp-content/plugins/woocommerce-multi-store/   ← this repo
```

There is no build step, no npm, no Composer. All PHP is plain WordPress/WooCommerce OOP. To test changes:

1. Ensure a local WordPress + WooCommerce server is running (e.g., LocalWP, XAMPP, or Docker).
2. Edit files directly — changes are reflected immediately on page reload.
3. Enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php` to catch PHP errors.

There are no automated tests. Verification is done manually in the browser.

## Database schema

Two custom tables (created on plugin activation via `dbDelta`):

| Table | Purpose |
|---|---|
| `{prefix}mswc_stores` | Store catalog: `id`, `code` (slug, immutable), `name`, `enabled` |
| `{prefix}mswc_stores_stock` | Per-product/per-store stock and price: `product_id`, `store_id`, `stock`, `price` |

The `code` field in `mswc_stores` is **immutable after creation** — it is used as the HTML field name suffix in the product editor (`_stock_store_{code}`, `_price_store_{code}`), and changing it would orphan existing product data.

The unique key `(store_id, product_id)` on `mswc_stores_stock` allows `$wpdb->replace()` to act as an upsert (INSERT … ON DUPLICATE KEY UPDATE).

## Architecture: how the classes fit together

`MSWC_Plugin` (entry point in `woocommerce-multi-store.php`) instantiates all components on `plugins_loaded`. It also exposes `MSWC_Plugin::get_active_stores()` — a static method used by multiple classes to fetch the enabled store list without duplicating the query.

| Class | File | Role |
|---|---|---|
| `MSWC_Session` | `class-mswc-session.php` | Forces WC session for guests; renders the store-selection modal; handles the `mswc_save_store` AJAX action; enqueues `selector.js` + localizes `mswc_vars` |
| `MSWC_Filters` | `class-mswc-filters.php` | Hooks into WooCommerce product filters to replace global stock/price/availability with store-specific values from session; also customizes admin product list columns |
| `MSWC_Frontend` | `class-mswc-frontend.php` | Renders the inline store selector above WooCommerce content and as `[mswc_selector]` shortcode; enqueues `style.css` |
| `MSWC_Admin` | `class-mswc-admin.php` | Adds the "Stores" tab in the product editor; saves per-store stock/price on product save |
| `MSWC_Admin_Stores` | `class-mswc-admin-stores.php` | Full CRUD UI under WooCommerce → Multi-Store; uses PRG pattern via `admin-post.php` |
| `MSWC_Stores_List_Table` | `class-mswc-stores-list-table.php` | `WP_List_Table` subclass for the stores listing (search, sort, paginate, bulk actions); lazy-loaded by `MSWC_Admin_Stores` |
| `MSWC_Orders` | `class-mswc-orders.php` | Attaches store ID to new orders (`_mswc_store_dispatch_id`); reduces store stock on order processing with idempotency flag (`_mswc_stock_reduced`) |
| `MSWC_Checkout_Validation` | `class-mswc-checkout-validation.php` | Defense-in-depth validation at cart, classic checkout, blocks checkout (Store API), and order creation |

## Key design decisions

**Session-driven store selection.** The active store is stored in `WC()->session` under `mswc_selected_store` (a string store ID). All filter callbacks check this key first; if absent they return the original WooCommerce value unchanged.

**Filter priority 100.** All product stock/price filters use priority 100 to run after WooCommerce's own processing.

**Admin context guard.** Every filter callback that modifies frontend data has an `is_admin() && ! wp_doing_ajax()` guard at the top so admin screens receive original product data.

**Stock cache.** `MSWC_Filters::$stock_cache` (static array, keyed `{product_id}_{store_id}`) prevents duplicate DB queries within a single request when multiple filter callbacks fire on the same product.

**Output buffer for admin columns.** `MSWC_Filters` intercepts the `is_in_stock` and `price` product list columns by opening an `ob_start()` at priority 5 and closing/replacing the buffer at priority 999.

**PRG pattern in admin.** All write actions (save/delete/toggle/bulk) in `MSWC_Admin_Stores` post to `admin-post.php` and redirect back with a `mswc_notice` query param; the notice is rendered from that param on the next GET.

**Idempotency for stock reduction.** `MSWC_Orders` writes `_mswc_stock_reduced = '1'` after decrementing stock because both `woocommerce_payment_complete` and `woocommerce_order_status_processing` can fire for the same order.

## Assets

| File | Loaded on |
|---|---|
| `assets/js/selector.js` | Every frontend page (enqueued by `MSWC_Session`) |
| `assets/css/style.css` | Every frontend page (enqueued by `MSWC_Frontend`) |
| `assets/js/admin.js` | Product editor + Multi-Store admin page |
| `assets/css/admin-style.css` | Product editor + Multi-Store admin page |

`selector.js` reads `mswc_vars.ajax_url` and `mswc_vars.nonce` (localized by `MSWC_Session::mswc_enqueue_scripts()`). Do not duplicate that localization in `MSWC_Frontend`.

## Shortcode

`[mswc_selector]` — renders the store-selector `<select>` + button anywhere on the site. Handled by `MSWC_Frontend::mswc_render_store_selector()`.

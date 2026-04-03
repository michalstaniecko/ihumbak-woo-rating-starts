# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WooCommerce Quick Ratings & Reviews (`ihumbak-woo-rating-stars`) - a WordPress/WooCommerce plugin that adds a quick star-rating widget to product pages. Users can rate products with a single click (no review text required). Quick ratings are stored in a custom DB table and combined with standard WooCommerce reviews to compute a unified average displayed across the store.

**Language:** PHP 7.4+, JavaScript (jQuery), CSS  
**Text domain:** `ihumbak-woo-rating-stars`  
**Primary language of docs/comments:** Polish (code identifiers and class names are in English)

## Architecture

### Plugin Bootstrap
`ihumbak-woo-rating-stars.php` - Main plugin file. Defines constants (`IHUMBAK_WRS_*`), loads the autoloader, and instantiates the singleton `Ihumbak_WooCommerce_Rating_Stars`. Components are initialized in `init_components()` — admin classes load only in `is_admin()`, frontend classes only on the public side.

### Autoloader
`includes/class-autoloader.php` - SPL autoloader that maps `Ihumbak_WRS_` prefixed classes to files. Naming convention: class `Ihumbak_WRS_Some_Name` resolves to `class-some-name.php`, searched in order: `includes/`, `admin/`, `public/`, `database/`.

### Key Components

**Data layer:**
- `Ihumbak_WRS_Rating_Model` (`includes/`) - All DB operations for `{prefix}_woo_quick_ratings` table (CRUD, rate limiting, distribution queries). Also syncs WC product meta (`_wc_average_rating`, `_wc_rating_count`) after changes.
- `Ihumbak_WRS_Rating_Calculator` (`includes/`) - Computes combined averages from quick ratings + WooCommerce reviews. Uses transient caching (`ihumbak_wrs_*` keys, 1-hour TTL).
- `Ihumbak_WRS_Database_Migration` (`database/`) - Creates/drops the `wp_woo_quick_ratings` table via `dbDelta`.

**WooCommerce integration:**
- `Ihumbak_WRS_WooCommerce_Integration` (`includes/`) - Hooks into `woocommerce_product_get_average_rating`, `woocommerce_product_get_rating_count`, and `woocommerce_product_get_rating_html` filters to inject combined ratings. Also syncs meta when WC comments change.

**REST API:**
- `Ihumbak_WRS_REST_API_Handler` (`includes/`) - Two endpoints under namespace `woo-quick-ratings/v1`:
  - `POST /rate` - Submit/update a rating (validates product, checks rate limit, login requirement)
  - `GET /stats/{product_id}` - Retrieve rating stats for a product

**Frontend:**
- `Ihumbak_WRS_Frontend_Render` (`public/`) - Hooks the star widget into `woocommerce_single_product_summary` at configurable priority based on `ihumbak_wrs_widget_position` option.
- `Ihumbak_WRS_Assets_Manager` (`public/`) - Enqueues CSS/JS only on single product pages. Localizes `ihumbakWRS` JS object with REST URL, nonce, and i18n strings.
- `assets/js/rating-widget.js` - jQuery `RatingWidget` class handling hover, click, AJAX submission via REST API.
- `templates/widget-stars.php` - Widget HTML template with Schema.org microdata.

**SEO:**
- `Ihumbak_WRS_Schema_Markup` (`includes/`) - Outputs JSON-LD `AggregateRating` schema and modifies WooCommerce's built-in structured data.

**Admin:**
- `Ihumbak_WRS_Admin_Panel` (`admin/`) - Admin menu pages (ratings list, product details, delete rating). Adds "Quick Ratings" column to product list.
- `Ihumbak_WRS_Admin_Settings` (`admin/`) - Settings page registered under the Quick Ratings menu.

### Data Flow
1. User clicks star -> `rating-widget.js` POSTs to `/wp-json/woo-quick-ratings/v1/rate`
2. `REST_API_Handler` validates, calls `Rating_Model::add_rating()` (inserts or updates)
3. `Rating_Model` clears transient caches and updates WC product meta
4. Response includes updated stats from `Rating_Calculator::get_product_stats()`
5. JS updates the widget UI with new count/average

### Plugin Options (wp_options)
All prefixed with `ihumbak_wrs_`: `enabled`, `require_login`, `admin_only`, `widget_position`, `show_count`, `hide_count_in_loop`, `star_color`, `text_rate`, `text_thanks`.

## Development Notes

- No build step — assets are plain CSS/JS (jQuery dependency).
- No Composer or npm; no test framework is configured.
- The plugin declares WooCommerce HPOS (`custom_order_tables`) and Cart/Checkout Blocks compatibility.
- `uninstall.php` handles cleanup on plugin deletion (drops table, deletes options).
- Transient cache keys follow pattern `ihumbak_wrs_{type}_{product_id}` — remember to clear them when modifying rating logic.

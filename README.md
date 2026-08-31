# POS Lite v0.5.14

## Setup wizard, health checks and offline controls

- Added **WooCommerce → POS Lite Setup** for first-run configuration.
- Added **WooCommerce → POS Lite Health** with checks for WooCommerce, POS route, REST URL, products, roles, payment methods and offline mode.
- Added a route repair / permalink flush tool for `/pos/`.
- Added configurable offline checkout mode:
  - allow offline checkout and queue sales
  - allow queued sales after local validation
  - block checkout when offline/server unavailable
- Added clearer first-load messages while products/tax settings sync.
- Added queue cleanup tools to export queued sales JSON and clear obviously invalid queued sales.

# POS Lite v0.5.13

## Global tax and receipt ID labels

- Replaced NZ-focused receipt wording with configurable tax/business ID labels.
- Set your fallback tax label to Tax, GST, VAT, Sales tax, or whatever suits your WooCommerce store.
- Set an optional receipt ID label and number, such as GST No, VAT No, ABN, EIN, Company No, etc.
- WooCommerce remains the source of truth for tax rates and inclusive/exclusive pricing.

# POS Lite for WooCommerce (free version)

A fast, offline-first point of sale for WooCommerce. The register loads your
catalog onto the device, searches it locally with no API round-trips, and
queues sales when the connection drops — pushing them to WooCommerce with an
idempotency key so a flaky reconnect never creates a duplicate order.

This free version has **no payment integration** by design. Sales are recorded
as cash or manual payments; card-present (e.g. Stripe Terminal) is the planned
paid add-on.

## Install

1. Copy the `pos-lite` folder into `wp-content/plugins/`.
2. Activate **POS Lite for WooCommerce** in the WordPress admin.
   Activation registers the `/pos/` route and flushes permalinks once.
3. Open `https://your-store.example/pos/` while logged in as a user who can
   manage orders. Add it to your tablet's home screen to run it full-screen.

If `/pos/` 404s, go to Settings → Permalinks and click Save once to flush rules.

## Settings (WooCommerce → POS Lite)

Configure the register from the admin:

- **Tax** — POS Lite reads your WooCommerce tax rules directly (rates, tax
  classes, inclusive/exclusive, rounding), so the register matches your store.
  Configure rates in WooCommerce → Settings → Tax. Here you only choose whether
  to tax at the store/register location (recommended for in-person sales) or by
  customer address, set a fallback tax label, plus an optional country-specific tax/business ID label and number for receipts.
- **Payment types** — add/remove the methods shown at the register. Tick
  "require confirmation" for methods the cashier must confirm in person, such
  as EFTPOS or card.
- **Receipt** — store header, address, phone, and footer for the 80mm receipt.
- **Staff PINs** — set 4–8 digit PINs for POS staff, require a PIN before selling, and optionally force staff PIN login again after every completed sale.

## At the register

- A **category dropdown** sits under the search bar; barcode/SKU scans bypass it
  so a scan always finds the item.
- The cart shows the **tax breakdown and total** using WooCommerce's own rules:
  each line is taxed by its product's tax class and status, inclusive or
  exclusive per your store setting, with the configured rounding mode. Tax is
  grouped by rate label (e.g. GST). The order is recalculated authoritatively by
  WooCommerce on save, and the receipt shows that figure once synced.
- **Charge** opens the payment screen. Methods flagged for confirmation show a
  "payment received — complete sale" step so EFTPOS/card paid in person is
  explicitly confirmed before the sale is recorded.
- After completing a sale, an **80mm thermal receipt** preview appears with a
  Print button (uses the browser print dialog and an 80mm page size).

## How it works

```
Browser register (PWA)              WordPress + WooCommerce
  React-free vanilla UI               purpose-built REST  (pos-lite/v1)
  IndexedDB via Dexie     ── sales ─▶ HPOS order tables   (WC_Order CRUD)
  catalog cache + outbox  ◀ catalog ─ WC tax engine       (authoritative)
  service worker (offline)
```

- **Catalog** is pulled in pages and delta-synced by `date_modified`, then
  mirrored in memory so barcode/SKU lookup is instant even on large catalogs.
- **Sales** are written to a local outbox first, then pushed. Each carries a
  client-generated `idempotency_key`; the server returns the original order on
  any replay instead of duplicating it.
- **HPOS-native**: the plugin never touches postmeta directly. It declares
  `custom_order_tables` compatibility and uses `WC_Order` CRUD throughout.
- **Tax** is always calculated server-side by WooCommerce on sync. The register
  shows a subtotal and labels tax as pending — it never guesses final totals.

## Loyalty

`includes/class-poslite-loyalty.php` is an adapter that detects whichever
loyalty plugin is active and normalizes balance / earn / redeem:

| Provider | Detected by | Balance source |
|---|---|---|
| WooCommerce Points & Rewards | `WC_Points_Rewards_Manager` | `get_users_points()` |
| myCred | `mycred_get_users_balance()` | balance API |
| Simple Points & Rewards | `spar_update_user_points()` | `spar_points` user meta |
| Built-in fallback | (none of the above) | `_poslite_points` user meta |

Earning is intentionally hands-off for third-party providers: they already
award points automatically when an order completes, so POS Lite lets their
hooks fire and only awards manually for the built-in fallback (no double-count).
Redemption is initiated at the register and applied as a negative order fee so
WooCommerce's tax engine stays authoritative.

To support another loyalty plugin, either add a branch in the adapter or use the
filters: `poslite_loyalty_balance`, `poslite_loyalty_redeem_value`,
`poslite_loyalty_redeem`, `poslite_loyalty_label`.

## REST endpoints (`/wp-json/pos-lite/v1`)

- `GET  /catalog?modified_after=&page=&per_page=` — delta of sellable units
- `GET  /customers?search=` — quick customer lookup with balances
- `GET  /loyalty/{id}` — normalized points balance
- `POST /orders` — idempotent, HPOS-native order creation

All require a logged-in user with `edit_shop_orders` (or `manage_woocommerce`)
and the REST nonce sent as `X-WP-Nonce`.

## Known limits / testing notes

- **Barcode field**: reads `_poslite_barcode`, then `_barcode`, then falls back
  to SKU. Map your own barcode meta key in `map_product()` if needed.
- **Catalog removals**: drafted, pending, trashed, and non-purchasable private products
  are now purged from the local cache during sync. Permanently deleted products can
  only be caught by a full cache refresh, which happens automatically on version change.
- **Offline stock**: stock is decremented server-side on sync, so two offline
  registers can oversell the same unit. The honest model is optimistic local
  display + server as source of truth + surfacing conflicts — not a guarantee.
- **Dexie** loads from jsDelivr and is cached by the service worker after first
  online load. To remove the CDN dependency entirely, vendor `dexie.min.js`
  into `app/` and point the shell and service worker at the local copy.
- **Refunds** are included, with server-side quantity/amount validation. Multi-register/outlet is not in the free scope yet.


## Changelog

#
## 0.5.3

- Fixed valid 100% discounted / zero-total sales being skipped as invalid queued orders.
- Zero-total POS orders now still require real cart lines and a valid POS payment method, but do not require a positive tender amount.

## 0.5.2
- Maintenance build using the Lite/free version numbering line.
- Added the refresh/outbox safety guard so malformed queued records cannot create blank $0 WooCommerce orders.
- Cleans up incomplete WooCommerce orders if POS order validation fails after WooCommerce has created the order shell.
- Drops invalid local outbox records instead of replaying them on every POS page load.
- Includes the current Lite fixes for customer lookup, staff PINs, print handling, order validation, stock validation, and catalog cleanup.

## License

GPL-2.0-or-later.

## What's in the register (v0.4 — free table-stakes tier)

The register has three views (top nav): **Sell**, **Sales**, and **Register**.

Sell:
- Category filter, instant barcode/SKU search, offline-first cart.
- **Discounts** — percentage or fixed cart discount, applied through line totals
  so tax stays correct (uses WooCommerce's rules).
- **Coupons** — enter a WooCommerce coupon code; validated and applied by
  WooCommerce when the sale completes (online).
- **Custom / open-price items** — ring up an item that isn't in the catalog.
- **Customers** — search existing or create a new customer at the register;
  view and redeem loyalty.
- **Park sale** — hold a cart and resume it later (stored on the device).
- **Split payments** — take multiple tenders on one sale, with change due, and
  the per-method confirmation step for EFTPOS/card.
- **Receipt** — 80mm thermal print, plus email receipt (uses WooCommerce's
  customer invoice email) when the customer has an address.

Sales:
- Look up recent orders by number or customer and process **refunds** — choose
  quantities per line, add a reason, and restock; uses WooCommerce's own refund
  engine, so tax and stock are handled correctly.

Register (cash management):
- Open the till with a float, record cash in/out, and **close with a Z-report**
  (expected vs counted cash, variance, and sales by payment method), printable
  on the 80mm roll. Plus a **Today** summary of sales, tax, refunds, and net.

## Free vs Pro (planned)

Free covers everything needed to run a till credibly: the above plus offline
operation, WooCommerce tax rules, and loyalty integration. The Pro tier is the
hardware, processing, scale, and analytics layer: integrated card payments
(Stripe Terminal / Tap to Pay / gateways), multi-outlet and multi-register with
per-outlet stock and multi-currency, per-cashier sessions and analytics
dashboards, raw ESC/POS printing with cash-drawer kick and customer display,
barcode label printing, and a restaurant/hospitality mode.

## Testing note

This package has been PHP-linted and the register JavaScript has been syntax-checked, but it still needs real WooCommerce staging testing before going live — especially discounts, coupons, split payments, refunds, stock conflicts, variable products, and inclusive/exclusive tax setups.

## Roles & permissions

POS Lite adds three roles you can assign to staff under Users:

- **POS Sales** — can sell (and park sales, look up customers, redeem loyalty).
- **POS Manager** — sell plus pricing adjustments, refunds, and end-of-day.
- **POS Admin** — full POS access.

Three sensitive functions are permission-gated: **pricing adjustments**
(discounts, coupons, custom-price items), **refunds**, and **end of day**
(open/close register, cash in/out, Z-report). Under WooCommerce → POS Lite a
role × function matrix lets you tick which roles may perform each action.

Enforcement is capability-based and applied server-side (it's real security, not
just hidden buttons); the register also hides controls and nav a role can't use.
WordPress administrators and WooCommerce shop managers always keep full POS
access, so you can't lock yourself out. Roles are created on activation and
refreshed on each version change.


## 0.5.5
- Changed out-of-stock checkout behaviour to a hard stop instead of queuing the sale.
- Added client-side stock handbrake before payment and before final sale completion.
- Server stock/validation failures now block the sale and are not saved to the offline outbox.
- Old queued sales that fail stock validation are removed from the queue with a warning instead of replaying forever.

## 0.5.4
- Hardened the POS Sales tab/order lookup endpoint so malformed WooCommerce orders or incompatible customer search queries cannot trigger a critical error.
- Added safer order detail handling for refund lookup.


### 0.5.7
- Added POS queue management and sync-health panel.
- Queued sales can now be viewed, retried, restored to cart, or deleted from the POS screen.
- Non-JSON/HTML REST responses and stock validation errors remain hard failures and are not silently retried forever.
- Unsynced offline sales no longer print a normal receipt before WooCommerce confirms the order.
- Queue records now store last error, retry count, and failure status for easier troubleshooting.

### 0.5.6
- Blocked HTML/non-JSON REST responses from being queued as offline sales.
- Prevented out-of-stock/server validation failures from printing receipts as queued transactions.
- Existing invalid queued sales are removed on next sync attempt instead of retrying forever.

### 0.5.8
- Fixed register open/close actions being unclear or silently failing in the POS UI.
- Register REST endpoints now return proper WordPress errors instead of successful responses containing an `error` field.
- Added UI error handling/re-enable behaviour for register open, close, and cash movements.
- Saves active POS register session IDs against POS orders so Z-reports can be matched to the actual open/close session.
- Captures optional Pro register/outlet context when a register is opened.


### 0.5.10 - Product Load Recovery
- Prevents plugin version changes from clearing the local product cache before a fresh catalog sync succeeds.
- Forces a full catalog sync when the local catalog is empty or stale.
- Shows a clearer POS toast if product sync fails instead of leaving the sale screen mysteriously blank.

### 0.5.12 - PIN Focus Fix
- Fixed a focus-stealing issue where the product search field could take keyboard focus back from the staff PIN modal after the POS screen refreshed/synced.
- Modal inputs now retain focus while login/approval prompts are open.

### 0.5.11 - Cash Rounding
- Added optional cash rounding settings under WooCommerce → POS Lite.
- Supports rounding cash sale totals to 1c, 5c, 10c, 50c, or $1 increments.
- Supports nearest, always up, and always down rounding modes.
- Applies only when the sale is paid entirely with a cash payment method; EFTPOS/card totals remain unchanged.
- Shows cash rounding and cash payable amount in the payment modal and on receipts.
- Adds a WooCommerce fee line and order meta for cash rounding adjustments so order totals, tendered amounts, change, and reports stay aligned.

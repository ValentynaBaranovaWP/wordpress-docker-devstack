# SECURITY.md — Client Orders Panel

Threat model for the restricted client cabinet in wp-admin.

## Assets

- WooCommerce order data (items, totals, addresses, contacts) — owner access only.
- Order and catalog integrity: clients cannot change status, metadata, or products.
- User accounts: login form brute-force protection.

## Threat actors and scenarios

| Actor | Scenario | Control |
|---|---|---|
| Authenticated client | View another user's order by guessing `order_id` | `customer_id === current_user_id` check before card render and in AJAX; attempts logged (`foreign_order_access`), 403 response |
| Authenticated client | Direct Woo admin URLs (`admin.php?page=wc-orders&action=edit&id=N`, legacy `post.php?post=N`) | Role has no `shop_order` capabilities; guard logs attempt and redirects to cabinet |
| Authenticated client | Data changes: metadata save, status change, bulk actions, product edits | 1) Role has only `read` + `cop_view_own_orders`; 2) deny-list in `user_has_cap` strips privileged caps even if another plugin grants them; 3) all non-GET admin requests blocked except `profile.php`; 4) no edit buttons in UI |
| Authenticated client | WooCommerce REST API (`/wp-json/wc/v3/orders`, etc.) | Woo REST requires caps the role lacks; all `wc*` namespaces hard-blocked via `rest_pre_dispatch` with logging |
| Authenticated client | Arbitrary AJAX via `admin-ajax.php` | Deny-all: only `heartbeat` and `cop_refresh_order_status` allowed (filter `cop_allowed_ajax_actions`); custom AJAX protected by nonce + ownership re-check |
| Authenticated client | Bypass hidden menu via direct admin screen URLs | Screen allowlist in `current_screen`: cabinet pages, profile, dashboard (immediate redirect); others logged and redirected |
| Unauthenticated attacker | Password brute force on login form | Rate limiting: 5 failed attempts per IP+username → 15 min lockout (filters `cop_login_max_attempts`, `cop_login_lockout_seconds`); event `login_throttled` |
| Client/bot | Cabinet request flood | Limit 120 requests/min per user (`cop_cabinet_rate_limit`) → 429 |
| Attacker | CSRF on cabinet links/actions | Nonce on order card deep-link and AJAX; no state-changing actions in cabinet |
| — | XSS via order data | All output escaped (`esc_html`/`esc_attr`/`wp_kses_post`); optional CSP (`COP_ENABLE_CSP`) |

## Design decisions

1. **Minimal privileges instead of a trimmed admin.** Role `client` does NOT get `edit_shop_orders` and does not use standard Woo screens. List and card are custom read-only pages on `wc_get_orders()` with forced `customer_id`. Filtering is the only data path. Works the same for HPOS and legacy order storage.
2. **Defence in depth.** If one layer fails: no caps → deny-list caps → write request block → screen allowlist → AJAX allowlist → REST block. Each denial is an audit log event.
3. **Audit for critical events only** in `{prefix}cop_access_log`: foreign order access attempts, blocked screens/writes/AJAX/REST, login lockout, rate limit exceeded. 90-day retention (filter `cop_audit_retention_days`).

## Known limitations

- Rate limiting uses transients: without object cache it lives in the DB; on clusters without shared cache, windows may be inaccurate. Use web server/WAF limits for high traffic.
- IP is taken from `REMOTE_ADDR` only. Behind a reverse proxy, configure real IP at the server level or login lockout may apply to the proxy IP.
- CSP is disabled by default: strict CSP breaks wp-admin. Policy uses `unsafe-inline`/`unsafe-eval` as a compromise; enable with `define( 'COP_ENABLE_CSP', true );`.
- Plugin does not protect against other plugins that skip capability checks (guards block standard admin entry points: `admin.php`, `admin-ajax.php`, `admin-post.php`, REST).
- `wp-login.php` and XML-RPC are out of scope except login attempt counter. Disable XML-RPC if unused.

## Vulnerability reporting

Do not open public issues with vulnerability details — email the site administrator (Settings → General).

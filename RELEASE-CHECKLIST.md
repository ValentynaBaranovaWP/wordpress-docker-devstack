# Release checklist — Client Orders Panel

Run under three accounts: **client A** (role `client`, has orders), **client B** (role `client`, different orders), **administrator**.

## 1. Role and menu

- [ ] After activation, Client role (`client`) exists with only `read` + `cop_view_own_orders`.
- [ ] After login, client A sees only tabs: My Orders, Help, Profile. Dashboard redirects to cabinet.
- [ ] Admin bar has no Add New, comments, updates, or settings.
- [ ] Administrator sees admin unchanged (menu/orders/products intact).

## 2. Own vs foreign orders

- [ ] Client A list shows **only** their orders (verify against `wp_wc_orders`/admin list).
- [ ] Own order card opens: status, items, totals, addresses, customer notes. No edit/status change buttons.
- [ ] Client A substitutes client B's `order_id` in cabinet URL → 403 + `foreign_order_access` in `{prefix}cop_access_log`.
- [ ] Guest orders (customer_id = 0) are not visible to any client.
- [ ] Refresh status works; tampered `order_id` in AJAX → 403 + log; missing/wrong nonce → rejected.

## 3. Direct URLs

- [ ] `admin.php?page=wc-orders` and `...&action=edit&id=N` → redirect to cabinet + log.
- [ ] `post.php?post=N&action=edit` (legacy) → reject/redirect + log.
- [ ] `edit.php`, `edit.php?post_type=product`, `users.php`, `options-general.php`, `plugins.php`, `tools.php` → redirect to cabinet + `blocked_admin_screen`.
- [ ] POST to `post.php`/`admin-post.php`/`options.php` as client → 403 + `blocked_write_request`.
- [ ] Own profile update (`profile.php`) works.

## 4. REST / AJAX

- [ ] As client: `GET /wp-json/wc/v3/orders` → 403 (`cop_rest_forbidden`) + `blocked_rest_request`.
- [ ] `GET /wp-json/wp/v2/posts` as client → no private data (standard `read` rights).
- [ ] Arbitrary ajax action (e.g. `action=inline-save`) as client → 403 + `blocked_ajax_action`.
- [ ] REST/AJAX as administrator unchanged.

## 5. Rate limiting / headers

- [ ] 5 failed logins in a row → sixth blocked even with correct password; `login_throttled` logged; login works after 15 min.
- [ ] Successful login clears attempt counter.
- [ ] >120 admin requests/min as client → 429 + `cabinet_rate_limited`.
- [ ] With `COP_ENABLE_CSP=true`: `Content-Security-Policy` header on cabinet pages; cabinet works without console errors.

## 6. Regression / compatibility

- [ ] Checkout, payment (Stripe), order emails unchanged.
- [ ] Frontend My Account for regular customers unchanged.
- [ ] HPOS: test with enabled and disabled (legacy) order storage (WooCommerce → Settings → Advanced → Features).
- [ ] Compatibility with Single Client Hub plugin: both active, no fatals or menu conflicts.
- [ ] Deactivation does not break site; re-activation restores role/table.
- [ ] No notice/warning from plugin in `debug.log`.

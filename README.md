# Client Orders Panel

[![CI](https://github.com/ValentynaBaranovaWP/wordpress-docker-devstack/actions/workflows/ci.yml/badge.svg)](https://github.com/ValentynaBaranovaWP/wordpress-docker-devstack/actions/workflows/ci.yml)

Restricted WordPress/WooCommerce admin panel: **Client** role (`client`) with read-only access to own orders.

## Features

- Role `client`: only `read` + custom capability `cop_view_own_orders`; no `manage_options`, no content/order/product permissions.
- Cabinet with three tabs: **My Orders** (list + read-only card), **Profile**, **Help**. Rest of admin is hidden and blocked.
- List and order card always filtered by `customer_id` via `wc_get_orders()` — compatible with HPOS and legacy storage.
- Guards: screen allowlist, write request blocking, deny-all AJAX (except allowlist), `wc*` REST namespace blocking, capability deny-list via `user_has_cap`.
- Nonce on order card deep-link and Refresh status AJAX.
- Login rate limiting (5 attempts / 15 min) and cabinet requests (120/min).
- Critical event audit in `{prefix}cop_access_log` table (90-day retention).
- Optional CSP: `define( 'COP_ENABLE_CSP', true );`.

See `SECURITY.md` for threat model; `RELEASE-CHECKLIST.md` before release.

## Installation

1. Activate the plugin (requires active WooCommerce). Role and log table are created on activation.
2. Assign the Client role to a user (Users → Edit → Role).
3. Orders are linked to accounts via the order Customer field (`customer_id`).

## Local development (Docker)

```bash
docker compose up -d --build
docker compose run --rm tools composer install
docker compose run --rm tools composer phpcs
docker compose run --rm tools composer test
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full setup (one-time WP install via WP-CLI, WooCommerce, asset build) and the tag-based staging deploy.

## Filters

| Filter                              | Default                                 | Description                           |
| ----------------------------------- | --------------------------------------- | ------------------------------------- |
| `cop_allowed_ajax_actions`          | `heartbeat`, `cop_refresh_order_status` | Allowed AJAX actions for clients      |
| `cop_allowed_write_pages`           | `profile.php`                           | Admin pages where clients may POST    |
| `cop_login_max_attempts`            | `5`                                     | Login attempts before lockout         |
| `cop_login_lockout_seconds`         | `900`                                   | Login lockout duration                |
| `cop_cabinet_rate_limit`            | `120`                                   | Admin requests per minute for clients |
| `cop_audit_retention_days`          | `90`                                    | Audit log retention                   |
| `cop_enable_csp` / `cop_csp_policy` | disabled                                | CSP enable and policy                 |

## Uninstall

`uninstall.php` moves `client` users to `customer`, removes the role and options. Log table is dropped only when `COP_REMOVE_DATA = true` is defined in `wp-config.php`.

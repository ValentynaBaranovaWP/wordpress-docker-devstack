# Contributing / Local Development

Author: Valentyna Baranova

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)

## Run locally

```bash
docker compose up -d --build

docker compose run --rm wpcli core install \
  --url=http://localhost:8081 --title="COP Dev" \
  --admin_user=admin --admin_password=admin \
  --admin_email=admin@example.com --skip-email

docker compose run --rm wpcli plugin install woocommerce --activate
docker compose run --rm wpcli plugin activate client-orders-panel
```

Open http://localhost:8081/wp-admin (login `admin` / `admin`).

Stop the stack with `docker compose down` (add `-v` to wipe the database).

## Tooling (same image as CI)

```bash
docker compose run --rm tools composer install
docker compose run --rm tools composer phpcs
docker compose run --rm tools composer phpcbf
docker compose run --rm tools composer test
docker compose run --rm tools sh -c "npm ci && npm run build"
```

## CI

Every push and pull request runs `.github/workflows/ci.yml`.

## Deploy to staging

```bash
git tag v1.0.1
git push origin v1.0.1
```

Configure repository secrets in GitHub (Settings → Secrets and variables → Actions):

| Secret            | Meaning                                                        |
| ----------------- | -------------------------------------------------------------- |
| `STAGING_SSH_KEY` | Private SSH key with access to the staging server              |
| `STAGING_HOST`    | Staging server hostname or IP                                  |
| `STAGING_USER`    | SSH user                                                       |
| `STAGING_PATH`    | Path to `wp-content/plugins/client-orders-panel` on the server |
| `STAGING_PORT`    | SSH port (optional, default 22)                                |

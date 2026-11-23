# Deployment (Portainer)

Production (`https://reports.fetchdock.dev`) and staging run as **Portainer BE Git stacks** built from this directory,
like fetchdock-server ([ADR 0010](https://github.com/FetchDock/fetchdock-server/blob/develop/deploy/README.md)).
GitHub Actions redeploys them through the Portainer API and waits until the app reports the new version on
`/.well-known/fetchdock-collector`.

| | Production | Staging |
|---|---|---|
| Trigger | Stable GitHub release that is the **highest stable version tag**, after approval in the `production` environment | Every `develop` commit that passed CI (also manual: *Actions → Staging → Run workflow*) |
| Workflow | `publish.yml` → `deploy.yml` | `staging.yml` → `deploy.yml` |
| Images | `X.Y.Z` | `sha-<commit>` (and `develop`); version `X.Y.(Z+1)-dev.<commit>` |
| Compose files | `deploy/compose.yaml` + `deploy/compose.production.yaml` at `refs/tags/X.Y.Z` | `deploy/compose.yaml` + `deploy/compose.staging.yaml` at `refs/heads/develop` |
| Database | Production | Its own database, **replaced with mock data** on every start. Never a copy of production |
| Maintainer login | OIDC application `fetchdock-collector` | OIDC application `fetchdock-collector-staging` |

The collector has its own semver (0.1.0 first) and is not part of fetchdock-server's shared version (ADR 0014).

## One-time setup

### 1. PostgreSQL
One database and role per stack, e.g. `fetchdock_collector` and `fetchdock_collector_staging`. No other access is
needed: staging doesn't read production.

### 2. IdP (Authentik)
One application per stack, both **public clients** (authorization code with PKCE, no client secret: the PWA signs in
in the browser with `oidc-client-ts`):
- Redirect URI `https://<host>/admin/auth-callback`.
- Scopes `openid profile email offline_access`. Authentik only issues a refresh token when the provider has the
  `offline_access` scope mapping (*Advanced protocol settings → Scopes*: add
  `authentik default OAuth Mapping: OpenID 'offline_access'`). Without it the PWA can't renew the access token
  (5 minutes by default) and has to send the maintainer back through the IdP each time it expires. Silent renewal in an
  iframe isn't an option: Authentik's flow pages send `X-Frame-Options: DENY`.
- Put maintainers in a group named `maintainer` and admins in a group named `admin`, and include the `groups` claim in
  the access token. The API maps group names to roles (`maintainer` → `ROLE_MAINTAINER`, `admin` → `ROLE_ADMIN`) and
  rejects every token without `ROLE_MAINTAINER` (or `ROLE_ADMIN`, which includes it).

The API checks the token audience (`OIDC_AUDIENCE`), so production tokens are rejected on staging.

### 3. Portainer stacks
*Add stack → Repository*:
- Repository URL `https://github.com/FetchDock/fetchdock-collector` (public, no authentication).
- Compose path `deploy/compose.yaml`, additional path `deploy/compose.production.yaml` or `deploy/compose.staging.yaml`.
- Reference: production `refs/tags/<current version>`, staging `refs/heads/develop`.
- **GitOps updates: off.** GitHub Actions decides when to deploy.
- Environment variables: see below. `DOCKER_TAG` is set by every deploy; give it an initial value.

Note each stack's ID and the environment (endpoint) ID from the stack URL:
`…/#!/<endpointId>/docker/stacks/<name>?id=<stackId>`.

### 4. Reverse proxy (nginx-proxy-manager)
Point `reports.fetchdock.dev` (and the staging host) at `<docker host>:<HTTP_PORT>` with scheme **`http`**, WebSockets
support and long timeouts for Mercure (Server-Sent Events). TLS ends at the proxy. The proxy's address must be in
`TRUSTED_PROXIES`, so the API builds `https://` URLs from `X-Forwarded-Proto`.

Client IPs only reach the Caddy access log (`/data/access.log` in the `caddy_data` volume, rotated, kept for 30 days)
and, briefly, the rate limiter's cache. Don't enable access logs with IPs elsewhere without updating the privacy
policy.

### 5. GitHub
*Settings → Environments*, for `production` and `staging`:
- Secret `PORTAINER_API_KEY`: a Portainer access token of a user that can manage the stack.
- Variables `PORTAINER_URL`, `PORTAINER_STACK_ID`, `PORTAINER_ENDPOINT_ID`, `APP_URL` (e.g. `https://reports.fetchdock.dev`).
- `production`: **required reviewers**, deployments restricted to tags (`*.*.*`).
- `staging`: deployments restricted to the `develop` branch.

Secrets only live in Portainer and GitHub environments. This repository is public: never commit them.

## Stack environment variables

| Variable | Notes |
|---|---|
| `DOCKER_TAG` | Image tag. Overwritten by every deploy |
| `HTTP_PORT` | Host port for the reverse proxy |
| `SERVER_NAME` | Public host name only, e.g. `reports.fetchdock.dev`. Used for URLs and trusted hosts; Caddy always serves `:80` |
| `APP_SECRET` | Random, **different** per stack |
| `CADDY_MERCURE_JWT_SECRET` | Random, **different** per stack |
| `CADDY_MERCURE_SUBSCRIBER_JWT_SECRET` | Optional separate key for subscriber tokens. Defaults to `CADDY_MERCURE_JWT_SECRET` |
| `TRUSTED_PROXIES` | Must include the reverse proxy |
| `TRUSTED_HOSTS`, `CORS_ALLOW_ORIGIN` | Optional, default to `SERVER_NAME` (`TRUSTED_HOSTS` also allows `php` and `localhost`, for the healthcheck) |
| `POSTGRES_HOST`, `POSTGRES_PORT`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`, `POSTGRES_VERSION` | The stack's own database |
| `OIDC_AUDIENCE` | The OIDC application's client ID (the access token's audience) |
| `OIDC_ISSUER`, `OIDC_DISCOVERY_BASE_URI` | E.g. `https://auth.example.org/application/o/fetchdock-collector/` for both |
| `NEXT_PUBLIC_OIDC_ISSUER`, `NEXT_PUBLIC_OIDC_CLIENT_ID` | Same values, for the PWA (read at request time) |
| `NEXT_PUBLIC_OIDC_REDIRECT_URI` | Optional, defaults to `https://${SERVER_NAME}/admin/auth-callback` |
| `REPORT_RETENTION_MONTHS` | Optional, default `24`: triaged reports (resolved, won't fix, duplicate) older than this are purged |
| `RATE_LIMIT_REGISTRATION_PER_IP`, `RATE_LIMIT_REPORTS_PER_INSTANCE`, `RATE_LIMIT_COMMENTS_PER_INSTANCE` | Optional abuse limits, requests per hour. `0` (default) is off; blocking an instance is the manual fallback |

## Operations
- **Roll back production:** re-run the *Production* job of an older release's *Publish Docker images* run, or redeploy
  the stack in Portainer at the older tag with that `DOCKER_TAG`. Migrations are not rolled back.
- **Refresh staging's mock data:** restart the `php` container, or *Actions → Staging → Run workflow*.

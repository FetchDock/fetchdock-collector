# FetchDock report collector

Receives the failure reports that [FetchDock](https://github.com/FetchDock/fetchdock-server) instances forward to the FetchDock developers, with each user's consent per report. Runs at **https://reports.fetchdock.dev**.

- **Instances** register once (`POST /v1/instances`) and authenticate with their own secret. The ingest API is versioned under `/v1`.
- **Maintainers** sign in to the PWA (OIDC) to triage reports and see aggregated stats.
- **Privacy:** reports are stored as the user saw and approved them. The privacy policy is versioned in this repo and served at `/v1/policy`.

The repo is public so that anyone can check what the collector stores and for how long.

Status: being set up ([FetchDock/fetchdock-workspace#1](https://github.com/FetchDock/fetchdock-workspace/issues/1)). Stack: Symfony + API Platform on FrankenPHP, PostgreSQL, Mercure and a Next.js PWA, like fetchdock-server.

## Development
Requirements: Docker with Compose.

```sh
make up      # API, worker, PWA and PostgreSQL on https://localhost:8443 (ports avoid a local fetchdock-server stack)
make seed    # replace the local data with mock data (20 instances, 500 reports)
make prep-test test
make openapi # export the contract to ../contracts/collector-openapi.json (FetchDock workspace)
```

- `api/`: Symfony + API Platform. Instances and reports live in `src/Entity`, mock data in `src/Factory` and
  `src/DataFixtures`, ingest rules (policy versions, error categories, retention, rate limits) in
  `config/packages/collector.yaml`.
- `pwa/`: Next.js maintainer app at `/admin`. Set `NEXT_PUBLIC_OIDC_*` in `pwa/.env` (see `.env.dist`) and `OIDC_*` for
  the API (`api/.env.local`) to sign in locally.
- `deploy/`: Portainer stacks for production and staging, see [deploy/README.md](deploy/README.md).

## License
MIT, see [LICENSE](LICENSE).

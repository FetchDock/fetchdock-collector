# FetchDock report collector

Receives the failure reports that [FetchDock](https://github.com/FetchDock/fetchdock-server) instances forward to the FetchDock developers, with each user's consent per report. Runs at **https://reports.fetchdock.dev**.

- **Instances** register once (`POST /v1/instances`) and authenticate with their own secret. The ingest API is versioned under `/v1`.
- **Maintainers** sign in to the PWA (OIDC) to triage reports and see aggregated stats.
- **Privacy:** reports are stored as the user saw and approved them. The privacy policy is versioned in this repo and served at `/v1/policy`.

The repo is public so that anyone can check what the collector stores and for how long.

Status: being set up ([FetchDock/fetchdock-workspace#1](https://github.com/FetchDock/fetchdock-workspace/issues/1)). Stack: Symfony + API Platform on FrankenPHP, PostgreSQL, Mercure and a Next.js PWA, like fetchdock-server.

## License
MIT, see [LICENSE](LICENSE).

# fetchdock-collector integrity digests

Manifest digests of official `fetchdock-collector-api` images (ADR 0013, collector#5).
The collector compares its baked `integrity-manifest.json` digest with `<version>.sha256`.
`-dev` digests older than 90 days are pruned automatically.

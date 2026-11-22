# Contributing to the FetchDock report collector

Thanks for helping out!

- **Bugs and ideas:** open an issue with one of the templates. Never paste report contents, instance secrets or instance ids in an issue.
- **Vulnerabilities:** see [SECURITY.md](../SECURITY.md); don't open a public issue.
- **Pull requests:** base them on `develop`, follow the [Symfony coding standards](https://symfony.com/doc/current/contributing/code/standards.html) (`php-cs-fixer fix`), add tests and keep them green.
- **Ingest API (`/v1`):** FetchDock servers in the wild depend on it. A breaking change needs a new path version (`/v2`) that runs alongside `/v1`.
- **Stored data:** a change to what the collector stores or keeps must update the privacy policy (a new version under `policy/`), and say whether earlier consents stay valid.

By opening a pull request, you agree to license your code under the [MIT license](../LICENSE).

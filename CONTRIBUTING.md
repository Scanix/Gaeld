# Contributing to Gäld

Thank you for considering a contribution! This guide explains how to get started.

---

## Code of Conduct

By participating, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## How to contribute

### Reporting bugs

Open an issue on [GitHub Issues](https://github.com/Scanix/Gaeld/issues) with:

- A clear title and description
- Steps to reproduce the problem
- Expected vs actual behaviour
- Your environment (OS, PHP/Node version, browser)

### Suggesting features

Open an issue with the **feature request** label. Describe the use case and what you'd like to see.

### Pull requests

1. **Fork** the repository and create your branch from `develop`.
2. **Install** the development environment (see below).
3. **Make your changes** — keep the PR focused on a single concern.
4. **Add tests** for new behaviour.
5. **Run the checks** before pushing (see below).
6. **Open a pull request** with a clear description of what and why.

For larger changes, please open an issue first so we can discuss the approach.

### Specification-driven development

Gäld uses [GitHub Spec Kit](https://github.com/github/spec-kit) for new or
substantial product changes. The existing codebase is brownfield: do not create
specifications for historical work just to fill the directory. Instead, start a
new flow-forward feature spec for the next change and keep its artifacts under
`specs/`:

Install the CLI once on macOS or Linux (Python 3.11+ and `uv` are required):

```bash
uv tool install specify-cli==0.16.3
specify version
```

```text
/speckit-specify → /speckit-clarify → /speckit-plan
→ /speckit-checklist → /speckit-tasks → /speckit-analyze
→ /speckit-implement → /speckit-converge
```

The specification describes user-facing behavior and acceptance criteria. The
plan records Laravel/domain implementation decisions, and the task list is the
execution checklist. For a small, obvious bug fix, the normal focused workflow
is acceptable; update the relevant spec when the intended behavior changes.

Spec Kit's project-local files live in `.specify/` and `.github/skills/speckit-*`.
Refresh those managed files with the CLI when upgrading Spec Kit, while
preserving the project constitution and any local template customizations.

---

## Development setup

```bash
./vendor/bin/sail composer install
pnpm install && pnpm build
cp .env.example .env
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan gaeld:install --demo
./vendor/bin/sail up
```

Or with Docker:

```bash
cp .env.example .env
docker compose up -d
./vendor/bin/sail artisan gaeld:install --demo
```

---

## Code style

We use [Laravel Pint](https://laravel.com/docs/pint) with the Laravel preset and [PHPStan](https://phpstan.org/) at level 5:

```bash
./vendor/bin/sail composer format   # auto-fix code style
./vendor/bin/sail composer lint     # check without fixing
./vendor/bin/sail phpstan analyse
```

### General rules

- Follow existing conventions in the codebase.
- Use type declarations (PHP return types, strict mode).
- Name classes and methods clearly — no abbreviations.
- Keep pull requests small and focused.

---

## Running tests

```bash
./vendor/bin/sail artisan test
```

Or with Docker/Sail:

```bash
./vendor/bin/sail artisan test
```

The test suite includes Unit, Feature, and Security test suites. All three must pass before a PR can be merged.

---

## Commit messages

Use clear, descriptive commit messages:

```
Short summary (max 72 chars)

Optional longer explanation of what changed and why.
Wrap at 72 characters.
```

Prefix with the area when helpful: `invoicing: add payment reminder emails`.

---

## Branch naming

Use descriptive branch names:

- `fix/invoice-pdf-alignment`
- `feature/bank-sync-integration`
- `docs/update-installation-guide`

---

## Git workflow

1. Fork the repository on GitHub.
2. Create a feature branch from `develop`: `git checkout -b feature/my-feature`
3. Commit your changes with clear messages.
4. Push to your fork: `git push origin feature/my-feature`
5. Open a pull request targeting `develop`.

The default public development branch is `develop`. All community contributions target `develop` via pull requests.

---

## License

By contributing, you agree that your contributions will be licensed under the [AGPL-3.0-or-later licence](LICENSE).

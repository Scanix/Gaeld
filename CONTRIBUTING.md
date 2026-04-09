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

1. **Fork** the repository and create your branch from `main`.
2. **Install** the development environment (see below).
3. **Make your changes** — keep the PR focused on a single concern.
4. **Add tests** for new behaviour.
5. **Run the checks** before pushing (see below).
6. **Open a pull request** with a clear description of what and why.

For larger changes, please open an issue first so we can discuss the approach.

---

## Development setup

```bash
composer install
pnpm install && pnpm build
cp .env.example .env
php artisan key:generate
php artisan gaeld:install --demo
php artisan serve
```

Or with Docker:

```bash
cp .env.example .env
docker compose up -d
docker compose exec laravel.test php artisan gaeld:install --demo
```

---

## Code style

We use [Laravel Pint](https://laravel.com/docs/pint) with the Laravel preset and [PHPStan](https://phpstan.org/) at level 5:

```bash
composer format          # auto-fix code style
composer lint            # check without fixing
vendor/bin/phpstan analyse
```

### General rules

- Follow existing conventions in the codebase.
- Use type declarations (PHP return types, strict mode).
- Name classes and methods clearly — no abbreviations.
- Keep pull requests small and focused.

---

## Running tests

```bash
php artisan test
```

Or with Docker:

```bash
docker compose exec laravel.test php artisan test
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
2. Create a feature branch from `main`: `git checkout -b feature/my-feature`
3. Commit your changes with clear messages.
4. Push to your fork: `git push origin feature/my-feature`
5. Open a pull request targeting `main`.

We use a Git Flow model internally — `main` is the stable development branch. All community contributions target `main` via pull requests.

---

## License

By contributing, you agree that your contributions will be licensed under the [AGPL-3.0-or-later licence](LICENSE).

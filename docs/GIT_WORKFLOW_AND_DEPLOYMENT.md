# Git Workflow And Deployment

## Repository Model

The Gäld ecosystem is split across separate repositories:

- `api`
- `web`
- `docs`
- `dl-stockaj`
- `orchestrator`

The API repository is the only one with both public GitHub and private GitLab remotes.

## API Remote Strategy

- `origin`: public GitHub repository for the Community Edition.
- `gitlab`: private GitLab repository used for production deployment.

This split exists because:

- Community Edition code is published publicly.
- production-only files and private EE integration live in the GitLab deployment flow,
- and the EE plugin must not leak into the public branch model.

## Branch Strategy

### API

- `develop`: active integration branch.
- `main`: public GitHub mirror for Community Edition releases.
- `production`: GitLab deployment branch with production-only files and EE integration.

### Other Repositories

- `develop`: active work branch.
- `main` or production equivalent: release branch depending on repo policy.

## CE And EE Split

- Community Edition lives in the public API codebase.
- Enterprise Edition lives in `api/plugins/gaeld-ee` and is deployed privately.
- Public releases and tags must not assume the EE plugin is present.
- Deployment automation must sync the EE plugin separately before API production deployment.

## Day-To-Day Workflow

1. Work in `develop`.
2. Run tests and static analysis before pushing.
3. Push `develop` to the appropriate remotes.
4. Cut release tags from the repository that is actually being released.
5. Merge or promote into `production` only when the deployment set is ready.

## Release Rules

- Use semantic version tags.
- Keep changelog numbering aligned with Git tags.
- Release notes must state whether a release is Community Edition only or ecosystem-wide.
- Do not deploy directly from an untagged dirty working tree.

## Production Deployment Flow

### API

1. Ensure `develop` is stable.
2. Update release notes and tags.
3. Promote the intended release into `main` if the public mirror should reflect it.
4. Merge the deployable state into `production` locally.
5. Push `production` to GitLab.
6. Run Deployer against the production host.

### EE Plugin

1. Enter `api/plugins/gaeld-ee`.
2. Ensure the working tree is clean.
3. Push the plugin to its private remote before API deployment.

### Web, Docs, And Stockaj Download Site

- `web` deploys through PM2.
- `docs` builds static output, syncs via `rsync`, and refreshes the Meilisearch docs index.
- `dl-stockaj` deploys through PM2.

## Orchestrator Script

`orchestrator/deploy-all.sh` is the operator entrypoint.

Key behaviors:

- deploys the EE plugin first,
- merges and pushes the API `production` branch before Deployer runs,
- builds and syncs docs,
- triggers PM2 deployments for the frontend applications.

## Deployer Expectations

`deploy.php` on the production branch is expected to:

- deploy with a release directory strategy,
- upload locally built frontend assets,
- run migrations,
- rebuild Laravel caches,
- sync permissions,
- reload PHP-FPM,
- restart workers after publish.

## Safety Checks Before Deploy

1. Working tree clean in every repo being deployed.
2. Required remotes configured.
3. Release tag created if this is a release deployment.
4. `.env` and server secrets already present on the target host.
5. Backup health verified.

## Safety Checks After Deploy

1. Verify `/up` and `/login`.
2. Verify queue and Horizon status.
3. Verify one authenticated API request if API access is enabled.
4. Verify docs search indexing if docs were deployed.
5. Confirm error tracking is quiet after the deploy.

## Known Operational Debt

- Changelog version numbering has drifted from Git tags in the past and must be normalized.
- The top-level workspace is not a single Git repository, so cross-repo changes must be committed per project.
- GitLab remains the production source of truth for deployable API state.
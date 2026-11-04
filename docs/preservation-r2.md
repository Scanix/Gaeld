# R2: accounting content preservation — 3.10.0-rc.1

This candidate adds dated, versioned accounting packages to the R1 installation and recovery baseline. The capability is the same in CE and EE and does not require a subscription or private registry. The CE/EE boundary contract remains 1.0.0.

R2 checks content integrity. It does not create PDF/A, digital signatures, trusted timestamps, protected remote storage or cryptographic renewals. It does not certify GeBüV compliance. An explicit request for the unsupported archival-proof profile fails before a snapshot is created; it is never downgraded to ordinary PDF. Keep `ARCHIVE_PROOFS_ENABLED=false` for this release.

## What is preserved

A single PostgreSQL statement captures the organization, exact fiscal-year dates, accounting records, references, document inventory and available audit/control history in a consistent database snapshot. Capture and its preparation request commit in the same transaction. Closing a financial year records that request after closing entries; PDF/file work runs afterward.

Each package includes JSON and CSV data, statement values, ordinary PDF reports, original supporting-document bytes, a versioned retention policy, operating instructions and a manifest listing the byte size and SHA-256 of every content file. Missing or changed required originals prevent successful preparation. Previously generated reports and original documents are preserved as separate evidence; a missing original is never silently regenerated.

The manifest has `schema_version=1.0.0`, `proof_profile=gaeld-content-package/1.0.0` and `report_profile=PDF`. `prepared` means the content package was built, not that it was independently sealed. Checking a package compares its manifest to the local database catalog and checks the complete file inventory, including unexpected files. Export refuses an invalid package.

The default minimum retention is calculated from the frozen fiscal-year end plus ten years. The operator may extend it or record/release a reasoned legal hold. This release implements no disposal command. PostgreSQL and model guards protect snapshots, audit records, original-document versions and posted accounting amounts against ordinary application changes. These guards do not replace protected external storage or prevent an administrator with infrastructure privileges from modifying the database or filesystem.

## Operation

Use **Accounting → Accounting preservation** to request, inspect, verify, export or resume a package for the currently selected company. Existing historical archives remain available separately. Permissions follow the company's accounting roles; viewing does not grant preparation rights. Financial records belonging to another company are never included in the page data or its package operations.

Commands run inside the application runtime:

```text
php artisan archive:capture --organization=ORGANIZATION_UUID
php artisan archive:dispatch
php artisan archive:package inspect PACKAGE_UUID
php artisan archive:package verify PACKAGE_UUID
php artisan archive:package export PACKAGE_UUID --output=/new/path/package.zip
php artisan archive:package resume PACKAGE_UUID
php artisan archive:retain PACKAGE_UUID --hold --reason="Pending accounting review"
php artisan archive:check --organization=ORGANIZATION_UUID
```

The scheduler captures daily at 00:15, dispatches committed requests every minute and checks local content daily at 04:00. The control reports any started financial year with no package, as well as incomplete preparation and changed or missing files. Horizon's `archives` connection has a 1,860-second retry interval, a 1,800-second worker timeout and a 40-minute outbox lease. PostgreSQL locks prevent concurrent preparation even if Redis is lost. Exhausted dispatch attempts become a visible incident; manual resume resets the durable attempt counter. Resume uses the same frozen snapshot and preserves already written bytes.

## Existing years and recovery

Historical capture is explicit and can be resumed:

```text
php artisan archive:backfill --organization=ORGANIZATION_UUID --fiscal-year=FISCAL_YEAR_UUID
php artisan archive:dispatch
```

Repeated capture of unchanged data reuses the same package. Changed accounting data or control history produces a subsequent version. Historical material is marked `historical_backfill`; original dates remain data, and no assertion is made about integrity before capture. Review missing-source incidents before relying on any historical package.

Maintain the R1 encrypted backup of database, original documents, packages, configuration and the original application key. Copy the encrypted backup outside the installation and keep recovery keys available to authorized successors. Restoring only package files or only a database is not a complete recovery.

At least annually, restore a complete backup to a separate environment and record the operator, date, environment and recovered package manifest hashes. Submit the resulting JSON to:

```text
php artisan archive:check --annual --recovery-report=/path/recovery-report.json
```

The report needs `environment`, `operator`, `completed_at` and a `packages` object mapping package UUIDs to manifest SHA-256 values. Its date must describe a completed restoration within the past year. An annual check without matching recovery evidence fails. The attestation is operator-supplied, not independently certified by Gäld. Each company's retained control report includes only its own package hashes. The scheduled annual control deliberately reports missing recovery evidence until an operator performs this step.

## Upgrade and release boundary

Apply the new migration forward after taking and verifying a complete R1 recovery backup. It adds evidence/outbox tables and PostgreSQL mutation guards without rewriting old ledger rows, UUIDs, dates, files or APP_KEY. The migration's reverse operation drops preservation data and protections; it is for isolated tests, not a production rollback strategy. Recover an earlier installation only by the explicit complete-restore procedure.

The nullable cryptographic-proof columns are not part of R2. R3 must add them in a separate migration and update the package mutation guard additively; it must never edit an already deployed R2 migration. The immutable `archival_pdf` flag remains false in R2 for forward compatibility. No proof services or cryptographic-provider dependencies are shipped in this candidate.

Acceptance covers populated legacy-schema migration, exact amounts and extended financial years, original/version preservation, missing/tampered files, durable recovery after interrupted dispatch, three-company separation, historical resumption, access roles and local controls. A staging deployment and its operational acceptance are separate steps; this document does not claim they have already occurred.

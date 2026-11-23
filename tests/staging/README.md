# Isolated R2 staging acceptance

`preservation_r2.php` exercises actual Laravel services and job handlers without PHPUnit, Faker or development dependencies. It never runs migrations, drops tables, empties a database or changes existing users. Use it only after applying R2 to the dedicated QA database and independent storage.

Allowed databases are `gaeld_ce_preservation`, `gaeld_ee_preservation` and the local `testing_r2_release`. Both the explicit argument and the actual PostgreSQL connection must match. The script preserves pre-existing rows and evidence files, creates three new companies prefixed with a unique `QA` run identifier, and checks that the baseline remains unchanged. Never point the application root at the existing staging installation's real database or shared storage.

Create a private file with mode 0600, unique synthetic email addresses ending in `.test` or `.invalid`, and passwords of at least 20 characters:

```json
{
  "owner": {"email": "owner-UNIQUE@example.test", "password": "GENERATE_A_NEW_RANDOM_PASSWORD"},
  "viewer": {"email": "viewer-UNIQUE@example.test", "password": "GENERATE_ANOTHER_RANDOM_PASSWORD"}
}
```

The owner receives all three companies; the viewer receives only the first. Credentials are never included in the report. Keep automatic R3 proofs disabled for this content-profile acceptance.

Run inside the isolated application's PHP 8.4 runtime:

```text
php /path/to/preservation_r2.php --root=/path/to/isolated/application \
  --database=gaeld_ce_preservation --credentials=/private/qa-credentials.json \
  --output=/new/path/r2-ce-business-report.json --run-id=unique-run-id --queued
```

Use the EE database name and isolated EE application root for the second run, adding `--ee-version=2.9.31`. This checks the installed compatibility pair and requires plugins and SaaS mode to be active before creating fixtures. EE runs without an exact version are rejected. The existing R1 fixture can remain in the CE database. Start the real `archives` worker with the same database, Redis and storage settings before using `--queued`. Queue mode refuses to dispatch when unrelated unfinished archive outbox entries exist and waits at most 60 seconds for the three newly created packages. Without `--queued`, the report explicitly identifies direct job-handler execution rather than worker acceptance.

The fixture checks exact decimal entries, three-company snapshot separation, original-byte exports, historical deduplication, a missing-document incident followed by same-package recovery, durable outbox completion, immutable posted values, cross-company capture refusal, annual recovery evidence requirements and preservation of all pre-existing baseline records/files that it inventories. It intentionally leaves synthetic data and generated evidence available for HTTP acceptance; there is no cleanup operation.

HTTP acceptance is separate and reported as pending by this script. Use the private credentials and report UUIDs to verify:

- Owner login, company switching and `/accounting/conservation` pages for all three companies.
- Download each reported `export_route` and compare the ZIP's `manifest.json` SHA-256 to the report.
- Viewer login: the first company is visible, creation/resume is forbidden, and another company's export/verify/resume returns 404.
- Financial-year dates, prepared/content-only wording and absence of a sealing action in the R2 UI.

Run the HTTP checks on the isolated loopback application with Python 3:

```text
python3 /path/to/preservation_http.py --url=http://127.0.0.1:18090 \
  --credentials=/private/qa-credentials.json \
  --business-report=/path/r2-ee-business-report.json --output=/new/path/r2-ee-http-report.json
```

This logs in using actual sessions and CSRF protection, selects all three companies, checks Inertia page data and verifies every exported file against the captured manifest. It also tests the viewer's allowed export and forbidden creation, resume and foreign-company requests. Credentials and session tokens are never written to its report. Use port 18089 for the isolated CE runtime.

EE accounting preservation and authorized company switching remain available without a paid subscription. The ordinary dashboard still follows the existing EE subscription policy. No SaaS data, billing plan or license is changed by these fixtures.

# Accounting preservation

This module belongs to the public CE core. CE and EE use the same archive format,
connectors, verification tools and controls. No subscription is required for
preservation, reading or export. Operator certificates and storage accounts are
configured independently of Gäld subscriptions.

R3 is a release candidate. Existing R2 content packages remain readable and are never rewritten or retroactively sealed. Request a new version to select the proof profile.

The implemented profile is `gaeld-sealed-package/1.0.0`; its package format is
`1.0.0`. These versions are independent of the CE/EE boundary contract `1.0.0`.
Software tests establish technical behaviour, not a general GeBüV certification.
Before relying on this profile, validate the deployed storage, trust services,
procedures and responsibilities with the organization's fiduciary and legal reviewer.

## Packages and originals

A package has an immutable UUID, company UUID, exact financial-year UUID and
version. Its snapshot contains full journal lines, frozen reference data,
document relationships, structured exports and readable reports. Uploaded
supporting documents and issued PDFs are versioned separately. Original bytes
are retained unchanged; PDF/A conversion only applies to generated reports.

Closing commits a consistent snapshot and PostgreSQL outbox entry. Workers
prepare files after commit. The dispatcher recovers outstanding work even after
loss of the Redis queue. Preparation and proof operations use PostgreSQL locks;
retries use the same captured data and preserve previously written bytes.

The states distinguish pending, preparation, prepared, sealing, deposited,
verified and incident. **Prepared does not mean sealed.** A missing original or
invalid proof prevents success. Missing provider configuration remains visible
as an incident. Generating a new version does not repair a lost original.

Historical UUIDs, files, hashes and dates are preserved. Backfill is labelled
`historical_backfill` and establishes a new capture date, not a retrospective
proof of authenticity. Existing individual archive downloads remain available.

The default minimum retention ends ten years after the financial year ends.
Operators can extend it or apply a legal hold. These releases contain no legal
archive purge command. Backup rotation is independent of legal retention.

## Configure the proof profile

Runtime requirements: PostgreSQL, OpenSSL 3, Ghostscript, veraPDF, PHP DOM and the
public AWS SDK. The production image must contain the validated versions of
Ghostscript and veraPDF recorded in its release inventory. Report conversion
targets PDF/A-2b and must pass veraPDF before a manifest is signed.

Configure the following through the installation's protected environment. Use
paths readable by the worker; never put private keys in a source checkout or in
the archive package. In Docker, operator files can be mounted from
`shared/secrets` at `/run/gaeld-secrets`.

```dotenv
ARCHIVE_PROOFS_ENABLED=true
ARCHIVE_SIGNING_CERTIFICATE=/run/gaeld-secrets/signer.pem
ARCHIVE_SIGNING_PRIVATE_KEY=/run/gaeld-secrets/signer-key.pem
ARCHIVE_SIGNING_PASSPHRASE_FILE=/run/gaeld-secrets/signer-passphrase
ARCHIVE_SIGNING_CHAIN=/run/gaeld-secrets/signer-chain.pem
ARCHIVE_SIGNING_TRUST_STORE=/run/gaeld-secrets/signer-ca.pem
ARCHIVE_SIGNING_CRL=/run/gaeld-secrets/signer-crls.pem
ARCHIVE_SIGNING_FINGERPRINT=
ARCHIVE_TIMESTAMP_URL=
ARCHIVE_TIMESTAMP_TRUST_STORE=/run/gaeld-secrets/tsa-ca.pem
ARCHIVE_TIMESTAMP_CHAIN=/run/gaeld-secrets/tsa-chain.pem
ARCHIVE_TIMESTAMP_CRL=/run/gaeld-secrets/tsa-crls.pem
ARCHIVE_S3_REGION=eu-central-1
ARCHIVE_S3_BUCKET=
ARCHIVE_S3_ACCESS_KEY=
ARCHIVE_S3_SECRET_KEY=
```

Fill the empty values with the operator's selected services. The signing
fingerprint is an independently approved certificate SHA-256 fingerprint;
comma-separated old and current fingerprints support deliberate rotation.
Custom S3 endpoints and path-style addressing are configured in
`config/archiving.php`. Timestamp authentication supports no authentication, Bearer, or Basic over verified HTTPS. Configure only one method. Prefer `ARCHIVE_TIMESTAMP_TOKEN_FILE` for Bearer, or both `ARCHIVE_TIMESTAMP_USERNAME_FILE` and `ARCHIVE_TIMESTAMP_PASSWORD_FILE` for Basic; these files stay outside packages and source control. `ARCHIVE_TIMESTAMP_TOKEN` remains available for existing installations. Credentials embedded in the URL are rejected. Provider error bodies and authentication values are not retained in exceptions.

`ARCHIVE_TIMESTAMP_CA_BUNDLE` and `ARCHIVE_S3_CA_BUNDLE` optionally select an explicit TLS CA bundle; verification is never disabled. These TLS roots are distinct from the independently approved signing and timestamp trust policies. Set `ARCHIVE_GHOSTSCRIPT_BINARY`, `ARCHIVE_VERAPDF_BINARY` and `ARCHIVE_ICC_PROFILE` for a native installation.

The bucket must support versioning and Object Lock **Compliance** mode.
Deposit checks capabilities, writes retention and reads it back before reporting
verified protection. Enabling the profile alone does not validate a provider.
Governance mode is not accepted. Keep IAM permissions restricted to the
installation prefix, including version reads, retention, legal holds and
inventory listing. A protected catalog records package identities, hashes and
object versions so a missing local package or substitution can be detected. Online verification requires the current receipt to cover every evidence version recorded in prior protected catalogs for that package; rolling back both local evidence and its receipt cannot hide a recorded renewal. Retention extensions and legal holds cover all historical catalog versions as well as the current object inventory.
An unfiltered `archive:check` also compares all protected company identities
with the local company catalog, including when preservation is disabled but a
storage bucket remains configured. This detects disappearance of an entire
company. These global reports are retained under
`storage/app/private/preservation-controls/global` on the default local disk;
include them in backup and annual control exports. An organization-scoped check
only establishes completeness for that organization.

The manifest is signed with detached CMS; RFC 3161 timestamps cover the signed
evidence. Certificates, validation results and renewals remain available with
the package. Existing evidence is never overwritten during renewal. Keep trust
anchors and the expected inventory independently of the package itself.

The operator supplies current CRL bundles for the full signer and timestamp
certificate chains and must refresh them before their validity expires. New
signatures and timestamps reject expired or revoked certificates. Historical
verification uses the trusted timestamp generation time and the preserved CRLs;
expiry after a valid timestamp does not by itself invalidate historical proof.
Keep historical trust anchors and approved signer fingerprints available when
rotating certificates. Renewal deadlines use the most recent trusted timestamp,
the configured renewal interval and a margin before certificate expiry.

## Commands and user interface

The Accounting menu exposes preservation to users with accounting read rights.
Preparing and resuming packages requires the closing permission. Company
boundaries apply to every UUID action. Accesses and controls use a dedicated
append-only audit table, separate from expiring technical logs.

Run these Artisan commands through the installation's application service
(through `vendor/bin/sail artisan` in development):

```text
archive:capture --organization=ORG_UUID --fiscal-year=YEAR_UUID --archival-pdf
archive:dispatch
archive:package prepare PACKAGE_UUID
archive:package inspect PACKAGE_UUID
archive:package verify PACKAGE_UUID
archive:package export PACKAGE_UUID --output=/exports/new-package.zip
archive:package resume PACKAGE_UUID
archive:backfill --organization=ORG_UUID --fiscal-year=YEAR_UUID
archive:retain PACKAGE_UUID --until=2047-12-31 --reason="Approved extended retention"
archive:retain PACKAGE_UUID --hold --reason="Pending litigation review"
archive:renew PACKAGE_UUID
archive:renew --due
archive:check --organization=ORG_UUID
```

`archive:capture` without a company/year filter visits all eligible financial
years. Capture deduplicates unchanged snapshots. Backfill can be restarted;
already captured work retains its identity. The outbox is the persistent
progress record. Preparation and export failures return non-zero status.

The scheduler dispatches pending work every minute, captures daily at 00:15,
checks daily at 04:00 and renews due evidence at 04:30, in the application time
zone. Annual control is scheduled on 15 January. The `archives` Horizon
supervisor has a 30-minute timeout and a longer queue retry interval; keep the
deployment drain period at least 35 minutes.

## Independent verification

Copy `scripts/archive-verifier/` and the exported package to a separate machine.
Extract the ZIP without accepting unsafe paths or symbolic links. The verifier
requires no Gäld database, Composer installation or EE account.

```sh
php scripts/archive-verifier/verify.php \
  --directory=/recovery/package \
  --signer-trust=/trusted/signer-ca.pem \
  --tsa-trust=/trusted/tsa-ca.pem \
  --signer-fingerprint=APPROVED_CERTIFICATE_SHA256 \
  --expected-manifest-sha256=INDEPENDENT_INVENTORY_SHA256 \
  --verapdf=/opt/verapdf/verapdf
```

The result distinguishes content integrity, proof validation and inventory
pinning. `--integrity-only` checks files without claiming the proof profile.
Offline verification does not establish the current remote Object Lock settings
or prove that no other package was deleted. A manifest hash remains unchanged across renewals: the offline result explicitly reports `latest_evidence_pinned: false`. It validates the evidence supplied, without establishing that it is the newest retained evidence set. Use the independently retained protected catalog and
`archive:check` for remote completeness and retention checks.

## Annual recovery and written control

Recover the complete company inventory on a separate environment, read every
report and supporting document, run independent verification against the
externally retained hashes and record the outcome. The annual command requires
an operator-supplied recovery report to claim completion:

```json
{
  "environment": "independent-recovery-workstation",
  "operator": "Responsible operator",
  "completed_at": "2027-01-10T10:00:00Z",
  "packages": {"PACKAGE_UUID": "EXPECTED_MANIFEST_SHA256"}
}
```

```text
archive:check --annual --organization=ORG_UUID --recovery-report=/controls/recovery.json
```

The report must be recent and name a different environment. Its human recovery
attestation is explicitly labelled as supplied by the operator. Without it,
the scheduled annual check records an incomplete control, not a successful
independent restoration. Reports are retained in the preservation audit log and
included in subsequent captures.

Assign an owner for provider credentials and renewal, a backup operator and a
fiduciary responsible for accounting completeness. Record who may prepare,
download, extend retention and release holds. Review incidents daily and access
rights after staff changes. Retain provider incident records and annual review
sign-off alongside the control report.

## Backup and recovery distinction

`gaeld backup` creates a coherent encrypted operational recovery set. Time
Machine or Proton Drive may copy the **completed** backup and its receipt.
Neither a live database volume copy nor file synchronisation substitutes for
the protected archive inventory. Keep the age recovery key separately, with an
off-site copy; losing APP_KEY or the backup key can make recovery impossible.

Protocol references: [CMS](https://docs.openssl.org/3.5/man1/openssl-cms/),
[RFC 3161](https://docs.openssl.org/3.5/man1/openssl-ts/),
[veraPDF](https://docs.verapdf.org/validation/),
[Object Lock](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lock.html).

## Release qualification

The native staging laboratory uses synthetic historical financial data, separate test signer/TSA/TLS CAs, an HTTPS OpenSSL RFC 3161 service and a source-built MinIO server with Compliance retention. Test authorities are never configured as production trust anchors. This validates only the recorded laboratory profile; it does not qualify SwissSign, AWS, Exoscale or another commercial service. Preserve exact commit, source digest, binaries, test reports, independent recovery results and the CE/EE pair in the release dossier. Before stable publication, validate the selected commercial services, operational procedures and accounting/legal review required by the release plan.

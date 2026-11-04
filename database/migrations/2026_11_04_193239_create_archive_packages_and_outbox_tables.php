<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('issued_document_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fiscal_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_type');
            $table->string('document_id');
            $table->unsignedInteger('version');
            $table->text('storage_path');
            $table->string('checksum_sha256', 64);
            $table->timestampTz('created_at');
            $table->unique(['organization_id', 'document_type', 'document_id', 'version'], 'issued_document_version_unique');
        });
        Schema::create('archive_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('state')->default('pending')->index();
            $table->string('provenance')->default('native');
            $table->boolean('archival_pdf')->default(false);
            $table->jsonb('snapshot');
            $table->string('snapshot_sha256', 64);
            $table->string('package_path');
            $table->string('manifest_sha256', 64)->nullable();
            $table->timestampTz('captured_at');
            $table->timestampTz('retention_until')->index();
            $table->boolean('legal_hold')->default(false);
            $table->timestampTz('prepared_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->text('failure')->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'fiscal_year_id', 'version']);
        });
        Schema::create('archive_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('archive_package_id')->unique()->constrained()->restrictOnDelete();
            $table->timestampTz('available_at')->index();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampsTz();
        });
        Schema::create('archive_source_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('archive_package_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('organization_id')->constrained()->restrictOnDelete();
            $table->string('source_type');
            $table->string('source_id');
            $table->text('source_path');
            $table->string('source_sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unique(['archive_package_id', 'source_type', 'source_id']);
            $table->index('organization_id');
        });
        Schema::create('archive_access_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('archive_package_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->jsonb('metadata');
            $table->timestampTz('occurred_at')->index();
            $table->index(['organization_id', 'archive_package_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION gaeld_reject_evidence_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Retained accounting evidence is append-only';
END;
$$;
CREATE OR REPLACE FUNCTION gaeld_guard_archive_package() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Archive disposal requires an authorized retention process'; END IF;
    IF (to_jsonb(NEW) - ARRAY['state','failure','verified_at','prepared_at','retention_until','legal_hold','manifest_sha256','updated_at']) IS DISTINCT FROM
       (to_jsonb(OLD) - ARRAY['state','failure','verified_at','prepared_at','retention_until','legal_hold','manifest_sha256','updated_at'])
       OR NEW.retention_until < OLD.retention_until
       OR (OLD.manifest_sha256 IS NOT NULL AND NEW.manifest_sha256 IS DISTINCT FROM OLD.manifest_sha256) THEN
       RAISE EXCEPTION 'An archive snapshot or manifest cannot be replaced';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER archive_package_guard BEFORE UPDATE OR DELETE ON archive_packages FOR EACH ROW EXECUTE FUNCTION gaeld_guard_archive_package();
CREATE OR REPLACE FUNCTION gaeld_guard_posted_entry() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.is_posted OR OLD.archived_at IS NOT NULL THEN
        IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Posted journal entries cannot be deleted'; END IF;
        IF ROW(NEW.organization_id,NEW.date,NEW.reference,NEW.description,NEW.is_posted) IS DISTINCT FROM ROW(OLD.organization_id,OLD.date,OLD.reference,OLD.description,OLD.is_posted)
           OR ((OLD.type IS NOT NULL OR OLD.archived_at IS NOT NULL) AND NEW.type IS DISTINCT FROM OLD.type)
           OR (OLD.archived_at IS NOT NULL AND NEW.archived_at IS DISTINCT FROM OLD.archived_at) THEN
           RAISE EXCEPTION 'Posted journal entries require a correcting entry';
        END IF;
        IF NEW.type IS DISTINCT FROM OLD.type THEN
            INSERT INTO archive_access_events (id,organization_id,actor_id,action,metadata,occurred_at)
            VALUES (gen_random_uuid(), OLD.organization_id, NULLIF(current_setting('gaeld.actor_id', true),'')::bigint,
                'ledger.classification_changed', jsonb_build_object('entry_id',OLD.id,'previous_type',OLD.type,'new_type',NEW.type,'database_role',current_user), CURRENT_TIMESTAMP);
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION gaeld_guard_posted_line() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE parent_posted boolean; parent_organization uuid;
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') THEN
        SELECT (is_posted OR archived_at IS NOT NULL), organization_id INTO parent_posted, parent_organization FROM journal_entries WHERE id=OLD.journal_entry_id FOR SHARE;
        IF parent_posted THEN
            IF TG_OP = 'UPDATE' AND (to_jsonb(NEW) - ARRAY['lettrage_key','updated_at']) IS NOT DISTINCT FROM (to_jsonb(OLD) - ARRAY['lettrage_key','updated_at']) THEN
                IF NEW.lettrage_key IS DISTINCT FROM OLD.lettrage_key THEN
                    INSERT INTO archive_access_events (id,organization_id,actor_id,action,metadata,occurred_at)
                    VALUES (gen_random_uuid(), parent_organization, NULLIF(current_setting('gaeld.actor_id', true),'')::bigint,
                        'ledger.matching_changed', jsonb_build_object('line_id',OLD.id,'previous_key',OLD.lettrage_key,'new_key',NEW.lettrage_key,'database_role',current_user), CURRENT_TIMESTAMP);
                END IF;
                RETURN NEW;
            END IF;
            RAISE EXCEPTION 'Posted journal lines require a correcting entry';
        END IF;
    END IF;
    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        SELECT (is_posted OR archived_at IS NOT NULL) INTO parent_posted FROM journal_entries WHERE id=NEW.journal_entry_id FOR SHARE;
        IF parent_posted THEN RAISE EXCEPTION 'Posted journal lines cannot be appended or replaced'; END IF;
        RETURN NEW;
    END IF;
    RETURN OLD;
END;
$$;
CREATE TRIGGER posted_entry_guard BEFORE UPDATE OR DELETE ON journal_entries FOR EACH ROW EXECUTE FUNCTION gaeld_guard_posted_entry();
CREATE TRIGGER posted_line_guard BEFORE INSERT OR UPDATE OR DELETE ON transaction_lines FOR EACH ROW EXECUTE FUNCTION gaeld_guard_posted_line();
CREATE OR REPLACE FUNCTION gaeld_guard_archived_document() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.archived_at IS NOT NULL THEN
        RAISE EXCEPTION 'An archived document requires a correcting document';
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END;
$$;
SQL);
            foreach (['issued_document_files', 'archive_source_files', 'archive_access_events'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION gaeld_reject_evidence_mutation()");
            }
            foreach (['invoices', 'expenses', 'salary_slips'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_archive_guard BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION gaeld_guard_archived_document()");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['invoices', 'expenses', 'salary_slips'] as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_archive_guard ON {$table}");
            }
            DB::unprepared('DROP FUNCTION IF EXISTS gaeld_guard_archived_document()');
            DB::unprepared('DROP TRIGGER IF EXISTS posted_entry_guard ON journal_entries; DROP TRIGGER IF EXISTS posted_line_guard ON transaction_lines; DROP FUNCTION IF EXISTS gaeld_guard_posted_entry(); DROP FUNCTION IF EXISTS gaeld_guard_posted_line();');
        }
        Schema::dropIfExists('archive_access_events');
        Schema::dropIfExists('archive_source_files');
        Schema::dropIfExists('archive_outbox');
        Schema::dropIfExists('archive_packages');
        Schema::dropIfExists('issued_document_files');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS gaeld_guard_archive_package(); DROP FUNCTION IF EXISTS gaeld_reject_evidence_mutation();');
        }
    }
};

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
        Schema::table('archive_packages', function (Blueprint $table) {
            $table->timestampTz('sealed_at')->nullable();
            $table->timestampTz('deposited_at')->nullable();
            $table->timestampTz('check_due_at')->nullable()->index();
            $table->jsonb('proof_result')->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION gaeld_guard_archive_package() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Archive disposal requires an authorized retention process'; END IF;
    IF (to_jsonb(NEW) - ARRAY['state','failure','verified_at','prepared_at','sealed_at','deposited_at','check_due_at','proof_result','retention_until','legal_hold','manifest_sha256','updated_at']) IS DISTINCT FROM
       (to_jsonb(OLD) - ARRAY['state','failure','verified_at','prepared_at','sealed_at','deposited_at','check_due_at','proof_result','retention_until','legal_hold','manifest_sha256','updated_at'])
       OR NEW.retention_until < OLD.retention_until
       OR (OLD.manifest_sha256 IS NOT NULL AND NEW.manifest_sha256 IS DISTINCT FROM OLD.manifest_sha256) THEN
       RAISE EXCEPTION 'An archive snapshot or manifest cannot be replaced';
    END IF;
    RETURN NEW;
END;
$$;
SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('archive_packages')->where('archival_pdf', true)->orWhereNotNull('proof_result')->orWhereNotNull('sealed_at')->orWhereNotNull('deposited_at')->exists()) {
            throw new LogicException('Proof records must be retained. Recover the complete verified database, files, keys and release instead of rolling back this migration.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
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
SQL);
        }
        Schema::table('archive_packages', function (Blueprint $table) {
            $table->dropColumn(['sealed_at', 'deposited_at', 'check_due_at', 'proof_result']);
        });
    }
};

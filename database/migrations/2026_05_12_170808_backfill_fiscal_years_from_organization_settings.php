<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backfill fiscal_years from existing organization settings.
     *
     * For each organization:
     *  - parses fiscal_year_start (MM-DD)
     *  - generates one record per calendar year between the org's earliest
     *    journal entry (or creation) and next year
     *  - marks years listed in closed_fiscal_years as 'closed'
     *  - marks past years as 'expired', current year as 'operative',
     *    future years as 'planned'
     *
     * Idempotent: skips date ranges that already exist for the org.
     */
    public function up(): void
    {
        $orgs = DB::table('organizations')->get([
            'id', 'fiscal_year_start', 'closed_fiscal_years', 'created_at',
        ]);

        $today = Carbon::today();
        $currentYear = (int) $today->year;
        $now = Carbon::now()->toDateTimeString();

        foreach ($orgs as $org) {
            $startMonthDay = $org->fiscal_year_start ?: '01-01';
            $parts = preg_split('/[\.\-\/]/', (string) $startMonthDay);
            $month = (int) ($parts[0] ?? 1);
            $day = (int) ($parts[1] ?? 1);
            if ($month < 1 || $month > 12) {
                $month = 1;
            }
            if ($day < 1 || $day > 31) {
                $day = 1;
            }

            $closedYears = [];
            if (! empty($org->closed_fiscal_years)) {
                $decoded = json_decode((string) $org->closed_fiscal_years, true);
                if (is_array($decoded)) {
                    $closedYears = array_map('intval', $decoded);
                }
            }

            $orgCreatedYear = $org->created_at
                ? (int) Carbon::parse($org->created_at)->year
                : $currentYear;

            $earliestEntry = DB::table('journal_entries')
                ->where('organization_id', $org->id)
                ->min('date');
            $earliestEntryYear = $earliestEntry
                ? (int) Carbon::parse($earliestEntry)->year
                : $orgCreatedYear;

            $startYear = min(
                $orgCreatedYear,
                $earliestEntryYear,
                $closedYears !== [] ? min($closedYears) : $currentYear,
            );
            $endYear = $currentYear + 1;

            for ($year = $startYear; $year <= $endYear; $year++) {
                $start = Carbon::create($year, $month, $day)->startOfDay();
                // Standard fiscal year: 12 months, ending the day before the next start
                $end = $start->copy()->addYear()->subDay();

                $exists = DB::table('fiscal_years')
                    ->where('organization_id', $org->id)
                    ->whereDate('start_date', $start->toDateString())
                    ->whereDate('end_date', $end->toDateString())
                    ->exists();
                if ($exists) {
                    continue;
                }

                if (in_array($year, $closedYears, true)) {
                    $status = 'closed';
                    $lockedAt = $now;
                } elseif ($end->lt($today)) {
                    $status = 'expired';
                    $lockedAt = null;
                } elseif ($start->gt($today)) {
                    $status = 'planned';
                    $lockedAt = null;
                } else {
                    $status = 'operative';
                    $lockedAt = null;
                }

                DB::table('fiscal_years')->insert([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $org->id,
                    'name' => (string) $year,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => $status,
                    'locked_at' => $lockedAt,
                    'locked_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('fiscal_years')->delete();
    }
};

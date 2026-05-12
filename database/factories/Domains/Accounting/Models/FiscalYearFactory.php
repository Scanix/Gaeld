<?php

namespace Database\Factories\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = (int) now()->year;

        return [
            'organization_id' => Organization::factory(),
            'name' => (string) $year,
            'start_date' => Carbon::create($year, 1, 1)->toDateString(),
            'end_date' => Carbon::create($year, 12, 31)->toDateString(),
            'status' => FiscalYearStatus::Operative->value,
            'locked_at' => null,
            'locked_by_user_id' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(['status' => FiscalYearStatus::Planned->value]);
    }

    public function operative(): static
    {
        return $this->state(['status' => FiscalYearStatus::Operative->value]);
    }

    public function expired(): static
    {
        return $this->state(['status' => FiscalYearStatus::Expired->value]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => FiscalYearStatus::Closed->value,
            'locked_at' => now(),
        ]);
    }

    /**
     * Realistic Swiss "long fiscal year" example: founded Oct 3 of the
     * current year, first fiscal year ends Dec 31 of the following year
     * (~15 months). Swiss law allows up to 23 months.
     */
    public function longYear(): static
    {
        $startYear = (int) now()->year;

        return $this->state([
            'name' => "{$startYear}–".($startYear + 1),
            'start_date' => Carbon::create($startYear, 10, 3)->toDateString(),
            'end_date' => Carbon::create($startYear + 1, 12, 31)->toDateString(),
        ]);
    }
}

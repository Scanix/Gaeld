<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Installation-level ownership state for the Community and commercial Editions.
 *
 * @property string $singleton_key
 * @property string $mode
 * @property string $migration_status
 * @property string $contract_version
 * @property string|null $ee_version
 * @property array<string, mixed>|null $detected_summary
 * @property array<string, mixed>|null $migration_summary
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EditionRuntimeMode extends Model
{
    public const SINGLETON_KEY = 'installation';

    public const COMMUNITY_MODE = 'ce';

    public const COMMERCIAL_MODE = 'ee';

    public const NO_MIGRATION = 'none';

    public const MIGRATION_PENDING = 'pending';

    public const MIGRATION_DRY_RUN = 'dry_run';

    public const MIGRATION_APPLIED = 'applied';

    public const MIGRATION_BLOCKED = 'blocked';

    protected $table = 'edition_runtime_modes';

    protected $primaryKey = 'singleton_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'singleton_key',
        'mode',
        'migration_status',
        'contract_version',
        'ee_version',
        'detected_summary',
        'migration_summary',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'singleton_key' => self::SINGLETON_KEY,
        'mode' => self::COMMUNITY_MODE,
        'migration_status' => self::NO_MIGRATION,
        'contract_version' => EditionCompatibility::CONTRACT_VERSION,
    ];

    protected function casts(): array
    {
        return [
            'detected_summary' => 'array',
            'migration_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function instance(): self
    {
        return static::query()->firstOrCreate([
            'singleton_key' => self::SINGLETON_KEY,
        ]);
    }

    public function isCommunity(): bool
    {
        return $this->mode === self::COMMUNITY_MODE;
    }

    public function isCommercial(): bool
    {
        return $this->mode === self::COMMERCIAL_MODE;
    }
}

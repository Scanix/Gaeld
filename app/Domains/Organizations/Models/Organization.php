<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Invoicing\Models\Client;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'legal_name',
        'address',
        'city',
        'postal_code',
        'canton',
        'country',
        'vat_number',
        'qr_iban',
        'currency',
        'fiscal_year_start',
        'locale',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @deprecated Use customers() instead. Remove after migrating client_id data to customer_id.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}

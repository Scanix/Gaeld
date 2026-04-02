# Gäld Coding Standards

> Conventions derived from the existing codebase. All new code **must** follow these norms.
> When refactoring legacy code, align it to these standards.

---

## 1. Architecture Overview

- **Framework**: Laravel 12, PHP 8.2+
- **Pattern**: Domain-Driven Design (DDD) with a flat domain layer
- **Frontend**: Vue 3 + Inertia.js (server-side routing, client-side rendering)
- **Database**: PostgreSQL 16 — all monetary values use `decimal` columns
- **Code style**: Laravel Pint (`"preset": "laravel"`)
- **Static analysis**: PHPStan level 7 via Larastan

---

## 2. Domain Structure

All business logic lives under `app/Domains/{DomainName}/`. Each domain is a self-contained module with the following directory layout:

```
app/Domains/{Domain}/
├── Actions/           # Single-purpose command classes
├── Controllers/       # HTTP controllers (web or API)
├── DTOs/              # Data Transfer Objects (readonly classes)
├── Enums/             # PHP 8.1 backed enums
├── Exceptions/        # Domain-specific exceptions
├── Jobs/              # Queued jobs
├── Mail/              # Mailable classes
├── Models/            # Eloquent models
├── Notifications/     # Notification classes
├── Policies/          # Authorization policies
├── Queries/           # Query builder classes (static list/filter methods)
├── Requests/          # Form request validation
│   └── Concerns/      # Shared validation rule traits
├── Resources/         # API resources (JSON transformers) — API domain only
├── Rules/             # Custom validation rules (domain-specific)
├── Search/            # Scout search providers
└── Services/          # Complex business logic, orchestration
```

### Naming Rules

| Layer       | Naming Pattern                  | Example                       |
|-------------|--------------------------------|-------------------------------|
| Action      | `{Verb}{Entity}Action`          | `CreateInvoiceAction`         |
| Service     | `{Entity}Service`               | `InvoiceService`              |
| Controller  | `{Entity}Controller`            | `InvoiceController`           |
| DTO         | `{Verb}{Entity}Data`            | `CreateInvoiceData`           |
| Enum        | `{Entity}{Concept}`             | `InvoiceStatus`               |
| Policy      | `{Entity}Policy`                | `InvoicePolicy`               |
| Query       | `{Entity}Query`                 | `InvoiceQuery`                |
| Request     | `{Verb}{Entity}Request`         | `StoreInvoiceRequest`         |
| Exception   | `{Descriptive}Exception`        | `InvalidInvoiceStateException`|
| Job         | `{Verb}{Entity}Job`             | `GenerateRecurringInvoicesJob`|
| Mail        | `{Entity}Mail`                  | `InvoiceMail`                 |

### Where Things Live

- **Domain-specific code** → `app/Domains/{Domain}/`
- **Cross-domain utilities** → `app/Support/` (traits, helpers, base classes)
- **HTTP middleware** → `app/Http/Middleware/`
- **Service providers** → `app/Providers/`
- **Global controllers** → `app/Http/Controllers/` (only `HealthController`, `GlobalSearchController`, base `Controller`)

---

## 3. Models

### Standard Structure

```php
<?php

namespace App\Domains\{Domain}\Models;

use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One-line description of the model's business purpose.
 *
 * @property string $id
 * @property string $organization_id
 * ...
 */
class MyModel extends Model
{
    use Auditable, BelongsToOrganization, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [...];

    protected function casts(): array
    {
        return [...];
    }

    // Relationships
    // Scopes
    // Business methods
    // Scout search (if applicable)
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Traits order** | Alphabetical: `Auditable, BelongsToOrganization, HasFactory, HasUuids, Searchable, SoftDeletes` |
| **Multi-tenancy** | Every organization-scoped model **must** use `BelongsToOrganization` trait |
| **Audit logging** | Every business model **must** use `Auditable` trait |
| **Primary keys** | Use UUIDs (`HasUuids`) for new entities visible to users. Auto-increment is acceptable for internal/pivot tables |
| **Soft deletes** | Use on all major business entities (invoices, expenses, contacts, bank accounts) |
| **Casts** | Use `protected function casts(): array` method (not `$casts` property). Cast enums, dates, decimals, and booleans |
| **Money columns** | Always cast as `'decimal:2'` — use `bcmath` for arithmetic, never float |
| **PHPDoc** | Include a one-line `/** */` class docblock describing purpose, plus `@property` tags for all columns |
| **Relationships** | Use typed return types (`BelongsTo`, `HasMany`, etc.). Define `organization()` first |
| **Scopes** | Named scopes use `scope{Name}(Builder $query, ...)` returning `Builder` |
| **Scout search** | Implement `toSearchableArray()` and `shouldBeSearchable()` when model is searchable |
| **No constants for statuses** | Use enum cases — never `STATUS_*` constants on the model |

---

## 4. DTOs (Data Transfer Objects)

### Create DTOs

```php
<?php

namespace App\Domains\{Domain}\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

readonly class Create{Entity}Data
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $organizationId,
        public string $requiredField,
        public ?string $optionalField = null,
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['organization_id', 'required_field']);

        return new self(
            organizationId: $data['organization_id'],
            requiredField: $data['required_field'],
            optionalField: $data['optional_field'] ?? null,
        );
    }
}
```

### Update DTOs

```php
<?php

namespace App\Domains\{Domain}\DTOs;

use App\Support\OmitsNullValues;

readonly class Update{Entity}Data
{
    use OmitsNullValues;

    public function __construct(
        public string $requiredField,
        public ?string $optionalField = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            requiredField: $data['required_field'],
            optionalField: $data['optional_field'] ?? null,
        );
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Readonly** | All DTOs are `readonly class` |
| **Create DTOs** | Use `MapsToSnakeCase` + `ValidatesFromArray` traits; include `organizationId` |
| **Update DTOs** | Use `OmitsNullValues` trait — null fields are excluded from `toArray()` to prevent overwriting existing values |
| **Properties** | camelCase. The `toArray()` method converts to snake_case |
| **fromArray()** | Static constructor accepting snake_case array (from validated request data). Use `assertRequired()` for mandatory fields |
| **No inheritance of fromArray()** | Child DTOs must override `fromArray()` if they extend a parent — `self` return type requires it |
| **Enum properties** | Use enum types in constructor, convert to `->value` in `toArray()` (handled by traits) |
| **toArray()** | Custom `toArray()` only if the trait's default is insufficient (e.g., nested objects like `AddressData`) |

---

## 5. Actions

Single-purpose command classes that encapsulate one business operation.

```php
<?php

namespace App\Domains\{Domain}\Actions;

class Create{Entity}Action
{
    public function __construct(
        // Inject dependencies via constructor
    ) {}

    public function execute(Create{Entity}Data $data): {Entity}
    {
        // Wrap multi-step operations in DB::transaction()
        return DB::transaction(function () use ($data) {
            // ...
        });
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Method name** | Always `execute()` |
| **Input** | Accept a DTO (or model + DTO for updates) |
| **Output** | Return the created/modified model |
| **Transactions** | Use `DB::transaction()` when creating related records |
| **Dependencies** | Inject via constructor promotion |
| **Naming** | `{Verb}{Entity}Action` — verbs: Create, Update, Delete, Approve, Post, Finalize, Cancel, Duplicate, Send |
| **State validation** | Throw domain exceptions on invalid state transitions (e.g., `InvalidInvoiceStateException`) |

### When to Use Actions vs Services

- **Action**: One focused operation (create, update, delete, approve, finalize)
- **Service**: Complex orchestration involving multiple models, journal entries, or multi-step business workflows (e.g., `InvoiceService::postToLedger()`, `ExpenseService::postToLedger()`)

---

## 6. Services

Services handle complex business logic that spans multiple models or involves orchestration.

```php
<?php

namespace App\Domains\{Domain}\Services;

class {Entity}Service
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function postToLedger({Entity} $entity, ...): Model
    {
        return DB::transaction(function () use (...) {
            // Multi-model coordination, journal entries, status updates
        });
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Naming** | `{Entity}Service` or `{Concept}Service` |
| **Constructor injection** | Use PHP constructor promotion: `private LedgerService $ledgerService` |
| **Transactions** | Always wrap multi-model mutations in `DB::transaction()` |
| **Section separators** | Use `// ──────────── Section Name ──────────── ` comment blocks to group methods |
| **Reporting queries** | Keep simple read-only queries (yearly totals, summaries) in the service alongside write logic |

---

## 7. Controllers

### Web Controllers (Inertia)

```php
<?php

namespace App\Domains\{Domain}\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Response;

class {Entity}Controller extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Entity::class);

        return Inertia::render('{Domain}/Index', [
            'entities' => EntityQuery::list($request),
            'query' => [
                'sort' => $request->input('sort', 'default_sort'),
                'direction' => $request->input('direction', 'desc'),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(StoreRequest $request, CreateAction $action, CurrentOrganization $currentOrg): RedirectResponse
    {
        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $entity = $action->execute(CreateData::fromArray($validated));

        return redirect()->route('entities.show', $entity)
            ->with('success', __('app.entity_created'));
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Authorization** | Call `$this->authorize()` at the beginning of every method |
| **Organization ID** | Set from `CurrentOrganization` service, never from user input |
| **Response type** | `Inertia::render()` for web, `RedirectResponse` for mutations |
| **Flash messages** | Use `->with('success', __('app.key'))` — translations via `__()` helper |
| **Error handling** | Catch domain exceptions and `redirect()->back()->with('error', ...)` |
| **File uploads** | Use `FileUploadService` — never `$request->file()->store()` directly |
| **Action injection** | Inject action classes via method parameters (method injection) |
| **CurrentOrganization** | Inject via method parameter, access via `$currentOrg->id()` |
| **Query params** | Pass `query` array to Inertia for preserving search/filter/sort state |
| **Simple CRUD** | Use `HandlesCrudOperations` trait for straightforward resource controllers |

### HandlesCrudOperations Trait

For simple resource controllers without custom logic, use the `HandlesCrudOperations` trait:

```php
class CustomerController extends Controller
{
    use HandlesCrudOperations;

    protected function modelClass(): string { return Customer::class; }
    protected function createDtoClass(): string { return CreateCustomerData::class; }
    protected function updateDtoClass(): string { return UpdateCustomerData::class; }
    protected function queryClass(): string { return CustomerQuery::class; }
    protected function storeRequestClass(): string { return StoreCustomerRequest::class; }
    protected function inertiaPrefix(): string { return 'Contacts/Customers'; }
    protected function routePrefix(): string { return 'customers'; }
    protected function resourceName(): string { return 'customer'; }
    protected function showRelations(): array { return ['invoices']; }
}
```

---

## 8. Form Requests

```php
<?php

namespace App\Domains\{Domain}\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{Entity}Request extends FormRequest
{
    use {Entity}ValidationRules; // if shared between store/update

    public function authorize(): bool
    {
        return $this->user()->can('create', Entity::class);
    }

    public function rules(): array
    {
        return $this->sharedRules(app(CurrentOrganization::class)->id());
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Naming** | `Store{Entity}Request`, `Update{Entity}Request` |
| **Authorize** | Use policy check via `$this->user()->can()` |
| **Shared rules** | Extract to `Requests/Concerns/{Entity}ValidationRules` trait when store/update share rules |
| **Organization scope** | Get org ID from `CurrentOrganization` service, not from request input |

---

## 9. Queries

Static query builder classes that encapsulate list/filter/search logic.

```php
<?php

namespace App\Domains\{Domain}\Queries;

class {Entity}Query
{
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Entity::query()->with(['relation']), $request)
            ->allowedSorts(['column1', 'column2'], 'default_sort', 'desc')
            ->allowedFilters(['status', 'category'])
            ->searchable(['name', 'relation.field'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function forSelect(): Collection
    {
        // Lightweight query for dropdowns
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Static methods** | All query methods are `public static` |
| **QueryBuilder** | Use `App\Support\QueryBuilder` for list endpoints |
| **Pagination** | Default 20 items, always call `->withQueryString()` |
| **Organization scoping** | Handled automatically by `BelongsToOrganization` global scope |
| **Select queries** | Provide `forSelect()` methods returning minimal fields for dropdowns |

---

## 10. Policies

```php
<?php

namespace App\Domains\{Domain}\Policies;

use App\Support\Policies\BasePolicy;

class {Entity}Policy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::{Domain}View);
    }

    public function view(User $user, Entity $entity): bool
    {
        return $this->belongsToOrganization($user, $entity)
            && $user->hasPermissionTo(Permission::{Domain}View);
    }

    public function update(User $user, Entity $entity): bool
    {
        return $this->belongsToOrganization($user, $entity)
            && $user->hasPermissionTo(Permission::{Domain}Edit)
            && $entity->status->isEditable(); // if applicable
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Base class** | Extend `App\Support\Policies\BasePolicy` |
| **Organization check** | `viewAny`/`create`: use `$this->hasCurrentOrganization()`. All others: use `$this->belongsToOrganization()` |
| **Permissions** | Use `Permission` enum cases via `$user->hasPermissionTo()` (Spatie Permission) |
| **State guards** | Check `$entity->status->isEditable()` / `isDeletable()` for state-dependent actions |
| **Lifecycle methods** | Add domain-specific policy methods (e.g., `finalize`, `recordPayment`, `approve`) |

---

## 11. Enums

```php
<?php

namespace App\Domains\{Domain}\Enums;

/** One-line description of the enum's purpose. */
enum {Entity}Status: string
{
    case Draft = 'draft';
    case Active = 'active';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active],
            self::Active => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('app.status_draft'),
            self::Active => __('app.status_active'),
        };
    }

    public function isEditable(): bool { ... }
    public function isDeletable(): bool { ... }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Backed type** | Always `string` backed |
| **Values** | snake_case string values: `'draft'`, `'pending'`, `'posted'` |
| **Casting** | Cast in model `casts()` method — never store/compare raw strings |
| **State machine** | Implement `canTransitionTo()`, `allowedTransitions()` for status enums |
| **Labels** | Provide `label()` method using translation keys |
| **Guards** | Provide `isEditable()`, `isDeletable()` for policies to check |
| **Doc comment** | One-line `/** */` comment describing the lifecycle |

---

## 12. Exceptions

```php
<?php

namespace App\Domains\{Domain}\Exceptions;

class InvalidEntityStateException extends \RuntimeException {}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Location** | `app/Domains/{Domain}/Exceptions/` |
| **Base class** | Extend `\RuntimeException` for runtime errors, `\InvalidArgumentException` for input errors |
| **Naming** | Descriptive: `Invalid{Entity}StateException`, `Unbalanced{Concept}Exception`, `Already{Action}Exception` |
| **Simple** | Usually empty classes — the message is set at throw site |

---

## 13. Support Layer (`app/Support/`)

Cross-domain utilities that are not part of any specific domain.

| Class/Trait | Purpose |
|------------|---------|
| `MapsToSnakeCase` | Trait: converts camelCase DTO properties to snake_case `toArray()` |
| `OmitsNullValues` | Trait: like `MapsToSnakeCase` but skips null values (for update DTOs) |
| `ValidatesFromArray` | Trait: `assertRequired()` guard for DTO `fromArray()` methods |
| `BelongsToOrganization` | Trait: global scope + auto-fill `organization_id` on models |
| `Auditable` | Trait: Spatie Activity Log integration with org-scoped logging |
| `BasePolicy` | Abstract: `belongsToOrganization()` + `hasCurrentOrganization()` helpers |
| `QueryBuilder` | Fluent query builder with sorting, filtering, search (MeiliSearch or LIKE) |
| `Money` | bcmath wrapper for arbitrary-precision monetary arithmetic |
| `SwissRounding` | 5-centime rounding for CHF amounts |
| `AddressData` | Embeddable address value object with `fromArray()` / `toArray()` |

### Rule: Never put domain-specific logic in `app/Support/`.

---

## 14. Routes

### Web Routes

```
routes/
├── web.php              # Auth, setup, onboarding, and the main authenticated group
└── web/
    ├── accounting.php
    ├── invoicing.php
    ├── expenses.php
    ├── banking.php
    ├── contacts.php
    ├── organizations.php
    ├── users.php
    ├── assets.php
    ├── payroll.php
    ├── migration.php
    └── reporting.php
```

### Rules

| Rule | Convention |
|------|-----------|
| **Split by domain** | Each domain has its own route file under `routes/web/` |
| **Middleware stack** | Authenticated routes use `['auth', 'verified', 'org', 'org-2fa', 'subscription']` |
| **EE features** | Gate behind `middleware('feature:{flag}')` |
| **Rate limiting** | Apply `throttle:N,M` on auth endpoints (login, register, password reset) |
| **CRUD routes** | Use `Route::resource()` where possible; explicit routes for lifecycle actions |
| **API routes** | Prefixed with `/api/v1`, use `auth:sanctum` + `api-org` middleware |
| **Named routes** | Always name routes: `Route::...->name('domain.action')` |

---

## 15. Tests

### Directory Structure

```
tests/
├── TestCase.php
├── Traits/
│   ├── WithAuthenticatedOrganization.php   # User + Org + owner role bootstrap
│   ├── WithOrganizationPermissions.php      # Spatie roles/permissions seeding
│   └── CreatesAccountingFixtures.php        # Journal entry helpers
├── Feature/
│   ├── {Domain}/                            # Mirror app/Domains/ structure
│   │   ├── {Entity}FlowTest.php             # Integration/flow tests
│   │   └── {Entity}ControllerTest.php       # HTTP tests
├── Unit/
│   └── {Domain}/
└── Security/
```

### Test Conventions

```php
<?php

namespace Tests\Feature\{Domain};

use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class {Entity}FlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        // Create required fixtures (accounts, VAT rates, etc.)
    }

    public function test_descriptive_snake_case_name(): void
    {
        // Arrange → Act → Assert
    }
}
```

### Rules

| Rule | Convention |
|------|-----------|
| **Database** | Use `RefreshDatabase` trait for all feature tests |
| **Auth setup** | Use `WithAuthenticatedOrganization` trait, call `$this->setUpOrganization()` in `setUp()` |
| **HTTP tests** | Use `$this->actAsOrg()->get(...)` for authenticated requests |
| **Method naming** | `test_descriptive_snake_case()` — not camelCase, not `@test` annotation |
| **Return type** | Always `: void` on test methods |
| **Assertions** | Use enum cases, not raw strings: `$this->assertEquals(InvoiceStatus::Paid, ...)` |
| **Fixtures** | Create accounting fixtures (accounts, VAT rates) in `setUp()` when needed |
| **Directory** | Mirror the domain structure: `tests/Feature/{Domain}/` |

---

## 16. Factories

### Location

Mirror the domain model namespace under `database/factories/`:

```
database/factories/Domains/{Domain}/Models/{Entity}Factory.php
```

### Rules

| Rule | Convention |
|------|-----------|
| **Namespace** | `Database\Factories\Domains\{Domain}\Models` |
| **Model binding** | Set `protected $model = Entity::class` explicitly |
| **Organization** | Default `organization_id` to `Organization::factory()` |
| **States** | Use named state methods (e.g., `->reduced()`, `->posted()`) |
| **PHPDoc** | Include `@extends Factory<Entity>` |

---

## 17. Money & Arithmetic

| Rule | Convention |
|------|-----------|
| **Storage** | `decimal(12,2)` columns in PostgreSQL |
| **PHP type** | Cast as `'decimal:2'` in models — values are strings |
| **Arithmetic** | Use `bcmath` functions (`bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bccomp`) or `Money` helper |
| **Never float** | Never use `float` for monetary calculations |
| **Swiss rounding** | Apply `SwissRounding::roundToFiveCents()` for CHF amounts in ledger posting |
| **Comparison** | Use `bccomp($a, $b, 2)` — never `==` or `>` on money strings |

---

## 18. Multi-Tenancy

| Rule | Convention |
|------|-----------|
| **Global scope** | `BelongsToOrganization` trait auto-scopes all queries to current org |
| **CurrentOrganization** | Singleton service resolved from `app(CurrentOrganization::class)` |
| **Never trust input** | Organization ID is always set server-side from `CurrentOrganization`, never from request |
| **Policy checks** | Always verify `belongsToOrganization()` before granting access to a specific record |
| **Scout search** | Filter by `organization_id` in MeiliSearch queries |

---

## 19. Feature Flags (CE/EE)

| Rule | Convention |
|------|-----------|
| **Config** | Defined in `config/features.php` |
| **Middleware** | Gate EE routes with `middleware('feature:{flag}')` |
| **Service** | Check programmatically via `FeatureFlag::isEnabled('feature_name')` |
| **CE features** | Enabled by default: `bank_import` |
| **EE features** | Disabled by default: `auto_reconciliation`, `bank_sync`, `saas`, etc. |

---

## 20. Code Style Quick Reference

| Topic | Convention |
|-------|-----------|
| **PHP version** | 8.2+ features allowed (readonly classes, enums, constructor promotion, match, named args) |
| **Code formatter** | Laravel Pint with `laravel` preset |
| **Static analysis** | PHPStan level 7 — no baseline growth |
| **Imports** | Fully qualified, one per line, alphabetically ordered (Pint handles this) |
| **Constructor** | Use PHP constructor promotion for DI |
| **Typed properties** | Always type hint properties and return types |
| **Nullable** | Use `?Type` syntax, not `Type\|null` |
| **Arrays** | Use `array<Key, Value>` in PHPDoc, `[]` in code |
| **Comments** | One-line `/** */` docblock on classes. Section separators in services. Avoid inline comments unless non-obvious |
| **Translations** | Use `__('app.key')` — never hardcode user-facing strings |
| **No global helpers** | Prefer DI and service classes over `app()` calls (except in traits and test setup) |

# Assets Domain

Manages fixed assets, depreciation tracking, and disposal for the organization.

## Models

- **FixedAsset** — Represents a physical or intangible asset with purchase details, useful life, and depreciation method.
- **DepreciationEntry** — Records individual depreciation entries linked to journal entries.

## Services

- **DepreciationCalculator** — Calculates annual and monthly depreciation using either linear or declining-balance methods. Respects salvage value and prevents over-depreciation.

## Depreciation Methods

| Method | Formula |
|---|---|
| Linear | `(purchase_amount - salvage_value) / useful_life_years` |
| Declining Balance | `net_book_value × (2 / useful_life_years)` — never below salvage value |

## Integration

- Each depreciation entry creates a corresponding journal entry in the **Accounting** domain.
- Uses the shared `LedgerService` for double-entry bookkeeping.

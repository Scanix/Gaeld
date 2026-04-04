# Migration Domain

Data import from external accounting systems (Bexio, Banana, CSV) into Gaeld.

## Scope

- **Migration Sessions**: tracked import workflows with status and progress
- **Parsers**: format-specific file readers (Bexio API, Banana CSV, generic CSV)
- **Mappers**: transform external data structures to Gaeld domain models
- **Importers**: bulk-insert accounts, contacts, invoices, and transactions

## Models

- **MigrationSession** — Import session with source system, status, and error log

## Integration

- Creates records across Accounting (accounts, journal entries), Contacts (customers, suppliers), Invoicing (invoices), and Expenses (expenses) domains
- Orchestrated by `MigrationOrchestrator` which coordinates parsers, mappers, and importers
- Registered via `MigrationRegistry` for extensible source system support

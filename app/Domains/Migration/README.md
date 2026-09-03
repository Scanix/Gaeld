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

## Plugin Extensions

Plugins can add a source application without changing core enums or the
migration controller. Declare migration extensions in `plugin.json`:

```json
{
	"extensions": {
		"migration": {
			"parsers": [
				"Plugins\\VendorConnector\\Migration\\VendorParser"
			],
			"connectors": [
				"Plugins\\VendorConnector\\Migration\\VendorConnector"
			],
			"importers": [
				"Plugins\\VendorConnector\\Migration\\VendorContactImporter"
			],
			"mappers": []
		}
	}
}
```

Parser classes implement `PlatformParserInterface`. Core parsers return a
`Platform` enum; plugin parsers return a stable lower-case source key such as
`vendor_app`. They normalize source data into the existing
`ImportRowInterface` DTOs, so core validation and domain services remain the
authority for persistence.

API-backed plugins can implement `MigrationConnectorInterface`. A connector
also supplies parser metadata and is automatically exposed as a migration
source when registered. Its `fetch()` method receives the organization and
target data type and must return a `ParseResult` containing normalized rows.
The plugin owns OAuth/API credentials and must keep them out of the core
contract.

For source-specific persistence, implement
`PluginDataTypeImporterInterface`. It declares a stable importer key and the
source keys it supports. The registry selects that importer only for those
sources and falls back to the core importer for all other sources.

Plugins that need conditional registration can use the typed
`PluginMigrationRegistrar` from their service provider. Manifest registration
is preferred for simple extensions. Extension classes are instantiated by the
Laravel container and must stay inside the plugin namespace.

For connector-backed sources, call `MigrationOrchestrator::fetchFromConnector()`
from a plugin action or route after authorizing the migration session. The
method keeps the organization context explicit and returns the same
`ParseResult` used by file imports, so the plugin can cache rows and continue
through the normal preview and import flow.

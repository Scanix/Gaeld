# Example Plugin

This is an example plugin for the Gäld accounting platform.

## Structure

```
example-plugin/
├── plugin.json              # Plugin manifest
├── src/
│   ├── ExamplePluginServiceProvider.php
│   └── Migration/
│       ├── ExamplePluginParser.php
│       └── ExamplePluginContactImporter.php
├── lang/                    # Parser labels for each supported locale
├── routes/
│   └── web.php
├── migrations/              # Database migrations
└── resources/
    └── views/               # Blade/Vue views
```

## Creating Your Own Plugin

1. Create a new directory under `/plugins`
2. Add a `plugin.json` with your plugin metadata
3. Create a ServiceProvider that extends `Illuminate\Support\ServiceProvider`
4. Implement `PlatformParserInterface` to normalize files from your source
5. Optionally implement `PluginDataTypeImporterInterface` for source-specific
    persistence
6. Declare the classes under `extensions.migration` in `plugin.json`
7. The plugin will be auto-discovered on the next request

## Migration Extension Contract

The example manifest registers `ExamplePluginParser` under the stable source
key `example_app`. The parser emits core `ContactImportRow` objects, while
`ExamplePluginContactImporter` delegates validation and persistence to the CE
`ContactImporter` service. A production connector can replace that delegation
with its own source-specific behavior while keeping organization and import
session boundaries enforced by the CE orchestrator.

An API-backed integration implements `MigrationConnectorInterface` instead of
`PlatformParserInterface`. It exposes the same source metadata and returns a
`ParseResult` from `fetch(Organization $organization, DataType $dataType)`.
Register it under `extensions.migration.connectors`; it becomes a selectable
source automatically. OAuth routes, tokens, and provider-specific settings
remain owned by the plugin.

For programmatic registration, resolve the typed registrar from the Laravel
container in your service provider:

```php
$registrar = $this->app->make(PluginMigrationRegistrar::class);
$registrar->registerParser('vendor-connector', $this->app->make(VendorParser::class));
$registrar->registerConnector('vendor-connector', $this->app->make(VendorConnector::class));
```

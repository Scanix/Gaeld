# Example Plugin

This is an example plugin for the Gäld accounting platform.

## Structure

```
example-plugin/
├── plugin.json              # Plugin manifest
├── src/
│   └── ExamplePluginServiceProvider.php
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
4. The plugin will be auto-discovered on next request

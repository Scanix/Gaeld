<?php

namespace App\Support\Contracts;

/**
 * Contract for plug-in lifecycle hooks (boot, register, migrations, etc.).
 */
interface PluginLifecycle
{
    public function onInstall(): void;

    public function onUninstall(): void;
}

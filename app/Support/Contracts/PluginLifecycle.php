<?php

namespace App\Support\Contracts;

interface PluginLifecycle
{
    public function onInstall(): void;

    public function onUninstall(): void;
}

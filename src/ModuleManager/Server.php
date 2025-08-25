<?php

declare(strict_types=1);

namespace Modularity\ModuleManager;

class Server
{
    private const MODULE_INSTALL_URL = 'https://Modularity.dev/api/command/module/install';
    private const MODULE_REMOVE_URL  = 'https://Modularity.dev/api/command/module/remove';

    public static function getModuleInstallUrl(): string
    {
        return self::MODULE_INSTALL_URL;
    }

    public static function getModuleRemoveUrl(): string
    {
        return self::MODULE_REMOVE_URL;
    }
}

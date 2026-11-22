<?php

namespace Core\Settings;

use Core\Settings\ConfigManagerBackup;
use Utils\Util;

defined('ROOT') or exit('Access denied!');

class ConfigManagerBackupsManager
{
    /**
     * @return ConfigManagerBackup[]
     */
    public static function getAll(): array
    {
        $backups = [];
        $directory = DATA_CORE_SETTINGS;
        if (!is_dir($directory)) {
            return [];
        }
        $files = scandir($directory);
        foreach ($files as $file) {
            if (preg_match('/backup-.*\.zip$/i', $file)) {
                try {
                    $obj = new ConfigManagerBackup($file);
                    // Clé unique pour éviter toute collision sur la même seconde
                    $key = $obj->timestamp . '_' . $file;
                    $backups[$key] = $obj;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        krsort($backups, SORT_NATURAL);
        return $backups;
    }
}

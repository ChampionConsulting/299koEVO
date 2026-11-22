<?php

namespace Core\Settings;

use Core\Storage\Zip;
use Utils\Util;
use Core\Router\Router;
use Core\Auth\UsersManager;
use Core\Storage\CacheManager;

defined('ROOT') or exit('Access denied!');

class ConfigManagerBackup extends Zip
{
    public string $filename;
    public string $basename;
    public string $date;
    public string $type = 'all';
    public int $timestamp = 0;
    public string $url;

    public function __construct($filename)
    {
        $this->basename = basename($filename);
        $this->filename = DATA_CORE_SETTINGS . $this->basename;
        parent::__construct($this->filename);

        // Analyse du nom de fichier : backup-[type-]YYYY-MM-DD-HH-II-SS.zip
        if (preg_match('/backup-(?:([a-zA-Z0-9_-]+)-)?(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})\.zip/i', $this->basename, $matches)) {
            $this->type = !empty($matches[1]) ? $matches[1] : 'all';
            $this->date = "{$matches[2]}-{$matches[3]}-{$matches[4]} {$matches[5]}:{$matches[6]}:{$matches[7]}";
            $this->timestamp = Util::getTimestampFromDate($this->date);
        } elseif (preg_match('/backup-(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})/', $this->basename, $matches)) {
            $this->type = 'all';
            $this->date = "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}";
            $this->timestamp = Util::getTimestampFromDate($this->date);
        } else {
            $this->type = 'custom';
            $this->date = date('Y-m-d H:i:s', filemtime($this->filename) ?: time());
            $this->timestamp = filemtime($this->filename) ?: time();
        }

        $user = UsersManager::getCurrentUser();
        $token = $user ? $user->token : '';
        $this->url = Router::getInstance()->generate('configmanager-dl-backup', [
            'token' => $token,
            'timestamp' => $this->timestamp
        ]);
    }

    public function getTypeLabel(): string
    {
        if (preg_match('/^pre-update-(plugin|theme)-([a-zA-Z0-9_-]+)/', $this->type, $m)) {
            $kind = $m[1] === 'plugin' ? 'Plugin' : 'Thème';
            return "Safety Rollback {$kind} ({$m[2]})";
        }
        if (str_starts_with($this->type, 'pre-update-core')) {
            return 'Safety Rollback Core CMS';
        }
        return match ($this->type) {
            'data' => 'Données uniquement (data/)',
            'code' => 'Code CMS uniquement',
            'pre-update' => 'Safety Rollback (Pré-Mise à jour)',
            default => 'Sauvegarde Complète'
        };
    }

    public function restore(): bool
    {
        $targetDir = ROOT;
        if (preg_match('/^pre-update-(plugin|theme)-([a-zA-Z0-9_-]+)/', $this->type, $m)) {
            $extType = $m[1];
            $extSlug = $m[2];
            $baseDir = ($extType === 'plugin') ? PLUGINS : THEMES;
            
            // Vérifier la structure interne du ZIP
            $zip = new \ZipArchive();
            if ($zip->open($this->filename) === true) {
                $first = $zip->getNameIndex(0);
                $zip->close();
                if ($first !== false && !str_starts_with($first, 'plugin/') && !str_starts_with($first, 'theme/')) {
                    $targetDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $extSlug;
                    if (!is_dir($targetDir)) {
                        @mkdir($targetDir, 0755, true);
                    }
                }
            }
        }

        $extracted = $this->extractTo($targetDir);
        if ($extracted) {
            if (class_exists(CacheManager::class)) {
                $cm = new CacheManager();
                $cm->clearCache();
            }
        }
        return $extracted;
    }

    public function delete(): bool
    {
        return @unlink($this->filename);
    }
}

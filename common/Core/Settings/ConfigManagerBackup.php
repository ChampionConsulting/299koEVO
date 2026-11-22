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
        return match ($this->type) {
            'data' => 'Données uniquement (data/)',
            'code' => 'Code CMS uniquement',
            'pre-update' => 'Safety Rollback (Pré-Mise à jour)',
            default => 'Sauvegarde Complète'
        };
    }

    public function restore(): bool
    {
        $extracted = $this->extractTo(ROOT);
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

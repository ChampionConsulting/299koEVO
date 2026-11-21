<?php

declare(strict_types=1);

namespace Core\Data;

use Core\Core;
use Core\Database;
use Utils\Util;

defined('ROOT') or exit('Access denied!');

/**
 * Moteur d'audit d'intégrité des données et de cohérence multi-stockage (Flat-File JSON & SQL).
 * 
 * Analyse les fichiers de configuration, la cohérence des liens de navigation,
 * les références croisées (catégories/articles, commentaires/articles), et
 * permet la détection et la réparation des données orphelines.
 */
class DataInspector
{
    private static ?DataInspector $instance = null;
    private array $issues = [];
    private array $stats = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Exécute un audit d'intégrité complet du système de données.
     */
    public function audit(): array
    {
        $this->issues = [
            'critical' => [],
            'warning' => [],
            'info' => []
        ];

        $this->stats = [
            'json_files_checked' => 0,
            'json_files_valid' => 0,
            'orphan_menu_items' => 0,
            'orphan_category_refs' => 0,
            'orphan_comments' => 0,
            'sql_integrity' => 'non_configured',
            'scanned_at' => date('Y-m-d H:i:s'),
        ];

        $this->auditJsonFiles();
        $this->auditMenuItems();
        $this->auditBlogIntegrity();
        $this->auditSqlIntegrity();

        $totalIssues = count($this->issues['critical']) + count($this->issues['warning']);
        $score = max(0, 100 - (count($this->issues['critical']) * 25) - (count($this->issues['warning']) * 5));

        return [
            'score' => $score,
            'status' => $score >= 90 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical'),
            'stats' => $this->stats,
            'issues' => $this->issues
        ];
    }

    /**
     * Répare automatiquement les anomalies d'intégrité réparables.
     */
    public function repair(): array
    {
        $repaired = [];

        // 1. Nettoyage des références de catégories orphelines dans le blog
        $cleanedCategories = $this->repairCategoryOrphans();
        if ($cleanedCategories > 0) {
            $repaired[] = "{$cleanedCategories} référence(s) d'articles orphelins retirée(s) des catégories du blog.";
        }

        // 2. Nettoyage des éléments de menu pointant vers des pages inexistantes
        $cleanedMenus = $this->repairMenuOrphans();
        if ($cleanedMenus > 0) {
            $repaired[] = "{$cleanedMenus} lien(s) de menu rompu(s) nettoyé(s).";
        }

        // Vider les caches applicatifs après réparation
        if (class_exists('\Core\Storage\CacheManager')) {
            $cm = new \Core\Storage\CacheManager();
            $cm->clearCache();
        }

        return [
            'success' => true,
            'repaired_count' => count($repaired),
            'actions' => $repaired,
            'new_audit' => $this->audit()
        ];
    }

    /**
     * Contrôle la syntaxe et la lisibilité de tous les fichiers JSON essentiels.
     */
    private function auditJsonFiles(): void
    {
        $coreFiles = [
            'config.json' => DATA . 'config.json',
            'menu_items.json' => defined('DATA_CORE') ? DATA_CORE . 'menu_items.json' : DATA . 'core/menu_items.json',
            'users.json' => defined('DATA_CORE_AUTH') ? DATA_CORE_AUTH . 'users.json' : DATA . 'core/auth/users.json',
        ];

        // Ajouter les fichiers JSON de plugins
        $pluginJsons = glob(DATA_PLUGIN . '*/*.json') ?: [];
        foreach ($pluginJsons as $pf) {
            $coreFiles[basename(dirname($pf)) . '/' . basename($pf)] = $pf;
        }

        foreach ($coreFiles as $relPath => $absPath) {
            if (!file_exists($absPath)) {
                continue;
            }

            $this->stats['json_files_checked']++;

            if (!is_readable($absPath)) {
                $this->issues['critical'][] = "Fichier JSON non lisible (permissions) : {$relPath}";
                continue;
            }

            $content = (string)@file_get_contents($absPath);
            json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->issues['critical'][] = "Erreur de syntaxe JSON dans {$relPath} : " . json_last_error_msg();
            } else {
                $this->stats['json_files_valid']++;
            }

            if (!is_writable($absPath)) {
                $this->issues['warning'][] = "Fichier JSON en lecture seule (non modifiable) : {$relPath}";
            }
        }
    }

    /**
     * Vérifie que les liens de menu pointant vers des pages internes sont valides.
     */
    private function auditMenuItems(): void
    {
        $menuFile = defined('DATA_CORE') ? DATA_CORE . 'menu_items.json' : DATA . 'core/menu_items.json';
        if (!file_exists($menuFile)) {
            return;
        }

        $items = Util::readJsonFile($menuFile, true) ?: [];
        if (!is_array($items)) {
            return;
        }

        $pageSlugs = $this->getExistingPageSlugs();

        foreach ($items as $item) {
            $url = $item['url'] ?? '';
            $name = $item['name'] ?? 'Sans nom';

            if (preg_match('#^/page/([a-zA-Z0-9_-]+)#', $url, $matches)) {
                $targetSlug = $matches[1];
                if (!in_array($targetSlug, $pageSlugs, true)) {
                    $this->stats['orphan_menu_items']++;
                    $this->issues['warning'][] = "Lien de menu rompu : '{$name}' pointe vers la page inexistante '{$targetSlug}'";
                }
            }
        }
    }

    /**
     * Vérifie la cohérence croisée dans le module blog (catégories/articles, commentaires).
     */
    private function auditBlogIntegrity(): void
    {
        $blogFile = DATA_PLUGIN . 'blog/blog.json';
        $catFile = DATA_PLUGIN . 'blog/categories-categories.json';

        if (!file_exists($blogFile)) {
            return;
        }

        $posts = Util::readJsonFile($blogFile, true) ?: [];
        $existingPostIds = [];
        if (is_array($posts)) {
            foreach ($posts as $p) {
                if (isset($p['id'])) {
                    $existingPostIds[] = (int)$p['id'];
                }
            }
        }

        // 1. Audit des catégories
        if (file_exists($catFile)) {
            $cats = Util::readJsonFile($catFile, true) ?: [];
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    $catItems = is_array($cat['items'] ?? null) ? $cat['items'] : [];
                    $orphans = array_diff($catItems, $existingPostIds);
                    if (!empty($orphans)) {
                        $this->stats['orphan_category_refs'] += count($orphans);
                        $this->issues['info'][] = "Catégorie '{$cat['label']}' contient des références à des articles supprimés (IDs: " . implode(', ', $orphans) . ")";
                    }
                }
            }
        }

        // 2. Audit des commentaires
        $commentFiles = glob(DATA_PLUGIN . 'blog/comments_*.json') ?: [];
        foreach ($commentFiles as $cf) {
            if (preg_match('/comments_(\d+)\.json$/', $cf, $matches)) {
                $postId = (int)$matches[1];
                if (!in_array($postId, $existingPostIds, true)) {
                    $this->stats['orphan_comments']++;
                    $this->issues['warning'][] = "Fichier de commentaires orphelin détecté : {$cf} (article ID {$postId} inexistant)";
                }
            }
        }
    }

    /**
     * Vérifie la connectivité et la structure SQL si une base de données est configurée.
     */
    private function auditSqlIntegrity(): void
    {
        $dbConfig = Core::getInstance()->getConfigVal('database') ?? [];
        if (empty($dbConfig['type'])) {
            $this->stats['sql_integrity'] = 'flat_file_only';
            return;
        }

        try {
            $db = Database::getInstance();
            if ($db && $db->getConnection()) {
                $this->stats['sql_integrity'] = 'connected_and_valid';
            } else {
                $this->stats['sql_integrity'] = 'error';
                $this->issues['warning'][] = "Base de données SQL configurée mais connexion impossible.";
            }
        } catch (\Throwable $e) {
            $this->stats['sql_integrity'] = 'error';
            $this->issues['warning'][] = "Erreur SQL : " . $e->getMessage();
        }
    }

    private function getExistingPageSlugs(): array
    {
        $slugs = [];
        $pagesDir = defined('DATA_CORE_CONTENT') ? DATA_CORE_CONTENT : DATA . 'core/content/';
        if (is_dir($pagesDir)) {
            foreach (glob($pagesDir . '*.json') ?: [] as $f) {
                $slugs[] = basename($f, '.json');
            }
        }
        return $slugs;
    }

    private function repairCategoryOrphans(): int
    {
        $blogFile = DATA_PLUGIN . 'blog/blog.json';
        $catFile = DATA_PLUGIN . 'blog/categories-categories.json';

        if (!file_exists($blogFile) || !file_exists($catFile)) {
            return 0;
        }

        $posts = Util::readJsonFile($blogFile, true) ?: [];
        $existingPostIds = array_map(fn($p) => (int)$p['id'], is_array($posts) ? $posts : []);

        $cats = Util::readJsonFile($catFile, true) ?: [];
        if (!is_array($cats)) {
            return 0;
        }

        $totalRemoved = 0;
        foreach ($cats as &$cat) {
            if (isset($cat['items']) && is_array($cat['items'])) {
                $origCount = count($cat['items']);
                $cat['items'] = array_values(array_intersect($cat['items'], $existingPostIds));
                $totalRemoved += ($origCount - count($cat['items']));
            }
        }

        if ($totalRemoved > 0) {
            Util::writeJsonFile($catFile, $cats);
        }

        return $totalRemoved;
    }

    private function repairMenuOrphans(): int
    {
        $menuFile = defined('DATA_CORE') ? DATA_CORE . 'menu_items.json' : DATA . 'core/menu_items.json';
        if (!file_exists($menuFile)) {
            return 0;
        }

        $items = Util::readJsonFile($menuFile, true) ?: [];
        if (!is_array($items)) {
            return 0;
        }

        $pageSlugs = $this->getExistingPageSlugs();
        $cleaned = [];
        $removed = 0;

        foreach ($items as $item) {
            $url = $item['url'] ?? '';
            if (preg_match('#^/page/([a-zA-Z0-9_-]+)#', $url, $matches)) {
                $targetSlug = $matches[1];
                if (!in_array($targetSlug, $pageSlugs, true)) {
                    $removed++;
                    continue; // Supprimer l'élément orphelin
                }
            }
            $cleaned[] = $item;
        }

        if ($removed > 0) {
            Util::writeJsonFile($menuFile, $cleaned);
        }

        return $removed;
    }
}

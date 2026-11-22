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
 * les références croisées (catégories/articles, commentaires/articles), la détection
 * des doublons de clés primaires et la synchronisation miroir JSON vs SQL.
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
            'duplicate_ids_found' => 0,
            'orphan_menu_items' => 0,
            'orphan_category_refs' => 0,
            'orphan_comments' => 0,
            'cross_storage_synced' => true,
            'cross_storage_diffs' => [],
            'sql_integrity' => 'non_configured',
            'scanned_at' => date('Y-m-d H:i:s'),
        ];

        $this->auditJsonFiles();
        $this->auditMenuItems();
        $this->auditBlogIntegrity();
        $this->auditCrossStorageIntegrity();

        $critCount = count($this->issues['critical']);
        $warnCount = count($this->issues['warning']);
        $infoCount = count($this->issues['info']);

        $score = max(0, 100 - ($critCount * 25) - ($warnCount * 5) - ($infoCount * 1));

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

        // 2. Nettoyage des fichiers de commentaires orphelins
        $cleanedComments = $this->repairCommentOrphans();
        if ($cleanedComments > 0) {
            $repaired[] = "{$cleanedComments} fichier(s) de commentaires orphelin(s) supprimé(s).";
        }

        // 3. Nettoyage des éléments de menu pointant vers des pages inexistantes
        $cleanedMenus = $this->repairMenuOrphans();
        if ($cleanedMenus > 0) {
            $repaired[] = "{$cleanedMenus} lien(s) de menu rompu(s) nettoyé(s).";
        }

        // 4. Déduplication des clés primaires si des doublons ont été détectés
        $dedupCount = $this->repairDuplicateIds();
        if ($dedupCount > 0) {
            $repaired[] = "{$dedupCount} enregistrement(s) en doublon d'identifiant réindexé(s).";
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
     * Contrôle la syntaxe et la lisibilité de tous les fichiers JSON essentiels et détecte les doublons d'IDs.
     */
    private function auditJsonFiles(): void
    {
        $coreFiles = [
            'config.json' => DATA . 'config.json',
            'menu_items.json' => defined('DATA_CORE') ? DATA_CORE . 'menu_items.json' : DATA . 'core/menu_items.json',
            'users.json' => defined('DATA_CORE_AUTH') ? DATA_CORE_AUTH . 'users.json' : DATA . 'core/auth/users.json',
            'pages.json' => defined('DATA_CORE_PAGE') ? DATA_CORE_PAGE . 'pages.json' : DATA . 'core/page/pages.json',
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
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->issues['critical'][] = "Erreur de syntaxe JSON dans {$relPath} : " . json_last_error_msg();
                continue;
            }

            $this->stats['json_files_valid']++;

            if (!is_writable($absPath)) {
                $this->issues['warning'][] = "Fichier JSON en lecture seule (non modifiable) : {$relPath}";
            }

            // Vérification des doublons d'IDs dans les listes
            if (is_array($decoded) && !empty($decoded)) {
                $ids = [];
                $hasDuplicates = false;
                foreach ($decoded as $item) {
                    if (is_array($item) && isset($item['id'])) {
                        $id = (string)$item['id'];
                        if (isset($ids[$id])) {
                            $hasDuplicates = true;
                            $this->stats['duplicate_ids_found']++;
                        }
                        $ids[$id] = true;
                    }
                }
                if ($hasDuplicates) {
                    $this->issues['warning'][] = "Doublons d'identifiants (IDs identiques) détectés dans {$relPath}.";
                }
            }
        }
    }

    /**
     * Vérifie la cohérence des liens de navigation vers les pages internes.
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
            $type = $item['type'] ?? 'page';
            // Ne vérifier la cible page QUE pour les liens de type 'page'
            if ($type !== 'page') {
                continue;
            }

            $url = $item['url'] ?? ($item['targetRef'] ?? '');
            $name = $item['name'] ?? ($item['label'] ?? 'Sans nom');

            if (preg_match('#(?:^/page/|^page/|^)([a-zA-Z0-9_\-\.]+)(?:\.html)?$#i', (string)$url, $matches)) {
                $targetSlug = $matches[1];
                if (!empty($targetSlug) && !is_numeric($targetSlug) && !in_array($targetSlug, $pageSlugs, true)) {
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
                        $this->issues['info'][] = "Catégorie '" . ($cat['label'] ?? $cat['name'] ?? 'Inconnue') . "' contient des références à des articles supprimés (IDs: " . implode(', ', $orphans) . ")";
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
                    $this->issues['warning'][] = "Fichier de commentaires orphelin détecté : " . basename($cf) . " (article ID {$postId} supprimé)";
                }
            }
        }
    }

    /**
     * Comparateur d'intégrité croisée entre Flat-file JSON et Base de données SQL (SQLite / MySQL)
     */
    private function auditCrossStorageIntegrity(): void
    {
        $db = Database::getInstance();
        $pdo = null;
        try {
            $pdo = $db->getPdo('default');
            $this->stats['sql_integrity'] = 'connected';
        } catch (\Throwable $e) {
            $this->stats['sql_integrity'] = 'not_connected';
            return;
        }

        // Modèles agnostiques majeurs à vérifier en miroir
        $models = [
            'Pages' => [
                'jsonFile' => defined('DATA_CORE_PAGE') ? DATA_CORE_PAGE . 'pages.json' : DATA . 'core/page/pages.json',
                'table' => 'pages'
            ],
            'Utilisateurs' => [
                'jsonFile' => defined('DATA_CORE_AUTH') ? DATA_CORE_AUTH . 'users.json' : DATA . 'core/auth/users.json',
                'table' => 'users'
            ]
        ];

        foreach ($models as $name => $info) {
            $jsonCount = 0;
            if (file_exists($info['jsonFile'])) {
                $jsonData = Util::readJsonFile($info['jsonFile'], true);
                $jsonCount = is_array($jsonData) ? count($jsonData) : 0;
            }

            // Vérifier si la table SQL existe
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `{$info['table']}`");
                if ($stmt !== false) {
                    $sqlCount = (int)$stmt->fetchColumn();
                    $this->stats['cross_storage_diffs'][$name] = [
                        'json' => $jsonCount,
                        'sql' => $sqlCount,
                        'synced' => ($jsonCount === $sqlCount)
                    ];

                    if ($jsonCount !== $sqlCount) {
                        $this->stats['cross_storage_synced'] = false;
                        $this->issues['warning'][] = "Désynchronisation multi-stockage ({$name}) : {$jsonCount} enregistrement(s) en JSON vs {$sqlCount} en base SQL.";
                    }
                }
            } catch (\Throwable $e) {
                // Table inexistante dans ce schéma SQL, ignorer
                continue;
            }
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
        $pagesFile = defined('DATA_CORE_PAGE') ? DATA_CORE_PAGE . 'pages.json' : DATA . 'core/page/pages.json';
        if (file_exists($pagesFile)) {
            $pages = Util::readJsonFile($pagesFile, true) ?: [];
            if (is_array($pages)) {
                foreach ($pages as $p) {
                    if (!empty($p['id'])) {
                        $slugs[] = (string)$p['id'];
                    }
                    if (!empty($p['slug'])) {
                        $slugs[] = (string)$p['slug'];
                    }
                    if (!empty($p['file'])) {
                        $slugs[] = str_replace('.json', '', (string)$p['file']);
                    }
                }
            }
        }
        return array_unique($slugs);
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

    private function repairCommentOrphans(): int
    {
        $blogFile = DATA_PLUGIN . 'blog/blog.json';
        if (!file_exists($blogFile)) {
            return 0;
        }

        $posts = Util::readJsonFile($blogFile, true) ?: [];
        $existingPostIds = array_map(fn($p) => (int)$p['id'], is_array($posts) ? $posts : []);

        $commentFiles = glob(DATA_PLUGIN . 'blog/comments_*.json') ?: [];
        $deleted = 0;
        foreach ($commentFiles as $cf) {
            if (preg_match('/comments_(\d+)\.json$/', $cf, $matches)) {
                $postId = (int)$matches[1];
                if (!in_array($postId, $existingPostIds, true)) {
                    if (@unlink($cf)) {
                        $deleted++;
                    }
                }
            }
        }
        return $deleted;
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
            $type = $item['type'] ?? 'page';
            if ($type !== 'page') {
                $cleaned[] = $item;
                continue;
            }

            $url = $item['url'] ?? ($item['targetRef'] ?? '');
            if (preg_match('#(?:^/page/|^page/|^)([a-zA-Z0-9_\-\.]+)(?:\.html)?$#i', (string)$url, $matches)) {
                $targetSlug = $matches[1];
                if (!empty($targetSlug) && !is_numeric($targetSlug) && !in_array($targetSlug, $pageSlugs, true)) {
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

    private function repairDuplicateIds(): int
    {
        $filesToAudit = [
            DATA_PLUGIN . 'blog/blog.json',
            defined('DATA_CORE_PAGE') ? DATA_CORE_PAGE . 'pages.json' : DATA . 'core/page/pages.json',
        ];

        $repairedCount = 0;
        foreach ($filesToAudit as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $data = Util::readJsonFile($file, true);
            if (!is_array($data) || empty($data)) {
                continue;
            }

            $seenIds = [];
            $maxId = 0;
            $hasDuplicates = false;

            foreach ($data as $item) {
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $id = (int)$item['id'];
                    $maxId = max($maxId, $id);
                    if (isset($seenIds[$id])) {
                        $hasDuplicates = true;
                    }
                    $seenIds[$id] = true;
                }
            }

            if ($hasDuplicates) {
                $cleaned = [];
                $allocatedIds = [];
                foreach ($data as $item) {
                    if (isset($item['id'])) {
                        $id = (int)$item['id'];
                        if (isset($allocatedIds[$id])) {
                            // Assigner un nouvel ID incrémental
                            $maxId++;
                            $item['id'] = $maxId;
                            $repairedCount++;
                        }
                        $allocatedIds[$item['id']] = true;
                    }
                    $cleaned[] = $item;
                }
                Util::writeJsonFile($file, $cleaned);
            }
        }

        return $repairedCount;
    }
}

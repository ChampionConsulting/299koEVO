<?php

declare(strict_types=1);

namespace Core\Media;

use Core\Core;
use Utils\Util;
use Utils\ImageOptimizer;

defined('ROOT') or exit('Access denied!');

/**
 * Moteur d'audit, détection des fichiers orphelins et optimisation WebP par lot.
 * Composant central du MediaManager 299ko EVO.
 */
class MediaInspector
{
    private static ?MediaInspector $instance = null;
    private string $uploadDir;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->uploadDir = defined('UPLOAD') ? UPLOAD : (defined('ROOT') ? ROOT . 'data/upload/' : '');
    }

    /**
     * Exécute un audit complet du dossier /data/upload/.
     * 
     * @return array Rapport d'audit { stats, orphans, non_webp, used }
     */
    public function audit(): array
    {
        $allFiles = $this->scanUploadFiles();
        $references = $this->collectAllReferences();

        $orphans = [];
        $nonWebp = [];
        $used = [];

        $totalSize = 0;
        $orphanSize = 0;
        $optimizableSavings = 0;

        foreach ($allFiles as $file) {
            $totalSize += $file['size'];
            $relPath = $file['rel_path'];
            $filename = $file['filename'];
            $webPath = 'data/upload/' . $relPath;

            $matchedUsage = null;

            // Recherche par chemin relatif, chemin web ou nom de fichier exact
            if (isset($references[$webPath])) {
                $matchedUsage = $references[$webPath];
            } elseif (isset($references[$relPath])) {
                $matchedUsage = $references[$relPath];
            } elseif (isset($references[$filename])) {
                $matchedUsage = $references[$filename];
            } else {
                // Recherche tolérante si le nom de fichier est contenu dans l'une des URLs référencées
                foreach ($references as $refPath => $info) {
                    if (str_ends_with($refPath, '/' . $filename) || $refPath === $filename) {
                        $matchedUsage = $info;
                        break;
                    }
                }
            }

            if ($matchedUsage !== null) {
                $file['usages'] = is_array($matchedUsage) ? $matchedUsage : [$matchedUsage];
                $used[] = $file;
            } else {
                $orphans[] = $file;
                $orphanSize += $file['size'];
            }

            // Détection des images éligibles à la conversion WebP
            if ($file['is_image'] && $file['ext'] !== 'webp' && $file['ext'] !== 'svg') {
                $estimatedWebpSize = (int) round($file['size'] * 0.45); // Gain moyen estimé ~55%
                $file['estimated_webp_size'] = $estimatedWebpSize;
                $file['estimated_savings'] = max(0, $file['size'] - $estimatedWebpSize);
                $file['estimated_savings_formatted'] = $this->formatBytes($file['estimated_savings']);
                $nonWebp[] = $file;
                $optimizableSavings += $file['estimated_savings'];
            }
        }

        return [
            'stats' => [
                'total_files' => count($allFiles),
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'used_files_count' => count($used),
                'orphan_files_count' => count($orphans),
                'orphan_size' => $orphanSize,
                'orphan_size_formatted' => $this->formatBytes($orphanSize),
                'non_webp_count' => count($nonWebp),
                'estimated_webp_savings' => $optimizableSavings,
                'estimated_webp_savings_formatted' => $this->formatBytes($optimizableSavings),
                'scanned_at' => date('Y-m-d H:i:s'),
            ],
            'orphans' => $orphans,
            'non_webp' => $nonWebp,
            'used' => $used,
        ];
    }

    /**
     * Supprime les fichiers orphelins spécifiés ou la totalité des orphelins.
     * 
     * @param array|null $targetRelPaths Liste de chemins relatifs (si null, tous les orphelins)
     * @return array Résultat de l'opération { deleted_count, freed_bytes, freed_formatted, errors }
     */
    public function cleanOrphans(?array $targetRelPaths = null): array
    {
        $audit = $this->audit();
        $orphanMap = [];
        foreach ($audit['orphans'] as $orphan) {
            $orphanMap[$orphan['rel_path']] = $orphan;
        }

        $pathsToDelete = ($targetRelPaths === null)
            ? array_keys($orphanMap)
            : array_intersect($targetRelPaths, array_keys($orphanMap));

        $deletedCount = 0;
        $freedBytes = 0;
        $errors = [];

        foreach ($pathsToDelete as $relPath) {
            $file = $orphanMap[$relPath];
            $fullPath = $file['full_path'];

            if (!file_exists($fullPath)) {
                continue;
            }

            // Sécurité absolue : interdiction formelle de supprimer en dehors de UPLOAD
            $realPath = realpath($fullPath);
            $realUpload = realpath($this->uploadDir);
            if (!$realPath || !$realUpload || !str_starts_with($realPath, $realUpload)) {
                $errors[] = "Tentative d'accès non autorisé : {$relPath}";
                continue;
            }

            if (@unlink($fullPath)) {
                $deletedCount++;
                $freedBytes += $file['size'];
            } else {
                $errors[] = "Impossible de supprimer : {$relPath}";
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'freed_formatted' => $this->formatBytes($freedBytes),
            'errors' => $errors,
        ];
    }

    /**
     * Convertit un ensemble d'images locales en format WebP optimisé.
     * 
     * @param array|null $targetRelPaths Liste de chemins relatifs (si null, toutes les images éligibles)
     * @param bool $deleteOriginal Supprimer l'image d'origine après conversion réussie
     * @return array Résultat de la conversion { converted_count, bytes_saved, saved_formatted, errors }
     */
    public function convertBulkWebP(?array $targetRelPaths = null, bool $deleteOriginal = false): array
    {
        if (!function_exists('imagewebp')) {
            return [
                'converted_count' => 0,
                'bytes_saved' => 0,
                'saved_formatted' => '0 o',
                'errors' => ["L'extension PHP GD avec support WebP n'est pas installée sur ce serveur."],
            ];
        }

        $audit = $this->audit();
        $nonWebpMap = [];
        foreach ($audit['non_webp'] as $item) {
            $nonWebpMap[$item['rel_path']] = $item;
        }

        $pathsToConvert = ($targetRelPaths === null)
            ? array_keys($nonWebpMap)
            : array_intersect($targetRelPaths, array_keys($nonWebpMap));

        $convertedCount = 0;
        $bytesSaved = 0;
        $errors = [];

        foreach ($pathsToConvert as $relPath) {
            $item = $nonWebpMap[$relPath];
            $sourcePath = $item['full_path'];
            $destPath = preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $sourcePath);

            if ($destPath === $sourcePath) {
                continue;
            }

            $success = ImageOptimizer::optimizeImage($sourcePath, $destPath);
            if ($success && file_exists($destPath)) {
                $convertedCount++;
                $newSize = filesize($destPath);
                $diff = $item['size'] - $newSize;
                if ($diff > 0) {
                    $bytesSaved += $diff;
                }

                if ($deleteOriginal && $sourcePath !== $destPath) {
                    @unlink($sourcePath);
                }
            } else {
                $errors[] = "Échec de conversion WebP pour : {$relPath}";
            }
        }

        return [
            'converted_count' => $convertedCount,
            'bytes_saved' => $bytesSaved,
            'saved_formatted' => $this->formatBytes($bytesSaved),
            'errors' => $errors,
        ];
    }

    /**
     * Scanne récursivement tous les fichiers du dossier /data/upload/.
     */
    private function scanUploadFiles(): array
    {
        if (!is_dir($this->uploadDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->uploadDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico'];

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $filename = $item->getFilename();
            // Ignorer les fichiers de protection système
            if ($filename === '.htaccess' || $filename === 'index.html' || str_starts_with($filename, '.')) {
                continue;
            }

            $fullPath = str_replace('\\', '/', $item->getPathname());
            $relPath = ltrim(substr($fullPath, strlen(str_replace('\\', '/', $this->uploadDir))), '/');
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $size = $item->getSize();

            $files[] = [
                'filename' => $filename,
                'rel_path' => $relPath,
                'full_path' => $fullPath,
                'web_path' => 'data/upload/' . $relPath,
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'mtime' => $item->getMTime(),
                'date_formatted' => date('d/m/Y H:i', $item->getMTime()),
                'ext' => $ext,
                'is_image' => in_array($ext, $imageExtensions, true),
            ];
        }

        return $files;
    }

    /**
     * Collecte toutes les références de médias utilisées dans l'ensemble du CMS.
     */
    private function collectAllReferences(): array
    {
        $references = [];

        // 1. Pages du Core
        $this->collectPageReferences($references);

        // 2. Blog
        $this->collectBlogReferences($references);

        // 3. Galerie
        $this->collectGalerieReferences($references);

        // 4. Petites Annonces
        $this->collectAnnoncesReferences($references);

        // 5. Configuration Générale (Logo, Favicon)
        $this->collectConfigReferences($references);

        // 6. Hook pour extensions personnalisées (ex: POIs, Ecommerce, etc.)
        if (class_exists('\Core\Core')) {
            $references = Core::getInstance()->callHook('media:collect-references', $references) ?? $references;
        }

        return $references;
    }

    private function collectPageReferences(array &$references): void
    {
        $pagesFile = (defined('DATA') ? DATA : '') . 'core/page/pages.json';
        if (!file_exists($pagesFile)) {
            $pagesFile = (defined('ROOT') ? ROOT : '') . 'data/core/page/pages.json';
        }

        if (file_exists($pagesFile)) {
            $pages = Util::readJsonFile($pagesFile);
            if (is_array($pages)) {
                foreach ($pages as $p) {
                    $pageName = $p['name'] ?? 'Page';
                    $pageId = $p['id'] ?? 0;
                    $label = "Page : {$pageName} (#{$pageId})";

                    // Scanner le contenu HTML
                    if (!empty($p['content'])) {
                        $this->extractPathsFromHtml((string) $p['content'], $label, $references);
                    }
                    // Scanner l'image à la une
                    if (!empty($p['featured_image'])) {
                        $this->registerReference((string) $p['featured_image'], $label, $references);
                    }
                }
            }
        }
    }

    private function collectBlogReferences(array &$references): void
    {
        $blogFile = (defined('DATA_PLUGIN') ? DATA_PLUGIN : '') . 'blog/blog.json';
        if (!file_exists($blogFile)) {
            $blogFile = (defined('ROOT') ? ROOT : '') . 'data/plugin/blog/blog.json';
        }

        if (file_exists($blogFile)) {
            $posts = Util::readJsonFile($blogFile);
            if (is_array($posts)) {
                foreach ($posts as $post) {
                    $title = $post['name'] ?? 'Article';
                    $id = $post['id'] ?? 0;
                    $label = "Article Blog : {$title} (#{$id})";

                    if (!empty($post['img'])) {
                        $this->registerReference((string) $post['img'], $label, $references);
                    }
                    if (!empty($post['intro'])) {
                        $this->extractPathsFromHtml((string) $post['intro'], $label, $references);
                    }
                    if (!empty($post['content'])) {
                        $this->extractPathsFromHtml((string) $post['content'], $label, $references);
                    }
                }
            }
        }
    }

    private function collectGalerieReferences(array &$references): void
    {
        $galerieFile = (defined('DATA_PLUGIN') ? DATA_PLUGIN : '') . 'galerie/galerie.json';
        if (!file_exists($galerieFile)) {
            $galerieFile = (defined('ROOT') ? ROOT : '') . 'data/plugin/galerie/galerie.json';
        }

        if (file_exists($galerieFile)) {
            $items = Util::readJsonFile($galerieFile);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $title = $item['name'] ?? 'Photo';
                    $label = "Galerie : {$title}";

                    if (!empty($item['img'])) {
                        $this->registerReference((string) $item['img'], $label, $references);
                    }
                    if (!empty($item['file'])) {
                        $this->registerReference((string) $item['file'], $label, $references);
                    }
                }
            }
        }
    }

    private function collectAnnoncesReferences(array &$references): void
    {
        $annoncesFile = (defined('DATA_PLUGIN') ? DATA_PLUGIN : '') . 'annonces/annonces.json';
        if (!file_exists($annoncesFile)) {
            $annoncesFile = (defined('ROOT') ? ROOT : '') . 'data/plugin/annonces/annonces.json';
        }

        if (file_exists($annoncesFile)) {
            $annonces = Util::readJsonFile($annoncesFile);
            if (is_array($annonces)) {
                foreach ($annonces as $a) {
                    $title = $a['title'] ?? 'Annonce';
                    $label = "Petite Annonce : {$title}";

                    if (!empty($a['images']) && is_array($a['images'])) {
                        foreach ($a['images'] as $img) {
                            $this->registerReference((string) $img, $label, $references);
                        }
                    }
                }
            }
        }
    }

    private function collectConfigReferences(array &$references): void
    {
        $configFile = (defined('DATA') ? DATA : '') . 'config.json';
        if (!file_exists($configFile)) {
            $configFile = (defined('ROOT') ? ROOT : '') . 'data/config.json';
        }

        if (file_exists($configFile)) {
            $config = Util::readJsonFile($configFile);
            if (is_array($config)) {
                if (!empty($config['siteLogo'])) {
                    $this->registerReference((string) $config['siteLogo'], "Logo du site (Configuration)", $references);
                }
                if (!empty($config['siteFavicon'])) {
                    $this->registerReference((string) $config['siteFavicon'], "Favicon du site (Configuration)", $references);
                }
            }
        }
    }

    /**
     * Extrait les chemins de fichiers contenus dans du code HTML (balises <img>, liens, etc.).
     */
    private function extractPathsFromHtml(string $html, string $sourceLabel, array &$references): void
    {
        if (trim($html) === '') {
            return;
        }

        // 1. Attributs src et href contenant "data/upload" ou "upload"
        if (preg_match_all('/(?:src|href)=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $path) {
                if (str_contains($path, 'data/upload/') || str_contains($path, 'upload/')) {
                    $this->registerReference($path, $sourceLabel, $references);
                }
            }
        }
    }

    /**
     * Normalise et enregistre une référence de média.
     */
    private function registerReference(string $rawPath, string $sourceLabel, array &$references): void
    {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            return;
        }

        // Nettoyer les paramètres query string et hash éventuels (?v=123, #top)
        $cleanPath = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;
        $cleanPath = ltrim(str_replace('\\', '/', $cleanPath), '/');

        // Extraire la partie à partir de "data/upload/" si chemin absolu
        if (preg_match('#(?:^|/)data/upload/(.+)$#i', $cleanPath, $m)) {
            $cleanPath = 'data/upload/' . $m[1];
        }

        $filename = basename($cleanPath);

        // Enregistrer la référence sous la clé complète et sous le nom de fichier
        $references[$cleanPath][] = $sourceLabel;
        if ($filename !== '') {
            $references[$filename][] = $sourceLabel;
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * (int) $pow));

        return round($bytes, $precision) . ' ' . $units[(int) $pow];
    }
}

<?php

namespace Core\Extensions;

use Core\Core;
use Core\Plugin\PluginsManager;
use ZipArchive;

defined('ROOT') or exit('Access denied!');

/**
 * PluginInspector - Inspecteur statique de sécurité, de structure et de conformité des extensions 299ko.
 * 
 * Analyse en profondeur les plugins et thèmes (répertoires ou archives ZIP) :
 * - Validité des métadonnées (infos.json, slug, dépendances, compatibilité core)
 * - Analyse statique de sécurité du code PHP (fonctions proscrites, eval, exec, traversal)
 * - Conformité CSRF des formulaires et contrôleurs
 * - Détection des hooks enregistrés et consommés
 * - Calcul d'un score de santé et rapport détaillé
 * 
 * @package 299Ko EVO
 */
class PluginInspector
{
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_INFO     = 'info';

    public const STATUS_PASSED    = 'passed';
    public const STATUS_WARNING   = 'warning';
    public const STATUS_DANGEROUS = 'dangerous';

    /**
     * Fonctions PHP hautement dangereuses proscrites dans les extensions
     */
    protected static array $proscribedFunctions = [
        'exec'          => 'Exécution de commande système arbitraire',
        'shell_exec'    => 'Exécution de commande shell arbitraire',
        'system'        => 'Exécution de commande système directe',
        'passthru'      => 'Passage direct de commande vers la sortie standard',
        'proc_open'     => 'Ouverture de processus système arbitraire',
        'popen'         => 'Ouverture de flux vers un processus externe',
        'pcntl_exec'    => 'Exécution d\'un binaire système via pcntl',
        'eval'          => 'Évaluation dynamique de code PHP arbitraire',
        'create_function' => 'Création dynamique de fonction obsolète et vulnérable',
    ];

    /**
     * Fichiers ou motifs sensibles interdits d'accès direct
     */
    protected static array $forbiddenPatterns = [
        '/\.env\b/i'            => 'Tentative de lecture du fichier d\'environnement .env',
        '/data\/core\/key\.php/i'=> 'Tentative d\'accès à la clé de chiffrement principale key.php',
        '/\.git\b/i'            => 'Tentative d\'accès au dépôt de versioning .git',
    ];

    /**
     * Inspecte un plugin installé par son slug
     */
    public function inspectInstalled(string $slug, string $type = 'plugin'): array
    {
        $baseDir = ($type === 'theme') ? THEMES : PLUGINS;
        $targetDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($targetDir)) {
            return $this->buildFailureReport($slug, "Le dossier de l'extension '{$slug}' n'existe pas dans {$baseDir}.");
        }

        return $this->inspectDirectory($targetDir, $type, $slug);
    }

    /**
     * Inspecte tous les plugins installés et renvoie un rapport synthétique
     */
    public function inspectAllInstalled(string $type = 'plugin'): array
    {
        $baseDir = ($type === 'theme') ? THEMES : PLUGINS;
        $reports = [];
        $summary = [
            'total'     => 0,
            'passed'    => 0,
            'warning'   => 0,
            'dangerous' => 0,
            'average_score' => 0,
        ];

        $totalScore = 0;
        $items = @scandir($baseDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
                continue;
            }
            $itemPath = $baseDir . $item;
            if (is_dir($itemPath)) {
                $report = $this->inspectDirectory($itemPath, $type, $item);
                $reports[$item] = $report;
                $summary['total']++;
                $summary[$report['status']]++;
                $totalScore += $report['score'];
            }
        }

        if ($summary['total'] > 0) {
            $summary['average_score'] = round($totalScore / $summary['total'], 1);
        }

        return [
            'summary' => $summary,
            'reports' => $reports,
        ];
    }

    /**
     * Inspecte une archive ZIP avant son extraction
     */
    public function inspectZip(string $zipPath, string $type = 'plugin'): array
    {
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            return $this->buildFailureReport(basename($zipPath), "Le fichier ZIP est introuvable ou illisible.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return $this->buildFailureReport(basename($zipPath), "Impossible d'ouvrir l'archive ZIP (fichier corrompu).");
        }

        // Création d'un dossier temporaire isolé
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '299ko_inspect_' . bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0755, true);

        try {
            $zip->extractTo($tmpDir);
            $zip->close();

            // Détecter la racine du plugin dans le ZIP (parfois encapsulé dans un sous-dossier)
            $targetDir = $tmpDir;
            $subItems = array_values(array_diff(@scandir($tmpDir) ?: [], ['.', '..']));
            if (count($subItems) === 1 && is_dir($tmpDir . DIRECTORY_SEPARATOR . $subItems[0])) {
                $targetDir = $tmpDir . DIRECTORY_SEPARATOR . $subItems[0];
            }

            $detectedSlug = basename($targetDir);
            $report = $this->inspectDirectory($targetDir, $type, $detectedSlug);
            $report['source'] = 'zip';
            $report['zip_name'] = basename($zipPath);

            return $report;
        } finally {
            $this->recursiveDelete($tmpDir);
        }
    }

    /**
     * Inspecte un dossier d'extension
     */
    public function inspectDirectory(string $dir, string $type = 'plugin', ?string $expectedSlug = null): array
    {
        $dir = rtrim($dir, '/\\');
        $slug = $expectedSlug ?: basename($dir);

        $report = [
            'slug'          => $slug,
            'type'          => $type,
            'path'          => $dir,
            'status'        => self::STATUS_PASSED,
            'score'         => 100,
            'analyzed_at'   => time(),
            'metadata'      => [],
            'files_stats'   => [
                'total_files' => 0,
                'php_files'   => 0,
                'tpl_files'   => 0,
                'css_files'   => 0,
                'js_files'    => 0,
                'total_lines' => 0,
                'size_bytes'  => 0,
                'size_formatted' => '0 B',
            ],
            'issues'        => [
                self::SEVERITY_CRITICAL => [],
                self::SEVERITY_WARNING  => [],
                self::SEVERITY_INFO     => [],
            ],
            'hooks'         => [
                'registered' => [],
                'called'     => [],
            ],
            'security'      => [
                'csrf_compliant' => true,
                'clean_syntax'   => true,
                'safe_functions' => true,
            ],
        ];

        // 1. Analyse des métadonnées (infos.json)
        $this->analyzeMetadata($dir, $slug, $type, $report);

        // 2. Scan récursif des fichiers et du code source
        $this->scanDirectoryFiles($dir, $report);

        // 3. Calcul du score et de l'état global
        $critCount = count($report['issues'][self::SEVERITY_CRITICAL]);
        $warnCount = count($report['issues'][self::SEVERITY_WARNING]);
        $infoCount = count($report['issues'][self::SEVERITY_INFO]);

        $score = 100 - ($critCount * 35) - ($warnCount * 10) - ($infoCount * 1);
        $report['score'] = max(0, min(100, $score));

        if ($critCount > 0) {
            $report['status'] = self::STATUS_DANGEROUS;
        } elseif ($warnCount > 0 || $report['score'] < 80) {
            $report['status'] = self::STATUS_WARNING;
        } else {
            $report['status'] = self::STATUS_PASSED;
        }

        $report['files_stats']['size_formatted'] = $this->formatBytes($report['files_stats']['size_bytes']);

        return $report;
    }

    /**
     * Analyse la structure et infos.json
     */
    protected function analyzeMetadata(string $dir, string $slug, string $type, array &$report): void
    {
        $infosPath = $dir . DIRECTORY_SEPARATOR . 'infos.json';
        if (!file_exists($infosPath)) {
            $infosPath = $dir . DIRECTORY_SEPARATOR . 'param' . DIRECTORY_SEPARATOR . 'infos.json';
        }

        if (!file_exists($infosPath)) {
            $this->addIssue($report, self::SEVERITY_CRITICAL, 'Fichier de métadonnées infos.json manquant.');
            return;
        }

        $content = @file_get_contents($infosPath);
        $json = json_decode($content, true);
        if (!is_array($json)) {
            $this->addIssue($report, self::SEVERITY_CRITICAL, 'Le fichier infos.json contient un JSON invalide ou malformé.');
            return;
        }

        $report['metadata'] = $json;

        // Validation des champs requis
        $requiredFields = ['name', 'version', 'author', 'description'];
        foreach ($requiredFields as $field) {
            if (empty($json[$field])) {
                $this->addIssue($report, self::SEVERITY_WARNING, "Champ obligatoire '{$field}' manquant ou vide dans infos.json.");
            }
        }

        // Vérification de la correspondance du slug
        if (!empty($json['name']) && strtolower(trim($json['name'])) !== strtolower($slug)) {
            $this->addIssue($report, self::SEVERITY_WARNING, "Divergence de nom : le dossier s'appelle '{$slug}' alors que infos.json déclare '{$json['name']}'.");
        }

        // Vérification de la version (format semver)
        if (!empty($json['version'])) {
            if (!preg_match('/^\d+\.\d+(\.\d+)?([a-zA-Z0-9_\-\.]+)?$/', (string)$json['version'])) {
                $this->addIssue($report, self::SEVERITY_WARNING, "Le format de version '{$json['version']}' ne respecte pas le standard sémantique.");
            }
        }

        // Compatibilité Core
        $coreVersion = defined('VERSION') ? VERSION : '0.3.5';
        if (!empty($json['minCoreVersion'])) {
            if (version_compare($coreVersion, $json['minCoreVersion'], '<')) {
                $this->addIssue($report, self::SEVERITY_WARNING, "Cette extension requiert le Core v{$json['minCoreVersion']} minimum (actuel : v{$coreVersion}).");
            }
        }
        if (!empty($json['maxCoreVersion'])) {
            if (version_compare($coreVersion, $json['maxCoreVersion'], '>')) {
                $this->addIssue($report, self::SEVERITY_WARNING, "Cette extension déclare une compatibilité maximale jusqu'au Core v{$json['maxCoreVersion']} (actuel : v{$coreVersion}).");
            }
        }

        // Vérification de la classe principale (pour plugins)
        if ($type === 'plugin') {
            $mainClassFile = $dir . DIRECTORY_SEPARATOR . $slug . '.php';
            if (!file_exists($mainClassFile)) {
                // Recherche insensible à la casse
                $foundClass = false;
                foreach (scandir($dir) ?: [] as $file) {
                    if (strcasecmp($file, $slug . '.php') === 0) {
                        $foundClass = true;
                        if ($file !== $slug . '.php') {
                            $this->addIssue($report, self::SEVERITY_WARNING, "Casse incohérente : le fichier principal s'appelle '{$file}' au lieu de '{$slug}.php'.");
                        }
                        break;
                    }
                }
                if (!$foundClass) {
                    $this->addIssue($report, self::SEVERITY_CRITICAL, "Fichier PHP principal du plugin '{$slug}.php' introuvable.");
                }
            }
        }
    }

    /**
     * Parcours et analyse tous les fichiers du module
     */
    protected function scanDirectoryFiles(string $dir, array &$report): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $hasForm = false;
        $hasCsrfToken = false;

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $relativePath = ltrim(substr($filePath, strlen($dir)), '/\\');
            $ext = strtolower($fileInfo->getExtension());
            $size = $fileInfo->getSize();

            $report['files_stats']['total_files']++;
            $report['files_stats']['size_bytes'] += $size;

            if ($ext === 'php') {
                $report['files_stats']['php_files']++;
                $this->analyzePhpFile($filePath, $relativePath, $report);
            } elseif ($ext === 'tpl' || $ext === 'html') {
                $report['files_stats']['tpl_files']++;
                $this->analyzeTemplateFile($filePath, $relativePath, $report, $hasForm, $hasCsrfToken);
            } elseif ($ext === 'css') {
                $report['files_stats']['css_files']++;
            } elseif ($ext === 'js') {
                $report['files_stats']['js_files']++;
            }

            // Compter les lignes de code pour les fichiers texte
            if (in_array($ext, ['php', 'tpl', 'html', 'css', 'js', 'json', 'sql', 'md'], true) && $size < 2000000) {
                $lines = @file($filePath);
                if (is_array($lines)) {
                    $report['files_stats']['total_lines'] += count($lines);
                }
            }
        }

        // Vérification de cohérence CSRF globale
        if ($hasForm && !$hasCsrfToken) {
            $report['security']['csrf_compliant'] = false;
            $this->addIssue($report, self::SEVERITY_WARNING, "Des formulaires POST ont été détectés sans trace apparente de protection CSRF (tokenField manquant).");
        }
    }

    /**
     * Analyse statique approfondie d'un fichier PHP
     */
    protected function analyzePhpFile(string $filePath, string $relativePath, array &$report): void
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        // 1. Validation de la syntaxe PHP via token_get_all
        try {
            $tokens = @token_get_all($content, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $report['security']['clean_syntax'] = false;
            $this->addIssue($report, self::SEVERITY_CRITICAL, "Erreur de syntaxe PHP dans {$relativePath} à la ligne {$e->getLine()} : {$e->getMessage()}");
            return;
        }

        // 2. Scan AST / Tokens pour fonctions proscrites
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            // Détection de 'eval' (qui est T_EVAL)
            if (is_array($token) && $token[0] === T_EVAL) {
                $line = $token[2];
                $report['security']['safe_functions'] = false;
                $this->addIssue($report, self::SEVERITY_CRITICAL, "Fonction critique interdite 'eval()' détectée dans {$relativePath} à la ligne {$line}.");
            }

            // Détection des appels de fonction T_STRING
            if (is_array($token) && $token[0] === T_STRING) {
                $funcName = strtolower($token[1]);
                $line = $token[2];

                // Vérifier si le token précédent n'est pas -> ou :: ou function
                $prevIndex = $this->getPreviousMeaningfulTokenIndex($tokens, $i);
                $isMethodCallOrDef = false;
                if ($prevIndex !== null && is_array($tokens[$prevIndex])) {
                    $prevType = $tokens[$prevIndex][0];
                    if ($prevType === T_OBJECT_OPERATOR || $prevType === T_NULLSAFE_OBJECT_OPERATOR || $prevType === T_PAAMAYIM_NEKUDOTAYIM || $prevType === T_FUNCTION) {
                        $isMethodCallOrDef = true;
                    }
                }

                // Vérifier si le token suivant est une parenthèse ouvrante '('
                $nextIndex = $this->getNextMeaningfulTokenIndex($tokens, $i);
                $isCall = ($nextIndex !== null && $tokens[$nextIndex] === '(');

                if (!$isMethodCallOrDef && $isCall) {
                    if (isset(self::$proscribedFunctions[$funcName])) {
                        $desc = self::$proscribedFunctions[$funcName];
                        $report['security']['safe_functions'] = false;
                        $this->addIssue($report, self::SEVERITY_CRITICAL, "Fonction proscrite '{$funcName}()' détectée dans {$relativePath} à la ligne {$line} ({$desc}).");
                    }

                    // Détection des hooks enregistrés : $core->addHook(...) ou addHook(...)
                    if ($funcName === 'addhook') {
                        $hookName = $this->extractFirstStringArgument($tokens, $nextIndex);
                        if ($hookName !== null) {
                            $report['hooks']['registered'][] = $hookName;
                        }
                    }

                    // Détection des hooks appelés : $core->callHook(...) ou callHook(...)
                    if ($funcName === 'callhook') {
                        $hookName = $this->extractFirstStringArgument($tokens, $nextIndex);
                        if ($hookName !== null) {
                            $report['hooks']['called'][] = $hookName;
                        }
                    }
                }
            }
        }

        // 3. Détection de motifs suspects (Regex)
        foreach (self::$forbiddenPatterns as $pattern => $msg) {
            if (preg_match($pattern, $content)) {
                $this->addIssue($report, self::SEVERITY_WARNING, "{$msg} dans {$relativePath}.");
            }
        }

        // Traversée de répertoires suspecte dans des includes
        if (preg_match('/(?:include|require)(?:_once)?\s*[\(\s][^\;]*\.\.[\/\\\\]/i', $content)) {
            $this->addIssue($report, self::SEVERITY_WARNING, "Utilisation suspecte de '../' dans une directive include/require dans {$relativePath}.");
        }
    }

    /**
     * Analyse statique d'un fichier Template (.tpl)
     */
    protected function analyzeTemplateFile(string $filePath, string $relativePath, array &$report, bool &$hasForm, bool &$hasCsrfToken): void
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        // Détection de formulaire POST
        if (preg_match('/<form\b[^>]*\bmethod=[\'"]post[\'"]/i', $content)) {
            $hasForm = true;
            if (str_contains($content, 'tokenField') || str_contains($content, 'csrf_token') || str_contains($content, 'token')) {
                $hasCsrfToken = true;
            } else {
                $this->addIssue($report, self::SEVERITY_WARNING, "Formulaire POST sans tokenField visible dans le template {$relativePath}.");
            }
        }

        // Détection des hooks insérés dans les templates : {% HOOK.xxx %}
        if (preg_match_all('/\{%\s*HOOK\.([a-zA-Z0-9_\-:]+)/i', $content, $matches)) {
            foreach ($matches[1] as $hook) {
                $report['hooks']['called'][] = $hook;
            }
        }
    }

    /**
     * Extrait le premier argument chaîne d'un appel de fonction
     */
    protected function extractFirstStringArgument(array $tokens, int $openParenIndex): ?string
    {
        $count = count($tokens);
        for ($i = $openParenIndex + 1; $i < $count; $i++) {
            $tok = $tokens[$i];
            if ($tok === ')' || $tok === ';') {
                break;
            }
            if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
                return trim($tok[1], "'\"");
            }
        }
        return null;
    }

    protected function getPreviousMeaningfulTokenIndex(array $tokens, int $currentIndex): ?int
    {
        for ($i = $currentIndex - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_WHITESPACE || $tokens[$i][0] === T_COMMENT || $tokens[$i][0] === T_DOC_COMMENT) {
                    continue;
                }
            }
            return $i;
        }
        return null;
    }

    protected function getNextMeaningfulTokenIndex(array $tokens, int $currentIndex): ?int
    {
        $count = count($tokens);
        for ($i = $currentIndex + 1; $i < $count; $i++) {
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_WHITESPACE || $tokens[$i][0] === T_COMMENT || $tokens[$i][0] === T_DOC_COMMENT) {
                    continue;
                }
            }
            return $i;
        }
        return null;
    }

    protected function addIssue(array &$report, string $severity, string $message): void
    {
        $report['issues'][$severity][] = $message;
    }

    protected function buildFailureReport(string $slug, string $error): array
    {
        return [
            'slug'        => $slug,
            'type'        => 'unknown',
            'status'      => self::STATUS_DANGEROUS,
            'score'       => 0,
            'analyzed_at' => time(),
            'error'       => $error,
            'issues'      => [
                self::SEVERITY_CRITICAL => [$error],
                self::SEVERITY_WARNING  => [],
                self::SEVERITY_INFO     => [],
            ],
            'metadata'    => [],
            'files_stats' => ['total_files' => 0, 'size_formatted' => '0 B'],
            'hooks'       => ['registered' => [], 'called' => []],
            'security'    => ['csrf_compliant' => false, 'clean_syntax' => false, 'safe_functions' => false],
        ];
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    protected function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveDelete($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}

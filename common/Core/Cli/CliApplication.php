<?php

namespace Core\Cli;

use Core\Api\ApiManager;
use Core\Blocks\BlockManager;
use Core\Content\ContentTypeManager;
use Core\Core;
use Core\Database;
use Core\Plugin\PluginsManager;
use Core\Router\Router;

defined('ROOT') or exit('Access denied!');

/**
 * Application CLI légère et extensible pour 299koEVO (zéro dépendance externe).
 */
class CliApplication
{
    /** @var array<string,array{desc:string,handler:callable}> */
    private array $commands = [];

    public function __construct()
    {
        $this->registerCoreCommands();
    }

    public function registerCommand(string $name, string $description, callable $handler): void
    {
        $this->commands[$name] = [
            'desc' => $description,
            'handler' => $handler,
        ];
    }

    public function getCommands(): array
    {
        return array_keys($this->commands);
    }

    /**
     * Point d'entrée CLI (retourne un code de sortie POSIX).
     */
    public function run(array $argv): int
    {
        if (PHP_SAPI !== 'cli') {
            return 1;
        }

        $args = array_slice($argv, 1);
        $command = 'help';
        $flags = [];
        $positionals = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $eq = strpos($arg, '=');
                if ($eq !== false) {
                    $flags[substr($arg, 2, $eq - 2)] = substr($arg, $eq + 1);
                } else {
                    $flags[substr($arg, 2)] = true;
                }
            } elseif ($command === 'help' && empty($positionals) && !str_contains($arg, '=')) {
                $command = $arg;
            } else {
                $positionals[] = $arg;
            }
        }

        if (!isset($this->commands[$command])) {
            fwrite(STDERR, "Commande inconnue : {$command}\n");
            $this->printHelp();
            return 1;
        }

        try {
            return (int) call_user_func($this->commands[$command]['handler'], $flags, $positionals);
        } catch (\Throwable $e) {
            fwrite(STDERR, "Erreur CLI [{$command}] : " . $e->getMessage() . "\n");
            return 1;
        }
    }

    private function registerCoreCommands(): void
    {
        $this->registerCommand('help', 'Affiche la liste des commandes CLI disponibles', function (): int {
            $this->printHelp();
            return 0;
        });

        $this->registerCommand('system:info', 'Affiche la version CMS, PHP, stockage et statistiques système', function (array $flags): int {
            $core = Core::getInstance();
            $db = Database::getInstance();
            $cfg = $db->getFullConfig();

            $info = [
                'cms_version' => defined('VERSION') ? VERSION : 'unknown',
                'php_version' => PHP_VERSION,
                'root' => ROOT,
                'data_dir' => DATA,
                'theme' => (string) ($core->getConfigVal('theme') ?: 'default'),
                'site_lang' => (string) ($core->getConfigVal('siteLang') ?: 'fr'),
                'storage_default' => (string) ($cfg['storage']['default'] ?? 'json'),
                'db_driver' => (string) ($cfg['database']['driver'] ?? 'none'),
                'content_types_count' => count(ContentTypeManager::getInstance()->all()),
                'blocks_count' => count(BlockManager::getInstance()->all()),
                'api_routes_count' => count(ApiManager::getInstance()->getRoutes()),
            ];

            if (!empty($flags['json'])) {
                echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                return 0;
            }

            echo "=== 299koEVO System Info ===\n";
            foreach ($info as $k => $v) {
                echo sprintf("  %-22s : %s\n", $k, (string) $v);
            }
            return 0;
        });

        $this->registerCommand('plugin:list', 'Liste les plugins présents et leur état d\'activation', function (array $flags): int {
            $pm = PluginsManager::getInstance();
            $rows = [];
            foreach ($pm->getPlugins() as $plugin) {
                $rows[] = [
                    'name' => $plugin->getName(),
                    'version' => (string) ($plugin->getInfoVal('version') ?: '-'),
                    'active' => (bool) $plugin->getConfigVal('activate'),
                ];
            }
            usort($rows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

            if (!empty($flags['json'])) {
                echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                return 0;
            }

            echo sprintf("=== Plugins détectés (%d) ===\n", count($rows));
            foreach ($rows as $row) {
                echo sprintf("  [%-7s] %-22s v%s\n", $row['active'] ? 'ACTIF' : 'INACTIF', $row['name'], $row['version']);
            }
            return 0;
        });

        $this->registerCommand('storage:check', 'Vérifie la configuration et connectivité JSON/SQLite/MySQL', function (array $flags): int {
            $db = Database::getInstance();
            $cfg = $db->getFullConfig();
            $driver = (string) ($cfg['database']['driver'] ?? 'json');

            $report = [
                'data_dir_writable' => is_writable(DATA),
                'storage_default' => (string) ($cfg['storage']['default'] ?? 'json'),
                'database_driver' => $driver,
                'pdo_ok' => false,
                'details' => '',
            ];

            if ($driver === 'sqlite' || $driver === 'mysql' || $driver === 'pgsql') {
                try {
                    $pdo = $db->getPdo();
                    $pdo->query('SELECT 1');
                    $report['pdo_ok'] = true;
                    $report['details'] = "Connexion PDO {$driver} opérationnelle.";
                } catch (\Throwable $e) {
                    $report['details'] = "Erreur PDO {$driver} : " . $e->getMessage();
                }
            } else {
                $report['pdo_ok'] = true;
                $report['details'] = 'Stockage JSON natif actif.';
            }

            if (!empty($flags['json'])) {
                echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "=== Storage Check ===\n";
                echo "  Data writable : " . ($report['data_dir_writable'] ? 'OUI' : 'NON') . "\n";
                echo "  Default mode  : " . $report['storage_default'] . "\n";
                echo "  DB driver     : " . $report['database_driver'] . "\n";
                echo "  Status        : " . $report['details'] . "\n";
            }

            return $report['data_dir_writable'] && $report['pdo_ok'] ? 0 : 1;
        });

        $this->registerCommand('cache:clear', 'Vide le cache applicatif dans CACHE (--dry-run pour simuler)', function (array $flags): int {
            $cacheDir = rtrim(CACHE, DIRECTORY_SEPARATOR);
            $dryRun = !empty($flags['dry-run']);
            $removed = 0;

            if (is_dir($cacheDir)) {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $entry) {
                    $path = $entry->getRealPath();
                    if ($path === false || str_contains($path, DIRECTORY_SEPARATOR . 'sessions')) {
                        continue;
                    }
                    if ($entry->isFile()) {
                        if ($dryRun || @unlink($path)) {
                            $removed++;
                        }
                    }
                }
            }

            echo ($dryRun ? '[DRY-RUN] ' : '') . "Cache nettoyé : {$removed} fichier(s) ciblé(s) dans {$cacheDir}.\n";
            return 0;
        });

        $this->registerCommand('routes:list', 'Liste les routes REST enregistrées et les routes HTTP cœur', function (array $flags): int {
            $apiRoutes = array_map(static fn($r): array => $r->toDocumentationArray(), ApiManager::getInstance()->getRoutes());
            $httpRoutes = Router::getInstance()->getRoutes();

            if (!empty($flags['json'])) {
                echo json_encode(['api_routes' => $apiRoutes, 'http_routes_count' => count($httpRoutes)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                return 0;
            }

            echo sprintf("=== Routes API REST (%d) ===\n", count($apiRoutes));
            foreach ($apiRoutes as $r) {
                $perm = is_array($r['permission']) ? json_encode($r['permission']) : $r['permission'];
                echo sprintf("  %-7s %-36s [%s]\n", $r['method'], $r['pattern'], $perm);
            }
            echo sprintf("=== Routes HTTP AltoRouter enregistrées : %d ===\n", count($httpRoutes));
            return 0;
        });

        $this->registerCommand('test:run', 'Exécute la suite de tests automatisés du projet', function (): int {
            $runner = ROOT . 'tests' . DIRECTORY_SEPARATOR . 'run.php';
            if (!is_file($runner)) {
                fwrite(STDERR, "Suite de tests introuvable : {$runner}\n");
                return 1;
            }
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner);
            passthru($cmd, $exitCode);
            return (int) $exitCode;
        });

        $this->registerCommand('task:run', 'Exécute les tâches en attente de la file d\'ordonnancement (TaskScheduler)', function (array $flags): int {
            $limit = isset($flags['limit']) ? (int) $flags['limit'] : 10;
            $duration = isset($flags['duration']) ? (int) $flags['duration'] : 30;
            $scheduler = \Core\Tasks\TaskScheduler::getInstance();
            $result = $scheduler->runPending($limit, $duration);

            if (isset($flags['json'])) {
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                return 0;
            }

            echo sprintf("=== TaskScheduler : Exécution terminée ===\n");
            echo sprintf("  Tâches traitées : %d (Succès : %d, Échecs : %d)\n", $result['processed'], $result['success'], $result['failed']);
            if (!empty($result['tasks'])) {
                foreach ($result['tasks'] as $t) {
                    $st = $t['status'] === 'completed' ? 'OK' : 'FAIL';
                    echo sprintf("  - [%s] %s (%s) attempts: %d %s\n", $st, $t['id'], $t['handler'], $t['attempts'], $t['error'] ? 'Err: ' . $t['error'] : '');
                }
            }
            return $result['failed'] > 0 ? 2 : 0;
        });

        $this->registerCommand('task:list', 'Affiche l\'état de la file d\'attente et des statistiques TaskScheduler', function (array $flags): int {
            $scheduler = \Core\Tasks\TaskScheduler::getInstance();
            $stats = $scheduler->getStats();
            $tasks = $scheduler->listTasks($flags['status'] ?? null);

            if (isset($flags['json'])) {
                echo json_encode(['stats' => $stats, 'tasks' => $tasks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                return 0;
            }

            echo "=== TaskScheduler : État de la file d'attente ===\n";
            echo sprintf("  En attente : %d | En cours : %d | En échec : %d | Historique : %d\n", $stats['pending'], $stats['processing'], $stats['failed'], $stats['history_count']);
            echo sprintf("  Dernière exécution : %s\n\n", $stats['last_run'] ? date('Y-m-d H:i:s', $stats['last_run']) : 'Aucune');

            if (empty($tasks)) {
                echo "  Aucune tâche dans la file active.\n";
            } else {
                echo sprintf("  %-20s %-28s %-12s %-10s %s\n", 'ID', 'HANDLER', 'STATUT', 'PRIORITÉ', 'EXÉCUTION');
                foreach ($tasks as $t) {
                    $runDate = date('Y-m-d H:i:s', $t['run_at']);
                    echo sprintf("  %-20s %-28s %-12s %-10d %s\n", $t['id'], substr($t['handler'], 0, 26), $t['status'], $t['priority'], $runDate);
                }
            }
            return 0;
        });

        $this->registerCommand('data:inspect', 'Contrôle l\'intégrité des données JSON et des références croisées', function (array $flags): int {
            $inspector = \Core\Data\DataInspector::getInstance();
            $report = $inspector->audit();

            if (isset($flags['json'])) {
                echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                return $report['score'] < 70 ? 1 : 0;
            }

            echo sprintf("=== DataInspector : Rapport d'intégrité (Score : %d/100) ===\n", $report['score']);
            echo sprintf("  Statut général : %s\n", strtoupper($report['status']));
            echo sprintf("  Fichiers JSON vérifiés : %d (Valides : %d)\n", $report['stats']['json_files_checked'], $report['stats']['json_files_valid']);
            echo sprintf("  Liens de menu orphelins : %d\n", $report['stats']['orphan_menu_items']);
            echo sprintf("  Références catégories orphelines : %d\n", $report['stats']['orphan_category_refs']);
            echo sprintf("  Commentaires orphelins : %d\n", $report['stats']['orphan_comments']);
            echo sprintf("  Cohérence SQL : %s\n\n", $report['stats']['sql_integrity']);

            if (!empty($report['issues']['critical'])) {
                echo "  [CRITIQUE]\n";
                foreach ($report['issues']['critical'] as $iss) {
                    echo "   - {$iss}\n";
                }
            }
            if (!empty($report['issues']['warning'])) {
                echo "  [AVERTISSEMENT]\n";
                foreach ($report['issues']['warning'] as $iss) {
                    echo "   - {$iss}\n";
                }
            }
            if (!empty($report['issues']['info'])) {
                echo "  [INFO]\n";
                foreach ($report['issues']['info'] as $iss) {
                    echo "   - {$iss}\n";
                }
            }
            if (empty($report['issues']['critical']) && empty($report['issues']['warning'])) {
                echo "  ✓ Aucune anomalie critique détectée. Système sain.\n";
            }
            return 0;
        });

        $this->registerCommand('data:repair', 'Répare automatiquement les données orphelines et liens rompus', function (array $flags): int {
            $inspector = \Core\Data\DataInspector::getInstance();
            $result = $inspector->repair();

            if (isset($flags['json'])) {
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                return 0;
            }

            echo "=== DataInspector : Opération de réparation ===\n";
            echo sprintf("  Actions effectuées : %d\n", $result['repaired_count']);
            foreach ($result['actions'] as $act) {
                echo "   ✓ {$act}\n";
            }
            echo sprintf("  Nouveau score d'intégrité : %d/100 (Statut : %s)\n", $result['new_audit']['score'], $result['new_audit']['status']);
            return 0;
        });
    }

    private function printHelp(): void
    {
        echo "299koEVO CLI — Utilisation : php cli.php <commande> [--json] [--data-dir=/chemin]\n\n";
        echo "Commandes disponibles :\n";
        foreach ($this->commands as $name => $meta) {
            echo sprintf("  %-18s %s\n", $name, $meta['desc']);
        }
    }
}

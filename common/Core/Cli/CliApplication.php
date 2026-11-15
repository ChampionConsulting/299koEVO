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

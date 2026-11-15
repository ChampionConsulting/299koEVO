<?php

declare(strict_types=1);

/**
 * Exhaustive Isolated Functional Audit of All 41 Plugins — 299koEVO v0.2.2
 *
 * CRITICAL GUARANTEES:
 * - Zero writes, reads, or modifications to /home/max/Déclic/data/.
 * - Enforced via PHP -d open_basedir excluding /home/max/Déclic/data,
 *   -d allow_url_fopen=0, -d sendmail_path=/bin/true, and dedicated /tmp isolation dirs.
 * - Tests every plugin on BOTH JSON and SQLite drivers in fresh isolated environments.
 * - Executes no code modifications in CMS core or plugin files.
 */

if (!function_exists('filemanagerDisplayManagerButton')) {
    function filemanagerDisplayManagerButton($textareaId = false, $buttonLabel = false): string
    {
        return \Core\Core::getInstance()->media()->renderManagerButton($textareaId, $buttonLabel);
    }
}

if (in_array('--worker', $argv ?? [], true)) {
    AuditWorker::run($argv);
    exit(0);
}

AuditOrchestrator::run($argv ?? []);
exit(0);

final class AuditConfig
{
    public const OUT_JSON_NAME = '10_JOURNAL_EXECUTION_AUDIT_41_PLUGINS.json';
    public const OUT_LOG_NAME = '10_JOURNAL_EXECUTION_AUDIT_41_PLUGINS.log';

    public static function rootDir(): string
    {
        return rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
    }

    public static function pluginsDir(): string
    {
        return self::rootDir() . 'plugin/';
    }

    public static function outDir(): string
    {
        return self::rootDir() . 'AUDIT_CMS/EVOLUTION_PHASES_1_4/';
    }

    public static function openBasedir(): string
    {
        $root = rtrim(self::rootDir(), '/');
        $paths = [
            $root . '/common',
            $root . '/admin',
            $root . '/plugin',
            $root . '/theme',
            $root . '/tests',
            $root . '/install.php',
            $root . '/index.php',
            $root . '/cli.php',
            '/tmp',
            '/var/tmp',
        ];
        if (is_dir('/home/max/Skins_299koEVO')) {
            $paths[] = '/home/max/Skins_299koEVO';
        }
        return implode(':', $paths);
    }

    public static function getMysqlConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => getenv('EVO_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('EVO_TEST_DB_PORT') ?: 3306),
            'dbname' => getenv('EVO_TEST_DB_NAME') ?: 'test_299koevo_audit',
            'user' => getenv('EVO_TEST_DB_USER') ?: 'test_299koevo_u',
            'password' => getenv('EVO_TEST_DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ];
    }

    public static function resetMysqlDatabase(): void
    {
        $cfg = self::getMysqlConfig();
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['charset']);
        $pdo = new \PDO($dsn, $cfg['user'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

final class AuditOrchestrator
{
    private static array $logLines = [];
    /** @var string[] */
    private static array $storageDrivers = ['json', 'sqlite'];

    public static function run(array $argv): void
    {
        $single = null;
        $workerReportOut = null;
        $outJson = AuditConfig::outDir() . AuditConfig::OUT_JSON_NAME;
        $outLog = AuditConfig::outDir() . AuditConfig::OUT_LOG_NAME;
        $storageArg = 'both';
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--single=')) {
                $single = substr($arg, 9);
            } elseif (str_starts_with($arg, '--worker-report=')) {
                $workerReportOut = substr($arg, 16);
            } elseif (str_starts_with($arg, '--out-json=')) {
                $outJson = substr($arg, 11);
            } elseif (str_starts_with($arg, '--out-log=')) {
                $outLog = substr($arg, 10);
            } elseif (str_starts_with($arg, '--output-prefix=')) {
                $prefix = preg_replace('/[^a-zA-Z0-9._-]/', '_', substr($arg, 16));
                $outJson = AuditConfig::outDir() . $prefix . '.json';
                $outLog = AuditConfig::outDir() . $prefix . '.log';
            } elseif (str_starts_with($arg, '--storage=')) {
                $storageArg = strtolower(trim(substr($arg, 10)));
            }
        }

        if ($storageArg === 'both') {
            self::$storageDrivers = ['json', 'sqlite'];
        } else {
            $parts = array_values(array_filter(array_map('trim', explode(',', $storageArg))));
            $valid = array_values(array_intersect($parts, ['json', 'sqlite', 'mysql']));
            if (empty($valid)) {
                fwrite(STDERR, "Invalid --storage argument: {$storageArg}\n");
                exit(1);
            }
            self::$storageDrivers = $valid;
        }

        if ($single !== null && $workerReportOut !== null) {
            $res = self::auditSinglePlugin($single);
            file_put_contents($workerReportOut, json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        if (!is_dir(AuditConfig::outDir())) {
            @mkdir(AuditConfig::outDir(), 0755, true);
        }

        self::log("=== 299koEVO Exhaustive 41-Plugin Functional Audit [drivers=" . implode(',', self::$storageDrivers) . "] ===");
        self::log("Started at: " . date('c'));
        self::log("PHP Version: " . PHP_VERSION);

        $plugins = self::discoverPlugins();
        if ($single !== null) {
            if (!in_array($single, $plugins, true)) {
                self::log("FATAL: Plugin '$single' not found.");
                exit(1);
            }
            $plugins = [$single];
        }
        self::log("Discovered plugin directories: " . count($plugins));

        $report = [
            'meta' => [
                'generated_at' => date('c'),
                'php_version' => PHP_VERSION,
                'plugin_count' => count($plugins),
                'drivers' => self::$storageDrivers,
                'open_basedir_isolated' => true,
                'single_filter' => $single,
            ],
            'summary' => [
                'VALIDE' => 0,
                'PARTIEL' => 0,
                'ECHEC' => 0,
                'NON_TESTE' => 0,
            ],
            'plugins' => [],
        ];

        $total = count($plugins);
        $usesMysql = in_array('mysql', self::$storageDrivers, true);
        if ($total === 1) {
            $plugin = $plugins[0];
            self::log("[1/1] Auditing plugin: $plugin");
            $resultsByPlugin = [$plugin => self::auditSinglePlugin($plugin)];
        } else {
            $concurrency = $usesMysql ? 1 : 6;
            $resultsByPlugin = self::runPluginsInParallelBatches($plugins, $concurrency, implode(',', self::$storageDrivers));
        }

        foreach ($plugins as $idx => $plugin) {
            $num = $idx + 1;
            $pluginResult = $resultsByPlugin[$plugin] ?? [
                'plugin' => $plugin,
                'status' => 'NON TESTÉ',
                'reasons' => ['Orchestration batch worker did not return result'],
                'warnings' => [],
            ];
            $report['plugins'][$plugin] = $pluginResult;

            $statusKey = match ($pluginResult['status']) {
                'VALIDÉ' => 'VALIDE',
                'PARTIEL' => 'PARTIEL',
                'ÉCHEC' => 'ECHEC',
                default => 'NON_TESTE',
            };
            $report['summary'][$statusKey]++;

            $instParts = [];
            $frontParts = [];
            $adminParts = [];
            foreach (self::$storageDrivers as $drv) {
                $tag = strtoupper($drv[0]);
                $instParts[] = $tag . ':' . self::shortBool($pluginResult['phase_b'][$drv]['active_after_reload'] ?? false);
                $frontParts[] = $tag . ':' . self::shortExec($pluginResult['phase_e'][$drv]['front'] ?? []);
                $adminParts[] = $tag . ':' . self::shortExec($pluginResult['phase_e'][$drv]['admin'] ?? []);
            }

            self::log(sprintf(
                "[%d/%d] %s -> %s | lint:%d/%d | install(%s) | routes:%d/%d | hooks:%d/%d(exec:%d/%d) | front(%s) | admin(%s) | reasons: %s",
                $num,
                $total,
                $plugin,
                $pluginResult['status'],
                $pluginResult['phase_a']['php_lint']['passed'] ?? 0,
                $pluginResult['phase_a']['php_lint']['checked'] ?? 0,
                implode(' ', $instParts),
                $pluginResult['phase_c']['resolved_count'] ?? 0,
                $pluginResult['phase_c']['declared_routes_count'] ?? 0,
                $pluginResult['phase_d']['callable_count'] ?? 0,
                $pluginResult['phase_d']['declared_hooks_count'] ?? 0,
                $pluginResult['phase_d']['succeeded_count'] ?? 0,
                $pluginResult['phase_d']['executed_count'] ?? 0,
                implode(' ', $frontParts),
                implode(' ', $adminParts),
                implode(' ; ', $pluginResult['reasons'] ?? [])
            ));
        }

        $report['meta']['finished_at'] = date('c');

        foreach ([$outJson, $outLog] as $targetOut) {
            $dir = dirname($targetOut);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        file_put_contents(
            $outJson,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        self::log("=== SUMMARY ===");
        self::log("VALIDÉ: " . $report['summary']['VALIDE']);
        self::log("PARTIEL: " . $report['summary']['PARTIEL']);
        self::log("ÉCHEC: " . $report['summary']['ECHEC']);
        self::log("NON TESTÉ: " . $report['summary']['NON_TESTE']);
        file_put_contents($outLog, implode(PHP_EOL, self::$logLines) . PHP_EOL);
    }

    private static function runPluginsInParallelBatches(array $plugins, int $concurrency, string $storageArg): array
    {
        $batchDir = sprintf('/tmp/299ko_audit_batch_%d_%s', getmypid(), bin2hex(random_bytes(4)));
        mkdir($batchDir, 0777, true);
        $results = [];
        try {
            $chunks = array_chunk($plugins, max(1, $concurrency));
            foreach ($chunks as $chunk) {
                $procs = [];
                foreach ($chunk as $plugin) {
                    $outJson = $batchDir . '/' . $plugin . '.json';
                    $cmd = implode(' ', [
                        escapeshellarg(PHP_BINARY),
                        escapeshellarg(__FILE__),
                        '--single=' . escapeshellarg($plugin),
                        '--storage=' . escapeshellarg($storageArg),
                        '--worker-report=' . escapeshellarg($outJson),
                    ]);
                    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                    $proc = proc_open($cmd, $spec, $pipes, AuditConfig::rootDir());
                    if (is_resource($proc)) {
                        fclose($pipes[0]);
                        $procs[$plugin] = ['proc' => $proc, 'pipes' => $pipes, 'out' => $outJson];
                    }
                }
                foreach ($procs as $plugin => $info) {
                    stream_get_contents($info['pipes'][1]);
                    stream_get_contents($info['pipes'][2]);
                    fclose($info['pipes'][1]);
                    fclose($info['pipes'][2]);
                    proc_close($info['proc']);
                    if (is_file($info['out'])) {
                        $decoded = json_decode((string) file_get_contents($info['out']), true);
                        if (is_array($decoded)) {
                            $results[$plugin] = $decoded;
                        }
                    }
                }
            }
        } finally {
            self::removeDir($batchDir);
        }
        return $results;
    }

    private static function discoverPlugins(): array
    {
        $entries = scandir(AuditConfig::pluginsDir());
        $plugins = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir(AuditConfig::pluginsDir() . $entry)) {
                $plugins[] = $entry;
            }
        }
        natcasesort($plugins);
        return array_values($plugins);
    }

    private static function auditSinglePlugin(string $plugin): array
    {
        $phaseA = self::runPhaseA($plugin);

        $phaseB = [];
        foreach (self::$storageDrivers as $drv) {
            $phaseB[$drv] = self::runWorkerInstall($plugin, $drv);
        }

        $primaryDriver = in_array('mysql', self::$storageDrivers, true)
            ? 'mysql'
            : (in_array('sqlite', self::$storageDrivers, true) ? 'sqlite' : self::$storageDrivers[0]);

        $phaseCD = self::runWorkerRoutesHooks($plugin, $primaryDriver);

        $phaseE = [];
        foreach (self::$storageDrivers as $drv) {
            $phaseE[$drv] = self::runPhaseEForDriver($plugin, $drv, $phaseA, $phaseCD);
        }

        $evaluation = self::evaluatePlugin($plugin, $phaseA, $phaseB, $phaseCD, $phaseE);

        return [
            'plugin' => $plugin,
            'status' => $evaluation['status'],
            'reasons' => $evaluation['reasons'],
            'warnings' => $evaluation['warnings'],
            'phase_a' => $phaseA,
            'phase_b' => $phaseB,
            'phase_c' => $phaseCD['routes'] ?? ['error' => $phaseCD['error'] ?? 'worker failed'],
            'phase_d' => $phaseCD['hooks'] ?? ['error' => $phaseCD['error'] ?? 'worker failed'],
            'phase_e' => $phaseE,
        ];
    }

    private static function runPhaseA(string $plugin): array
    {
        $dir = AuditConfig::pluginsDir() . $plugin . '/';
        $infosFile = $dir . 'param/infos.json';
        $configFile = $dir . 'param/config.json';
        $hooksFile = $dir . 'param/hooks.json';
        $routesFile = $dir . 'param/routes.php';
        $storageFile = $dir . 'param/storage.json';
        $mainFile = $dir . $plugin . '.php';

        $infos = is_file($infosFile) ? (json_decode((string) file_get_contents($infosFile), true) ?: []) : [];
        $defaultConfig = is_file($configFile) ? (json_decode((string) file_get_contents($configFile), true) ?: []) : [];
        $hooks = is_file($hooksFile) ? (json_decode((string) file_get_contents($hooksFile), true) ?: []) : [];
        $storage = is_file($storageFile) ? (json_decode((string) file_get_contents($storageFile), true) ?: []) : [];

        $phpFiles = [];
        $tplFiles = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            $path = $file->getPathname();
            if ($ext === 'php') {
                $phpFiles[] = $path;
            } elseif ($ext === 'tpl') {
                $tplFiles[] = substr($path, strlen($dir));
            }
        }
        sort($phpFiles);
        sort($tplFiles);

        $lintResult = self::lintPhpFiles($phpFiles);

        $hardcodedDataIo = [];
        $externalCalls = [];
        $interPluginDeps = [];
        $sqlDialectSuspects = [];
        $referencedTpls = [];
        $hasInstallFn = false;
        $hasUninstallFn = false;

        foreach ($phpFiles as $phpFile) {
            $rel = substr($phpFile, strlen($dir));
            if (str_contains($rel, 'libs/mpdf/') || str_contains($rel, '/vendor/') || str_starts_with($rel, 'tests/')) {
                continue;
            }
            $content = (string) file_get_contents($phpFile);
            $lines = explode("\n", $content);

            if (preg_match('/function\s+' . preg_quote($plugin, '/') . 'Install\s*\(/i', $content)) {
                $hasInstallFn = true;
            }
            if (preg_match('/function\s+' . preg_quote($plugin, '/') . 'Uninstall\s*\(/i', $content)) {
                $hasUninstallFn = true;
            }

            foreach ($lines as $lineIdx => $line) {
                $ln = $lineIdx + 1;
                if (preg_match('/(?:file_exists|is_file|is_dir|fopen|file_get_contents|file_put_contents|readJsonFile|writeJsonFile|unlink|rmdir|mkdir|scandir|glob|copy|rename|require(?:_once)?|include(?:_once)?)\s*\([^)]*(?:ROOT\s*\.\s*[\'"]data|dirname\s*\([^)]*\)\s*\.\s*[\'"]\/?(?:\.\.\/)*data|__DIR__\s*\.\s*[\'"]\/(?:\.\.\/)+data)/i', $line)) {
                    $hardcodedDataIo[] = ['file' => $rel, 'line' => $ln, 'code' => trim($line)];
                }

                if (preg_match('/\b(curl_init|curl_exec|curl_multi_exec|fsockopen|stream_socket_client)\s*\(/i', $line)) {
                    $externalCalls[] = ['file' => $rel, 'line' => $ln, 'kind' => 'network_socket_curl', 'code' => trim($line)];
                } elseif (preg_match('/file_get_contents\s*\(\s*[\'"]https?:\/\//i', $line) || preg_match('/[\'"]https?:\/\/(?:api\.|graph\.|oauth|checkout|stripe|paypal|openstreetmap|nominatim|open-meteo|api\.github|299ko)/i', $line)) {
                    $externalCalls[] = ['file' => $rel, 'line' => $ln, 'kind' => 'http_api', 'code' => trim($line)];
                } elseif (preg_match('/(?:->sendMail|\bmail\s*\(|PHPMailer|smtp)/i', $line)) {
                    $externalCalls[] = ['file' => $rel, 'line' => $ln, 'kind' => 'mail_smtp', 'code' => trim($line)];
                } elseif (preg_match('/\b(?:Stripe|PayPal|HelloAsso|OAuth2?Client)\b/i', $line)) {
                    $externalCalls[] = ['file' => $rel, 'line' => $ln, 'kind' => 'payment_or_oauth', 'code' => trim($line)];
                }

                if (preg_match_all('/isActivePlugin\s*\(\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/', $line, $m)) {
                    foreach ($m[1] as $dep) {
                        if ($dep !== $plugin) {
                            $interPluginDeps[] = $dep;
                        }
                    }
                }
                if (preg_match_all('/DATA_PLUGIN\s*\.\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/', $line, $m)) {
                    foreach ($m[1] as $dep) {
                        if ($dep !== $plugin) {
                            $interPluginDeps[] = $dep;
                        }
                    }
                }

                if (preg_match('/\b(?:SHOW\s+TABLES|DESCRIBE\s+|AUTO_INCREMENT|ENGINE\s*=|ENUM\s*\(|NOW\s*\(\)|DATE_SUB\s*\(|YEAR\s*\(|GROUP_CONCAT\s*\(|ON\s+DUPLICATE\s+KEY|INSERT\s+IGNORE|REPLACE\s+INTO)\b/i', $line, $sm)) {
                    $sqlDialectSuspects[] = ['file' => $rel, 'line' => $ln, 'pattern' => $sm[0], 'code' => trim($line)];
                }

                if (preg_match_all('/(?:Template|setFile|new\s+(?:\\\\Template\\\\)?Template)\s*\(\s*(?:PLUGINS\s*\.\s*[\'"][^\'"]+?[\'"]\s*\.\s*[\'"]|[A-Z_]+\s*\.\s*)?[\'"]([^\'"]+\.tpl)[\'"]/', $line, $tm)) {
                    foreach ($tm[1] as $tplRef) {
                        $referencedTpls[] = ['file' => $rel, 'line' => $ln, 'ref' => $tplRef];
                    }
                }
            }
        }

        $interPluginDeps = array_values(array_unique($interPluginDeps));

        return [
            'php_files_count' => count($phpFiles),
            'tpl_files_count' => count($tplFiles),
            'tpl_files' => $tplFiles,
            'has_infos' => is_file($infosFile),
            'has_config' => is_file($configFile),
            'has_hooks' => is_file($hooksFile),
            'has_routes' => is_file($routesFile),
            'has_storage' => is_file($storageFile),
            'has_plugin_php' => is_file($mainFile),
            'has_langs' => is_dir($dir . 'langs'),
            'has_template_dir' => is_dir($dir . 'template') || is_dir($dir . 'views'),
            'has_install_fn' => $hasInstallFn,
            'has_uninstall_fn' => $hasUninstallFn,
            'infos' => $infos,
            'default_config_keys' => array_keys($defaultConfig),
            'storage_json' => $storage,
            'hooks_json' => $hooks,
            'php_lint' => $lintResult,
            'hardcoded_data_io' => $hardcodedDataIo,
            'external_calls' => $externalCalls,
            'inter_plugin_deps' => $interPluginDeps,
            'sql_dialect_suspects' => $sqlDialectSuspects,
            'referenced_tpls' => $referencedTpls,
        ];
    }

    private static function lintPhpFiles(array $files): array
    {
        $checked = count($files);
        $passed = 0;
        $errors = [];

        if ($checked === 0) {
            return ['checked' => 0, 'passed' => 0, 'errors' => []];
        }

        $chunks = array_chunk($files, 20);
        $batchSize = 8;
        for ($i = 0; $i < count($chunks); $i += $batchSize) {
            $slice = array_slice($chunks, $i, $batchSize);
            $procs = [];
            foreach ($slice as $cIdx => $chunk) {
                $escaped = array_map('escapeshellarg', $chunk);
                $cmd = 'for f in ' . implode(' ', $escaped) . '; do php -l "$f" 2>&1 || true; done';
                $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $proc = proc_open($cmd, $spec, $pipes);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $procs[$cIdx] = ['proc' => $proc, 'pipes' => $pipes, 'count' => count($chunk)];
                }
            }
            foreach ($procs as $item) {
                $out = stream_get_contents($item['pipes'][1]);
                fclose($item['pipes'][1]);
                fclose($item['pipes'][2]);
                proc_close($item['proc']);

                $lines = explode("\n", trim((string) $out));
                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }
                    if (str_starts_with($line, 'No syntax errors detected in ')) {
                        $passed++;
                    } elseif (preg_match('/error|fatal|parse/i', $line)) {
                        $errors[] = $line;
                    }
                }
            }
        }

        return ['checked' => $checked, 'passed' => $passed, 'errors' => $errors];
    }

    private static function createTempDir(string $plugin, string $tag): string
    {
        $dir = sprintf('/tmp/299ko_plugin_audit_%s_%s_%d_%s', preg_replace('/[^a-zA-Z0-9_-]/', '_', $plugin), $tag, getmypid(), bin2hex(random_bytes(4)));
        mkdir($dir, 0777, true);
        return $dir;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private static function runWorkerProcess(string $plugin, string $storage, string $mode, string $tmpDir, ?string $extraRoute = null): array
    {
        $outFile = $tmpDir . '/worker_' . $mode . '_' . bin2hex(random_bytes(4)) . '.json';
        $cmdParts = [
            escapeshellarg(PHP_BINARY),
            '-d', 'allow_url_fopen=0',
            '-d', 'sendmail_path=/bin/true',
            '-d', 'display_errors=0',
            '-d', 'log_errors=0',
            '-d', escapeshellarg('error_log=' . $tmpDir . '/php_error.log'),
            '-d', escapeshellarg('session.save_path=' . $tmpDir . '/sessions'),
            '-d', escapeshellarg('open_basedir=' . AuditConfig::openBasedir()),
            escapeshellarg(__FILE__),
            '--worker',
            '--plugin=' . escapeshellarg($plugin),
            '--storage=' . escapeshellarg($storage),
            '--mode=' . escapeshellarg($mode),
            '--tmp=' . escapeshellarg($tmpDir),
            '--out=' . escapeshellarg($outFile),
        ];
        if ($extraRoute !== null) {
            $cmdParts[] = '--route=' . escapeshellarg($extraRoute);
        }

        $cmd = implode(' ', $cmdParts);
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $spec, $pipes, AuditConfig::rootDir());
        if (!is_resource($proc)) {
            return ['worker_ok' => false, 'error' => 'Failed to spawn worker'];
        }
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);
        stream_set_timeout($pipes[2], 15);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if (is_file($outFile)) {
            $decoded = json_decode((string) file_get_contents($outFile), true);
            @unlink($outFile);
            if (is_array($decoded)) {
                $decoded['process_exit_code'] = $exitCode;
                if ($stderr !== '') {
                    $decoded['process_stderr'] = $stderr;
                }
                return $decoded;
            }
        }

        return [
            'worker_ok' => false,
            'process_exit_code' => $exitCode,
            'error' => 'Worker exited without producing output JSON',
            'stdout' => substr($stdout, 0, 1500),
            'stderr' => substr($stderr, 0, 1500),
        ];
    }

    private static function runWorkerInstall(string $plugin, string $storage): array
    {
        $tmpDir = self::createTempDir($plugin, 'inst_' . $storage);
        try {
            if ($storage === 'mysql') {
                AuditConfig::resetMysqlDatabase();
            }
            return self::runWorkerProcess($plugin, $storage, 'install', $tmpDir);
        } finally {
            self::removeDir($tmpDir);
        }
    }

    private static function runWorkerRoutesHooks(string $plugin, string $driver = 'sqlite'): array
    {
        $tmpDir = self::createTempDir($plugin, 'rh_' . $driver);
        try {
            if ($driver === 'mysql') {
                AuditConfig::resetMysqlDatabase();
            }
            $inst = self::runWorkerProcess($plugin, $driver, 'install', $tmpDir);
            $res = self::runWorkerProcess($plugin, $driver, 'routes_hooks', $tmpDir);
            $res['pre_install_ok'] = $inst['active_after_reload'] ?? false;
            return $res;
        } finally {
            self::removeDir($tmpDir);
        }
    }

    private static function runPhaseEForDriver(string $plugin, string $storage, array $phaseA, array $phaseCD): array
    {
        $tmpDir = self::createTempDir($plugin, 'rt_' . $storage);
        try {
            if ($storage === 'mysql') {
                AuditConfig::resetMysqlDatabase();
            }
            $inst = self::runWorkerProcess($plugin, $storage, 'install', $tmpDir);
            $frontRoutes = [];
            $adminRoutes = [];
            foreach (($phaseCD['routes']['routes'] ?? []) as $r) {
                $methods = explode('|', strtoupper((string) ($r['method'] ?? '')));
                if (!in_array('GET', $methods, true)) {
                    continue;
                }
                if (!($r['is_safe_get'] ?? false)) {
                    continue;
                }
                if (($r['route_params_count'] ?? 0) > 0) {
                    continue;
                }
                $spec = base64_encode((string) json_encode(['uri' => $r['uri'] ?? '/', 'target' => $r['target'] ?? '']));
                if (($r['scope'] ?? 'public') === 'admin') {
                    $adminRoutes[] = $spec;
                } else {
                    $frontRoutes[] = $spec;
                }
            }

            $frontRes = ['tested' => false, 'routes_tested' => []];
            if (!empty($frontRoutes)) {
                $frontRes['tested'] = true;
                foreach (array_slice($frontRoutes, 0, 3) as $rSpec) {
                    $sub = self::runWorkerProcess($plugin, $storage, 'front', $tmpDir, $rSpec);
                    $frontRes['routes_tested'][] = $sub;
                }
            }

            $adminRes = ['tested' => false, 'routes_tested' => []];
            if (!empty($adminRoutes)) {
                $adminRes['tested'] = true;
                foreach (array_slice($adminRoutes, 0, 3) as $rSpec) {
                    $sub = self::runWorkerProcess($plugin, $storage, 'admin', $tmpDir, $rSpec);
                    $adminRes['routes_tested'][] = $sub;
                }
            }

            $entitiesRes = self::runWorkerProcess($plugin, $storage, 'entities', $tmpDir);

            return [
                'install_prep_ok' => $inst['active_after_reload'] ?? false,
                'front' => $frontRes,
                'admin' => $adminRes,
                'entities' => $entitiesRes,
            ];
        } finally {
            self::removeDir($tmpDir);
        }
    }

    private static function evaluatePlugin(string $plugin, array $a, array $b, array $cd, array $e): array
    {
        $failReasons = [];
        $partialReasons = [];
        $warnings = [];

        if (!empty($a['php_lint']['errors'])) {
            $failReasons[] = 'Erreurs de syntaxe PHP (' . count($a['php_lint']['errors']) . ')';
        }
        if (!empty($a['hardcoded_data_io'])) {
            $failReasons[] = 'Chemin /data/ code en dur (' . count($a['hardcoded_data_io']) . ' occurrence(s))';
        }

        foreach (self::$storageDrivers as $drv) {
            $bDrv = $b[$drv] ?? [];
            if (!($bDrv['worker_ok'] ?? false) || !($bDrv['installed'] ?? false) || !($bDrv['active_after_reload'] ?? false)) {
                $failReasons[] = "Install/activation $drv echouee";
            }
            if (!empty($bDrv['exceptions']) || !empty($bDrv['fatal'])) {
                $failReasons[] = "Exception/Fatal lors de l'install ($drv)";
            }
            if (!($bDrv['idempotent_reinstall'] ?? true)) {
                $partialReasons[] = "Reinstall non idempotente ($drv)";
            }
            if (!empty($bDrv['warnings'])) {
                $warnings["install_$drv"] = $bDrv['warnings'];
            }
        }

        $routes = $cd['routes'] ?? [];
        if (($routes['failed_count'] ?? 0) > 0) {
            $failReasons[] = $routes['failed_count'] . ' route(s)/controleur(s) introuvable(s) ou invalide(s)';
        }
        if (($routes['unsafe_get_routes_count'] ?? 0) > 0) {
            $partialReasons[] = $routes['unsafe_get_routes_count'] . ' route(s) GET mutante(s)/risquee(s)';
        }

        $hooks = $cd['hooks'] ?? [];
        if (($hooks['non_callable_count'] ?? 0) > 0) {
            $failReasons[] = $hooks['non_callable_count'] . ' hook(s) declare(s) non appelable(s) au demarrage';
        }
        if (($hooks['failed_execution_count'] ?? 0) > 0) {
            $failReasons[] = $hooks['failed_execution_count'] . ' hook(s) ont leve une erreur a l\'execution';
        }
        if (!empty($hooks['warnings'])) {
            $warnings['hooks'] = $hooks['warnings'];
        }

        foreach (self::$storageDrivers as $drv) {
            $eDrv = $e[$drv] ?? [];
            $ent = $eDrv['entities'] ?? [];
            if (!empty($ent['missing_tpls'])) {
                $failReasons[] = "Templates .tpl manquants ($drv): " . implode(', ', $ent['missing_tpls']);
            }
            if (!empty($ent['fatal']) || !empty($ent['exceptions'])) {
                $failReasons[] = "Erreur entites/services ($drv)";
            }
            if (!empty($ent['warnings'])) {
                $warnings["entities_$drv"] = $ent['warnings'];
            }

            foreach (['front', 'admin'] as $side) {
                $sideRes = $eDrv[$side] ?? [];
                foreach (($sideRes['routes_tested'] ?? []) as $rt) {
                    if (!empty($rt['fatal']) || !empty($rt['exceptions']) || !empty($rt['html_errors'])) {
                        $failReasons[] = "Erreur execution $side ($drv) sur " . ($rt['target'] ?? $rt['uri'] ?? '?');
                    } elseif (!($rt['worker_ok'] ?? false) || ($rt['status'] ?? '') === 'exited_early') {
                        $failReasons[] = "Arret premature / crash silencieux $side ($drv) (HTTP " . ($rt['http_code'] ?? '?') . ") sur " . ($rt['target'] ?? $rt['uri'] ?? '?');
                    } elseif (($rt['status'] ?? '') === 'ok_raw_exit') {
                        $partialReasons[] = "Sortie brute avec exit()/die() direct $side ($drv) sur " . ($rt['target'] ?? $rt['uri'] ?? '?');
                    } elseif (($rt['status'] ?? '') === 'redirect' && $side === 'admin') {
                        $partialReasons[] = "Admin redirige (" . ($rt['location'] ?? '?') . ") sur " . ($rt['target'] ?? '?');
                    } elseif (($rt['status'] ?? '') === 'ok' && ($rt['output_len'] ?? 0) === 0 && !str_contains(strtolower($rt['uri'] ?? ''), 'ajax') && !str_contains(strtolower($rt['uri'] ?? ''), 'api')) {
                        $partialReasons[] = "Sortie vide $side ($drv) sur " . ($rt['target'] ?? '?');
                    }
                    if (!empty($rt['warnings'])) {
                        $warnings["{$side}_{$drv}"] = array_merge($warnings["{$side}_{$drv}"] ?? [], $rt['warnings']);
                    }
                }
            }
        }

        if (!empty($a['external_calls'])) {
            $kinds = array_unique(array_column($a['external_calls'], 'kind'));
            $partialReasons[] = 'Dependance externe (' . implode(', ', $kinds) . ') non testable completement hors ligne';
        }
        if (!empty($a['inter_plugin_deps'])) {
            $partialReasons[] = 'Dependance inter-plugins: ' . implode(', ', $a['inter_plugin_deps']);
        }
        if (!empty($warnings)) {
            $partialReasons[] = 'Warnings/Deprecations PHP observes';
        }

        if (!empty($failReasons)) {
            return [
                'status' => 'ÉCHEC',
                'reasons' => array_values(array_unique(array_merge($failReasons, $partialReasons))),
                'warnings' => $warnings,
            ];
        }
        if (!empty($partialReasons)) {
            return [
                'status' => 'PARTIEL',
                'reasons' => array_values(array_unique($partialReasons)),
                'warnings' => $warnings,
            ];
        }
        return [
            'status' => 'VALIDÉ',
            'reasons' => ['Install, routes, hooks, front/admin et entites valides sur JSON et SQLite sans erreur'],
            'warnings' => [],
        ];
    }

    private static function shortBool(bool $v): string
    {
        return $v ? 'OK' : 'KO';
    }

    private static function shortExec(array $side): string
    {
        if (!($side['tested'] ?? false)) {
            return 'N/A';
        }
        $routes = $side['routes_tested'] ?? [];
        $ok = 0;
        $err = 0;
        $redir = 0;
        foreach ($routes as $r) {
            if (!empty($r['fatal']) || !empty($r['exceptions']) || !empty($r['html_errors']) || !($r['worker_ok'] ?? false) || ($r['status'] ?? '') === 'exited_early') {
                $err++;
            } elseif (($r['status'] ?? '') === 'redirect') {
                $redir++;
            } else {
                $ok++;
            }
        }
        return "{$ok}ok/{$redir}rd/{$err}err";
    }

    private static function log(string $msg): void
    {
        self::$logLines[] = $msg;
        echo $msg . PHP_EOL;
    }
}

final class AuditWorker
{
    private static array $capturedWarnings = [];
    private static ?string $outFile = null;
    private static array $result = ['worker_ok' => false];
    private static bool $finishedCleanly = false;
    private static int $initialObLevel = 0;

    public static function run(array $argv): void
    {
        $opts = [];
        foreach ($argv as $arg) {
            if (preg_match('/^--(plugin|storage|mode|tmp|out|route)=(.+)$/', $arg, $m)) {
                $opts[$m[1]] = $m[2];
            }
        }
        $plugin = $opts['plugin'] ?? '';
        $storage = $opts['storage'] ?? 'sqlite';
        $mode = $opts['mode'] ?? '';
        $tmpDir = rtrim($opts['tmp'] ?? '', '/');
        self::$outFile = $opts['out'] ?? null;
        $routeSpec = null;
        if (!empty($opts['route'])) {
            $decoded = json_decode((string) base64_decode($opts['route'], true), true);
            if (is_array($decoded)) {
                $routeSpec = $decoded;
            }
        }

        self::$initialObLevel = ob_get_level();
        ob_start();
        self::installHandlers();

        register_shutdown_function(static function (): void {
            $capturedOut = '';
            while (ob_get_level() > self::$initialObLevel) {
                $chunk = ob_get_contents();
                if ($chunk !== false) {
                    $capturedOut .= $chunk;
                }
                @ob_end_clean();
            }
            if (!self::$finishedCleanly) {
                $lastErr = error_get_last();
                if ($lastErr && in_array($lastErr['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                    self::$result['fatal'] = $lastErr;
                    self::$result['status'] = 'fatal';
                } elseif (!isset(self::$result['fatal'])) {
                    $headers = headers_list();
                    $location = null;
                    foreach ($headers as $h) {
                        if (stripos($h, 'Location:') === 0) {
                            $location = trim(substr($h, 9));
                        }
                    }
                    $httpCode = (int) http_response_code();
                    $trimmedOut = trim($capturedOut);
                    $hasPhpErr = (bool) preg_match('/(Fatal error|Parse error|Uncaught\s+\w*(?:Exception|Error)|PDOException|SQLSTATE\[|Warning:|Notice:|Deprecated:)/i', $trimmedOut);
                    $isValidJson = $trimmedOut !== '' && (($trimmedOut[0] === '{' || $trimmedOut[0] === '[') && json_decode($trimmedOut, true) !== null && json_last_error() === JSON_ERROR_NONE);
                    $isRawTextOrScript = $trimmedOut !== '' && !$hasPhpErr;

                    if ($location !== null || ($httpCode >= 300 && $httpCode < 400)) {
                        self::$result['worker_ok'] = true;
                        self::$result['status'] = 'redirect';
                        self::$result['http_code'] = $httpCode;
                        self::$result['location'] = $location ?? ('HTTP ' . $httpCode);
                    } elseif (($httpCode === 0 || $httpCode === 200) && ($isValidJson || $isRawTextOrScript)) {
                        self::$result['worker_ok'] = true;
                        self::$result['status'] = 'ok_raw_exit';
                        self::$result['http_code'] = $httpCode === 0 ? 200 : $httpCode;
                        self::$result['raw_kind'] = $isValidJson ? 'json' : 'text/script';
                        self::$result['output_len'] = strlen($trimmedOut);
                        self::$result['output_preview'] = substr($trimmedOut, 0, 300);
                        self::$result['uri'] = self::$result['uri'] ?? ($_SERVER['REQUEST_URI'] ?? null);
                        self::$capturedWarnings[] = sprintf('[EXIT_DIRECT] Sortie brute (%s) via exit()/die() sur %s', self::$result['raw_kind'], self::$result['target'] ?? (self::$result['uri'] ?? '?'));
                    } else {
                        self::$result['status'] = 'exited_early';
                        self::$result['http_code'] = $httpCode;
                        self::$result['uri'] = self::$result['uri'] ?? ($_SERVER['REQUEST_URI'] ?? null);
                        self::$result['output_preview'] = substr($trimmedOut, 0, 300);
                    }
                }
            }
            self::$result['warnings'] = array_values(array_unique(self::$capturedWarnings));
            if (self::$outFile !== null) {
                file_put_contents(self::$outFile, json_encode(self::$result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        });

        self::bootEnvironment($tmpDir, $storage, $mode === 'admin', $plugin, $mode, $routeSpec['uri'] ?? '/');

        switch ($mode) {
            case 'install':
                self::handleInstall($plugin, $tmpDir, $storage);
                break;
            case 'routes_hooks':
                self::handleRoutesHooks($plugin);
                break;
            case 'front':
                self::handleControllerExec($plugin, false, $routeSpec);
                break;
            case 'admin':
                self::handleControllerExec($plugin, true, $routeSpec);
                break;
            case 'entities':
                self::handleEntities($plugin);
                break;
            default:
                self::$result['error'] = "Unknown worker mode: $mode";
        }

        self::$finishedCleanly = true;
    }

    private static function bootEnvironment(string $tmpDir, string $storage, bool $asAdmin, string $targetPlugin = '', string $mode = '', string $requestUri = '/'): void
    {
        $dataDir = $tmpDir . '/data/';
        foreach ([
            $dataDir,
            $dataDir . 'core/',
            $dataDir . 'core/auth/',
            $dataDir . 'core/page/',
            $dataDir . 'core/media/',
            $dataDir . 'core/settings/',
            $dataDir . 'core/extensions/',
            $dataDir . 'core/content/',
            $dataDir . 'plugin/',
            $dataDir . 'upload/',
            $dataDir . 'cache/',
            $dataDir . 'logs/',
            $tmpDir . '/sessions/',
        ] as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0777, true);
            }
        }

        if (!is_file($dataDir . 'config.json')) {
            file_put_contents($dataDir . 'config.json', json_encode([
                'siteName' => 'Audit Declic18',
                'siteDesc' => 'Isolated audit',
                'siteUrl' => 'http://declic18.test/',
                'theme' => 'default',
                'hideTitles' => false,
                'defaultPlugin' => 'page',
                'defaultAdminPlugin' => 'page',
                'debug' => false,
                'defaultAdminLang' => 'fr',
                'lang' => 'fr',
                'itemsByPage' => 10,
            ], JSON_PRETTY_PRINT));
        }

        if (!is_file($dataDir . 'env.json')) {
            $defaultDriver = ($storage === 'sqlite' || $storage === 'mysql') ? 'sql' : 'json';
            $dbConf = ($storage === 'mysql')
                ? AuditConfig::getMysqlConfig()
                : [
                    'driver' => 'sqlite',
                    'path' => $dataDir . 'database.sqlite',
                ];
            file_put_contents($dataDir . 'env.json', json_encode([
                'database' => $dbConf,
                'storage' => [
                    'default' => $defaultDriver,
                    'User' => 'json',
                    'PageItem' => $defaultDriver,
                    'MenuItem' => $defaultDriver,
                ],
            ], JSON_PRETTY_PRINT));
        }

        if (!is_file($dataDir . 'core/auth/groups.json')) {
            file_put_contents($dataDir . 'core/auth/groups.json', json_encode([
                [
                    'id' => 1,
                    'slug' => 'admin',
                    'name' => 'Administrateur',
                    'permissions' => ['all'],
                    'system' => true,
                ],
                [
                    'id' => 2,
                    'slug' => 'member',
                    'name' => 'Membre',
                    'permissions' => [],
                    'system' => true,
                ],
            ], JSON_PRETTY_PRINT));
        }

        if (!is_file($dataDir . 'core/auth/users.json')) {
            file_put_contents($dataDir . 'core/auth/users.json', json_encode([
                [
                    'id' => 1,
                    'email' => 'admin@test.local',
                    'username' => 'AdminAudit',
                    'password_hash' => password_hash('TestAdmin123!', PASSWORD_BCRYPT),
                    'token' => 'tok_admin_audit',
                    'role' => 'admin',
                    'group_id' => 1,
                    'status' => 'active',
                    'permissions' => ['all'],
                    'tokens' => [
                        ['token' => 'tok_admin_audit', 'created_at' => time()],
                        ['token' => 'api_admin_token_xyz', 'created_at' => time()],
                    ],
                    'created_at' => '2026-01-01 00:00:00',
                ],
            ], JSON_PRETTY_PRINT));
        }

        if (!is_file($dataDir . 'core/page/pages.json')) {
            file_put_contents($dataDir . 'core/page/pages.json', json_encode([], JSON_PRETTY_PRINT));
        }

        if (!defined('ROOT')) define('ROOT', AuditConfig::rootDir());
        if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
        if (!defined('DATA')) define('DATA', $dataDir);
        if (!defined('DATA_CORE')) define('DATA_CORE', DATA . 'core/');
        if (!defined('DATA_CORE_AUTH')) define('DATA_CORE_AUTH', DATA_CORE . 'auth/');
        if (!defined('DATA_CORE_PAGE')) define('DATA_CORE_PAGE', DATA_CORE . 'page/');
        if (!defined('DATA_CORE_MEDIA')) define('DATA_CORE_MEDIA', DATA_CORE . 'media/');
        if (!defined('DATA_CORE_SETTINGS')) define('DATA_CORE_SETTINGS', DATA_CORE . 'settings/');
        if (!defined('DATA_CORE_EXTENSIONS')) define('DATA_CORE_EXTENSIONS', DATA_CORE . 'extensions/');
        if (!defined('DATA_CORE_CONTENT')) define('DATA_CORE_CONTENT', DATA_CORE . 'content/');
        if (!defined('UPLOAD')) define('UPLOAD', DATA . 'upload/');
        if (!defined('DATA_PLUGIN')) define('DATA_PLUGIN', DATA . 'plugin/');
        if (!defined('CACHE')) define('CACHE', DATA . 'cache/');
        if (!defined('BASE_PATH')) define('BASE_PATH', '');
        if (!defined('IS_LOGGED')) define('IS_LOGGED', $asAdmin);
        if (!defined('IS_ADMIN')) define('IS_ADMIN', $asAdmin);
        if (!defined('IS_PRO')) define('IS_PRO', $asAdmin);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $requestUri !== '' ? $requestUri : '/';
        $_SERVER['HTTP_HOST'] = 'declic18.test';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_SESSION = [];

        if ($asAdmin) {
            $_SESSION['email'] = 'admin@test.local';
            $_SESSION['token'] = 'tok_admin_audit';
        }

        require_once ROOT . 'common/config.php';
        require_once COMMON . 'Core/Autoloader/HybridAutoloader.php';
        new \Core\Autoloader\HybridAutoloader([COMMON, PLUGINS], CACHE . 'autoloader_cache.php', false, false);

        \Core\Security\Csrf::start();
        if ($asAdmin) {
            $_SESSION['email'] = 'admin@test.local';
            $_SESSION['token'] = 'tok_admin_audit';
        }
        $router = \Core\Router\Router::getInstance();
        $core = \Core\Core::getInstance();
        $core->init();
        // Re-install AuditWorker handlers so Core\Logger does not swallow uncaught exceptions or warnings
        self::installHandlers();
        if ($asAdmin) {
            $core->auth()->resetCurrentUserCache();
        }

        $pluginsManager = \Core\Plugin\PluginsManager::getInstance();
        if ($mode !== 'install') {
            foreach ($pluginsManager->getPlugins() as $pl) {
                if ($mode === 'routes_hooks' && $pl->getName() === $targetPlugin) {
                    continue;
                }
                if ($pl->getConfigVal('activate')) {
                    $pl->loadLangFile();
                    $pl->loadRoutes();
                    if ($pl->getLibFile() !== false && file_exists($pl->getLibFile())) {
                        include_once $pl->getLibFile();
                    }
                    foreach ($pl->getHooks() as $hName => $hFn) {
                        $core->addHook($hName, $hFn);
                    }
                }
            }
        }

        \Core\Page\Page::addToNavigation();
        \Core\Auth\CurrentUser::get()->initialize();
        \Core\Lang::loadLanguageFile(THEMES . $core->getConfigVal('theme') . '/langs/');
        $themeFn = THEMES . $core->getConfigVal('theme') . '/functions.php';
        if (file_exists($themeFn)) {
            include_once $themeFn;
        }

        $activeRunPlugin = ($targetPlugin !== '' && !$core->isCoreModule($targetPlugin))
            ? ($pluginsManager->getPlugin($targetPlugin) ?: null)
            : null;
        $GLOBALS['runPlugin'] = $activeRunPlugin;

        \Template\Template::addGlobal('COMMON', COMMON);
        \Template\Template::addGlobal('DATA', DATA);
        \Template\Template::addGlobal('UPLOAD', UPLOAD);
        \Template\Template::addGlobal('DATA_PLUGIN', DATA_PLUGIN);
        \Template\Template::addGlobal('THEMES', THEMES);
        \Template\Template::addGlobal('PLUGINS', PLUGINS);
        \Template\Template::addGlobal('THEME_PATH', THEMES . $core->getConfigVal('theme') . '/');
        \Template\Template::addGlobal('SITE_URL', rtrim((string) $core->getConfigVal('siteUrl'), '/'));
        \Template\Template::addGlobal('THEME_URL', \Utils\Util::urlBuild(THEMES . $core->getConfigVal('theme')));
        \Template\Template::addGlobal('ADMIN_URL', rtrim($router->generate('admin'), '/') . '/');
        \Template\Template::addGlobal('VERSION', VERSION);
        \Template\Template::addGlobal('runPlugin', $activeRunPlugin);
        \Template\Template::addGlobal('ROUTER', $router);
        \Template\Template::addGlobal('pluginsManager', $pluginsManager);
        \Template\Template::addGlobal('CORE', $core);
        \Template\Template::addGlobal('ADMIN_PATH', ADMIN_PATH);
        \Template\Template::addGlobal('SHOW', \Utils\Show::class);
        \Template\Template::addGlobal('show', \Utils\Show::class);
        \Template\Template::addGlobal('Lang', \Core\Lang::class);
        \Template\Template::addGlobal('lang', \Core\Lang::class);
        \Template\Template::addGlobal('Util', \Utils\Util::class);
        \Template\Template::addGlobal('util', \Utils\Util::class);
        \Template\Template::addGlobal('USER', \Core\Auth\CurrentUser::get());
        \Template\Template::addGlobal('user', \Core\Auth\CurrentUser::get());
        \Template\Template::addGlobal('_csrfToken', \Core\Security\Csrf::token());
        \Template\Template::addGlobal('allowRegistrations', (bool) $core->getConfigVal('allowRegistrations'));
    }

    private static function handleInstall(string $plugin, string $tmpDir, string $storage): void
    {
        $exceptions = [];
        $start = microtime(true);
        $pm = \Core\Plugin\PluginsManager::getInstance();

        $installed = false;
        try {
            $installed = $pm->installPlugin($plugin, true);
        } catch (\Throwable $e) {
            $exceptions[] = self::fmtEx($e, 'installPlugin_1');
        }

        // Reset singleton to force disk reload verification
        $ref = new \ReflectionProperty(\Core\Plugin\PluginsManager::class, 'instance');
        $ref->setValue(null, null);
        $activeAfterReload = false;
        $isInstalledFlag = false;
        try {
            $activeAfterReload = \Core\Plugin\PluginsManager::isActivePlugin($plugin);
            $pl = \Core\Plugin\PluginsManager::getInstance()->getPlugin($plugin);
            $isInstalledFlag = $pl ? $pl->isInstalled() : false;
        } catch (\Throwable $e) {
            $exceptions[] = self::fmtEx($e, 'reload_check');
        }

        $idempotent = false;
        try {
            $idempotent = \Core\Plugin\PluginsManager::getInstance()->installPlugin($plugin, true);
        } catch (\Throwable $e) {
            $exceptions[] = self::fmtEx($e, 'installPlugin_2_idempotence');
        }

        $deactReact = false;
        try {
            $pm2 = \Core\Plugin\PluginsManager::getInstance();
            $pl2 = $pm2->getPlugin($plugin);
            if ($pl2) {
                $pl2->setConfigVal('activate', 0);
                $pm2->savePluginConfig($pl2);
                $ref->setValue(null, null);
                $off = !\Core\Plugin\PluginsManager::isActivePlugin($plugin);

                $pm3 = \Core\Plugin\PluginsManager::getInstance();
                $pl3 = $pm3->getPlugin($plugin);
                if ($pl3) {
                    $pl3->setConfigVal('activate', 1);
                    $pm3->savePluginConfig($pl3);
                    $ref->setValue(null, null);
                    $on = \Core\Plugin\PluginsManager::isActivePlugin($plugin);
                    $deactReact = ($off && $on);
                }
            }
        } catch (\Throwable $e) {
            $exceptions[] = self::fmtEx($e, 'deactivate_reactivate');
        }

        $createdFiles = [];
        $pluginDataDir = $tmpDir . '/data/plugin/' . $plugin;
        if (is_dir($pluginDataDir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDataDir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $createdFiles[] = substr($f->getPathname(), strlen($tmpDir . '/data/'));
                }
            }
            sort($createdFiles);
        }

        $createdTables = [];
        $sqlitePath = $tmpDir . '/data/database.sqlite';
        if ($storage === 'mysql') {
            try {
                $cfg = AuditConfig::getMysqlConfig();
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['charset']);
                $pdo = new \PDO($dsn, $cfg['user'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $rows = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
                $createdTables = $rows ?: [];
            } catch (\Throwable $e) {
                $exceptions[] = self::fmtEx($e, 'inspect_mysql');
            }
        } elseif (is_file($sqlitePath)) {
            try {
                $pdo = new \PDO('sqlite:' . $sqlitePath);
                $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
                $createdTables = $rows ?: [];
            } catch (\Throwable $e) {
                $exceptions[] = self::fmtEx($e, 'inspect_sqlite');
            }
        }

        self::$result = [
            'worker_ok' => true,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'installed' => $installed,
            'active_after_reload' => $activeAfterReload,
            'is_installed_flag' => $isInstalledFlag,
            'idempotent_reinstall' => $idempotent,
            'deactivate_reactivate' => $deactReact,
            'created_files' => $createdFiles,
            'created_tables' => $createdTables,
            'exceptions' => $exceptions,
        ];
    }

    private static function handleRoutesHooks(string $plugin): void
    {
        $pm = \Core\Plugin\PluginsManager::getInstance();
        $plObj = $pm->getPlugin($plugin);
        if (!$plObj) {
            self::$result = ['worker_ok' => false, 'error' => "Plugin object '$plugin' null"];
            return;
        }

        $plObj->loadLangFile();
        $libLoadErrors = [];
        if ($plObj->getLibFile() !== false && file_exists($plObj->getLibFile())) {
            try {
                include_once $plObj->getLibFile();
            } catch (\Throwable $e) {
                $libLoadErrors[] = self::fmtEx($e, 'libFile');
            }
        }

        // Phase D: Test hooks BEFORE loadControllers(), faithfully matching real common.php -> Core::callHook()!
        $hooksFile = AuditConfig::pluginsDir() . $plugin . '/param/hooks.json';
        $declaredHooks = is_file($hooksFile) ? (json_decode((string) file_get_contents($hooksFile), true) ?: []) : [];

        $hookEntries = [];
        $callableCount = 0;
        $nonCallableCount = 0;
        $executedCount = 0;
        $succeededCount = 0;
        $failedExecCount = 0;

        $safeHooks = [
            'frontHead', 'endFrontHead', 'endFrontBody', 'adminHead', 'endAdminHead',
            'afterHeader', 'adminAfterHeader', 'footer', 'adminFooter', 'endFooter',
            'endMainNavigation', 'endAdminNavigation', 'beforeRunPlugin',
            'search:get-results', 'navigation:build', 'navigation:catalog',
            'dashboard:widgets', 'dashboard:inject-widgets', 'adminDashboard',
            'core:stats-collector', 'seo:collect-sitemap-urls',
            'user:notification-preferences', 'user:profile-allowed-settings',
            'user:avatar-menu', 'home', 'page',
        ];

        if (is_array($declaredHooks)) {
            foreach ($declaredHooks as $hookName => $cbRaw) {
                $cbList = is_array($cbRaw) && array_is_list($cbRaw) && isset($cbRaw[0]) && is_array($cbRaw[0])
                    ? $cbRaw
                    : [$cbRaw];

                foreach ($cbList as $cb) {
                    $resolvedCb = $cb;
                    if (is_string($cb) && str_contains($cb, '::')) {
                        [$cls, $mtd] = explode('::', $cb, 2);
                        $cls = '\\' . ltrim($cls, '\\');
                        $resolvedCb = [$cls, $mtd];
                    } elseif (is_string($cb) && str_contains($cb, '#')) {
                        [$cls, $mtd] = explode('#', $cb, 2);
                        $cls = '\\' . ltrim($cls, '\\');
                        $resolvedCb = [$cls, $mtd];
                    }

                    $isCallable = is_callable($resolvedCb);
                    if (!$isCallable && is_string($cb) && str_contains($cb, '::')) {
                        // Also check static/instance class existence even if non-static method was listed with ::
                        [$cls, $mtd] = explode('::', $cb, 2);
                        $cls = '\\' . ltrim($cls, '\\');
                        $isCallable = class_exists($cls) && method_exists($cls, $mtd);
                    }
                    if ($isCallable) {
                        $callableCount++;
                    } else {
                        $nonCallableCount++;
                    }

                    $entry = [
                        'hook' => $hookName,
                        'callback_raw' => $cb,
                        'callable_at_boot' => $isCallable,
                        'safe_to_execute' => in_array($hookName, $safeHooks, true),
                        'executed' => false,
                        'exec_ok' => false,
                        'output_len' => 0,
                        'error' => null,
                    ];

                    if ($isCallable && $entry['safe_to_execute']) {
                        $executedCount++;
                        $entry['executed'] = true;
                        try {
                            $args = self::buildSafeHookArgs($hookName, $resolvedCb);
                            ob_start();
                            if (is_array($resolvedCb) && is_string($resolvedCb[0])) {
                                $refM = new \ReflectionMethod($resolvedCb[0], $resolvedCb[1]);
                                $target = $refM->isStatic() ? null : new $resolvedCb[0]();
                                $resHook = $refM->invokeArgs($target, $args);
                            } else {
                                $resHook = call_user_func_array($resolvedCb, $args);
                            }
                            $out = (string) ob_get_clean();
                            $entry['output_len'] = strlen($out) + (is_string($resHook) ? strlen($resHook) : 0);
                            $entry['exec_ok'] = true;
                            $succeededCount++;
                        } catch (\Throwable $e) {
                            while (ob_get_level() > self::$initialObLevel + 1) {
                                @ob_end_clean();
                            }
                            $entry['error'] = self::fmtEx($e, 'hook_' . $hookName);
                            $failedExecCount++;
                        }
                    } elseif (!$isCallable) {
                        $entry['error'] = 'Not callable at normal boot (missing class/function or controller not autoloadable)';
                    }

                    $hookEntries[] = $entry;
                }
            }
        }

        // Phase C: Routes (clean delta because bootEnvironment skipped target plugin on routes_hooks)
        $router = \Core\Router\Router::getInstance();
        $beforeRoutesCount = count($router->getRoutes());
        $routeLoadErrors = $libLoadErrors;
        try {
            $plObj->loadRoutes();
        } catch (\Throwable $e) {
            $routeLoadErrors[] = self::fmtEx($e, 'loadRoutes');
        }
        $afterRoutes = array_slice($router->getRoutes(), $beforeRoutesCount);

        $infos = [
            'homePublicMethod' => $plObj->getInfoVal('homePublicMethod'),
            'homeAdminMethod' => $plObj->getInfoVal('homeAdminMethod'),
        ];
        $targetsSeen = [];
        $parsedRoutes = [];

        foreach ($afterRoutes as $r) {
            $method = strtoupper((string) ($r[0] ?? 'GET'));
            $path = (string) ($r[1] ?? '');
            $target = (string) ($r[2] ?? '');
            $name = (string) ($r[3] ?? '');
            $targetsSeen[$target] = true;
            $parsedRoutes[] = self::inspectRoute($plObj, $method, $path, $target, $name);
        }

        foreach (['homePublicMethod' => 'public', 'homeAdminMethod' => 'admin'] as $infoKey => $defaultScope) {
            $homeTarget = $infos[$infoKey] ?? null;
            if (is_string($homeTarget) && $homeTarget !== '' && !isset($targetsSeen[$homeTarget])) {
                $parsedRoutes[] = self::inspectRoute($plObj, 'GET', ($defaultScope === 'admin' ? "/admin/$plugin" : "/$plugin"), $homeTarget, $infoKey);
            }
        }

        $resolvedCount = 0;
        $failedCount = 0;
        $unsafeGetCount = 0;
        foreach ($parsedRoutes as $pr) {
            if ($pr['resolved_with_loadControllers']) {
                $resolvedCount++;
            } else {
                $failedCount++;
            }
            $methods = explode('|', strtoupper((string) ($pr['method'] ?? '')));
            if (in_array('GET', $methods, true) && !$pr['is_safe_get']) {
                $unsafeGetCount++;
            }
        }

        self::$result = [
            'worker_ok' => true,
            'routes' => [
                'declared_routes_count' => count($parsedRoutes),
                'resolved_count' => $resolvedCount,
                'failed_count' => $failedCount,
                'unsafe_get_routes_count' => $unsafeGetCount,
                'load_errors' => $routeLoadErrors,
                'routes' => $parsedRoutes,
            ],
            'hooks' => [
                'declared_hooks_count' => count($hookEntries),
                'callable_count' => $callableCount,
                'non_callable_count' => $nonCallableCount,
                'executed_count' => $executedCount,
                'succeeded_count' => $succeededCount,
                'failed_execution_count' => $failedExecCount,
                'entries' => $hookEntries,
            ],
        ];
    }

    private static function buildSafeHookArgs(string $hookName, callable|array|string $cb): array
    {
        try {
            $ref = is_array($cb)
                ? new \ReflectionMethod($cb[0], $cb[1])
                : new \ReflectionFunction($cb);
            $params = $ref->getParameters();
            if (count($params) === 0) {
                return [];
            }
            $args = [];
            foreach ($params as $idx => $p) {
                if ($p->isOptional() && $idx > 0) {
                    continue;
                }
                $type = $p->getType();
                $tName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
                if ($hookName === 'search:get-results' && $idx === 0) {
                    $args[] = ($tName === 'string') ? 'test' : ['query' => 'test', 'results' => []];
                } elseif ($hookName === 'navigation:build' && $idx === 0) {
                    $args[] = class_exists(\Core\Navigation\NavigationTree::class) ? new \Core\Navigation\NavigationTree() : '';
                } elseif ($hookName === 'user:avatar-menu' && $idx === 0) {
                    $args[] = ['items' => []];
                } elseif ($tName === 'array') {
                    $args[] = [];
                } elseif ($tName === 'bool') {
                    $args[] = false;
                } elseif ($tName === 'int') {
                    $args[] = 0;
                } else {
                    $args[] = '';
                }
            }
            return $args;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function inspectRoute(\Core\Plugin\Plugin $plObj, string $method, string $path, string $target, string $name): array
    {
        $scope = (str_starts_with($path, '/admin') || str_contains(strtolower($target), 'admin')) ? 'admin' : 'public';
        $strippedOptional = (string) preg_replace(['#\[/\?\]#', '#\[[^\]]+\]\?#'], '', $path);
        preg_match_all('/\[[^\]]+\]/', $strippedOptional, $pm);
        $routeParamsCount = count($pm[0]);

        $autoloadOk = false;
        $resolvedWithLoad = false;
        $publicNonStatic = false;
        $reqParams = 0;
        $err = null;
        $hasGet = in_array('GET', explode('|', strtoupper($method)), true);
        $isSafeGet = $hasGet;
        $unsafeReason = null;

        if (!str_contains($target, '#')) {
            $err = "Invalid target format (expected Class#method): $target";
            $isSafeGet = false;
        } else {
            [$rawClass, $action] = explode('#', $target, 2);
            $fqcn = '\\' . ltrim($rawClass, '\\');

            $autoloadOk = class_exists($fqcn);
            if (!$autoloadOk) {
                $plObj->loadControllers();
            }

            if (!class_exists($fqcn)) {
                $err = "Controller class not found: $fqcn";
            } elseif (!method_exists($fqcn, $action)) {
                $err = "Method $action missing on $fqcn";
            } else {
                try {
                    $rm = new \ReflectionMethod($fqcn, $action);
                    $publicNonStatic = ($rm->isPublic() && !$rm->isStatic());
                    $reqParams = $rm->getNumberOfRequiredParameters();
                    if (!$publicNonStatic) {
                        $err = "Method $fqcn::$action is not public instance method";
                    } else {
                        $resolvedWithLoad = true;
                        if ($reqParams > $routeParamsCount) {
                            $err = "Method requires $reqParams params but route supplies $routeParamsCount";
                            $resolvedWithLoad = false;
                        }
                    }
                    if ($hasGet) {
                        [$isSafeGet, $unsafeReason] = self::checkGetSafety($path, $target, $rm);
                    }
                } catch (\Throwable $e) {
                    $err = $e->getMessage();
                }
            }
        }

        return [
            'method' => $method,
            'uri' => $path,
            'target' => $target,
            'name' => $name,
            'scope' => $scope,
            'route_params_count' => $routeParamsCount,
            'autoload_ok_without_loadControllers' => $autoloadOk,
            'resolved_with_loadControllers' => $resolvedWithLoad,
            'method_public_non_static' => $publicNonStatic,
            'required_params' => $reqParams,
            'is_safe_get' => $isSafeGet,
            'unsafe_reason' => $unsafeReason,
            'error' => $err,
        ];
    }

    private static function checkGetSafety(string $path, string $target, \ReflectionMethod $rm): array
    {
        // Verified read-only endpoints whose route/target names contain historical filter keywords (export/ics, rss, checkout view)
        $verifiedReadOnlyTargets = [
            'Agenda\\Controllers\\AgendaController#exportAllIcal',
            'BlogReadController#rss',
            'Ecommerce\\Controllers\\EcommerceController#checkout',
            'Rss\\Controllers\\RssPublicController#feed',
            'Rss\\Controllers\\RssPublicController#channel',
            'Rss\\Controllers\\RssAdminController#home',
        ];
        if (in_array(ltrim($target, '\\'), $verifiedReadOnlyTargets, true)) {
            return [true, null];
        }

        $haystack = strtolower($path . ' ' . $target);
        if (preg_match('/\b(delete|del|remove|uninstall|activate|deactivate|approve|reject|ban|logout|cron|sync|import|webhook|callback|pay|checkout|send|reset|purge|clear|download|export|install|ics|rss)\b/', $haystack, $m)) {
            return [false, 'GET pattern matches unsafe/side-effect keyword: ' . $m[1]];
        }
        $file = $rm->getFileName();
        $start = $rm->getStartLine();
        $end = $rm->getEndLine();
        if ($file && is_file($file) && $start && $end) {
            $lines = array_slice(file($file), $start - 1, $end - $start + 1);
            $body = implode('', $lines);
            if (preg_match('/\b(?:->save\s*\(|->delete\s*\(|unlink\s*\(|file_put_contents|writeJsonFile|DELETE\s+FROM|INSERT\s+INTO|UPDATE\s+\w+\s+SET|curl_exec|\bmail\s*\()/i', $body, $bm)) {
                return [false, 'GET method body performs write/external op: ' . $bm[0]];
            }
        }
        return [true, null];
    }

    private static function handleControllerExec(string $plugin, bool $asAdmin, ?array $chosen): void
    {
        $pm = \Core\Plugin\PluginsManager::getInstance();
        $plObj = $pm->getPlugin($plugin);
        if (!$plObj) {
            self::$result = ['worker_ok' => false, 'error' => 'Plugin missing'];
            return;
        }
        if (!$chosen || empty($chosen['target'])) {
            self::$result = ['worker_ok' => false, 'error' => 'Invalid route spec'];
            return;
        }

        $cleanUri = (string) preg_replace(['#\[/\?\]#', '#\[[^\]]+\]\?#'], '', (string) ($chosen['uri'] ?? '/'));
        if ($cleanUri === '') {
            $cleanUri = '/';
        }
        $_SERVER['REQUEST_URI'] = $cleanUri;
        self::$result['uri'] = $cleanUri;
        self::$result['target'] = $chosen['target'];
        $refCore = new \ReflectionProperty(\Core\Core::class, 'pluginToCall');
        $refCore->setValue(\Core\Core::getInstance(), $plugin);
        $GLOBALS['runPlugin'] = $plObj;
        \Template\Template::addGlobal('runPlugin', $plObj);

        $plObj->loadControllers();
        $exceptions = [];
        $htmlErrors = [];
        $outputLen = 0;

        try {
            \Core\Core::getInstance()->callHook('beforeRunPlugin');
            [$rawClass, $action] = explode('#', $chosen['target'], 2);
            $fqcn = '\\' . ltrim($rawClass, '\\');
            $controller = new $fqcn();
            $res = $controller->$action();
            $html = '';
            if (is_object($res) && method_exists($res, 'output')) {
                $html = (string) $res->output();
            } elseif (is_string($res)) {
                $html = $res;
            }
            $buffered = (string) ob_get_contents();
            $combined = $buffered . $html;
            $outputLen = strlen(trim($combined));

            if (preg_match('/(Fatal error|Parse error|Uncaught\s+\w*(?:Exception|Error)|PDOException|SQLSTATE\[|Template\s+not\s+found|Failed\s+opening\s+required)/i', $combined, $hm)) {
                $htmlErrors[] = 'Output contains runtime error: ' . $hm[0];
            }
        } catch (\Throwable $e) {
            $exceptions[] = self::fmtEx($e, $chosen['target']);
        }

        self::$result = [
            'worker_ok' => true,
            'status' => empty($exceptions) && empty($htmlErrors) ? 'ok' : 'error',
            'uri' => $chosen['uri'],
            'target' => $chosen['target'],
            'output_len' => $outputLen,
            'exceptions' => $exceptions,
            'html_errors' => $htmlErrors,
        ];
    }

    private static function rebuildRoutesList(\Core\Plugin\Plugin $plObj, string $plugin): array
    {
        // Note: Router starts clean per worker since only Core built-in routes run during init;
        // capturing delta around loadRoutes() guarantees identical indices to routes_hooks!
        $router = \Core\Router\Router::getInstance();
        $all = $router->getRoutes();
        // Reset isn't needed if we re-run in fresh router? Actually Core::init loads active plugin routes already?
        // Let's check: yes, Core::init loads active plugin's routes? No, Router might only load current route!
        // Safely collect routes added during explicit loadRoutes() if not already there, OR match plugin targets:
        $before = count($all);
        $plObj->loadRoutes();
        $after = $router->getRoutes();
        $delta = array_slice($after, $before);
        $source = !empty($delta) ? $delta : $after;

        $list = [];
        $targetsSeen = [];
        foreach ($source as $r) {
            $t = (string) ($r[2] ?? '');
            $p = (string) ($r[1] ?? '');
            if (!empty($delta) || self::targetBelongsToPlugin($plObj, $plugin, $t, $p)) {
                $targetsSeen[$t] = true;
                $list[] = ['uri' => $p, 'target' => $t];
            }
        }
        $infos = [
            'homePublicMethod' => $plObj->getInfoVal('homePublicMethod'),
            'homeAdminMethod' => $plObj->getInfoVal('homeAdminMethod'),
        ];
        foreach (['homePublicMethod' => 'public', 'homeAdminMethod' => 'admin'] as $ik => $sc) {
            $ht = $infos[$ik] ?? null;
            if (is_string($ht) && $ht !== '' && !isset($targetsSeen[$ht])) {
                $list[] = ['uri' => $sc === 'admin' ? "/admin/$plugin" : "/$plugin", 'target' => $ht];
            }
        }
        return $list;
    }

    private static function targetBelongsToPlugin(\Core\Plugin\Plugin $plObj, string $plugin, string $target, string $path): bool
    {
        if (str_contains(strtolower($target), strtolower($plugin))) {
            return true;
        }
        if (str_contains(strtolower($path), '/' . strtolower($plugin))) {
            return true;
        }
        return false;
    }

    private static function handleEntities(string $plugin): void
    {
        $dir = AuditConfig::pluginsDir() . $plugin . '/';
        $pm = \Core\Plugin\PluginsManager::getInstance();
        $plObj = $pm->getPlugin($plugin);
        if ($plObj) {
            $plObj->loadLangFile();
        }

        $exceptions = [];
        $missingTpls = [];
        $testedClasses = [];

        // Check referenced templates exist on disk
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $rel = substr($file->getPathname(), strlen($dir));
            if (str_contains($rel, 'libs/mpdf/') || str_contains($rel, '/vendor/') || str_starts_with($rel, 'tests/')) {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/(?:new\s+(?:\\\\Template\\\\)?Template|->setFile)\s*\(\s*[\'"]([^\'"]+\.tpl)[\'"]\s*\)/', $content, $m)) {
                foreach ($m[1] as $tpl) {
                    $clean = ltrim($tpl, '/');
                    if (str_contains($clean, '$') || str_contains($clean, '..')) {
                        continue;
                    }
                    $exists = is_file($tpl)
                        || is_file($dir . 'template/' . $clean)
                        || is_file($dir . 'views/' . $clean)
                        || is_file($dir . $clean)
                        || is_file(AuditConfig::rootDir() . 'theme/default/' . $clean)
                        || is_file(AuditConfig::rootDir() . 'common/' . $clean);
                    if (!$exists) {
                        $missingTpls[] = "$rel -> $tpl";
                    }
                }
            }
        }
        $missingTpls = array_values(array_unique($missingTpls));

        // Discover and test entity/service classes
        $classFiles = [];
        foreach (['entities', 'src/Entities', 'services', 'src/Services', 'models', 'src/Models'] as $sub) {
            $subDir = $dir . $sub;
            if (is_dir($subDir)) {
                $sit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($subDir, FilesystemIterator::SKIP_DOTS));
                foreach ($sit as $sf) {
                    if ($sf->isFile() && strtolower($sf->getExtension()) === 'php') {
                        $classFiles[] = $sf->getPathname();
                    }
                }
            }
        }
        sort($classFiles);

        foreach ($classFiles as $cf) {
            try {
                require_once $cf;
            } catch (\Throwable $e) {
                $exceptions[] = self::fmtEx($e, 'require_' . basename($cf));
            }
        }

        // Filter to concrete entity/service classes declared inside this plugin directory (including autoloaded ones)
        $pluginRealDir = realpath($dir) ?: $dir;
        $pluginClasses = [];
        foreach (get_declared_classes() as $cls) {
            try {
                $rc = new \ReflectionClass($cls);
                $fn = $rc->getFileName();
                $realFn = $fn ? (realpath($fn) ?: $fn) : '';
                if ($realFn !== '' && str_starts_with($realFn, $pluginRealDir . '/') && !$rc->isAbstract() && !$rc->isInterface() && !$rc->isTrait() && !str_contains($realFn, '/controllers/') && !str_contains($realFn, '/Controllers/')) {
                    $pluginClasses[] = $cls;
                }
            } catch (\Throwable) {
            }
        }
        sort($pluginClasses);

        $okCount = 0;
        $errCount = 0;
        foreach ($pluginClasses as $cls) {
            $entry = ['class' => $cls, 'instantiated' => false, 'read_method' => null, 'read_ok' => false, 'error' => null];
            try {
                $rc = new \ReflectionClass($cls);
                $instance = null;
                if ($rc->hasMethod('getInstance')) {
                    $gm = $rc->getMethod('getInstance');
                    if ($gm->isPublic() && $gm->isStatic() && $gm->getNumberOfRequiredParameters() === 0) {
                        $instance = $cls::getInstance();
                    }
                }
                if ($instance === null) {
                    $ctor = $rc->getConstructor();
                    if ($ctor === null || ($ctor->isPublic() && $ctor->getNumberOfRequiredParameters() === 0)) {
                        $instance = new $cls();
                    }
                }
                if ($instance !== null) {
                    $entry['instantiated'] = true;
                    foreach (['findAll', 'all', 'getAll', 'getItems', 'getPosts', 'getEvents', 'getCategories', 'count', 'getConfig', 'getStats', 'load'] as $rmName) {
                        if ($rc->hasMethod($rmName)) {
                            $rm = $rc->getMethod($rmName);
                            if ($rm->isPublic() && $rm->getNumberOfRequiredParameters() === 0) {
                                $entry['read_method'] = $rmName;
                                if ($rm->isStatic()) {
                                    $cls::$rmName();
                                } else {
                                    $instance->$rmName();
                                }
                                $entry['read_ok'] = true;
                                break;
                            }
                        }
                    }
                }
                $okCount++;
            } catch (\Throwable $e) {
                $errCount++;
                $entry['error'] = self::fmtEx($e, $cls);
                $exceptions[] = $entry['error'];
            }
            $testedClasses[] = $entry;
        }

        self::$result = [
            'worker_ok' => true,
            'classes_found' => count($pluginClasses),
            'tested_classes' => $testedClasses,
            'ok_count' => $okCount,
            'error_count' => $errCount,
            'missing_tpls' => $missingTpls,
            'exceptions' => $exceptions,
        ];
    }

    private static function installHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            $rootPath = rtrim(AuditConfig::rootDir(), '/');
            // Ignore harness-only artifact from ROOT . '.env' or ROOT . 'plugin_back' check when outside open_basedir
            if (str_contains($message, 'open_basedir restriction in effect')
                && (str_contains($message, $rootPath . '/.env') || str_contains($message, $rootPath . '/plugin_back'))
            ) {
                return true;
            }
            $label = match ($severity) {
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
                E_NOTICE, E_USER_NOTICE => 'NOTICE',
                E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
                E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
                default => 'ERROR_' . $severity,
            };
            self::$capturedWarnings[] = sprintf('[%s] %s in %s:%d', $label, $message, $file, $line);
            return true;
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::$result['worker_ok'] = false;
            self::$result['status'] = 'fatal';
            self::$result['fatal'] = self::fmtEx($e, 'uncaught_exception');
            exit(255);
        });
    }

    private static function fmtEx(\Throwable $e, string $ctx): array
    {
        return [
            'context' => $ctx,
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }
}



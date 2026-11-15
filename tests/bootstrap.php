<?php

declare(strict_types=1);

if (!defined('EVO_TEST_BOOTSTRAPPED')) {
    define('EVO_TEST_BOOTSTRAPPED', true);

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }
    if (!defined('ROOT')) {
        define('ROOT', dirname(__DIR__) . DS);
    }

    $storageMode = getenv('EVO_TEST_STORAGE') ?: 'json';
    $tmpDataDir = rtrim(sys_get_temp_dir(), DS) . DS . '299ko_test_' . $storageMode . '_' . getmypid() . '_' . bin2hex(random_bytes(3)) . DS;

    $dirs = [
        $tmpDataDir,
        $tmpDataDir . 'core',
        $tmpDataDir . 'core' . DS . 'auth',
        $tmpDataDir . 'core' . DS . 'page',
        $tmpDataDir . 'core' . DS . 'media',
        $tmpDataDir . 'core' . DS . 'settings',
        $tmpDataDir . 'core' . DS . 'extensions',
        $tmpDataDir . 'core' . DS . 'content',
        $tmpDataDir . 'upload',
        $tmpDataDir . 'upload' . DS . 'files',
        $tmpDataDir . 'plugin',
        $tmpDataDir . 'cache',
        $tmpDataDir . 'cache' . DS . 'sessions',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    define('DATA', $tmpDataDir);
    define('DATA_CORE', DATA . 'core' . DS);
    define('DATA_CORE_AUTH', DATA_CORE . 'auth' . DS);
    define('DATA_CORE_PAGE', DATA_CORE . 'page' . DS);
    define('DATA_CORE_MEDIA', DATA_CORE . 'media' . DS);
    define('DATA_CORE_SETTINGS', DATA_CORE . 'settings' . DS);
    define('DATA_CORE_EXTENSIONS', DATA_CORE . 'extensions' . DS);
    define('DATA_CORE_CONTENT', DATA_CORE . 'content' . DS);
    define('UPLOAD', DATA . 'upload' . DS);
    define('DATA_PLUGIN', DATA . 'plugin' . DS);
    define('CACHE', DATA . 'cache' . DS);

    if (!defined('BASE_PATH')) {
        define('BASE_PATH', '');
    }

    require_once ROOT . 'common/config.php';

    // Configuration de test isolée dans /tmp (jamais dans /data du dépôt)
    file_put_contents(DATA . 'config.json', json_encode([
        'siteName' => '299koEVO Test Suite',
        'siteDesc' => 'Tests automatisés',
        'siteUrl' => 'http://localhost',
        'siteLang' => 'fr',
        'theme' => 'default',
        'defaultPlugin' => 'page',
        'hideTitles' => false,
        'debug' => true,
        'cache_enabled' => false,
        'locales_enabled' => ['fr', 'en', 'de'],
        'fallback_locale' => 'fr',
        'translation_fallback' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $sqlStorage = ($storageMode === 'sqlite' || $storageMode === 'mysql') ? 'sql' : 'json';
    if ($storageMode === 'mysql') {
        $dbConfig = [
            'driver' => 'mysql',
            'host' => getenv('EVO_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('EVO_TEST_DB_PORT') ?: 3306),
            'dbname' => getenv('EVO_TEST_DB_NAME') ?: 'test_299koevo_audit',
            'user' => getenv('EVO_TEST_DB_USER') ?: 'test_299koevo_u',
            'password' => getenv('EVO_TEST_DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ];
    } else {
        $dbConfig = [
            'driver' => 'sqlite',
            'path' => DATA . 'test_suite.sqlite',
        ];
    }
    file_put_contents(DATA . 'env.json', json_encode([
        'database' => $dbConfig,
        'storage' => [
            'default' => $sqlStorage,
            'User' => 'json',
            'PageItem' => $sqlStorage,
            'ContentEntry' => $sqlStorage,
            'Revision' => $sqlStorage,
            'TranslationLink' => $sqlStorage,
        ],
    ], JSON_PRETTY_PRINT));

    file_put_contents(DATA_CORE_AUTH . 'groups.json', json_encode([
        [
            'id' => 1,
            'slug' => 'admin',
            'name' => 'Super Admin',
            'system' => true,
            'permissions' => ['*'],
        ],
        [
            'id' => 2,
            'slug' => 'editor',
            'name' => 'Éditeur',
            'system' => false,
            'permissions' => ['admin.access', 'pages.manage', 'pages.edit', 'pages.publish', 'content.view', 'content.create', 'content.edit', 'content.publish', 'content.revisions', 'api.access'],
        ],
        [
            'id' => 3,
            'slug' => 'contributor',
            'name' => 'Contributeur',
            'system' => false,
            'permissions' => ['admin.access', 'pages.edit', 'content.view', 'content.create', 'content.edit'],
        ],
        [
            'id' => 4,
            'slug' => 'member',
            'name' => 'Membre',
            'system' => false,
            'permissions' => ['content.view'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    file_put_contents(DATA_CORE_AUTH . 'users.json', json_encode([
        [
            'id' => 1,
            'email' => 'admin@test.local',
            'username' => 'admin',
            'password_hash' => password_hash('AdminPass123!', PASSWORD_DEFAULT),
            'token' => 'tok_admin_123456',
            'role' => '1',
            'status' => 'active',
            'permissions' => ['*'],
            'tokens' => ['api_admin_token_xyz'],
            'created_at' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'email' => 'editor@test.local',
            'username' => 'editor',
            'password_hash' => password_hash('EditorPass123!', PASSWORD_DEFAULT),
            'token' => 'tok_editor_123456',
            'role' => '2',
            'status' => 'active',
            'permissions' => [],
            'tokens' => ['api_editor_token_xyz'],
            'created_at' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 3,
            'email' => 'contrib@test.local',
            'username' => 'contrib',
            'password_hash' => password_hash('ContribPass123!', PASSWORD_DEFAULT),
            'token' => 'tok_contrib_123456',
            'role' => '3',
            'status' => 'active',
            'permissions' => [],
            'tokens' => ['api_contrib_token_xyz'],
            'created_at' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 4,
            'email' => 'member@test.local',
            'username' => 'member',
            'password_hash' => password_hash('MemberPass123!', PASSWORD_DEFAULT),
            'token' => 'tok_member_123456',
            'role' => '4',
            'status' => 'active',
            'permissions' => [],
            'tokens' => [],
            'created_at' => '2026-01-01 00:00:00',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    file_put_contents(DATA_CORE_PAGE . 'pages.json', json_encode([], JSON_PRETTY_PRINT));

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    require_once COMMON . 'Core/Autoloader/HybridAutoloader.php';
    new \Core\Autoloader\HybridAutoloader(
        [COMMON, PLUGINS],
        CACHE . 'autoloader_cache.php',
        true,
        false
    );

    \Core\Core::getInstance()->init();
    \Core\Security\Csrf::start();

    register_shutdown_function(static function () use ($tmpDataDir): void {
        if (is_dir($tmpDataDir)) {
            $it = new RecursiveDirectoryIterator($tmpDataDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
            }
            @rmdir($tmpDataDir);
        }
    });
}

if (!class_exists('EvoTestRunner', false)) {
    class EvoTestRunner
    {
        public static int $passed = 0;
        public static int $failed = 0;
        /** @var string[] */
        public static array $failures = [];

        public static function assertTrue(bool $cond, string $message): void
        {
            if ($cond) {
                self::$passed++;
            } else {
                self::$failed++;
                self::$failures[] = $message;
                fwrite(STDERR, "  [FAIL] {$message}\n");
            }
        }

        public static function assertFalse(bool $cond, string $message): void
        {
            self::assertTrue(!$cond, $message);
        }

        public static function assertEquals(mixed $expected, mixed $actual, string $message): void
        {
            if ($expected === $actual) {
                self::$passed++;
            } else {
                self::$failed++;
                $exp = var_export($expected, true);
                $act = var_export($actual, true);
                $msg = "{$message} (attendu: {$exp}, obtenu: {$act})";
                self::$failures[] = $msg;
                fwrite(STDERR, "  [FAIL] {$msg}\n");
            }
        }

        public static function assertContains(string $needle, string $haystack, string $message): void
        {
            self::assertTrue(str_contains($haystack, $needle), $message);
        }

        public static function runSuite(string $suiteName, array $tests): int
        {
            echo "=== Suite : {$suiteName} [storage=" . (getenv('EVO_TEST_STORAGE') ?: 'json') . "] ===\n";
            foreach ($tests as $name => $fn) {
                $beforeFail = self::$failed;
                try {
                    $fn();
                } catch (\Throwable $e) {
                    self::$failed++;
                    $msg = "{$name} : Exception " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
                    self::$failures[] = $msg;
                    fwrite(STDERR, "  [EXC] {$msg}\n");
                }
                $status = self::$failed === $beforeFail ? 'OK' : 'FAIL';
                echo "  [{$status}] {$name}\n";
            }
            return self::$failed === 0 ? 0 : 1;
        }

        public static function summaryAndExit(): int
        {
            echo "\nRésumé assertions : " . self::$passed . " réussies, " . self::$failed . " échouées.\n";
            return self::$failed === 0 ? 0 : 1;
        }
    }
}

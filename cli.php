<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied: CLI interface only.');
}

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', __DIR__ . DS);
define('BASE_PATH', '');

// Option --data-dir=... ou EVO_DATA_DIR pour pointer vers un dossier data isolé sans modifier la prod
$customDataDir = getenv('EVO_DATA_DIR') ?: '';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--data-dir=')) {
        $customDataDir = substr($arg, 11);
    }
}

if ($customDataDir !== '') {
    $norm = rtrim($customDataDir, DS) . DS;
    define('DATA', $norm);
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
}

require_once ROOT . 'common/config.php';
require_once COMMON . 'Core/Autoloader/HybridAutoloader.php';

if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}

new \Core\Autoloader\HybridAutoloader(
    [COMMON, PLUGINS],
    CACHE . 'autoloader_cache.php',
    false,
    false
);

// Enregistrement explicite du cœur si besoin
$core = \Core\Core::getInstance();
$core->init();

$app = new \Core\Cli\CliApplication();
exit($app->run($argv ?? ['cli.php', 'help']));

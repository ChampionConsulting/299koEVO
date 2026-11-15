<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$suites = [
    __DIR__ . '/Phase1ContentTest.php',
    __DIR__ . '/Phase2BlocksEditorialTest.php',
    __DIR__ . '/Phase3ApiCliTest.php',
    __DIR__ . '/Phase4TranslationTest.php',
    __DIR__ . '/PluginsCompatibilityTest.php',
];

$allPassed = true;
foreach ($suites as $suiteFile) {
    $res = require $suiteFile;
    if ((int) $res !== 0) {
        $allPassed = false;
    }
}

$exitCode = EvoTestRunner::summaryAndExit();
exit($allPassed && $exitCode === 0 ? 0 : 1);

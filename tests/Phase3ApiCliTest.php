<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Api\ApiManager;
use Core\Auth\User;
use Core\Cli\CliApplication;
use Core\Content\ContentTypeManager;
use Core\Responses\ApiResponse;
use Core\Security\Csrf;

$phase3Tests = [
    'ApiResponse normalisée et rétrocompatibilité' => function (): void {
        $ok = ApiResponse::success(['hello' => 'world'], ['page' => 1], 201);
        EvoTestRunner::assertEquals(201, $ok->getHttpCode(), 'Code HTTP 201 préservé');
        $decoded = json_decode($ok->output(), true);
        EvoTestRunner::assertTrue($decoded['success'] === true, 'Clé success = true');
        EvoTestRunner::assertEquals(['hello' => 'world'], $decoded['data'], 'Clé data exacte');
        EvoTestRunner::assertEquals(['page' => 1], $decoded['meta'], 'Clé meta exacte');
        EvoTestRunner::assertEquals([], $decoded['errors'], 'Clé errors vide');

        $err = ApiResponse::error('Accès refusé', 403);
        EvoTestRunner::assertEquals(403, $err->getHttpCode(), 'Code HTTP 403 préservé');
        $errDec = json_decode($err->output(), true);
        EvoTestRunner::assertFalse($errDec['success'], 'Clé success = false');
        EvoTestRunner::assertEquals('Accès refusé', $errDec['errors'][0]['message'] ?? '', 'Message d erreur présent');

        // Rétrocompatibilité de l'usage legacy sans helper
        $legacy = new ApiResponse();
        $legacy->status = ApiResponse::STATUS_SUCCESS;
        $legacy->body = ['legacy' => 1];
        EvoTestRunner::assertEquals('{"legacy":1}', $legacy->output(), 'Rétrocompatibilité ApiResponse directe conservée');
    },

    'API REST générique : Auth Bearer/Session, CSRF, RBAC, Validation, CRUD et Filtrage secrets' => function (): void {
        ContentTypeManager::register([
            'id' => 'event_api',
            'label' => 'Événement API',
            'config' => ['workflow' => true, 'revisions' => true, 'rest_enabled' => true],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'delete' => 'admin',
                'publish' => 'content.publish',
            ],
            'fields' => [
                ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true],
                ['name' => 'capacity', 'label' => 'Capacité', 'type' => 'integer', 'rules' => ['min' => 1]],
                ['name' => 'internal_note', 'label' => 'Note admin', 'type' => 'text', 'readPermission' => 'admin'],
            ],
        ]);

        $api = ApiManager::getInstance();

        // 1. Documentation des routes publique
        $routesResp = $api->dispatch('GET', '/api/v1/routes');
        EvoTestRunner::assertEquals(200, $routesResp->getHttpCode(), 'GET /api/v1/routes retourne 200');
        EvoTestRunner::assertTrue(count($routesResp->body['data']['routes'] ?? []) >= 7, 'Routes core documentées');

        // 2. Anonyme sur route protégée -> 401
        $anonPost = $api->dispatch('POST', '/api/v1/content/event_api', [], ['title' => 'Test']);
        EvoTestRunner::assertEquals(401, $anonPost->getHttpCode(), 'POST anonyme bloqué en 401');

        // 3. Bearer token invalide -> 401
        $badTok = $api->dispatch('POST', '/api/v1/content/event_api', [], ['title' => 'Test'], ['Authorization' => 'Bearer wrong_token']);
        EvoTestRunner::assertEquals(401, $badTok->getHttpCode(), 'Bearer token invalide bloqué en 401');

        // 4. Session cookie SANS token CSRF -> 403, puis AVEC X-CSRF-Token -> 201
        $prevSession = $_SESSION;
        $auth = \Core\Core::getInstance()->auth();
        $_SESSION['email'] = 'admin@test.local';
        $_SESSION['token'] = 'api_admin_token_xyz';
        $auth->resetCurrentUserCache();
        $noCsrf = $api->dispatch('POST', '/api/v1/content/event_api', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Sans CSRF'],
        ]);
        EvoTestRunner::assertEquals(403, $noCsrf->getHttpCode(), 'POST en session cookie sans CSRF bloqué en 403');

        $withCsrf = $api->dispatch('POST', '/api/v1/content/event_api', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Avec CSRF Session'],
        ], ['X-CSRF-Token' => Csrf::token()]);
        EvoTestRunner::assertEquals(201, $withCsrf->getHttpCode(), 'POST en session cookie avec X-CSRF-Token valide accepté en 201');
        if (!empty($withCsrf->body['data']['id'])) {
            $api->dispatch('DELETE', '/api/v1/content/event_api/' . $withCsrf->body['data']['id'], [], [], ['X-CSRF-Token' => Csrf::token()]);
        }
        $_SESSION = $prevSession;
        $auth->resetCurrentUserCache();

        // 5. Création via Bearer token contributeur (sans CSRF requis en Bearer) -> brouillon OK, mais validation 422 si invalide
        $invalidInput = $api->dispatch('POST', '/api/v1/content/event_api', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Conférence', 'capacity' => -5],
        ], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(422, $invalidInput->getHttpCode(), 'Validation capacité négative retourne 422');

        // Contributeur tente de publier directement -> 403
        $forbiddenPub = $api->dispatch('POST', '/api/v1/content/event_api', [], [
            'status' => 'published',
            'fields' => ['title' => 'Conférence'],
        ], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(403, $forbiddenPub->getHttpCode(), 'Contributeur bloqué en 403 s il tente published');

        // Éditeur crée un contenu publié via API Token
        $created = $api->dispatch('POST', '/api/v1/content/event_api', [], [
            'status' => 'published',
            'fields' => ['title' => 'Forum Local', 'capacity' => 150],
        ], ['Authorization' => 'Bearer api_editor_token_xyz']);
        EvoTestRunner::assertEquals(201, $created->getHttpCode(), 'Création via Bearer token retourne 201');
        $createdId = $created->body['data']['id'] ?? null;
        EvoTestRunner::assertTrue($createdId !== null, 'ID créé retourné dans data');

        // Lecture publique paginée & filtrée + champs restreints masqués
        $list = $api->dispatch('GET', '/api/v1/content/event_api', ['page' => '1', 'per_page' => '10']);
        EvoTestRunner::assertEquals(200, $list->getHttpCode(), 'GET liste retourne 200');
        EvoTestRunner::assertEquals(1, $list->body['meta']['total'] ?? 0, 'Total pagination exact');
        EvoTestRunner::assertFalse(array_key_exists('internal_note', $list->body['data'][0]['fields'] ?? []), 'Champ internal_note masqué en lecture publique');

        // Paramètre invalide sur GET list (page=abc -> 422)
        $badParam = $api->dispatch('GET', '/api/v1/content/event_api', ['page' => 'abc']);
        EvoTestRunner::assertEquals(422, $badParam->getHttpCode(), 'Paramètre page=abc rejeté en 422');

        // Filtrage automatique des clés sensibles dans une route custom de plugin
        $api->registerRoute([
            'name' => 'plugin-secret-check',
            'method' => 'GET',
            'pattern' => '/api/v1/test-sanitize/[a:slug]',
            'permission' => 'auth',
            'params' => ['mode' => ['type' => 'string', 'in' => ['safe', 'full']]],
            'handler' => fn(array $ctx) => [
                'slug' => $ctx['pathParams']['slug'],
                'password_hash' => 'SHOULD_BE_STRIPPED',
                'token' => 'SHOULD_BE_STRIPPED',
                'nested' => ['api_key' => 'SECRET', 'label' => 'Visible'],
            ],
        ]);
        $sanResp = $api->dispatch('GET', '/api/v1/test-sanitize/alpha', ['mode' => 'safe'], [], ['Authorization' => 'Bearer api_admin_token_xyz']);
        EvoTestRunner::assertEquals(200, $sanResp->getHttpCode(), 'Route custom plugin accessible avec Bearer admin');
        EvoTestRunner::assertFalse(isset($sanResp->body['data']['password_hash']), 'password_hash strippé automatiquement');
        EvoTestRunner::assertFalse(isset($sanResp->body['data']['token']), 'token strippé automatiquement');
        EvoTestRunner::assertFalse(isset($sanResp->body['data']['nested']['api_key']), 'api_key strippée récursivement');
        EvoTestRunner::assertEquals('Visible', $sanResp->body['data']['nested']['label'] ?? '', 'Données non sensibles préservées');

        // Admin supprime l'entrée via DELETE Bearer
        $delResp = $api->dispatch('DELETE', "/api/v1/content/event_api/{$createdId}", [], [], ['Authorization' => 'Bearer api_admin_token_xyz']);
        EvoTestRunner::assertEquals(200, $delResp->getHttpCode(), 'DELETE admin retourne 200');
    },

    'Application CLI : system:info, plugin:list, storage:check, routes:list, cache:clear' => function (): void {
        $cli = new CliApplication();
        $commands = $cli->getCommands();
        foreach (['help', 'system:info', 'plugin:list', 'storage:check', 'cache:clear', 'routes:list', 'test:run'] as $cmd) {
            EvoTestRunner::assertTrue(in_array($cmd, $commands, true), "Commande CLI {$cmd} enregistrée");
        }

        ob_start();
        $code1 = $cli->run(['cli.php', 'system:info', '--json']);
        $out1 = (string) ob_get_clean();
        EvoTestRunner::assertEquals(0, $code1, 'system:info --json exit 0');
        $info = json_decode($out1, true);
        EvoTestRunner::assertTrue(is_array($info) && isset($info['cms_version'], $info['php_version']), 'Sortie JSON valide pour system:info');

        ob_start();
        $code2 = $cli->run(['cli.php', 'routes:list', '--json']);
        $out2 = (string) ob_get_clean();
        EvoTestRunner::assertEquals(0, $code2, 'routes:list --json exit 0');
        $routesData = json_decode($out2, true);
        EvoTestRunner::assertTrue(count($routesData['api_routes'] ?? []) >= 7, 'routes:list inclut les routes REST');

        ob_start();
        $code3 = $cli->run(['cli.php', 'cache:clear', '--dry-run']);
        $out3 = (string) ob_get_clean();
        EvoTestRunner::assertEquals(0, $code3, 'cache:clear --dry-run exit 0');
        EvoTestRunner::assertContains('[DRY-RUN]', $out3, 'cache:clear --dry-run affiché');

        ob_start();
        $code4 = $cli->run(['cli.php', 'commande:inexistante']);
        $out4 = (string) ob_get_clean();
        EvoTestRunner::assertEquals(1, $code4, 'Commande CLI inexistante retourne code 1');
    },
];

$exit = EvoTestRunner::runSuite('Phase 3 — API REST normalisée & CLI', $phase3Tests);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'Phase3ApiCliTest.php') {
    exit(EvoTestRunner::summaryAndExit());
}
return $exit;

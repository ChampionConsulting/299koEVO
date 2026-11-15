<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Api\ApiManager;
use Core\Auth\User;
use Core\Content\ContentTypeManager;
use Core\Content\Controllers\ContentAdminController;
use Core\Core;
use Core\Database;
use Core\Plugin\PluginsManager;
use Core\Router\Router;
use Core\Storage\Dialects\MysqlDialect;
use Core\Storage\Dialects\PgsqlDialect;
use Core\Storage\Dialects\SqliteDialect;
use Core\Translation\TranslationManager;

$contradictoryAndPhase5Tests = [
    'Back-office Content Types : routes, persistance JSON, reset, suppression et constructeur visuel' => function (): void {
        $router = Router::getInstance();
        EvoTestRunner::assertTrue(is_string($router->generate('content-admin-index')), 'Route /admin/content enregistrée');
        EvoTestRunner::assertTrue(is_string($router->generate('content-admin-type-new')), 'Route /admin/content/type/new enregistrée');
        EvoTestRunner::assertTrue(is_string($router->generate('content-admin-type-save')), 'Route /admin/content/type/save enregistrée');

        $ctm = ContentTypeManager::getInstance();
        $saved = $ctm->saveCustomType([
            'id' => 'bo_association',
            'label' => 'Association BO',
            'description' => 'Annuaire géré depuis le back-office',
            'config' => ['blocks' => true, 'workflow' => true, 'revisions' => true, 'translatable' => true],
            'fields' => [
                ['name' => 'title', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ],
        ]);
        EvoTestRunner::assertTrue($saved instanceof \Core\Content\ContentTypeDefinition && $saved->id === 'bo_association', 'Sauvegarde d\'un type de contenu personnalisé en JSON réussie');
        EvoTestRunner::assertTrue($ctm->isPersistedType('bo_association'), 'isPersistedType retourne true');

        // Rechargement réel depuis le disque après réinitialisation du cache en mémoire
        ContentTypeManager::reset();
        $ctm = ContentTypeManager::getInstance();
        $reloaded = $ctm->get('bo_association');
        EvoTestRunner::assertTrue($reloaded !== null, 'Le type personnalisé se recharge automatiquement après reset()');
        EvoTestRunner::assertEquals('Association BO', $reloaded?->label, 'Label restauré fidèlement');

        // Vérification contrôleur back-office réel sous session Admin
        $prevSession = $_SESSION;
        $core = Core::getInstance();
        $refProp = new \ReflectionProperty(Core::class, 'pluginToCall');
        $refProp->setAccessible(true);
        $prevPluginToCall = $refProp->getValue($core);
        try {
            $_SESSION['email'] = 'admin@test.local';
            $_SESSION['token'] = 'api_admin_token_xyz';
            $core->auth()->resetCurrentUserCache();
            $refProp->setValue($core, 'content');

            $ctrl = new ContentAdminController();
            $htmlIndex = $ctrl->index()->output();
            EvoTestRunner::assertContains('Association BO', $htmlIndex, 'ContentAdminController::index affiche le type enregistré');

            $htmlEditType = $ctrl->editType('bo_association')->output();
            EvoTestRunner::assertContains('bo_association', $htmlEditType, 'ContentAdminController::editType charge le type existant');
        } finally {
            $refProp->setValue($core, $prevPluginToCall);
            $_SESSION = $prevSession;
            $core->auth()->resetCurrentUserCache();
        }

        // Un type enregistré uniquement en PHP ne peut pas être supprimé via deleteCustomType()
        ContentTypeManager::register([
            'id' => 'php_code_only_type',
            'label' => 'Code Only',
            'fields' => [['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true]],
        ]);
        EvoTestRunner::assertFalse($ctm->deleteCustomType('php_code_only_type'), 'deleteCustomType refuse un type non persisté en JSON');
        EvoTestRunner::assertTrue($ctm->deleteCustomType('bo_association'), 'deleteCustomType supprime le type persisté');
        EvoTestRunner::assertEquals(null, $ctm->get('bo_association'), 'Type supprimé retiré du registre');

        // Constructeur visuel typé présent dans les deux éditeurs (pages + entrées)
        $pageTpl = (string) file_get_contents(ROOT . 'admin/templates/page/edit.tpl');
        $entryTpl = (string) file_get_contents(ROOT . 'admin/templates/content/edit_entry.tpl');
        EvoTestRunner::assertContains('renderTypedFields', $pageTpl, 'page/edit.tpl utilise les champs visuels typés');
        EvoTestRunner::assertContains("blk.type === 'gallery'", $pageTpl, 'page/edit.tpl gère le bloc galerie visuellement');
        EvoTestRunner::assertContains("blk.type === 'columns'", $pageTpl, 'page/edit.tpl gère le bloc colonnes visuellement');
        EvoTestRunner::assertContains('renderTypedFields', $entryTpl, 'content/edit_entry.tpl utilise les champs visuels typés');
    },

    'Durcissement API : liste blanche api_exposed_fields, api_writable, api_readable et allowedOutputKeys' => function (): void {
        $admin = User::findPK(1);
        ContentTypeManager::register([
            'id' => 'hardened_doc',
            'label' => 'Document Durci',
            'config' => ['workflow' => true, 'rest_enabled' => true],
            'api_exposed_fields' => ['title', 'summary', 'readonly_score'],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'edit_others' => 'content.edit_others',
                'delete' => 'admin',
                'publish' => 'content.publish',
            ],
            'fields' => [
                ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true],
                ['name' => 'summary', 'label' => 'Résumé', 'type' => 'text'],
                ['name' => 'readonly_score', 'label' => 'Score', 'type' => 'integer', 'api_writable' => false],
                ['name' => 'secret_code', 'label' => 'Secret', 'type' => 'text', 'api_readable' => false, 'api_writable' => false],
                ['name' => 'unlisted_internal', 'label' => 'Hors whitelist', 'type' => 'text'],
            ],
        ]);

        $api = ApiManager::getInstance();

        // Tentative d'écriture sur un champ api_writable=false -> 422
        $badWrite1 = $api->dispatch('POST', '/api/v1/content/hardened_doc', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Doc', 'readonly_score' => 999],
        ], ['Authorization' => 'Bearer api_admin_token_xyz']);
        EvoTestRunner::assertEquals(422, $badWrite1->getHttpCode(), 'Écriture API sur champ api_writable=false rejetée en 422');

        // Tentative d'écriture sur secret_code (api_writable=false) -> 422
        $badWrite2 = $api->dispatch('POST', '/api/v1/content/hardened_doc', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Doc', 'secret_code' => 'bypass'],
        ], ['Authorization' => 'Bearer api_admin_token_xyz']);
        EvoTestRunner::assertEquals(422, $badWrite2->getHttpCode(), 'Écriture API sur champ secret_code (api_writable=false) rejetée en 422');

        // Création interne côté serveur avec valeurs confidentielles
        $created = ContentTypeManager::getInstance()->createEntry('hardened_doc', [
            'status' => 'published',
            'fields' => [
                'title' => 'Rapport Public',
                'summary' => 'Résumé officiel',
                'readonly_score' => 42,
                'secret_code' => 'TOP_SECRET_99',
                'unlisted_internal' => 'NE_DOIT_PAS_FUITER',
            ],
        ], $admin);
        EvoTestRunner::assertTrue($created['success'], 'Création interne réussie');
        $entryId = $created['entry']->getId();

        // Lecture API : seuls les champs de api_exposed_fields & api_readable=true sortent
        $getResp = $api->dispatch('GET', "/api/v1/content/hardened_doc/{$entryId}");
        EvoTestRunner::assertEquals(200, $getResp->getHttpCode(), 'GET public retourne 200');
        $fields = $getResp->body['data']['fields'] ?? [];
        EvoTestRunner::assertEquals('Rapport Public', $fields['title'] ?? '', 'Champ whiteliste title exposé');
        EvoTestRunner::assertFalse(array_key_exists('secret_code', $fields), 'Champ api_readable=false jamais exposé');
        EvoTestRunner::assertFalse(array_key_exists('unlisted_internal', $fields), 'Champ hors api_exposed_fields jamais exposé');

        // Liste blanche de route + filtrage récursif regex de clés sensibles
        $sanitized = $api->sanitizeOutputData([
            'id' => 10,
            'public' => [
                'name' => 'OK',
                'smtp_credentials' => 'smtp_pass',
                'payment_configuration' => ['stripe' => 'sk_live_123'],
                'webhook_signature' => 'whsec_xxx',
                'private_data' => 'hidden',
                'internal_note' => 'admin only',
            ],
            'extra_unlisted' => 'filtered_by_top_whitelist',
        ], ['id', 'public']);

        EvoTestRunner::assertFalse(isset($sanitized['extra_unlisted']), 'Clé top-level hors allowedOutputKeys retirée');
        EvoTestRunner::assertFalse(isset($sanitized['public']['smtp_credentials']), 'smtp_credentials filtré par regex');
        EvoTestRunner::assertFalse(isset($sanitized['public']['payment_configuration']), 'payment_configuration filtré par regex');
        EvoTestRunner::assertFalse(isset($sanitized['public']['webhook_signature']), 'webhook_signature filtré par regex');
        EvoTestRunner::assertFalse(isset($sanitized['public']['private_data']), 'private_data filtré par regex');
        EvoTestRunner::assertFalse(isset($sanitized['public']['internal_note']), 'internal_note filtré par regex');
        EvoTestRunner::assertEquals('OK', $sanitized['public']['name'] ?? '', 'Donnée publique conservée');
    },

    'Durcissement API : tokens Bearer hashés SHA-256, expiration et révocation' => function (): void {
        $api = ApiManager::getInstance();
        $contrib = User::findPK(3);
        $rawToken = $api->issueToken($contrib, 'test-scoped', 3600);
        EvoTestRunner::assertTrue(strlen($rawToken) >= 32, 'Token généré de longueur suffisante');

        $reloaded = User::findPK(3);
        $rawStored = json_encode($reloaded->tokens);
        EvoTestRunner::assertFalse(str_contains((string) $rawStored, $rawToken), 'Token jamais stocké en clair dans User::tokens');
        EvoTestRunner::assertContains(hash('sha256', $rawToken), (string) $rawStored, 'Empreinte SHA-256 présente dans User::tokens');

        // Requête avec le Bearer hashé valide
        $okResp = $api->dispatch('GET', '/api/v1/content/hardened_doc', [], [], ['Authorization' => 'Bearer ' . $rawToken]);
        EvoTestRunner::assertEquals(200, $okResp->getHttpCode(), 'Bearer token hashé SHA-256 accepté');

        // Révocation immédiate -> 401
        EvoTestRunner::assertTrue($api->revokeToken($reloaded, $rawToken), 'Révocation retournant true');
        $revokedResp = $api->dispatch('GET', '/api/v1/content/hardened_doc', ['status' => 'draft'], [], ['Authorization' => 'Bearer ' . $rawToken]);
        // /api/v1/content/... requiert auth pour écriture ; testons une route strictement 'auth'
        $api->registerRoute([
            'name' => 'test-auth-ping',
            'method' => 'GET',
            'pattern' => '/api/v1/test-auth-ping',
            'permission' => 'auth',
            'handler' => fn() => ['pong' => true],
        ]);
        $revokedAuth = $api->dispatch('GET', '/api/v1/test-auth-ping', [], [], ['Authorization' => 'Bearer ' . $rawToken]);
        EvoTestRunner::assertEquals(401, $revokedAuth->getHttpCode(), 'Token révoqué rejeté en HTTP 401');

        // Token expiré -> 401
        $expiredTok = $api->issueToken($reloaded, 'expired-check', 3600);
        $reloaded2 = User::findPK(3);
        $tokens = $reloaded2->tokens;
        $expHash = hash('sha256', $expiredTok);
        foreach ($tokens as &$entry) {
            if (is_array($entry) && ($entry['token_hash'] ?? '') === $expHash) {
                $entry['expires_at'] = time() - 120;
            }
        }
        unset($entry);
        $reloaded2->tokens = $tokens;
        $reloaded2->save();

        $expiredResp = $api->dispatch('GET', '/api/v1/test-auth-ping', [], [], ['Authorization' => 'Bearer ' . $expiredTok]);
        EvoTestRunner::assertEquals(401, $expiredResp->getHttpCode(), 'Token expiré rejeté en HTTP 401');
        $api->revokeToken($reloaded2, $expiredTok);
    },

    'Durcissement API : anti-IDOR (404 brouillon autrui, 403 écriture/suppression autrui) et content.edit_others' => function (): void {
        $api = ApiManager::getInstance();

        // Contributeur (User 3) crée son propre brouillon
        $c1 = $api->dispatch('POST', '/api/v1/content/hardened_doc', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Brouillon Contributeur'],
        ], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(201, $c1->getHttpCode(), 'Contributeur crée son brouillon');
        $contribDraftId = $c1->body['data']['id'];

        // Éditeur crée un brouillon ET un contenu publié
        $e1 = $api->dispatch('POST', '/api/v1/content/hardened_doc', [], [
            'status' => 'draft',
            'fields' => ['title' => 'Brouillon Editeur'],
        ], ['Authorization' => 'Bearer api_editor_token_xyz']);
        EvoTestRunner::assertEquals(201, $e1->getHttpCode(), 'Éditeur crée son brouillon');
        $editorDraftId = $e1->body['data']['id'];

        ContentTypeManager::register([
            'id' => 'idor_open_del',
            'label' => 'IDOR Open Delete',
            'config' => ['workflow' => true, 'rest_enabled' => true],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'edit_others' => 'content.edit_others',
                'delete' => 'content.edit',
                'publish' => 'content.publish',
            ],
            'fields' => [['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true]],
        ]);
        $ePub = $api->dispatch('POST', '/api/v1/content/idor_open_del', [], [
            'status' => 'published',
            'fields' => ['title' => 'Article Publié Editeur'],
        ], ['Authorization' => 'Bearer api_editor_token_xyz']);
        EvoTestRunner::assertEquals(201, $ePub->getHttpCode(), 'Éditeur publie son article');
        $editorPubId = $ePub->body['data']['id'];

        // Contributeur ne doit PAS voir le brouillon de l'éditeur (404)
        $idorGet = $api->dispatch('GET', "/api/v1/content/hardened_doc/{$editorDraftId}", [], [], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(404, $idorGet->getHttpCode(), 'Anti-IDOR GET brouillon d\'autrui retourne 404');

        // Contributeur ne doit PAS pouvoir modifier le brouillon de l'éditeur (403)
        $idorPut = $api->dispatch('PUT', "/api/v1/content/hardened_doc/{$editorDraftId}", [], [
            'fields' => ['title' => 'Hijack Attempt'],
        ], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(403, $idorPut->getHttpCode(), 'Anti-IDOR PUT sur brouillon d\'autrui retourne 403');

        // Même avec delete=content.edit, Contributeur ne doit PAS pouvoir supprimer le contenu d'autrui (403)
        $idorDel = $api->dispatch('DELETE', "/api/v1/content/idor_open_del/{$editorPubId}", [], [], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(403, $idorDel->getHttpCode(), 'Anti-IDOR DELETE sur entrée publiée d\'autrui retourne 403');

        // Contributeur peut modifier son PROPRE brouillon (200)
        $ownPut = $api->dispatch('PUT', "/api/v1/content/hardened_doc/{$contribDraftId}", [], [
            'fields' => ['title' => 'Brouillon Contributeur Mis à Jour'],
        ], ['Authorization' => 'Bearer api_contrib_token_xyz']);
        EvoTestRunner::assertEquals(200, $ownPut->getHttpCode(), 'Auteur propriétaire autorisé à modifier son propre brouillon');

        // Admin (qui dispose de content.edit_others via *) peut modifier le brouillon de l'éditeur (200)
        $adminPut = $api->dispatch('PUT', "/api/v1/content/hardened_doc/{$editorDraftId}", [], [
            'fields' => ['title' => 'Relu par Admin'],
        ], ['Authorization' => 'Bearer api_admin_token_xyz']);
        EvoTestRunner::assertEquals(200, $adminPut->getHttpCode(), 'Rôle privilégié (content.edit_others) autorisé');
    },

    'Durcissement API : rate limiting retournant HTTP 429' => function (): void {
        $api = ApiManager::getInstance();
        $api->registerRoute([
            'name' => 'test-rate-limit',
            'method' => 'GET',
            'pattern' => '/api/v1/test-ratelimit',
            'permission' => 'public',
            'rateLimit' => ['max' => 2, 'window' => 60],
            'handler' => fn() => ['ok' => true],
        ]);
        $api->resetRateLimits();

        $r1 = $api->dispatch('GET', '/api/v1/test-ratelimit');
        $r2 = $api->dispatch('GET', '/api/v1/test-ratelimit');
        $r3 = $api->dispatch('GET', '/api/v1/test-ratelimit');
        EvoTestRunner::assertEquals(200, $r1->getHttpCode(), 'Appel 1 sous quota -> 200');
        EvoTestRunner::assertEquals(200, $r2->getHttpCode(), 'Appel 2 sous quota -> 200');
        EvoTestRunner::assertEquals(429, $r3->getHttpCode(), 'Dépassement de quota -> HTTP 429');
        $api->resetRateLimits();
    },

    'Multilingue réel : unicité (type, locale, slug), findBySlug, promotion is_default et balise canonical' => function (): void {
        $admin = User::findPK(1);
        ContentTypeManager::register([
            'id' => 'i18n_guide',
            'label' => 'Guide multilingue',
            'config' => ['translatable' => true, 'workflow' => true, 'rest_enabled' => true],
            'permissions' => ['view' => 'public', 'create' => 'content.create', 'edit' => 'content.edit', 'publish' => 'content.publish'],
            'fields' => [
                ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true, 'translatable' => true],
            ],
        ]);
        $ctm = ContentTypeManager::getInstance();

        $fr = $ctm->createEntry('i18n_guide', [
            'locale' => 'fr',
            'slug' => 'guide-pratique',
            'status' => 'published',
            'fields' => ['title' => 'Guide Pratique FR'],
        ], $admin);
        EvoTestRunner::assertTrue($fr['success'], 'Création FR slug guide-pratique acceptée');

        // Même slug autorisé dans une locale différente ('en')
        $en = $ctm->createEntry('i18n_guide', [
            'locale' => 'en',
            'slug' => 'guide-pratique',
            'status' => 'published',
            'fields' => ['title' => 'Practical Guide EN'],
        ], $admin);
        EvoTestRunner::assertTrue($en['success'], 'Même slug guide-pratique autorisé dans une autre locale (en)');

        // Collision de slug dans la MÊME locale ('fr') rejetée
        $frDup = $ctm->createEntry('i18n_guide', [
            'locale' => 'fr',
            'slug' => 'guide-pratique',
            'status' => 'published',
            'fields' => ['title' => 'Doublon FR'],
        ], $admin);
        EvoTestRunner::assertFalse($frDup['success'], 'Collision de slug dans la même locale rejetée');

        $apiDup = ApiManager::getInstance()->dispatch('POST', '/api/v1/content/i18n_guide', [], [
            'locale' => 'fr',
            'slug' => 'guide-pratique',
            'status' => 'published',
            'fields' => ['title' => 'Doublon API'],
        ], ['Authorization' => 'Bearer api_admin_token_xyz']);
        EvoTestRunner::assertEquals(422, $apiDup->getHttpCode(), 'Collision de slug via API retourne HTTP 422');

        // Résolution par locale exacte via findBySlug
        $foundFr = $ctm->findBySlug('i18n_guide', 'guide-pratique', 'fr');
        $foundEn = $ctm->findBySlug('i18n_guide', 'guide-pratique', 'en');
        EvoTestRunner::assertEquals((string) $fr['entry']->getId(), (string) ($foundFr?->getId() ?? ''), 'findBySlug résout l\'entrée FR');
        EvoTestRunner::assertEquals((string) $en['entry']->getId(), (string) ($foundEn?->getId() ?? ''), 'findBySlug résout l\'entrée EN');

        // Liaison de traduction + suppression de la variante par défaut -> nettoyage + promotion is_default
        $tm = TranslationManager::getInstance();
        $idFr = (string) $fr['entry']->getId();
        $idEn = (string) $en['entry']->getId();
        $groupId = $tm->linkEntities('content:i18n_guide', ['fr' => $idFr, 'en' => $idEn]);
        EvoTestRunner::assertTrue($groupId !== '', 'Lien multilingue créé');

        $ctm->deleteEntry('i18n_guide', $idFr, $admin);
        $afterGroup = $tm->getTranslations('content:i18n_guide', $groupId);
        EvoTestRunner::assertFalse(isset($afterGroup['fr']), 'Lien de l\'entité supprimée nettoyé');
        EvoTestRunner::assertTrue(isset($afterGroup['en']), 'Variante EN préservée après suppression FR');
        EvoTestRunner::assertTrue($afterGroup['en']->isDefault(), 'La variante restante est promue is_default=true');

        // Balise canonical SEO
        $canonicalHtml = $tm->renderCanonicalTag('https://example.org/fr/guide-pratique');
        EvoTestRunner::assertContains('<link rel="canonical" href="https://example.org/fr/guide-pratique"', $canonicalHtml, 'renderCanonicalTag produit la balise HTML canonical attendue');
    },

    'Phase 5 : Dialectes SQL harmonisés, DDL SQLite LocalFeed/Gamification, 299search hooks et Dons edit/save' => function (): void {
        // 1. Dialectes SQL
        $sqlite = new SqliteDialect();
        $mysql = new MysqlDialect();
        $pgsql = new PgsqlDialect();

        $sSql = $sqlite->getCreateTableSql('items', 'id', ['amount' => 'decimal', 'ratio' => 'float']);
        $mSql = $mysql->getCreateTableSql('items', 'id', ['amount' => 'decimal', 'ratio' => 'float']);
        $pSql = $pgsql->getCreateTableSql('items', 'id', ['amount' => 'decimal', 'ratio' => 'float']);

        EvoTestRunner::assertContains('CREATE TABLE IF NOT EXISTS items', $sSql, 'SqliteDialect utilise CREATE TABLE IF NOT EXISTS');
        EvoTestRunner::assertContains('REAL', $sSql, 'SqliteDialect mappe decimal/float en REAL');
        EvoTestRunner::assertContains('CREATE INDEX IF NOT EXISTS', $sqlite->getIndexSql('items', 'amount'), 'SqliteDialect utilise CREATE INDEX IF NOT EXISTS');

        EvoTestRunner::assertContains('CREATE TABLE IF NOT EXISTS `items`', $mSql, 'MysqlDialect utilise CREATE TABLE IF NOT EXISTS');
        EvoTestRunner::assertContains('DECIMAL(12,4)', $mSql, 'MysqlDialect mappe decimal en DECIMAL(12,4)');
        EvoTestRunner::assertContains('DOUBLE', $mSql, 'MysqlDialect mappe float en DOUBLE');

        EvoTestRunner::assertContains('CREATE TABLE IF NOT EXISTS "items"', $pSql, 'PgsqlDialect utilise CREATE TABLE IF NOT EXISTS');
        EvoTestRunner::assertContains('NUMERIC(12,4)', $pSql, 'PgsqlDialect mappe decimal en NUMERIC(12,4)');
        EvoTestRunner::assertContains('DOUBLE PRECISION', $pSql, 'PgsqlDialect mappe float en DOUBLE PRECISION');
        EvoTestRunner::assertContains('CREATE INDEX IF NOT EXISTS', $pgsql->getIndexSql('items', 'amount'), 'PgsqlDialect utilise CREATE INDEX IF NOT EXISTS');

        // 2. LocalFeed & Gamification sous SQLite (Database utilise test_suite.sqlite dans env.json)
        $db = Database::getInstance();
        EvoTestRunner::assertEquals('sqlite', $db->getDriverName(), 'Driver SQLite actif pour les DDL plugins');

        require_once PLUGINS . 'LocalFeed/LocalFeed.php';
        $lfInstalled = \LocalFeed\LocalFeed::install();
        EvoTestRunner::assertTrue($lfInstalled, 'LocalFeed::install() réussit sous SQLite');
        foreach (['poi_posts', 'poi_post_likes', 'poi_post_shares', 'poi_post_comments'] as $tbl) {
            $exists = $db->query($sqlite->tableExists($tbl));
            EvoTestRunner::assertTrue(!empty($exists), "Table SQLite {$tbl} créée par LocalFeed");
        }

        require_once PLUGINS . 'gamification/services/PointsService.php';
        $pointsSvc = new \Gamification\Services\PointsService();
        $gpExists = $db->query($sqlite->tableExists('gamification_points'));
        EvoTestRunner::assertTrue(!empty($gpExists), 'Table SQLite gamification_points créée par PointsService');
        $pointsSvc->addPoints(999, 25, 'test_phase5');
        EvoTestRunner::assertEquals(25, $pointsSvc->getUserPoints(999), 'PointsService lit et écrit sous SQLite sans erreur');

        // 3. 299search : exécution du hook search:get-results + recherche ContentEntry publiés
        $admin = User::findPK(1);
        ContentTypeManager::register([
            'id' => 'asso_search',
            'label' => 'Association Recherche',
            'config' => ['rest_enabled' => true],
            'permissions' => ['view' => 'public', 'create' => 'content.create', 'edit' => 'content.edit', 'publish' => 'content.publish'],
            'fields' => [['name' => 'title', 'label' => 'Nom', 'type' => 'text', 'required' => true]],
        ]);
        ContentTypeManager::getInstance()->createEntry('asso_search', [
            'status' => 'published',
            'slug' => 'collectif-solidaire-test',
            'fields' => ['title' => 'Collectif Solidaire Démo'],
        ], $admin);

        $hookEnabled = true;
        Core::getInstance()->addHook('search:get-results', static function (array $data) use (&$hookEnabled): array {
            if (!$hookEnabled) {
                return $data;
            }
            $data['results'][] = [
                'title' => 'Atelier Solidaire Hook',
                'excerpt' => 'Résultat injecté par un plugin via search:get-results',
                'url' => '/plugin-demo/atelier-solidaire',
                'source' => 'plugin',
                'source_label' => 'Annuaire Solidaire',
                'icon' => 'fa-solid fa-star',
            ];
            return $data;
        });

        require_once PLUGINS . '299search/param/routes.php';
        require_once PLUGINS . '299search/controllers/SearchController.php';
        $prevGet = $_GET;
        $prevServer = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/search?q=Solidaire';
            $_GET = ['q' => 'Solidaire', 'type' => 'all'];
            $searchCtrl = new \Search299\Controllers\SearchController();
            $searchHtml = $searchCtrl->index()->output();
            EvoTestRunner::assertContains('Collectif Solidaire Démo', $searchHtml, '299search remonte les ContentEntry publiés');
            EvoTestRunner::assertContains('Atelier Solidaire Hook', $searchHtml, '299search déclenche et affiche le hook search:get-results');
        } finally {
            $hookEnabled = false;
            $_GET = $prevGet;
            $_SERVER = $prevServer;
        }

        // 4. Dons : edit() et save() avec contrôle Admin + CSRF
        require_once PLUGINS . 'dons/entities/Don.php';
        require_once PLUGINS . 'dons/entities/DonManager.php';
        require_once PLUGINS . 'dons/param/routes.php';
        require_once PLUGINS . 'dons/controllers/DonAdminController.php';

        EvoTestRunner::assertTrue(method_exists(\Dons\Controllers\DonAdminController::class, 'edit'), 'DonAdminController::edit() existe');
        EvoTestRunner::assertTrue(method_exists(\Dons\Controllers\DonAdminController::class, 'save'), 'DonAdminController::save() existe');
        EvoTestRunner::assertTrue(is_file(PLUGINS . 'dons/template/admin-edit.tpl'), 'Template dons admin-edit.tpl présent');

        $pm = PluginsManager::getInstance();
        $pm->installPlugin('dons', true);
        $donsPlugin = $pm->getPlugin('dons');
        if ($donsPlugin) {
            // Synchroniser la configuration complète depuis DATA_PLUGIN/dons/config.json
            $cfgFile = DATA_PLUGIN . 'dons' . DS . 'config.json';
            $installedCfg = json_decode((string) @file_get_contents($cfgFile), true);
            if (!is_array($installedCfg)) {
                $installedCfg = [];
            }
            $installedCfg['activate'] = 1;
            $refCfg = new \ReflectionProperty(get_class($donsPlugin), 'config');
            $refCfg->setAccessible(true);
            $refCfg->setValue($donsPlugin, $installedCfg);
        }

        $prevSession = $_SESSION;
        $prevPost = $_POST;
        $prevGet2 = $_GET;
        $prevServer2 = $_SERVER;
        $core = Core::getInstance();
        $refProp = new \ReflectionProperty(Core::class, 'pluginToCall');
        $refProp->setAccessible(true);
        $prevPluginToCall = $refProp->getValue($core);
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/admin/dons/edit';
            $_SESSION['email'] = 'admin@test.local';
            $_SESSION['token'] = 'api_admin_token_xyz';
            $core->auth()->resetCurrentUserCache();
            $refProp->setValue($core, 'dons');

            $donCtrl = new \Dons\Controllers\DonAdminController();
            $editHtml = $donCtrl->edit()->output();
            EvoTestRunner::assertContains('name="amount"', $editHtml, 'DonAdminController::edit() rend le formulaire de don');

            $donMgr = new \Dons\Entities\DonManager();
            $beforeCount = count($donMgr->getDons());

            // Tentative save() sans CSRF valide -> refusée, aucun don ajouté
            $_POST = [
                'token' => 'wrong_csrf_token',
                'amount' => '50',
                'firstName' => 'Alice',
                'lastName' => 'Test',
                'status' => 'completed',
            ];
            $donCtrl->save();
            EvoTestRunner::assertEquals($beforeCount, count($donMgr->getDons()), 'DonAdminController::save() bloque une requête sans token CSRF valide');

            // Sauvegarde avec CSRF valide -> enregistrée
            $_POST['token'] = 'tok_admin_123456';
            $donCtrl->save();
            $afterDons = $donMgr->getDons();
            EvoTestRunner::assertEquals($beforeCount + 1, count($afterDons), 'DonAdminController::save() enregistre un nouveau don avec CSRF valide');
        } finally {
            $refProp->setValue($core, $prevPluginToCall);
            $_SESSION = $prevSession;
            $_POST = $prevPost;
            $_GET = $prevGet2;
            $_SERVER = $prevServer2;
            $core->auth()->resetCurrentUserCache();
        }
    },
];

$exit = EvoTestRunner::runSuite('Tests contradictoires (sécurité, UI, i18n) et Phase 5', $contradictoryAndPhase5Tests);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'ContradictoryAndPhase5Test.php') {
    exit(EvoTestRunner::summaryAndExit());
}
return $exit;

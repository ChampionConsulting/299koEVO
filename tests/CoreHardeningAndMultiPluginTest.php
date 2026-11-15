<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Auth\Group;
use Core\Auth\RegistrationRequest;
use Core\Autoloader\HybridAutoloader;
use Core\Core;
use Core\Database;
use Core\Plugin\PluginsManager;
use Core\Responses\StringResponse;
use Core\Storage\FallbackStorage;
use Core\Storage\JsonActiveRecord;
use Core\Storage\JsonStorage;
use Template\Template;

// Modèles de test pour vérifier l'héritage statique, $filePath isolé et $table vs $tableName
class AuditDummyParentTable extends JsonActiveRecord
{
    protected static string $tableName = 'audit_parent_table';
    protected static array $schema = ['title' => 'string'];
}

class AuditDummyChildOverrideTable extends AuditDummyParentTable
{
    protected static string $table = 'audit_child_legacy_table';
}

class AuditDummyBothProps extends JsonActiveRecord
{
    protected static string $tableName = 'audit_preferred_name';
    protected static string $table = 'audit_ignored_table';
}

class AuditDummyIsolatedA extends JsonActiveRecord
{
    protected static string $tableName = 'audit_isolated_a';
}

class AuditDummyIsolatedB extends JsonActiveRecord
{
    protected static string $tableName = 'audit_isolated_b';
}

$coreHardeningTests = [
    'JsonActiveRecord: héritage statique, isolation setFilePath et priorité $tableName > $table' => function (): void {
        EvoTestRunner::assertEquals('audit_parent_table', (new AuditDummyParentTable())->getTableName(), 'Parent résout son $tableName');
        EvoTestRunner::assertEquals('audit_child_legacy_table', (new AuditDummyChildOverrideTable())->getTableName(), 'Enfant déclarant $table surcharge le $tableName du parent');
        EvoTestRunner::assertEquals('audit_preferred_name', (new AuditDummyBothProps())->getTableName(), 'Sur une même classe, $tableName prime sur $table');

        $pathA = DATA . 'custom_isolated_a.json';
        AuditDummyIsolatedA::setFilePath($pathA);

        $refResolve = new \ReflectionMethod(JsonActiveRecord::class, 'resolveFilePath');
        $resolvedA = $refResolve->invoke(null, 'AuditDummyIsolatedA');
        $resolvedB = $refResolve->invoke(null, 'AuditDummyIsolatedB');

        // Reflection invoke without late static binding calls self, so verify via static wrapper closure cleanly:
        $getPathFor = static function (string $cls): string {
            $closure = \Closure::bind(static fn() => static::resolveFilePath(), null, $cls);
            return $closure();
        };

        EvoTestRunner::assertEquals($pathA, $getPathFor(AuditDummyIsolatedA::class), 'AuditDummyIsolatedA conserve son setFilePath');
        EvoTestRunner::assertTrue($getPathFor(AuditDummyIsolatedB::class) !== $pathA, 'AuditDummyIsolatedB ne reçoit pas la fuite de setFilePath de AuditDummyIsolatedA');
    },

    'JsonActiveRecord & SqlStorage: persistance complète, clés primaires chaîne et auto-ALTER TABLE' => function (): void {
        $pm = PluginsManager::getInstance();
        foreach (['faq', 'guestbook', 'dons', 'BugTracker', 'galerie', 'newsletter'] as $reqPlugin) {
            $pm->installPlugin($reqPlugin, true);
        }

        $mode = getenv('EVO_TEST_STORAGE') ?: 'json';
        $expectSql = ($mode === 'sqlite' || $mode === 'mysql');

        $getStorageMethod = new \ReflectionMethod(JsonActiveRecord::class, 'getStorage');

        // 1. Vérifier que storage.default = sql s'applique même sans $storageDriver sur la classe fille
        $faqStorage = \Closure::bind(static fn() => \Faq\Entities\FaqQuestion::getStorage(), null, \Faq\Entities\FaqQuestion::class)();
        $gbStorage = \Closure::bind(static fn() => \Guestbook\Entities\GuestbookEntry::getStorage(), null, \Guestbook\Entities\GuestbookEntry::class)();
        $donStorage = \Closure::bind(static fn() => \Dons\Entities\Don::getStorage(), null, \Dons\Entities\Don::class)();
        $grpStorage = \Closure::bind(static fn() => Group::getStorage(), null, Group::class)();
        $regStorage = \Closure::bind(static fn() => RegistrationRequest::getStorage(), null, RegistrationRequest::class)();

        if ($expectSql) {
            EvoTestRunner::assertTrue($faqStorage instanceof FallbackStorage, 'FaqQuestion utilise SQL/FallbackStorage quand storage.default=sql');
            EvoTestRunner::assertTrue($gbStorage instanceof FallbackStorage, 'GuestbookEntry utilise SQL/FallbackStorage quand storage.default=sql');
            EvoTestRunner::assertTrue($donStorage instanceof FallbackStorage, 'Don utilise SQL/FallbackStorage quand storage.default=sql');
            EvoTestRunner::assertTrue($grpStorage instanceof FallbackStorage, 'Group (table réservée groups) utilise SQL/FallbackStorage');
            EvoTestRunner::assertTrue($regStorage instanceof FallbackStorage, 'RegistrationRequest (PK token) utilise SQL/FallbackStorage');
        } else {
            EvoTestRunner::assertTrue($faqStorage instanceof JsonStorage, 'FaqQuestion utilise JsonStorage en mode json');
        }

        // 2. FaqQuestion round-trip complet + colonne dynamique auto-ajoutée
        $faq = new \Faq\Entities\FaqQuestion([
            'question' => 'Comment tester 299koEVO ?',
            'answer' => 'Avec une suite isolée multi-moteurs.',
            'category' => 'Audit',
            'position' => 1,
            'votes' => 7,
            'active' => true,
            'date' => '2026-11-15 10:00:00',
            'extra_audit_flag' => 'dynamic_col_ok',
        ]);
        EvoTestRunner::assertTrue($faq->save(), 'FaqQuestion::save() réussit');
        $faqId = $faq->id;
        EvoTestRunner::assertTrue(!empty($faqId) && (string) $faqId !== '0', "FaqQuestion ID valide attribué ($faqId)");
        $reloadedFaq = \Faq\Entities\FaqQuestion::findPK($faqId);
        EvoTestRunner::assertTrue($reloadedFaq !== null, 'FaqQuestion relu via findPK()');
        EvoTestRunner::assertEquals('Comment tester 299koEVO ?', $reloadedFaq->question ?? null, 'FaqQuestion conserve question');
        EvoTestRunner::assertEquals('Avec une suite isolée multi-moteurs.', $reloadedFaq->answer ?? null, 'FaqQuestion conserve answer');
        EvoTestRunner::assertEquals('dynamic_col_ok', $reloadedFaq->extra_audit_flag ?? null, 'Colonne dynamique extra_audit_flag persistée automatiquement');

        // 3. GuestbookEntry (booléens false/true + array cast likes)
        $gb = new \Guestbook\Entities\GuestbookEntry([
            'name' => 'Jean Test',
            'email' => 'jean@test.local',
            'message' => 'Excellent CMS multi-stockage',
            'approved' => false,
            'likes' => ['fp_abc', 'fp_def'],
            'date' => '2026-11-15 11:00:00',
        ]);
        EvoTestRunner::assertTrue($gb->save(), 'GuestbookEntry::save() avec bool=false réussit');
        $reloadedGb = \Guestbook\Entities\GuestbookEntry::findPK($gb->id);
        EvoTestRunner::assertTrue($reloadedGb !== null, 'GuestbookEntry relu via findPK()');
        EvoTestRunner::assertEquals('Jean Test', $reloadedGb->name ?? null, 'GuestbookEntry conserve name');
        EvoTestRunner::assertEquals('Excellent CMS multi-stockage', $reloadedGb->message ?? null, 'GuestbookEntry conserve message');

        // 4. Don (DECIMAL + bool + datetime)
        $don = new \Dons\Entities\Don([
            'amount' => 42.50,
            'firstName' => 'Alice',
            'lastName' => 'Durand',
            'email' => 'alice@test.local',
            'message' => 'Bravo',
            'gateway' => 'stripe',
            'status' => 'completed',
            'anonymous' => false,
            'date' => '2026-11-15 12:00:00',
        ]);
        EvoTestRunner::assertTrue($don->save(), 'Don::save() réussit');
        $reloadedDon = \Dons\Entities\Don::findPK($don->id);
        EvoTestRunner::assertTrue($reloadedDon !== null, 'Don relu via findPK()');
        EvoTestRunner::assertEquals('Alice', $reloadedDon->firstName ?? null, 'Don conserve firstName');

        // 5. Entités à clé primaire string / hex (BugReport, GalerieItem, RegistrationRequest)
        $bug = new \BugTracker\Entities\BugReport([
            'title' => 'Bug PK chaîne',
            'description' => 'Vérification VARCHAR PK',
            'status' => 'open',
            'priority' => 'high',
            'created_at' => time(),
        ]);
        EvoTestRunner::assertTrue($bug->save(), 'BugReport (VARCHAR PK auto-générée) save réussit');
        EvoTestRunner::assertTrue(is_string((string) $bug->id) && strlen((string) $bug->id) >= 8, 'BugReport reçoit une clé hex non vide');
        $reloadedBug = \BugTracker\Entities\BugReport::findPK($bug->id);
        EvoTestRunner::assertEquals('Bug PK chaîne', $reloadedBug->title ?? null, 'BugReport relu correctement par sa clé chaîne');

        $token = 'reg_tok_' . bin2hex(random_bytes(6));
        $reg = new RegistrationRequest([
            'token' => $token,
            'email' => 'newuser@test.local',
            'username' => 'newuser',
            'status' => 'pending',
            'expires_at' => time() + 3600,
            'created_at' => '2026-11-15 12:30:00',
        ]);
        EvoTestRunner::assertTrue($reg->save(), 'RegistrationRequest (PK=token) save réussit');
        $reloadedReg = RegistrationRequest::findPK($token);
        EvoTestRunner::assertTrue($reloadedReg !== null && ($reloadedReg->email ?? '') === 'newuser@test.local', 'RegistrationRequest relu par findPK($token)');

        // 6. Group (nom de table SQL réservé 'groups' sous MariaDB)
        $group = new Group([
            'name' => 'Modérateur Audit',
            'slug' => 'mod-audit-' . bin2hex(random_bytes(3)),
            'permissions' => ['content.view', 'content.edit'],
            'system' => false,
        ]);
        EvoTestRunner::assertTrue($group->save(), 'Group (table `groups`) save réussit');
        $reloadedGroup = Group::findPK($group->id);
        EvoTestRunner::assertTrue($reloadedGroup !== null, 'Group relu par findPK()');
        EvoTestRunner::assertEquals(['content.view', 'content.edit'], $reloadedGroup->getPermissions(), 'Group désérialise correctement permissions[]');
    },

    'HybridAutoloader: parseur token_get_all(), accolade, filtre vendors et validation fallback' => function (): void {
        $sandbox = sys_get_temp_dir() . '/299ko_al_test_' . getmypid() . '_' . bin2hex(random_bytes(3));
        mkdir($sandbox . '/BracketNs/Sub', 0777, true);
        mkdir($sandbox . '/libs', 0777, true);
        mkdir($sandbox . '/mymod', 0777, true);

        // Fichier à namespace avec accolades + commentaires piégés + ::class + new class
        file_put_contents($sandbox . '/BracketNs/Sub/Multi.php', <<<'PHP'
<?php
/*
namespace FakeTrap\ShouldNeverAppear;
class TrapClass {}
*/
namespace BracketNs\Sub {
    $ref = \stdClass::class;
    $anon = new class { public function ping(): int { return 1; } };
    class AlphaDecl {
        public function id(): string { return 'alpha'; }
    }
    class BetaDecl {
        public function id(): string { return 'beta'; }
    }
}
PHP
        );

        // Vendor et backup qui doivent être ignorés du cache
        file_put_contents($sandbox . '/libs/IgnoredVendor.php', "<?php\nnamespace Libs;\nclass IgnoredVendor {}\n");
        file_put_contents($sandbox . '/BracketNs/Stale.php.bak', "<?php\nnamespace BracketNs;\nclass Stale {}\n");

        // Fallback non-PSR4 déclarant une autre classe (ne doit PAS être chargé aveuglément)
        file_put_contents($sandbox . '/mymod/WrongFile.php', "<?php\nnamespace MyMod;\nclass CompletelyDifferent {}\n");

        $cacheFile = $sandbox . '/cache/al_cache.php';
        $al = new HybridAutoloader([$sandbox], $cacheFile, false, true);
        $cacheData = require $cacheFile;
        $map = $cacheData['__classmap__'] ?? [];

        EvoTestRunner::assertTrue(isset($map['BracketNs\\Sub\\AlphaDecl']), 'Cache inclut AlphaDecl avec namespace en accolades');
        EvoTestRunner::assertTrue(isset($map['BracketNs\\Sub\\BetaDecl']), 'Cache inclut BetaDecl depuis le même fichier');
        EvoTestRunner::assertFalse(isset($map['FakeTrap\\ShouldNeverAppear\\TrapClass']), 'Les commentaires ne polluent pas le classmap');
        EvoTestRunner::assertFalse(isset($map['Libs\\IgnoredVendor']), 'Les répertoires libs/vendor sont exclus du cache');
        foreach (array_keys($map) as $key) {
            EvoTestRunner::assertFalse(str_contains($key, '{') || str_contains($key, "\n"), "Clé classmap propre sans accolade ni retour à la ligne: $key");
        }

        // Vérifier le rejet d'un fichier fallback non-PSR4 ne déclarant pas la classe demandée
        $loadedWrong = $al->load('MyMod\\WrongFile');
        EvoTestRunner::assertFalse($loadedWrong, 'HybridAutoloader refuse un fichier fallback ne déclarant pas MyMod\\WrongFile');

        // Vérifier la génération du cache sur le vrai dépôt [COMMON, PLUGINS]
        $realCache = $sandbox . '/cache/real_cache.php';
        $realAl = new HybridAutoloader([COMMON, PLUGINS], $realCache, false, true);
        $realData = require $realCache;
        $realMap = $realData['__classmap__'] ?? [];

        EvoTestRunner::assertTrue(count($realMap) > 200, 'Cache réel indexe > 200 classes Core/Plugins (' . count($realMap) . ')');
        EvoTestRunner::assertEquals('Core/Plugin/Plugin.php', $realMap['Core\\Plugin\\Plugin']['file'] ?? null, 'Core\\Plugin\\Plugin pointe vers le chemin canonique Core/Plugin/Plugin.php');
        EvoTestRunner::assertEquals('Core/Plugin/PluginsManager.php', $realMap['Core\\Plugin\\PluginsManager']['file'] ?? null, 'Core\\Plugin\\PluginsManager pointe vers Core/Plugin/PluginsManager.php');

        foreach ($realMap as $fqcn => $entry) {
            EvoTestRunner::assertFalse(str_contains($fqcn, '{') || str_contains($fqcn, "\n") || str_contains($fqcn, ';'), "Clé réelle valide: $fqcn");
            $fileRel = is_array($entry) ? $entry['file'] : $entry;
            EvoTestRunner::assertFalse(str_contains($fileRel, 'libs/mpdf/'), "Fichier vendor mpdf exclu: $fileRel");
        }

        // Cleanup sandbox
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($sandbox);
    },

    'StringResponse & Core: non-concaténation des templates, MIME/headers et protection debug' => function (): void {
        $tplFile = DATA . 'test_str_tpl.tpl';
        file_put_contents($tplFile, '<div>TEMPLATE_QUI_NE_DOIT_PAS_FUITER</div>');
        $tpl = new Template($tplFile);

        $resp = new StringResponse();
        $resp->addTemplate($tpl);
        $resp->setContent('console.log("service-worker-ok");')
            ->setContentType('application/javascript; charset=utf-8')
            ->setStatusCode(201)
            ->addHeader('X-Audit-Header', 'verified');

        EvoTestRunner::assertTrue($resp->hasRawContent(), 'hasRawContent() vaut true après setContent()');
        EvoTestRunner::assertTrue($resp->isRawOrNonHtml(), 'isRawOrNonHtml() détecte un StringResponse JS/brut');
        EvoTestRunner::assertEquals('application/javascript; charset=utf-8', $resp->getContentType(), 'Content-Type conservé');
        EvoTestRunner::assertEquals(201, $resp->getStatusCode(), 'Code HTTP conservé');

        $out = $resp->output();
        EvoTestRunner::assertEquals('console.log("service-worker-ok");', $out, 'output() ne concatène pas les templates au rawContent');
        EvoTestRunner::assertFalse(str_contains($out, 'TEMPLATE_QUI_NE_DOIT_PAS_FUITER'), 'Aucune fuite de template');

        // Vérifier que Core::processResponseContent() ne passe pas Minifyer HTML sur raw StringResponse
        $core = Core::getInstance();
        $procMethod = new \ReflectionMethod(Core::class, 'processResponseContent');
        $processed = $procMethod->invoke($core, $resp);
        EvoTestRunner::assertEquals('console.log("service-worker-ok");', $processed, 'Core::processResponseContent() conserve intact le contenu brut JS');
        EvoTestRunner::assertFalse(str_contains($processed, 'Generation time'), 'Aucune injection <script> debug dans StringResponse JS');

        // Vérifier qu'un StringResponse uniquement template continue de fonctionner normalement
        $tplResp = new StringResponse();
        $tplResp->addTemplate($tpl);
        EvoTestRunner::assertFalse($tplResp->hasRawContent(), 'StringResponse sans setContent() garde hasRawContent=false');
        EvoTestRunner::assertEquals('<div>TEMPLATE_QUI_NE_DOIT_PAS_FUITER</div>', $tplResp->output(), 'StringResponse template seul rend le template');
    },

    'Intégration multi-plugins combinés (blog, galerie, agenda, antispam, notifications, pwa, 299search, docs, homebuilder, LocalFeed, rss)' => function (): void {
        if (!defined('IS_LOGGED')) define('IS_LOGGED', false);
        if (!defined('IS_ADMIN')) define('IS_ADMIN', false);
        if (!defined('IS_PRO')) define('IS_PRO', false);

        $pm = PluginsManager::getInstance();
        $core = Core::getInstance();
        $router = \Core\Router\Router::getInstance();
        $pluginsToEnable = [
            'antispam', 'blog', 'galerie', 'agenda', 'notifications',
            'pwa', '299search', 'docs', 'homebuilder', 'LocalFeed', 'rss',
        ];

        foreach ($pluginsToEnable as $pName) {
            $ok = $pm->installPlugin($pName, true);
            EvoTestRunner::assertTrue($ok, "Installation combinée réussie pour {$pName}");
        }

        // Recharger PluginsManager depuis le disque pour que config/activate reflète l'état installé
        $refPm = new \ReflectionProperty(PluginsManager::class, 'instance');
        $refPm->setValue(null, null);
        $pm = PluginsManager::getInstance();

        Template::addGlobal('COMMON', COMMON);
        Template::addGlobal('DATA', DATA);
        Template::addGlobal('UPLOAD', UPLOAD);
        Template::addGlobal('DATA_PLUGIN', DATA_PLUGIN);
        Template::addGlobal('THEMES', THEMES);
        Template::addGlobal('PLUGINS', PLUGINS);
        Template::addGlobal('THEME_PATH', THEMES . $core->getConfigVal('theme') . '/');
        Template::addGlobal('SITE_URL', rtrim((string) $core->getConfigVal('siteUrl'), '/'));
        Template::addGlobal('THEME_URL', \Utils\Util::urlBuild(THEMES . $core->getConfigVal('theme')));
        Template::addGlobal('ADMIN_URL', rtrim($router->generate('admin'), '/') . '/');
        Template::addGlobal('VERSION', VERSION);
        Template::addGlobal('ROUTER', $router);
        Template::addGlobal('pluginsManager', $pm);
        Template::addGlobal('CORE', $core);
        Template::addGlobal('ADMIN_PATH', ADMIN_PATH);
        Template::addGlobal('SHOW', \Utils\Show::class);
        Template::addGlobal('show', \Utils\Show::class);
        Template::addGlobal('Lang', \Core\Lang::class);
        Template::addGlobal('lang', \Core\Lang::class);
        Template::addGlobal('Util', \Utils\Util::class);
        Template::addGlobal('util', \Utils\Util::class);
        Template::addGlobal('USER', \Core\Auth\CurrentUser::get());
        Template::addGlobal('user', \Core\Auth\CurrentUser::get());
        Template::addGlobal('_csrfToken', \Core\Security\Csrf::token());
        Template::addGlobal('allowRegistrations', (bool) $core->getConfigVal('allowRegistrations'));

        // Charger lang, routes et contrôleurs de tous ces plugins ensemble dans le même processus
        foreach ($pluginsToEnable as $pName) {
            $pl = $pm->getPlugin($pName);
            EvoTestRunner::assertTrue($pl !== null && $pl->isInstalled(), "Plugin {$pName} actif après installation");
            $pl->loadLangFile();
            $pl->loadRoutes();
            if ($pl->getLibFile() !== false && is_file($pl->getLibFile())) {
                include_once $pl->getLibFile();
            }
            $pl->loadControllers();
            foreach ($pl->getHooks() as $hookName => $cb) {
                $core->addHook($hookName, $cb);
            }
        }

        // Création de données réelles cross-plugins
        $newsMgr = new \Blog\Entities\NewsManager();
        $news = new \Blog\Entities\News();
        $news->setName('Inauguration Parc Bourges');
        $news->setDate(date('Y-m-d H:i:s'));
        $news->setIntro('Nouvel espace vert');
        $news->setContent('<p>Ouverture au public ce week-end.</p>');
        $news->setDraft('0');
        EvoTestRunner::assertTrue((bool) $newsMgr->saveNews($news), 'Blog News sauvegardé en environnement combiné');

        $event = new \Agenda\Entities\Event([
            'title' => 'Fête du Parc Bourges',
            'description' => 'Animations locales',
            'start_date' => date('Y-m-d 14:00:00', strtotime('+2 days')),
            'end_date' => date('Y-m-d 18:00:00', strtotime('+2 days')),
            'location' => 'Bourges',
            'status' => 'approved',
        ]);
        EvoTestRunner::assertTrue($event->save(), 'Agenda Event sauvegardé en environnement combiné');

        // Hooks croisés front
        ob_start();
        $core->callHook('frontHead');
        $core->callHook('endFrontHead');
        $core->callHook('endFrontBody');
        $hooksOut = (string) ob_get_clean();
        EvoTestRunner::assertFalse(str_contains($hooksOut, 'Fatal error'), 'Hooks croisés endFrontHead/endFrontBody sans erreur fatale');

        // LocalFeedService multi-plugin (agenda_events + blog sans erreur SQL CURDATE/NOW)
        $timeline = \LocalFeed\Services\LocalFeedService::getInstance()->getTimeline(null, 1, 10, 'all');
        EvoTestRunner::assertTrue(is_array($timeline) && array_key_exists('items', $timeline), 'LocalFeedService::getTimeline() retourne items[] sans erreur SQL dialecte');

        // RSS multi-plugin providers
        $providers = \Rss\Services\RssProviderManager::getAvailableProviders();
        EvoTestRunner::assertTrue(is_array($providers) && isset($providers['blog']), 'RssProviderManager détecte le provider blog en cohabitation');
        $rssBlogItems = \Rss\Services\RssProviderManager::getItemsForProvider('blog', 'http://localhost');
        EvoTestRunner::assertTrue(is_array($rssBlogItems) && count($rssBlogItems) >= 1, 'RssProviderManager remonte l\'article créé via Blog\\Entities\\News');

        // Utilitaire pour définir le plugin courant avant chaque contrôleur
        $refCorePlugin = new \ReflectionProperty(Core::class, 'pluginToCall');
        $setRunPlugin = static function (string $name) use ($pm, $core, $refCorePlugin): void {
            $plObj = $pm->getPlugin($name);
            $refCorePlugin->setValue($core, $name);
            $GLOBALS['runPlugin'] = $plObj;
            Template::addGlobal('runPlugin', $plObj);
        };

        // Recherche globale 299search
        $setRunPlugin('299search');
        $searchCtrl = new \Search299\Controllers\SearchController();
        $_GET['q'] = 'Bourges';
        $searchResp = $searchCtrl->index();
        EvoTestRunner::assertTrue($searchResp instanceof \Core\Responses\Response, 'SearchController#index retourne une Response valide');
        $searchHtml = $searchResp->output();
        EvoTestRunner::assertTrue(strlen($searchHtml) > 50, 'SearchController#index génère une page non vide');

        // Contrôleurs publics combinés (PWA SW, PWA Manifest, Docs, Blog, Galerie, Agenda, LocalFeed)
        $setRunPlugin('pwa');
        $pwaCtrl = new \Pwa\Controllers\PwaController();
        $swResp = $pwaCtrl->serviceWorker();
        EvoTestRunner::assertTrue($swResp instanceof StringResponse && $swResp->hasRawContent(), 'PwaController#serviceWorker retourne StringResponse brut');
        $manifestResp = $pwaCtrl->manifest();
        EvoTestRunner::assertTrue($manifestResp instanceof \Core\Responses\Response, 'PwaController#manifest retourne Response valide');

        $setRunPlugin('blog');
        $blogCtrl = new \BlogListController();
        $blogResp = $blogCtrl->home();
        EvoTestRunner::assertTrue($blogResp instanceof \Core\Responses\Response && str_contains($blogResp->output(), 'Inauguration Parc Bourges'), 'BlogListController#home rend l\'article créé');

        $setRunPlugin('galerie');
        $galCtrl = new \GalerieController();
        $galResp = $galCtrl->home();
        EvoTestRunner::assertTrue($galResp instanceof \Core\Responses\Response, 'GalerieController#home rend sans erreur');

        $setRunPlugin('docs');
        $docsCtrl = new \Docs\Controllers\DocsListController();
        $docsResp = $docsCtrl->home();
        EvoTestRunner::assertTrue($docsResp instanceof \Core\Responses\Response, 'DocsListController#home rend sans erreur');

        $setRunPlugin('agenda');
        $agendaCtrl = new \Agenda\Controllers\AgendaController();
        $agendaResp = $agendaCtrl->index();
        EvoTestRunner::assertTrue($agendaResp instanceof \Core\Responses\Response, 'AgendaController#index rend sans erreur');

        $setRunPlugin('LocalFeed');
        $lfCtrl = new \LocalFeed\Controllers\LocalFeedController();
        $lfResp = $lfCtrl->page();
        EvoTestRunner::assertTrue($lfResp instanceof \Core\Responses\Response, 'LocalFeedController#page rend sans erreur');
    },
];

$res = EvoTestRunner::runSuite('Durcissement Coeur & Intégration Multi-Plugins', $coreHardeningTests);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(EvoTestRunner::summaryAndExit());
}

return $res;

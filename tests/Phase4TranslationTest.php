<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Auth\User;
use Core\Content\ContentTypeManager;
use Core\Core;
use Core\Lang;
use Core\Page\Page;
use Core\Page\PageItem;
use Core\Router\Router;
use Core\Translation\TranslationManager;

$phase4Tests = [
    'Configuration multilingue, groupes de traduction et synchronisation champs communs' => function (): void {
        $admin = User::findPK(1);
        $tm = TranslationManager::getInstance();

        $locales = $tm->getEnabledLocales();
        EvoTestRunner::assertTrue(in_array('fr', $locales, true) && in_array('en', $locales, true) && in_array('de', $locales, true), 'Locales fr, en, de activées');
        EvoTestRunner::assertEquals('fr', $tm->getDefaultLocale(), 'Langue par défaut fr');

        ContentTypeManager::register([
            'id' => 'product_i18n',
            'label' => 'Produit multilingue',
            'config' => ['translatable' => true, 'workflow' => true],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'publish' => 'content.publish',
            ],
            'fields' => [
                ['name' => 'title', 'label' => 'Nom du produit', 'type' => 'text', 'required' => true, 'translatable' => true],
                ['name' => 'summary', 'label' => 'Résumé', 'type' => 'textarea', 'translatable' => true],
                ['name' => 'sku', 'label' => 'Référence SKU', 'type' => 'text', 'translatable' => false],
                ['name' => 'price', 'label' => 'Prix EUR', 'type' => 'decimal', 'translatable' => false],
            ],
        ]);
        $ctm = ContentTypeManager::getInstance();

        // Version FR
        $frRes = $ctm->createEntry('product_i18n', [
            'locale' => 'fr',
            'status' => 'published',
            'fields' => [
                'title' => 'Vélo Urbain',
                'summary' => 'Modèle léger pour la ville.',
                'sku' => 'VELO-100',
                'price' => 499.00,
            ],
        ], $admin);
        EvoTestRunner::assertTrue($frRes['success'], 'Création version FR réussie');
        $frEntry = $frRes['entry'];
        $group = $frEntry->getTranslationGroup();
        EvoTestRunner::assertTrue($group !== '', 'Groupe de traduction généré automatiquement');

        // Version EN liée au même groupe
        $enRes = $ctm->createEntry('product_i18n', [
            'locale' => 'en',
            'translation_group' => $group,
            'status' => 'published',
            'fields' => [
                'title' => 'City Bike',
                'summary' => 'Lightweight urban bicycle.',
                'sku' => 'VELO-100',
                'price' => 499.00,
            ],
        ], $admin);
        EvoTestRunner::assertTrue($enRes['success'], 'Création version EN liée réussie');
        $enEntry = $enRes['entry'];

        // Mise à jour d'un champ commun (price, sku) + champ traduisible (title) sur FR :
        // -> price & sku se propagent à EN, tandis que EN conserve son titre anglais 'City Bike' !
        $ctm->updateEntry('product_i18n', $frEntry->getId(), [
            'fields' => [
                'title' => 'Vélo Urbain Pro',
                'price' => 549.50,
                'sku' => 'VELO-200',
            ],
        ], $admin, true);

        $enReloaded = $ctm->getEntry('product_i18n', $enEntry->getId(), null);
        EvoTestRunner::assertEquals('City Bike', $enReloaded->getField('title'), 'Champ traduisible EN conservé lors d une sync');
        EvoTestRunner::assertEquals(549.5, $enReloaded->getField('price'), 'Champ commun price synchronisé sur EN');
        EvoTestRunner::assertEquals('VELO-200', $enReloaded->getField('sku'), 'Champ commun sku synchronisé sur EN');
    },

    'Résolution exacte, fallback explicite et option fallback désactivée' => function (): void {
        $tm = TranslationManager::getInstance();
        $ctm = ContentTypeManager::getInstance();
        $admin = User::findPK(1);

        $fr = $ctm->createEntry('product_i18n', [
            'locale' => 'fr',
            'status' => 'published',
            'fields' => ['title' => 'Casque', 'sku' => 'CAS-1', 'price' => 50],
        ], $admin)['entry'];
        $en = $ctm->createEntry('product_i18n', [
            'locale' => 'en',
            'translation_group' => $fr->getTranslationGroup(),
            'status' => 'published',
            'fields' => ['title' => 'Helmet', 'sku' => 'CAS-1', 'price' => 50],
        ], $admin)['entry'];

        // Résolution EN -> correspondance exacte (isFallback = false)
        $resEn = $tm->resolveContentEntry('product_i18n', $fr->getId(), 'en', null);
        EvoTestRunner::assertTrue($resEn->found(), 'Résolution EN trouvée');
        EvoTestRunner::assertFalse($resEn->isFallback, 'Pas de fallback pour EN qui existe');
        EvoTestRunner::assertEquals('en', $resEn->actualLocale, 'actualLocale = en');
        EvoTestRunner::assertEquals('Helmet', $resEn->entity->getField('title'), 'Contenu EN retourné');

        // Résolution DE absente -> fallback FR explicite (isFallback = true)
        $resDe = $tm->resolveContentEntry('product_i18n', $fr->getId(), 'de', null);
        EvoTestRunner::assertTrue($resDe->found(), 'Résolution DE utilise fallback');
        EvoTestRunner::assertTrue($resDe->isFallback, 'isFallback signalé à true pour DE manquante');
        EvoTestRunner::assertEquals('fr', $resDe->actualLocale, 'actualLocale retournée = fr');
        EvoTestRunner::assertEquals('de', $resDe->requestedLocale, 'requestedLocale = de');

        // Pages multilingues + hreflang
        $pageSvc = new Page();
        $pFr = new PageItem(['name' => 'À propos', 'content' => '<p>FR</p>', 'isHidden' => 0, 'locale' => 'fr']);
        $pageSvc->save($pFr);
        $pEn = new PageItem(['name' => 'About', 'content' => '<p>EN</p>', 'isHidden' => 0, 'locale' => 'en', 'translationGroup' => $pFr->getTranslationGroup()]);
        $pageSvc->save($pEn);

        $resolvedPageEn = $tm->resolvePageItem($pFr->getId(), 'en');
        EvoTestRunner::assertTrue($resolvedPageEn->found() && !$resolvedPageEn->isFallback, 'Page EN résolue directement');
        EvoTestRunner::assertEquals('About', $resolvedPageEn->entity->getName(), 'Page EN retournée');

        $hreflang = $tm->renderHreflangTags('page', (string) $pFr->getId(), fn(string $loc, string $id): string => 'http://localhost' . $tm->buildLocalizedUrl("/page/p-{$id}", $loc));
        EvoTestRunner::assertContains('hreflang="fr"', $hreflang, 'Balise hreflang fr générée');
        EvoTestRunner::assertContains('hreflang="en"', $hreflang, 'Balise hreflang en générée');
        EvoTestRunner::assertContains('hreflang="x-default"', $hreflang, 'Balise hreflang x-default générée');
    },

    'Routage URI préfixé non destructif et préservation Core\\Lang UI' => function (): void {
        $tm = TranslationManager::getInstance();
        $router = Router::getInstance();

        // Préfixes /en/... et /de/... détectés sans collision avec les modules cœur ou plugins
        $ex1 = $tm->extractLocaleFromPath('/en/page/about-2');
        EvoTestRunner::assertEquals('en', $ex1['locale'], 'Préfixe /en extrait');
        EvoTestRunner::assertEquals('/page/about-2', $ex1['path'], 'Chemin interne /page/about-2 restauré');

        $exAdmin = $tm->extractLocaleFromPath('/admin/page');
        EvoTestRunner::assertEquals(null, $exAdmin['locale'], '/admin jamais intercepté comme langue');

        $exApi = $tm->extractLocaleFromPath('/api/v1/routes');
        EvoTestRunner::assertEquals(null, $exApi['locale'], '/api jamais intercepté comme langue');

        $exBlog = $tm->extractLocaleFromPath('/blog/mon-article');
        EvoTestRunner::assertEquals(null, $exBlog['locale'], 'Plugin /blog jamais intercepté comme langue');

        $router->map('GET', '/demo-i18n/[a:slug]', 'Demo#show', 'demo-i18n');
        $m = $router->match('/en/demo-i18n/hello', 'GET');
        EvoTestRunner::assertTrue(is_array($m) && ($m['name'] ?? '') === 'demo-i18n', 'Router::match résout /en/demo-i18n/hello sur route sans préfixe');
        EvoTestRunner::assertEquals('en', $router->getActivePrefixLocale(), 'Router expose activePrefixLocale=en');

        // Core\Lang continue de fonctionner pour la traduction de l'interface
        Core::getInstance()->setLocale('en');
        EvoTestRunner::assertEquals('en', Lang::getLocale(), 'Lang::getLocale() suit le switch de locale');
        Core::getInstance()->setLocale('fr');
    },
];

$exit = EvoTestRunner::runSuite('Phase 4 — Contenus multilingues & Routage i18n', $phase4Tests);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'Phase4TranslationTest.php') {
    exit(EvoTestRunner::summaryAndExit());
}
return $exit;

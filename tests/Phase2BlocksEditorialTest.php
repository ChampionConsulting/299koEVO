<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Auth\User;
use Core\Blocks\BlockManager;
use Core\Content\ContentTypeManager;
use Core\Editorial\RevisionManager;
use Core\Editorial\WorkflowManager;
use Core\Page\Page;
use Core\Page\PageItem;

$phase2Tests = [
    'Moteur de blocs : 9 types natifs, validation anti-XSS/URL, composition et rendu' => function (): void {
        $bm = BlockManager::getInstance();
        $required = ['text', 'image', 'gallery', 'video', 'button', 'columns', 'quote', 'list', 'plugin_content'];
        foreach ($required as $type) {
            EvoTestRunner::assertTrue($bm->has($type), "Bloc natif '{$type}' doit être enregistré");
        }

        // Composition complète + nettoyage XSS
        $b1 = $bm->createBlock('text', ['heading' => 'Introduction', 'content' => '<p>Bienvenue</p><script>alert(1)</script>']);
        $b2 = $bm->createBlock('image', ['src' => '/upload/files/banner.jpg', 'alt' => 'Bannière']);
        $b3 = $bm->createBlock('gallery', ['columns' => 2, 'items' => [['src' => '/upload/files/a.jpg', 'alt' => 'A']]]);
        $b4 = $bm->createBlock('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Démo']);
        $b5 = $bm->createBlock('button', ['label' => 'Découvrir', 'url' => 'https://declic18.fr', 'target' => '_blank']);
        $b6 = $bm->createBlock('columns', ['columns' => [['content' => '<p>Col 1</p>'], ['blocks' => [$bm->createBlock('quote', ['text' => 'La simplicité.', 'author' => '299ko'])]]]]);
        $b7 = $bm->createBlock('quote', ['text' => 'Code propre.', 'author' => 'Équipe', 'source' => 'Audit']);
        $b8 = $bm->createBlock('list', ['style' => 'ordered', 'items' => ['Étape 1', 'Étape 2']]);
        $b9 = $bm->createBlock('plugin_content', ['plugin' => 'blog', 'limit' => 3]);

        $composition = [];
        foreach ([$b1, $b2, $b3, $b4, $b5, $b6, $b7, $b8, $b9] as $blk) {
            $composition = $bm->addBlock($composition, $blk);
        }
        EvoTestRunner::assertEquals(9, count($composition), '9 blocs ajoutés à la composition');

        $validation = $bm->validateComposition($composition);
        EvoTestRunner::assertTrue($validation['valid'], 'Composition de 9 blocs valide: ' . implode(' | ', $validation['errors']));
        $validated = $validation['blocks'];

        // Réordonnancement & suppression
        $reordered = $bm->reorderBlocks($validated, [$b9['id'], $b1['id']]);
        EvoTestRunner::assertEquals('plugin_content', $reordered[0]['type'], 'Premier bloc réordonné est plugin_content');
        EvoTestRunner::assertEquals(0, $reordered[0]['order'], 'Ordre réindexé à 0');

        $updated = $bm->updateBlock($reordered, $b5['id'], ['label' => 'Explorer']);
        $html = $bm->renderBlocks($updated);
        EvoTestRunner::assertContains('evo-blocks-container', $html, 'Conteneur global de blocs rendu');
        EvoTestRunner::assertContains('Bienvenue', $html, 'Contenu texte rendu');
        EvoTestRunner::assertFalse(str_contains($html, '<script>alert(1)</script>'), 'XSS supprimé du bloc texte');
        EvoTestRunner::assertContains('youtube.com/embed/dQw4w9WgXcQ', $html, 'Vidéo YouTube embarquée');
        EvoTestRunner::assertContains('rel="noopener noreferrer"', $html, 'Bouton externe sécurisé');
        EvoTestRunner::assertContains('Explorer', $html, 'Bloc mis à jour rendu');

        $afterRemove = $bm->removeBlock($updated, $b8['id']);
        EvoTestRunner::assertEquals(8, count($afterRemove), 'Suppression ramène à 8 blocs');

        // Rejets attendus
        $badUrl = $bm->validateBlock(['type' => 'button', 'data' => ['label' => 'Piège', 'url' => 'javascript:evil()']]);
        EvoTestRunner::assertFalse($badUrl['valid'], 'URL javascript: refusée sur bloc button');
        $badImg = $bm->validateBlock(['type' => 'image', 'data' => ['src' => '/upload/files/hack.php']]);
        EvoTestRunner::assertFalse($badImg['valid'], 'Image .php refusée sur bloc image');

        // Extension par plugin + coexistence HomeBuilder
        BlockManager::register([
            'type' => 'callout_info',
            'label' => 'Encart Info',
            'validator' => fn(array $d) => ['valid' => !empty($d['message']), 'data' => $d, 'errors' => empty($d['message']) ? ['Vide'] : []],
            'renderer' => fn(array $d) => '<aside>' . htmlspecialchars((string) $d['message'], ENT_QUOTES, 'UTF-8') . '</aside>',
        ]);
        $customHtml = $bm->renderBlock($bm->createBlock('callout_info', ['message' => 'Alerte locale']));
        EvoTestRunner::assertContains('<aside>Alerte locale</aside>', $customHtml, 'Bloc personnalisé plugin rendu');
        require_once PLUGINS . 'homebuilder/entities/BlockManager.php';
        EvoTestRunner::assertTrue(class_exists(\HomeBuilder\Entities\BlockManager::class), 'HomeBuilder\\Entities\\BlockManager reste chargeable sans conflit');
    },

    'Workflow éditorial (draft, pending, published, archived) et contrôles RBAC' => function (): void {
        $wf = WorkflowManager::getInstance();
        $admin = User::findPK(1);
        $editor = User::findPK(2);
        $contrib = User::findPK(3);

        EvoTestRunner::assertTrue($wf->canSetInitialStatus('draft', $contrib), 'Contributeur peut créer en draft');
        EvoTestRunner::assertFalse($wf->canSetInitialStatus('published', $contrib), 'Contributeur ne peut pas publier directement');
        EvoTestRunner::assertTrue($wf->canTransition('draft', 'pending', $contrib), 'Contributeur peut passer de draft à pending');
        EvoTestRunner::assertFalse($wf->canTransition('pending', 'published', $contrib), 'Contributeur ne peut pas passer de pending à published');

        EvoTestRunner::assertTrue($wf->canTransition('pending', 'published', $editor), 'Éditeur peut publier depuis pending');
        EvoTestRunner::assertTrue($wf->canTransition('published', 'archived', $admin), 'Admin peut archiver un contenu publié');

        ContentTypeManager::register([
            'id' => 'article_evo',
            'label' => 'Article Évo',
            'config' => ['workflow' => true, 'revisions' => true],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'delete' => 'content.publish',
                'publish' => 'content.publish',
            ],
            'fields' => [
                ['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true],
            ],
        ]);
        $ctm = ContentTypeManager::getInstance();

        $deny = $ctm->createEntry('article_evo', ['status' => 'published', 'fields' => ['title' => 'Interdit']], $contrib);
        EvoTestRunner::assertFalse($deny['success'], 'ContentTypeManager refuse publication directe par contributeur');
        EvoTestRunner::assertEquals(403, $deny['httpCode'] ?? 0, 'Code HTTP 403 retourné');

        $okDraft = $ctm->createEntry('article_evo', ['status' => 'draft', 'fields' => ['title' => 'Article en attente']], $contrib);
        EvoTestRunner::assertTrue($okDraft['success'], 'Contributeur crée en draft');
        $id = $okDraft['entry']->getId();

        // Contributeur passe en pending, mais ne peut pas passer en published
        $toPending = $ctm->updateEntry('article_evo', $id, ['status' => 'pending'], $contrib, true);
        EvoTestRunner::assertTrue($toPending['success'], 'Transition draft -> pending réussie');
        $denyPub = $ctm->updateEntry('article_evo', $id, ['status' => 'published'], $contrib, true);
        EvoTestRunner::assertFalse($denyPub['success'], 'Transition pending -> published refusée au contributeur');

        // Invisible en public tant que pending
        EvoTestRunner::assertTrue($ctm->getEntry('article_evo', $id, null) === null, 'Entrée pending invisible au public');

        // Éditeur publie
        $toPub = $ctm->updateEntry('article_evo', $id, ['status' => 'published'], $editor, true);
        EvoTestRunner::assertTrue($toPub['success'], 'Éditeur publie l entrée');
        EvoTestRunner::assertTrue($ctm->getEntry('article_evo', $id, null) !== null, 'Entrée publiée visible au public');
    },

    'Révisions éditoriales, anti-doublon, comparaison et restauration + rétrocompatibilité PageItem' => function (): void {
        $admin = User::findPK(1);
        $rm = RevisionManager::getInstance();

        // 1. Rétrocompatibilité d'une PageItem historique sans champs blocks/status
        $legacy = new PageItem([
            'name' => 'Page Historique',
            'content' => htmlspecialchars('<p>Contenu v1</p>', ENT_QUOTES, 'UTF-8'),
            'isHidden' => 0,
            'position' => 1,
        ]);
        EvoTestRunner::assertEquals('published', $legacy->getStatus(), 'Page historique sans champ status est traitée comme published');
        EvoTestRunner::assertTrue($legacy->getVisibilityFlag('PUB'), 'Page historique visible publiquement');
        EvoTestRunner::assertContains('<p>Contenu v1</p>', $legacy->getParsedContent(), 'Rendu HTML rétrocompatible préservé');

        // 2. Enregistrement via Page::save() + blocs structurés + révisions auto
        $pageService = new Page();
        $bm = BlockManager::getInstance();
        $blk1 = $bm->createBlock('text', ['content' => '<p>Bloc initial</p>']);
        $legacy->setBlocks([$blk1]);
        $legacy->setStatus('published');
        EvoTestRunner::assertTrue($pageService->save($legacy), 'Sauvegarde PageItem avec blocs réussie');
        $pageId = $legacy->getId();

        $hist1 = $rm->getHistory('page', $pageId);
        EvoTestRunner::assertEquals(1, count($hist1), 'Première révision enregistrée à la sauvegarde de la page');

        // Sauvegarde identique -> anti-doublon checksum ne crée pas de v2 inutile
        $pageService->save($legacy);
        EvoTestRunner::assertEquals(1, count($rm->getHistory('page', $pageId)), 'Sauvegarde identique ignorée (anti-doublon)');

        // Modification réelle v2
        $legacy->setName('Page Modifiée v2');
        $blk2 = $bm->createBlock('quote', ['text' => 'Nouvelle citation']);
        $legacy->setBlocks([$blk1, $blk2]);
        $pageService->save($legacy);

        $hist2 = $rm->getHistory('page', $pageId);
        EvoTestRunner::assertEquals(2, count($hist2), 'Deuxième révision enregistrée après modification réelle');

        // Comparaison v1 vs v2
        $diff = $rm->compare($hist1[0]->getId(), $hist2[0]->getId());
        EvoTestRunner::assertEquals(1, $diff['from_version'], 'Version source 1');
        EvoTestRunner::assertEquals(2, $diff['to_version'], 'Version cible 2');
        EvoTestRunner::assertTrue(isset($diff['fields']['name']), 'Diff détecte le changement de name');
        EvoTestRunner::assertEquals(1, count($diff['blocks']['added']), 'Diff détecte 1 bloc ajouté');

        // Restauration vers v1
        $restore = $rm->restoreRevision($hist1[0]->getId(), $admin);
        EvoTestRunner::assertTrue($restore['success'], 'Restauration révision v1 réussie');
        $reloaded = PageItem::findPK($pageId);
        EvoTestRunner::assertEquals('Page Historique', $reloaded->getName(), 'Nom restauré à v1');
        EvoTestRunner::assertEquals(1, count($reloaded->getBlocks()), 'Blocs restaurés à v1');
        EvoTestRunner::assertContains('Bloc initial', $reloaded->getParsedContent(), 'getParsedContent rend contenu + blocs');
    },
];

$exit = EvoTestRunner::runSuite('Phase 2 — Moteur de blocs, Révisions & Workflow', $phase2Tests);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'Phase2BlocksEditorialTest.php') {
    exit(EvoTestRunner::summaryAndExit());
}
return $exit;

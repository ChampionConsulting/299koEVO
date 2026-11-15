<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Blocks\BlockManager;
use Core\Content\ContentTypeManager;
use Core\Plugin\PluginsManager;
use Core\Translation\TranslationManager;

$compatTests = [
    'Découverte intégrale des 41 plugins existants et validité param/infos.json' => function (): void {
        $dirs = glob(PLUGINS . '*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        EvoTestRunner::assertEquals(41, count($dirs), 'Les 41 répertoires de plugins existants sont toujours présents');

        foreach ($dirs as $dir) {
            $name = basename($dir);
            $infosFile = $dir . DS . 'param' . DS . 'infos.json';
            $entryFile = $dir . DS . $name . '.php';
            EvoTestRunner::assertTrue(is_file($infosFile), "infos.json présent pour {$name}");
            $decoded = json_decode((string) file_get_contents($infosFile), true);
            EvoTestRunner::assertTrue(is_array($decoded), "infos.json valide pour {$name}");
            EvoTestRunner::assertTrue(is_file($entryFile), "Fichier principal {$name}.php présent");
        }

        // PluginsManager charge sans erreur la liste complète
        $pm = PluginsManager::getInstance();
        $plugins = $pm->getPlugins();
        EvoTestRunner::assertEquals(41, count($plugins), 'PluginsManager découvre exactement 41 plugins sans erreur');
    },

    'Non-collision routes / plugins et coexistence HomeBuilder + adaptateurs blocs' => function (): void {
        $tm = TranslationManager::getInstance();
        $dirs = glob(PLUGINS . '*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = strtolower(basename($dir));
            $parsed = $tm->extractLocaleFromPath('/' . $slug . '/test');
            EvoTestRunner::assertEquals(null, $parsed['locale'], "Le préfixe /{$slug} n'est jamais capturé comme langue");
        }

        // Coexistence Core\Blocks\BlockManager et HomeBuilder\Entities\BlockManager
        require_once PLUGINS . 'homebuilder/entities/BlockManager.php';
        $hb = new \HomeBuilder\Entities\BlockManager();
        $coreBm = BlockManager::getInstance();
        EvoTestRunner::assertTrue(is_object($hb) && is_object($coreBm), 'Coexistence effective HomeBuilder et Core\\Blocks');

        // Enregistrement simulé d'un contenu plugin (ex: blog / agenda) et rendu via bloc plugin_content
        ContentTypeManager::register([
            'id' => 'agenda_compat',
            'label' => 'Agenda Compat',
            'fields' => [['name' => 'title', 'label' => 'Titre', 'type' => 'text', 'required' => true]],
            'permissions' => ['view' => 'public', 'create' => 'content.create', 'edit' => 'content.edit', 'publish' => 'content.publish'],
        ]);
        $html = $coreBm->renderBlock($coreBm->createBlock('plugin_content', ['plugin' => 'blog', 'limit' => 2]));
        EvoTestRunner::assertContains('evo-block-plugin_content', $html, 'Bloc plugin_content fonctionne sans activation obligatoire');
    },
];

$exit = EvoTestRunner::runSuite('Compatibilité des 41 plugins existants', $compatTests);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'PluginsCompatibilityTest.php') {
    exit(EvoTestRunner::summaryAndExit());
}
return $exit;

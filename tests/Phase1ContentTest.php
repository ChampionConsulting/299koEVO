<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Core\Auth\User;
use Core\Content\ContentTypeManager;
use Core\Content\FieldDefinition;
use Core\Content\FieldManager;
use Core\Storage\DialectResolver;

$phase1Tests = [
    'Validation des 12 types de champs (valides et invalides)' => function (): void {
        $fm = FieldManager::getInstance();
        $requiredTypes = ['text', 'textarea', 'integer', 'decimal', 'date', 'datetime', 'boolean', 'email', 'url', 'media', 'select', 'geo'];
        $supported = $fm->getSupportedTypes();
        foreach ($requiredTypes as $t) {
            EvoTestRunner::assertTrue(in_array($t, $supported, true), "Type {$t} doit être supporté");
        }

        $cases = [
            ['def' => ['name' => 'titre', 'type' => 'text', 'required' => true, 'rules' => ['minLength' => 3]], 'ok' => 'Bonjour', 'bad' => 'Ab'],
            ['def' => ['name' => 'desc', 'type' => 'textarea', 'rules' => ['maxLength' => 50]], 'ok' => 'Description complète', 'bad' => str_repeat('a', 60)],
            ['def' => ['name' => 'age', 'type' => 'integer', 'rules' => ['min' => 1, 'max' => 120]], 'ok' => '42', 'bad' => '150'],
            ['def' => ['name' => 'prix', 'type' => 'decimal', 'rules' => ['min' => 0]], 'ok' => '19,50', 'bad' => 'abc'],
            ['def' => ['name' => 'date_creation', 'type' => 'date'], 'ok' => '2026-11-15', 'bad' => '2026-13-40'],
            ['def' => ['name' => 'horodatage', 'type' => 'datetime'], 'ok' => '2026-11-15T14:30', 'bad' => 'not-a-datetime'],
            ['def' => ['name' => 'actif', 'type' => 'boolean'], 'ok' => 'oui', 'bad' => 'peut-etre'],
            ['def' => ['name' => 'contact', 'type' => 'email'], 'ok' => 'Contact@Example.org', 'bad' => 'email-invalide'],
            ['def' => ['name' => 'site', 'type' => 'url'], 'ok' => 'https://declic18.fr/asso', 'bad' => 'javascript:alert(1)'],
            ['def' => ['name' => 'logo', 'type' => 'media', 'rules' => ['extensions' => ['png', 'jpg', 'webp']]], 'ok' => '/upload/files/logo.png', 'bad' => '/upload/files/shell.php'],
            ['def' => ['name' => 'statut_asso', 'type' => 'select', 'options' => ['active' => 'Active', 'pause' => 'En pause']], 'ok' => 'active', 'bad' => 'inconnue'],
            ['def' => ['name' => 'position', 'type' => 'geo'], 'ok' => ['lat' => 47.081, 'lng' => 2.398], 'bad' => ['lat' => 120, 'lng' => 2.4]],
        ];

        foreach ($cases as $c) {
            $field = FieldDefinition::fromArray($c['def']);
            $okRes = $fm->validateField($field, $c['ok']);
            EvoTestRunner::assertTrue($okRes['valid'], "Validation valide échouée pour {$field->name}: " . ($okRes['error'] ?? ''));

            $badRes = $fm->validateField($field, $c['bad']);
            EvoTestRunner::assertFalse($badRes['valid'], "Validation invalide acceptée à tort pour {$field->name}");
        }

        // Vérification normalisation email & geo & decimal & boolean
        $emailVal = $fm->validateField(FieldDefinition::fromArray(['name' => 'e', 'type' => 'email']), 'TEST@Example.COM');
        EvoTestRunner::assertEquals('test@example.com', $emailVal['value'], 'Email normalisé en minuscules');
        $decVal = $fm->validateField(FieldDefinition::fromArray(['name' => 'p', 'type' => 'decimal']), '12,75');
        EvoTestRunner::assertEquals(12.75, $decVal['value'], 'Decimal accepte virgule française');
        $boolVal = $fm->validateField(FieldDefinition::fromArray(['name' => 'b', 'type' => 'boolean']), 'oui');
        EvoTestRunner::assertEquals(true, $boolVal['value'], 'Boolean normalisé');

        // Type personnalisé extensible
        $fm->registerType('siret', function (FieldDefinition $f, mixed $v): array {
            $clean = preg_replace('/\s+/', '', (string) $v);
            return strlen($clean) === 14 && ctype_digit($clean)
                ? ['valid' => true, 'value' => $clean, 'error' => null]
                : ['valid' => false, 'value' => null, 'error' => 'SIRET invalide'];
        });
        EvoTestRunner::assertTrue($fm->validateField(FieldDefinition::fromArray(['name' => 's', 'type' => 'siret']), '123 456 789 01234')['valid'], 'Type personnalisé siret accepté');
        EvoTestRunner::assertFalse($fm->validateField(FieldDefinition::fromArray(['name' => 's', 'type' => 'siret']), '123')['valid'], 'Type personnalisé siret invalide rejeté');
    },

    'Permissions de lecture et d’écriture par champ (RBAC)' => function (): void {
        $admin = User::findPK(1);
        $contrib = User::findPK(3);
        $member = User::findPK(4);
        EvoTestRunner::assertTrue($admin !== null && $contrib !== null && $member !== null, 'Utilisateurs de test chargés');

        $fields = [
            FieldDefinition::fromArray(['name' => 'nom', 'label' => 'Nom', 'type' => 'text', 'required' => true]),
            FieldDefinition::fromArray([
                'name' => 'note_interne',
                'label' => 'Note interne',
                'type' => 'textarea',
                'readPermission' => 'content.publish',
                'writePermission' => 'content.publish',
            ]),
        ];

        // Le contributeur n'a pas content.publish
        $payload = $fields ? FieldManager::getInstance()->validatePayload($fields, ['nom' => 'Club Échecs', 'note_interne' => 'Secret'], $contrib) : [];
        EvoTestRunner::assertFalse($payload['valid'], 'Écriture sur note_interne interdite au contributeur');
        EvoTestRunner::assertTrue(isset($payload['errors']['note_interne']), 'Erreur ciblée sur note_interne');

        // Sans note_interne, le contributeur peut écrire nom
        $okContrib = FieldManager::getInstance()->validatePayload($fields, ['nom' => 'Club Échecs'], $contrib);
        EvoTestRunner::assertTrue($okContrib['valid'], 'Contributeur peut écrire nom seul');

        // En lecture, un membre ne voit pas note_interne, un éditeur/admin la voit
        $filteredMember = FieldManager::getInstance()->filterReadableValues($fields, ['nom' => 'Club Échecs', 'note_interne' => 'Confidentiel'], $member);
        EvoTestRunner::assertFalse(array_key_exists('note_interne', $filteredMember), 'note_interne masqué au membre');
        EvoTestRunner::assertEquals('Club Échecs', $filteredMember['nom'], 'Champ public visible au membre');

        $filteredAdmin = FieldManager::getInstance()->filterReadableValues($fields, ['nom' => 'Club Échecs', 'note_interne' => 'Confidentiel'], $admin);
        EvoTestRunner::assertEquals('Confidentiel', $filteredAdmin['note_interne'], 'note_interne visible à l admin');

        // Rendu HTML admin des champs autorisés
        $html = FieldManager::getInstance()->renderFormFields($fields, ['nom' => 'Club'], $contrib);
        EvoTestRunner::assertContains('name="custom_fields[nom]"', $html, 'HTML contient le champ autorisé nom');
        EvoTestRunner::assertFalse(str_contains($html, 'note_interne'), 'HTML masque le champ sans droit lecture/écriture');
    },

    'Enregistrement type de contenu, CRUD complet, filtrage et rattachement aux pages' => function (): void {
        $admin = User::findPK(1);
        $member = User::findPK(4);

        ContentTypeManager::register([
            'id' => 'association',
            'label' => 'Association',
            'description' => 'Annuaire associatif local',
            'display' => ['title_field' => 'name'],
            'permissions' => [
                'view' => 'public',
                'create' => 'content.create',
                'edit' => 'content.edit',
                'delete' => 'admin',
                'publish' => 'content.publish',
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true, 'order' => 1],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'order' => 2],
                ['name' => 'ville', 'label' => 'Ville', 'type' => 'text', 'required' => true, 'order' => 3],
                ['name' => 'adherents', 'label' => 'Adhérents', 'type' => 'integer', 'rules' => ['min' => 0], 'order' => 4],
                ['name' => 'coordonnees', 'label' => 'GPS', 'type' => 'geo', 'order' => 5],
            ],
        ]);

        $ctm = ContentTypeManager::getInstance();
        EvoTestRunner::assertTrue($ctm->has('association'), 'Type association enregistré');

        $res1 = $ctm->createEntry('association', [
            'status' => 'published',
            'fields' => [
                'name' => 'Les Jardins du Cher',
                'email' => 'jardins@bourges.fr',
                'ville' => 'Bourges',
                'adherents' => 85,
                'coordonnees' => ['lat' => 47.0810, 'lng' => 2.3987],
            ],
        ], $admin);
        EvoTestRunner::assertTrue($res1['success'], 'Création association publiée réussie');
        $entry1 = $res1['entry'];
        EvoTestRunner::assertEquals('Les Jardins du Cher', $entry1->getTitle(), 'Titre résolu depuis display.title_field');
        EvoTestRunner::assertEquals('les-jardins-du-cher', $entry1->getSlug(), 'Slug auto-généré');

        // Création brouillon + deuxième entrée
        $res2 = $ctm->createEntry('association', [
            'status' => 'draft',
            'fields' => [
                'name' => 'Atelier Vélo Vierzon',
                'email' => 'velo@vierzon.fr',
                'ville' => 'Vierzon',
                'adherents' => 25,
            ],
        ], $admin);
        EvoTestRunner::assertTrue($res2['success'], 'Création association brouillon réussie');

        // Filtrage par champ personnalisé pour visiteur anonyme (ne doit voir que published)
        $publicBourges = $ctm->findEntries('association', ['ville' => 'Bourges'], [], null, 0, null);
        EvoTestRunner::assertEquals(1, count($publicBourges), '1 association publiée à Bourges');
        $publicVierzon = $ctm->findEntries('association', ['ville' => 'Vierzon'], [], null, 0, null);
        EvoTestRunner::assertEquals(0, count($publicVierzon), 'Brouillon masqué au public');

        // Filtrage avec opérateur >= pour admin
        $allMin20 = $ctm->findEntries('association', ['adherents' => ['op' => '>=', 'value' => 20]], [], null, 0, $admin);
        EvoTestRunner::assertEquals(2, count($allMin20), 'Admin voit 2 associations >= 20 adhérents');

        // Mise à jour partielle
        $upd = $ctm->updateEntry('association', $entry1->getId(), ['fields' => ['adherents' => 110]], $admin, true);
        EvoTestRunner::assertTrue($upd['success'], 'Mise à jour partielle réussie');
        $reloaded = $ctm->getEntry('association', $entry1->getId(), null);
        EvoTestRunner::assertEquals(110, $reloaded->getField('adherents'), 'Valeur mise à jour persistée');
        EvoTestRunner::assertEquals('Bourges', $reloaded->getField('ville'), 'Champs non transmis préservés en mode partiel');

        // Un membre ne peut pas supprimer
        EvoTestRunner::assertFalse($ctm->deleteEntry('association', $entry1->getId(), $member), 'Suppression refusée au membre');
        // Un admin peut supprimer
        EvoTestRunner::assertTrue($ctm->deleteEntry('association', $entry1->getId(), $admin), 'Suppression réussie pour l admin');
        EvoTestRunner::assertTrue($ctm->getEntry('association', $entry1->getId(), $admin) === null, 'Entrée supprimée introuvable');

        // Attachement de champs personnalisés au type natif 'page'
        $ctm->attachFieldsToType('page', [
            ['name' => 'sous_titre', 'label' => 'Sous-titre', 'type' => 'text'],
            ['name' => 'badge', 'label' => 'Badge', 'type' => 'select', 'options' => ['nouveau', 'important']],
        ]);
        $saveTarget = $ctm->saveTargetFields('page', 'page_42', ['sous_titre' => 'Accueil citoyen', 'badge' => 'important'], $admin);
        EvoTestRunner::assertTrue($saveTarget['success'], 'Champs personnalisés attachés à une page');
        $loadedTarget = $ctm->getTargetFields('page', 'page_42', null);
        EvoTestRunner::assertEquals('important', $loadedTarget['badge'] ?? null, 'Champs personnalisés de page récupérés');
    },

    'Compatibilité schéma DDL MySQL et SQLite' => function (): void {
        $mysql = DialectResolver::resolve('mysql');
        $ddl = $mysql->getCreateTableSql('core_content_entries', 'id', [
            'content_type' => 'string',
            'target_id' => 'string',
            'fields_data' => 'text',
            'blocks_data' => 'text',
        ], ['content_type']);
        EvoTestRunner::assertContains('CREATE TABLE `core_content_entries`', $ddl, 'DDL MySQL génère la table core_content_entries');
        EvoTestRunner::assertContains('`fields_data` TEXT', $ddl, 'Colonne fields_data en TEXT pour compatibilité MySQL');
    },
];

$exit = EvoTestRunner::runSuite('Phase 1 — Types de contenus & Champs personnalisés', $phase1Tests);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'Phase1ContentTest.php') {
    exit(EvoTestRunner::summaryAndExit());
}
return $exit;

<?php

namespace Core\Content;

use Core\Auth\User;
use Core\Auth\UsersManager;
use Core\Core;
use Core\Navigation\AccessPolicyEvaluator;
use Core\Navigation\ConditionContext;
use Core\Navigation\GenericResource;
use Utils\Util;

defined('ROOT') or exit('Access denied!');

/**
 * Registre et service central des types de contenus et de leurs données.
 */
class ContentTypeManager
{
    private static ?self $instance = null;

    /** @var array<string,ContentTypeDefinition> */
    private array $types = [];

    /** @var array<string,bool> */
    private array $persistedTypeIds = [];

    private bool $hooksDispatched = false;

    private function __construct()
    {
        $this->registerCoreDefaults();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * API statique stable pour les plugins :
     * ContentTypeManager::register(['id' => 'association', 'label' => 'Association', 'fields' => [...]]);
     */
    public static function register(array|ContentTypeDefinition $definition): ContentTypeDefinition
    {
        return self::getInstance()->registerDefinition($definition);
    }

    public function registerDefinition(array|ContentTypeDefinition $definition): ContentTypeDefinition
    {
        $def = $definition instanceof ContentTypeDefinition
            ? $definition
            : ContentTypeDefinition::fromArray($definition);

        $this->types[$def->id] = $def;
        return $def;
    }

    public function attachFieldsToType(string $typeId, array $fields): void
    {
        $this->ensureHookTypesLoaded();
        $def = $this->types[$typeId] ?? null;
        if ($def === null) {
            throw new \InvalidArgumentException("Type de contenu introuvable : {$typeId}");
        }
        foreach ($fields as $field) {
            $def->addField($field);
        }
    }

    public function unregister(string $typeId): void
    {
        unset($this->types[$typeId]);
    }

    public function has(string $typeId): bool
    {
        $this->ensureHookTypesLoaded();
        return isset($this->types[$typeId]);
    }

    public function get(string $typeId): ?ContentTypeDefinition
    {
        $this->ensureHookTypesLoaded();
        return $this->types[$typeId] ?? null;
    }

    /**
     * @return array<string,ContentTypeDefinition>
     */
    public function all(): array
    {
        $this->ensureHookTypesLoaded();
        return $this->types;
    }

    public function canPerform(string $typeId, string $action, ?User $user = null, ?ContentEntry $entry = null): bool
    {
        $type = $this->get($typeId);
        if ($type === null) {
            return false;
        }

        $perm = $type->permissions[$action] ?? null;
        if ($perm === null || $perm === 'public') {
            return true;
        }

        if ($perm === 'auth') {
            return $user !== null;
        }

        if ($user === null) {
            return false;
        }

        $ctx = new ConditionContext($user, $entry ? $entry->getMetadata() : ['content_type' => $typeId]);
        $evaluator = AccessPolicyEvaluator::getInstance();

        if ($perm === 'admin') {
            return $evaluator->isAdmin($ctx);
        }

        $permHash = md5((string) json_encode($perm));
        if (is_array($perm)) {
            return $evaluator->canAccess(new GenericResource("content_type:{$typeId}:{$action}:{$permHash}", $perm, ['PUB' => false]), $ctx);
        }

        return $evaluator->canAccess(
            new GenericResource(
                "content_type:{$typeId}:{$action}:{$permHash}",
                [['type' => 'permission', 'value' => (string) $perm]],
                ['PUB' => false]
            ),
            $ctx
        );
    }

    /**
     * Contrôle anti-IDOR : autorisé si super-admin, auteur propriétaire,
     * détenteur du droit de publication du type ou de la permission `content.edit_others`.
     */
    public function isOwnerOrPrivileged(ContentTypeDefinition $type, ContentEntry $entry, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        $authorId = (string) ($entry->attributes['author_id'] ?? '');
        if ($authorId !== '' && $authorId === (string) $user->getId()) {
            return true;
        }
        if ($this->canPerform($type->id, 'publish', $user, $entry)) {
            return true;
        }
        $evaluator = AccessPolicyEvaluator::getInstance();
        $ctx = new ConditionContext($user, ['content_type' => $type->id, 'entry_id' => $entry->getId()]);
        return $evaluator->canAccess(
            new GenericResource(
                'content_type:' . $type->id . ':edit_others',
                [['type' => 'permission', 'value' => 'content.edit_others']],
                ['PUB' => false]
            ),
            $ctx
        );
    }

    public function hasSlugCollision(string $typeId, string $slug, string $locale, int|string|null $excludeId = null): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }
        $normLoc = strtolower(trim($locale));
        $rows = ContentEntry::queryBuilder()
            ->where('content_type', '=', $typeId)
            ->where('slug', '=', $slug)
            ->get();

        foreach ($rows as $row) {
            if ($excludeId !== null && (string) $row->getId() === (string) $excludeId) {
                continue;
            }
            if (strtolower($row->getLocale()) === $normLoc) {
                return true;
            }
        }
        return false;
    }

    public function findBySlug(string $typeId, string $slug, ?string $locale = null, ?User $user = null): ?ContentEntry
    {
        $type = $this->get($typeId);
        if ($type === null || trim($slug) === '') {
            return null;
        }
        $rows = ContentEntry::queryBuilder()
            ->where('content_type', '=', $typeId)
            ->where('slug', '=', trim($slug))
            ->get();

        $normLoc = $locale !== null ? strtolower(trim($locale)) : null;
        foreach ($rows as $entry) {
            if ($normLoc !== null && strtolower($entry->getLocale()) !== $normLoc) {
                continue;
            }
            if ($entry->getStatus() !== 'published') {
                if (!$this->isOwnerOrPrivileged($type, $entry, $user)) {
                    continue;
                }
            } elseif (!$this->canPerform($typeId, 'view', $user, $entry)) {
                continue;
            }
            return $entry;
        }
        return null;
    }

    public function getCustomTypesFile(): string
    {
        return DATA_CORE_CONTENT . 'content_types.json';
    }

    public function loadPersistedTypes(): void
    {
        $file = $this->getCustomTypesFile();
        if (!is_file($file)) {
            return;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return;
        }
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $def = ContentTypeDefinition::fromArray($item);
            $this->types[$def->id] = $def;
            $this->persistedTypeIds[$def->id] = true;
        }
    }

    public function getPersistedTypes(): array
    {
        $file = $this->getCustomTypesFile();
        if (!is_file($file)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isPersistedType(string $typeId): bool
    {
        $this->ensureHookTypesLoaded();
        return !empty($this->persistedTypeIds[$typeId]);
    }

    public function saveCustomType(array $config): ContentTypeDefinition
    {
        $this->ensureHookTypesLoaded();
        $def = ContentTypeDefinition::fromArray($config);
        $all = $this->getPersistedTypes();
        $all[$def->id] = $def->toArray();

        $dir = dirname($this->getCustomTypesFile());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($this->getCustomTypesFile(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->types[$def->id] = $def;
        $this->persistedTypeIds[$def->id] = true;
        return $def;
    }

    public function deleteCustomType(string $typeId): bool
    {
        $this->ensureHookTypesLoaded();
        if ($typeId === 'page') {
            return false;
        }
        $all = $this->getPersistedTypes();
        $existed = isset($all[$typeId]);
        unset($all[$typeId]);
        $dir = dirname($this->getCustomTypesFile());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($this->getCustomTypesFile(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        unset($this->types[$typeId], $this->persistedTypeIds[$typeId]);
        return $existed;
    }

    /**
     * Crée et valide côté serveur une nouvelle entrée de type de contenu.
     *
     * @return array{success:bool,entry:?ContentEntry,errors:array<string,string>,httpCode?:int}
     */
    public function createEntry(string $typeId, array $data, ?User $user = null, bool $apiContext = false): array
    {
        $type = $this->get($typeId);
        if ($type === null) {
            return ['success' => false, 'entry' => null, 'errors' => ['content_type' => "Type inconnu : {$typeId}"], 'httpCode' => 404];
        }

        $actor = $user ?? UsersManager::getCurrentUser();
        if (!$this->canPerform($typeId, 'create', $actor)) {
            return [
                'success' => false,
                'entry' => null,
                'errors' => ['permission' => "Permission insuffisante pour créer un contenu « {$type->label} »."],
                'httpCode' => $actor === null ? 401 : 403,
            ];
        }

        $targetStatus = (string) ($data['status'] ?? ($type->config['default_status'] ?? 'draft'));
        if (!empty($type->config['workflow']) && class_exists('\Core\Editorial\WorkflowManager')) {
            $wf = \Core\Editorial\WorkflowManager::getInstance();
            if (!$wf->isValidStatus($targetStatus)) {
                return ['success' => false, 'entry' => null, 'errors' => ['status' => "Statut éditorial invalide : {$targetStatus}"], 'httpCode' => 422];
            }
            if (!$wf->canSetInitialStatus($targetStatus, $actor, $type->permissions['publish'] ?? 'content.publish')) {
                return ['success' => false, 'entry' => null, 'errors' => ['status' => "Vous n'êtes pas autorisé à publier directement avec le statut « {$targetStatus} »."], 'httpCode' => 403];
            }
        }

        $fieldInput = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : $this->extractFieldInput($type, $data);
        $validation = FieldManager::getInstance()->validatePayload($type->getFields(), $fieldInput, $actor, false, [], $apiContext);
        $errors = $validation['errors'];

        if (isset($type->validation['callback']) && is_callable($type->validation['callback'])) {
            $customErrors = call_user_func($type->validation['callback'], $validation['values'], $data, null, $type);
            if (is_array($customErrors) && !empty($customErrors)) {
                $errors = array_merge($errors, $customErrors);
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'entry' => null, 'errors' => $errors, 'httpCode' => 422];
        }

        $blocks = is_array($data['blocks'] ?? null) ? $data['blocks'] : (is_array($data['blocks_data'] ?? null) ? $data['blocks_data'] : []);
        if (!empty($blocks) && class_exists('\Core\Blocks\BlockManager')) {
            $blockValidation = \Core\Blocks\BlockManager::getInstance()->validateComposition($blocks);
            if (!$blockValidation['valid']) {
                return ['success' => false, 'entry' => null, 'errors' => ['blocks' => implode(' | ', $blockValidation['errors'])], 'httpCode' => 422];
            }
            $blocks = $blockValidation['blocks'];
        }

        $title = $this->resolveTitle($type, $data, $validation['values']);
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '' && $title !== '') {
            $slug = Util::strToUrl($title);
        } elseif ($slug !== '') {
            $slug = Util::strToUrl($slug);
        }

        $now = date('Y-m-d H:i:s');
        $locale = strtolower(trim((string) ($data['locale'] ?? Core::getInstance()->getConfigVal('siteLang') ?: 'fr')));
        if ($slug !== '' && $this->hasSlugCollision($typeId, $slug, $locale)) {
            return ['success' => false, 'entry' => null, 'errors' => ['slug' => "Ce slug est déjà utilisé pour la langue « {$locale} »."], 'httpCode' => 422];
        }
        $entry = new ContentEntry([
            'content_type' => $typeId,
            'target_id' => (string) ($data['target_id'] ?? ''),
            'slug' => $slug,
            'title' => $title,
            'status' => $targetStatus,
            'author_id' => (string) ($data['author_id'] ?? ($actor ? $actor->getId() : '')),
            'locale' => $locale,
            'translation_group' => (string) ($data['translation_group'] ?? ''),
            'fields_data' => $validation['values'],
            'blocks_data' => $blocks,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Core::getInstance()->callHook('content:before-save', ['type' => $type, 'entry' => $entry, 'isNew' => true]);

        if (!$entry->save()) {
            return ['success' => false, 'entry' => null, 'errors' => ['storage' => 'Erreur lors de l\'enregistrement.'], 'httpCode' => 500];
        }

        $this->afterSaveSideEffects($type, $entry, $actor, (string) ($data['revision_summary'] ?? 'Création'));

        return ['success' => true, 'entry' => $entry, 'errors' => [], 'httpCode' => 201];
    }

    /**
     * Met à jour une entrée existante avec contrôle d'accès, workflow et révisions.
     *
     * @return array{success:bool,entry:?ContentEntry,errors:array<string,string>,httpCode?:int}
     */
    public function updateEntry(string $typeId, int|string $id, array $data, ?User $user = null, bool $partial = false, bool $apiContext = false): array
    {
        $type = $this->get($typeId);
        if ($type === null) {
            return ['success' => false, 'entry' => null, 'errors' => ['content_type' => "Type inconnu : {$typeId}"], 'httpCode' => 404];
        }

        $entry = ContentEntry::findPK((string) $id);
        if (!$entry instanceof ContentEntry || $entry->getContentType() !== $typeId) {
            return ['success' => false, 'entry' => null, 'errors' => ['id' => 'Contenu introuvable.'], 'httpCode' => 404];
        }

        $actor = $user ?? UsersManager::getCurrentUser();
        if (!$this->canPerform($typeId, 'edit', $actor, $entry) || !$this->isOwnerOrPrivileged($type, $entry, $actor)) {
            return [
                'success' => false,
                'forbidden' => true,
                'entry' => null,
                'errors' => ['permission' => "Permission insuffisante pour modifier ce contenu."],
                'httpCode' => $actor === null ? 401 : 403,
            ];
        }

        $currentStatus = $entry->getStatus();
        $targetStatus = array_key_exists('status', $data) ? (string) $data['status'] : $currentStatus;
        if ($targetStatus !== $currentStatus && !empty($type->config['workflow']) && class_exists('\Core\Editorial\WorkflowManager')) {
            $wf = \Core\Editorial\WorkflowManager::getInstance();
            if (!$wf->canTransition($currentStatus, $targetStatus, $actor, $type->permissions['publish'] ?? 'content.publish')) {
                return [
                    'success' => false,
                    'forbidden' => true,
                    'entry' => null,
                    'errors' => ['status' => "Transition de statut « {$currentStatus} » → « {$targetStatus} » non autorisée."],
                    'httpCode' => 403,
                ];
            }
        }

        $fieldInput = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : $this->extractFieldInput($type, $data);
        $validation = FieldManager::getInstance()->validatePayload($type->getFields(), $fieldInput, $actor, $partial, $entry->getFields(), $apiContext);
        $errors = $validation['errors'];

        if (isset($type->validation['callback']) && is_callable($type->validation['callback'])) {
            $customErrors = call_user_func($type->validation['callback'], $validation['values'], $data, $entry, $type);
            if (is_array($customErrors) && !empty($customErrors)) {
                $errors = array_merge($errors, $customErrors);
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'entry' => null, 'errors' => $errors, 'httpCode' => 422];
        }

        $blocks = $entry->getBlocks();
        if (array_key_exists('blocks', $data) || array_key_exists('blocks_data', $data)) {
            $rawBlocks = is_array($data['blocks'] ?? null) ? $data['blocks'] : (is_array($data['blocks_data'] ?? null) ? $data['blocks_data'] : []);
            if (class_exists('\Core\Blocks\BlockManager')) {
                $blockValidation = \Core\Blocks\BlockManager::getInstance()->validateComposition($rawBlocks);
                if (!$blockValidation['valid']) {
                    return ['success' => false, 'entry' => null, 'errors' => ['blocks' => implode(' | ', $blockValidation['errors'])], 'httpCode' => 422];
                }
                $blocks = $blockValidation['blocks'];
            } else {
                $blocks = $rawBlocks;
            }
        }

        $nextSlug = array_key_exists('slug', $data) && trim((string) $data['slug']) !== ''
            ? Util::strToUrl((string) $data['slug'])
            : $entry->getSlug();
        $nextLocale = array_key_exists('locale', $data)
            ? strtolower(trim((string) $data['locale']))
            : $entry->getLocale();
        if ((array_key_exists('slug', $data) || array_key_exists('locale', $data))
            && $nextSlug !== ''
            && $this->hasSlugCollision($typeId, $nextSlug, $nextLocale, $entry->getId())) {
            return ['success' => false, 'entry' => null, 'errors' => ['slug' => "Ce slug est déjà utilisé pour la langue « {$nextLocale} »."], 'httpCode' => 422];
        }

        $entry->setFields($validation['values']);
        $entry->setBlocks($blocks);
        $entry->attributes['status'] = $targetStatus;
        if (array_key_exists('title', $data)) {
            $entry->attributes['title'] = trim((string) $data['title']);
        } elseif ($entry->getTitle() === '') {
            $entry->attributes['title'] = $this->resolveTitle($type, $data, $validation['values']);
        }
        $entry->attributes['slug'] = $nextSlug;
        $entry->attributes['locale'] = $nextLocale;
        if (array_key_exists('translation_group', $data)) {
            $entry->attributes['translation_group'] = (string) $data['translation_group'];
        }
        $entry->attributes['updated_at'] = date('Y-m-d H:i:s');

        Core::getInstance()->callHook('content:before-save', ['type' => $type, 'entry' => $entry, 'isNew' => false]);

        if (!$entry->save()) {
            return ['success' => false, 'entry' => null, 'errors' => ['storage' => 'Erreur lors de la mise à jour.'], 'httpCode' => 500];
        }

        $this->afterSaveSideEffects($type, $entry, $actor, (string) ($data['revision_summary'] ?? 'Mise à jour'));

        return ['success' => true, 'entry' => $entry, 'errors' => [], 'httpCode' => 200];
    }

    public function getEntry(string $typeId, int|string $id, ?User $user = null): ?ContentEntry
    {
        $type = $this->get($typeId);
        if ($type === null) {
            return null;
        }
        $entry = ContentEntry::findPK((string) $id);
        if (!$entry instanceof ContentEntry || $entry->getContentType() !== $typeId) {
            return null;
        }
        if ($entry->getStatus() !== 'published') {
            return $this->isOwnerOrPrivileged($type, $entry, $user) ? $entry : null;
        }
        if (!$this->canPerform($typeId, 'view', $user, $entry)) {
            return null;
        }
        return $entry;
    }

    public function deleteEntry(string $typeId, int|string $id, ?User $user = null): bool
    {
        $type = $this->get($typeId);
        if ($type === null) {
            return false;
        }
        $entry = ContentEntry::findPK((string) $id);
        if (!$entry instanceof ContentEntry || $entry->getContentType() !== $typeId) {
            return false;
        }

        $actor = $user ?? UsersManager::getCurrentUser();
        if (!$this->canPerform($typeId, 'delete', $actor, $entry) || !$this->isOwnerOrPrivileged($type, $entry, $actor)) {
            return false;
        }

        Core::getInstance()->callHook('content:before-delete', ['type' => $type, 'entry' => $entry]);
        $deleted = $entry->delete();
        if ($deleted) {
            if (class_exists('\Core\Translation\TranslationManager')) {
                \Core\Translation\TranslationManager::getInstance()->removeEntityTranslations('content:' . $typeId, (string) $id);
            }
            Core::getInstance()->callHook('content:after-delete', ['type' => $type, 'id' => $id]);
        }
        return $deleted;
    }

    /**
     * Requête multi-stockage (JSON, SQLite, MySQL) avec filtrage sur colonnes natives et champs personnalisés.
     *
     * @return ContentEntry[]
     */
    public function findEntries(
        string $typeId,
        array $filters = [],
        array $orderBy = ['created_at' => 'desc'],
        ?int $limit = null,
        int $offset = 0,
        ?User $user = null
    ): array {
        $type = $this->get($typeId);
        if ($type === null || !$this->canPerform($typeId, 'view', $user)) {
            return [];
        }

        $nativeCols = ['id', 'target_id', 'slug', 'title', 'status', 'author_id', 'locale', 'translation_group', 'created_at', 'updated_at'];
        $qb = ContentEntry::queryBuilder()->where('content_type', '=', $typeId);

        $customFilters = [];
        foreach ($filters as $key => $filterVal) {
            if (in_array($key, $nativeCols, true)) {
                if (is_array($filterVal) && isset($filterVal['op']) && array_key_exists('value', $filterVal)) {
                    $qb->where($key, (string) $filterVal['op'], $filterVal['value']);
                } elseif (is_array($filterVal)) {
                    $qb->whereIn($key, $filterVal);
                } else {
                    $qb->where($key, '=', $filterVal);
                }
            } else {
                $customFilters[$key] = $filterVal;
            }
        }

        foreach ($orderBy as $col => $dir) {
            if (in_array($col, $nativeCols, true)) {
                $qb->orderBy($col, strtolower((string) $dir) === 'asc' ? 'asc' : 'desc');
            }
        }

        /** @var ContentEntry[] $entries */
        $entries = $qb->get();

        // Sécurité ABAC/statut/propriété : masquer les non-publiés à tout utilisateur non auteur et non privilégié
        $filtered = [];
        foreach ($entries as $entry) {
            if ($entry->getStatus() !== 'published' && !$this->isOwnerOrPrivileged($type, $entry, $user)) {
                continue;
            }
            if (!empty($customFilters) && !$this->matchesCustomFilters($entry, $customFilters)) {
                continue;
            }
            $filtered[] = $entry;
        }

        if ($offset > 0 || $limit !== null) {
            $filtered = array_slice($filtered, max(0, $offset), $limit);
        }

        return $filtered;
    }

    public function countEntries(string $typeId, array $filters = [], ?User $user = null): int
    {
        return count($this->findEntries($typeId, $filters, [], null, 0, $user));
    }

    /**
     * Associe ou met à jour des valeurs de champs personnalisés pour une entité existante (ex: PageItem).
     *
     * @return array{success:bool,entry:?ContentEntry,errors:array<string,string>}
     */
    public function saveTargetFields(string $targetType, int|string $targetId, array $fieldValues, ?User $user = null): array
    {
        $existing = ContentEntry::queryBuilder()
            ->where('content_type', '=', $targetType)
            ->where('target_id', '=', (string) $targetId)
            ->first();

        if ($existing instanceof ContentEntry) {
            return $this->updateEntry($targetType, $existing->getId(), [
                'fields' => $fieldValues,
                'status' => $existing->getStatus() ?: 'published',
            ], $user, true);
        }

        return $this->createEntry($targetType, [
            'target_id' => (string) $targetId,
            'title' => $targetType . '#' . $targetId,
            'status' => 'published',
            'fields' => $fieldValues,
        ], $user);
    }

    public function getTargetFields(string $targetType, int|string $targetId, ?User $user = null): array
    {
        $type = $this->get($targetType);
        $entry = ContentEntry::queryBuilder()
            ->where('content_type', '=', $targetType)
            ->where('target_id', '=', (string) $targetId)
            ->first();

        if (!$entry instanceof ContentEntry) {
            return [];
        }

        $fields = $entry->getFields();
        if ($type !== null) {
            return FieldManager::getInstance()->filterReadableValues($type->getFields(), $fields, $user);
        }
        return $fields;
    }

    private function afterSaveSideEffects(ContentTypeDefinition $type, ContentEntry $entry, ?User $actor, string $summary): void
    {
        if (!empty($type->config['revisions']) && class_exists('\Core\Editorial\RevisionManager')) {
            \Core\Editorial\RevisionManager::getInstance()->recordRevision(
                'content:' . $type->id,
                (string) $entry->getId(),
                [
                    'title' => $entry->getTitle(),
                    'slug' => $entry->getSlug(),
                    'status' => $entry->getStatus(),
                    'locale' => $entry->getLocale(),
                    'fields' => $entry->getFields(),
                    'blocks' => $entry->getBlocks(),
                ],
                $actor,
                $entry->getStatus(),
                $summary
            );
        }

        if (class_exists('\Core\Translation\TranslationManager') && $entry->getLocale() !== '') {
            $tm = \Core\Translation\TranslationManager::getInstance();
            $group = $tm->registerTranslation(
                'content:' . $type->id,
                (string) $entry->getId(),
                $entry->getLocale(),
                $entry->getTranslationGroup() !== '' ? $entry->getTranslationGroup() : null
            );
            if ($entry->getTranslationGroup() === '' && $group !== '') {
                $entry->attributes['translation_group'] = $group;
                $entry->save();
            }
            $tm->syncCommonFields($type, $entry);
        }

        Core::getInstance()->callHook('content:after-save', ['type' => $type, 'entry' => $entry]);
    }

    private function extractFieldInput(ContentTypeDefinition $type, array $data): array
    {
        $extracted = [];
        foreach ($type->getFields() as $field) {
            if (array_key_exists($field->name, $data)) {
                $extracted[$field->name] = $data[$field->name];
            }
        }
        return $extracted;
    }

    private function resolveTitle(ContentTypeDefinition $type, array $data, array $fields): string
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        $titleField = (string) ($type->display['title_field'] ?? 'title');
        if ($titleField !== '' && isset($fields[$titleField]) && is_scalar($fields[$titleField])) {
            return trim((string) $fields[$titleField]);
        }
        foreach (['title', 'name', 'nom', 'label'] as $candidate) {
            if (isset($fields[$candidate]) && is_scalar($fields[$candidate]) && trim((string) $fields[$candidate]) !== '') {
                return trim((string) $fields[$candidate]);
            }
        }
        return '';
    }

    private function matchesCustomFilters(ContentEntry $entry, array $filters): bool
    {
        $fields = $entry->getFields();
        foreach ($filters as $key => $expected) {
            $actual = $fields[$key] ?? null;

            if (is_array($expected) && isset($expected['op']) && array_key_exists('value', $expected)) {
                $op = strtolower((string) $expected['op']);
                $target = $expected['value'];
                $matched = match ($op) {
                    '=' => $actual == $target,
                    '!=' => $actual != $target,
                    '>' => $actual > $target,
                    '>=' => $actual >= $target,
                    '<' => $actual < $target,
                    '<=' => $actual <= $target,
                    'contains', 'like' => is_string($actual) && stripos($actual, trim((string) $target, '%')) !== false,
                    'in' => is_array($target) && (is_array($actual) ? !empty(array_intersect($actual, $target)) : in_array($actual, $target)),
                    default => false,
                };
                if (!$matched) {
                    return false;
                }
            } elseif (is_array($expected)) {
                if (is_array($actual)) {
                    if (empty(array_intersect($actual, $expected))) {
                        return false;
                    }
                } elseif (!in_array($actual, $expected)) {
                    return false;
                }
            } elseif (is_array($actual)) {
                if (!in_array($expected, $actual)) {
                    return false;
                }
            } elseif ($actual != $expected) {
                return false;
            }
        }
        return true;
    }

    private function registerCoreDefaults(): void
    {
        $this->registerDefinition([
            'id' => 'page',
            'label' => 'Page',
            'description' => 'Pages éditoriales natives du cœur.',
            'icon' => 'fa-regular fa-file-lines',
            'plugin' => 'core',
            'config' => [
                'revisions' => true,
                'workflow' => true,
                'translatable' => true,
                'rest_enabled' => true,
            ],
            'permissions' => [
                'view' => 'public',
                'create' => 'pages.edit',
                'edit' => 'pages.edit',
                'delete' => 'pages.manage',
                'publish' => 'pages.publish',
                'revisions' => 'content.revisions',
            ],
            'fields' => [],
        ]);
    }

    private function ensureHookTypesLoaded(): void
    {
        if ($this->hooksDispatched) {
            return;
        }
        $this->hooksDispatched = true;
        $this->loadPersistedTypes();
        if (class_exists('\Core\Core')) {
            Core::getInstance()->callHook('content:register-types', $this);
        }
    }
}

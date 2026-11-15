<?php

namespace Core\Editorial;

use Core\Auth\User;
use Core\Auth\UsersManager;
use Core\Content\ContentTypeManager;
use Core\Core;
use Core\Navigation\AccessPolicyEvaluator;
use Core\Navigation\ConditionContext;
use Core\Navigation\GenericResource;
use Core\Page\Page;
use Core\Page\PageItem;

defined('ROOT') or exit('Access denied!');

/**
 * Gestionnaire de révisions éditoriales : historisation anti-doublon, comparaison et restauration.
 */
class RevisionManager
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enregistre une nouvelle révision si le contenu a changé par rapport à la précédente.
     */
    public function recordRevision(
        string $entityType,
        int|string $entityId,
        array $snapshot,
        ?User $author = null,
        string $status = 'draft',
        string $summary = ''
    ): ?Revision {
        $canonical = $this->canonicalizeSnapshot($snapshot);
        $checksum = hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $latest = $this->getLatestRevision($entityType, $entityId);
        if ($latest !== null && $latest->getChecksum() === $checksum && $latest->getStatus() === $status) {
            return null;
        }

        $nextVersion = $latest !== null ? ($latest->getVersion() + 1) : 1;
        $actor = $author ?? UsersManager::getCurrentUser();

        $rev = new Revision([
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'version' => $nextVersion,
            'status' => $status,
            'author_id' => $actor ? (string) $actor->getId() : '',
            'author_name' => $actor ? ($actor->username() ?: $actor->getEmail()) : 'system',
            'summary' => trim($summary) !== '' ? trim($summary) : "Révision v{$nextVersion}",
            'checksum' => $checksum,
            'snapshot' => $canonical,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$rev->save()) {
            return null;
        }

        if (class_exists('\Core\Core')) {
            Core::getInstance()->callHook('editorial:revision-recorded', [
                'entityType' => $entityType,
                'entityId' => (string) $entityId,
                'revision' => $rev,
            ]);
        }

        return $rev;
    }

    /**
     * @return Revision[]
     */
    public function getHistory(string $entityType, int|string $entityId, ?int $limit = null): array
    {
        /** @var Revision[] $revs */
        $revs = Revision::queryBuilder()
            ->where('entity_type', '=', $entityType)
            ->where('entity_id', '=', (string) $entityId)
            ->orderBy('version', 'desc')
            ->get();

        if ($limit !== null && $limit > 0) {
            return array_slice($revs, 0, $limit);
        }
        return $revs;
    }

    public function getLatestRevision(string $entityType, int|string $entityId): ?Revision
    {
        $history = $this->getHistory($entityType, $entityId, 1);
        return $history[0] ?? null;
    }

    public function getRevision(int|string $revisionId): ?Revision
    {
        $rev = Revision::findPK((string) $revisionId);
        return $rev instanceof Revision ? $rev : null;
    }

    /**
     * Compare deux révisions et retourne un diff structuré par champ et par bloc.
     */
    public function compare(int|string $revIdA, int|string $revIdB): array
    {
        $revA = $this->getRevision($revIdA);
        $revB = $this->getRevision($revIdB);
        if ($revA === null || $revB === null) {
            return ['error' => 'Révision introuvable.'];
        }

        $snapA = $revA->getSnapshot();
        $snapB = $revB->getSnapshot();

        $fieldsDiff = [];
        $allKeys = array_unique(array_merge(array_keys($snapA), array_keys($snapB)));
        foreach ($allKeys as $key) {
            if ($key === 'blocks') {
                continue;
            }
            $valA = $snapA[$key] ?? null;
            $valB = $snapB[$key] ?? null;
            if ($valA !== $valB) {
                $fieldsDiff[$key] = ['from' => $valA, 'to' => $valB];
            }
        }

        $blocksDiff = $this->diffBlocks($snapA['blocks'] ?? [], $snapB['blocks'] ?? []);

        return [
            'from_version' => $revA->getVersion(),
            'to_version' => $revB->getVersion(),
            'status_changed' => $revA->getStatus() !== $revB->getStatus()
                ? ['from' => $revA->getStatus(), 'to' => $revB->getStatus()]
                : null,
            'fields' => $fieldsDiff,
            'blocks' => $blocksDiff,
        ];
    }

    /**
     * Restaure une révision vers l'entité cible (page ou content:<type>).
     *
     * @return array{success:bool,error:?string,entity:mixed}
     */
    public function restoreRevision(int|string $revisionId, ?User $actor = null): array
    {
        $rev = $this->getRevision($revisionId);
        if ($rev === null) {
            return ['success' => false, 'error' => 'Révision introuvable.', 'entity' => null];
        }

        $user = $actor ?? UsersManager::getCurrentUser();
        if (!$this->canRestore($user)) {
            return ['success' => false, 'error' => 'Permission insuffisante pour restaurer une révision.', 'entity' => null];
        }

        $snap = $rev->getSnapshot();
        $entityType = $rev->getEntityType();
        $entityId = $rev->getEntityId();

        if ($entityType === 'page') {
            $pageService = class_exists('\Core\Core') ? Core::getInstance()->pages() : new Page();
            $pageItem = PageItem::findPK($entityId);
            if (!$pageItem instanceof PageItem) {
                return ['success' => false, 'error' => 'Page cible introuvable.', 'entity' => null];
            }
            if (array_key_exists('name', $snap)) {
                $pageItem->setName((string) $snap['name']);
            }
            if (array_key_exists('content', $snap)) {
                $pageItem->setContent((string) $snap['content']);
            }
            if (array_key_exists('mainTitle', $snap)) {
                $pageItem->setMainTitle((string) $snap['mainTitle']);
            }
            if (array_key_exists('metaDescriptionTag', $snap)) {
                $pageItem->setMetaDescriptionTag((string) $snap['metaDescriptionTag']);
            }
            if (array_key_exists('metaTitleTag', $snap)) {
                $pageItem->setMetaTitleTag((string) $snap['metaTitleTag']);
            }
            if (array_key_exists('blocks', $snap) && method_exists($pageItem, 'setBlocks')) {
                $pageItem->setBlocks(is_array($snap['blocks']) ? $snap['blocks'] : []);
            }
            if (!$pageService->save($pageItem)) {
                return ['success' => false, 'error' => 'Échec lors de la sauvegarde de la page restaurée.', 'entity' => null];
            }
            $this->recordRevision('page', $entityId, $snap, $user, $pageItem->getStatus(), "Restauration de la révision v{$rev->getVersion()}");
            return ['success' => true, 'error' => null, 'entity' => $pageItem];
        }

        if (str_starts_with($entityType, 'content:')) {
            $typeId = substr($entityType, 8);
            $result = ContentTypeManager::getInstance()->updateEntry($typeId, $entityId, [
                'title' => $snap['title'] ?? '',
                'slug' => $snap['slug'] ?? '',
                'fields' => is_array($snap['fields'] ?? null) ? $snap['fields'] : [],
                'blocks' => is_array($snap['blocks'] ?? null) ? $snap['blocks'] : [],
                'revision_summary' => "Restauration de la révision v{$rev->getVersion()}",
            ], $user, false);

            return [
                'success' => $result['success'],
                'error' => $result['success'] ? null : implode(' | ', $result['errors']),
                'entity' => $result['entry'],
            ];
        }

        return ['success' => false, 'error' => "Type d'entité non supporté pour la restauration : {$entityType}", 'entity' => null];
    }

    private function canRestore(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        $evaluator = AccessPolicyEvaluator::getInstance();
        $ctx = new ConditionContext($user);
        return $evaluator->canAccess(
            new GenericResource('editorial:restore-revision', [['type' => 'permission', 'value' => ['content.revisions', 'pages.edit', 'pages.manage']]], ['PUB' => false]),
            $ctx
        );
    }

    private function diffBlocks(array $blocksA, array $blocksB): array
    {
        $mapA = [];
        foreach ($blocksA as $blk) {
            if (!empty($blk['id'])) {
                $mapA[(string) $blk['id']] = $blk;
            }
        }
        $mapB = [];
        foreach ($blocksB as $blk) {
            if (!empty($blk['id'])) {
                $mapB[(string) $blk['id']] = $blk;
            }
        }

        $added = array_values(array_diff_key($mapB, $mapA));
        $removed = array_values(array_diff_key($mapA, $mapB));
        $modified = [];
        foreach ($mapA as $id => $blkA) {
            if (isset($mapB[$id]) && $blkA !== $mapB[$id]) {
                $modified[$id] = ['from' => $blkA, 'to' => $mapB[$id]];
            }
        }

        return compact('added', 'removed', 'modified');
    }

    private function canonicalizeSnapshot(array $snapshot): array
    {
        ksort($snapshot);
        foreach ($snapshot as $k => $v) {
            if (is_array($v)) {
                $snapshot[$k] = $this->canonicalizeSnapshot($v);
            }
        }
        return $snapshot;
    }
}

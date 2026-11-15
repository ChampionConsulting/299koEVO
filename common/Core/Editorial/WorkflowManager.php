<?php

namespace Core\Editorial;

use Core\Auth\User;
use Core\Core;
use Core\Navigation\AccessPolicyEvaluator;
use Core\Navigation\ConditionContext;
use Core\Navigation\GenericResource;

defined('ROOT') or exit('Access denied!');

/**
 * Workflow éditorial extensible (draft, pending, published, archived) contrôlé par RBAC/ABAC.
 */
class WorkflowManager
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    private static ?self $instance = null;

    /** @var array<string,array{label:string,transitions:array<string,string>}> */
    private array $states = [
        self::STATUS_DRAFT => [
            'label' => 'Brouillon',
            'transitions' => [
                self::STATUS_PENDING => 'edit',
                self::STATUS_PUBLISHED => 'publish',
                self::STATUS_ARCHIVED => 'publish',
            ],
        ],
        self::STATUS_PENDING => [
            'label' => 'En attente de relecture',
            'transitions' => [
                self::STATUS_DRAFT => 'edit',
                self::STATUS_PUBLISHED => 'publish',
                self::STATUS_ARCHIVED => 'publish',
            ],
        ],
        self::STATUS_PUBLISHED => [
            'label' => 'Publié',
            'transitions' => [
                self::STATUS_DRAFT => 'publish',
                self::STATUS_PENDING => 'publish',
                self::STATUS_ARCHIVED => 'publish',
            ],
        ],
        self::STATUS_ARCHIVED => [
            'label' => 'Archivé',
            'transitions' => [
                self::STATUS_DRAFT => 'publish',
                self::STATUS_PUBLISHED => 'publish',
            ],
        ],
    ];

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

    public function getStatuses(): array
    {
        $labels = [];
        foreach ($this->states as $status => $meta) {
            $labels[$status] = $meta['label'];
        }
        return $labels;
    }

    public function isValidStatus(string $status): bool
    {
        return isset($this->states[$status]);
    }

    public function registerStatus(string $status, string $label, array $transitions = []): void
    {
        $this->states[$status] = [
            'label' => $label,
            'transitions' => $transitions,
        ];
    }

    public function canSetInitialStatus(string $targetStatus, ?User $user, string|array $publishPermission = 'content.publish', string|array $editPermission = 'content.edit'): bool
    {
        if (!$this->isValidStatus($targetStatus) || $user === null) {
            return false;
        }
        if (in_array($targetStatus, [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true)) {
            return $this->hasPermissionRule($user, $publishPermission);
        }
        return $this->hasPermissionRule($user, $editPermission) || $this->hasPermissionRule($user, $publishPermission);
    }

    public function canTransition(
        string $from,
        string $to,
        ?User $user,
        string|array $publishPermission = 'content.publish',
        string|array $editPermission = 'content.edit'
    ): bool {
        if (!$this->isValidStatus($from) || !$this->isValidStatus($to) || $user === null) {
            return false;
        }
        if ($from === $to) {
            return $this->hasPermissionRule($user, $editPermission) || $this->hasPermissionRule($user, $publishPermission);
        }
        $requirement = $this->states[$from]['transitions'][$to] ?? null;
        if ($requirement === null) {
            return false;
        }
        if ($requirement === 'publish') {
            return $this->hasPermissionRule($user, $publishPermission);
        }
        return $this->hasPermissionRule($user, $editPermission) || $this->hasPermissionRule($user, $publishPermission);
    }

    /**
     * Retourne les statuts cibles accessibles à l'utilisateur depuis un statut courant.
     *
     * @return array<string,string> [status => label]
     */
    public function getAvailableStatuses(
        string $currentStatus,
        ?User $user,
        string|array $publishPermission = 'content.publish',
        string|array $editPermission = 'content.edit'
    ): array {
        if (!$this->isValidStatus($currentStatus)) {
            $currentStatus = self::STATUS_DRAFT;
        }
        $allowed = [$currentStatus => $this->states[$currentStatus]['label']];
        foreach (array_keys($this->states) as $candidate) {
            if ($this->canTransition($currentStatus, $candidate, $user, $publishPermission, $editPermission)) {
                $allowed[$candidate] = $this->states[$candidate]['label'];
            }
        }
        return $allowed;
    }

    public function notifyTransition(string $entityType, int|string $entityId, string $from, string $to, ?User $user): void
    {
        if (class_exists('\Core\Core')) {
            Core::getInstance()->callHook('editorial:workflow-transition', [
                'entityType' => $entityType,
                'entityId' => (string) $entityId,
                'from' => $from,
                'to' => $to,
                'user' => $user,
            ]);
        }
    }

    private function hasPermissionRule(User $user, string|array $rule): bool
    {
        $evaluator = AccessPolicyEvaluator::getInstance();
        $ctx = new ConditionContext($user);
        $policy = is_array($rule) ? $rule : [['type' => 'permission', 'value' => $rule]];
        $resId = 'editorial:workflow:' . md5((string) json_encode($policy));
        return $evaluator->canAccess(new GenericResource($resId, $policy, ['PUB' => false]), $ctx);
    }
}

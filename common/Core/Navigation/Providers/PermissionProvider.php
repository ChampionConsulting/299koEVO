<?php

namespace Core\Navigation\Providers;

use Core\Navigation\ConditionContext;
use Core\Navigation\Interfaces\ConditionProviderInterface;

defined('ROOT') or exit('Access denied!');

/**
 * Fournisseur de condition basé sur les permissions RBAC (Permissions::keys()).
 * Type: 'permission'
 * Config: ['value' => 'content.publish'] ou ['operator' => 'in', 'value' => ['content.edit', 'pages.manage']]
 */
class PermissionProvider implements ConditionProviderInterface
{
    public function getId(): string
    {
        return 'permission';
    }

    public function evaluate(array $config, ConditionContext $ctx): bool
    {
        $required = $config['value'] ?? null;
        if ($required === null || $required === '' || $required === []) {
            return true;
        }

        $user = $ctx->getUser();
        if ($user === null) {
            return false;
        }

        $operator = strtolower((string) ($config['operator'] ?? '='));
        if (is_array($required) && $operator === '=') {
            $operator = 'in';
        }

        $checkSingle = static function (string $perm) use ($user): bool {
            if ($user->hasPermission($perm)) {
                return true;
            }
            if (($perm === 'pages.edit' || $perm === 'pages.publish') && $user->hasPermission('pages.manage')) {
                return true;
            }
            if (str_starts_with($perm, 'content.') && $user->hasPermission('content.manage')) {
                return true;
            }
            return false;
        };

        switch ($operator) {
            case 'all':
                foreach ((array) $required as $perm) {
                    if (!$checkSingle((string) $perm)) {
                        return false;
                    }
                }
                return true;

            case '!=':
            case 'not':
                foreach ((array) $required as $perm) {
                    if ($checkSingle((string) $perm)) {
                        return false;
                    }
                }
                return true;

            case 'in':
                foreach ((array) $required as $perm) {
                    if ($checkSingle((string) $perm)) {
                        return true;
                    }
                }
                return false;

            case '=':
            default:
                return $checkSingle((string) $required);
        }
    }

    public function getMetadata(): array
    {
        return [
            'label' => 'Permission RBAC',
            'description' => 'Vérifie une permission attribuée à l\'utilisateur ou à son groupe',
            'fields' => [
                'value' => [
                    'type' => 'text',
                    'label' => 'Permission',
                    'placeholder' => 'content.publish, pages.edit...',
                ],
                'operator' => [
                    'type' => 'select',
                    'label' => 'Opérateur',
                    'options' => [
                        '=' => 'Possède la permission',
                        'in' => 'Possède au moins une permission',
                        'all' => 'Possède toutes les permissions',
                        '!=' => 'Ne possède pas la permission',
                    ],
                    'default' => '=',
                ],
            ],
        ];
    }

    public function getCost(): int
    {
        return 1;
    }
}

<?php

namespace Core\Translation;

use Core\Storage\JsonActiveRecord;

defined('ROOT') or exit('Access denied!');

/**
 * Lien de traduction associant une entité (page ou contenu personnalisé) à une langue et un groupe.
 */
class TranslationLink extends JsonActiveRecord
{
    protected static string $filePath = DATA_CORE_CONTENT . 'translations.json';
    protected static string $tableName = 'core_translations';
    protected static string $primaryKey = 'id';
    protected static bool $autoIncrementId = true;

    protected static array $indexes = [
        'translation_group',
        'entity_type',
        'entity_id',
        'locale',
    ];

    protected static function getSchema(): array
    {
        return [
            'translation_group' => 'string',
            'entity_type' => 'string',
            'entity_id' => 'string',
            'locale' => 'string',
            'is_default' => 'int',
            'created_at' => 'string',
        ];
    }

    public function getGroup(): string
    {
        return (string) ($this->attributes['translation_group'] ?? '');
    }

    public function getEntityType(): string
    {
        return (string) ($this->attributes['entity_type'] ?? '');
    }

    public function getEntityId(): string
    {
        return (string) ($this->attributes['entity_id'] ?? '');
    }

    public function getLocale(): string
    {
        return (string) ($this->attributes['locale'] ?? '');
    }

    public function isDefault(): bool
    {
        return !empty($this->attributes['is_default']);
    }
}

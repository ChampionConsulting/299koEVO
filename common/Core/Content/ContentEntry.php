<?php

namespace Core\Content;

use Core\Navigation\Interfaces\MetadataAwareInterface;
use Core\Navigation\Interfaces\ResourceInterface;
use Core\Storage\JsonActiveRecord;

defined('ROOT') or exit('Access denied!');

/**
 * Enregistrement générique d'une entrée de type de contenu personnalisé ou de métadonnées
 * liées à une entité existante (ex: page). Compatible JSON, SQLite et MySQL.
 */
class ContentEntry extends JsonActiveRecord implements ResourceInterface, MetadataAwareInterface
{
    protected static string $filePath = DATA_CORE_CONTENT . 'entries.json';
    protected static string $tableName = 'core_content_entries';
    protected static string $primaryKey = 'id';
    protected static bool $autoIncrementId = true;

    protected static array $casts = [
        'fields_data' => 'array',
        'blocks_data' => 'array',
    ];

    protected static array $indexes = [
        'content_type',
        'target_id',
        'slug',
        'status',
        'locale',
        'translation_group',
    ];

    protected static function getSchema(): array
    {
        return [
            'content_type' => 'string',
            'target_id' => 'string',
            'slug' => 'string',
            'title' => 'string',
            'status' => 'string',
            'author_id' => 'string',
            'locale' => 'string',
            'translation_group' => 'string',
            'fields_data' => 'text',
            'blocks_data' => 'text',
            'created_at' => 'string',
            'updated_at' => 'string',
        ];
    }

    public function getId(): int|string|null
    {
        return $this->attributes['id'] ?? null;
    }

    public function getContentType(): string
    {
        return (string) ($this->attributes['content_type'] ?? '');
    }

    public function getTargetId(): string
    {
        return (string) ($this->attributes['target_id'] ?? '');
    }

    public function getTitle(): string
    {
        return (string) ($this->attributes['title'] ?? '');
    }

    public function getSlug(): string
    {
        return (string) ($this->attributes['slug'] ?? '');
    }

    public function getStatus(): string
    {
        return (string) ($this->attributes['status'] ?? 'draft');
    }

    public function getLocale(): string
    {
        return (string) ($this->attributes['locale'] ?? '');
    }

    public function getTranslationGroup(): string
    {
        return (string) ($this->attributes['translation_group'] ?? '');
    }

    public function getFields(): array
    {
        $fields = $this->attributes['fields_data'] ?? [];
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($fields) ? $fields : [];
    }

    public function setFields(array $fields): void
    {
        $this->attributes['fields_data'] = $fields;
    }

    public function getField(string $name, mixed $default = null): mixed
    {
        $fields = $this->getFields();
        return array_key_exists($name, $fields) ? $fields[$name] : $default;
    }

    public function setField(string $name, mixed $value): void
    {
        $fields = $this->getFields();
        $fields[$name] = $value;
        $this->attributes['fields_data'] = $fields;
    }

    public function getBlocks(): array
    {
        $blocks = $this->attributes['blocks_data'] ?? [];
        if (is_string($blocks)) {
            $decoded = json_decode($blocks, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($blocks) ? $blocks : [];
    }

    public function setBlocks(array $blocks): void
    {
        $this->attributes['blocks_data'] = $blocks;
    }

    public function getResourceId(): string
    {
        return 'content:' . $this->getContentType() . ':' . (string) $this->getId();
    }

    public function getPolicy(): array
    {
        if ($this->getStatus() !== 'published') {
            return [
                ['type' => 'permission', 'value' => ['content.edit', 'content.publish', 'pages.manage']],
            ];
        }
        return [];
    }

    public function getVisibilityFlags(): array
    {
        $isPublished = $this->getStatus() === 'published';
        return [
            'PUB' => $isPublished,
            'MEM' => false,
            'ADM' => !$isPublished,
        ];
    }

    public function getResourceParent(): ?ResourceInterface
    {
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'resource_id' => $this->getResourceId(),
            'content_type' => $this->getContentType(),
            'owner_id' => $this->attributes['author_id'] ?? null,
            'status' => $this->getStatus(),
            'locale' => $this->getLocale(),
        ];
    }

    public function toPublicArray(?ContentTypeDefinition $typeDef = null, ?\Core\Auth\User $viewer = null, bool $apiContext = false): array
    {
        $fields = $this->getFields();
        if ($typeDef !== null) {
            $fm = FieldManager::getInstance();
            $fields = $apiContext
                ? $fm->filterApiReadableValues($typeDef->getFields(), $fields, $viewer)
                : $fm->filterReadableValues($typeDef->getFields(), $fields, $viewer);
            if ($apiContext && !empty($typeDef->apiExposedFields)) {
                $allowedFields = array_flip($typeDef->apiExposedFields);
                $fields = array_intersect_key($fields, $allowedFields);
            }
        }

        return [
            'id' => $this->getId(),
            'content_type' => $this->getContentType(),
            'target_id' => $this->getTargetId(),
            'slug' => $this->getSlug(),
            'title' => $this->getTitle(),
            'status' => $this->getStatus(),
            'author_id' => $this->attributes['author_id'] ?? '',
            'locale' => $this->getLocale(),
            'translation_group' => $this->getTranslationGroup(),
            'fields' => $fields,
            'blocks' => $this->getBlocks(),
            'created_at' => $this->attributes['created_at'] ?? '',
            'updated_at' => $this->attributes['updated_at'] ?? '',
        ];
    }
}

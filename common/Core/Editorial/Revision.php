<?php

namespace Core\Editorial;

use Core\Storage\JsonActiveRecord;

defined('ROOT') or exit('Access denied!');

/**
 * Révision éditoriale horodatée pour une page ou un contenu personnalisé.
 */
class Revision extends JsonActiveRecord
{
    protected static string $filePath = DATA_CORE_CONTENT . 'revisions.json';
    protected static string $tableName = 'core_revisions';
    protected static string $primaryKey = 'id';
    protected static bool $autoIncrementId = true;

    protected static array $casts = [
        'snapshot' => 'array',
    ];

    protected static array $indexes = [
        'entity_type',
        'entity_id',
        'checksum',
        'created_at',
    ];

    protected static function getSchema(): array
    {
        return [
            'entity_type' => 'string',
            'entity_id' => 'string',
            'version' => 'int',
            'status' => 'string',
            'author_id' => 'string',
            'author_name' => 'string',
            'summary' => 'string',
            'checksum' => 'string',
            'snapshot' => 'text',
            'created_at' => 'string',
        ];
    }

    public function getId(): int|string|null
    {
        return $this->attributes['id'] ?? null;
    }

    public function getEntityType(): string
    {
        return (string) ($this->attributes['entity_type'] ?? '');
    }

    public function getEntityId(): string
    {
        return (string) ($this->attributes['entity_id'] ?? '');
    }

    public function getVersion(): int
    {
        return (int) ($this->attributes['version'] ?? 1);
    }

    public function getStatus(): string
    {
        return (string) ($this->attributes['status'] ?? 'draft');
    }

    public function getSummary(): string
    {
        return (string) ($this->attributes['summary'] ?? '');
    }

    public function getChecksum(): string
    {
        return (string) ($this->attributes['checksum'] ?? '');
    }

    public function getSnapshot(): array
    {
        $snap = $this->attributes['snapshot'] ?? [];
        if (is_string($snap)) {
            $decoded = json_decode($snap, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($snap) ? $snap : [];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'entity_type' => $this->getEntityType(),
            'entity_id' => $this->getEntityId(),
            'version' => $this->getVersion(),
            'status' => $this->getStatus(),
            'author_id' => (string) ($this->attributes['author_id'] ?? ''),
            'author_name' => (string) ($this->attributes['author_name'] ?? ''),
            'summary' => $this->getSummary(),
            'checksum' => $this->getChecksum(),
            'snapshot' => $this->getSnapshot(),
            'created_at' => (string) ($this->attributes['created_at'] ?? ''),
        ];
    }
}

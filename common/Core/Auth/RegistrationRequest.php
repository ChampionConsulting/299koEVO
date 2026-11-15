<?php

namespace Core\Auth;

use Core\Storage\JsonActiveRecord;

defined('ROOT') or exit('Access denied!');

class RegistrationRequest extends JsonActiveRecord
{
    protected static string $filePath = DATA_CORE_AUTH . 'registrations.json';
    protected static string $tableName = 'registration_requests';
    protected static string $primaryKey = 'token';

    protected static function getSchema(): array
    {
        return [
            'token' => 'VARCHAR(64)',
            'email' => 'VARCHAR(191)',
            'username' => 'VARCHAR(150)',
            'status' => 'VARCHAR(50)',
            'expires_at' => 'int',
            'created_at' => 'DATETIME',
            'approved_at' => 'DATETIME',
            'approved_by' => 'int',
        ];
    }

    public function isExpired(): bool
    {
        $ttl = (int) ($this->attributes['expires_at'] ?? 0);
        return $ttl > 0 && time() > $ttl;
    }
}


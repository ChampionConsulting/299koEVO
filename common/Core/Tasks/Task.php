<?php

declare(strict_types=1);

namespace Core\Tasks;

defined('ROOT') or exit('Access denied!');

/**
 * Entité représentant une tâche asynchrone persistante.
 */
class Task
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public string $id;
    public string $handler;
    public array $payload;
    public string $status;
    public int $runAt;
    public int $attempts;
    public int $maxRetries;
    public int $priority;
    public int $createdAt;
    public ?int $startedAt = null;
    public ?int $completedAt = null;
    public ?string $lastError = null;

    public function __construct(array $data)
    {
        $this->id = (string)($data['id'] ?? uniqid('task_', true));
        $this->handler = (string)($data['handler'] ?? '');
        $this->payload = (array)($data['payload'] ?? []);
        $this->status = (string)($data['status'] ?? self::STATUS_PENDING);
        $this->runAt = (int)($data['run_at'] ?? ($data['runAt'] ?? time()));
        $this->attempts = (int)($data['attempts'] ?? 0);
        $this->maxRetries = (int)($data['max_retries'] ?? ($data['maxRetries'] ?? 3));
        $this->priority = (int)($data['priority'] ?? 0);
        $this->createdAt = (int)($data['created_at'] ?? ($data['createdAt'] ?? time()));
        $this->startedAt = isset($data['started_at']) ? (int)$data['started_at'] : (isset($data['startedAt']) ? (int)$data['startedAt'] : null);
        $this->completedAt = isset($data['completed_at']) ? (int)$data['completed_at'] : (isset($data['completedAt']) ? (int)$data['completedAt'] : null);
        $this->lastError = isset($data['last_error']) ? (string)$data['last_error'] : (isset($data['lastError']) ? (string)$data['lastError'] : null);
    }

    public function isReady(int $now): bool
    {
        return $this->status === self::STATUS_PENDING && $this->runAt <= $now;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'handler' => $this->handler,
            'payload' => $this->payload,
            'status' => $this->status,
            'run_at' => $this->runAt,
            'attempts' => $this->attempts,
            'max_retries' => $this->maxRetries,
            'priority' => $this->priority,
            'created_at' => $this->createdAt,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'last_error' => $this->lastError,
        ];
    }
}

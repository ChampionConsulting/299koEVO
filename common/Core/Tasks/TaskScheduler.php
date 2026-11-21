<?php

declare(strict_types=1);

namespace Core\Tasks;

use Core\Core;
use Utils\Util;

defined('ROOT') or exit('Access denied!');

/**
 * Ordonnanceur et gestionnaire de file d'attente de tâches asynchrones pour 299ko EVO.
 * 
 * Assure la persistance (JSON flat-file sécurisé), le verrouillage anti-concurrence (flock),
 * les relances automatiques avec rétrogradation (exponential backoff), et l'exécution
 * de fond non-bloquante pour newsletters, flux RSS et publications programmées.
 */
class TaskScheduler
{
    private static ?TaskScheduler $instance = null;
    private string $storageDir;
    private string $queueFile;
    private string $historyFile;
    private string $lockFile;
    private string $stateFile;

    public function __construct(?string $storageDir = null)
    {
        $base = $storageDir ?? (defined('DATA_CORE') ? DATA_CORE . 'tasks/' : ROOT . 'data/core/tasks/');
        $this->storageDir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;
        $this->queueFile = $this->storageDir . 'queue.json';
        $this->historyFile = $this->storageDir . 'history.json';
        $this->lockFile = $this->storageDir . 'scheduler.lock';
        $this->stateFile = $this->storageDir . 'scheduler_state.json';
        $this->ensureDirectories();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensureDirectories(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Ajoute une nouvelle tâche dans la file d'attente persistante.
     *
     * @param string $handler Nom de la fonction, 'Class::method', 'Class#method', ou 'hook:nom_du_hook'
     * @param array $payload Paramètres transmis à l'exécuteur
     * @param int|null $runAt Timestamp Unix d'exécution (défaut: immédiat)
     * @param int $maxRetries Nombre maximal de tentatives avant abandon
     * @param int $priority Priorité numérique (plus élevé = prioritaire)
     * @return string ID unique de la tâche
     */
    public function enqueue(string $handler, array $payload = [], ?int $runAt = null, int $maxRetries = 3, int $priority = 0): string
    {
        $task = new Task([
            'id' => uniqid('tsk_', true),
            'handler' => $handler,
            'payload' => $payload,
            'status' => Task::STATUS_PENDING,
            'run_at' => $runAt ?? time(),
            'max_retries' => $maxRetries,
            'priority' => $priority,
            'created_at' => time(),
        ]);

        $this->withLock(function () use ($task) {
            $queue = $this->loadQueue();
            $queue[$task->id] = $task->toArray();
            $this->saveQueue($queue);
        });

        return $task->id;
    }

    /**
     * Planifie ou met à jour une tâche périodique nommée (idempotente).
     */
    public function schedule(string $id, string $handler, array $payload = [], int $delaySeconds = 3600): string
    {
        $this->withLock(function () use ($id, $handler, $payload, $delaySeconds) {
            $queue = $this->loadQueue();
            if (isset($queue[$id]) && $queue[$id]['status'] === Task::STATUS_PENDING) {
                return; // Déjà programmée
            }
            $task = new Task([
                'id' => $id,
                'handler' => $handler,
                'payload' => $payload,
                'status' => Task::STATUS_PENDING,
                'run_at' => time() + $delaySeconds,
                'max_retries' => 5,
                'priority' => 10,
                'created_at' => time(),
            ]);
            $queue[$id] = $task->toArray();
            $this->saveQueue($queue);
        });

        return $id;
    }

    /**
     * Exécute les tâches échues dans la file d'attente avec verrou exclusif anti-concurrence.
     *
     * @param int $limit Nombre maximal de tâches traitées par lot
     * @param int $maxDuration Durée maximale en secondes avant restitution
     * @return array Rapport d'exécution (exécutées, réussies, échouées)
     */
    public function runPending(int $limit = 10, int $maxDuration = 25): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'tasks' => []
        ];

        $startTime = microtime(true);

        $executed = $this->withLock(function () use ($limit, $maxDuration, $startTime, &$stats) {
            $now = time();
            $queue = $this->loadQueue();
            if (empty($queue)) {
                return $stats;
            }

            // Trier par priorité décroissante, puis par run_at croissant
            uasort($queue, function ($a, $b) {
                if (($a['priority'] ?? 0) !== ($b['priority'] ?? 0)) {
                    return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
                }
                return ($a['run_at'] ?? 0) <=> ($b['run_at'] ?? 0);
            });

            $processedCount = 0;
            $history = $this->loadHistory();

            foreach ($queue as $taskId => &$taskData) {
                if ($processedCount >= $limit || (microtime(true) - $startTime) >= $maxDuration) {
                    break;
                }

                $task = new Task($taskData);
                if (!$task->isReady($now)) {
                    continue;
                }

                $processedCount++;
                $task->status = Task::STATUS_PROCESSING;
                $task->startedAt = time();
                $task->attempts++;
                $taskData = $task->toArray();
                $this->saveQueue($queue);

                $success = false;
                $errorMessage = null;

                try {
                    $this->executeHandler($task->handler, $task->payload);
                    $success = true;
                } catch (\Throwable $e) {
                    $errorMessage = $e->getMessage();
                }

                if ($success) {
                    $task->status = Task::STATUS_COMPLETED;
                    $task->completedAt = time();
                    $task->lastError = null;
                    $stats['success']++;

                    // Déplacer vers l'historique et retirer de la file active
                    $history[] = $task->toArray();
                    unset($queue[$taskId]);
                } else {
                    $stats['failed']++;
                    $task->lastError = $errorMessage;

                    if ($task->attempts < $task->maxRetries) {
                        // Rétrogradation exponentielle (1 min, 5 min, 20 min, 1h...)
                        $backoff = 60 * (int)pow(4, $task->attempts - 1);
                        $task->status = Task::STATUS_PENDING;
                        $task->runAt = time() + $backoff;
                        $taskData = $task->toArray();
                    } else {
                        $task->status = Task::STATUS_FAILED;
                        $task->completedAt = time();
                        $history[] = $task->toArray();
                        unset($queue[$taskId]);
                    }
                }

                $stats['processed']++;
                $stats['tasks'][] = [
                    'id' => $task->id,
                    'handler' => $task->handler,
                    'status' => $task->status,
                    'attempts' => $task->attempts,
                    'error' => $errorMessage
                ];

                $this->saveQueue($queue);
            }

            // Conserver au maximum les 200 dernières entrées d'historique
            if (count($history) > 200) {
                $history = array_slice($history, -200);
            }
            $this->saveHistory($history);

            $this->updateLastRunState($stats);
            return $stats;
        });

        return is_array($executed) ? $executed : $stats;
    }

    /**
     * Exécute un gestionnaire dynamique (hook, méthode de classe, fonction).
     */
    private function executeHandler(string $handler, array $payload): void
    {
        // 1. Hook du CMS (ex: hook:blog_publish_scheduled)
        if (str_starts_with($handler, 'hook:')) {
            $hookName = substr($handler, 5);
            if (class_exists(Core::class)) {
                Core::getInstance()->callHook($hookName, $payload);
            }
            return;
        }

        // 2. Méthode statique ou d'instance Class::method ou Class#method
        if (strpos($handler, '::') !== false || strpos($handler, '#') !== false) {
            $parts = preg_split('/(::|#)/', $handler, 2);
            $className = $parts[0];
            $methodName = $parts[1];

            if (!class_exists($className)) {
                throw new \RuntimeException("Classe de tâche introuvable : {$className}");
            }
            if (!method_exists($className, $methodName)) {
                throw new \RuntimeException("Méthode de tâche introuvable : {$className}::{$methodName}");
            }

            $ref = new \ReflectionMethod($className, $methodName);
            if ($ref->isStatic()) {
                forward_static_call([$className, $methodName], $payload);
            } else {
                $instance = new $className();
                $instance->$methodName($payload);
            }
            return;
        }

        // 3. Fonction globale
        if (function_exists($handler)) {
            call_user_func($handler, $payload);
            return;
        }

        throw new \RuntimeException("Gestionnaire de tâche non résolu : {$handler}");
    }

    /**
     * Déclencheur pseudo-cron pour requêtes Web (asynchrone, throttling 60s).
     * S'exécute à la fermeture de la connexion client sans bloquer le rendu.
     */
    public function registerWebTrigger(): void
    {
        $lastRun = $this->getLastRunTime();
        if ((time() - $lastRun) < 60) {
            return; // Déjà exécuté récemment
        }

        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $this->runPending(5, 10);
        });
    }

    public function listTasks(?string $status = null): array
    {
        $queue = $this->loadQueue();
        if ($status === null) {
            return array_values($queue);
        }
        return array_values(array_filter($queue, fn($t) => ($t['status'] ?? '') === $status));
    }

    public function cancel(string $taskId): bool
    {
        return (bool)$this->withLock(function () use ($taskId) {
            $queue = $this->loadQueue();
            if (isset($queue[$taskId])) {
                $queue[$taskId]['status'] = Task::STATUS_CANCELLED;
                unset($queue[$taskId]);
                $this->saveQueue($queue);
                return true;
            }
            return false;
        });
    }

    public function purge(int $maxAgeDays = 7): int
    {
        return (int)$this->withLock(function () use ($maxAgeDays) {
            $cutoff = time() - ($maxAgeDays * 86400);
            $history = $this->loadHistory();
            $initialCount = count($history);
            $history = array_filter($history, fn($t) => ($t['completed_at'] ?? $t['created_at'] ?? 0) >= $cutoff);
            $this->saveHistory(array_values($history));
            return $initialCount - count($history);
        });
    }

    public function getStats(): array
    {
        $queue = $this->loadQueue();
        $history = $this->loadHistory();
        $pending = 0;
        $processing = 0;
        $failed = 0;

        foreach ($queue as $t) {
            $st = $t['status'] ?? Task::STATUS_PENDING;
            if ($st === Task::STATUS_PENDING) $pending++;
            elseif ($st === Task::STATUS_PROCESSING) $processing++;
            elseif ($st === Task::STATUS_FAILED) $failed++;
        }

        return [
            'pending' => $pending,
            'processing' => $processing,
            'failed' => $failed,
            'history_count' => count($history),
            'last_run' => $this->getLastRunTime(),
        ];
    }

    private function getLastRunTime(): int
    {
        if (file_exists($this->stateFile)) {
            $state = json_decode((string)@file_get_contents($this->stateFile), true);
            return (int)($state['last_run_time'] ?? 0);
        }
        return 0;
    }

    private function updateLastRunState(array $stats): void
    {
        $data = [
            'last_run_time' => time(),
            'last_stats' => $stats
        ];
        @file_put_contents($this->stateFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function loadQueue(): array
    {
        if (!file_exists($this->queueFile)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($this->queueFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveQueue(array $queue): void
    {
        $this->ensureDirectories();
        @file_put_contents($this->queueFile, json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function loadHistory(): array
    {
        if (!file_exists($this->historyFile)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($this->historyFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveHistory(array $history): void
    {
        $this->ensureDirectories();
        @file_put_contents($this->historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Exécute un callback sous verrou flock exclusif anti-concurrence.
     */
    private function withLock(callable $callback)
    {
        $this->ensureDirectories();
        $fp = @fopen($this->lockFile, 'c+');
        if (!$fp) {
            return $callback();
        }

        $locked = @flock($fp, LOCK_EX);
        if (!$locked) {
            fclose($fp);
            return null; // Une autre instance est déjà en train de traiter la file
        }

        try {
            return $callback();
        } finally {
            @flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}

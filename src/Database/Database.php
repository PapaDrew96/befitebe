<?php

declare(strict_types=1);

namespace Befit\Database;

use PDO;
use Throwable;

final class Database
{
    private PDO $pdo;
    private array $afterCommitCallbacks = [];

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function afterCommit(callable $callback): void
    {
        if (!$this->pdo->inTransaction()) {
            $callback();
            return;
        }

        $this->afterCommitCallbacks[] = $callback;
    }

    public function transaction(callable $callback): mixed
    {
        $this->afterCommitCallbacks = [];
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();

            $callbacks = $this->afterCommitCallbacks;
            $this->afterCommitCallbacks = [];

            foreach ($callbacks as $afterCommit) {
                $afterCommit();
            }

            return $result;
        } catch (Throwable $exception) {
            $this->afterCommitCallbacks = [];

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function ping(): bool
    {
        return (bool) $this->pdo->query('SELECT 1')->fetchColumn();
    }
}

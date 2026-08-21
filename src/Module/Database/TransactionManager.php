<?php

declare(strict_types=1);

namespace Micro\Module\Database;

use PDO;
use Throwable;

final readonly class TransactionManager
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * Get the underlying PDO instance.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a callback inside an atomic database transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

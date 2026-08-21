<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Database;

use Exception;
use Micro\Module\Database\TransactionManager;
use PDO;
use PHPUnit\Framework\TestCase;

final class TransactionManagerTest extends TestCase
{
    private PDO $pdo;
    private TransactionManager $tm;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $this->tm = new TransactionManager($this->pdo);
    }

    public function testSuccessfulTransactionCommits(): void
    {
        $result = $this->tm->transaction(function (): string {
            $this->pdo->exec("INSERT INTO items (name) VALUES ('Widget')");
            return 'inserted';
        });

        $this->assertSame('inserted', $result);
        $this->assertFalse($this->pdo->inTransaction());

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testFailedTransactionRollsBack(): void
    {
        try {
            $this->tm->transaction(function (): void {
                $this->pdo->exec("INSERT INTO items (name) VALUES ('Gizmo')");
                throw new Exception('Simulated database write error');
            });
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('Simulated database write error', $e->getMessage());
        }

        $this->assertFalse($this->pdo->inTransaction());
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testNestedTransactionExecution(): void
    {
        $this->tm->transaction(function (): void {
            $this->pdo->exec("INSERT INTO items (name) VALUES ('Outer')");

            // Nested call should participate in existing transaction
            $this->tm->transaction(function (): void {
                $this->pdo->exec("INSERT INTO items (name) VALUES ('Inner')");
            });
        });

        $this->assertFalse($this->pdo->inTransaction());
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        $this->assertSame(2, $count);
    }
}

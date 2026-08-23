<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Database;

use Micro\Module\Database\ConnectionFactory;
use Micro\Module\Database\DatabaseConfig;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testCreateFromDsnWithSqliteMemory(): void
    {
        $pdo = ConnectionFactory::createFromDsn('sqlite::memory:');

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testCreateWithDatabaseConfigHavingDsn(): void
    {
        $config = new DatabaseConfig(dsn: 'sqlite::memory:');
        $pdo = ConnectionFactory::create($config);

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testCreateFromDsnWithCustomOptions(): void
    {
        $pdo = ConnectionFactory::createFromDsn('sqlite::memory:', options: [
            PDO::ATTR_CASE => PDO::CASE_LOWER,
        ]);

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::CASE_LOWER, $pdo->getAttribute(PDO::ATTR_CASE));
    }

    public function testInvalidDsnThrowsPdoException(): void
    {
        $this->expectException(PDOException::class);

        ConnectionFactory::createFromDsn('invalid_driver:foo=bar');
    }
}

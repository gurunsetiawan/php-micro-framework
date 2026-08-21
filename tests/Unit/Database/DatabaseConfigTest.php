<?php

declare(strict_types=1);

namespace Micro\Tests\Unit\Database;

use Micro\Module\Database\DatabaseConfig;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new DatabaseConfig();

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(3306, $config->port);
        $this->assertSame('', $config->database);
        $this->assertSame('root', $config->username);
        $this->assertSame('', $config->password);
        $this->assertSame('utf8mb4', $config->charset);
        $this->assertSame([], $config->options);
    }

    public function testFromEnvWithExplicitArray(): void
    {
        $env = [
            'DB_HOST' => '10.0.0.5',
            'DB_PORT' => '3307',
            'DB_DATABASE' => 'app_production',
            'DB_USERNAME' => 'db_user',
            'DB_PASSWORD' => 'secret_pass',
            'DB_CHARSET' => 'utf8mb4',
        ];

        $config = DatabaseConfig::fromEnv($env);

        $this->assertSame('10.0.0.5', $config->host);
        $this->assertSame(3307, $config->port);
        $this->assertSame('app_production', $config->database);
        $this->assertSame('db_user', $config->username);
        $this->assertSame('secret_pass', $config->password);
        $this->assertSame('utf8mb4', $config->charset);
    }

    public function testFromEnvFallbackToAliases(): void
    {
        $env = [
            'DB_NAME' => 'alias_db',
            'DB_USER' => 'alias_user',
            'DB_PASS' => 'alias_pass',
        ];

        $config = DatabaseConfig::fromEnv($env);

        $this->assertSame('127.0.0.1', $config->host);
        $this->assertSame(3306, $config->port);
        $this->assertSame('alias_db', $config->database);
        $this->assertSame('alias_user', $config->username);
        $this->assertSame('alias_pass', $config->password);
    }
}

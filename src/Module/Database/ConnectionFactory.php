<?php

declare(strict_types=1);

namespace Micro\Module\Database;

use PDO;

final readonly class ConnectionFactory
{
    /**
     * Default PDO configuration options.
     *
     * @var array<int, mixed>
     */
    public const array DEFAULT_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /**
     * Create and configure a PDO instance from DatabaseConfig.
     */
    public static function create(DatabaseConfig $config): PDO
    {
        if ($config->dsn !== null && $config->dsn !== '') {
            return self::createFromDsn(
                dsn: $config->dsn,
                username: $config->username !== '' ? $config->username : null,
                password: $config->password !== '' ? $config->password : null,
                options: $config->options,
            );
        }

        $dsnParts = [
            "host={$config->host}",
            "port={$config->port}",
            "charset={$config->charset}",
        ];

        if ($config->database !== '') {
            $dsnParts[] = "dbname={$config->database}";
        }

        $dsn = 'mysql:' . implode(';', $dsnParts);

        return self::createFromDsn(
            dsn: $dsn,
            username: $config->username,
            password: $config->password,
            options: $config->options,
        );
    }

    /**
     * Create and configure a PDO instance directly from a DSN string.
     *
     * @param array<int, mixed> $options
     */
    public static function createFromDsn(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
    ): PDO {
        $resolvedOptions = self::DEFAULT_OPTIONS;
        foreach ($options as $key => $value) {
            $resolvedOptions[$key] = $value;
        }

        return new PDO(
            $dsn,
            $username,
            $password,
            $resolvedOptions,
        );
    }
}

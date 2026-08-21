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
        $dsnParts = [
            "host={$config->host}",
            "port={$config->port}",
            "charset={$config->charset}",
        ];

        if ($config->database !== '') {
            $dsnParts[] = "dbname={$config->database}";
        }

        $dsn = 'mysql:' . implode(';', $dsnParts);

        $options = self::DEFAULT_OPTIONS;
        foreach ($config->options as $key => $value) {
            $options[$key] = $value;
        }

        return new PDO(
            $dsn,
            $config->username,
            $config->password,
            $options,
        );
    }
}

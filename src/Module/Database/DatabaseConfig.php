<?php

declare(strict_types=1);

namespace Micro\Module\Database;

final readonly class DatabaseConfig
{
    /**
     * @param array<int, mixed> $options
     */
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 3306,
        public string $database = '',
        public string $username = 'root',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = [],
    ) {
    }

    /**
     * Create a DatabaseConfig instance from an environment array or getenv().
     *
     * @param array<string, string> $env
     */
    public static function fromEnv(array $env = []): self
    {
        $get = static function (string $key, ?string $default = null) use ($env): ?string {
            if (!empty($env)) {
                return $env[$key] ?? $default;
            }

            $val = getenv($key);
            if ($val !== false && $val !== '') {
                return (string) $val;
            }

            if (isset($_ENV[$key]) && (string) $_ENV[$key] !== '') {
                return (string) $_ENV[$key];
            }

            if (isset($_SERVER[$key]) && (string) $_SERVER[$key] !== '') {
                return (string) $_SERVER[$key];
            }

            return $default;
        };

        $host = $get('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
        $port = (int) ($get('DB_PORT', '3306') ?? '3306');
        $database = $get('DB_DATABASE', $get('DB_NAME', '')) ?? '';
        $username = $get('DB_USERNAME', $get('DB_USER', 'root')) ?? 'root';
        $password = $get('DB_PASSWORD', $get('DB_PASS', '')) ?? '';
        $charset = $get('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

        return new self(
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
        );
    }
}

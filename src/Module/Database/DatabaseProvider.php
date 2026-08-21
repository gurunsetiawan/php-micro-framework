<?php

declare(strict_types=1);

namespace Micro\Module\Database;

use Micro\Container\Container;
use PDO;

final readonly class DatabaseProvider
{
    public function register(Container $container, ?DatabaseConfig $config = null): void
    {
        $resolvedConfig = $config ?? DatabaseConfig::fromEnv();

        $container->singleton(DatabaseConfig::class, $resolvedConfig);

        $container->singleton(PDO::class, static function () use ($resolvedConfig): PDO {
            return ConnectionFactory::create($resolvedConfig);
        });

        $container->singleton(TransactionManager::class, static function (Container $c): TransactionManager {
            return new TransactionManager($c->get(PDO::class));
        });
    }
}

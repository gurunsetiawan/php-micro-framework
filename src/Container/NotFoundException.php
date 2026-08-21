<?php

declare(strict_types=1);

namespace Micro\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}

<?php

declare(strict_types=1);

namespace Micro\Container;

use Micro\Exception\FrameworkException;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends FrameworkException implements ContainerExceptionInterface
{
}

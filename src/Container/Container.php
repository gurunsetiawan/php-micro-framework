<?php

declare(strict_types=1);

namespace Micro\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

final class Container implements ContainerInterface
{
    /**
     * @var array<string, callable|string>
     */
    private array $bindings = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, bool>
     */
    private array $singletons = [];

    /**
     * Stack to track building classes and detect circular dependencies.
     *
     * @var array<string, bool>
     */
    private array $buildStack = [];

    /**
     * Register a factory binding.
     *
     * @param string $id
     * @param callable|string|null $concrete
     */
    public function bind(string $id, callable|string|null $concrete = null): self
    {
        $this->bindings[$id] = $concrete ?? $id;
        unset($this->instances[$id], $this->singletons[$id]);
        return $this;
    }

    /**
     * Register a singleton binding (instantiated once and cached).
     *
     * @param string $id
     * @param callable|string|object|null $concrete
     */
    public function singleton(string $id, callable|string|object|null $concrete = null): self
    {
        if (is_object($concrete) && !$concrete instanceof Closure) {
            $this->instances[$id] = $concrete;
        } else {
            $this->bindings[$id] = $concrete ?? $id;
            $this->singletons[$id] = true;
        }
        return $this;
    }

    /**
     * Set an existing object instance as a singleton.
     */
    public function instance(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;
        return $this;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];

            if ($concrete instanceof Closure || is_callable($concrete)) {
                $object = $concrete($this);
            } elseif (is_string($concrete)) {
                $object = $this->build($concrete);
            } else {
                throw new ContainerException("Invalid binding registered for [{$id}].");
            }

            if (isset($this->singletons[$id])) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        if (class_exists($id)) {
            $object = $this->build($id);

            if (isset($this->singletons[$id])) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        throw new NotFoundException("Identifier [{$id}] is not bound and cannot be resolved.");
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    /**
     * Instantiate a concrete class using constructor reflection autowiring.
     *
     * @template T of object
     * @param class-string<T> $concrete
     * @return T
     */
    public function build(string $concrete): object
    {
        if (isset($this->buildStack[$concrete])) {
            $cycle = implode(' -> ', array_keys($this->buildStack)) . ' -> ' . $concrete;
            throw new ContainerException("Circular dependency detected while resolving [{$concrete}]: {$cycle}");
        }

        $this->buildStack[$concrete] = true;

        try {
            $reflector = new ReflectionClass($concrete);

            if (!$reflector->isInstantiable()) {
                throw new ContainerException("Target [{$concrete}] is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $concrete();
            }

            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters, $concrete);

            return $reflector->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to build [{$concrete}]: " . $e->getMessage(), 0, $e);
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }

    /**
     * Call a given callable and inject its dependencies.
     *
     * @param callable|array{object|string, string} $callable
     * @param array<string, mixed> $parameters
     */
    public function call(callable|array $callable, array $parameters = []): mixed
    {
        try {
            if (is_array($callable)) {
                [$target, $method] = $callable;
                $object = is_string($target) ? $this->get($target) : $target;
                $reflector = new ReflectionMethod($object, $method);
                $dependencies = $this->resolveParameters($reflector->getParameters(), $parameters);
                return $reflector->invokeArgs($object, $dependencies);
            }

            if ($callable instanceof Closure || is_string($callable)) {
                $reflector = new ReflectionFunction($callable);
                $dependencies = $this->resolveParameters($reflector->getParameters(), $parameters);
                return $reflector->invokeArgs($dependencies);
            }

            if (is_object($callable) && method_exists($callable, '__invoke')) {
                $reflector = new ReflectionMethod($callable, '__invoke');
                $dependencies = $this->resolveParameters($reflector->getParameters(), $parameters);
                return $reflector->invokeArgs($callable, $dependencies);
            }

            throw new ContainerException('Target callable is not valid.');
        } catch (Throwable $e) {
            if ($e instanceof ContainerException) {
                throw $e;
            }
            throw new ContainerException('Failed to invoke callable: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve an array of constructor ReflectionParameters.
     *
     * @param list<ReflectionParameter> $parameters
     * @return list<mixed>
     */
    private function resolveDependencies(array $parameters, string $contextClass): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                try {
                    $dependencies[] = $this->get($className);
                    continue;
                } catch (NotFoundException $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }

                    if ($parameter->allowsNull()) {
                        $dependencies[] = null;
                        continue;
                    }

                    throw new ContainerException(
                        "Cannot resolve dependency [{$className}] for parameter [\${$name}] in class [{$contextClass}].",
                        0,
                        $e
                    );
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new ContainerException(
                "Cannot resolve un-typed or primitive parameter [\${$name}] in class [{$contextClass}]."
            );
        }

        return $dependencies;
    }

    /**
     * Resolve callable parameters combining provided arguments and container bindings.
     *
     * @param list<ReflectionParameter> $reflectionParams
     * @param array<string, mixed> $providedParams
     * @return list<mixed>
     */
    private function resolveParameters(array $reflectionParams, array $providedParams): array
    {
        $resolved = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $providedParams)) {
                $resolved[] = $providedParams[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($this->has($className)) {
                    $resolved[] = $this->get($className);
                    continue;
                }
            }

            if ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();
                continue;
            }

            if ($param->allowsNull()) {
                $resolved[] = null;
                continue;
            }

            throw new ContainerException("Missing required argument [\${$name}].");
        }

        return $resolved;
    }
}

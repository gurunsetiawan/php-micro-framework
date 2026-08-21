<?php

declare(strict_types=1);

namespace Micro\Tests\Unit;

use Micro\Container\Container;
use Micro\Container\ContainerException;
use Micro\Container\NotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

interface TestServiceInterface
{
    public function getValue(): string;
}

class TestServiceConcrete implements TestServiceInterface
{
    public function getValue(): string
    {
        return 'test-value';
    }
}

class TestDependentService
{
    public function __construct(public TestServiceInterface $service)
    {
    }
}

class TestServiceWithDefault
{
    public function __construct(public string $value = 'default')
    {
    }
}

class TestPrimitiveUnbound
{
    public function __construct(public string $value)
    {
    }
}

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testExplicitBindingAndFactoryResolution(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceConcrete::class);

        $service = $this->container->get(TestServiceInterface::class);

        $this->assertInstanceOf(TestServiceConcrete::class, $service);
        $this->assertSame('test-value', $service->getValue());

        // Factory returns a new instance each time
        $service2 = $this->container->get(TestServiceInterface::class);
        $this->assertNotSame($service, $service2);
    }

    public function testSingletonRegistration(): void
    {
        $this->container->singleton(TestServiceInterface::class, TestServiceConcrete::class);

        $instance1 = $this->container->get(TestServiceInterface::class);
        $instance2 = $this->container->get(TestServiceInterface::class);

        $this->assertSame($instance1, $instance2);
    }

    public function testInstanceRegistration(): void
    {
        $obj = new stdClass();
        $obj->key = 'val';

        $this->container->instance('custom_object', $obj);

        $this->assertTrue($this->container->has('custom_object'));
        $this->assertSame($obj, $this->container->get('custom_object'));
    }

    public function testConcreteAutowiring(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceConcrete::class);

        $dependent = $this->container->get(TestDependentService::class);

        $this->assertInstanceOf(TestDependentService::class, $dependent);
        $this->assertInstanceOf(TestServiceConcrete::class, $dependent->service);
    }

    public function testDefaultParameterHandling(): void
    {
        $service = $this->container->get(TestServiceWithDefault::class);

        $this->assertInstanceOf(TestServiceWithDefault::class, $service);
        $this->assertSame('default', $service->value);
    }

    public function testUnresolvablePrimitiveThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Cannot resolve un-typed or primitive parameter [\$value] in class [Micro\Tests\Unit\TestPrimitiveUnbound].");

        $this->container->get(TestPrimitiveUnbound::class);
    }

    public function testUnboundInterfaceThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get(TestServiceInterface::class);
    }

    public function testCircularDependencyDetection(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->get(CircularA::class);
    }

    public function testCallableInvocationWithCall(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceConcrete::class);

        $result = $this->container->call(function (TestServiceInterface $service, string $suffix = '!'): string {
            return $service->getValue() . $suffix;
        }, ['suffix' => '??']);

        $this->assertSame('test-value??', $result);
    }
}

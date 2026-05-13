<?php

declare(strict_types=1);

namespace Jb\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Register a factory for a class or service id.
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Register an already constructed singleton instance.
     */
    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    /**
     * Resolve a class or service id from the container.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            return $this->instances[$id] = ($this->bindings[$id])($this);
        }

        return $this->instances[$id] = $this->build($id);
    }

    /**
     * Check whether the container has a binding or singleton instance.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || array_key_exists($id, $this->instances);
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new HttpException("Service [$class] is not resolvable.", 500);
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new HttpException("Cannot resolve parameter [{$parameter->getName()}].", 500);
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}

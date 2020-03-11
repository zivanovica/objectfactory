<?php


namespace ObjectFactory;

class Factory
{
    /** @var array Data that are require to provide proper object instances. */
    private static array $interfaceDefinitions = [];

    /** @var array Tree used to determine circular dependency between objects. */
    private static array $instantiationDependencyTree = [];

    /**
     * Retrieve new instance correlated to provided interface/class name.
     *
     * @param string $interface Name of interface/class
     * @param bool $preventClassRegistration Flag that determines whether or not script try to register class name.
     * @return object
     *
     * @throws \ReflectionException
     */
    public static function getInstance(string $interface, bool $preventClassRegistration = false): object
    {
        if (static::$instantiationDependencyTree[$interface] ?? false) {
            throw new \RuntimeException("Circular dependency on {$interface}");
        }

        $interfaceData = static::$interfaceDefinitions[$interface] ?? null;

        if (empty($interfaceData) && class_exists($interface) && false === $preventClassRegistration) {
            static::registerInterfaceClass($interface, $interface);

            return static::getInstance($interface, true);
        } if (empty($interfaceData)) {
            throw new \RuntimeException("Interface {$interface} not registered.");
        }

        $reflection = $interfaceData['classReflection'] ?? null;
        $instanceProvider = $interfaceData['instanceProvider'] ?? null;

        if (false === $reflection instanceof \ReflectionClass && false === is_callable($instanceProvider)) {
            throw new \RuntimeException("There is no proper instance provider for {$interface}");
        }

        static::$instantiationDependencyTree[$interface] = true;

        $results = is_callable($instanceProvider) ? $instanceProvider() : $reflection
            ->newInstanceArgs(static::buildConstructorDependencies($interfaceData['arguments'] ?? []));

        unset(static::$instantiationDependencyTree[$interface]);

        return $results;
    }

    /**
     * Acts same as "getInstance" only difference is that provided object is singleton.
     *
     * @param string $interface Name of requested interface/class
     * @return object
     * @throws \ReflectionException
     */
    public static function getSharedInstance(string $interface): object
    {
        $instance = static::$interfaceDefinitions[$interface]['sharedInstance'] ?? null;

        if ($instance) {
            return $instance;
        }

        static::$interfaceDefinitions[$interface]['sharedInstance'] = static::getInstance($interface);

        return static::$interfaceDefinitions[$interface]['sharedInstance'];
    }

    /**
     * Create map between interface and class.
     *
     * @param string $interface Interface name.
     * @param string $className Class name
     *
     * @throws \ReflectionException
     */
    public static function registerInterfaceClass(string $interface, string $className): void
    {
        $reflection = new \ReflectionClass($className);

        if (false === is_a($reflection->newInstanceWithoutConstructor(), $interface)) {
            throw new \RuntimeException("{$className} does not implement {$interface}");
        }

        static::$interfaceDefinitions[$interface] = [
            'classReflection' => $reflection,
            'arguments' => static::buildConstructorArguments($reflection),
        ];
    }

    /**
     * Create map between interface and object provided by executing given callback/
     *
     * @param string $interface Interface name.
     * @param callable $callback Object maker (provider) function.
     *
     * @throws \ReflectionException
     */
    public static function registerInterfaceInstanceProvider(string $interface, callable $callback): void
    {
        $reflection = new \ReflectionFunction($callback);
        $returnType = $reflection->getReturnType();

        if (false === $returnType instanceof \ReflectionNamedType) {
            throw new \RuntimeException("Instance provider for {$interface} must have return type");
        }

        $sharedInstance = $callback();

        if (false === is_a($sharedInstance, $interface)) {
            throw new \RuntimeException("Return value of provider doesn't implement {$interface}");
        }

        static::$interfaceDefinitions[$interface] = [
            'instanceProvider' => $callback,
            'sharedInstance' => $sharedInstance,
        ];
    }

    /**
     * Create array of instances used to be provided as dependency arguments.
     *
     * @param string[] $dependencies Names of classes for which object instance should be provided.
     *
     * @return object[]
     */
    private static function buildConstructorDependencies(array $dependencies): array
    {
        return array_map(fn(string $dependency) => static::getInstance($dependency), $dependencies);
    }

    /**
     * Create array of class names that represents class dependencies.
     *
     * @param \ReflectionClass $reflectionClass Reflection of class itself.
     * @return string[]
     */
    private static function buildConstructorArguments(\ReflectionClass $reflectionClass): array
    {
        if (null === $reflectionClass->getConstructor()) {
            return [];
        }

        $object = $reflectionClass->newInstanceWithoutConstructor();

        return array_map(function (\ReflectionParameter $parameter) use ($object) {
            $parameterType = $parameter->getType();

            if (false === $parameterType instanceof \ReflectionNamedType) {
                throw new \RuntimeException('Non-typed arguments are not supported. Use instance provider.');
            }

            $parameterTypeName = $parameterType->getName();
            $isClass = class_exists($parameterTypeName);

            if (false === ($isClass || interface_exists($parameterTypeName))) {
                throw new \RuntimeException("Invalid interface/class {$parameterType->getName()}");
            }

            if ($isClass && is_a($object, $parameterType->getName()) && false === is_subclass_of($object, $parameterType->getName())) {
                throw new \RuntimeException("Dependency {$parameterTypeName} cannot be self-referring");
            }

            return $parameterType->getName();
        }, $reflectionClass->getConstructor()->getParameters());
    }
}
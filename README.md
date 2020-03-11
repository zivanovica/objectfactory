# Object Factory
In this document you can find proper usage of this library.

### About
This library is used to easily retrieve instance of certain object without the need to provide all of its dependencies.

### Requirements
- PHP: >=7.4

### Overview
This library ensure to provide proper instance of object implementing certain interface, or being certain type of a class.
Dependencies are required to be complex type as primitive ones (string, int, float, bool) cannot be exactly auto determined.
There are two ways of defining "provider" for certain interface.


### Usage
First approach is providing simple map between interface and class itself, to do so we need to call ``registerInterfaceClass`` method.
```php

interface IModel {}
class Model implements IModel {}

\ObjectFactory\Factory::registerInterfaceClass(IModel::class, Model::class);

```
##### Note: Use this only when providing class has complex typed dependencies, providing class with primitive types will cause exception to be thrown.
##### Note: Library handle circular dependencies.

Another approach is using provider callback- function that is called when instance is requested, to achieve this we need to call ``registerInterfaceInstanceProvider``.
This approach is usually useful when object depends on primitive types.
```php

interface IDatabase {}
class MySQL implements IDatabase
{
    public function __constructor(string $host, string $username, string $password, string $schema) {}
}

class PostgreSQL implements IDatabase
{
    public function __constructor(string $host, string $username, string $password, string $schema, string $role = null) {}
}

\ObjectFactory\Factory::registerInterfaceInstanceProvider(IDatabase::class, function (): IDatabase {
    return new MySQL('localhost', 'root', '', 'database');
});
```

There are two ways to retrieve instance of certain type. One is ``getInstance`` which always returns new instance of requested type.

And there is also another way by using ``getSharedInstance`` which always return signleton instance of requested model.

```php

$nonShared = \ObjectFactory\Factory::getInstance(IModel::class);
$shared1 = \ObjectFactory\Factory::getSharedInstance(IModel::class);
$shared2 = \ObjectFactory\Factory::getSharedInstance(IModel::class);

var_dump($nonShared === $shared1, $shared1 === $shared2); // false, true will be the output.

```

# Autumn Framework

Autumn Framework is a micro service framework based on PECL/Swoole.

Autumn Framework是基于PECL/Swoole的微服务框架。



## Getting Started

1.Import:

1.导入：

```
composer require autumn/autumn-framework
```



2.Add your own namespace to composer.json (eg: Market):

2.在composer.json中加入自定义的项目名称空间（如：Market）：

```json
// ...
    "autoload": {
        "psr-4": {
            "Market\\": "src"
        }
    }
// ...
```



3.Create index.php:

3.编写index.php：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

exit(Autumn\Framework\Boot\AutumnApplication::run($argc, $argv));
```



4.Create model class (Plain Ordinary PHP Object, POPO):

4.创建模型类（简单的常规PHP类）：

```php
<?php

namespace Market;

class Car
{
    public $id;
}
```

Notice: we use [mintware-de/json-object-mapper](https://packagist.org/packages/mintware-de/json-object-mapper) as default JSON object mapper since 0.1.0, and `json_encode()` as fallback mapper.

注意：自0.1.0开始，我们使用[mintware-de/json-object-mapper](https://packagist.org/packages/mintware-de/json-object-mapper)作为默认的JSON对象序列化及反序列化工具。无法使用该代码包时（例如版权问题），框架使用`json_encode()`函数作为备选。



5.Create controller class (POPO):

5.创建控制器类（POPO）：

```php
<?php

namespace Market;

use \Autumn\Framework\Annotation\RestController;
use \Autumn\Framework\Annotation\RequestMapping;

/**
 * @RestController
 */
class CarController
{
    /**
     * @RequestMapping(value="/cars", method="GET")
     */
    public function list()
    {
        $cars = [];
        for ($i=1; $i<=10; ++$i) {
            $car = new Car();
            $car->id = $i;
            $cars[] = $car;
        }

        return $cars;
    }
}
```

Respond 10 cars when requesting "GET /cars".

接收"GET /cars"并响应10辆车的信息。



6.Launch service:

6.启动微服务：

```
php index.php
```



7.Enjoy:

```
curl -i http://localhost:3028/cars
```



## Define a bean and inject it into a property (since 0.1.0)

1.Create configuration class and define beans:

1.创建Configuration类，定义Bean：

```php
use \Autumn\Framework\Context\Annotation\Configuration;
use \Autumn\Framework\Context\Annotation\Bean;

/**
 * Main Configuration
 * 
 * @Configuration
 */
class DaTrieConfiguration
{
    /** @Bean */
    public function daTrieService()
    {
        // Implementation of interface DaTrieServer
        return new DaTrieServiceImpl();
    }
}
```



2.Inject the bean to a property:

2.注入到类属性上：

```php
use \Autumn\Framework\Annotation\RestController;
use \Autumn\Framework\Annotation\RequestMapping;
use \Autumn\Framework\Context\Annotation\Autowired;

/**
 * @RestController
 */
class SearchInController
{
    /** @Autowired(value=DaTrieService::class) */
    private $daTrieService;
}
```



## Extra Dependencies

- [PECL/Swoole](https://pecl.php.net/package/swoole)



## See

- [Autumn Example](https://github.com/Timandes/autumn-example)
- [Double-Array Trie Server](https://github.com/Timandes/datrie-server)
- [Spring](https://spring.io/)
- Spring in Action


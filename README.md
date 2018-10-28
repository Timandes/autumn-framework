# Autumn Framework
Autumn Framework is a micro service framework based on PECL/Swoole.
Autumn Framework是基于PECL/Swoole的微服务框架。


## Getting Started

1.Import:

1. 导入：

```
composer require autumn/autumn-framework
```


2.Add your own namespace to composer.json (eg: Market):

2.在composer.json中加入自定义的项目名称空间（如：Market）：

```json
// ...
    "autoload": {
        "psr-4": {
            "Market\\": "src/"
        }
    }
// ...
```


3.Create index.php:

3.编写index.php：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

exit(Autumn\Boot\AutumnApplication::run($argc, $argv));
```


4.Create model class (Plain Ordinary PHP Object):

4.创建模型类（简单的常规PHP类）：

```php
<?php

namespace Market;

class Car
{
    public $id;
}
```


5.Create controller class (POPO):

5.创建控制器类（POPO）：

```php
<?php

namespace Market;

use \Autumn\Annotation\RestController;
use \Autumn\Annotation\RequestMapping;

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


## See
* [Autumn Example](https://github.com/Timandes/autumn-framework)

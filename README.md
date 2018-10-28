# Autumn Framework
Autumn Framework is a micro service framework based on PECL/Swoole.
Autumn Framework是基于PECL/Swoole的微服务框架。

## Getting Started

Import:
导入：
```
composer require autumn/autumn-framework
```

Add your own namespace to composer.json (eg: Market):
在composer.json中加入自定义的项目名称空间（如：Market）：
```json
// ...
    "autoload": {
        "psr-4": {
            "Market\\": "src/"
        }
    }
// ...
```

Create index.php:
编写index.php：
```php
<?php

require __DIR__ . '/vendor/autoload.php';

exit(Autumn\Boot\AutumnApplication::run($argc, $argv));
```

Create model class (Plain Ordinary PHP Object):
创建模型类（简单的常规PHP类）：
```php
<?php

namespace Market;

class Car
{
    public $id;
}
```

Create controller class (POPO):
创建控制器类（POPO）：
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


Enjoy:
启动微服务：
```
php index.php
```


## See
* [Autumn Example](https://github.com/Timandes/autumn-framework)

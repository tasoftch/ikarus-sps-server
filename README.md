# Ikarus SPS Server

The server package of Ikarus SPS provides plugins to establish unix and/or tcp connections to communicate with a running sps.

#### Installation
````bin
$ composer require ikarus/sps-server
````

#### Usage
__Cyclic__
```php
<?php
use Ikarus\SPS\CyclicEngine;
use Ikarus\SPS\Server\Cyclic\ServerPlugin;

$sps = new CyclicEngine(2, 'Test SPS');
// add the plugins your need to run properly

$sps->addPlugin( new ServerPlugin('192.168.1.100', 8686) );
$sps->run();
```
__Triggered__
```php
<?php
use Ikarus\SPS\TriggeredEngine;
use Ikarus\SPS\Server\Trigger\ServerPlugin;

$sps = new TriggeredEngine('Test SPS');
// add the plugins your need to run properly

$sps->addPlugin( new ServerPlugin('192.168.1.100', 8686) );
// or local unix server
$sps->addPlugin( new ServerPlugin('/tmp/example-sps.sock') );
$sps->run();
```
Now any application will be able to call the sps via tcp://192.168.1.100 on port 8686.

Please note that we don't recommend to add more than one server plugin. The SPS would accept them but it might cause conflicts.
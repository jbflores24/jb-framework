<?php

declare(strict_types=1);

use Jb\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = (new Application(dirname(__DIR__)))->bootstrap();
$app->routes(dirname(__DIR__) . '/routes/api.php');
$app->run();

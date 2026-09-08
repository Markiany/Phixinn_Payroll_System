<?php

require __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Router;

$router = new Router();
require __DIR__ . '/../routes/web.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
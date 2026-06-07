<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Response;

require dirname(__DIR__) . '/app/bootstrap.php';

ob_start();
require dirname(__DIR__) . '/views/store/home.php';

Response::html((string) ob_get_clean());

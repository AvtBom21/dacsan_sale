<?php

declare(strict_types=1);

return [
    'name' => 'Đặc Sản Nhà Dân',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => (getenv('APP_DEBUG') ?: 'true') === 'true',
    'locale' => 'vi',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'base_url' => getenv('APP_URL') ?: '',
    'root_path' => dirname(__DIR__),
    'storage_path' => dirname(__DIR__) . '/storage',
];

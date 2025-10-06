<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'geo_photothek',
        'username' => 'geo_user',
        'password' => 'secret',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => 'http://localhost:8000',
        'upload_dir' => __DIR__ . '/../public/uploads',
        'original_upload_dir' => __DIR__ . '/../public/uploads/originals',
        'shared_dir' => __DIR__ . '/../shared',
        'max_upload_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/heic'],
        'display_max_dimension' => 1600,
    ],
];

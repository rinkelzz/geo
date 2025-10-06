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
        'upload_dir' => __DIR__ . '/../storage/uploads',
        'original_upload_dir' => __DIR__ . '/../storage/uploads/originals',
        'shared_dir' => __DIR__ . '/../shared',
        'max_upload_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/heic'],
        'display_max_dimension' => 1600,
        // Optional: Koordinaten des Zuhauses für die Distanzanzeige im Dashboard
        'home_latitude' => 48.137154,
        'home_longitude' => 11.576124,
    ],
    'mail' => [
        'from_address' => 'noreply@example.com',
        'from_name' => 'Geo Photothek',
        'admin_recipients' => [
            'admin@example.com',
        ],
    ],
];

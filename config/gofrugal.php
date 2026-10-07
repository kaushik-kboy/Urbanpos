<?php

return [
    'url' => env('GOFRUGAL_URL', 'https://urbanpets.true-pos.com/TruePOS/index.do'),
    'username' => env('GOFRUGAL_USERNAME', 'admin1'),
    'password' => env('GOFRUGAL_PASSWORD', 'Moni@31'),

    'reports' => [
        'daily_sales' => '110150',
        'sales_register' => '110116',
        'purchase_detail' => '110120',
        'stock_transfer' => '110282',
    ],

    'branches' => [
        'satellite' => [
            'id' => 2,
            'name' => 'Satellite',
            'code' => 'SAT',
        ],
        'motera' => [
            'id' => 3,
            'name' => 'Motera',
            'code' => 'MOT',
        ],
    ],

    'storage_path' => storage_path('app/gofrugal_sync'),
];

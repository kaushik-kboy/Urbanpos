<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smart Replenishment Module Toggle
    |--------------------------------------------------------------------------
    | Controlled by SMART_REPLENISHMENT_ENABLED in .env.
    | When false, all replenishment routes return 404 and menus are hidden.
    */
    'enabled' => env('SMART_REPLENISHMENT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default Velocity Calculation Window (in Days)
    |--------------------------------------------------------------------------
    */
    'velocity_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Stock Type Color Mappings
    |--------------------------------------------------------------------------
    */
    'colors' => [
        'Critical'  => 'danger',   // Red badge
        'Low'       => 'warning',  // Yellow / Amber badge
        'Healthy'   => 'success',  // Green badge
        'Overstock' => 'info',     // Blue badge
    ],
];


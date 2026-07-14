<?php

return [
    'enabled' => (bool) env('DEMO_MODE', false),
    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@ledgerflow.test'),
    'admin_password' => env('DEMO_ADMIN_PASSWORD', 'password'),
    'customer_email' => env('DEMO_CUSTOMER_EMAIL', 'customer@ledgerflow.test'),
    'customer_password' => env('DEMO_CUSTOMER_PASSWORD', 'password'),
];

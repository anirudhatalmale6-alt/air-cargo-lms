<?php

return [

    'company_name' => env('AIRCARGO_COMPANY', 'Air Cargo Logistics'),
    'company_short' => env('AIRCARGO_COMPANY_SHORT', 'AirCargo LMS'),

    'default_currency' => strtoupper(env('AIRCARGO_CURRENCY', 'USD')),

    'currencies' => array_map('strtoupper', array_filter(array_map(
        'trim',
        explode(',', env('AIRCARGO_CURRENCIES', 'USD,EUR,AED,INR,GBP,SGD'))
    ))),

    'documents' => [
        'disk' => env('AIRCARGO_DOC_DISK', 'local'),
        'max_kb' => (int) env('AIRCARGO_DOC_MAX_KB', 10240),
        'allowed' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'],
    ],

    // IATA volumetric divisor: 6000 cm3 per chargeable kg.
    'volumetric_factor' => 166.6667,

];
